<?php
/*
 * vpn_softether5.php
 *
 * part of pfSense (https://pfsense.org)
 * Copyright (c) 2011-2024 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 */

require_once("guiconfig.inc");

$pgtitle = array("SoftEther", "VPN Manager");

$reset_msg       = '';
$vpnbridge_status = 'stopped';
$vpnserver_status = 'stopped';

if (function_exists('exec')) {
	@exec('/usr/sbin/service vpnbridge status 2>/dev/null', $output_vpnb, $ret_vpnb);
	$vpnbridge_status = ($ret_vpnb === 0) ? 'running' : 'stopped';

	@exec('/usr/sbin/service vpnserver status 2>/dev/null', $output_vpns, $ret_vpns);
	$vpnserver_status = ($ret_vpns === 0) ? 'running' : 'stopped';
}

if (!empty($_POST['action']) && isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
	$targets = [
		'reset_bridge_config' => ['vpnbridge', '/cf/conf/softether/vpn_bridge*', '/var/db/softether/vpn_bridge*', 'VPN Bridge'],
		'reset_server_config' => ['vpnserver', '/cf/conf/softether/vpn_server*', '/var/db/softether/vpn_server*', 'VPN Server'],
	];
	$action = $_POST['action'];
	if (isset($targets[$action])) {
		[$svc, $conf1, $conf2, $label] = $targets[$action];
		exec("/usr/sbin/service {$svc} stop 2>/dev/null &");
		sleep(1);
		@shell_exec("rm -f {$conf1} 2>/dev/null");
		@shell_exec("rm -f {$conf2} 2>/dev/null");
		$reset_msg = "{$label} configuration reset successfully.";
		${"{$svc}_status"} = 'stopped'; // $vpnbridge_status / $vpnserver_status
	}
}

include("head.inc");
?>
<style>
/* ── Spacing / sizing only — NO hardcoded colors (theme provides them) ── */

/* form-control uniform size */
.se-page .form-control,
.se-page select.form-control,
.se-page input.form-control,
.se-page textarea.form-control {
    height: 30px;
    padding: 4px 8px;
    font-size: 13px;
}
.se-page textarea.form-control { height: auto; }

/* form-group rhythm */
.se-page .form-horizontal .form-group,
.se-page .form-group { margin-bottom: 10px; }
.se-page .form-horizontal .control-label { padding-top: 5px; font-size: 13px; font-weight: 600; }

/* buttons */
.se-page .btn            { font-size: 12px; padding: 4px 10px; line-height: 1.5; }
.se-page .btn-xs         { font-size: 11px; padding: 2px 7px; }
.se-page .btn    i.fa    { margin-right: 3px; }
.se-page .btn-xs i.fa    { margin-right: 2px; }

/* disabled buttons */
.btn[disabled],
.btn[disabled]:hover,
.btn[disabled]:focus,
.btn[disabled]:active {
    pointer-events: none;
    opacity: 0.55;
    box-shadow: none;
    cursor: not-allowed;
}

/* data tables */
.se-page .table-condensed > thead > tr > th,
.se-page .table-condensed > tbody > tr > td {
    padding: 5px 8px;
    font-size: 13px;
    vertical-align: middle;
}
.se-page .table-condensed > thead > tr > th {
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .02em;
    border-top: none;
}

/* key-value info tables */
.se-kv-table > tbody > tr > td { padding: 5px 8px; vertical-align: top; }
.se-kv-table > tbody > tr > td:first-child {
    width: 38%;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .02em;
    white-space: nowrap;
    padding-right: 12px;
}
.se-kv-table > tbody > tr > td:last-child { font-size: 13px; word-break: break-all; }
.se-kv-table > tbody > tr:first-child > td { border-top: none; }

/* panels */
.se-page .panel              { margin-bottom: 14px; }
.se-page .panel-heading      { padding: 8px 14px; }
.se-page .panel-heading .panel-title { font-size: 13px; font-weight: 700; line-height: 1.4; }
.se-page .panel-body         { padding: 14px; }
.se-page .panel-body.form-horizontal { padding: 14px 14px 4px; }

/* nav-tabs sizing */
.se-page .nav-tabs > li > a               { font-size: 13px; padding: 7px 14px; }
.se-page .nav-tabs > li.active > a,
.se-page .nav-tabs > li.active > a:hover,
.se-page .nav-tabs > li.active > a:focus  { font-weight: 600; }

/* sub-tab content frames — borders applied by JS from theme */
#srvSubTabContent,
#hubTabContent {
    border-style: solid;
    border-width: 0 1px 1px 1px;
    padding: 16px 16px 10px;
    border-radius: 0 0 4px 4px;
    margin-bottom: 15px;
}

/* hub bar */
.softether-hub-bar.panel > .panel-body { padding: 6px 12px; }
.softether-hub-tabs { margin-bottom: 0 !important; }

/* secureNAT toolbar — border color applied by JS */
#secureNATControls {
    padding: 6px 10px;
    margin-bottom: 10px;
    border-radius: 4px;
    border-style: solid;
    border-width: 1px;
}

/* panel titles with a right-aligned action button */
.se-title-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* section headers — border-bottom color applied by JS */
.se-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    margin-bottom: 8px;
    border-bottom-style: solid;
    border-bottom-width: 1px;
}
.se-section-header strong { font-size: 13px; }

/* misc sizing */
.se-page .label             { font-size: 11px; padding: 2px 6px; }
.se-page nav.action-buttons { margin-bottom: 14px; }
.se-page .bs-callout        { padding: 8px 14px; margin-bottom: 12px; }
.se-page .bs-callout p:last-child { margin-bottom: 0; }

/* modals */
.modal .modal-header { padding: 12px 16px; }
.modal .modal-title  { font-size: 14px; font-weight: 700; }
.modal .modal-body   { padding: 16px; }
.modal .modal-footer { padding: 10px 16px; border-top-width: 1px; }
.modal .form-horizontal .form-group  { margin-bottom: 8px; }
.modal .form-horizontal .control-label { padding-top: 5px; font-size: 13px; font-weight: 600; }
.modal .form-control  { height: 30px; padding: 4px 8px; font-size: 13px; }
.modal textarea.form-control { height: auto; }
.modal .panel > .panel-heading { padding: 6px 12px; font-size: 13px; font-weight: 700; }
.modal .panel > .panel-body   { padding: 10px 12px; }
.modal .panel { margin-bottom: 10px; }
.modal hr { margin: 10px 0; }
.modal .btn { font-size: 13px; }
.modal label { font-weight: 600; font-size: 13px; }
.modal .form-group label { font-weight: normal; }
.modal .form-group label.control-label { font-weight: 600; }
.modal .form-group label input[type=checkbox],
.modal .form-group label input[type=radio] { margin-right: 5px; }
/* page-level panel headings: consistent height and font */
.se-page .panel-default > .panel-heading { padding: 8px 14px; }
.se-page .panel-default > .panel-heading h2.panel-title,
.se-page .panel-default > .panel-heading h4.panel-title { font-size: 13px; font-weight: 700; line-height: 1.4; margin: 0; }
/* flush panel bodies (padding:0) should have table borders removed at top */
.se-page .panel-body[style*="padding:0"] > .table > thead > tr > th:first-child,
.se-page .panel-body[style*="padding:0"] > .table > tbody > tr:first-child > td { border-top: none; }
/* inline form-group-sm in panels */
.se-page .form-group-sm .form-control { height: 28px; padding: 3px 8px; font-size: 12px; }
.se-page .form-group-sm label { font-size: 12px; }

/* checkbox labels */
.se-page .form-group label input[type=checkbox] { margin-right: 5px; }
.se-page .form-group label { font-weight: normal; font-size: 13px; }

/* nav-tabs inside panel-heading (Sessions / NAT tabs) */
.se-page .panel-heading .panel-tabs { margin: -8px -14px; }
.se-page .panel-heading .panel-tabs > li > a {
    padding: 8px 14px;
    border: none;
    border-radius: 0;
    background: transparent;
    font-size: 13px;
}
.se-page .panel-heading .panel-tabs > li.active > a {
    font-weight: 600;
    border-bottom: 2px solid currentColor;
    background: transparent;
}

/* disabled tabs */
#tabNavServer.disabled > a,
#tabNavHub.disabled > a {
    opacity: 0.5;
    cursor: not-allowed !important;
    pointer-events: none;
}
</style>
<script>
/* Read border/text colors from the live pfSense theme and apply to custom elements */
document.addEventListener('DOMContentLoaded', function() {
	var cs = window.getComputedStyle;

	/* Reference elements Bootstrap/pfSense themes already style */
	var navTabs    = document.querySelector('.nav-tabs');
	var panelRef   = document.querySelector('.panel-default');
	var tableRef   = document.querySelector('.table');
	var bodyEl     = document.body;

	var borderColor   = navTabs  ? cs(navTabs).borderBottomColor  : '';
	var panelBorder   = panelRef ? cs(panelRef).borderColor        : borderColor;
	var textMuted     = bodyEl   ? cs(bodyEl).color                : '';

	function applyBorder(el, color) {
		if (!el || !color) { return; }
		el.style.borderColor = color;
	}
	function applyBorderBottom(el, color) {
		if (!el || !color) { return; }
		el.style.borderBottomColor = color;
	}

	/* Sub-tab content frames */
	['hubTabContent','srvSubTabContent'].forEach(function(id) {
		var el = document.getElementById(id);
		if (el && borderColor) {
			el.style.borderLeftColor = el.style.borderRightColor = el.style.borderBottomColor = borderColor;
		}
	});

	/* Section-header dividers */
	document.querySelectorAll('.se-section-header').forEach(function(el) {
		applyBorderBottom(el, borderColor);
	});

	/* SecureNAT toolbar */
	applyBorder(document.getElementById('secureNATControls'), panelBorder);

	/* Policy panel split border */
	['newGroupPolicyListWrap','editGroupPolicyListWrap'].forEach(function(id) {
		var el = document.getElementById(id);
		if (el && panelBorder) { el.style.borderRightColor = panelBorder; }
	});

	/* kv-table row separators — match table border color from theme */
	var tableBorder = '';
	if (tableRef) {
		var td = tableRef.querySelector('td,th');
		if (td) { tableBorder = cs(td).borderTopColor; }
	}
	if (tableBorder) {
		var sheet = document.createElement('style');
		sheet.textContent =
			'.se-kv-table > tbody > tr > td { border-top-color: ' + tableBorder + '; }';
		document.head.appendChild(sheet);
	}
});
</script>

<div class="se-page">
<div class="bs-callout bs-callout-danger" style="margin-bottom:15px;">
	<h4><?=gettext("Caution!")?></h4>
	<?=gettext("The pfSense SoftEther package is under development.")?>
	<?=gettext("For missing configurations use the")?> <b><?=gettext("SoftEther VPN Server Manager")?></b> <?=gettext("tool from the official")?> <a href="https://softether.org" target="_blank">SoftEther</a> <?=gettext("website.")?>
</div>

<?php if (!empty($reset_msg)): ?>
<div class="bs-callout bs-callout-success" style="margin-bottom:15px;">
	<i class="fa fa-check"></i> <?=htmlspecialchars($reset_msg)?>
</div>
<?php endif; ?>

<!-- API Access -->
<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("API Access")?></h2></div>
	<div class="panel-body">
		<!-- form autocomplete=off is the only cross-browser way to suppress the credential-save prompt -->
		<form method="post" autocomplete="off" onsubmit="return false;" style="margin:0;">
		<!-- Dummy hidden fields fool browsers that ignore autocomplete=off on visible inputs -->
		<input type="text"     style="display:none;" aria-hidden="true" tabindex="-1">
		<input type="password" style="display:none;" aria-hidden="true" tabindex="-1">
		<div class="form-horizontal">
			<div class="form-group">
				<label class="col-sm-2 control-label"><?=gettext("Username")?></label>
				<div class="col-sm-4">
					<input type="text" id="apiUser" class="form-control" value="Administrator"
						autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false">
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label"><?=gettext("Password")?></label>
				<div class="col-sm-4">
					<input type="password" id="apiPass" class="form-control" placeholder="Administrator Password"
						autocomplete="new-password" autocapitalize="off" autocorrect="off" spellcheck="false"
						data-lpignore="true" data-1p-ignore="true" data-form-type="other">
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-2 col-sm-10">
					<button type="button" id="connectBtn" class="btn btn-sm btn-primary">
						<i class="fa fa-plug"></i> <?=gettext("Connect")?>
					</button>
					<button type="button" id="disconnectBtn" class="btn btn-sm btn-default" onclick="disconnectApp()" style="display:none;margin-left:6px;">
						<i class="fa fa-times"></i> <?=gettext("Disconnect")?>
					</button>
					<span id="connectStatus" class="label label-default" style="margin-left:8px;font-size:12px;vertical-align:middle;">Not connected</span>
				</div>
			</div>
		</div>
		</form>
	</div>
</div>

<!-- TOP-LEVEL TABS -->
<ul class="nav nav-tabs" role="tablist" style="margin-bottom:15px;">
	<li role="presentation" class="active"><a href="#tabStatus" role="tab" data-toggle="tab"><i class="fa fa-info-circle"></i> <?=gettext("Status")?></a></li>
	<li role="presentation" id="tabNavServer"><a href="#tabServer" role="tab" data-toggle="tab"><i class="fa fa-cog"></i> <?=gettext("Server Settings")?></a></li>
	<li role="presentation" id="tabNavHub"><a href="#tabHub" role="tab" data-toggle="tab"><i class="fa fa-server"></i> <?=gettext("Hub")?></a></li>
	<li role="presentation"><a href="#tabMaintenance" role="tab" data-toggle="tab"><i class="fa fa-wrench"></i> <?=gettext("Maintenance")?></a></li>
</ul>

<div class="tab-content">

	<!-- TAB: SERVER SETTINGS -->
	<div role="tabpanel" class="tab-pane" id="tabServer">
		<div id="srvNotConnected" class="alert alert-warning">
			<i class="fa fa-exclamation-triangle"></i> <?=gettext("Please connect to the API using the credentials above.")?>
		</div>
		<div id="srvPanel" style="display:none;">
			<!-- Server Settings sub-tabs -->
			<ul class="nav nav-tabs" role="tablist" style="margin-bottom:0;" id="srvSubTabs">
				<li role="presentation" class="active"><a href="#srvTabGeneral"     role="tab" data-toggle="tab"><i class="fa fa-info-circle"></i> <?=gettext("General")?></a></li>
				<li role="presentation"><a href="#srvTabAdmin"       role="tab" data-toggle="tab"><i class="fa fa-lock"></i> <?=gettext("Administration")?></a></li>
				<li role="presentation"><a href="#srvTabProtocols"  role="tab" data-toggle="tab"><i class="fa fa-exchange"></i> <?=gettext("Protocols")?></a></li>
				<li role="presentation"><a href="#srvTabNetwork"    role="tab" data-toggle="tab"><i class="fa fa-sitemap"></i> <?=gettext("Network")?></a></li>
				<li role="presentation"><a href="#srvTabCertificate" role="tab" data-toggle="tab"><i class="fa fa-certificate"></i> <?=gettext("Certificate")?></a></li>
				<li role="presentation"><a href="#srvTabServices"   role="tab" data-toggle="tab"><i class="fa fa-cloud"></i> <?=gettext("Services")?></a></li>
			</ul>
			<div class="tab-content" id="srvSubTabContent" style="border:1px solid #ddd;border-top:none;padding:15px 15px 10px;border-radius:0 0 4px 4px;margin-bottom:15px;">

				<!-- GENERAL: Server Info + Admin Password + SSL Cipher -->
				<div role="tabpanel" class="tab-pane active" id="srvTabGeneral">
					<div class="panel panel-default">
						<div class="panel-heading"><h2 class="panel-title"><?=gettext("Server Information")?></h2></div>
						<div class="panel-body">
							<table class="table table-condensed se-kv-table" style="margin-bottom:0;">
								<tbody id="srvInfo"></tbody>
							</table>
						</div>
					</div>
				</div><!-- /srvTabGeneral -->

				<!-- ADMINISTRATION: Admin Password + SSL Cipher -->
				<div role="tabpanel" class="tab-pane" id="srvTabAdmin">
					<div class="row">
						<div class="col-sm-6">
							<div class="panel panel-default">
								<div class="panel-heading"><h2 class="panel-title"><?=gettext("Administrator Password")?></h2></div>
								<div class="panel-body form-horizontal">
									<div class="form-group">
										<label class="col-sm-4 control-label"><?=gettext("New Password")?></label>
										<div class="col-sm-8"><input type="password" id="newServerPassword" class="form-control" autocomplete="new-password"></div>
									</div>
									<div class="form-group">
										<label class="col-sm-4 control-label"><?=gettext("Confirm Password")?></label>
										<div class="col-sm-8"><input type="password" id="newServerPasswordConfirm" class="form-control" autocomplete="new-password"></div>
									</div>
									<div class="form-group">
										<div class="col-sm-offset-4 col-sm-8">
											<button type="button" class="btn btn-sm btn-primary" onclick="saveServerConfig()"><i class="fa-solid fa-save"></i> <?=gettext("Save")?></button>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="col-sm-6">
							<div class="panel panel-default">
								<div class="panel-heading"><h2 class="panel-title"><?=gettext("SSL Cipher")?></h2></div>
								<div class="panel-body form-horizontal">
									<div class="form-group form-group-sm">
										<label class="col-sm-4 control-label"><?=gettext("Algorithm")?></label>
										<div class="col-sm-8">
											<select id="sslCipher" class="form-control">
												<optgroup label="TLS 1.3 / AEAD (Recommended)">
													<option>ECDHE-RSA-AES128-GCM-SHA256</option>
													<option>ECDHE-RSA-AES256-GCM-SHA384</option>
													<option>ECDHE-RSA-CHACHA20-POLY1305</option>
													<option>DHE-RSA-CHACHA20-POLY1305</option>
												</optgroup>
												<optgroup label="ECDHE (TLS 1.2)">
													<option>ECDHE-RSA-AES128-SHA256</option>
													<option>ECDHE-RSA-AES256-SHA384</option>
													<option>ECDHE-RSA-AES128-SHA</option>
													<option>ECDHE-RSA-AES256-SHA</option>
												</optgroup>
												<optgroup label="DHE (TLS 1.2)">
													<option>DHE-RSA-AES128-SHA256</option>
													<option>DHE-RSA-AES256-SHA256</option>
													<option>DHE-RSA-AES128-SHA</option>
													<option>DHE-RSA-AES256-SHA</option>
												</optgroup>
												<optgroup label="RSA Static">
													<option>AES128-SHA256</option>
													<option>AES256-SHA256</option>
													<option>AES128-SHA</option>
													<option>AES256-SHA</option>
													<option>DES-CBC3-SHA</option>
												</optgroup>
												<optgroup label="Legacy (Insecure)">
													<option>RC4-SHA</option>
													<option>RC4-MD5</option>
												</optgroup>
											</select>
										</div>
									</div>
									<div class="form-group form-group-sm">
										<div class="col-sm-offset-4 col-sm-8"><button class="btn btn-sm btn-primary" onclick="saveServerCipher()"><i class="fa-solid fa-save"></i> <?=gettext("Save")?></button></div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- PROTOCOLS: L2TP/IPsec + OpenVPN/SSTP + VPN Azure/ICMP/DNS -->
				<div role="tabpanel" class="tab-pane" id="srvTabProtocols">
					<div class="row">
						<div class="col-sm-6">
							<div class="panel panel-default">
								<div class="panel-heading"><h2 class="panel-title"><?=gettext("L2TP / IPsec")?></h2></div>
								<div class="panel-body form-horizontal">
									<div class="form-group">
										<div class="col-sm-12"><label><input type="checkbox" id="l2tp_ipsec_on"> <?=gettext("Enable L2TP over IPsec")?></label></div>
									</div>
									<div class="form-group">
										<div class="col-sm-12"><label><input type="checkbox" id="l2tp_raw_on"> <?=gettext("Enable Raw L2TP (no encryption)")?></label></div>
									</div>
									<div class="form-group">
										<div class="col-sm-12"><label><input type="checkbox" id="etherip_on"> <?=gettext("Enable EtherIP / L2TPv3 over IPsec")?></label></div>
									</div>
									<div class="form-group form-group-sm">
										<label class="col-sm-4 control-label"><?=gettext("IPsec PSK")?></label>
										<div class="col-sm-8"><input type="text" id="ipsec_psk" class="form-control"></div>
									</div>
									<div class="form-group form-group-sm">
										<label class="col-sm-4 control-label"><?=gettext("Default Hub")?></label>
										<div class="col-sm-8"><select id="ipsecDefaultHub" class="form-control"></select></div>
									</div>
									<div class="form-group form-group-sm">
										<div class="col-sm-offset-4 col-sm-8"><button type="button" class="btn btn-sm btn-primary" onclick="saveIpsec()"><i class="fa-solid fa-save"></i> <?=gettext("Save")?></button></div>
									</div>
								</div>
							</div>
						</div>
						<div class="col-sm-6">
							<div class="panel panel-default">
								<div class="panel-heading"><h2 class="panel-title"><?=gettext("OpenVPN / SSTP")?></h2></div>
								<div class="panel-body form-horizontal">
									<div class="form-group">
										<div class="col-sm-12"><label><input type="checkbox" id="openvpn_on"> <?=gettext("Enable OpenVPN (UDP 1194)")?></label></div>
									</div>
									<div class="form-group">
										<div class="col-sm-12"><label><input type="checkbox" id="sstp_on"> <?=gettext("Enable Microsoft SSTP (TCP 443)")?></label></div>
									</div>
									<div class="form-group form-group-sm">
										<label class="col-sm-4 control-label"><?=gettext("OpenVPN UDP Ports")?></label>
										<div class="col-sm-8"><input type="text" id="openvpn_ports" class="form-control" placeholder="1194,1195"></div>
									</div>
									<div class="form-group">
										<div class="col-sm-offset-4 col-sm-8">
											<button type="button" class="btn btn-sm btn-primary" onclick="saveOpenVpnSstp()"><i class="fa-solid fa-save"></i> <?=gettext("Save")?></button>
											<button type="button" class="btn btn-sm btn-default" onclick="downloadOpenVpnConfig()" style="margin-left:4px;" title="<?=gettext("Generate and download sample OpenVPN client config ZIP")?>"><i class="fa-solid fa-download icon-embed-btn"></i> <?=gettext("Client Config")?></button>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-sm-6">
							<div class="panel panel-default">
								<div class="panel-heading"><h2 class="panel-title"><?=gettext("VPN Azure / ICMP / DNS")?></h2></div>
								<div class="panel-body form-horizontal" id="azureIcmpPanel">
									<div id="azureIcmpUnsupportedMsg" style="display:none;" class="text-muted small" style="margin-bottom:8px;"><i class="fa fa-info-circle"></i> <?=gettext("VPN Azure and VPN over ICMP/DNS are not supported by this SoftEther build.")?></div>
									<div class="form-group form-group-sm">
										<div class="col-sm-12"><label><input type="checkbox" id="azureEnabled"> <?=gettext("Enable VPN Azure")?></label></div>
									</div>
									<div id="azureStatus" style="margin:0 0 8px 15px;"></div>
									<div class="form-group form-group-sm">
										<div class="col-sm-12"><label><input type="checkbox" id="icmpEnabled"> <?=gettext("Enable VPN over ICMP")?></label></div>
									</div>
									<div class="form-group form-group-sm">
										<div class="col-sm-12"><label><input type="checkbox" id="dnsEnabled"> <?=gettext("Enable VPN over DNS")?></label></div>
									</div>
									<div class="form-group form-group-sm">
										<div class="col-sm-offset-4 col-sm-8"><button id="azureIcmpSaveBtn" class="btn btn-sm btn-primary" onclick="saveAzureIcmpDns()"><i class="fa-solid fa-save"></i> <?=gettext("Save")?></button></div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- NETWORK: Listeners + Local Bridge/TAP + L3 Switches + EtherIP -->
				<div role="tabpanel" class="tab-pane" id="srvTabNetwork">
					<div class="bs-callout bs-callout-warning" style="margin-top:0;">
						<b><?=gettext("Be careful:")?></b> <?=gettext("if you need to use port 443 for VPN, change the pfSense default WebGUI port first.")?> <a href="/system_advanced_admin.php"><b><?=gettext("(System > Advanced > Admin Access)")?></b></a>
					</div>
					<div class="panel panel-default">
						<div class="panel-heading">
							<h2 class="panel-title se-title-flex">
								<span><i class="fa fa-plug"></i> <?=gettext("Ports / Listeners")?></span>
								<button type="button" class="btn btn-xs btn-success" data-toggle="modal" data-target="#createListenerModal"><i class="fa fa-plus"></i> <?=gettext("Add")?></button>
							</h2>
						</div>
						<div class="panel-body" style="padding:0;">
							<table class="table table-striped table-condensed" style="margin-bottom:0;">
								<thead><tr><th><?=gettext("Port")?></th><th><?=gettext("Status")?></th><th><?=gettext("Actions")?></th></tr></thead>
								<tbody id="listenerTable"></tbody>
							</table>
						</div>
					</div>
					<div class="panel panel-default">
						<div class="panel-heading">
							<h2 class="panel-title se-title-flex">
								<span><i class="fa fa-ethernet"></i> <?=gettext("Interfaces / TAP / Local Bridge")?></span>
								<button type="button" class="btn btn-xs btn-success" data-toggle="modal" data-target="#createTapModal"><i class="fa fa-plus"></i> <?=gettext("Add")?></button>
							</h2>
						</div>
						<div class="panel-body" style="padding:0;">
							<table class="table table-striped table-condensed" style="margin-bottom:0;">
								<thead><tr><th><?=gettext("Status")?></th><th><?=gettext("Virtual Hub Name")?></th><th><?=gettext("Network Adapter / TAP Device")?></th><th><?=gettext("Type")?></th><th><?=gettext("Actions")?></th></tr></thead>
								<tbody id="ifTable"></tbody>
							</table>
						</div>
					</div>
					<div class="panel panel-default">
						<div class="panel-heading">
							<h2 class="panel-title se-title-flex">
								<span><i class="fa fa-random"></i> <?=gettext("Virtual L3 Switches")?></span>
								<button type="button" class="btn btn-xs btn-success" data-toggle="modal" data-target="#createL3Modal"><i class="fa fa-plus"></i> <?=gettext("Add")?></button>
							</h2>
						</div>
						<div class="panel-body" style="padding:0;">
							<table class="table table-condensed table-striped" style="margin-bottom:0;">
								<thead><tr><th><?=gettext("Name")?></th><th><?=gettext("Status")?></th><th><?=gettext("Interfaces")?></th><th style="width:110px;"><?=gettext("Actions")?></th></tr></thead>
								<tbody id="l3SwitchTable"><tr><td colspan="4" class="text-muted"><?=gettext("Loading...")?></td></tr></tbody>
							</table>
						</div>
					</div>
					<div class="panel panel-default">
						<div class="panel-heading">
							<h2 class="panel-title se-title-flex">
								<span><i class="fa fa-exchange"></i> <?=gettext("EtherIP / L2TPv3 Clients")?></span>
								<button type="button" class="btn btn-xs btn-success" data-toggle="modal" data-target="#addEtherIpModal"><i class="fa fa-plus"></i> <?=gettext("Add")?></button>
							</h2>
						</div>
						<div class="panel-body" style="padding:0;">
							<table class="table table-condensed table-striped" style="margin-bottom:0;">
								<thead><tr><th><?=gettext("Client ID")?></th><th><?=gettext("Hub")?></th><th><?=gettext("Username")?></th><th style="width:50px;"><?=gettext("Actions")?></th></tr></thead>
								<tbody id="etherIpTable"><tr><td colspan="4" class="text-muted"><?=gettext("Loading...")?></td></tr></tbody>
							</table>
						</div>
					</div>
				</div>

				<!-- CERTIFICATE: Server Certificate -->
				<div role="tabpanel" class="tab-pane" id="srvTabCertificate">
					<div class="panel panel-default">
						<div class="panel-heading">
							<h2 class="panel-title se-title-flex">
								<span><?=gettext("Server Certificate")?></span>
								<div>
									<button type="button" class="btn btn-xs btn-default" onclick="viewServerCert()" style="margin-right:4px;"><i class="fa fa-eye"></i> <?=gettext("View")?></button>
									<button type="button" class="btn btn-xs btn-default" onclick="exportServerCert()" style="margin-right:4px;"><i class="fa-solid fa-download icon-embed-btn"></i> <?=gettext("Export")?></button>
									<button type="button" class="btn btn-xs btn-default" data-toggle="modal" data-target="#newServerCertModal" onclick="initNewCertModal()" style="margin-right:4px;"><i class="fa fa-plus"></i> <?=gettext("New")?></button>
									<button type="button" class="btn btn-xs btn-primary" data-toggle="modal" data-target="#serverCertModal"><i class="fa fa-upload"></i> <?=gettext("Import")?></button>
								</div>
							</h2>
						</div>
						<div class="panel-body">
							<table class="table table-condensed se-kv-table" style="margin-bottom:0;"><tbody id="serverCertInfo"><tr><td class="text-muted"><?=gettext("Loading...")?></td></tr></tbody></table>
						</div>
					</div>
				</div>

				<!-- SERVICES: Syslog + Keep Alive + Dynamic DNS -->
				<div role="tabpanel" class="tab-pane" id="srvTabServices">
					<div class="row">
						<div class="col-sm-6">
							<div class="panel panel-default">
								<div class="panel-heading"><h2 class="panel-title"><?=gettext("Syslog")?></h2></div>
								<div class="panel-body form-horizontal">
									<div class="form-group form-group-sm">
										<label class="col-sm-4 control-label"><?=gettext("Type")?></label>
										<div class="col-sm-8">
											<select id="syslogType" class="form-control">
												<option value="0"><?=gettext("Disabled")?></option>
												<option value="1"><?=gettext("Server log only")?></option>
												<option value="2"><?=gettext("Security log + Server log")?></option>
												<option value="3"><?=gettext("All logs")?></option>
											</select>
										</div>
									</div>
									<div class="form-group form-group-sm">
										<label class="col-sm-4 control-label"><?=gettext("Hostname")?></label>
										<div class="col-sm-8"><input type="text" id="syslogHost" class="form-control" placeholder="syslog.example.com"></div>
									</div>
									<div class="form-group form-group-sm">
										<label class="col-sm-4 control-label"><?=gettext("Port")?></label>
										<div class="col-sm-8"><input type="number" id="syslogPort" class="form-control" value="514"></div>
									</div>
									<div class="form-group form-group-sm">
										<div class="col-sm-offset-4 col-sm-8">
											<button type="button" class="btn btn-sm btn-primary" onclick="saveSyslog()"><i class="fa-solid fa-save"></i> <?=gettext("Save")?></button>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="col-sm-6">
							<div class="panel panel-default">
								<div class="panel-heading"><h2 class="panel-title"><?=gettext("Keep Alive")?></h2></div>
								<div class="panel-body form-horizontal">
									<div class="form-group form-group-sm">
										<div class="col-sm-12"><label><input type="checkbox" id="keepEnabled"> <?=gettext("Enable Keep Alive")?></label></div>
									</div>
									<div class="form-group form-group-sm">
										<label class="col-sm-4 control-label"><?=gettext("Protocol")?></label>
										<div class="col-sm-8"><select id="keepProto" class="form-control"><option value="udp">UDP</option><option value="tcp">TCP</option></select></div>
									</div>
									<div class="form-group form-group-sm">
										<label class="col-sm-4 control-label"><?=gettext("Host")?></label>
										<div class="col-sm-8"><input type="text" id="keepHost" class="form-control" placeholder="ping.softether.com"></div>
									</div>
									<div class="form-group form-group-sm">
										<label class="col-sm-4 control-label"><?=gettext("Port")?></label>
										<div class="col-sm-8"><input type="number" id="keepPort" class="form-control" value="80"></div>
									</div>
									<div class="form-group form-group-sm">
										<label class="col-sm-4 control-label"><?=gettext("Interval (sec)")?></label>
										<div class="col-sm-8"><input type="number" id="keepInterval" class="form-control" value="50"></div>
									</div>
									<div class="form-group form-group-sm">
										<div class="col-sm-offset-4 col-sm-8"><button class="btn btn-sm btn-primary" onclick="saveKeepAlive()"><i class="fa-solid fa-save"></i> <?=gettext("Save")?></button></div>
									</div>
								</div>
							</div>
						</div>
					</div>

				<div id="ddnsPanel">
					<div class="row">
						<div class="col-sm-6">
							<div class="panel panel-default">
								<div class="panel-heading"><h2 class="panel-title"><i class="fa fa-globe"></i> <?=gettext("Dynamic DNS")?></h2></div>
								<div class="panel-body form-horizontal">
									<div class="form-group form-group-sm">
										<label class="col-sm-4 control-label"><?=gettext("Hostname")?></label>
										<div class="col-sm-6"><input type="text" id="ddnsHostname" class="form-control" placeholder="myhostname"></div>
										<div class="col-sm-2"><button id="ddnsSetBtn" class="btn btn-sm btn-primary" onclick="setDdnsHostname()"><i class="fa-solid fa-save"></i> <?=gettext("Set")?></button></div>
									</div>
									<div id="ddnsUnsupportedMsg" style="display:none;" class="form-group form-group-sm">
										<div class="col-sm-offset-4 col-sm-8 text-muted small"><i class="fa fa-info-circle"></i> <?=gettext("Dynamic DNS is not supported by this SoftEther build.")?></div>
									</div>
								</div>
								<div id="ddnsInfoWrap" style="display:none;">
									<div class="panel-body" style="padding:0;">
										<table class="table table-condensed se-kv-table" style="margin-bottom:0;">
											<tbody id="ddnsInfo"></tbody>
										</table>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div><!-- close srvTabServices -->

			</div><!-- /srvSubTabContent -->
		</div><!-- /srvPanel -->
	</div><!-- /tabServer -->

	<!-- TAB: HUB -->
	<div role="tabpanel" class="tab-pane" id="tabHub">
		<div id="hubNotConnected" class="alert alert-warning">
			<i class="fa fa-exclamation-triangle"></i> <?=gettext("Please connect to the server first using the Server Settings tab.")?>
		</div>
		<div id="dynamicArea" style="display:none;">

			<!-- Hub selector bar -->
			<div class="panel panel-default softether-hub-bar" style="margin-bottom:12px;">
				<div class="panel-body" style="padding:6px 12px;line-height:1;">
					<div style="display:flex;align-items:center;flex-wrap:wrap;gap:6px;">
						<label style="margin:0;font-weight:600;white-space:nowrap;line-height:30px;"><?=gettext("Hub:")?></label>
						<select id="hubSelector" class="form-control" style="height:30px;padding:4px 8px;font-size:13px;min-width:160px;width:auto;" onchange="refreshHubData()"></select>
						<span id="hubOnlineBadge" class="label" style="font-size:11px;padding:3px 7px;line-height:1.4;"></span>
						<div class="btn-group btn-group-sm" style="margin-left:4px;">
							<button class="btn btn-success" data-toggle="modal" data-target="#createHubModal" title="<?=gettext("Create Hub")?>"><i class="fa fa-plus"></i> <?=gettext("New")?></button>
							<button class="btn btn-danger" onclick="deleteHub()" title="<?=gettext("Delete current hub")?>"><i class="fa fa-trash"></i></button>
						</div>
						<div class="btn-group btn-group-sm">
							<button class="btn btn-default" onclick="setHubOnline(true)" title="<?=gettext("Set Online")?>"><i class="fa fa-play text-success"></i></button>
							<button class="btn btn-default" onclick="setHubOnline(false)" title="<?=gettext("Set Offline")?>"><i class="fa fa-stop text-warning"></i></button>
						</div>
						<div class="btn-group btn-group-sm">
							<button class="btn btn-default" data-toggle="modal" data-target="#hubPasswordModal"><i class="fa fa-key"></i> <?=gettext("Password")?></button>
							<button class="btn btn-default" onclick="openHubSettings()"><i class="fa fa-sliders"></i> <?=gettext("Settings")?></button>
						</div>
					</div>
				</div><!-- /panel-body -->
			</div><!-- /panel -->

			<!-- Hub inner tabs -->
			<ul class="nav nav-tabs softether-hub-tabs" role="tablist" style="margin-bottom:0;">
				<li role="presentation" class="active"><a href="#hubTabOverview"  role="tab" data-toggle="tab"><i class="fa fa-tachometer"></i>  <?=gettext("Overview")?></a></li>
				<li role="presentation"><a href="#hubTabUsers"    role="tab" data-toggle="tab"><i class="fa fa-users"></i>                       <?=gettext("Users &amp; Groups")?></a></li>
				<li role="presentation"><a href="#hubTabSessions" role="tab" data-toggle="tab"><i class="fa fa-plug"></i>                        <?=gettext("Sessions")?></a></li>
				<li role="presentation"><a href="#hubTabNatDhcp"  role="tab" data-toggle="tab"><i class="fa fa-random"></i>                     <?=gettext("NAT &amp; DHCP")?></a></li>
				<li role="presentation"><a href="#hubTabSecurity" role="tab" data-toggle="tab"><i class="fa fa-shield"></i>                      <?=gettext("Security")?></a></li>
				<li role="presentation"><a href="#hubTabCascade"  role="tab" data-toggle="tab"><i class="fa fa-code-fork"></i>                  <?=gettext("Cascade")?></a></li>
				<li role="presentation"><a href="#hubTabSettings" role="tab" data-toggle="tab"><i class="fa fa-cog"></i>                        <?=gettext("Settings")?></a></li>
			</ul>

			<div class="tab-content" style="border-style:solid;border-width:0 1px 1px 1px;padding:15px 15px 10px;margin-bottom:15px;border-radius:0 0 4px 4px;" id="hubTabContent">

				<!-- OVERVIEW -->
				<div role="tabpanel" class="tab-pane active" id="hubTabOverview">
					<div class="panel panel-default">
						<div class="panel-heading">
							<h2 class="panel-title"><i class="fa fa-tachometer"></i> <?=gettext("Hub Overview")?></h2>
						</div>
						<div class="panel-body" style="padding:0;">
							<table class="table table-condensed se-kv-table" style="margin-bottom:0;"><tbody id="hubInfo"></tbody></table>
						</div>
					</div>
				</div>

				<!-- USERS & GROUPS -->
				<div role="tabpanel" class="tab-pane" id="hubTabUsers">
					<div class="panel panel-default">
						<div class="panel-heading">
							<h2 class="panel-title se-title-flex">
								<span><i class="fa fa-user"></i> <?=gettext("Users")?></span>
								<button class="btn btn-xs btn-success" data-toggle="modal" data-target="#createUserModal"><i class="fa fa-plus"></i> <?=gettext("Add User")?></button>
							</h2>
						</div>
						<div class="panel-body" style="padding:0;">
							<table class="table table-striped table-condensed" style="margin-bottom:0;">
								<thead><tr><th><?=gettext("User Name")?></th><th><?=gettext("Real Name")?></th><th><?=gettext("Group")?></th><th><?=gettext("Auth")?></th><th><?=gettext("Logins")?></th><th><?=gettext("Last Login")?></th><th><?=gettext("Send")?></th><th><?=gettext("Recv")?></th><th><?=gettext("Description")?></th><th><?=gettext("Deny")?></th><th><?=gettext("Expires")?></th><th style="width:60px;"><?=gettext("Actions")?></th></tr></thead>
								<tbody id="userTable"></tbody>
							</table>
						</div>
					</div>
					<div class="panel panel-default">
						<div class="panel-heading">
							<h2 class="panel-title se-title-flex">
								<span><i class="fa fa-group"></i> <?=gettext("Groups")?></span>
								<button class="btn btn-xs btn-success" data-toggle="modal" data-target="#createGroupModal"><i class="fa fa-plus"></i> <?=gettext("Add Group")?></button>
							</h2>
						</div>
						<div class="panel-body" style="padding:0;">
							<table class="table table-striped table-condensed" style="margin-bottom:0;">
								<thead><tr><th><?=gettext("Group Name")?></th><th><?=gettext("Full Name")?></th><th><?=gettext("Description")?></th><th><?=gettext("Users")?></th><th style="width:50px;"><?=gettext("Actions")?></th></tr></thead>
								<tbody id="groupTable"></tbody>
							</table>
						</div>
					</div>
				</div>

				<!-- SESSIONS: active sessions, MAC, IP, TCP connections -->
				<div role="tabpanel" class="tab-pane" id="hubTabSessions">
					<div class="panel panel-default">
						<div class="panel-heading">
							<ul class="nav nav-tabs panel-tabs" role="tablist" style="margin:0;border:none;">
								<li role="presentation" class="active"><a href="#tabSessions"  role="tab" data-toggle="tab"><?=gettext("Sessions")?></a></li>
								<li role="presentation"><a href="#tabMacTable"  role="tab" data-toggle="tab"><?=gettext("MAC Table")?></a></li>
								<li role="presentation"><a href="#tabIpTable"   role="tab" data-toggle="tab"><?=gettext("IP Table")?></a></li>
								<li role="presentation"><a href="#tabConnTable" role="tab" data-toggle="tab"><?=gettext("TCP Connections")?></a></li>
							</ul>
						</div>
						<div class="panel-body" style="padding:0;">
							<div class="tab-content">
								<div role="tabpanel" class="tab-pane active" id="tabSessions"><table class="table table-striped table-condensed" style="margin-bottom:0;"><thead></thead><tbody id="sessionTable"></tbody></table></div>
								<div role="tabpanel" class="tab-pane" id="tabMacTable"><table class="table table-striped table-condensed" style="margin-bottom:0;"><thead></thead><tbody id="macTable"></tbody></table></div>
								<div role="tabpanel" class="tab-pane" id="tabIpTable"><table class="table table-striped table-condensed" style="margin-bottom:0;"><thead></thead><tbody id="ipTable"></tbody></table></div>
								<div role="tabpanel" class="tab-pane" id="tabConnTable"><table class="table table-striped table-condensed" style="margin-bottom:0;"><thead></thead><tbody id="connTable"></tbody></table></div>
							</div>
						</div>
					</div>
				</div>

				<!-- NAT & DHCP: SecureNAT controls + NAT table + DHCP leases -->
				<div role="tabpanel" class="tab-pane" id="hubTabNatDhcp">
					<div class="panel panel-default">
						<div class="panel-heading se-title-flex">
							<ul class="nav nav-tabs panel-tabs" role="tablist" style="margin:0;border:none;flex:1;">
								<li role="presentation" class="active"><a href="#tabNatTable"  role="tab" data-toggle="tab"><?=gettext("NAT Table")?></a></li>
								<li role="presentation"><a href="#tabDhcpTable" role="tab" data-toggle="tab"><?=gettext("DHCP Leases")?></a></li>
							</ul>
							<div id="secureNATControls" style="display:none;align-items:center;gap:6px;flex-shrink:0;">
								<span id="secureNATStatus" class="label label-default"></span>
								<div class="btn-group btn-group-xs">
									<button class="btn btn-success" onclick="toggleSecureNAT(true)"><i class="fa fa-play"></i> <?=gettext("Enable")?></button>
									<button class="btn btn-warning" onclick="toggleSecureNAT(false)"><i class="fa fa-stop"></i> <?=gettext("Disable")?></button>
									<button class="btn btn-default" onclick="showSecureNATSettings()"><i class="fa fa-cog"></i> <?=gettext("Settings")?></button>
								</div>
							</div>
						</div>
						<div class="panel-body" style="padding:0;">
							<div class="tab-content">
								<div role="tabpanel" class="tab-pane active" id="tabNatTable"><table class="table table-striped table-condensed" style="margin-bottom:0;"><thead></thead><tbody id="natTable"></tbody></table></div>
								<div role="tabpanel" class="tab-pane" id="tabDhcpTable"><table class="table table-striped table-condensed" style="margin-bottom:0;"><thead></thead><tbody id="dhcpTable"></tbody></table></div>
							</div>
						</div>
					</div>
				</div>

				<!-- SECURITY -->
				<div role="tabpanel" class="tab-pane" id="hubTabSecurity">
					<div class="panel panel-default">
						<div class="panel-heading">
							<h2 class="panel-title se-title-flex">
								<span><i class="fa fa-filter"></i> <?=gettext("Access List (Packet Filter)")?></span>
								<button class="btn btn-xs btn-success" data-toggle="modal" data-target="#addAccessModal" id="addAccessBtn"><i class="fa fa-plus"></i> <?=gettext("Add Rule")?></button>
							</h2>
						</div>
						<div id="accessListPanel" class="panel-body" style="padding:0;">
							<table class="table table-striped table-condensed" style="margin-bottom:0;">
								<thead><tr><th><?=gettext("Pri")?></th><th><?=gettext("Note")?></th><th><?=gettext("Action")?></th><th><?=gettext("Proto")?></th><th><?=gettext("Src IP/Mask")?></th><th><?=gettext("Dst IP/Mask")?></th><th><?=gettext("Src Port")?></th><th><?=gettext("Dst Port")?></th><th><?=gettext("On")?></th><th style="width:40px;"><?=gettext("Actions")?></th></tr></thead>
								<tbody id="accessTable"></tbody>
							</table>
						</div>
					</div>
					<div class="row">
						<div class="col-sm-5">
							<div class="panel panel-default">
								<div class="panel-heading"><h4 class="panel-title"><i class="fa fa-key"></i> <?=gettext("RADIUS Authentication")?></h4></div>
								<div class="panel-body">
									<div id="radiusPanel" class="form-horizontal">
										<div class="form-group form-group-sm"><label class="col-sm-4 control-label"><?=gettext("Server")?></label><div class="col-sm-8"><input type="text" id="radiusServer" class="form-control" placeholder="radius.example.com"></div></div>
										<div class="form-group form-group-sm"><label class="col-sm-4 control-label"><?=gettext("Port")?></label><div class="col-sm-8"><input type="number" id="radiusPort" class="form-control" value="1812"></div></div>
										<div class="form-group form-group-sm"><label class="col-sm-4 control-label"><?=gettext("Secret")?></label><div class="col-sm-8"><input type="password" id="radiusSecret" class="form-control" autocomplete="new-password"></div></div>
										<div class="form-group form-group-sm"><label class="col-sm-4 control-label"><?=gettext("Retry (ms)")?></label><div class="col-sm-8"><input type="number" id="radiusRetry" class="form-control" value="500"></div></div>
										<div class="form-group form-group-sm"><div class="col-sm-offset-4 col-sm-8">
											<button class="btn btn-sm btn-primary" onclick="saveHubRadius()"><i class="fa-solid fa-save"></i> <?=gettext("Save")?></button>
											<button class="btn btn-sm btn-default" onclick="clearHubRadius()" style="margin-left:4px;"><i class="fa-solid fa-eraser icon-embed-btn"></i> <?=gettext("Clear")?></button>
										</div></div>
									</div>
								</div>
							</div>
						</div>
						<div class="col-sm-6 col-sm-offset-1">
							<div class="panel panel-default">
								<div class="panel-heading">
									<h4 class="panel-title se-title-flex">
										<span><i class="fa fa-certificate"></i> <?=gettext("Trusted CA Certificates")?></span>
										<button class="btn btn-xs btn-success" data-toggle="modal" data-target="#addCaModal" style="font-size:11px;"><i class="fa fa-plus"></i> <?=gettext("Add CA")?></button>
									</h4>
								</div>
								<div class="panel-body" style="padding:0;">
									<table class="table table-condensed table-striped" style="margin-bottom:0;">
										<thead><tr><th><?=gettext("Subject")?></th><th><?=gettext("Expires")?></th><th style="width:40px;"><?=gettext("Actions")?></th></tr></thead>
										<tbody id="caTable"></tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
					<div class="panel panel-default">
						<div class="panel-heading">
							<h4 class="panel-title se-title-flex">
								<span><i class="fa fa-ban"></i> <?=gettext("Certificate Revocation List (CRL)")?></span>
								<button class="btn btn-xs btn-success" data-toggle="modal" data-target="#addCrlModal"><i class="fa fa-plus"></i> <?=gettext("Add CRL")?></button>
							</h4>
						</div>
						<div class="panel-body" style="padding:0;">
							<table class="table table-condensed table-striped" style="margin-bottom:0;">
								<thead><tr><th><?=gettext("Common Name")?></th><th><?=gettext("Serial")?></th><th><?=gettext("Not After")?></th><th style="width:50px;"><?=gettext("Actions")?></th></tr></thead>
								<tbody id="crlTable"><tr><td colspan="4" class="text-muted"><?=gettext("Not loaded.")?></td></tr></tbody>
							</table>
						</div>
					</div>
				</div>

				<!-- CASCADE -->
				<div role="tabpanel" class="tab-pane" id="hubTabCascade">
					<div class="panel panel-default">
						<div class="panel-heading">
							<h2 class="panel-title se-title-flex">
								<span><i class="fa fa-code-fork"></i> <?=gettext("Cascade Connections")?></span>
								<button class="btn btn-xs btn-success" data-toggle="modal" data-target="#createCascadeModal"><i class="fa fa-plus"></i> <?=gettext("Add Cascade")?></button>
							</h2>
						</div>
						<div class="panel-body" style="padding:0;">
							<table class="table table-striped table-condensed" style="margin-bottom:0;">
								<thead><tr><th><?=gettext("Name")?></th><th><?=gettext("Target Hub")?></th><th><?=gettext("Hostname")?></th><th><?=gettext("Hub User")?></th><th><?=gettext("Status")?></th><th><?=gettext("Est.")?></th><th style="width:110px;"><?=gettext("Actions")?></th></tr></thead>
								<tbody id="cascadeTable"></tbody>
							</table>
						</div>
					</div>
				</div>

				<!-- SETTINGS: Hub Log + Admin Options + Extended Options + WireGuard Keys -->
				<div role="tabpanel" class="tab-pane" id="hubTabSettings">
					<div class="row">
						<div class="col-sm-5">
							<div class="panel panel-default">
								<div class="panel-heading"><h4 class="panel-title"><i class="fa fa-file-text-o"></i> <?=gettext("Hub Log Settings")?></h4></div>
								<div class="panel-body">
									<div id="hubLogPanel" class="form-horizontal">
										<div class="form-group form-group-sm"><div class="col-sm-12"><label><input type="checkbox" id="hubLogSecurity"> <?=gettext("Save Security Log")?></label></div></div>
										<div class="form-group form-group-sm"><div class="col-sm-12"><label><input type="checkbox" id="hubLogPacket"> <?=gettext("Save Packet Log")?></label></div></div>
										<div class="form-group form-group-sm">
											<label class="col-sm-4 control-label"><?=gettext("Rotation")?></label>
											<div class="col-sm-8"><select id="hubLogSecuritySwitch" class="form-control">
												<option value="0"><?=gettext("No Switch")?></option><option value="1"><?=gettext("Hourly")?></option>
												<option value="2" selected><?=gettext("Daily")?></option><option value="3"><?=gettext("Monthly")?></option>
											</select></div>
										</div>
										<div class="form-group form-group-sm"><div class="col-sm-offset-4 col-sm-8">
											<button class="btn btn-sm btn-primary" onclick="saveHubLog()"><i class="fa-solid fa-save"></i> <?=gettext("Save")?></button>
										</div></div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-sm-6">
							<div class="panel panel-default">
								<div class="panel-heading"><h4 class="panel-title"><i class="fa fa-cog"></i> <?=gettext("Admin Options")?></h4></div>
								<div class="panel-body" style="padding:0;">
									<table class="table table-condensed table-striped" style="margin-bottom:0;">
										<thead><tr><th><?=gettext("Name")?></th><th><?=gettext("Value")?></th><th style="width:60px;"><?=gettext("Actions")?></th></tr></thead>
										<tbody id="adminOptionsTable"><tr><td colspan="3" class="text-muted"><?=gettext("Not loaded.")?></td></tr></tbody>
									</table>
								</div>
							</div>
						</div>
						<div class="col-sm-6">
							<div class="panel panel-default">
								<div class="panel-heading"><h4 class="panel-title"><i class="fa fa-sliders"></i> <?=gettext("Extended Options")?></h4></div>
								<div class="panel-body" style="padding:0;">
									<table class="table table-condensed table-striped" style="margin-bottom:0;">
										<thead><tr><th><?=gettext("Name")?></th><th><?=gettext("Value")?></th><th style="width:60px;"><?=gettext("Actions")?></th></tr></thead>
										<tbody id="extOptionsTable"><tr><td colspan="3" class="text-muted"><?=gettext("Not loaded.")?></td></tr></tbody>
									</table>
								</div>
							</div>
						</div>
					</div>
					<div class="panel panel-default">
						<div class="panel-heading">
							<h4 class="panel-title se-title-flex">
								<span><i class="fa fa-shield"></i> <?=gettext("WireGuard Keys")?></span>
								<button class="btn btn-xs btn-success" data-toggle="modal" data-target="#addWgkModal"><i class="fa fa-plus"></i> <?=gettext("Add Key")?></button>
							</h4>
						</div>
						<div class="panel-body" style="padding:0;">
							<table class="table table-condensed table-striped" style="margin-bottom:0;">
								<thead><tr><th><?=gettext("Username")?></th><th><?=gettext("Public Key")?></th><th style="width:50px;"><?=gettext("Actions")?></th></tr></thead>
								<tbody id="wgkTable"><tr><td colspan="3" class="text-muted"><?=gettext("Not loaded.")?></td></tr></tbody>
							</table>
						</div>
					</div>
				</div>

			</div><!-- /hub tab-content -->

		</div><!-- /dynamicArea -->
	</div><!-- /tabHub -->

	<!-- TAB: STATUS -->
	<div role="tabpanel" class="tab-pane active" id="tabStatus">
		<div class="panel panel-default">
			<div class="panel-heading"><h2 class="panel-title"><?=gettext("Service Status")?></h2></div>
			<div class="panel-body">
				<table class="table table-condensed se-kv-table" style="margin-bottom:0;">
					<tbody>
						<?php foreach (['VPN Bridge' => $vpnbridge_status, 'VPN Server' => $vpnserver_status] as $svc => $status): ?>
						<tr>
							<td style="width:50%;"><strong><?=gettext($svc)?></strong></td>
							<td><span class="label <?=($status === 'running' ? 'label-success' : 'label-default')?>"><?=($status === 'running' ? gettext('Running') : gettext('Stopped'))?></span></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php if ($vpnserver_status === 'running' && $vpnbridge_status === 'running'): ?>
				<div class="bs-callout bs-callout-danger" style="margin-top:10px;margin-bottom:0;">
					<strong><?=gettext("Warning:")?></strong> <?=gettext("Both services are running. Only one can run at a time.")?>
				</div>
				<?php endif; ?>
				<div class="alert alert-info" style="margin-top:10px;margin-bottom:0;">
					<i class="fa fa-info-circle"></i>
					<strong><?=gettext("Note:")?></strong>
					<?=gettext("Only one service (VPN Server or VPN Bridge) can run at a time.")?>
					<?=gettext("To start or stop services, go to")?> <a href="/status_services.php"><b><?=gettext("Status > Services")?></b></a>.
					<?=gettext("If one service is running, starting the other will be blocked until you stop the active one.")?>
				</div>
			</div>
		</div>
	</div><!-- /tabStatus -->

	<!-- TAB: MAINTENANCE -->
	<div role="tabpanel" class="tab-pane" id="tabMaintenance">
		<div class="row">
			<div class="col-sm-6">
				<div class="panel panel-default">
					<div class="panel-heading"><h2 class="panel-title"><?=gettext("Server Actions")?></h2></div>
					<div class="panel-body">
						<button type="button" id="btnRebootServer"     class="btn btn-sm btn-warning" onclick="rebootServer()"       style="margin-right:6px;margin-bottom:6px;" disabled><i class="fa fa-power-off"></i> <?=gettext("Reboot VPN Server")?></button>
						<button type="button" id="btnExportConfig"     class="btn btn-sm btn-default" onclick="downloadServerConfig()" style="margin-right:6px;margin-bottom:6px;" disabled><i class="fa-solid fa-download icon-embed-btn"></i> <?=gettext("Export Config")?></button>
						<button type="button" id="btnImportConfig"     class="btn btn-sm btn-default" data-toggle="modal" data-target="#importConfigModal" style="margin-right:6px;margin-bottom:6px;" disabled><i class="fa fa-upload"></i> <?=gettext("Import Config")?></button>
						<button type="button" id="btnFlushLog"         class="btn btn-sm btn-default" onclick="flushServerLog()"      style="margin-bottom:6px;" disabled><i class="fa fa-eraser"></i> <?=gettext("Flush Log")?></button>
					</div>
				</div>
			</div>
			<div class="col-sm-6">
				<div class="panel panel-default">
					<div class="panel-heading"><h2 class="panel-title"><?=gettext("Configuration Reset")?></h2></div>
					<div class="panel-body">
						<div class="bs-callout bs-callout-warning" style="margin-top:0;">
							<strong><?=gettext("Warning!")?></strong> <?=gettext("This stops the service and deletes its configuration files.")?>
						</div>
						<div style="margin-top:10px;">
							<button type="button" class="btn btn-sm btn-danger" onclick="showResetConfirm('server')" style="margin-right:8px;"><i class="fa fa-refresh"></i> <?=gettext("Reset VPN Server")?></button>
							<button type="button" class="btn btn-sm btn-danger" onclick="showResetConfirm('bridge')"><i class="fa fa-refresh"></i> <?=gettext("Reset VPN Bridge")?></button>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="panel panel-default">
			<div class="panel-heading">
				<h2 class="panel-title se-title-flex">
					<span><?=gettext("Log Files")?></span>
					<button class="btn btn-xs btn-default" onclick="refreshLogFiles()"><i class="fa fa-refresh"></i> <?=gettext("Refresh")?></button>
				</h2>
			</div>
			<div class="panel-body" style="padding:0;">
				<table class="table table-condensed table-striped" style="margin-bottom:0;">
					<thead><tr><th><?=gettext("File Path")?></th><th><?=gettext("Server")?></th><th><?=gettext("Size")?></th><th style="width:50px;"><?=gettext("Actions")?></th></tr></thead>
					<tbody id="logFilesTable"><tr><td colspan="4" class="text-muted"><?=gettext("Click Refresh to load log files.")?></td></tr></tbody>
				</table>
			</div>
		</div>
	</div><!-- /tabMaintenance -->

</div><!-- /top-level tab-content -->

<!-- MODALS -->

<div class="modal fade" id="createUserModal" tabindex="-1" role="dialog">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("Create User")?></h4>
		</div>
		<div class="modal-body form-horizontal">
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Username")?></label><div class="col-sm-8"><input type="text" id="newUserName" class="form-control"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Real Name")?></label><div class="col-sm-8"><input type="text" id="newUserReal" class="form-control"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Description")?></label><div class="col-sm-8"><input type="text" id="newUserNote" class="form-control"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Auth Type")?></label><div class="col-sm-8"><select id="newUserAuthType" class="form-control"><option value="0">Anonymous</option><option value="1" selected>Password</option><option value="4">RADIUS / NT Domain</option><option value="5">NT Domain</option></select></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Password")?></label><div class="col-sm-8"><input type="password" id="newUserPass" class="form-control" autocomplete="new-password"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Group")?></label><div class="col-sm-8"><select id="newUserGroup" class="form-control"><option value="">-- No Group --</option></select></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Expiry Date")?></label><div class="col-sm-8"><input type="date" id="newUserExpiry" class="form-control"><small class="text-muted"><?=gettext("Leave blank for no expiry")?></small></div></div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="createUser()"><i class="fa-solid fa-plus icon-embed-btn"></i> <?=gettext("Create")?></button>
		</div>
	</div></div>
</div>

<div class="modal fade" id="createGroupModal" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-lg"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("Create Group")?></h4>
		</div>
		<div class="modal-body">
			<div class="form-horizontal">
				<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Group Name")?></label><div class="col-sm-8"><input type="text" id="newGroupName" class="form-control"></div></div>
				<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Full Name")?></label><div class="col-sm-8"><input type="text" id="newGroupReal" class="form-control"></div></div>
				<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Description")?></label><div class="col-sm-8"><input type="text" id="newGroupNote" class="form-control"></div></div>
			</div>
			<hr style="margin:12px 0;">
			<div class="panel panel-default" style="margin-bottom:0;">
				<div class="panel-heading" style="padding:6px 12px;display:flex;align-items:center;justify-content:space-between;">
					<strong><?=gettext("Security Policy")?></strong>
					<label style="margin:0;font-weight:normal;font-size:13px;cursor:pointer;">
						<input type="checkbox" id="newGroupPolicyEnabled" onchange="onGroupPolicyToggle('new')" style="margin-right:5px;"><?=gettext("Enable Security Policy")?>
					</label>
				</div>
				<div class="panel-body" style="padding:0;">
					<div style="display:flex;height:280px;pointer-events:none;opacity:0.45;" id="newGroupPolicyContent">
						<div style="width:55%;overflow-y:auto;border-right:1px solid transparent;" id="newGroupPolicyListWrap">
							<table class="table table-condensed table-striped" style="margin:0;font-size:12px;">
								<thead><tr><th><?=gettext("Policy Name")?></th><th style="width:80px;"><?=gettext("Value")?></th></tr></thead>
								<tbody id="newGroupPolicyList"></tbody>
							</table>
						</div>
						<div style="width:45%;padding:12px;overflow-y:auto;" id="newGroupPolicyDetail">
							<p class="text-muted" style="font-size:12px;"><?=gettext("Select a policy to configure it.")?></p>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="createGroup()"><i class="fa-solid fa-plus icon-embed-btn"></i> <?=gettext("Create")?></button>
		</div>
	</div></div>
</div>

<div class="modal fade" id="editGroupModal" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-lg"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("Group Properties")?></h4>
		</div>
		<div class="modal-body">
			<input type="hidden" id="editGroupOldName">
			<div class="form-horizontal">
				<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Group Name")?></label><div class="col-sm-8"><input type="text" id="editGroupName" class="form-control" readonly></div></div>
				<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Full Name")?></label><div class="col-sm-8"><input type="text" id="editGroupReal" class="form-control"></div></div>
				<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Description")?></label><div class="col-sm-8"><input type="text" id="editGroupNote" class="form-control"></div></div>
			</div>
			<hr style="margin:12px 0;">
			<div class="panel panel-default" style="margin-bottom:8px;">
				<div class="panel-heading" style="padding:6px 12px;display:flex;align-items:center;justify-content:space-between;">
					<strong><?=gettext("Security Policy")?></strong>
					<label style="margin:0;font-weight:normal;font-size:13px;cursor:pointer;">
						<input type="checkbox" id="editGroupPolicyEnabled" onchange="onGroupPolicyToggle('edit')" style="margin-right:5px;"><?=gettext("Enable Security Policy")?>
					</label>
				</div>
				<div class="panel-body" style="padding:0;">
					<div style="display:flex;height:280px;pointer-events:none;opacity:0.45;" id="editGroupPolicyContent">
						<div style="width:55%;overflow-y:auto;border-right:1px solid transparent;" id="editGroupPolicyListWrap">
							<table class="table table-condensed table-striped" style="margin:0;font-size:12px;">
								<thead><tr><th><?=gettext("Policy Name")?></th><th style="width:80px;"><?=gettext("Value")?></th></tr></thead>
								<tbody id="editGroupPolicyList"></tbody>
							</table>
						</div>
						<div style="width:45%;padding:12px;overflow-y:auto;" id="editGroupPolicyDetail">
							<p class="text-muted" style="font-size:12px;"><?=gettext("Select a policy to configure it.")?></p>
						</div>
					</div>
				</div>
			</div>
			<div class="panel panel-default" style="margin-bottom:8px;">
				<div class="panel-heading" style="padding:6px 12px;"><strong><?=gettext("Statistical Information")?></strong></div>
				<div class="panel-body" style="padding:0;">
					<table class="table table-condensed se-kv-table" style="margin-bottom:0;">
						<thead><tr><th><?=gettext("Item")?></th><th><?=gettext("Value")?></th></tr></thead>
						<tbody id="editGroupStats"></tbody>
					</table>
				</div>
			</div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="saveEditGroup()"><i class="fa-solid fa-check icon-embed-btn"></i> <?=gettext("OK")?></button>
		</div>
	</div></div>
</div>

<div class="modal fade" id="createHubModal" tabindex="-1" role="dialog">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("Create Virtual Hub")?></h4>
		</div>
		<div class="modal-body form-horizontal">
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Hub Name")?></label><div class="col-sm-8"><input type="text" id="newHubName" class="form-control"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Password")?></label><div class="col-sm-8"><input type="password" id="newHubPass" class="form-control" autocomplete="new-password"></div></div>
			<div class="form-group"><div class="col-sm-offset-4 col-sm-8" style="padding-top:5px;"><label><input type="checkbox" id="newHubEnabled" checked> <?=gettext("Enable hub after creation")?></label></div></div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="createHub()"><i class="fa-solid fa-plus icon-embed-btn"></i> <?=gettext("Create")?></button>
		</div>
	</div></div>
</div>

<div class="modal fade" id="createListenerModal" tabindex="-1" role="dialog">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("Add Listener")?></h4>
		</div>
		<div class="modal-body form-horizontal">
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Port")?></label><div class="col-sm-8"><input type="number" id="newListenerPort" class="form-control" value="443" min="1" max="65535"></div></div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="createListener()"><i class="fa-solid fa-plus icon-embed-btn"></i> <?=gettext("Add")?></button>
		</div>
	</div></div>
</div>

<div class="modal fade" id="editListenerModal" tabindex="-1" role="dialog">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("Edit Listener")?></h4>
		</div>
		<div class="modal-body form-horizontal">
			<input type="hidden" id="editListenerOldPort">
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Port")?></label><div class="col-sm-8"><input type="number" id="editListenerPort" class="form-control" min="1" max="65535"></div></div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="saveEditListener()"><i class="fa-solid fa-save icon-embed-btn"></i> <?=gettext("Save")?></button>
		</div>
	</div></div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" role="dialog">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("Edit User")?></h4>
		</div>
		<div class="modal-body form-horizontal">
			<input type="hidden" id="editUserName">
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Username")?></label><div class="col-sm-8"><input type="text" id="editUserDisplayName" class="form-control" readonly></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Real Name")?></label><div class="col-sm-8"><input type="text" id="editUserReal" class="form-control"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Description")?></label><div class="col-sm-8"><input type="text" id="editUserNote" class="form-control"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Auth Type")?></label><div class="col-sm-8"><select id="editUserAuthType" class="form-control"><option value="0">Anonymous</option><option value="1" selected>Password</option><option value="4">RADIUS / NT Domain</option><option value="5">NT Domain</option></select></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Group")?></label><div class="col-sm-8"><select id="editUserGroup" class="form-control"><option value="">-- No Group --</option></select></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Expiry Date")?></label><div class="col-sm-8"><input type="date" id="editUserExpiry" class="form-control"><small class="text-muted"><?=gettext("Leave blank for no expiry")?></small></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("New Password")?></label><div class="col-sm-8"><input type="password" id="editUserPass" class="form-control" autocomplete="new-password" placeholder="<?=gettext("Leave blank to keep current")?>"></div></div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="saveEditUser()"><i class="fa-solid fa-save icon-embed-btn"></i> <?=gettext("Save")?></button>
		</div>
	</div></div>
</div>

<!-- Set Hub Password Modal -->
<div class="modal fade" id="hubPasswordModal" tabindex="-1" role="dialog">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("Set Hub Admin Password")?></h4>
		</div>
		<div class="modal-body form-horizontal">
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("New Password")?></label><div class="col-sm-8"><input type="password" id="hubNewPassword" class="form-control" autocomplete="new-password"></div></div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="saveHubPassword()"><i class="fa-solid fa-save icon-embed-btn"></i> <?=gettext("Save")?></button>
		</div>
	</div></div>
</div>

<!-- Hub Settings Modal -->
<div class="modal fade" id="hubSettingsModal" tabindex="-1" role="dialog">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("Hub Settings")?></h4>
		</div>
		<div class="modal-body form-horizontal">
			<div class="form-group">
				<label class="col-sm-4 control-label"><?=gettext("Hub Type")?></label>
				<div class="col-sm-8"><select id="hubSettingsType" class="form-control">
					<option value="0"><?=gettext("Standalone Virtual Hub")?></option>
					<option value="1"><?=gettext("Static Virtual Hub")?></option>
					<option value="2"><?=gettext("Dynamic Virtual Hub")?></option>
				</select></div>
			</div>
			<div class="form-group">
				<label class="col-sm-4 control-label"><?=gettext("Max Sessions")?></label>
				<div class="col-sm-8"><input type="number" id="hubSettingsMaxSessions" class="form-control" value="0" min="0" placeholder="0 = unlimited"></div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-4 col-sm-8" style="padding-top:5px;"><label><input type="checkbox" id="hubSettingsNoEnum"> <?=gettext("Hide from anonymous EnumHub")?></label></div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-4 col-sm-8" style="padding-top:5px;"><label><input type="checkbox" id="hubSettingsShowMsg" onchange="onHubMsgToggle()"> <?=gettext("Show message when client connects")?></label></div>
			</div>
			<div class="form-group" id="hubSettingsMsgRow" style="display:none;">
				<label class="col-sm-4 control-label"><?=gettext("Client Message")?></label>
				<div class="col-sm-8"><textarea id="hubSettingsMsg" class="form-control" rows="4" style="resize:vertical;"></textarea></div>
			</div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="saveHubSettings()"><i class="fa-solid fa-save icon-embed-btn"></i> <?=gettext("Save")?></button>
		</div>
	</div></div>
</div>

<!-- Add Access Rule Modal -->
<div class="modal fade" id="addAccessModal" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-lg"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("Add Access Rule")?></h4>
		</div>
		<div class="modal-body form-horizontal">
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Priority")?></label><div class="col-sm-8"><input type="number" id="accPriority" class="form-control" value="100" min="1" max="4294967295"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Note")?></label><div class="col-sm-8"><input type="text" id="accNote" class="form-control" placeholder="<?=gettext("Rule description")?>"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Action")?></label><div class="col-sm-8"><select id="accAction" class="form-control"><option value="0"><?=gettext("Pass")?></option><option value="1"><?=gettext("Discard")?></option></select></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Active")?></label><div class="col-sm-8" style="padding-top:5px;"><label><input type="checkbox" id="accActive" checked> <?=gettext("Enabled")?></label></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Protocol")?></label><div class="col-sm-8"><select id="accProtocol" class="form-control"><option value="0"><?=gettext("All")?></option><option value="1">ICMP</option><option value="6">TCP</option><option value="17">UDP</option></select></div></div>
			<div class="form-group">
				<label class="col-sm-4 control-label"><?=gettext("Source IP")?></label>
				<div class="col-sm-4"><input type="text" id="accSrcIp" class="form-control" placeholder="0.0.0.0"></div>
				<div class="col-sm-4"><input type="text" id="accSrcMask" class="form-control" placeholder="Mask: 0.0.0.0"></div>
			</div>
			<div class="form-group">
				<label class="col-sm-4 control-label"><?=gettext("Dest IP")?></label>
				<div class="col-sm-4"><input type="text" id="accDstIp" class="form-control" placeholder="0.0.0.0"></div>
				<div class="col-sm-4"><input type="text" id="accDstMask" class="form-control" placeholder="Mask: 0.0.0.0"></div>
			</div>
			<div class="form-group">
				<label class="col-sm-4 control-label"><?=gettext("Src Port")?></label>
				<div class="col-sm-4"><input type="number" id="accSrcPortStart" class="form-control" value="0" min="0" max="65535" placeholder="Start"></div>
				<div class="col-sm-4"><input type="number" id="accSrcPortEnd" class="form-control" value="65535" min="0" max="65535" placeholder="End"></div>
			</div>
			<div class="form-group">
				<label class="col-sm-4 control-label"><?=gettext("Dst Port")?></label>
				<div class="col-sm-4"><input type="number" id="accDstPortStart" class="form-control" value="0" min="0" max="65535" placeholder="Start"></div>
				<div class="col-sm-4"><input type="number" id="accDstPortEnd" class="form-control" value="65535" min="0" max="65535" placeholder="End"></div>
			</div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="addAccess()"><i class="fa-solid fa-plus icon-embed-btn"></i> <?=gettext("Add Rule")?></button>
		</div>
	</div></div>
</div>

<!-- SecureNAT Settings Modal -->
<div class="modal fade" id="secureNATSettingsModal" tabindex="-1" role="dialog">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("SecureNAT Settings")?></h4>
		</div>
		<div class="modal-body form-horizontal">
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("NAT")?></label><div class="col-sm-8" style="padding-top:5px;"><label><input type="checkbox" id="natUseNat" checked> <?=gettext("Enable NAT")?></label></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("MTU")?></label><div class="col-sm-8"><input type="number" id="natMtu" class="form-control" value="1500"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("TCP Timeout (sec)")?></label><div class="col-sm-8"><input type="number" id="natTcpTimeout" class="form-control" value="300"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("UDP Timeout (sec)")?></label><div class="col-sm-8"><input type="number" id="natUdpTimeout" class="form-control" value="60"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("DHCP Server")?></label><div class="col-sm-8" style="padding-top:5px;"><label><input type="checkbox" id="natUseDhcp" checked> <?=gettext("Enable DHCP Server")?></label></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Lease Start IP")?></label><div class="col-sm-8"><input type="text" id="natDhcpStart" class="form-control" value="192.168.30.10"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Lease End IP")?></label><div class="col-sm-8"><input type="text" id="natDhcpEnd" class="form-control" value="192.168.30.200"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Subnet Mask")?></label><div class="col-sm-8"><input type="text" id="natDhcpMask" class="form-control" value="255.255.255.0"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Default Gateway")?></label><div class="col-sm-8"><input type="text" id="natDhcpGw" class="form-control" placeholder="<?=gettext("SecureNAT IP if blank")?>"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("DNS Server 1")?></label><div class="col-sm-8"><input type="text" id="natDhcpDns1" class="form-control" placeholder="e.g. 8.8.8.8"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("DNS Server 2")?></label><div class="col-sm-8"><input type="text" id="natDhcpDns2" class="form-control"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Domain Name")?></label><div class="col-sm-8"><input type="text" id="natDhcpDomain" class="form-control"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Lease Time (sec)")?></label><div class="col-sm-8"><input type="number" id="natDhcpExpire" class="form-control" value="7200"></div></div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="saveSecureNATSettings()"><i class="fa-solid fa-save icon-embed-btn"></i> <?=gettext("Save")?></button>
		</div>
	</div></div>
</div>

<div class="modal fade" id="importConfigModal" tabindex="-1" role="dialog">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("Import Server Configuration")?></h4>
		</div>
		<div class="modal-body">
			<div class="bs-callout bs-callout-warning" style="margin-bottom:12px;">
				<strong><?=gettext("Warning:")?></strong> <?=gettext("Importing a configuration will overwrite the current server config and restart the VPN service.")?>
			</div>
			<div class="form-group">
				<label class="control-label"><?=gettext("Configuration file (.config)")?></label>
				<input type="file" id="importConfigFile" accept=".config,.txt" class="form-control">
			</div>
			<div id="importConfigPreview" style="display:none;">
				<label class="control-label"><?=gettext("Preview (first 20 lines)")?></label>
				<pre id="importConfigPreviewText" style="max-height:150px;overflow:auto;font-size:11px;"></pre>
			</div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="importServerConfig()"><i class="fa-solid fa-upload icon-embed-btn"></i> <?=gettext("Import")?></button>
		</div>
	</div></div>
</div>

<div class="modal fade" id="addCaModal" tabindex="-1" role="dialog">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("Add CA Certificate")?></h4>
		</div>
		<div class="modal-body">
			<div class="form-group">
				<label class="control-label"><?=gettext("Certificate (PEM format)")?></label>
				<textarea id="caCertPem" class="form-control" rows="10" placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----" style="font-family:monospace;font-size:11px;"></textarea>
			</div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="addCa()"><i class="fa-solid fa-plus icon-embed-btn"></i> <?=gettext("Add")?></button>
		</div>
	</div></div>
</div>

<?php
// Helper: render cascade modal body fields
function cascadeModalFields($prefix) {
	$fields = [
		['text', 'Name',     'Connection Name',   'e.g. Remote Office', ''],
		['text', 'Host',     'Server Hostname',   'vpn.example.com',    'onblur="loadRemoteHubs(\'' . $prefix . '\')"'],
		['number','Port',    'Port',              '443',                ''],
		['text', 'User',     'Remote API User',   'Administrator',      ''],
		['password','Pass',  'Remote API Password','',                  ''],
		['text', 'HubUser',  'Hub Username',      'hub user',           ''],
		['password','HubPass','Hub Password',      '',                   ''],
	];
	foreach ($fields as [$type, $id, $label, $placeholder, $extra]) {
		$inputId = $prefix . 'Cascade' . $id;
		$val = ($id === 'Port') ? 'value="443" min="1" max="65535"' : 'placeholder="' . $placeholder . '"';
		echo '<div class="form-group">';
		echo '<label class="col-sm-4 control-label">' . gettext($label) . '</label>';
		echo '<div class="col-sm-8">';
		if ($type === 'number') {
			echo '<input type="number" id="' . $inputId . '" class="form-control" ' . $val . ' ' . $extra . ' oninput="scheduleRemoteHubReload(\'' . $prefix . '\',true)" onchange="scheduleRemoteHubReload(\'' . $prefix . '\',true)">';
		} else {
			echo '<input type="' . $type . '" id="' . $inputId . '" class="form-control" ' . $val . ' ' . $extra . ' oninput="scheduleRemoteHubReload(\'' . $prefix . '\',true)" onchange="scheduleRemoteHubReload(\'' . $prefix . '\',true)">';
		}
		echo '</div></div>';
	}
	echo '<div class="form-group">';
	echo '<label class="col-sm-4 control-label">' . gettext('Target Hub') . '</label>';
	echo '<div class="col-sm-8">';
	echo '<select id="' . $prefix . 'CascadeHub" class="form-control" onfocus="scheduleRemoteHubReload(\'' . $prefix . '\',true)" onclick="scheduleRemoteHubReload(\'' . $prefix . '\',true)" onchange="scheduleRemoteHubReload(\'' . $prefix . '\',true)">';
	echo '<option value="">(Select a hub...)</option></select>';
	echo '</div></div>';
}
?>

<div class="modal fade" id="createCascadeModal" tabindex="-1" role="dialog">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("Add Cascade Connection")?></h4>
		</div>
		<div class="modal-body form-horizontal">
			<?php cascadeModalFields('new'); ?>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="createCascade()"><i class="fa-solid fa-plus icon-embed-btn"></i> <?=gettext("Create")?></button>
		</div>
	</div></div>
</div>

<div class="modal fade" id="editCascadeModal" tabindex="-1" role="dialog">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("Edit Cascade Connection")?></h4>
		</div>
		<div class="modal-body form-horizontal">
			<input type="hidden" id="editCascadeOldName">
			<?php cascadeModalFields('edit'); ?>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="saveEditCascade()"><i class="fa-solid fa-save icon-embed-btn"></i> <?=gettext("Save")?></button>
		</div>
	</div></div>
</div>

<div class="modal fade" id="createTapModal" tabindex="-1" role="dialog">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("Add Local Bridge / TAP")?></h4>
		</div>
		<div class="modal-body form-horizontal">
			<div class="form-group">
				<label class="col-sm-4 control-label"><?=gettext("Hub")?></label>
				<div class="col-sm-8">
					<select id="newLocalBridgeHub" class="form-control" onchange="updateLocalBridgeDeviceList()">
						<option value="">(Select a hub...)</option>
					</select>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-4 control-label"><?=gettext("Bridge Type")?></label>
				<div class="col-sm-8">
					<select id="newLocalBridgeType" class="form-control" onchange="onLocalBridgeTypeChange()">
						<option value="physical"><?=gettext("Physical Interface")?></option>
						<option value="tap"><?=gettext("TAP Device")?></option>
					</select>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-4 control-label" id="newLocalBridgeDeviceLabel"><?=gettext("Physical Interface")?></label>
				<div class="col-sm-8">
					<select id="newLocalBridgeDeviceSelect" class="form-control" onchange="toggleLocalBridgeDeviceInput()">
						<option value="">(Detecting interfaces...)</option>
					</select>
					<input type="text" id="newLocalBridgeDeviceText" class="form-control" placeholder="e.g. em0, igb0, re0, softether0" style="display:none;margin-top:6px;">
				</div>
			</div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="createLocalBridge()"><i class="fa-solid fa-plus icon-embed-btn"></i> <?=gettext("Create")?></button>
		</div>
	</div></div>
</div>

<div class="modal fade" id="serverCertModal" tabindex="-1" role="dialog">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("Import Server Certificate")?></h4>
		</div>
		<div class="modal-body">
			<div class="bs-callout bs-callout-warning" style="margin-bottom:12px;">
				<?=gettext("Upload a PEM-formatted certificate + private key. Both must be in the same file, or provide certificate only (the server will keep the existing key).")?>
			</div>
			<div class="form-group">
				<label class="control-label"><?=gettext("Certificate file (.pem / .crt)")?></label>
				<input type="file" id="serverCertFile" accept=".pem,.crt,.cer" class="form-control">
			</div>
			<div class="form-group">
				<label class="control-label"><?=gettext("Or paste PEM directly:")?></label>
				<textarea id="serverCertPem" class="form-control" rows="8" style="font-family:monospace;font-size:11px;" placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----"></textarea>
			</div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="uploadServerCert()"><i class="fa-solid fa-upload icon-embed-btn"></i> <?=gettext("Upload")?></button>
		</div>
	</div></div>
</div>

<div class="modal fade" id="serverCertViewModal" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-lg"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><i class="fa fa-certificate"></i> <?=gettext("Server Certificate")?></h4>
		</div>
		<div class="modal-body">
			<table class="table table-condensed" id="certViewTable" style="margin-bottom:12px;"></table>
			<label class="control-label"><?=gettext("PEM")?></label>
			<textarea id="certViewPem" class="form-control" rows="10" readonly style="font-family:monospace;font-size:11px;"></textarea>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" onclick="exportServerCert()"><i class="fa-solid fa-download icon-embed-btn"></i> <?=gettext("Export")?></button>
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-xmark icon-embed-btn"></i> <?=gettext("Close")?></button>
		</div>
	</div></div>
</div>

<div class="modal fade" id="newServerCertModal" tabindex="-1" role="dialog">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><i class="fa fa-certificate"></i> <?=gettext("Create New Certificate")?></h4>
		</div>
		<div class="modal-body form-horizontal">
			<div class="bs-callout bs-callout-info" style="margin-bottom:12px;">
				<?=gettext("You can easily create certificates which is signed by self or other certificates.")?>
			</div>
			<div class="form-group">
				<label class="col-sm-4 control-label"><?=gettext("Certificate Type")?></label>
				<div class="col-sm-8">
					<label class="radio" style="margin:2px 0;"><input type="radio" name="newCertType" id="newCertTypeSelf" value="self" checked onchange="onNewCertTypeChange()"> <?=gettext("Root Certificate (Self-Signed Certificate)")?></label>
					<label class="radio" style="margin:2px 0;"><input type="radio" name="newCertType" id="newCertTypeSigned" value="signed" onchange="onNewCertTypeChange()"> <?=gettext("Certificate Signed by Other Certificate")?></label>
				</div>
			</div>
			<div class="form-group" id="newCertSigningRow" style="display:none;">
				<label class="col-sm-4 control-label"><?=gettext("Signing Certificate")?></label>
				<div class="col-sm-8">
					<button type="button" class="btn btn-default btn-sm" id="newCertLoadSigningBtn" onclick="document.getElementById('newCertSigningFile').click()">
						<i class="fa fa-folder-open"></i> <?=gettext("Load Certificate and Private Key")?>
					</button>
					<input type="file" id="newCertSigningFile" accept=".pem,.crt,.cer,.key" style="display:none;" onchange="onSigningFileSelected(this)">
					<span id="newCertSigningFileName" style="margin-left:8px;color:#666;font-size:12px;"></span>
					<p class="help-block" style="font-size:11px;"><?=gettext("Specify the X.509 Certificate and RSA Private Key for signing.")?></p>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-4 control-label"><?=gettext("Common Name (CN)")?></label>
				<div class="col-sm-8"><input type="text" id="newCertCN" class="form-control"></div>
			</div>
			<div class="form-group">
				<label class="col-sm-4 control-label"><?=gettext("Organization (O)")?></label>
				<div class="col-sm-8"><input type="text" id="newCertO" class="form-control"></div>
			</div>
			<div class="form-group">
				<label class="col-sm-4 control-label"><?=gettext("Organization Unit (OU)")?></label>
				<div class="col-sm-8"><input type="text" id="newCertOU" class="form-control"></div>
			</div>
			<div class="form-group">
				<label class="col-sm-4 control-label"><?=gettext("Country (C)")?></label>
				<div class="col-sm-8"><input type="text" id="newCertC" class="form-control" maxlength="2" placeholder="e.g. US"></div>
			</div>
			<div class="form-group">
				<label class="col-sm-4 control-label"><?=gettext("State (ST)")?></label>
				<div class="col-sm-8"><input type="text" id="newCertST" class="form-control"></div>
			</div>
			<div class="form-group">
				<label class="col-sm-4 control-label"><?=gettext("Locale (L)")?></label>
				<div class="col-sm-8"><input type="text" id="newCertL" class="form-control"></div>
			</div>
			<div class="form-group">
				<label class="col-sm-4 control-label"><?=gettext("Serial Number (Hex)")?></label>
				<div class="col-sm-8"><input type="text" id="newCertSerial" class="form-control" placeholder="e.g. 1A2B3C"></div>
			</div>
			<div class="form-group">
				<label class="col-sm-4 control-label"><?=gettext("Expires in")?></label>
				<div class="col-sm-4"><div class="input-group"><input type="number" id="newCertDays" class="form-control" value="3650" min="1" max="36500"><span class="input-group-addon"><?=gettext("Days")?></span></div></div>
			</div>
			<div class="form-group">
				<label class="col-sm-4 control-label"><?=gettext("Strength")?></label>
				<div class="col-sm-4">
					<select id="newCertBits" class="form-control">
						<option value="1024">1024 bits</option>
						<option value="2048" selected>2048 bits</option>
						<option value="4096">4096 bits</option>
					</select>
				</div>
			</div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="submitNewServerCert()"><i class="fa-solid fa-check icon-embed-btn"></i> <?=gettext("OK")?></button>
		</div>
	</div></div>
</div>

<div class="modal fade" id="createL3Modal" tabindex="-1" role="dialog">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("Create Virtual L3 Switch")?></h4>
		</div>
		<div class="modal-body form-horizontal">
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Switch Name")?></label><div class="col-sm-8"><input type="text" id="newL3Name" class="form-control" placeholder="L3Switch1"></div></div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="addL3Switch()"><i class="fa-solid fa-plus icon-embed-btn"></i> <?=gettext("Create")?></button>
		</div>
	</div></div>
</div>

<div class="modal fade" id="addEtherIpModal" tabindex="-1" role="dialog">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("Add EtherIP / L2TPv3 Client")?></h4>
		</div>
		<div class="modal-body form-horizontal">
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Client ID")?></label><div class="col-sm-8"><input type="text" id="etherIpId" class="form-control" placeholder="*"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Virtual Hub")?></label><div class="col-sm-8"><input type="text" id="etherIpHub" class="form-control"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Username")?></label><div class="col-sm-8"><input type="text" id="etherIpUser" class="form-control"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Password")?></label><div class="col-sm-8"><input type="password" id="etherIpPass" class="form-control" autocomplete="new-password"></div></div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="addEtherIpClient()"><i class="fa-solid fa-plus icon-embed-btn"></i> <?=gettext("Add")?></button>
		</div>
	</div></div>
</div>

<div class="modal fade" id="addWgkModal" tabindex="-1" role="dialog">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("Add WireGuard Key")?></h4>
		</div>
		<div class="modal-body form-horizontal">
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Username")?></label><div class="col-sm-8"><input type="text" id="wgkUser" class="form-control" placeholder="vpnuser"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Public Key (Base64)")?></label><div class="col-sm-8"><input type="text" id="wgkPublicKey" class="form-control" placeholder="base64-encoded WireGuard public key" style="font-family:monospace;font-size:11px;"></div></div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="addWgk()"><i class="fa-solid fa-plus icon-embed-btn"></i> <?=gettext("Add")?></button>
		</div>
	</div></div>
</div>

<div class="modal fade" id="addCrlModal" tabindex="-1" role="dialog">
	<div class="modal-dialog"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title"><?=gettext("Add CRL Entry")?></h4>
		</div>
		<div class="modal-body form-horizontal">
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Common Name (CN)")?></label><div class="col-sm-8"><input type="text" id="crlCn" class="form-control" placeholder="user@example.com"></div></div>
			<div class="form-group"><label class="col-sm-4 control-label"><?=gettext("Serial Number")?></label><div class="col-sm-8"><input type="text" id="crlSerial" class="form-control" placeholder="hex string (optional)"></div></div>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-ban icon-embed-btn"></i> <?=gettext("Cancel")?></button>
			<button type="button" class="btn btn-primary" onclick="addCrl()"><i class="fa-solid fa-plus icon-embed-btn"></i> <?=gettext("Add")?></button>
		</div>
	</div></div>
</div>

<div class="modal fade" id="logFileViewModal" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-lg"><div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			<h4 class="modal-title" id="logFileViewTitle"><?=gettext("Log File")?></h4>
		</div>
		<div class="modal-body">
			<pre id="logFileViewContent" style="max-height:450px;overflow:auto;font-size:11px;white-space:pre-wrap;word-break:break-all;">Loading...</pre>
		</div>
		<div class="modal-footer">
			<button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa-solid fa-xmark icon-embed-btn"></i> <?=gettext("Close")?></button>
		</div>
	</div></div>
</div>

<script type="text/javascript">
// ============================================================
// CONSTANTS & STATE
// ============================================================

var currentHub = '';
var groupsList = [];

var SOFTETHER_ERROR_CODES = {
	0:'No error',1:'Not supported',2:'Unknown error',3:'Internal error',
	4:'Service not started',5:'Incompatible version',6:'Already disconnected',
	7:'Session not found',8:'Session not established',9:'User authentication failed',
	10:'Protocol error',11:'Token not found',12:'Duplicated',13:'Cannot create listener',
	14:'Cannot create connection',15:'Memory error',16:'Network error',17:'Not implemented',
	18:'Authentication error',19:'License restriction',20:'Verification error',
	21:'Blocked by firewall',22:'Invalid SID',23:'Certificate error',24:'Crypto error',
	25:'Bad IPv4 address',26:'Bad IPv6 address',27:'Not enough memory',28:'File not found',
	29:'Already connecting',30:'Overloaded',31:'Access denied',32:'Setting not found',
	33:'Object not found',34:'Timeout',35:'Routing error',36:'Connection failed',37:'Disconnected'
};

// ============================================================
// UTILITIES
// ============================================================

function escapeHtml(text) {
	return String(text == null ? '' : text)
		.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
		.replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

// Escape a value for embedding inside single-quoted inline JS attributes.
function escapeAttr(text) {
	return String(text == null ? '' : text).replace(/\\/g,'\\\\').replace(/'/g,"\\'");
}

function getErrorDescription(code) {
	return SOFTETHER_ERROR_CODES[Number(code)] || ('Error code ' + code);
}

/**
 * Humanize a raw field value based on key naming conventions
 * (MAC addresses, packed IPv4 ints, ISO/Unix timestamps).
 */
function humanize(key, val) {
	if (val == null) { return '-'; }
	if (/mac/i.test(key)) {
		if (typeof val === 'string') {
			// Plain hex string (12 chars, no separators)
			if (/^[0-9a-f]{12}$/i.test(val)) {
				return val.replace(/(.{2})(?=.)/gi, '$1:').toUpperCase();
			}
			// Base64-encoded binary (SoftEther JSON-RPC _bin fields)
			try {
				var raw = atob(val);
				if (raw.length === 6) {
					return Array.from(raw).map(function(c) {
						return ('0' + c.charCodeAt(0).toString(16)).slice(-2).toUpperCase();
					}).join(':');
				}
			} catch(e) {}
			return val;
		}
	}
	if (/ip/i.test(key) && typeof val === 'number') {
		return [(val>>>24)&255,(val>>>16)&255,(val>>>8)&255,val&255].join('.');
	}
	if (/time|date/i.test(key)) {
		// ISO 8601 string (SoftEther _dt fields)
		if (typeof val === 'string' && val.indexOf('T') !== -1) {
			var d = new Date(val);
			if (!isNaN(d.getTime())) { return d.toLocaleString(); }
		}
		// Unix timestamp (seconds)
		if (!isNaN(val) && Number(val) > 0) {
			var d2 = new Date(Number(val) * 1000);
			if (!isNaN(d2.getTime())) { return d2.toLocaleString(); }
		}
	}
	return String(val);
}

function fmtBytes(n) {
	if (n == null || isNaN(n)) { return '-'; }
	if (n >= 1073741824) { return (n/1073741824).toFixed(2) + ' GB'; }
	if (n >= 1048576)    { return (n/1048576).toFixed(2) + ' MB'; }
	if (n >= 1024)       { return (n/1024).toFixed(1) + ' KB'; }
	return n + ' B';
}

function fmtDuration(sec) {
	if (!sec || isNaN(sec)) { return '-'; }
	var d = Math.floor(sec/86400), h = Math.floor((sec%86400)/3600), m = Math.floor((sec%3600)/60), s = sec%60;
	return (d?d+'d ':'') + (h?h+'h ':'') + (m?m+'m ':'') + s + 's';
}

// ---- DOM shorthands ----

function $id(id) { return document.getElementById(id); }

// Trimmed string value of an input; '' if the element is missing.
function val(id) {
	var el = $id(id);
	return el ? String(el.value || '').trim() : '';
}

// Untrimmed value (passwords may contain significant whitespace).
function rawVal(id) {
	var el = $id(id);
	return el ? (el.value || '') : '';
}

// Integer value of an input; def when empty, invalid or zero.
function intVal(id, def) {
	return parseInt(rawVal(id), 10) || def || 0;
}

function isChecked(id) {
	var el = $id(id);
	return !!(el && el.checked);
}

// Set input value / checkbox state, ignoring missing elements.
function setVal(id, v) { var el = $id(id); if (el) { el.value = v; } }
function setChecked(id, on) { var el = $id(id); if (el) { el.checked = !!on; } }

// ---- API result helpers ----

// Human-readable detail from a call() result.
function apiDetail(r) {
	var d = r && r.detail;
	if (d == null) { return 'API not available'; }
	return (typeof d === 'string') ? d : JSON.stringify(d);
}

function showApiError(prefix, r) {
	alert(prefix + ': ' + apiDetail(r));
}

// Extract an array payload from a call() result, trying each given key in order.
function resultList(r) {
	if (r.error || !r.result) { return []; }
	for (var i = 1; i < arguments.length; i++) {
		if (Array.isArray(r.result[arguments[i]])) { return r.result[arguments[i]]; }
	}
	return [];
}

// Confirm (optional), run one method (or a tryMethods chain), report errors,
// then refresh. The workhorse behind the delete/toggle action buttons.
async function confirmCall(message, methods, params, errorMsg, refresh) {
	if (message && !confirm(message)) { return; }
	var r = Array.isArray(methods) ? await tryMethods(methods, params) : await call(methods, params);
	if (r.error) { showApiError(errorMsg, r); return; }
	if (refresh) { await refresh(); }
}

// ---- Table rendering ----

function emptyRow(colspan, msg) {
	return '<tr><td colspan="' + colspan + '" class="text-muted">' + escapeHtml(msg) + '</td></tr>';
}

// Fill a tbody from a list; shows emptyMsg when the list is empty.
// Optional headerHtml replaces the table's <thead> content.
function fillTable(tbodyId, list, colspan, emptyMsg, rowFn, headerHtml) {
	var tbody = $id(tbodyId);
	if (!tbody) { return; }
	if (headerHtml !== undefined) {
		var thead = tbody.parentNode.querySelector('thead');
		if (thead) { thead.innerHTML = headerHtml; }
	}
	tbody.innerHTML = list.map(rowFn).join('') || emptyRow(colspan, emptyMsg);
}

// Rows for a two-column key/value table (se-kv-table CSS styles the cells).
function kvRows(rows, labelWidth) {
	var style = labelWidth ? ' style="width:' + labelWidth + ';"' : '';
	return rows.map(function(r) {
		return '<tr><td' + style + '>' + escapeHtml(String(r[0])) + '</td><td>' + escapeHtml(String(r[1])) + '</td></tr>';
	}).join('');
}

// <option> list for hubs returned by EnumHub.
function hubOptionsHtml(hubs, placeholder) {
	return (placeholder ? '<option value="">' + escapeHtml(placeholder) + '</option>' : '') +
		hubs.map(function(h) {
			var n = h.HubName_str || h.Name_str || h.HubName_utf || '';
			return n ? '<option value="' + escapeHtml(n) + '">' + escapeHtml(n) + '</option>' : '';
		}).join('');
}

// ---- Browser helpers ----

// Trigger a download of generated content.
function downloadBlob(data, filename, mime) {
	var blob = (data instanceof Blob) ? data : new Blob([data], { type: mime || 'text/plain' });
	var a = document.createElement('a');
	a.href = URL.createObjectURL(blob);
	a.download = filename;
	document.body.appendChild(a);
	a.click();
	document.body.removeChild(a);
	URL.revokeObjectURL(a.href);
}

// pfSense CSRF field (csrf-magic injects these globals into every page).
function csrfField() {
	return {
		name:  (typeof csrfMagicName  !== 'undefined') ? csrfMagicName  : '__csrf_magic',
		token: (typeof csrfMagicToken !== 'undefined') ? csrfMagicToken : ''
	};
}

// POST body for the API proxy: CSRF token + JSON-RPC payload (+ extra fields).
function proxyBody(method, params, extra) {
	var csrf = csrfField();
	var body = new URLSearchParams();
	if (csrf.token) { body.append(csrf.name, csrf.token); }
	Object.keys(extra || {}).forEach(function(k) { body.append(k, extra[k]); });
	body.append('payload', JSON.stringify({ jsonrpc:'2.0', id:String(Date.now()), method:method, params:params || {} }));
	return body;
}

// ============================================================
// AUTO-REFRESH
// ============================================================

/**
 * Periodic refresh that skips ticks while the page is hidden or a
 * previous refresh is still running.
 * Usage: var r = new AutoRefresher(fn, seconds); r.start(); r.stop();
 */
function AutoRefresher(fn, intervalSeconds) {
	this._fn = fn;
	this._interval = (intervalSeconds || 5) * 1000;
	this._timer = null;
	this._busy = false;
}
AutoRefresher.prototype.start = function() {
	this.stop();
	var self = this;
	this._timer = setInterval(function() {
		if (document.hidden || self._busy) { return; }
		self._busy = true;
		Promise.resolve(self._fn()).catch(function(err) {
			console.error('[AutoRefresher]', err);
		}).then(function() {
			self._busy = false;
		});
	}, this._interval);
};
AutoRefresher.prototype.stop = function() {
	if (this._timer) { clearInterval(this._timer); this._timer = null; }
};

// One refresh pass over every server-level panel plus the active hub.
function refreshServerPanels() {
	return Promise.all([
		refreshServerStatus(), refreshListeners(), refreshIpsec(), refreshOpenVpnSstp(),
		refreshSyslog(), refreshServerCert(), refreshKeepAlive(), refreshAzureIcmpDns(),
		refreshServerCipher(), refreshL3Switches(), refreshEtherIpClients(), refreshHubData()
	]);
}

var cascadeRefresher = new AutoRefresher(refreshCascadeTable, 5);
var pageRefresher    = new AutoRefresher(refreshServerPanels, 15);

// ============================================================
// API
// ============================================================

async function call(method, params) {
	var user = val('apiUser') || 'Administrator';
	var pass = rawVal('apiPass');
	try {
		var res = await fetch('/vpn_softether5_api_proxy.php', {
			method: 'POST',
			headers: { Authorization: 'Basic ' + btoa(user + ':' + pass) },
			credentials: 'same-origin',
			body: proxyBody(method, params)
		});
		var data = await res.json();
		if (!res.ok || data.error) { return { error:true, detail: data.detail || data.error || data }; }
		return data;
	} catch(e) {
		return { error:true, detail:String(e) };
	}
}

async function tryMethods(methods, params) {
	for (var i = 0; i < methods.length; i++) {
		var r = await call(methods[i], params);
		if (!r.error) { return r; }
	}
	return { error:true };
}

// ============================================================
// CONNECTION UI
// ============================================================

function setConnectStatus(ok, text) {
	var el = document.getElementById('connectStatus');
	el.className = 'label ' + (text === 'Not connected' ? 'label-default' : (ok ? 'label-success' : 'label-danger'));
	el.textContent = text;
}

function setConnectedUi(connected) {
	['tabNavServer','tabNavHub'].forEach(function(id) {
		var nav = document.getElementById(id);
		if (!nav) { return; }
		var a = nav.querySelector('a');
		// 'disabled' class is what show.bs.tab checks to block activation
		nav.classList.toggle('disabled', !connected);
		if (a) {
			a.setAttribute('aria-disabled', connected ? 'false' : 'true');
			a.tabIndex = connected ? 0 : -1;
		}
	});
	var connectBtn    = document.getElementById('connectBtn');
	var disconnectBtn = document.getElementById('disconnectBtn');
	if (connectBtn)    { connectBtn.style.display    = connected ? 'none' : ''; }
	if (disconnectBtn) { disconnectBtn.style.display = connected ? '' : 'none'; }
	['btnRebootServer','btnExportConfig','btnImportConfig','btnFlushLog'].forEach(function(id) {
		var btn = document.getElementById(id);
		if (btn) { btn.disabled = !connected; }
	});
	document.getElementById('srvNotConnected').style.display = connected ? 'none'  : 'block';
	document.getElementById('srvPanel').style.display        = connected ? 'block' : 'none';
	document.getElementById('hubNotConnected').style.display = connected ? 'none'  : 'block';
	document.getElementById('dynamicArea').style.display     = connected ? 'block' : 'none';
	if (!connected) {
		if (window.jQuery && typeof $ === 'function') {
			$('a[href="#tabStatus"]').tab('show');
		} else {
			document.addEventListener('DOMContentLoaded', function() {
				if (window.jQuery && typeof $ === 'function') {
					$('a[href="#tabStatus"]').tab('show');
				}
			});
		}
	}
}

function disconnectApp() {
	setConnectStatus(false, 'Not connected');
	setConnectedUi(false);
	cascadeRefresher.stop();
	pageRefresher.stop();
}

// ============================================================
// SERVER STATUS
// ============================================================

function renderServerStatusTable(sr) {
	if (!sr) {
		document.getElementById('srvInfo').innerHTML = emptyRow(2, 'Server status unavailable.');
		return;
	}
	var srvTypeNames = {0:'Standalone Server', 1:'Farm Controller', 2:'Farm Member'};
	function n(v) { var i = parseInt(v, 10); return (isNaN(i) || i < 0) ? 0 : i; }
	// Normalise field names: HTTP API uses different keys than CLI
	// Server Type: API=ServerType_u32 (int), CLI=ServerType_str
	var srvType = sr.ServerType_str || srvTypeNames[sr.ServerType_u32] || '-';
	// Packet counts: API=Send.UnicastCount_u64, CLI=Send.UnicastPackets_u64
	var sendUniPkts  = n(sr['Send.UnicastPackets_u64']  ?? sr['Send.UnicastCount_u64']);
	var sendBrdPkts  = n(sr['Send.BroadcastPackets_u64'] ?? sr['Send.BroadcastCount_u64']);
	var recvUniPkts  = n(sr['Recv.UnicastPackets_u64']  ?? sr['Recv.UnicastCount_u64']);
	var recvBrdPkts  = n(sr['Recv.BroadcastPackets_u64'] ?? sr['Recv.BroadcastCount_u64']);
	// License fields: API=AssignedClientLicenses_u32, CLI=AssignedClientLicenseCount_u32
	var clientLic = n(sr.AssignedClientLicenseCount_u32 ?? sr.AssignedClientLicenses_u32);
	var bridgeLic = n(sr.AssignedBridgeLicenseCount_u32 ?? sr.AssignedBridgeLicenses_u32);
	// Start time: API=StartTime_dt (ISO), CLI=ServerStartedAt_str (formatted)
	var startedAt = sr.ServerStartedAt_str || (sr.StartTime_dt ? sr.StartTime_dt.replace('T',' ').replace('.000Z','') : '-');
	// Current time: CLI=CurrentTime_str, API=not returned → use JS clock
	var currentTime = sr.CurrentTime_str || new Date().toISOString().replace('T',' ').replace('Z','');
	// Uptime: CLI=ServerUpTime_u32 (seconds), API=compute from StartTime_dt
	var uptimeSec = sr.ServerUpTime_u32 ?? sr.ServerUptime_u32;
	if ((uptimeSec === undefined || uptimeSec === null) && sr.StartTime_dt) {
		uptimeSec = Math.floor((Date.now() - new Date(sr.StartTime_dt).getTime()) / 1000);
	}
	// Logic clock: CLI only
	var logicClock = (sr.LogicClock_u64 !== undefined && sr.LogicClock_u64 !== null) ? String(sr.LogicClock_u64) : (sr.CurrentTick_u64 !== undefined ? String(sr.CurrentTick_u64) : '-');

	var rows = [
		['Server Type',                                    srvType],
		['Number of Active Sockets',                       n(sr.NumActiveSockets_u32)],
		['Number of Virtual Hubs',                         n(sr.NumHubTotal_u32 ?? sr.NumHubsTotalCount_u32)],
		['Number of Sessions',                             n(sr.NumSessionsTotal_u32 ?? sr.NumSessionsTotal)],
		['Number of MAC Address Tables',                   n(sr.NumMacTables_u32)],
		['Number of IP Address Tables',                    n(sr.NumIpTables_u32)],
		['Number of Users',                                n(sr.NumUsers_u32)],
		['Number of Groups',                               n(sr.NumGroups_u32)],
		['Using Client Connection Licenses (This Server)', clientLic],
		['Using Bridge Connection Licenses (This Server)', bridgeLic],
		['Outgoing Unicast Packets',                       sendUniPkts.toLocaleString() + ' packets'],
		['Outgoing Unicast Total Size',                    n(sr['Send.UnicastBytes_u64']).toLocaleString() + ' bytes'],
		['Outgoing Broadcast Packets',                     sendBrdPkts.toLocaleString() + ' packets'],
		['Outgoing Broadcast Total Size',                  n(sr['Send.BroadcastBytes_u64']).toLocaleString() + ' bytes'],
		['Incoming Unicast Packets',                       recvUniPkts.toLocaleString() + ' packets'],
		['Incoming Unicast Total Size',                    n(sr['Recv.UnicastBytes_u64']).toLocaleString() + ' bytes'],
		['Incoming Broadcast Packets',                     recvBrdPkts.toLocaleString() + ' packets'],
		['Incoming Broadcast Total Size',                  n(sr['Recv.BroadcastBytes_u64']).toLocaleString() + ' bytes'],
		['Server Started at',                              startedAt],
		['Current Time',                                   currentTime],
		['64 bit High-Precision Logical System Clock',     logicClock],
		['Up Time',                                        fmtDuration(Math.max(0, parseInt(uptimeSec, 10)))],
	];
	document.getElementById('srvInfo').innerHTML = kvRows(rows, '60%');
}

async function refreshServerStatus() {
	var r1 = await call('GetServerStatus');
	var r2 = await call('GetServerInfo');
	var merged = Object.assign({}, (r2.error ? {} : r2.result), (r1.error ? {} : r1.result));
	if (!r1.error || !r2.error) { renderServerStatusTable(merged); }
}

// ============================================================
// INIT
// ============================================================

async function initApp() {
	setConnectStatus(false, 'Connecting...');
	var srv = await call('GetServerStatus');
	if (srv.error || !srv.result) {
		setConnectStatus(false, 'Connection failed');
		setConnectedUi(false);
		alert('API connection error. Check password and vpnserver service.');
		return;
	}
	setConnectStatus(true, 'Connected');
	setConnectedUi(true);
	$('a[href="#tabServer"]').tab('show');
	var srvInfo = await call('GetServerInfo');
	renderServerStatusTable(Object.assign({}, (srvInfo.error ? {} : srvInfo.result), srv.result));

	var hubs = await call('EnumHub');
	if (hubs.error || !hubs.result || !Array.isArray(hubs.result.HubList)) {
		alert('Unable to read hub list.');
		return;
	}

	var hubList = hubs.result.HubList;
	document.getElementById('hubSelector').innerHTML = hubOptionsHtml(hubList);

	// Sync hub list into TAP/bridge modal
	var tapHubSel = document.getElementById('newLocalBridgeHub');
	if (tapHubSel) {
		tapHubSel.innerHTML = hubOptionsHtml(hubList, '(Select a hub...)');
	}

	currentHub = document.getElementById('hubSelector').value || '';
	await refreshServerPanels();

	cascadeRefresher.start();
	pageRefresher.start();
	await refreshInterfaceTable();
}

setConnectedUi(false);

// ============================================================
// HUB DATA
// ============================================================

async function refreshHubData() {
	currentHub = document.getElementById('hubSelector').value || '';
	if (!currentHub) {
		clearHubTables();
		return;
	}
	await Promise.all([
		refreshHubInfo(), refreshUsers(), refreshGroups(),
		refreshSessions(), refreshMacTable(), refreshIpTable(),
		refreshCascadeTable(), refreshInterfaceTable(),
		refreshAccessList(), refreshSecureNAT(), refreshHubRadius(), refreshHubLog(),
		refreshConnTable(), refreshCaList()
	]);
}

async function refreshHubInfo() {
	if (!currentHub) { return; }
	var params = { HubName_str: currentHub };
	var r1 = await call('GetHubStatus', params);
	var r2 = await call('GetHub', params);
	if (r1.error && r2.error) { return; }

	// Merge: GetHubStatus has rich stats; GetHub has MaxSession/NoEnum/HubType
	var h = Object.assign({}, (r2.error ? {} : r2.result), (r1.error ? {} : r1.result));

	function hv() {
		for (var i = 0; i < arguments.length; i++) {
			var v = h[arguments[i]];
			if (v !== undefined && v !== null && v !== '') { return v; }
		}
	}
	var hubTypeNames = {0:'Standalone Virtual Hub', 1:'Static Virtual Hub', 2:'Dynamic Virtual Hub'};
	var rows = [];
	function pushRaw(label, val) { if (val !== undefined && val !== null && val !== '') { rows.push([label, val]); } }
	function push(label, val) { pushRaw(label, val !== undefined && val !== null ? String(val) : undefined); }

	var onlineRaw = hv('Online_bool');
	var isOnline  = onlineRaw === true || onlineRaw === 'true' || onlineRaw === 1;
	var isOffline = onlineRaw === false || onlineRaw === 'false' || onlineRaw === 0;

	// Update online badge in hub selector bar
	var badge = document.getElementById('hubOnlineBadge');
	if (badge) {
		if (isOnline)       { badge.className = 'label label-success'; badge.textContent = 'Online'; }
		else if (isOffline) { badge.className = 'label label-warning'; badge.textContent = 'Offline'; }
		else                { badge.className = 'label label-default'; badge.textContent = ''; }
	}

	var hubTypeRaw = hv('HubType_u32');
	var hubTypeStr = (hubTypeRaw !== undefined && hubTypeNames[hubTypeRaw]) ? hubTypeNames[hubTypeRaw] : hv('HubType_str','Type_str');
	var maxSess    = hv('MaxSession_u32','MaxSessionCount_u32');
	var secNat     = hv('SecureNATEnabled_bool','SecureNAT_bool');
	var noEnum     = hv('NoEnum_bool');

	push('Virtual Hub Name',           hv('HubName_str','Name_str'));
	push('Status',                     isOnline ? 'Online' : (isOffline ? 'Offline' : hv('Status_str','State_str')));
	push('Type',                       hubTypeStr);
	push('Max Sessions',               maxSess !== undefined && maxSess !== null ? (maxSess === 0 ? 'Unlimited' : maxSess) : undefined);
	push('SecureNAT',                  secNat !== undefined ? (secNat ? 'Enabled' : 'Disabled') : undefined);
	push('Anonymous Enum',             noEnum !== undefined ? (noEnum ? 'Deny' : 'Allow') : undefined);
	push('Number of Users',            hv('NumUsers_u32'));
	push('Number of Groups',           hv('NumGroups_u32'));
	push('Number of Sessions',         hv('NumSessions_u32'));
	push('Sessions (Client)',          hv('NumSessionsClient_u32'));
	push('Sessions (Bridge)',          hv('NumSessionsBridge_u32'));
	push('Number of Logins',           hv('NumLogin_u32'));
	push('Number of Access Rules',     hv('NumAccessLists_u32'));
	push('MAC Table Entries',          hv('NumMacTables_u32'));
	push('IP Table Entries',           hv('NumIpTables_u32'));

	var sendUni  = hv('Send.UnicastBytes_u64')  || 0;
	var sendBrd  = hv('Send.BroadcastBytes_u64') || 0;
	var recvUni  = hv('Recv.UnicastBytes_u64')  || 0;
	var recvBrd  = hv('Recv.BroadcastBytes_u64') || 0;
	if (sendUni || sendBrd || recvUni || recvBrd) {
		pushRaw('Send (Unicast / Broadcast)',   fmtBytes(sendUni) + ' / ' + fmtBytes(sendBrd));
		pushRaw('Recv (Unicast / Broadcast)',   fmtBytes(recvUni) + ' / ' + fmtBytes(recvBrd));
	}

	push('Created',       hv('CreatedTime_dt')   ? humanize('CreatedTime_dt',   hv('CreatedTime_dt'))   : undefined);
	push('Last Login',    hv('LastLoginTime_dt')  ? humanize('LastLoginTime_dt', hv('LastLoginTime_dt')) : undefined);
	push('Last Comm',     hv('LastCommTime_dt')   ? humanize('LastCommTime_dt',  hv('LastCommTime_dt'))  : undefined);

	if (!rows.length) { rows.push(['Hub Name', currentHub]); }

	document.getElementById('hubInfo').innerHTML = kvRows(rows, '45%');
}

function clearHubTables() {
	// tbody id -> colspan of its "No hub selected." placeholder row
	var empties = {
		hubInfo: 2, userTable: 7, groupTable: 5, sessionTable: 10, macTable: 6,
		ipTable: 7, connTable: 6, cascadeTable: 7, ifTable: 5, accessTable: 10,
		natTable: 6, dhcpTable: 5, caTable: 3, adminOptionsTable: 3,
		extOptionsTable: 3, crlTable: 4, wgkTable: 3
	};
	Object.keys(empties).forEach(function(id) {
		var el = document.getElementById(id);
		if (el) { el.innerHTML = emptyRow(empties[id], 'No hub selected.'); }
	});
	var badge = document.getElementById('hubOnlineBadge');
	if (badge) { badge.className = 'label'; badge.textContent = ''; }
	var natCtl = document.getElementById('secureNATControls');
	if (natCtl) { natCtl.style.display = 'none'; }
}

// ============================================================
// SESSIONS / MAC / IP TABLES
// ============================================================

async function refreshSessions() {
	var s = await call('EnumSession', { HubName_str: currentHub });
	var now = Math.floor(Date.now()/1000);
	fillTable('sessionTable', resultList(s, 'SessionList'), 10, 'No active sessions.', function(sess) {
		var name      = sess.Name_str || sess.SessionName_str || '-';
		var user      = sess.Username_str || sess.AuthUsername_str || '-';
		var srcIp     = humanize('ClientIp_ip', sess.ClientIp_ip !== undefined ? sess.ClientIp_ip : sess.RemoteIP_ip);
		var tcpConns  = (sess.NumTcpConnections_u32 !== undefined ? sess.NumTcpConnections_u32 : '-');
		var inBytes   = fmtBytes(sess.TotalIncomingBytes_u64 !== undefined ? sess.TotalIncomingBytes_u64 : sess.TotalRecvBytes_u64);
		var outBytes  = fmtBytes(sess.TotalOutgoingBytes_u64 !== undefined ? sess.TotalOutgoingBytes_u64 : sess.TotalSendBytes_u64);
		var connTime  = sess.ConnectionTime_dt || sess.CreatedTime_dt || sess.StartTime_u32;
		var durSec    = connTime ? (now - Number(connTime)) : null;
		var duration  = durSec != null && durSec > 0 ? fmtDuration(durSec) : '-';
		var clientName= sess.ClientProductName_str || '-';
		var encrypt   = sess.DataEncryptAlgorithm_str || (sess.UseEncrypt_bool ? 'Yes' : 'No');
		var nameEsc   = escapeAttr(name);
		return '<tr>' +
			'<td><code>' + escapeHtml(name) + '</code></td>' +
			'<td>' + escapeHtml(user) + '</td>' +
			'<td>' + escapeHtml(srcIp) + '</td>' +
			'<td>' + tcpConns + '</td>' +
			'<td>' + inBytes + '</td>' +
			'<td>' + outBytes + '</td>' +
			'<td>' + duration + '</td>' +
			'<td>' + escapeHtml(clientName) + '</td>' +
			'<td>' + escapeHtml(encrypt) + '</td>' +
			'<td><button class="btn btn-xs btn-danger" onclick="killSession(\'' + nameEsc + '\')" title="Disconnect"><i class="fa fa-times"></i></button></td>' +
			'</tr>';
	}, '<tr><th>Session Name</th><th>User Name</th><th>Source IP</th><th>TCP Conns</th><th>Transfer (In)</th><th>Transfer (Out)</th><th>Duration</th><th>Client Name</th><th>Encryption</th><th>Actions</th></tr>');
}

async function refreshMacTable() {
	var m = await call('EnumMacTable', { HubName_str: currentHub });
	fillTable('macTable', resultList(m, 'MacTable'), 6, 'No MAC entries.', function(row) {
		var mac = humanize('MacAddress_str', row.MacAddress_bin || row.MacAddress_str || '-');
		var macEsc = escapeAttr(mac);
		return '<tr>' +
			'<td><code>' + escapeHtml(row.SessionName_str || '-') + '</code></td>' +
			'<td><code>' + escapeHtml(mac) + '</code></td>' +
			'<td>' + escapeHtml(humanize('CreatedTime_dt', row.CreatedTime_dt)) + '</td>' +
			'<td>' + escapeHtml(humanize('UpdatedTime_dt', row.UpdatedTime_dt)) + '</td>' +
			'<td>' + (row.RemoteItem_bool ? '<span class="label label-info">Yes</span>' : 'No') + '</td>' +
			'<td><button class="btn btn-xs btn-danger" onclick="deleteMacEntry(\'' + macEsc + '\')" title="Delete entry"><i class="fa fa-trash"></i></button></td>' +
			'</tr>';
	}, '<tr><th>Session Name</th><th>MAC Address</th><th>Created</th><th>Updated</th><th>Remote</th><th>Actions</th></tr>');
}

function deleteMacEntry(mac) {
	return confirmCall('Delete MAC table entry for ' + mac + '?', 'DeleteMacTable',
		{ HubName_str: currentHub, MacAddress_str: mac }, 'Error deleting MAC entry', refreshMacTable);
}

async function refreshIpTable() {
	var i = await call('EnumIpTable', { HubName_str: currentHub });
	fillTable('ipTable', resultList(i, 'IpTable'), 7, 'No IP entries.', function(row) {
		var ip = row.IpAddress_ip !== undefined ? humanize('IpAddress_ip', row.IpAddress_ip) : (row.IpAddress_str || '-');
		var ipEsc = escapeAttr(ip);
		return '<tr>' +
			'<td><code>' + escapeHtml(row.SessionName_str || '-') + '</code></td>' +
			'<td>' + escapeHtml(ip) + '</td>' +
			'<td>' + escapeHtml(humanize('CreatedTime_dt', row.CreatedTime_dt)) + '</td>' +
			'<td>' + escapeHtml(humanize('UpdatedTime_dt', row.UpdatedTime_dt)) + '</td>' +
			'<td>' + (row.RemoteItem_bool ? '<span class="label label-info">Yes</span>' : 'No') + '</td>' +
			'<td>' + (row.DhcpTable_bool  ? '<span class="label label-success">Yes</span>' : 'No') + '</td>' +
			'<td><button class="btn btn-xs btn-danger" onclick="deleteIpEntry(\'' + ipEsc + '\')" title="Delete entry"><i class="fa fa-trash"></i></button></td>' +
			'</tr>';
	}, '<tr><th>Session Name</th><th>IP Address</th><th>Created</th><th>Updated</th><th>Remote</th><th>DHCP</th><th>Actions</th></tr>');
}

function deleteIpEntry(ip) {
	return confirmCall('Delete IP table entry for ' + ip + '?', 'DeleteIpTable',
		{ HubName_str: currentHub, IpAddress_str: ip }, 'Error deleting IP entry', refreshIpTable);
}

// ============================================================
// USERS & GROUPS
// ============================================================

var AUTH_TYPE_NAMES = {0:'Anonymous', 1:'Password Auth.', 2:'Individual Cert.', 3:'Signed Cert.', 4:'RADIUS / NT Domain', 5:'NT Domain'};
async function refreshUsers() {
	var u = await call('EnumUser', { HubName_str: currentHub });
	fillTable('userTable', resultList(u, 'UserList'), 12, 'No users.', function(usr) {
		var authType = usr.AuthType_u32;
		var authStr = (authType !== undefined && AUTH_TYPE_NAMES[authType]) ? AUTH_TYPE_NAMES[authType] : (authType !== undefined ? String(authType) : '-');
		var lastLogin = usr.LastLoginTime_dt || usr.LastLoginTime_u32;
		var lastLoginStr = lastLogin ? humanize('LastLoginTime_dt', lastLogin) : 'Never';
		var sendBytes = (usr['Ex.Send.UnicastBytes_u64'] || 0) + (usr['Ex.Send.BroadcastBytes_u64'] || 0);
		var recvBytes = (usr['Ex.Recv.UnicastBytes_u64'] || 0) + (usr['Ex.Recv.BroadcastBytes_u64'] || 0);
		var expires = (usr.IsExpiresFilled_bool && usr.Expires_dt && usr.Expires_dt !== '1970-01-01T09:00:00.000Z')
			? humanize('Expires_dt', usr.Expires_dt) : '-';
		var denyStr = usr.DenyAccess_bool
			? '<span class="label label-danger">Deny</span>'
			: '<span class="label label-success">Allow</span>';
		return '<tr>' +
			'<td><strong>' + escapeHtml(usr.Name_str || '-') + '</strong></td>' +
			'<td>' + escapeHtml(usr.Realname_utf || usr.RealName_utf || usr.RealName_str || '-') + '</td>' +
			'<td>' + escapeHtml(usr.GroupName_str || '-') + '</td>' +
			'<td><span class="label label-default">' + escapeHtml(authStr) + '</span></td>' +
			'<td class="text-right">' + escapeHtml(String(usr.NumLogin_u32 !== undefined ? usr.NumLogin_u32 : '-')) + '</td>' +
			'<td>' + escapeHtml(lastLoginStr) + '</td>' +
			'<td class="text-right"><small>' + fmtBytes(sendBytes) + '</small></td>' +
			'<td class="text-right"><small>' + fmtBytes(recvBytes) + '</small></td>' +
			'<td><small>' + escapeHtml(usr.Note_utf || usr.Note_str || '') + '</small></td>' +
			'<td>' + denyStr + '</td>' +
			'<td><small>' + escapeHtml(expires) + '</small></td>' +
			'<td style="white-space:nowrap;">' +
			'<button class="btn btn-xs btn-warning" onclick="editUser(\'' + escapeAttr(usr.Name_str||'') + '\')" title="Edit" style="margin-right:2px;"><i class="fa fa-edit"></i></button>' +
			'<button class="btn btn-xs btn-danger" onclick="delUser(\'' + escapeAttr(usr.Name_str||'') + '\')" title="Delete"><i class="fa fa-trash"></i></button>' +
			'</td>' +
			'</tr>';
	});
}

async function refreshGroups() {
	var g = await tryMethods(['EnumGroup','ListGroup'], { HubName_str: currentHub });
	groupsList = resultList(g, 'GroupList');
	fillTable('groupTable', groupsList, 5, 'No groups.', function(grp) {
		var n = escapeAttr(grp.Name_str || '');
		return '<tr>' +
			'<td>' + escapeHtml(grp.Name_str || '-') + '</td>' +
			'<td>' + escapeHtml(grp.Realname_utf || grp.RealName_str || '-') + '</td>' +
			'<td><small>' + escapeHtml(grp.Note_utf || grp.Note_str || '') + '</small></td>' +
			'<td>' + escapeHtml(String(grp.NumUsers_u32 !== undefined ? grp.NumUsers_u32 : '-')) + '</td>' +
			'<td style="white-space:nowrap;">' +
			'<button class="btn btn-xs btn-warning" onclick="editGroup(\'' + n + '\')" title="Edit" style="margin-right:2px;"><i class="fa fa-edit"></i></button>' +
			'<button class="btn btn-xs btn-danger"  onclick="delGroup(\'' + n + '\')"  title="Delete"><i class="fa fa-trash"></i></button>' +
			'</td></tr>';
	});
}

async function createUser() {
	var name   = val('newUserName');
	var pass = rawVal('newUserPass');
	var real   = val('newUserReal');
	var note   = val('newUserNote');
	var group = rawVal('newUserGroup');
	var expiry = rawVal('newUserExpiry');
	if (!name) { alert('Username is required.'); return; }

	var expiryTs = 0;
	if (expiry) { var d = new Date(expiry); expiryTs = Math.floor(d.getTime() / 1000); }

	var r = await call('CreateUser', {
		HubName_str: currentHub,
		Name_str: name,
		RealName_utf: real,
		RealName_str: real,
		Note_utf: note,
		Note_str: note,
		GroupName_str: group,
		AuthType_u32: parseInt(rawVal('newUserAuthType'), 10),
		AuthData: { Password_str: pass },
		Expires_u64: expiryTs
	});
	if (r.error) { alert('Error creating user.'); return; }

	if (pass) {
		var rp = await call('SetUserPassword', { HubName_str: currentHub, Name_str: name, Password_str: pass });
		if (rp.error && rp.detail && String(rp.detail).indexOf('already') === -1) {
			console.warn('[createUser] password set warning:', rp.detail);
		}
	}

	$('#createUserModal').modal('hide');
	['newUserName','newUserPass','newUserReal','newUserNote','newUserExpiry'].forEach(function(id){ document.getElementById(id).value=''; });
	setVal('newUserGroup', '');
	await refreshUsers();
}

function delUser(name) {
	return confirmCall("Delete user '" + name + "'?", 'DeleteUser',
		{ HubName_str:currentHub, Name_str:name }, 'Error deleting user', refreshUsers);
}

// ============================================================
// SECURITY POLICY
// ============================================================
var SE_POLICIES = [
	{ name:'Access',        label:'Allow Access',                                       desc:'The users defined in this policy have permission to make VPN connections to VPN Server.',       type:'bool' },
	{ name:'DHCPFilter',    label:'Filter DHCP Packets (IPv4)',                         desc:'Filter DHCP packets sent by the client on the virtual network.',                                type:'bool' },
	{ name:'DHCPNoServer',  label:'Disallow DHCP Server Operation (IPv4)',              desc:'Stops the user from operating a DHCP server on the virtual network.',                          type:'bool' },
	{ name:'DHCPForce',     label:'Enforce DHCP Allocated IP Addresses (IPv4)',         desc:'Requires the client to use an IP address allocated by the DHCP server.',                       type:'bool' },
	{ name:'NoBridge',      label:'Deny Bridge Operation',                              desc:'Prevents the user from using bridge mode.',                                                    type:'bool' },
	{ name:'NoRouting',     label:'Deny Routing Operation (IPv4)',                      desc:'Prevents the user from using the VPN client as an IPv4 router.',                               type:'bool' },
	{ name:'CheckMac',      label:'Deny MAC Addresses Duplication',                     desc:'Denies sessions that attempt to use duplicate MAC addresses.',                                 type:'bool' },
	{ name:'CheckIP',       label:'Deny IP Address Duplication (IPv4)',                 desc:'Denies sessions that attempt to use duplicate IPv4 addresses.',                               type:'bool' },
	{ name:'ArpDhcpOnly',   label:'Deny Non-ARP / Non-DHCP / Non-ICMPv6 broadcasts',   desc:'Allows only ARP, DHCP, and ICMPv6 broadcast packets.',                                       type:'bool' },
	{ name:'PrivacyFilter', label:'Privacy Filter Mode',                                desc:'Filters packets to prevent direct communication between VPN clients.',                        type:'bool' },
	{ name:'NoServer',      label:'Deny Operation as TCP/IP Server (IPv4)',             desc:'Prevents the user from running a TCP/IP server on the VPN.',                                  type:'bool' },
	{ name:'NoBroadcastLimiter', label:'Unlimited Number of Broadcasts',               desc:'Removes the limit on the number of broadcast packets.',                                       type:'bool' },
	{ name:'MonitorPort',   label:'Allow Monitoring Mode',                              desc:'Allows the client to monitor all packets on the virtual hub.',                                type:'bool' },
	{ name:'MaxConnection', label:'Maximum Number of TCP Connections',                  desc:'Limits the number of simultaneous TCP connections in the VPN session.',                      type:'num', unit:'' },
	{ name:'TimeOut',       label:'Time-out Period',                                    desc:'Sets the VPN session timeout in seconds.',                                                    type:'num', unit:'sec' },
	{ name:'MaxMac',        label:'Maximum Number of MAC Addresses',                    desc:'Limits the number of MAC addresses the client can register.',                               type:'num', unit:'' },
	{ name:'MaxIP',         label:'Maximum Number of IP Addresses (IPv4)',              desc:'Limits the number of IPv4 addresses the client can register.',                              type:'num', unit:'' },
	{ name:'MaxUpload',     label:'Upload Bandwidth',                                   desc:'Maximum upload bandwidth for this session (bps). 0 = unlimited.',                           type:'num', unit:'bps' },
	{ name:'MaxDownload',   label:'Download Bandwidth',                                 desc:'Maximum download bandwidth for this session (bps). 0 = unlimited.',                         type:'num', unit:'bps' },
	{ name:'FixPassword',   label:'Deny Changing Password',                             desc:'Prevents the user from changing their own password.',                                       type:'bool' },
	{ name:'MultiLogins',   label:'Maximum Number of Multiple Logins',                  desc:'Limits the number of simultaneous logins for this user/group. 0 = unlimited.',             type:'num', unit:'' },
	{ name:'NoQoS',         label:'Deny VoIP / QoS Function',                           desc:'Disables the QoS / VoIP priority function for this session.',                             type:'bool' },
	{ name:'RSandRAFilter', label:'Filter RS / RA Packets (IPv6)',                      desc:'Filters IPv6 Router Solicitation and Router Advertisement packets.',                       type:'bool' },
	{ name:'RAFilter',      label:'Filter RA Packets (IPv6)',                           desc:'Filters IPv6 Router Advertisement packets.',                                               type:'bool' },
	{ name:'DHCPv6Filter',  label:'Filter DHCP Packets (IPv6)',                         desc:'Filters DHCPv6 packets.',                                                                  type:'bool' },
	{ name:'DHCPv6NoServer',label:'Disallow DHCP Server Operation (IPv6)',              desc:'Stops the user from operating a DHCPv6 server.',                                          type:'bool' },
	{ name:'NoRoutingV6',   label:'Deny Routing Operation (IPv6)',                      desc:'Prevents the user from using the VPN client as an IPv6 router.',                          type:'bool' },
	{ name:'CheckIPv6',     label:'Deny IP Address Duplication (IPv6)',                 desc:'Denies sessions that attempt to use duplicate IPv6 addresses.',                           type:'bool' },
	{ name:'NoServerV6',    label:'Deny Operation as TCP/IP Server (IPv6)',             desc:'Prevents the user from running a TCP/IP server (IPv6) on the VPN.',                      type:'bool' },
	{ name:'MaxIPv6',       label:'Maximum Number of IP Addresses (IPv6)',              desc:'Limits the number of IPv6 addresses the client can register.',                           type:'num', unit:'' },
	{ name:'NoSavePassword',label:'Disallow Password Save in VPN Client',              desc:'Prevents the VPN client from saving the password locally.',                              type:'bool' },
	{ name:'AutoDisconnect',label:'VPN Client Automatic Disconnect',                   desc:'Automatically disconnects the session after this many seconds. 0 = disabled.',          type:'num', unit:'sec' },
	{ name:'FilterIPv4',    label:'Filter All IPv4 Packets',                            desc:'Drops all IPv4 packets sent by this session.',                                          type:'bool' },
	{ name:'FilterIPv6',    label:'Filter All IPv6 Packets',                            desc:'Drops all IPv6 packets sent by this session.',                                          type:'bool' },
	{ name:'FilterNonIP',   label:'Filter All Non-IP Packets',                          desc:'Drops all non-IP packets sent by this session.',                                        type:'bool' },
	{ name:'NoIPv6DefaultRouterInRA',        label:'No Default-Router on IPv6 RA',                       desc:'Removes the default router field from IPv6 RA packets.',                type:'bool' },
	{ name:'NoIPv6DefaultRouterInRAWhenIPv6',label:'No Default-Router on IPv6 RA (physical IPv6)',       desc:'Removes the default router field from IPv6 RA when using physical IPv6.', type:'bool' },
	{ name:'VLanId',        label:'VLAN ID (IEEE802.1Q)',                               desc:'Assigns a VLAN ID to packets sent by this session. 0 = disabled.',                     type:'num', unit:'' },
];

// _groupPolicies[prefix] = { PolicyName: value_or_null }  (null = remove/not set)
var _groupPolicies = { 'new': {}, 'edit': {} };
var _groupPolicySelected = { 'new': null, 'edit': null };

function initGroupPolicyUI(prefix, currentPolicies) {
	_groupPolicies[prefix] = {};
	_groupPolicySelected[prefix] = null;
	SE_POLICIES.forEach(function(p) {
		var raw = currentPolicies ? (currentPolicies[p.name] || null) : null;
		if (raw === null || raw === '-') {
			_groupPolicies[prefix][p.name] = null;
		} else if (p.type === 'bool') {
			_groupPolicies[prefix][p.name] = (raw === 'Yes' || raw === '1' || raw === 1) ? 1 : 0;
		} else {
			var n = parseInt(raw, 10);
			_groupPolicies[prefix][p.name] = isNaN(n) ? null : n;
		}
	});
	// Auto-enable toggle if any policy is set in the loaded data
	var hasAny = currentPolicies && Object.keys(currentPolicies).some(function(k) {
		return currentPolicies[k] && currentPolicies[k] !== '-';
	});
	var cb = document.getElementById(prefix + 'GroupPolicyEnabled');
	if (cb) { cb.checked = !!hasAny; }
	var content = document.getElementById(prefix + 'GroupPolicyContent');
	if (content) {
		content.style.pointerEvents = hasAny ? '' : 'none';
		content.style.opacity       = hasAny ? '1' : '0.45';
	}
	renderGroupPolicyList(prefix);
	document.getElementById(prefix + 'GroupPolicyDetail').innerHTML = '<p class="text-muted" style="font-size:12px;">Select a policy to configure it.</p>';
}

function onGroupPolicyToggle(prefix) {
	var cb = document.getElementById(prefix + 'GroupPolicyEnabled');
	var content = document.getElementById(prefix + 'GroupPolicyContent');
	var enabled = cb && cb.checked;
	if (content) {
		content.style.pointerEvents = enabled ? '' : 'none';
		content.style.opacity       = enabled ? '1' : '0.45';
	}
	if (!enabled) {
		// Clear selection and detail when disabling
		_groupPolicySelected[prefix] = null;
		document.getElementById(prefix + 'GroupPolicyDetail').innerHTML = '<p class="text-muted" style="font-size:12px;">Select a policy to configure it.</p>';
	}
}

function renderGroupPolicyList(prefix) {
	var tbody = document.getElementById(prefix + 'GroupPolicyList');
	tbody.innerHTML = SE_POLICIES.map(function(p, i) {
		var val = _groupPolicies[prefix][p.name];
		var valStr, valClass;
		if (val === null) { valStr = '-'; valClass = 'text-muted'; }
		else if (p.type === 'bool') { valStr = val ? 'Enabled' : 'Disabled'; valClass = val ? 'text-success' : ''; }
		else { valStr = String(val) + (p.unit ? ' ' + p.unit : ''); valClass = 'text-info'; }
		var sel = _groupPolicySelected[prefix] === p.name ? ' style="background:#d9edf7;"' : '';
		return '<tr' + sel + ' style="cursor:pointer;" onclick="selectGroupPolicy(\'' + prefix + '\',\'' + p.name + '\')">' +
			'<td style="font-size:12px;">' + escapeHtml(p.label) + '</td>' +
			'<td class="' + valClass + '" style="font-size:11px;white-space:nowrap;">' + escapeHtml(valStr) + '</td></tr>';
	}).join('');
}

function selectGroupPolicy(prefix, pname) {
	_groupPolicySelected[prefix] = pname;
	renderGroupPolicyList(prefix);
	var p = SE_POLICIES.find(function(x){ return x.name === pname; });
	if (!p) { return; }
	var val = _groupPolicies[prefix][pname];
	var detail = '<p><strong>' + escapeHtml(p.label) + '</strong></p>' +
		'<p class="text-muted" style="font-size:12px;">' + escapeHtml(p.desc) + '</p>' +
		'<hr style="margin:8px 0;">' +
		'<p><strong>Current Value:</strong></p>';
	if (p.type === 'bool') {
		var chkEn = (val === 1) ? ' checked' : '';
		var chkDis = (val === 0) ? ' checked' : '';
		var chkNone = (val === null) ? ' checked' : '';
		detail += '<div class="radio" style="margin:4px 0;"><label><input type="radio" name="pol_' + prefix + '_' + pname + '" value="1"' + chkEn + ' onchange="setGroupPolicyVal(\'' + prefix + '\',\'' + pname + '\',1)"> Enable the Policy</label></div>' +
			'<div class="radio" style="margin:4px 0;"><label><input type="radio" name="pol_' + prefix + '_' + pname + '" value="0"' + chkDis + ' onchange="setGroupPolicyVal(\'' + prefix + '\',\'' + pname + '\',0)"> Disable the Policy</label></div>' +
			'<div class="radio" style="margin:4px 0;"><label><input type="radio" name="pol_' + prefix + '_' + pname + '" value="null"' + chkNone + ' onchange="setGroupPolicyVal(\'' + prefix + '\',\'' + pname + '\',null)"> Not Set (inherit default)</label></div>';
	} else {
		var curNum = (val !== null) ? val : '';
		var enabledCheck = (val !== null) ? ' checked' : '';
		detail += '<div class="checkbox" style="margin:4px 0;"><label><input type="checkbox" id="polEn_' + prefix + '_' + pname + '"' + enabledCheck + ' onchange="onGroupPolicyNumToggle(\'' + prefix + '\',\'' + pname + '\')"> Enable this policy</label></div>' +
			'<div style="display:flex;align-items:center;gap:6px;margin-top:6px;">' +
			'<input type="number" id="polNum_' + prefix + '_' + pname + '" class="form-control" style="width:100px;height:28px;padding:2px 6px;font-size:13px;" value="' + escapeHtml(String(curNum)) + '" ' + (val === null ? 'disabled' : '') + ' oninput="setGroupPolicyNumVal(\'' + prefix + '\',\'' + pname + '\',this.value)">' +
			(p.unit ? '<span class="text-muted" style="font-size:12px;">' + escapeHtml(p.unit) + '</span>' : '') +
			'</div>';
	}
	document.getElementById(prefix + 'GroupPolicyDetail').innerHTML = detail;
}

function setGroupPolicyVal(prefix, pname, val) {
	_groupPolicies[prefix][pname] = (val === 'null' || val === null) ? null : parseInt(val, 10);
	renderGroupPolicyList(prefix);
}

function onGroupPolicyNumToggle(prefix, pname) {
	var cb = document.getElementById('polEn_' + prefix + '_' + pname);
	var inp = document.getElementById('polNum_' + prefix + '_' + pname);
	if (cb.checked) {
		inp.disabled = false;
		_groupPolicies[prefix][pname] = parseInt(inp.value, 10) || 0;
	} else {
		inp.disabled = true;
		_groupPolicies[prefix][pname] = null;
	}
	renderGroupPolicyList(prefix);
}

function setGroupPolicyNumVal(prefix, pname, val) {
	_groupPolicies[prefix][pname] = parseInt(val, 10) || 0;
}

async function applyGroupPolicies(groupName, prefix) {
	var cb = document.getElementById(prefix + 'GroupPolicyEnabled');
	var enabled = cb && cb.checked;
	for (var i = 0; i < SE_POLICIES.length; i++) {
		var p = SE_POLICIES[i];
		var val = (!enabled) ? null : _groupPolicies[prefix][p.name];
		if (val === null) {
			await call('GroupPolicyRemove', { HubName_str: currentHub, Group: groupName, PolicyName: p.name });
		} else {
			await call('GroupPolicySet', { HubName_str: currentHub, Group: groupName, PolicyName: p.name, PolicyValue: val });
		}
	}
}

// ============================================================
// HUB GROUPS
// ============================================================
async function createGroup() {
	var name = val('newGroupName');
	var real = val('newGroupReal');
	var note = val('newGroupNote');
	if (!name) { alert('Group name is required.'); return; }
	var r = await tryMethods(['CreateGroup','AddGroup'], { HubName_str:currentHub, Name_str:name, Realname_utf:real, Note_utf:note, Group:name, RealName:real, Note:note });
	if (r.error) { alert('Error creating group or API not available.'); return; }
	await applyGroupPolicies(name, 'new');
	$('#createGroupModal').modal('hide');
	setVal('newGroupName', '');
	setVal('newGroupReal', '');
	setVal('newGroupNote', '');
	await refreshGroups();
}

async function editGroup(name) {
	var grp = groupsList.find(function(g) { return g.Name_str === name; });
	setVal('editGroupOldName', name);
	setVal('editGroupName', name);
	setVal('editGroupReal', grp ? (grp.Realname_utf || grp.RealName_str || '') : '');
	setVal('editGroupNote', grp ? (grp.Note_utf || grp.Note_str || '') : '');
	document.getElementById('editGroupStats').innerHTML = emptyRow(2, 'Loading...');
	initGroupPolicyUI('edit', null);
	$('#editGroupModal').modal('show');
	var r = await call('GetGroup', { HubName_str: currentHub, Name_str: name, Group: name });
	var sr = (!r.error && r.result) ? r.result : {};
	setVal('editGroupNote', sr.Note_utf || sr.Note_str || document.getElementById('editGroupNote').value);
	// Load policies from _policies field populated by proxy
	if (sr._policies) { initGroupPolicyUI('edit', sr._policies); }
	function np(v) { var i = parseInt(v, 10); return isNaN(i) ? 0 : i; }
	var statsRows = [
		['Outgoing Unicast Packets',      np(sr['Send.UnicastPackets_u64']   ?? sr['Send.UnicastCount_u64'])   .toLocaleString() + ' packets'],
		['Outgoing Unicast Total Size',   np(sr['Send.UnicastBytes_u64'])    .toLocaleString() + ' bytes'],
		['Outgoing Broadcast Packets',    np(sr['Send.BroadcastPackets_u64'] ?? sr['Send.BroadcastCount_u64']).toLocaleString() + ' packets'],
		['Outgoing Broadcast Total Size', np(sr['Send.BroadcastBytes_u64'])  .toLocaleString() + ' bytes'],
		['Incoming Unicast Packets',      np(sr['Recv.UnicastPackets_u64']   ?? sr['Recv.UnicastCount_u64'])   .toLocaleString() + ' packets'],
		['Incoming Unicast Total Size',   np(sr['Recv.UnicastBytes_u64'])    .toLocaleString() + ' bytes'],
		['Incoming Broadcast Packets',    np(sr['Recv.BroadcastPackets_u64'] ?? sr['Recv.BroadcastCount_u64']).toLocaleString() + ' packets'],
		['Incoming Broadcast Total Size', np(sr['Recv.BroadcastBytes_u64'])  .toLocaleString() + ' bytes'],
	];
	document.getElementById('editGroupStats').innerHTML = kvRows(statsRows);
}

async function saveEditGroup() {
	var oldName = rawVal('editGroupOldName');
	var real    = val('editGroupReal');
	var note    = val('editGroupNote');
	var r = await tryMethods(['SetGroup'], { HubName_str:currentHub, Name_str:oldName, Realname_utf:real, Note_utf:note, Group:oldName, RealName:real, Note:note });
	if (r.error) { showApiError('Error updating group', r); return; }
	await applyGroupPolicies(oldName, 'edit');
	$('#editGroupModal').modal('hide');
	await refreshGroups();
}

function delGroup(name) {
	return confirmCall("Delete group '" + name + "'?", ['DeleteGroup','DelGroup'],
		{ HubName_str:currentHub, Name_str:name }, 'Error deleting group', refreshGroups);
}

function killSession(sessionName) {
	return confirmCall('Disconnect this session?', 'KillSession',
		{ HubName_str:currentHub, Name_str:sessionName, SessionName_str:sessionName },
		'Error disconnecting session', refreshSessions);
}

// ============================================================
// HUB MANAGEMENT
// ============================================================

function setHubOnline(online) {
	if (!currentHub) { return; }
	return confirmCall(null, online ? 'SetHubOnline' : 'SetHubOffline',
		{ HubName_str: currentHub }, 'Error changing hub state', refreshHubInfo);
}

async function saveHubPassword() {
	var pwd = rawVal('hubNewPassword');
	if (!pwd) { alert('Password is required.'); return; }
	var r = await call('SetHubPassword', { HubName_str: currentHub, Password_str: pwd });
	if (r.error) { showApiError('Error setting hub password', r); return; }
	$('#hubPasswordModal').modal('hide');
	setVal('hubNewPassword', '');
	alert('Hub password changed successfully.');
}

// ============================================================
// USER EDIT
// ============================================================

async function editUser(name) {
	var usr = null;
	var r = await tryMethods(['GetUser'], { HubName_str: currentHub, Name_str: name });
	if (!r.error && r.result) {
		usr = r.result;
	} else {
		var u = await call('EnumUser', { HubName_str: currentHub });
		var list = resultList(u, 'UserList');
		usr = list.find(function(u) { return u.Name_str === name; }) || { Name_str: name };
	}
	setVal('editUserName', usr.Name_str || name);
	setVal('editUserDisplayName', usr.Name_str || name);
	setVal('editUserReal', usr.Realname_utf || usr.RealName_utf || usr.RealName_str || '');
	var authSel = document.getElementById('editUserAuthType'); if (authSel && usr.AuthType_u32 !== undefined) { authSel.value = String(usr.AuthType_u32); }
	setVal('editUserNote', usr.Note_utf || usr.Note_str || '');
	setVal('editUserPass', '');
	var expiryEl = document.getElementById('editUserExpiry');
	if (expiryEl) { expiryEl.value = usr.ExpiresTime_dt ? usr.ExpiresTime_dt.split('T')[0] : ''; }
	var grpSel = document.getElementById('editUserGroup');
	grpSel.innerHTML = '<option value="">-- No Group --</option>';
	groupsList.forEach(function(g) {
		var n = g.Name_str || '';
		if (!n) { return; }
		var opt = document.createElement('option');
		opt.value = n; opt.textContent = n;
		grpSel.appendChild(opt);
	});
	grpSel.value = usr.GroupName_str || '';
	$('#editUserModal').modal('show');
}

async function saveEditUser() {
	var name = rawVal('editUserName');
	var real  = val('editUserReal');
	var note  = val('editUserNote');
	var group = rawVal('editUserGroup');
	var pass = rawVal('editUserPass');
	var r = await tryMethods(['SetUser'], {
		HubName_str: currentHub, Name_str: name,
		RealName_utf: real, RealName_str: real,
		Note_utf: note, Note_str: note,
		GroupName_str: group, AuthType_u32: parseInt(rawVal('editUserAuthType'), 10)
	});
	if (r.error) {
		showApiError('Error updating user', r);
		return;
	}
	if (pass) {
		var rp = await call('SetUserPassword', { HubName_str: currentHub, Name_str: name, Password_str: pass });
		if (rp.error) { alert('User updated but error setting password.'); }
	}
	var expiry = val('editUserExpiry');
	if (expiry) {
		await call('SetUserExpires', { HubName_str: currentHub, UserName: name, Name_str: name, Expires: expiry });
	}
	$('#editUserModal').modal('hide');
	await refreshUsers();
}

// ============================================================
// ACCESS LIST
// ============================================================

function ipToInt(ip) {
	if (!ip || ip === '0.0.0.0' || ip.trim() === '') { return 0; }
	var parts = (ip.trim()).split('.');
	if (parts.length !== 4) { return 0; }
	return (((parseInt(parts[0],10)||0)&255)<<24)|(((parseInt(parts[1],10)||0)&255)<<16)|(((parseInt(parts[2],10)||0)&255)<<8)|((parseInt(parts[3],10)||0)&255);
}

function intToIp(n) {
	if (!n) { return ''; }
	return [(n>>>24)&255,(n>>>16)&255,(n>>>8)&255,n&255].join('.');
}

var PROTO_NAMES = {0:'All', 1:'ICMP', 6:'TCP', 17:'UDP', 58:'ICMPv6'};

async function refreshAccessList() {
	if (!currentHub) { return; }
	var r = await call('GetAccessList', { HubName_str: currentHub });
	// CLI fallback returns a bare array instead of { AccessList: [...] }
	var list = (!r.error && Array.isArray(r.result)) ? r.result : resultList(r, 'AccessList');
	list = list.slice().sort(function(a,b) { return (a.Priority_u32||0)-(b.Priority_u32||0); });
	fillTable('accessTable', list, 10, 'No access rules. Pass-all by default.', function(rule) {
		var srcIp   = rule.SrcIpAddress_ip   ? intToIp(rule.SrcIpAddress_ip)   : '0.0.0.0';
		var srcMask = rule.SrcSubnetMask_ip   ? intToIp(rule.SrcSubnetMask_ip)  : '0.0.0.0';
		var dstIp   = rule.DestIpAddress_ip   ? intToIp(rule.DestIpAddress_ip)  : '0.0.0.0';
		var dstMask = rule.DestSubnetMask_ip  ? intToIp(rule.DestSubnetMask_ip) : '0.0.0.0';
		var proto   = PROTO_NAMES[rule.Protocol_u32] || String(rule.Protocol_u32||0);
		var action  = rule.Discard_bool ? '<span class="label label-danger">Discard</span>' : '<span class="label label-success">Pass</span>';
		var active  = rule.Active_bool  ? '<span class="label label-success">Yes</span>' : '<span class="label label-warning">No</span>';
		var srcPort = (rule.SrcPortStart_u32||0) + '-' + (rule.SrcPortEnd_u32 !== undefined ? rule.SrcPortEnd_u32 : 65535);
		var dstPort = (rule.DestPortStart_u32||0) + '-' + (rule.DestPortEnd_u32 !== undefined ? rule.DestPortEnd_u32 : 65535);
		var id = rule.Id_u32 || 0;
		return '<tr>' +
			'<td>' + escapeHtml(String(rule.Priority_u32||0)) + '</td>' +
			'<td>' + escapeHtml(rule.Note_utf || rule.Note_str || '') + '</td>' +
			'<td>' + action + '</td>' +
			'<td>' + escapeHtml(proto) + '</td>' +
			'<td><small>' + escapeHtml(srcIp + '/' + srcMask) + '</small></td>' +
			'<td><small>' + escapeHtml(dstIp + '/' + dstMask) + '</small></td>' +
			'<td><small>' + escapeHtml(srcPort) + '</small></td>' +
			'<td><small>' + escapeHtml(dstPort) + '</small></td>' +
			'<td>' + active + '</td>' +
			'<td>' +
			'<button class="btn btn-xs btn-' + (rule.Active_bool ? 'warning' : 'success') + '" onclick="toggleAccess(' + id + ',' + !rule.Active_bool + ')" style="margin-right:2px;" title="' + (rule.Active_bool ? 'Disable' : 'Enable') + '"><i class="fa fa-' + (rule.Active_bool ? 'pause' : 'play') + '"></i></button>' +
			'<button class="btn btn-xs btn-danger" onclick="deleteAccess(' + id + ')"><i class="fa fa-trash"></i></button>' +
			'</td>' +
			'</tr>';
	});
}

async function addAccess() {
	var priority     = intVal('accPriority', 100);
	var note         = val('accNote');
	var discard      = rawVal('accAction') === '1';
	var active = isChecked('accActive');
	var protocol     = parseInt(rawVal('accProtocol'), 10);
	var srcIp        = ipToInt(rawVal('accSrcIp'));
	var srcMask      = ipToInt(rawVal('accSrcMask'));
	var dstIp        = ipToInt(rawVal('accDstIp'));
	var dstMask      = ipToInt(rawVal('accDstMask'));
	var srcPortStart = intVal('accSrcPortStart', 0);
	var srcPortEnd   = parseInt(rawVal('accSrcPortEnd'), 10);
	var dstPortStart = intVal('accDstPortStart', 0);
	var dstPortEnd   = parseInt(rawVal('accDstPortEnd'), 10);
	if (isNaN(srcPortEnd)) { srcPortEnd = 65535; }
	if (isNaN(dstPortEnd)) { dstPortEnd = 65535; }
	var r = await call('AddAccess', {
		HubName_str: currentHub,
		Id_u32: 0, Note_utf: note, Active_bool: active, Priority_u32: priority,
		Discard_bool: discard, IsIPv6_bool: false,
		SrcIpAddress_ip: srcIp, SrcSubnetMask_ip: srcMask,
		DestIpAddress_ip: dstIp, DestSubnetMask_ip: dstMask,
		Protocol_u32: protocol,
		SrcPortStart_u32: srcPortStart, SrcPortEnd_u32: srcPortEnd,
		DestPortStart_u32: dstPortStart, DestPortEnd_u32: dstPortEnd,
		CheckSrcMac_bool: false, CheckDstMac_bool: false,
		CheckTcpState_bool: false, Established_bool: false,
		Delay_u32: 0, Jitter_u32: 0, Loss_u32: 0, RedirectUrl_str: '',
		// CLI fallback params
		Note: note, Priority: String(priority), Action: discard ? '/DISCARD' : '/PASS',
		SrcIpMask: (intToIp(srcIp) + '/' + intToIp(srcMask)),
		DstIpMask: (intToIp(dstIp) + '/' + intToIp(dstMask)),
		SrcPort: srcPortStart + '-' + srcPortEnd,
		DstPort: dstPortStart + '-' + dstPortEnd
	});
	if (r.error) { showApiError('Error adding rule', r); return; }
	$('#addAccessModal').modal('hide');
	await refreshAccessList();
}

function deleteAccess(id) {
	return confirmCall('Delete access rule #' + id + '?', 'DeleteAccess',
		{ HubName_str: currentHub, Id_u32: id }, 'Error deleting rule', refreshAccessList);
}

// ============================================================
// SECURENAT
// ============================================================

async function refreshSecureNAT() {
	if (!currentHub) {
		document.getElementById('secureNATControls').style.display = 'none';
		return;
	}
	document.getElementById('secureNATControls').style.display = 'flex';
	var status = await tryMethods(['GetSecureNatStatus'], { HubName_str: currentHub });
	var statusEl = document.getElementById('secureNATStatus');
	if (!status.error && status.result) {
		var enabled = !!(status.result.Enabled_bool || status.result.Online_bool || status.result.Enable_bool);
		statusEl.className = 'label ' + (enabled ? 'label-success' : 'label-warning');
		statusEl.textContent = 'SecureNAT: ' + (enabled ? 'Enabled' : 'Disabled');
	} else {
		statusEl.className = 'label label-default';
		statusEl.textContent = 'SecureNAT';
	}
	await Promise.all([refreshNatTable(), refreshDhcpTable()]);
}

async function toggleSecureNAT(enable) {
	var method = enable ? 'EnableSecureNat' : 'DisableSecureNat';
	var r = await call(method, { HubName_str: currentHub });
	if (r.error) { showApiError('Error', r); return; }
	await refreshSecureNAT();
}

async function refreshNatTable() {
	var r = await call('EnumNat', { HubName_str: currentHub });
	fillTable('natTable', resultList(r, 'NatTable'), 6, 'No NAT sessions.', function(row) {
		var proto   = PROTO_NAMES[row.Protocol_u32] || String(row.Protocol_u32||0);
		var srcIp   = row.SrcIp_str || intToIp(row.SrcIp_ip) || '-';
		var srcPort = row.SrcPort_u32 || '';
		var dstIp   = row.DestIp_str || intToIp(row.DestIp_ip) || '-';
		var dstPort = row.DestPort_u32 || '';
		return '<tr>' +
			'<td>' + escapeHtml(proto) + '</td>' +
			'<td>' + escapeHtml(srcPort ? srcIp + ':' + srcPort : srcIp) + '</td>' +
			'<td>' + escapeHtml(dstPort ? dstIp + ':' + dstPort : dstIp) + '</td>' +
			'<td>' + escapeHtml(humanize('CreatedTime_dt', row.CreatedTime_dt || row.Created_dt)) + '</td>' +
			'<td>' + escapeHtml(humanize('LastCommTime_dt', row.LastCommTime_dt)) + '</td>' +
			'<td>' + fmtBytes(row.SendSize_u64) + ' / ' + fmtBytes(row.RecvSize_u64) + '</td>' +
			'</tr>';
	}, '<tr><th>Protocol</th><th>Source IP:Port</th><th>Dest IP:Port</th><th>Created</th><th>Last Comm</th><th>Sent/Recv</th></tr>');
}

async function refreshDhcpTable() {
	var r = await call('EnumDhcp', { HubName_str: currentHub });
	fillTable('dhcpTable', resultList(r, 'DhcpTable'), 5, 'No DHCP leases.', function(row) {
		var mac = humanize('MacAddress_bin', row.MacAddress_bin || row.MacAddress_str || '-');
		var ip  = row.IpAddress_str || intToIp(row.IpAddress_ip) || '-';
		return '<tr>' +
			'<td><code>' + escapeHtml(mac) + '</code></td>' +
			'<td>' + escapeHtml(ip) + '</td>' +
			'<td>' + escapeHtml(row.Hostname_str || '-') + '</td>' +
			'<td>' + escapeHtml(humanize('ExpiryTime_dt', row.ExpiryTime_dt || row.Expiry_dt)) + '</td>' +
			'<td><code>' + escapeHtml(row.SessionName_str || '-') + '</code></td>' +
			'</tr>';
	}, '<tr><th>MAC Address</th><th>IP Address</th><th>Hostname</th><th>Expiry</th><th>Session</th></tr>');
}

async function showSecureNATSettings() {
	var r = await tryMethods(['GetSecureNatOption'], { HubName_str: currentHub });
	if (!r.error && r.result) {
		var o = r.result;
		setChecked('natUseNat', !(o.UseNat_bool === false));
		setVal('natMtu', o.Mtu_u32 || 1500);
		setVal('natTcpTimeout', o.NatTcpTimeout_u32 || 300);
		setVal('natUdpTimeout', o.NatUdpTimeout_u32 || 60);
		setChecked('natUseDhcp', !(o.UseDhcp_bool === false));
		setVal('natDhcpStart', intToIp(o.DhcpLeaseIPStart_ip) || '192.168.30.10');
		setVal('natDhcpEnd', intToIp(o.DhcpLeaseIPEnd_ip)   || '192.168.30.200');
		setVal('natDhcpMask', intToIp(o.DhcpSubnetMask_ip)   || '255.255.255.0');
		setVal('natDhcpGw', intToIp(o.DhcpGatewayAddress_ip)  || '');
		setVal('natDhcpDns1', intToIp(o.DhcpDnsServerAddress_ip)  || '');
		setVal('natDhcpDns2', intToIp(o.DhcpDnsServerAddress2_ip) || '');
		setVal('natDhcpDomain', o.DhcpDomainName_str || '');
		setVal('natDhcpExpire', o.DhcpExpireTimeSpan_u32 || 7200);
	}
	$('#secureNATSettingsModal').modal('show');
}

async function saveSecureNATSettings() {
	var r = await call('SetSecureNatOption', {
		HubName_str:              currentHub,
		UseNat_bool:              isChecked('natUseNat'),
		Mtu_u32:                  intVal('natMtu', 1500),
		NatTcpTimeout_u32:        intVal('natTcpTimeout', 300),
		NatUdpTimeout_u32:        intVal('natUdpTimeout', 60),
		UseDhcp_bool:             isChecked('natUseDhcp'),
		DhcpLeaseIPStart_ip:      ipToInt(rawVal('natDhcpStart')),
		DhcpLeaseIPEnd_ip:        ipToInt(rawVal('natDhcpEnd')),
		DhcpSubnetMask_ip:        ipToInt(rawVal('natDhcpMask')),
		DhcpGatewayAddress_ip:    ipToInt(rawVal('natDhcpGw')),
		DhcpDnsServerAddress_ip:  ipToInt(rawVal('natDhcpDns1')),
		DhcpDnsServerAddress2_ip: ipToInt(rawVal('natDhcpDns2')),
		DhcpDomainName_str:       rawVal('natDhcpDomain'),
		DhcpExpireTimeSpan_u32:   intVal('natDhcpExpire', 7200),
		SaveLog_bool:             true
	});
	if (r.error) { showApiError('Error saving SecureNAT settings', r); return; }
	$('#secureNATSettingsModal').modal('hide');
	alert('SecureNAT settings saved.');
}

// ============================================================
// HUB RADIUS
// ============================================================

async function refreshHubRadius() {
	if (!currentHub) { return; }
	var r = await call('GetHubRadius', { HubName_str: currentHub });
	if (!r.error && r.result) {
		setVal('radiusServer', r.result.RadiusServerName_str || '');
		setVal('radiusPort', r.result.RadiusPort_u32 || 1812);
		setVal('radiusSecret', '');
		setVal('radiusRetry', r.result.RadiusRetryInterval_u32 || 500);
	}
}

async function saveHubRadius() {
	var server = val('radiusServer');
	var port   = intVal('radiusPort', 1812);
	var secret = rawVal('radiusSecret');
	var retry  = intVal('radiusRetry', 500);
	var r = await call('SetHubRadius', {
		HubName_str: currentHub,
		RadiusServerName_str: server,
		RadiusPort_u32: port,
		RadiusServerSecret_str: secret,
		RadiusRetryInterval_u32: retry
	});
	if (r.error) { showApiError('Error saving RADIUS settings', r); return; }
	alert('RADIUS settings saved.');
}

async function clearHubRadius() {
	if (!confirm('Clear RADIUS configuration? This will disable RADIUS authentication for this hub.')) { return; }
	var r = await call('SetHubRadius', {
		HubName_str: currentHub,
		RadiusServerName_str: '',
		RadiusPort_u32: 1812,
		RadiusServerSecret_str: '',
		RadiusRetryInterval_u32: 500
	});
	if (r.error) { alert('Error clearing RADIUS settings.'); return; }
	setVal('radiusServer', '');
	setVal('radiusSecret', '');
	alert('RADIUS configuration cleared.');
}

// ============================================================
// HUB LOG SETTINGS
// ============================================================

async function refreshHubLog() {
	if (!currentHub) { return; }
	var r = await call('GetHubLog', { HubName_str: currentHub });
	if (!r.error && r.result) {
		setChecked('hubLogSecurity', !(r.result.SaveSecurityLog_bool === false));
		setChecked('hubLogPacket', !(r.result.SavePacketLog_bool === false));
		setVal('hubLogSecuritySwitch', r.result.SecurityLogSwitchType_u32 !== undefined ? r.result.SecurityLogSwitchType_u32 : 2);
	}
}

async function saveHubLog() {
	var r = await call('SetHubLog', {
		HubName_str: currentHub,
		SaveSecurityLog_bool:      isChecked('hubLogSecurity'),
		SavePacketLog_bool:        isChecked('hubLogPacket'),
		SecurityLogSwitchType_u32: intVal('hubLogSecuritySwitch', 2),
		PacketLogSwitchType_u32:   2
	});
	if (r.error) { showApiError('Error saving log settings', r); return; }
	alert('Hub log settings saved.');
}

// ============================================================
// CASCADES
// Shared rendering extracted from formerly duplicated functions
// ============================================================

function buildCascadeRows(list, detailsByName) {
	return list.map(function(cs) {
		var online  = !!(cs.Online_bool || cs.Connected_bool);
		var account = cs.AccountName_utf || '';
		var det     = detailsByName[account] || {};

		var targetHub  = det.HubName_str || det.TargetHubName_str || cs.HubName_str || cs.TargetHubName_str || '-';
		var hostname   = det.Hostname_str || cs.Hostname_str || '-';
		var hubUser    = det.Username_str || det.UserName_str || cs.HubUsername_utf || cs.HubUsername_str ||
		                 cs.TargetHubUsername_utf || cs.TargetHubUsername_str || cs.Username_utf ||
		                 cs.Username_str || cs.UserName_str || '-';
		var lastError  = det.LastError_u32 !== undefined ? det.LastError_u32 : (cs.LastError_u32 !== undefined ? cs.LastError_u32 : 0);
		var lastErrDetail = det.LastErrorDetail_str || det.LastError_str || det.ErrorMessage_str ||
		                    cs.LastErrorDetail_str || cs.LastError_str || cs.ErrorMessage_str || '';
		var statusRaw  = det.AccountStatus_str || det.Status_str || det.LinkStatus_str ||
		                 cs.AccountStatus_str  || cs.Status_str  || cs.LinkStatus_str  || '';

		var statusText, statusClass;
		if (Number(lastError) !== 0) {
			statusText  = 'Error (' + lastError + ')' + (lastErrDetail ? ': ' + lastErrDetail : '');
			statusClass = 'label-danger';
		} else if (statusRaw) {
			statusText  = statusRaw;
			statusClass = /error|failed|failure|cannot|denied|auth|offline/i.test(statusRaw) ? 'label-danger'
			            : /online|connected|established|success/i.test(statusRaw) ? 'label-success'
			            : 'label-warning';
		} else {
			statusText  = online ? 'Online' : 'Offline';
			statusClass = online ? 'label-success' : 'label-danger';
		}

		var established = det.EstablishedTime_str || det.ConnectedTime_str || det.ConnectedTimeSpan_str ||
		                  cs.EstablishedTime_str  || cs.ConnectedTime_str  || cs.ConnectedTimeSpan_str  || '';
		if (!established) {
			var estBool = (det.Connected_bool !== undefined) ? det.Connected_bool
			            : ((cs.Connected_bool !== undefined) ? cs.Connected_bool : online);
			established = estBool ? 'Yes' : 'No';
		}

		var tooltip = escapeHtml(statusText);
		if (lastError || lastErrDetail) {
			tooltip = 'Error ' + lastError + ': ' + getErrorDescription(lastError) +
			          (lastErrDetail ? ' (' + lastErrDetail + ')' : '');
		}
		var a = escapeAttr(account);
		return '<tr>' +
			'<td>' + escapeHtml(account) + '</td>' +
			'<td>' + escapeHtml(targetHub) + '</td>' +
			'<td>' + escapeHtml(hostname) + '</td>' +
			'<td>' + escapeHtml(hubUser) + '</td>' +
			'<td><span class="label ' + statusClass + '" title="' + escapeHtml(tooltip) + '" data-toggle="tooltip" data-placement="top">' + escapeHtml(statusText) + '</span></td>' +
			'<td>' + escapeHtml(established) + '</td>' +
			'<td style="white-space:nowrap;">' +
			'<button class="btn btn-xs btn-info"    onclick="toggleCascade(\'' + a + '\',' + (online ? 'false' : 'true') + ')" style="margin-right:2px;">' + (online ? '<i class="fa-solid fa-stop icon-embed-btn"></i> Offline' : '<i class="fa-solid fa-play icon-embed-btn"></i> Online') + '</button>' +
			'<button class="btn btn-xs btn-warning" onclick="editCascade(\'' + a + '\')" title="Edit" style="margin-right:2px;"><i class="fa fa-edit"></i></button>' +
			'<button class="btn btn-xs btn-danger"  onclick="deleteCascade(\'' + a + '\')" title="Delete"><i class="fa fa-trash"></i></button>' +
			'</td></tr>';
	}).join('') || emptyRow(7, 'No cascade connections.');
}

async function fetchCascadeDetails(list) {
	var detailsByName = {};
	await Promise.all(list.map(async function(cs) {
		var account = cs.AccountName_utf || '';
		if (!account) { return; }
		var d = await tryMethods(['GetLink'], { HubName_Ex_str:currentHub, AccountName_utf:account });
		if (!d.error && d.result) { detailsByName[account] = d.result; }
	}));
	return detailsByName;
}

// Cascade-only refresh (used by refreshHubData and the auto-refresher)
async function refreshCascadeTable() {
	if (!currentHub) { return; }
	var c = await call('EnumLink', { HubName_str: currentHub });
	var list = resultList(c, 'LinkList');
	var detailsByName = await fetchCascadeDetails(list);
	document.getElementById('cascadeTable').innerHTML = buildCascadeRows(list, detailsByName);
	$('[data-toggle="tooltip"]').tooltip();
}

async function createCascade() {
	var name    = val('newCascadeName');
	var hub     = val('newCascadeHub');
	var host    = val('newCascadeHost');
	var port    = intVal('newCascadePort', 443);
	var hubUser = val('newCascadeHubUser');
	var hubPass = rawVal('newCascadeHubPass');
	if (!name || !hub || !host || !hubUser || !hubPass) {
		alert('Connection name, target hub, hostname, hub user and hub password are required.');
		return;
	}
	var payload = buildCascadePayload(name, hub, host, port, hubUser, hubPass);
	var r = await call('CreateLink', payload);
	if (!r.error) { await applyCascadeAuthSettings(name, hub, host, port, hubUser, hubPass); }
	if (r.error) { showApiError('Error creating cascade', r); return; }
	$('#createCascadeModal').modal('hide');
	['newCascadeName','newCascadeHub','newCascadeHost','newCascadePort','newCascadeUser','newCascadePass','newCascadeHubUser','newCascadeHubPass'].forEach(function(id) {
		var el = document.getElementById(id);
		if (el) { el.value = (id === 'newCascadePort') ? '443' : ''; }
	});
	await refreshCascadeTable();
}

async function editCascade(name) {
	var details = await tryMethods(['GetLink'], { HubName_Ex_str:currentHub, AccountName_utf:name });
	var casc = details && details.result ? details.result : null;
	if (!casc) {
		var c = await call('EnumLink', { HubName_str:currentHub });
		var list = resultList(c, 'LinkList');
		casc = list.find(function(l) { return l.AccountName_utf === name; });
	}
	if (!casc) { return; }
	setVal('editCascadeOldName', name);
	setVal('editCascadeName', name);
	setVal('editCascadeHub', casc.TargetHubName_str || casc.HubName_str || '');
	setVal('editCascadeHost', casc.Hostname_str || '');
	setVal('editCascadePort', casc.Port_u32 || 443);
	setVal('editCascadeUser', 'Administrator');
	setVal('editCascadeHubUser', casc.Username_str || casc.UserName_str || casc.HubUsername_utf || casc.HubUsername_str || '');
	setVal('editCascadePass', '');
	setVal('editCascadeHubPass', '');
	$('#editCascadeModal').modal('show');
}

async function saveEditCascade() {
	var oldName = rawVal('editCascadeOldName');
	var newName = val('editCascadeName');
	var hub     = val('editCascadeHub');
	var host    = val('editCascadeHost');
	var port    = intVal('editCascadePort', 443);
	var hubUser = val('editCascadeHubUser');
	var hubPass = rawVal('editCascadeHubPass');
	if (!newName || !hub || !host || !hubUser || !hubPass) {
		alert('All fields are required except remote discovery password.');
		return;
	}
	await call('DeleteLink', { HubName_str:currentHub, AccountName_utf:oldName });
	var r = await call('CreateLink', buildCascadePayload(newName, hub, host, port, hubUser, hubPass));
	if (!r.error) { await applyCascadeAuthSettings(newName, hub, host, port, hubUser, hubPass); }
	if (r.error) { alert('Error updating cascade.'); return; }
	$('#editCascadeModal').modal('hide');
	await refreshCascadeTable();
}

function deleteCascade(name) {
	return confirmCall("Delete cascade '" + name + "'?", 'DeleteLink',
		{ HubName_str:currentHub, AccountName_utf:name }, 'Error deleting cascade', refreshCascadeTable);
}

function toggleCascade(name, online) {
	return confirmCall(null, online ? 'SetLinkOnline' : 'SetLinkOffline',
		{ HubName_str:currentHub, AccountName_utf:name }, 'Error changing cascade state', refreshCascadeTable);
}

function buildCascadePayload(name, hub, host, port, hubUser, hubPass) {
	return {
		HubName_Ex_str: currentHub, AccountName_utf: name,
		CheckServerCert_bool: false, Hostname_str: host,
		Port_u32: port, ProxyType_u32: 0, HubName_str: hub,
		Online_bool: true, AuthType_u32: 2,
		Username_str: hubUser, PlainPassword_str: hubPass
	};
}

async function applyCascadeAuthSettings(accountName, targetHub, host, port, hubUser, hubPass) {
	var r = await call('SetLink', buildCascadePayload(accountName, targetHub, host, port, hubUser, hubPass));
	if (!r.error) { console.log('[Cascade] SetLink succeeded for', accountName); }
}

// ============================================================
// LISTENERS
// ============================================================

async function refreshListeners() {
	var l = await call('EnumListener');
	fillTable('listenerTable', resultList(l, 'ListenerList'), 3, 'No listeners.', function(li) {
		var port    = li.Ports_u32 !== undefined ? li.Ports_u32 : li.Port_u32;
		var enabled = !!(li.Enables_bool !== undefined ? li.Enables_bool : (li.Enable_bool || li.Enabled_bool));
		var portStr = String(port !== undefined ? port : 0);

		var statusText = enabled ? 'Listening' : 'Stopped listening';
		var statusClass = enabled ? 'label-success' : 'label-warning';
		var statusRaw = li.Status_str || li.State_str || li.ListenerStatus_str || li.StatusMessage_str || '';
		var hasError = statusRaw && /error|failed|failure|bind|cannot|in use/i.test(String(statusRaw));

		if (!hasError) {
			Object.keys(li).some(function(k) {
				var key = k.toLowerCase();
				if (key.indexOf('error') === -1 && key.indexOf('err') === -1) { return false; }
				var v = li[k];
				if ((typeof v === 'number' && v !== 0) || (typeof v === 'boolean' && v)) { hasError = true; return true; }
				if (typeof v === 'string') {
					var s = v.toLowerCase();
					if (s && s !== '0' && s !== 'ok' && s !== 'none') { hasError = true; return true; }
				}
				return false;
			});
		}
		if (hasError) {
			statusText = 'Error'; statusClass = 'label-danger';
		} else if (statusRaw) {
			statusText = String(statusRaw);
			statusClass = /stop|disabled|not listening/i.test(statusText) ? 'label-warning'
			            : /listen|online|running|active/i.test(statusText) ? 'label-success'
			            : statusClass;
		}
		var mgmt = (port === 5555);
		return '<tr>' +
			'<td>' + (port !== undefined ? port : '-') + (mgmt ? ' <span class="label label-default" title="Management port used by pfSense">mgmt</span>' : '') + '</td>' +
			'<td><span class="label ' + statusClass + '">' + statusText + '</span></td>' +
			'<td>' +
			'<button class="btn btn-xs btn-info"    onclick="toggleListener(' + portStr + ',' + (enabled?0:1) + ')" ' + (mgmt ? 'disabled title="Management port cannot be stopped"' : '') + '>' + (enabled ? '<i class="fa-solid fa-stop icon-embed-btn"></i> Stop' : '<i class="fa-solid fa-play icon-embed-btn"></i> Start') + '</button> ' +
			'<button class="btn btn-xs btn-warning" onclick="editListener(' + portStr + ')"   title="' + (mgmt ? 'Management port cannot be modified' : 'Edit') + '" ' + (mgmt ? 'disabled' : '') + '><i class="fa fa-edit"></i></button> ' +
			'<button class="btn btn-xs btn-danger"  onclick="deleteListener(' + portStr + ')" ' + (mgmt ? 'disabled title="Management port cannot be deleted"' : '') + '><i class="fa fa-trash"></i></button>' +
			'</td></tr>';
	});
}

async function createListener() {
	var port = intVal('newListenerPort', 0);
	if (port === 5555) { alert('Port 5555 is reserved for pfSense management.'); return; }
	var r = await call('AddListener', { Port_u32:port, Enable_bool:true });
	if (r.error) { showApiError('Error adding listener', r); return; }
	$('#createListenerModal').modal('hide');
	await refreshListeners();
}

function editListener(port) {
	setVal('editListenerOldPort', port);
	setVal('editListenerPort', port);
	$('#editListenerModal').modal('show');
}

async function saveEditListener() {
	var oldPort = parseInt(rawVal('editListenerOldPort'), 10);
	var newPort = intVal('editListenerPort', 0);
	if (!newPort) { alert('Port is required.'); return; }
	if (oldPort === 5555) { alert('Port 5555 is the pfSense management port and cannot be modified.'); return; }
	if (newPort === 5555) { alert('Port 5555 is reserved for pfSense management and cannot be assigned.'); return; }
	if (oldPort !== newPort) {
		await call('DeleteListener', { Port_u32:oldPort });
		var r = await call('AddListener', { Port_u32:newPort, Enable_bool:true });
		if (r.error) { showApiError('Error updating listener', r); return; }
	}
	$('#editListenerModal').modal('hide');
	await refreshListeners();
}

function toggleListener(port, enable) {
	if (Number(port) === 5555) { alert('Port 5555 is the pfSense management port and cannot be stopped.'); return; }
	return confirmCall(null, 'EnableListener', { Port_u32:Number(port), Enable_bool:!!enable },
		'Error updating listener state', refreshListeners);
}

function deleteListener(port) {
	if (Number(port) === 5555) { alert('Port 5555 is the pfSense management port and cannot be deleted.'); return; }
	return confirmCall('Delete listener on port ' + port + '?', 'DeleteListener',
		{ Port_u32:port }, 'Error deleting listener', refreshListeners);
}

// ============================================================
// IPSEC
// ============================================================

async function refreshIpsec() {
	// Populate Default Hub dropdown
	var hubSel = document.getElementById('ipsecDefaultHub');
	if (hubSel) {
		var hubs = await call('EnumHub');
		hubSel.innerHTML = hubOptionsHtml(resultList(hubs, 'HubList'));
	}
	var i = await call('GetIPsecConfig');
	if (!i.error && i.result) {
		setChecked('l2tp_ipsec_on', i.result.L2TP_bool);
		setChecked('l2tp_raw_on',   i.result.L2TP_Raw_bool);
		setChecked('etherip_on',    i.result.EtherIP_bool);
		setVal('ipsec_psk',         i.result.IPsec_Secret_str || '');
		var defHub = i.result.L2TP_DefaultHub_str || '';
		if (hubSel && defHub) { hubSel.value = defHub; }
	}
}

async function saveIpsec() {
	var r = await call('SetIPsecConfig', {
		L2TP_bool:           isChecked('l2tp_ipsec_on'),
		L2TP_Raw_bool:       isChecked('l2tp_raw_on'),
		EtherIP_bool:        isChecked('etherip_on'),
		IPsec_Secret_str:    rawVal('ipsec_psk'),
		L2TP_DefaultHub_str: rawVal('ipsecDefaultHub') || (currentHub || ''),
	});
	if (r.error) { showApiError('Error saving IPsec configuration', r); return; }
	alert('IPsec configuration saved.');
}

// ============================================================
// SERVER CONFIG
// ============================================================

async function saveServerConfig() {
	var pwd = rawVal('newServerPassword');
	var pwd2 = rawVal('newServerPasswordConfirm');
	if (!pwd)       { alert('Nothing to save. Set a new admin password first.'); return; }
	if (pwd !== pwd2) { alert('Passwords do not match.'); return; }
	var r = await tryMethods(['SetServerPassword','SetServerAdminPassword'], { Password_str:pwd });
	if (r.error) { alert('Error saving server configuration or API not available.'); return; }
	setVal('newServerPassword', '');
	setVal('newServerPasswordConfirm', '');
	alert('Server configuration saved.');
}

// ============================================================
// HUB CRUD
// ============================================================

async function createHub() {
	var name    = val('newHubName');
	var pass = rawVal('newHubPass');
	var enabled = isChecked('newHubEnabled');
	if (!name || !pass) { alert('Hub name and password are required.'); return; }
	var r = await call('CreateHub', { HubName_str:name, AdminPassword_str:pass });
	if (r.error) { alert('Error creating hub.'); return; }
	if (!enabled) { await call('SetHubOffline', { HubName_str:name }); }
	$('#createHubModal').modal('hide');
	setVal('newHubName', '');
	setVal('newHubPass', '');
	setChecked('newHubEnabled', true);
	await initApp();
}

async function deleteHub() {
	if (!currentHub || !confirm("Delete hub '" + currentHub + "'?")) { return; }
	var r = await call('DeleteHub', { HubName_str:currentHub });
	if (r.error) { alert('Error deleting hub.'); return; }
	await initApp();
}

// ============================================================
// LOCAL BRIDGE / TAP
// ============================================================

async function refreshInterfaceTable() {
	var r = await call('EnumLocalBridge');
	if (r.error || !r.result) {
		document.getElementById('ifTable').innerHTML = emptyRow(5, 'API not available or error.');
		return;
	}
	fillTable('ifTable', resultList(r, 'LocalBridgeList'), 5, 'No local bridge connections.', function(bridge) {
		var deviceName = bridge.DeviceName_str || '-';
		var hubName    = bridge.HubNameLB_str  || '-';
		var tapMode    = !!bridge.TapMode_bool;
		var active     = !!bridge.Active_bool;
		var online     = !!bridge.Online_bool;
		var statusRaw  = bridge.Status_str || bridge.State_str || bridge.BridgeStatus_str || '';
		var statusText, statusClass;
		if (statusRaw) {
			statusText  = String(statusRaw);
			statusClass = /operat|active|running|up|online/i.test(statusText) ? 'label-success'
			            : /stop|down|disable|inactive|offline/i.test(statusText) ? 'label-warning'
			            : 'label-default';
		} else if (active) {
			statusText = 'Operating'; statusClass = 'label-success';
		} else if (online) {
			statusText = 'Enabled';   statusClass = 'label-info';
		} else {
			statusText = 'Stopped';   statusClass = 'label-warning';
		}
		return '<tr>' +
			'<td><span class="label ' + statusClass + '">' + statusText + '</span></td>' +
			'<td>' + escapeHtml(hubName) + '</td>' +
			'<td>' + escapeHtml(deviceName) + '</td>' +
			'<td><span class="label ' + (tapMode ? 'label-info' : 'label-default') + '">' + (tapMode ? 'TAP Device' : 'Physical Adapter') + '</span></td>' +
			'<td><button class="btn btn-xs btn-danger" onclick="deleteLocalBridge(\'' + escapeAttr(hubName) + '\',\'' + escapeAttr(deviceName) + '\')" title="Delete"><i class="fa fa-trash"></i></button></td>' +
			'</tr>';
	});
}

function toggleLocalBridgeDeviceInput() {
	var typeSelect  = document.getElementById('newLocalBridgeType');
	var deviceSelect = document.getElementById('newLocalBridgeDeviceSelect');
	var deviceText  = document.getElementById('newLocalBridgeDeviceText');
	var deviceLabel = document.getElementById('newLocalBridgeDeviceLabel');
	var isTap = !!(typeSelect && typeSelect.value === 'tap');
	if (deviceLabel) { deviceLabel.textContent = isTap ? 'TAP Device Name' : 'Physical Interface'; }
	if (isTap) {
		if (deviceSelect) { deviceSelect.style.display = 'none'; }
		if (deviceText)   { deviceText.style.display = ''; deviceText.placeholder = 'e.g. softether0'; }
	} else {
		if (deviceSelect) { deviceSelect.style.display = ''; }
		if (deviceText) {
			deviceText.placeholder = 'e.g. em0, igb0, re0';
			deviceText.style.display = (deviceSelect && deviceSelect.value === '__custom__') ? '' : 'none';
		}
	}
}

function onLocalBridgeTypeChange() {
	toggleLocalBridgeDeviceInput();
	var typeSelect = document.getElementById('newLocalBridgeType');
	if (typeSelect && typeSelect.value === 'physical') {
		updateLocalBridgeDeviceList().catch(console.error);
	}
}

async function updateLocalBridgeDeviceList() {
	var typeSelect   = document.getElementById('newLocalBridgeType');
	var deviceSelect = document.getElementById('newLocalBridgeDeviceSelect');
	if (!deviceSelect || (typeSelect && typeSelect.value !== 'physical')) {
		toggleLocalBridgeDeviceInput();
		return;
	}
	deviceSelect.innerHTML = '<option value="">(Loading interfaces...)</option>';
	var r = await tryMethods(['EnumEthernet','EnumEth']);
	var names = [];
	if (!r.error && r.result) {
		var rawList = r.result.EthernetList || r.result.EthList || r.result.DeviceList || [];
		rawList.forEach(function(it) {
			var n = String(it && (it.DeviceName_str || it.Name_str || it.DeviceName_utf || it.Name_utf || it.DeviceName || it.Name) || '').trim();
			if (n && names.indexOf(n) === -1) { names.push(n); }
		});
	}
	deviceSelect.innerHTML = names.length
		? '<option value="">(Select interface...)</option>' +
		  names.map(function(n) { return '<option value="' + escapeHtml(n) + '">' + escapeHtml(n) + '</option>'; }).join('') +
		  '<option value="__custom__">(Custom...)</option>'
		: '<option value="__custom__">(No interface list from API, type manually)</option>';
	toggleLocalBridgeDeviceInput();
}

async function createLocalBridge() {
	var hubName      = val('newLocalBridgeHub');
	var type         = (rawVal('newLocalBridgeType') || 'physical').trim();
	var deviceSelect = document.getElementById('newLocalBridgeDeviceSelect');
	var deviceText   = document.getElementById('newLocalBridgeDeviceText');
	var selected     = deviceSelect ? String(deviceSelect.value || '').trim() : '';
	var typed        = deviceText   ? String(deviceText.value   || '').trim() : '';
	var deviceName   = (type === 'tap') ? typed : ((selected && selected !== '__custom__') ? selected : typed);
	if (!hubName)    { alert('Please select a hub.'); return; }
	if (!deviceName) { alert(type === 'tap' ? 'Please enter a TAP device name.' : 'Please select or enter a physical interface name.'); return; }
	var r = await call('AddLocalBridge', { HubNameLB_str:hubName, DeviceName_str:deviceName, TapMode_bool:(type==='tap') });
	if (r.error) { showApiError('Error creating local bridge', r); return; }
	setVal('newLocalBridgeHub', '');
	setVal('newLocalBridgeType', 'physical');
	setVal('newLocalBridgeDeviceText', '');
	$('#createTapModal').modal('hide');
	await refreshInterfaceTable();
	alert((type === 'tap' ? 'TAP bridge' : 'Local bridge') + ' created successfully.');
}

async function deleteLocalBridge(hubName, deviceName) {
	if (!confirm('Delete bridge: ' + deviceName + ' from hub ' + hubName + '?')) { return; }
	var r = await call('DeleteLocalBridge', { HubNameLB_str:hubName, DeviceName_str:deviceName });
	if (r.error) { showApiError('Error deleting local bridge', r); return; }
	await refreshInterfaceTable();
	alert('Local bridge deleted successfully.');
}

// ============================================================
// CONNECTIONS TABLE
// ============================================================

async function refreshConnTable() {
	if (!currentHub) { return; }
	var r = await call('EnumConnection');
	var connTypes = {0:'Client', 1:'Admin', 2:'Server', 3:'Bridge', 4:'Layer3'};
	fillTable('connTable', resultList(r, 'ConnectionList'), 6, 'No TCP connections.', function(row) {
		var ip = row.RemoteIp_ip !== undefined ? humanize('RemoteIp_ip', row.RemoteIp_ip) : (row.RemoteIp_str || '-');
		return '<tr>' +
			'<td><code>' + escapeHtml(row.Name_str || '-') + '</code></td>' +
			'<td>' + escapeHtml(row.Hostname_str || '-') + '</td>' +
			'<td>' + escapeHtml(ip) + '</td>' +
			'<td>' + escapeHtml(String(row.RemotePort_u32 || '-')) + '</td>' +
			'<td>' + escapeHtml(connTypes[row.Type_u32] || String(row.Type_u32 || '-')) + '</td>' +
			'<td>' + escapeHtml(humanize('CreatedTime_dt', row.ConnectedTime_dt || row.CreatedTime_dt)) + '</td>' +
			'</tr>';
	}, '<tr><th>Name</th><th>Hostname</th><th>IP Address</th><th>Port</th><th>Type</th><th>Created</th></tr>');
}

// ============================================================
// CA CERTIFICATES
// ============================================================

async function refreshCaList() {
	if (!currentHub) { return; }
	var r = await call('EnumCa', { HubName_str: currentHub });
	fillTable('caTable', resultList(r, 'CaList'), 3, 'No CA certificates.', function(ca) {
		var key = ca.Key_u32 !== undefined ? ca.Key_u32 : (ca.CaId_u32 !== undefined ? ca.CaId_u32 : '');
		var subj = ca.SubjectName_utf || ca.SubjectName_str || ca.Subject_str || '-';
		var exp  = ca.Expires_dt || ca.ExpirationDate_dt || '';
		var expStr = exp ? humanize('Expires_dt', exp) : '-';
		return '<tr>' +
			'<td>' + escapeHtml(subj) + '</td>' +
			'<td>' + escapeHtml(expStr) + '</td>' +
			'<td><button class="btn btn-xs btn-danger" onclick="deleteCa(' + key + ')" title="Delete CA"><i class="fa fa-trash"></i></button></td>' +
			'</tr>';
	});
}

async function addCa() {
	var pem = rawVal('caCertPem');
	pem = pem.trim();
	if (!pem) { alert('Please paste a PEM certificate.'); return; }
	var b64 = btoa(pem);
	var r = await call('AddCa', { HubName_str: currentHub, Cert_bin: b64 });
	if (r.error) {
		showApiError('Error adding CA certificate', r);
		return;
	}
	$('#addCaModal').modal('hide');
	setVal('caCertPem', '');
	await refreshCaList();
}

function deleteCa(key) {
	return confirmCall('Delete CA certificate #' + key + '?', 'DeleteCa',
		{ HubName_str: currentHub, Key_u32: key }, 'Error deleting CA certificate', refreshCaList);
}

// ============================================================
// HUB SETTINGS (SetHub)
// ============================================================

function onHubMsgToggle() {
	var show = isChecked('hubSettingsShowMsg');
	document.getElementById('hubSettingsMsgRow').style.display = show ? '' : 'none';
}

async function openHubSettings() {
	if (!currentHub) { return; }
	var r = await tryMethods(['GetHub', 'GetHubStatus'], { HubName_str: currentHub });
	if (!r.error && r.result) {
		var h = r.result;
		setVal('hubSettingsType', h.HubType_u32 !== undefined ? h.HubType_u32 : 0);
		setVal('hubSettingsMaxSessions', h.MaxSessionCount_u32 !== undefined ? h.MaxSessionCount_u32 : 0);
		setChecked('hubSettingsNoEnum', !!h.NoEnum_bool);
	}
	var rm = await call('GetHubMsg', { HubName_str: currentHub });
	var msgText = '';
	if (!rm.error && rm.result) {
		msgText = rm.result.Msg_utf || rm.result.Msg_str || '';
	}
	var hasMsg = msgText.trim().length > 0;
	setChecked('hubSettingsShowMsg', hasMsg);
	setVal('hubSettingsMsg', msgText);
	document.getElementById('hubSettingsMsgRow').style.display = hasMsg ? '' : 'none';
	$('#hubSettingsModal').modal('show');
}

async function saveHubSettings() {
	var hubType    = intVal('hubSettingsType', 0);
	var maxSess    = intVal('hubSettingsMaxSessions', 0);
	var noEnum = isChecked('hubSettingsNoEnum');
	var r = await call('SetHub', {
		HubName_str: currentHub,
		HubType_u32: hubType,
		MaxSessionCount_u32: maxSess,
		NoEnum_bool: noEnum
	});
	if (r.error) { showApiError('Error saving hub settings', r); return; }
	var showMsg = isChecked('hubSettingsShowMsg');
	var msgText  = showMsg ? rawVal('hubSettingsMsg') : '';
	await call('SetHubMsg', { HubName_str: currentHub, Msg_utf: msgText });
	$('#hubSettingsModal').modal('hide');
	await refreshHubInfo();
	alert('Hub settings saved.');
}

// ============================================================
// MAINTENANCE / SERVER ACTIONS
// ============================================================

function rebootServer() {
	return confirmCall('Reboot the VPN Server process? Active sessions will be disconnected.',
		'RebootServer', {}, 'Reboot failed', function() {
			alert('VPN Server is rebooting. Reconnect in a few seconds.');
			disconnectApp();
		});
}

function flushServerLog() {
	return confirmCall('Flush all server logs now?', 'FlushLog', {}, 'Error flushing log',
		function() { alert('Log flushed.'); });
}

async function downloadServerConfig() {
	var r = await call('GetConfig');
	if (r.error || !r.result) { showApiError('Error fetching config', r); return; }
	var cfg = r.result.FileBody_bin || r.result.Config_str || r.result.Body_str || JSON.stringify(r.result, null, 2);
	if (r.result.FileBody_bin) {
		try { cfg = atob(r.result.FileBody_bin); } catch(e) { cfg = r.result.FileBody_bin; }
	}
	downloadBlob(cfg, 'vpn_server_config.txt');
}

// Wire up file-input preview for import modal
document.addEventListener('DOMContentLoaded', function() {
	var fileInput = document.getElementById('importConfigFile');
	if (!fileInput) { return; }
	fileInput.addEventListener('change', function() {
		var file = fileInput.files && fileInput.files[0];
		var preview = document.getElementById('importConfigPreview');
		var previewText = document.getElementById('importConfigPreviewText');
		if (!file) { if (preview) { preview.style.display = 'none'; } return; }
		var reader = new FileReader();
		reader.onload = function(e) {
			var lines = (e.target.result || '').split('\n').slice(0, 20).join('\n');
			if (previewText) { previewText.textContent = lines; }
			if (preview) { preview.style.display = 'block'; }
		};
		reader.readAsText(file);
	});
});

async function importServerConfig() {
	var fileInput = document.getElementById('importConfigFile');
	var file = fileInput && fileInput.files && fileInput.files[0];
	if (!file) { alert('Please select a configuration file.'); return; }
	if (!confirm('Import this configuration? The VPN Server will restart and current config will be overwritten.')) { return; }
	var reader = new FileReader();
	reader.onload = async function(e) {
		var content = e.target.result || '';
		var b64 = btoa(unescape(encodeURIComponent(content)));
		var r = await call('SetConfig', { FileBody_bin: b64 });
		if (r.error) {
			showApiError('Import failed', r);
			return;
		}
		$('#importConfigModal').modal('hide');
		fileInput.value = '';
		document.getElementById('importConfigPreview').style.display = 'none';
		alert('Configuration imported. The VPN Server is restarting.');
		disconnectApp();
	};
	reader.readAsText(file);
}

async function refreshDdnsStatus() {
	var r = await tryMethods(['GetDDnsClientStatus']);
	var info   = document.getElementById('ddnsInfo');
	var wrap   = document.getElementById('ddnsInfoWrap');
	var btn    = document.getElementById('ddnsSetBtn');
	var notice = document.getElementById('ddnsUnsupportedMsg');
	var unsupported = r.error || !r.result || Array.isArray(r.result);
	if (notice) { notice.style.display = unsupported ? '' : 'none'; }
	if (btn)    { btn.disabled = unsupported; }
	if (!info)  { return; }
	if (unsupported) { info.innerHTML = ''; if (wrap) { wrap.style.display = 'none'; } return; }
	var d = r.result;
	var rows = [];
	function add(label, v) { if (v !== undefined && v !== null && v !== '') { rows.push([label, v]); } }
	add('FQDN (IPv4)', d.DdnsFqdn_str || d.DdnsHostname_str);
	add('FQDN (IPv6)', d.DdnsFqdnForIPv6_str);
	add('Internet IP', d.CurrentPublicIp_str);
	add('Internet IPv6', d.CurrentPublicIpV6_str);
	add('Status', d.DdnsStatus_str);
	info.innerHTML = kvRows(rows);
	if (wrap) { wrap.style.display = rows.length ? '' : 'none'; }
}

// ============================================================
// MAINTENANCE / RESET
// ============================================================

function showResetConfirm(target) {
	var label = (target === 'bridge') ? 'VPN Bridge' : 'VPN Server';
	if (!confirm('Are you sure you want to reset ' + label + ' configuration?\n\nThis will:\n- Stop only the selected service (if running)\n- Delete only the selected service configuration\n- This action cannot be undone!\n\nContinue?')) { return; }
	if (!confirm('FINAL CONFIRMATION: Reset ' + label + ' configuration?')) { return; }
	resetConfiguration(target);
}

function resetConfiguration(target) {
	var form = document.createElement('form');
	form.method = 'POST';
	form.action = window.location.href;

	var csrf = csrfField();
	[
		[csrf.name, csrf.token],
		['action',  (target === 'bridge') ? 'reset_bridge_config' : 'reset_server_config'],
		['confirm', 'yes']
	].forEach(function(pair) {
		if (!pair[1]) { return; }
		var f = document.createElement('input');
		f.type = 'hidden'; f.name = pair[0]; f.value = pair[1];
		form.appendChild(f);
	});
	document.body.appendChild(form);
	form.submit();
}

// ============================================================
// REMOTE HUB DISCOVERY (CASCADE MODALS)
// ============================================================

var remoteHubReloadTimers = { new:null, edit:null };

function scheduleRemoteHubReload(mode, forceReload) {
	var prefix = (mode === 'edit') ? 'edit' : 'new';
	clearTimeout(remoteHubReloadTimers[prefix]);
	remoteHubReloadTimers[prefix] = setTimeout(function() {
		var hostEl = document.getElementById(prefix + 'CascadeHost');
		if (hostEl && (hostEl.value || '').trim()) { loadRemoteHubs(prefix, !!forceReload); }
	}, 250);
}

async function loadRemoteHubs(mode, forceReload) {
	var prefix   = (mode === 'edit') ? 'edit' : 'new';
	var g        = function(id) { var el = document.getElementById(prefix + id); return el ? el.value : ''; };
	var host     = g('CascadeHost').trim();
	var port     = parseInt(g('CascadePort'), 10) || 443;
	var apiUser  = val('apiUser') || 'Administrator';
	var apiPass  = rawVal('apiPass');
	var modalUser = g('CascadeUser').trim();
	var modalPass = g('CascadePass');
	var hubSelect = document.getElementById(prefix + 'CascadeHub');
	var prevValue = hubSelect ? hubSelect.value : '';
	var sig = [host, port, modalUser, modalPass, g('CascadeHubUser'), g('CascadeHubPass')].join('|');

	if (hubSelect && !forceReload && hubSelect.dataset.lastHubLoadSignature === sig && hubSelect.options.length > 1) { return; }
	if (!host) { if (hubSelect) { hubSelect.innerHTML = '<option value="">(Enter hostname first)</option>'; hubSelect.dataset.lastHubLoadSignature = ''; } return; }
	hubSelect.innerHTML = '<option value="">Loading...</option>';

	var body = proxyBody('EnumHub', {}, { remote_host: host, remote_port: String(port) });

	async function attempt(authHeader) {
		var headers = authHeader ? { Authorization: authHeader } : {};
		var res = await fetch('/vpn_softether5_api_proxy.php', { method:'POST', headers:headers, credentials:'same-origin', body:body });
		var data = null;
		try { data = await res.json(); } catch(e) {}
		return { ok:res.ok, status:res.status, data:data };
	}

	var attempts = [{ label:'no-auth', auth:'' }];
	if (modalUser || modalPass) { attempts.push({ label:'modal', auth:'Basic ' + btoa((modalUser||'Administrator') + ':' + modalPass) }); }
	attempts.push({ label:'api', auth:'Basic ' + btoa(apiUser + ':' + apiPass) });

	var finalData = null, lastErr = '';
	for (var i = 0; i < attempts.length; i++) {
		var resp = await attempt(attempts[i].auth);
		if (!resp.ok || !resp.data || resp.data.error) {
			lastErr = resp.data && resp.data.error ? String(resp.data.error) : ('HTTP ' + resp.status);
			continue;
		}
		finalData = resp.data;
		break;
	}

	if (!finalData) {
		hubSelect.innerHTML = '<option value="">(' + escapeHtml(lastErr || 'Failed to load remote hubs') + ')</option>';
		return;
	}

	var hubs = (finalData.result && (finalData.result.HubList || finalData.result.Hubs)) || [];
	hubSelect.innerHTML = hubOptionsHtml(hubs, '-- Select a hub --') +
		(!hubs.length ? '<option value="" disabled>(No hubs found)</option>' : '');

	if (prevValue) { hubSelect.value = prevValue; }
	hubSelect.dataset.lastHubLoadSignature = sig;
}

function bindRemoteHubReloadListeners(mode) {
	var prefix = (mode === 'edit') ? 'edit' : 'new';
	['Host','Port','User','Pass','HubUser','HubPass'].forEach(function(field) {
		var el = document.getElementById(prefix + 'Cascade' + field);
		if (!el || el.dataset.rhrlBound === '1') { return; }
		['input','change','blur','keyup','paste'].forEach(function(evt) {
			el.addEventListener(evt, function() { scheduleRemoteHubReload(mode, true); }, false);
		});
		el.dataset.rhrlBound = '1';
	});
	var hubSel = document.getElementById(prefix + 'CascadeHub');
	if (hubSel && hubSel.dataset.rhrlBound !== '1') {
		['focus','click','change'].forEach(function(evt) {
			hubSel.addEventListener(evt, function() { scheduleRemoteHubReload(mode, true); }, false);
		});
		hubSel.dataset.rhrlBound = '1';
	}
}

// ============================================================
// MODAL EVENT HOOKS
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
	if (window.jQuery && typeof $ === 'function') {
		$('#createGroupModal').on('show.bs.modal', function() {
			initGroupPolicyUI('new', null);
		});
		$('#createUserModal').on('show.bs.modal', function() {
			var sel = document.getElementById('newUserGroup');
			sel.innerHTML = '<option value="">-- No Group --</option>';
			if (!currentHub) { return; }
			var added = {};
			function addOpt(name) {
				if (!name || added[name]) { return; }
				var opt = document.createElement('option');
				opt.value = name; opt.textContent = name;
				sel.appendChild(opt);
				added[name] = true;
			}
			function processGroups(g) {
				var groups = resultList(g, 'GroupList', 'Group', 'Groups');
				if (!groups.length && groupsList.length) { groups = groupsList; }
				groups.forEach(function(grp) { addOpt(grp.Name_str || grp.GroupName_str || grp.Name_utf || ''); });
			}
			call('EnumGroup', { HubName_str:currentHub }).then(processGroups).catch(function() {
				call('EnumGroup', {}).then(processGroups).catch(function() {
					groupsList.forEach(function(g) { addOpt(g.Name_str || ''); });
				});
			});
		});
	}
});


document.addEventListener('DOMContentLoaded', function() {
	if (window.jQuery && typeof $ === 'function') {
		$('#createTapModal').on('show.bs.modal', function() {
			var sel = document.getElementById('newLocalBridgeHub');
			if (!sel) { return; }
			var hubSel = document.getElementById('hubSelector');
			sel.innerHTML = '<option value="">(Select a hub...)</option>';
			if (hubSel) {
				for (var i = 0; i < hubSel.options.length; i++) {
					var name = hubSel.options[i].value || hubSel.options[i].textContent || '';
					if (!name) { continue; }
					var opt = document.createElement('option');
					opt.value = name; opt.textContent = name;
					sel.appendChild(opt);
				}
			}
			if (currentHub) { sel.value = currentHub; }
			onLocalBridgeTypeChange();
		});

		$('#editCascadeModal').on('shown.bs.modal', function() {
			var el = document.getElementById('editCascadeHost');
			if (el && (el.value || '').trim()) { loadRemoteHubs('edit', true); }
		});

		$('#createCascadeModal').on('shown.bs.modal', function() {
			var el = document.getElementById('newCascadeHost');
			if (el && (el.value || '').trim()) { loadRemoteHubs('new', true); }
		});

		// Bind Enter key on credential inputs here, not inline, so initApp() is
		// guaranteed to be defined before the handler can ever fire.
		$('#apiUser, #apiPass').on('keypress', function(e) {
			if (e.key === 'Enter') { initApp(); }
		});

		// Use Bootstrap's own cancellable show.bs.tab event -- the official way
		// to block tab activation. Fires before any UI change, fully preventable.
		$(document).on('show.bs.tab', 'a[data-toggle="tab"]', function(e) {
			if ($(e.target).closest('li').hasClass('disabled')) {
				e.preventDefault();
				return false;
			}
		});

		$('a[href="#srvTabServices"]').on('shown.bs.tab', function() {
			refreshDdnsStatus().catch(function() {});
		});

		$('a[href="#hubTabSecurity"]').on('shown.bs.tab', function() {
			if (currentHub) { refreshCrlList().catch(function() {}); }
		});

		$('a[href="#hubTabSettings"]').on('shown.bs.tab', function() {
			if (currentHub) {
				refreshAdminOptions().catch(function() {});
				refreshExtOptions().catch(function() {});
				refreshWgkList().catch(function() {});
			}
		});

		setTimeout(function() {
			bindRemoteHubReloadListeners('new');
			bindRemoteHubReloadListeners('edit');
		}, 100);
	}
});

// window.load fires after browser autofill settles; the 200ms delay ensures
// we win the race against any autofocus the browser may apply.
window.addEventListener('load', function() {
	setTimeout(function() {
		var passEl = document.getElementById('apiPass');
		if (passEl) { passEl.focus(); }
	}, 200);
});

window.addEventListener('beforeunload', function() {
	cascadeRefresher.stop();
	pageRefresher.stop();
	var p = document.getElementById('apiPass');
	if (p) { p.value = ''; }
});

// ============================================================
// OPENVPN / SSTP
// ============================================================

async function refreshOpenVpnSstp() {
	var r = await call('GetOpenVpnSstpConfig');
	if (r.error || !r.result) { return; }
	var d = r.result;
	setChecked('openvpn_on', d.EnableOpenVPN_bool || d.OpenVpnEnable_bool);
	setChecked('sstp_on',    d.EnableSSTP_bool || d.SstpEnable_bool);
	setVal('openvpn_ports',  d.OpenVPNPortList_str || d.UdpPorts_str || '');
}

async function saveOpenVpnSstp() {
	var ov    = isChecked('openvpn_on');
	var st    = isChecked('sstp_on');
	var ports = val('openvpn_ports');
	var r = await call('SetOpenVpnSstpConfig', {
		EnableOpenVPN_bool: ov, EnableSSTP_bool: st, OpenVPN_bool: ov, SSTP_bool: st,
		OpenVPNPortList_str: ports, UdpPorts_str: ports
	});
	if (r.error) { showApiError('Error saving OpenVPN/SSTP', r); return; }
	alert('Saved.');
}

// ============================================================
// SYSLOG
// ============================================================

async function refreshSyslog() {
	var r = await call('GetSysLog');
	if (r.error || !r.result) { return; }
	var d = r.result;
	setVal('syslogType', d.SaveType_u32 !== undefined ? d.SaveType_u32 : 0);
	setVal('syslogHost', d.Hostname_str || '');
	setVal('syslogPort', d.Port_u32 || 514);
}

async function saveSyslog() {
	var type = intVal('syslogType', 0);
	var host = val('syslogHost');
	var port = intVal('syslogPort', 514);
	var r = await call('SetSysLog', { SaveType_u32: type, Hostname_str: host, Port_u32: port });
	if (r.error) { showApiError('Error saving syslog', r); return; }
	alert('Syslog saved.');
}

// ============================================================
// SERVER CERTIFICATE
// ============================================================

async function refreshServerCert() {
	var r = await tryMethods(['GetServerCertInfo', 'ServerCertGet']);
	var tbody = document.getElementById('serverCertInfo');
	if (!tbody) { return; }
	if (r.error || !r.result) {
		tbody.innerHTML = emptyRow(2, 'Certificate info not available.');
		return;
	}
	var d = r.result;
	var rows = [];
	function add(label, v) { if (v !== undefined && v !== null && String(v) !== '') { rows.push([label, v]); } }
	add('Subject', d.SubjectName_utf || d.SubjectName_str || d.Subject_str);
	add('Issuer', d.IssuerName_utf || d.IssuerName_str || d.Issuer_str);
	add('Not Before', d.NotBefore_dt ? humanize('NotBefore_dt', d.NotBefore_dt) : '');
	add('Not After', d.NotAfter_dt ? humanize('NotAfter_dt', d.NotAfter_dt) : (d.Expire_dt ? humanize('Expire_dt', d.Expire_dt) : ''));
	add('Serial Number',     d.SerialNumber_str || d.Serial_str);
	add('SHA-1 Fingerprint', d.Sha1Hash_bin ? d.Sha1Hash_bin : (d.Hash_bin || ''));
	tbody.innerHTML = kvRows(rows, '35%') || emptyRow(2, 'No certificate info.');
}

async function uploadServerCert() {
	var fileInput = document.getElementById('serverCertFile');
	var textarea  = document.getElementById('serverCertPem');
	var pem = '';
	if (fileInput && fileInput.files && fileInput.files[0]) {
		pem = await new Promise(function(resolve) {
			var reader = new FileReader();
			reader.onload = function(e) { resolve(e.target.result || ''); };
			reader.readAsText(fileInput.files[0]);
		});
	} else {
		pem = ((textarea || {}).value) || '';
	}
	pem = pem.trim();
	if (!pem) { alert('Provide a certificate file or paste PEM text.'); return; }
	var b64 = btoa(unescape(encodeURIComponent(pem)));
	var r = await call('ServerCertSet', { Cert_bin: b64 });
	if (r.error) { showApiError('Error uploading certificate', r); return; }
	$('#serverCertModal').modal('hide');
	if (fileInput) { fileInput.value = ''; }
	if (textarea)  { textarea.value = ''; }
	await refreshServerCert();
	alert('Server certificate updated.');
}

var _newCertSigningPem = null;

function onNewCertTypeChange() {
	var signed = isChecked('newCertTypeSigned');
	document.getElementById('newCertSigningRow').style.display = signed ? '' : 'none';
	if (!signed) { _newCertSigningPem = null; document.getElementById('newCertSigningFileName').textContent = ''; }
}

function onSigningFileSelected(input) {
	if (!input.files || !input.files[0]) { return; }
	var file = input.files[0];
	document.getElementById('newCertSigningFileName').textContent = file.name;
	var reader = new FileReader();
	reader.onload = function(e) { _newCertSigningPem = e.target.result; };
	reader.readAsText(file);
}

function initNewCertModal() {
	// Pre-fill CN from current cert if available
	var tbody = document.getElementById('serverCertInfo');
	var cn = '';
	if (tbody) {
		var rows = tbody.querySelectorAll('tr');
		rows.forEach(function(row) {
			var cells = row.querySelectorAll('td');
			if (cells.length >= 2 && cells[0].textContent.trim() === 'Subject') {
				var m = cells[1].textContent.match(/CN=([^,]+)/);
				if (m) { cn = m[1].trim(); }
			}
		});
	}
	setChecked('newCertTypeSelf', true);
	document.getElementById('newCertSigningRow').style.display = 'none';
	_newCertSigningPem = null;
	document.getElementById('newCertSigningFileName').textContent = '';
	setVal('newCertSigningFile', '');
	setVal('newCertCN', cn || (window.location.hostname || 'vpnserver'));
	setVal('newCertO', '');
	setVal('newCertOU', '');
	setVal('newCertC', '');
	setVal('newCertST', '');
	setVal('newCertL', '');
	setVal('newCertSerial', '');
	setVal('newCertDays', '3650');
	setVal('newCertBits', '2048');
}

async function submitNewServerCert() {
	var cn = val('newCertCN');
	if (!cn) { alert('Common Name (CN) cannot be empty.'); return; }
	var signed = isChecked('newCertTypeSigned');
	if (signed && !_newCertSigningPem) {
		alert('Please load the signing Certificate and Private Key first.');
		return;
	}
	var params = {
		CN:         cn,
		O:          val('newCertO'),
		OU:         val('newCertOU'),
		C:          val('newCertC'),
		ST:         val('newCertST'),
		L:          val('newCertL'),
		Serial:     val('newCertSerial'),
		Days:       intVal('newCertDays', 3650),
		Bits:       intVal('newCertBits', 2048),
		SignedBy:   signed ? (_newCertSigningPem || '') : '',
	};
	$('#newServerCertModal').modal('hide');
	var r = await call('ServerCertRegenerate', params);
	if (r.error) {
		showApiError('Error creating certificate', r);
		return;
	}
	await refreshServerCert();
	alert('New certificate created successfully.');
}

async function viewServerCert() {
	var r = await call('GetServerCertPem');
	if (r.error || !r.result || !r.result.Pem_str) {
		alert('Could not retrieve certificate PEM.');
		return;
	}
	var pem = r.result.Pem_str;
	// Populate PEM textarea
	var pemEl = document.getElementById('certViewPem');
	if (pemEl) { pemEl.value = pem; }
	// Populate detail table from cached cert info (already loaded)
	var tbody = document.getElementById('serverCertInfo');
	var table = document.getElementById('certViewTable');
	if (table && tbody) { table.innerHTML = tbody.innerHTML; }
	$('#serverCertViewModal').modal('show');
}

async function exportServerCert() {
	var r = await call('GetServerCertPem');
	if (r.error || !r.result || !r.result.Pem_str) {
		alert('Could not retrieve certificate for export.');
		return;
	}
	downloadBlob(r.result.Pem_str, 'softether_server.pem', 'application/x-pem-file');
}

// ============================================================
// DDNS SET HOSTNAME
// ============================================================

async function setDdnsHostname() {
	var name = val('ddnsHostname');
	if (!name) { alert('Enter a hostname.'); return; }
	var r = await call('SetDDnsClientConfig', { Hostname_str: name });
	if (r.error) {
		var code = r.detail && r.detail.code;
		if (code === 33) { return; } // already shown inline notice
		showApiError('Error setting DDNS hostname', r);
		return;
	}
	alert('DDNS hostname updated.');
	await refreshDdnsStatus();
}

// ============================================================
// HUB ADMIN / EXTENDED OPTIONS
// ============================================================

// Shared renderer for the Admin Options and Extended Options tables.
// saveFnName is the global function the inline onchange handler calls.
function renderHubOptionsTable(tbodyId, list, idPrefix, saveFnName, emptyMsg) {
	fillTable(tbodyId, list, 3, emptyMsg, function(opt) {
		var name   = opt.Name_str || opt.Name_utf || opt.OptionName_str || '-';
		var optVal = opt.Value_u32 !== undefined ? opt.Value_u32 : (opt.Value_str !== undefined ? opt.Value_str : '-');
		var desc   = opt.Descrption_utf || opt.Description_str || opt.Description_utf || '';
		var nameEsc = escapeAttr(name);
		var inputId = idPrefix + nameEsc.replace(/[^a-zA-Z0-9]/g, '_');
		return '<tr>' +
			'<td title="' + escapeHtml(desc) + '">' + escapeHtml(name) + '</td>' +
			'<td><input type="number" id="' + inputId + '" class="form-control input-sm" value="' + escapeHtml(String(optVal)) + '" style="width:80px;" onchange="' + saveFnName + '(\'' + nameEsc + '\',this.value)"></td>' +
			'<td style="white-space:nowrap;"><button class="btn btn-xs btn-warning" onclick="document.getElementById(\'' + inputId + '\').focus();document.getElementById(\'' + inputId + '\').select();" style="margin-right:2px;"><i class="fa fa-edit"></i></button></td></tr>';
	});
}

async function refreshAdminOptions() {
	if (!currentHub) { return; }
	var r = await call('GetHubAdminOptions', { HubName_str: currentHub });
	var list = (!r.error && Array.isArray(r.result)) ? r.result : resultList(r, 'AdminOptionList');
	renderHubOptionsTable('adminOptionsTable', list, 'adminOpt_', 'setAdminOption', 'No admin options available.');
}

async function refreshExtOptions() {
	if (!currentHub) { return; }
	var r = await call('GetHubExtendedOptions', { HubName_str: currentHub });
	var list = (!r.error && Array.isArray(r.result)) ? r.result : resultList(r, 'ExtOptionList', 'AdminOptionList');
	renderHubOptionsTable('extOptionsTable', list, 'extOpt_', 'setExtOption', 'No extended options available.');
}

async function setHubOption(method, name, value) {
	var r = await call(method, { HubName_str: currentHub, Name_str: name, Value_u32: parseInt(value, 10) || 0 });
	if (r.error) { showApiError('Error saving option', r); }
}

function setAdminOption(name, value) { return setHubOption('SetHubAdminOption', name, value); }
function setExtOption(name, value)   { return setHubOption('SetHubExtendedOption', name, value); }

// ============================================================
// HUB CRL
// ============================================================

async function refreshCrlList() {
	if (!currentHub) { return; }
	var r = await call('EnumCrl', { HubName_str: currentHub });
	fillTable('crlTable', resultList(r, 'CrlList'), 4, 'No CRL entries.', function(crl) {
		var key    = crl.Key_u32 !== undefined ? crl.Key_u32 : 0;
		var cn     = crl.CnName_utf || crl.CnName_str || crl.CommonName_str || crl.SubjectName_utf || '-';
		var serial = crl.Serial_str || crl.SerialNumber_str || '-';
		var exp    = crl.NotAfterDate_dt ? humanize('NotAfterDate_dt', crl.NotAfterDate_dt) : '-';
		return '<tr>' +
			'<td>' + escapeHtml(cn) + '</td>' +
			'<td><code>' + escapeHtml(serial) + '</code></td>' +
			'<td>' + escapeHtml(exp) + '</td>' +
			'<td><button class="btn btn-xs btn-danger" onclick="deleteCrl(' + key + ')"><i class="fa fa-trash"></i></button></td>' +
			'</tr>';
	});
}

async function addCrl() {
	var cn     = val('crlCn');
	var serial = val('crlSerial');
	if (!cn && !serial) { alert('Enter a Common Name or serial number.'); return; }
	var r = await call('AddCrl', { HubName_str: currentHub, CnName_utf: cn, Serial_str: serial });
	if (r.error) { showApiError('Error adding CRL entry', r); return; }
	$('#addCrlModal').modal('hide');
	setVal('crlCn', '');
	setVal('crlSerial', '');
	await refreshCrlList();
}

function deleteCrl(key) {
	return confirmCall('Delete CRL entry #' + key + '?', 'DeleteCrl',
		{ HubName_str: currentHub, Key_u32: key }, 'Error deleting CRL entry', refreshCrlList);
}

// ============================================================
// KEEP ALIVE
// ============================================================

async function refreshKeepAlive() {
	var r = await call('GetKeepConfig');
	if (r.error || !r.result) { return; }
	var d = r.result;
	setChecked('keepEnabled', d.UseKeepConnect_bool || d.KeepEnabled_bool || d.Enable_bool);
	setVal('keepProto',    (d.KeepConnectProtocol_u32 === 0 || d.Protocol_str === 'tcp') ? 'tcp' : 'udp');
	setVal('keepHost',     d.KeepConnectHost_str || d.Host_str || '');
	setVal('keepPort',     d.KeepConnectPort_u32 || d.Port_u32 || 80);
	setVal('keepInterval', d.KeepConnectInterval_u32 || d.Interval_u32 || 50);
}

async function saveKeepAlive() {
	var enabled  = isChecked('keepEnabled');
	var protocol = (rawVal('keepProto') || 'udp');
	var host     = val('keepHost');
	var port     = intVal('keepPort', 80);
	var interval = intVal('keepInterval', 50);
	await call(enabled ? 'KeepEnable' : 'KeepDisable');
	var r = await call('SetKeepConfig', {
		UseKeepConnect_bool: enabled,
		KeepConnectProtocol_u32: (protocol === 'tcp') ? 0 : 1,
		KeepConnectHost_str: host, KeepConnectPort_u32: port, KeepConnectInterval_u32: interval,
		Protocol: protocol, Host: host, Port: String(port), Interval: String(interval)
	});
	if (r.error) { showApiError('Error saving Keep Alive', r); return; }
	alert('Keep Alive settings saved.');
}

// ============================================================
// VPN AZURE / ICMP / DNS
// ============================================================

async function refreshAzureIcmpDns() {
	var r1 = await call('GetAzureStatus');
	var r2 = await call('GetVpnOverIcmpDns');

	var azureUnsupported = r1.error || !r1.result || Array.isArray(r1.result);
	var icmpUnsupported  = r2.error || !r2.result || Array.isArray(r2.result);
	var allUnsupported   = azureUnsupported && icmpUnsupported;

	var notice  = document.getElementById('azureIcmpUnsupportedMsg');
	var saveBtn = document.getElementById('azureIcmpSaveBtn');
	var azureEl = document.getElementById('azureEnabled');
	var icmpEl  = document.getElementById('icmpEnabled');
	var dnsEl   = document.getElementById('dnsEnabled');

	if (notice)  { notice.style.display = allUnsupported ? 'block' : 'none'; }
	if (saveBtn) { saveBtn.disabled = allUnsupported; }
	if (azureEl) { azureEl.disabled = azureUnsupported; }
	if (icmpEl)  { icmpEl.disabled  = icmpUnsupported; }
	if (dnsEl)   { dnsEl.disabled   = icmpUnsupported; }

	if (!azureUnsupported) {
		if (azureEl) azureEl.checked = !!(r1.result.IsEnabled_bool || r1.result.Enable_bool || r1.result.Enabled_bool);
		var st = r1.result.AzureHostName_str || r1.result.Hostname_str || '';
		var statusEl = document.getElementById('azureStatus');
		if (statusEl) { statusEl.innerHTML = st ? '<small class="text-muted">Host: ' + escapeHtml(st) + '</small>' : ''; }
	}
	if (!icmpUnsupported) {
		if (icmpEl) icmpEl.checked = !!(r2.result.EnableVpnOverIcmp_bool || r2.result.IcmpEnable_bool);
		if (dnsEl)  dnsEl.checked  = !!(r2.result.EnableVpnOverDns_bool  || r2.result.DnsEnable_bool);
	}
}

async function saveAzureIcmpDns() {
	var btn = document.getElementById('azureIcmpSaveBtn');
	if (btn && btn.disabled) { return; }
	var azure = isChecked('azureEnabled');
	var icmp  = isChecked('icmpEnabled');
	var dns   = isChecked('dnsEnabled');
	var r1 = await call('SetAzureEnable', { IsEnabled_bool: azure, Enable: azure ? 'yes' : 'no' });
	var r2 = await call('SetVpnOverIcmpDns', { EnableVpnOverIcmp_bool: icmp, EnableVpnOverDns_bool: dns, IcmpEnable: icmp ? 'yes' : 'no', DnsEnable: dns ? 'yes' : 'no' });
	var err1 = r1.error && r1.detail && r1.detail.code !== 33;
	var err2 = r2.error && r2.detail && r2.detail.code !== 33;
	if (err1 || err2) { alert('Error saving: ' + ((err1 ? JSON.stringify(r1.detail) : '') || (err2 ? JSON.stringify(r2.detail) : ''))); return; }
	if (!r1.error || !r2.error) { alert('Saved.'); }
}

// ============================================================
// SSL CIPHER
// ============================================================

async function refreshServerCipher() {
	var r = await call('GetServerCipher');
	if (r.error || !r.result) { return; }
	var cipherEl = document.getElementById('sslCipher');
	if (!cipherEl) { return; }
	var current = r.result.String_str || r.result.CipherName_str || r.result.Cipher_str || '';
	if (!current) { return; }
	var found = false;
	for (var i = 0; i < cipherEl.options.length; i++) {
		if (cipherEl.options[i].value === current) { cipherEl.selectedIndex = i; found = true; break; }
	}
	if (!found) {
		var opt = document.createElement('option');
		opt.value = current; opt.textContent = current + ' (current)';
		cipherEl.insertBefore(opt, cipherEl.firstChild);
		cipherEl.selectedIndex = 0;
	}
}

async function saveServerCipher() {
	var cipher = val('sslCipher');
	if (!cipher) { return; }
	var r = await call('SetServerCipher', { String_str: cipher, Cipher: cipher });
	if (r.error) { showApiError('Error saving cipher', r); return; }
	alert('SSL cipher saved.');
}

// ============================================================
// LOG FILES
// ============================================================

async function refreshLogFiles() {
	var tbody = document.getElementById('logFilesTable');
	if (tbody) { tbody.innerHTML = emptyRow(4, 'Loading...'); }
	var r = await call('EnumLogFile');
	fillTable('logFilesTable', resultList(r, 'LogFileList'), 4, 'No log files found.', function(f) {
		var path   = f.FilePath_str || f.FileName_str || f.Name_str || '-';
		var server = f.ServerName_str || '-';
		var size   = f.FileSize_u32 !== undefined ? fmtBytes(f.FileSize_u32) : '-';
		var pathEsc = escapeAttr(path);
		return '<tr>' +
			'<td><code>' + escapeHtml(path) + '</code></td>' +
			'<td>' + escapeHtml(server) + '</td>' +
			'<td>' + escapeHtml(size) + '</td>' +
			'<td><button class="btn btn-xs btn-default" onclick="viewLogFile(\'' + pathEsc + '\')" title="View"><i class="fa fa-eye"></i></button></td>' +
			'</tr>';
	});
}

// ============================================================
// VIRTUAL L3 SWITCHES
// ============================================================

async function refreshL3Switches() {
	var r = await call('EnumL3Switch');
	fillTable('l3SwitchTable', resultList(r, 'SwitchList', 'L3SwitchList'), 4, 'No L3 switches.', function(sw) {
		var name    = sw.Name_str || sw.SwitchName_str || '-';
		var online  = !!(sw.Online_bool || sw.Active_bool || sw.Running_bool);
		var ifCount = sw.InterfaceCount_u32 !== undefined ? sw.InterfaceCount_u32 : '-';
		var nameEsc = escapeAttr(name);
		return '<tr>' +
			'<td>' + escapeHtml(name) + '</td>' +
			'<td><span class="label ' + (online ? 'label-success' : 'label-warning') + '">' + (online ? 'Running' : 'Stopped') + '</span></td>' +
			'<td>' + escapeHtml(String(ifCount)) + '</td>' +
			'<td>' +
			'<button class="btn btn-xs btn-' + (online ? 'warning' : 'success') + '" onclick="toggleL3Switch(\'' + nameEsc + '\',' + !online + ')" style="margin-right:2px;">' + (online ? '<i class="fa-solid fa-stop icon-embed-btn"></i> Stop' : '<i class="fa-solid fa-play icon-embed-btn"></i> Start') + '</button>' +
			'<button class="btn btn-xs btn-danger" onclick="delL3Switch(\'' + nameEsc + '\')"><i class="fa fa-trash"></i></button>' +
			'</td></tr>';
	});
}

async function addL3Switch() {
	var name = val('newL3Name');
	if (!name) { alert('Switch name is required.'); return; }
	var r = await call('AddL3Switch', { SwitchName: name, SwitchName_str: name });
	if (r.error) { showApiError('Error creating L3 switch', r); return; }
	$('#createL3Modal').modal('hide');
	setVal('newL3Name', '');
	await refreshL3Switches();
}

function delL3Switch(name) {
	return confirmCall("Delete L3 switch '" + name + "'?", 'DelL3Switch',
		{ SwitchName: name, SwitchName_str: name }, 'Error deleting L3 switch', refreshL3Switches);
}

function toggleL3Switch(name, start) {
	return confirmCall(null, start ? 'StartL3Switch' : 'StopL3Switch',
		{ SwitchName: name, SwitchName_str: name }, 'Error toggling L3 switch', refreshL3Switches);
}

// ============================================================
// ETHERIP / L2TPv3 CLIENTS
// ============================================================

async function refreshEtherIpClients() {
	var r = await call('EnumEtherIpClient');
	fillTable('etherIpTable', resultList(r, 'EtherIpClientList'), 4, 'No EtherIP clients.', function(c) {
		var id   = c.Id_str || c.IpClientId_str || c.EtherIpId_str || '-';
		var hub  = c.HubName_str || c.VirtualHubName_str || '-';
		var user = c.UserName_str || c.Username_str || '-';
		var idEsc = escapeAttr(id);
		return '<tr>' +
			'<td><code>' + escapeHtml(id) + '</code></td>' +
			'<td>' + escapeHtml(hub) + '</td>' +
			'<td>' + escapeHtml(user) + '</td>' +
			'<td><button class="btn btn-xs btn-danger" onclick="deleteEtherIpClient(\'' + idEsc + '\')"><i class="fa fa-trash"></i></button></td>' +
			'</tr>';
	});
}

async function addEtherIpClient() {
	var id   = val('etherIpId') || '*';
	var hub  = val('etherIpHub');
	var user = val('etherIpUser');
	var pass = rawVal('etherIpPass');
	if (!hub || !user) { alert('Hub and username are required.'); return; }
	var r = await call('AddEtherIpClient', { IpClientId: id, IpClientId_str: id, HubName_str: hub, UserName_str: user, Password_str: pass });
	if (r.error) { showApiError('Error adding EtherIP client', r); return; }
	$('#addEtherIpModal').modal('hide');
	['etherIpId','etherIpHub','etherIpUser','etherIpPass'].forEach(function(id) { var el = document.getElementById(id); if (el) el.value = ''; });
	await refreshEtherIpClients();
}

function deleteEtherIpClient(id) {
	return confirmCall("Delete EtherIP client '" + id + "'?", 'DeleteEtherIpClient',
		{ IpClientId: id, IpClientId_str: id }, 'Error deleting EtherIP client', refreshEtherIpClients);
}

// ============================================================
// ACCESS RULE TOGGLE
// ============================================================

function toggleAccess(id, enable) {
	return confirmCall(null, enable ? 'EnableAccess' : 'DisableAccess',
		{ HubName_str: currentHub, Id_u32: id, Id: String(id) }, 'Error toggling access rule', refreshAccessList);
}

// ============================================================
// WIREGUARD KEYS
// ============================================================

async function refreshWgkList() {
	if (!currentHub) { return; }
	var r = await call('WgkEnum', { HubName_str: currentHub });
	// CLI fallback returns a bare array instead of { WgkList: [...] }
	var list = (!r.error && Array.isArray(r.result)) ? r.result : resultList(r, 'WgkList');
	fillTable('wgkTable', list, 3, 'No WireGuard keys.', function(wgk) {
		var user = wgk.UserName_str || wgk.Username_str || '-';
		var key  = wgk.Key_str || wgk.PublicKey_str || wgk.WgPublicKey_str || '-';
		var keyId = escapeAttr(key);
		var short = key.length > 32 ? key.substring(0, 32) + '…' : key;
		return '<tr>' +
			'<td>' + escapeHtml(user) + '</td>' +
			'<td><code title="' + escapeHtml(key) + '">' + escapeHtml(short) + '</code></td>' +
			'<td><button class="btn btn-xs btn-danger" onclick="deleteWgk(\'' + keyId + '\')"><i class="fa fa-trash"></i></button></td>' +
			'</tr>';
	});
}

async function addWgk() {
	var user   = val('wgkUser');
	var pubkey = val('wgkPublicKey');
	if (!user || !pubkey) { alert('Username and public key are required.'); return; }
	var r = await call('WgkAdd', { HubName_str: currentHub, UserName_str: user, PublicKey: pubkey, PublicKey_str: pubkey });
	if (r.error) { showApiError('Error adding WireGuard key', r); return; }
	$('#addWgkModal').modal('hide');
	setVal('wgkUser', '');
	setVal('wgkPublicKey', '');
	await refreshWgkList();
}

function deleteWgk(key) {
	return confirmCall('Delete WireGuard key?', 'WgkDelete',
		{ HubName_str: currentHub, Key_str: key, Key: key }, 'Error deleting WireGuard key', refreshWgkList);
}

// ============================================================
// OPENVPN CLIENT CONFIG GENERATOR
// ============================================================

async function downloadOpenVpnConfig() {
	var r = await call('MakeOpenVpnConfigFile');
	if (r.error || !r.result) {
		var detail = r.detail || {};
		if (detail.code === 142 || (typeof detail === 'string' && detail.indexOf('142') !== -1)) {
			alert('OpenVPN is not enabled on this server.\nPlease enable it first in the Server tab → OpenVPN / SSTP settings.');
		} else {
			showApiError('Error generating OpenVPN config', r);
		}
		return;
	}
	var data = r.result.Buffer_bin || r.result.FileBody_bin || r.result.ZipData_bin || '';
	if (!data) {
		alert('No config data returned from server.');
		return;
	}
	try {
		var binary = atob(data);
		var bytes = new Uint8Array(binary.length);
		for (var i = 0; i < binary.length; i++) { bytes[i] = binary.charCodeAt(i); }
		downloadBlob(new Blob([bytes], { type: 'application/zip' }), 'openvpn_config.zip');
	} catch(e) {
		alert('Error processing config data: ' + e.message);
	}
}

async function viewLogFile(path) {
	var titleEl   = document.getElementById('logFileViewTitle');
	var contentEl = document.getElementById('logFileViewContent');
	if (titleEl)   { titleEl.textContent   = path; }
	if (contentEl) { contentEl.textContent = 'Loading...'; }
	$('#logFileViewModal').modal('show');
	var r = await call('ReadLogFile', { FilePath_str: path, Path: path });
	var content = 'Error loading file.';
	if (!r.error && r.result) {
		if (r.result.Buffer_bin) {
			try { content = atob(r.result.Buffer_bin); } catch(e) { content = r.result.Buffer_bin; }
		} else {
			content = r.result.FileBody_str || r.result.Body_str || (typeof r.result === 'string' ? r.result : JSON.stringify(r.result, null, 2));
		}
	}
	if (contentEl) { contentEl.textContent = content; }
}
</script>

</div><!-- /.se-page -->

<?php include("foot.inc"); ?>