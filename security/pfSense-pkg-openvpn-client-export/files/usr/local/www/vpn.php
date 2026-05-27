<?php
/*
 * vpn.php
 *
 * Standalone VPN portal using pfSense LDAP authentication and local
 * FreeRADIUS package user data.
 *
 * FreeRADIUS provisioning only updates the local pfSense package config at
 * installedpackages/freeradius/config and then regenerates the FreeRADIUS
 * users text file with freeradius_users_resync(). SQL/MySQL/MariaDB
 * FreeRADIUS backends are not updated; no radcheck/radreply records are
 * created by this portal.
 */

header("X-Frame-Options: SAMEORIGIN");
header("Expires: 0");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, no-store, must-revalidate");

/*
 * VPN portal configuration.
 *
 * This is the only section administrators should normally need to edit.
 * All server names must match the names configured in pfSense under
 * System > User Manager > Authentication Servers.
 */
$vpn_portal_config = array(
	/* Display name shown in authenticator apps after scanning the OTP QR code. */
	"otp_qr_display_name" => "Company VPN",

	/*
	 * Optional LDAP authentication server name.
	 * Leave empty to use the first configured LDAP authentication server.
	 */
	"ldap_authserver_name" => "",

	/*
	 * Optional RADIUS authentication server name.
	 * Leave empty to use the first configured RADIUS authentication server.
	 */
	"radius_authserver_name" => "",

	/*
	 * Optional primary HA firewall hostname, FQDN, or management IP address.
	 * Leave empty to disable warnings for temporary provisioning on backup nodes.
	 */
	"primary_ha_hostname_or_ip" => "firewall-primary",

	/* LDAP or pfSense group name required for VPN access. Leave empty to allow every authenticated user. */
	"vpn_group_name" => "vpnuser",

	/* Normalize login names to lowercase because LDAP is case-insensitive while FreeRADIUS is not. */
	"lowercase_usernames" => true,

	/*
	 * Default values used when the portal automatically creates a local
	 * FreeRADIUS package user for VPN OTP.
	 */
	"freeradius_user_defaults" => array(
		"varuserspasswordencryption" => "Cleartext-Password",
		"varusersmotpenable" => "on",
		"varusersauthmethod" => "googleauth",
		/*
		 * VPN users log in with LDAP password followed by OTP code. The MOTP PIN
		 * is still required by the FreeRADIUS googleauth/MOTP record, but users do
		 * not type it during VPN login in this portal design.
		 */
		"varusersmotppin" => str_pad((string)random_int(0, 9999), 4, "0", STR_PAD_LEFT),
		"varusersmotpoffset" => "0",
		"description" => "Created by vpn.php",
		"varuserspointoftime" => "Daily",
		"varusersmaxtotaloctetstimerange" => "daily"
	),

	/*
	 * Download buttons shown in the VPN Software/Configuration panel.
	 * Disabled internal OpenVPN export actions are also blocked server-side.
	 */
	"download_buttons" => array(
		"config_inline_most_clients" => true,
		"config_inline_android" => false,
		"config_inline_connect" => false,
		"msi_windows_64" => true,
		"msi_windows_32" => false,
		"exe_windows_64" => true,
		"exe_windows_32" => false,
		"legacy_windows_10" => false,
		"legacy_windows_7" => false,
		"viscosity_bundle" => false,
		"viscosity_inline" => false
	)
);

require_once("/usr/local/pfSense/include/www/vpn_portal.inc");

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["export_act"])) {
	vpn_handle_openvpn_download_request($vpn_portal_config);
}

$view = vpn_handle_request($vpn_portal_config);
$show_error_only = !empty($view["portal_blocked"]);
$show_login_layout = (!$view["authenticated"] || $show_error_only);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>VPN-Portal</title>
	<link rel="stylesheet" href="/vendor/bootstrap/css/bootstrap.min.css" type="text/css" />
	<?php if ($show_login_layout): ?>
	<link rel="stylesheet" href="/css/login.css?v=<?= filemtime('/usr/local/www/css/login.css') ?>" type="text/css" />
	<style>
		body#login .vpn-login-pagebody {
			background: #1e3f75;
		}

		body#login .vpn-login-logo {
			text-align: center;
		}

		body#login form.login input::placeholder {
			color: rgba(255, 255, 255, .75);
		}

		body#login form.login .form-group {
			margin-bottom: 18px;
		}
	</style>
	<?php elseif (!$show_error_only): ?>
	<link rel="stylesheet" href="/vendor/font-awesome/css/all.min.css" />
	<link rel="stylesheet" href="/css/pfSense.css" />
	<style>
		.vpn-portal-panel-body {
			padding-left: 30px;
		}

		.vpn-portal-2fa-text {
			margin-bottom: 5px;
		}

		.vpn-portal-qrcode {
			margin-top: 5px;
		}

		.vpn-portal-qrcode-box {
			display: none;
			height: 200px;
			width: 200px;
		}

		.vpn-portal-2fa-setup {
			align-items: flex-start;
			display: flex;
			flex-wrap: wrap;
			gap: 16px 28px;
		}

		.vpn-portal-token-check {
			max-width: 280px;
			padding-top: 5px;
		}

		.vpn-portal-token-check .form-control {
			max-width: 150px;
		}

		.vpn-portal-token-input {
			align-items: center;
			display: flex;
			gap: 10px;
		}

		.vpn-portal-token-status {
			margin-top: 10px;
		}

		.vpn-portal-downloads {
			padding-bottom: 12px;
		}
	</style>
	<?php endif; ?>
</head>
<?php if ($show_login_layout): ?>
<body id="login">
<div id="total">
	<header>
		<div id="headerrow">
			<div class="row">
				<div class="col-sm-4">
					<div id="logodiv" class="nowarning vpn-login-logo">
						<?php include("/usr/local/www/logo.svg"); ?>
					</div>
				</div>
				<div class="col-sm-4 nowarning msgbox text-center text-danger"></div>
				<div class="col-sm-4 nowarning msgbox text-center">
					<span id="hostspan"><h4>VPN-Portal</h4></span>
				</div>
			</div>
		</div>
	</header>

	<div class="pagebody vpn-login-pagebody">
		<div class="col-sm-4"></div>

		<div class="col-sm-4 offset-md-4 logoCol">
			<div class="loginCont center-block">
				<?php vpn_print_messages($view["errors"], "danger"); ?>
				<?php vpn_print_messages($view["notices"], "success"); ?>
				<?php vpn_print_messages($view["warnings"], "warning"); ?>

				<?php if (empty($view["portal_blocked"])): ?>
				<form method="post" action="vpn.php" autocomplete="off" class="login">
					<p class="form-title">Sign In</p>
					<input type="hidden" name="csrf_token" value="<?= vpn_h($view["csrf_token"]) ?>" />

					<div class="form-group">
						<input id="username" name="username" type="text" value="<?= vpn_h($view["username"]) ?>" placeholder="Username" required="required" autofocus="autofocus" />
					</div>

					<div class="form-group">
						<input id="password" name="password" type="password" placeholder="Password" required="required" />
					</div>

					<input name="login" type="submit" class="btn btn-success btn-sm" value="Login" />
				</form>
				<?php endif; ?>
			</div>
		</div>

		<div class="col-sm-4"></div>
	</div>

	<footer id="3">
		<div id="footertext">
			<p class="text-muted">
				<?= print_credit() ?>
			</p>
		</div>
	</footer>
</div>
<?php else: ?>
<body>
<nav id="topmenu" class="navbar navbar-static-top navbar-inverse">
	<div class="container">
		<div class="navbar-header">
			<a class="navbar-brand" href="/vpn.php">
				<?php include("/usr/local/www/logo.svg"); ?>
			</a>
		</div>
	</div>
</nav>
<div class="container static">
	<header class="header">
		<ol class="breadcrumb"><li>VPN-Portal for <?= vpn_h($view["display_name"]) ?></li></ol>
	</header>

	<?php vpn_print_messages($view["errors"], "danger"); ?>
	<?php vpn_print_messages($view["notices"], "success"); ?>
	<?php vpn_print_messages($view["warnings"], "warning"); ?>

	<?php if ($view["authenticated"] && !$view["has_vpn_access"]): ?>
		<div class="alert alert-danger" role="alert">
			You do not have VPN permissions!
		</div>
	<?php endif; ?>

	<?php if ($view["authenticated"] && $view["has_vpn_access"] && $view["radius_user"] && !empty($view["radius_otp_secret"])): ?>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title">Two Factor Authentication (2FA)</h2>
		</div>
		<div class="panel-body vpn-portal-panel-body">
			<p class="vpn-portal-2fa-text">
				Scan the QR code with an authenticator app on your smartphone. The app will generate a new access code every few seconds. When logging in to the VPN, enter your password followed by the displayed access code.
			</p>
			<div class="vpn-portal-2fa-setup">
				<input type="hidden" id="description" value="<?= vpn_h($vpn_portal_config["otp_qr_display_name"]) ?>" />
				<input type="hidden" id="varusersauthmethod" value="<?= vpn_h($view["radius_auth_method"]) ?>" />
				<input type="hidden" id="varusersusername" value="<?= vpn_h($view["username"]) ?>" />
				<input type="hidden" id="varusersmotpinitsecret" value="<?= vpn_h($view["radius_otp_secret"]) ?>" />
				<div class="form-group vpn-portal-qrcode">
					<div id="qrcode" class="vpn-portal-qrcode-box"></div>
				</div>
				<div class="form-group vpn-portal-token-check">
					<label for="totp_token">Test authenticator code</label>
					<div class="vpn-portal-token-input">
						<input id="totp_token" type="text" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="123456" />
						<button type="button" id="totp_verify" class="btn btn-primary">Test</button>
					</div>
					<div id="totp_status" class="vpn-portal-token-status" aria-live="polite"></div>
				</div>
			</div>
			<script type="text/javascript" src="/freeradius/qrcode/qrcode.js"></script>
			<script type="text/javascript">
			//<![CDATA[
				var qrcode = new QRCode(document.getElementById("qrcode"), {
					width : 200,
					height : 200,
				});
				function genOTPQR() {
					var provider = encodeURIComponent(document.getElementById("description").value);
					if (provider.length == 0) {
						provider = encodeURIComponent("FreeRADIUS");
					}
					var user = encodeURIComponent(document.getElementById("varusersusername").value);
					var key = encodeURIComponent(document.getElementById("varusersmotpinitsecret").value);
					if ((user.length == 0) || (key.length == 0)) {
						return;
					}
					qrcode.makeCode("otpauth://totp/" + provider + ":" + user + "?secret=" + key + "&issuer=" + provider);
					document.getElementById("qrcode").style.display = "block";
				}

				/**
				 * Decode a Base32 TOTP secret into raw bytes.
				 *
				 * Google Authenticator secrets use the RFC 4648 alphabet A-Z and 2-7.
				 * Padding is ignored because FreeRADIUS stores the secret without it.
				 *
				 * @param {string} secret Base32 encoded TOTP secret.
				 * @returns {Uint8Array} Raw secret bytes for WebCrypto.
				 */
				function base32ToBytes(secret) {
					var alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
					var cleanSecret = secret.toUpperCase().replace(/=+$/g, "").replace(/\s+/g, "");
					var bits = "";
					var bytes = [];

					for (var i = 0; i < cleanSecret.length; i++) {
						var value = alphabet.indexOf(cleanSecret.charAt(i));
						if (value < 0) {
							throw new Error("Invalid Base32 secret.");
						}
						bits += value.toString(2).padStart(5, "0");
					}

					for (var offset = 0; offset + 8 <= bits.length; offset += 8) {
						bytes.push(parseInt(bits.substring(offset, offset + 8), 2));
					}

					return new Uint8Array(bytes);
				}

				/**
				 * Generate a six-digit TOTP value with HMAC-SHA1.
				 *
				 * This follows RFC 6238 with the Google Authenticator defaults used by
				 * FreeRADIUS: SHA1, six digits, and a 30-second time step.
				 *
				 * @param {string} secret Base32 encoded TOTP secret.
				 * @param {number} counter 30-second time-step counter.
				 * @returns {Promise<string>} Six-digit TOTP code.
				 */
				async function generateTOTP(secret, counter) {
					var key = await crypto.subtle.importKey(
						"raw",
						base32ToBytes(secret),
						{ name: "HMAC", hash: "SHA-1" },
						false,
						["sign"]
					);
					var counterBytes = new ArrayBuffer(8);
					var counterView = new DataView(counterBytes);
					counterView.setUint32(4, counter, false);
					var hmac = new Uint8Array(await crypto.subtle.sign("HMAC", key, counterBytes));
					var offset = hmac[hmac.length - 1] & 0x0f;
					var binary =
						((hmac[offset] & 0x7f) << 24) |
						((hmac[offset + 1] & 0xff) << 16) |
						((hmac[offset + 2] & 0xff) << 8) |
						(hmac[offset + 3] & 0xff);

					return String(binary % 1000000).padStart(6, "0");
				}

				/**
				 * Verify the user-entered authenticator code in the browser.
				 *
				 * FreeRADIUS checks the previous, current, and next 30-second window.
				 * Use the same tolerance here so the portal test behaves like the VPN
				 * login when the phone clock is slightly ahead or behind.
				 *
				 * @returns {Promise<void>}
				 */
				async function verifyTOTPToken() {
					var tokenInput = document.getElementById("totp_token");
					var status = document.getElementById("totp_status");
					var token = tokenInput.value.replace(/\s+/g, "");
					var secret = document.getElementById("varusersmotpinitsecret").value;

					status.className = "vpn-portal-token-status";
					status.textContent = "";

					if (!/^[0-9]{6}$/.test(token)) {
						status.className += " text-danger";
						status.textContent = "Enter the 6-digit code from your authenticator app.";
						return;
					}
					if (!window.crypto || !window.crypto.subtle) {
						status.className += " text-danger";
						status.textContent = "This browser cannot verify authenticator codes.";
						return;
					}

					try {
						var currentCounter = Math.floor(Date.now() / 1000 / 30);
						for (var drift = -1; drift <= 1; drift++) {
							if (await generateTOTP(secret, currentCounter + drift) === token) {
								status.className += " text-success";
								status.textContent = "Authenticator code verified.";
								return;
							}
						}
						status.className += " text-danger";
						status.textContent = "Invalid authenticator code.";
					} catch (error) {
						status.className += " text-danger";
						status.textContent = "The authenticator code could not be verified.";
					}
				}

				genOTPQR();
				document.getElementById("totp_verify").addEventListener("click", verifyTOTPToken);
				document.getElementById("totp_token").addEventListener("keyup", function(event) {
					if (event.key === "Enter") {
						verifyTOTPToken();
					}
				});
			//]]>
			</script>
		</div>
	</div>
	<?php endif; ?>

	<?php if ($view["authenticated"] && $view["has_vpn_access"] && $view["radius_user"] && !empty($view["radius_otp_secret"])): ?>
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title">Software/Configuration</h2>
		</div>
		<div class="panel-body vpn-portal-panel-body vpn-portal-downloads">
			<?php if ($view["openvpn_export"]["available"] ?? false): ?>
				<?php if (vpn_download_button_enabled($vpn_portal_config, "config_inline_most_clients") ||
				    vpn_download_button_enabled($vpn_portal_config, "config_inline_android") ||
				    vpn_download_button_enabled($vpn_portal_config, "config_inline_connect")): ?>
				Configuration:<br />
				<?php if (vpn_download_button_enabled($vpn_portal_config, "config_inline_most_clients")): ?>
				&nbsp;&nbsp; <button type="button" data-export-act="confinline" class="btn btn-sm btn-primary vpn-openvpn-download"><i class="fa-solid fa-download"></i> Most Clients</button>
				<?php endif; ?>
				<?php if (vpn_download_button_enabled($vpn_portal_config, "config_inline_android")): ?>
				&nbsp;&nbsp; <button type="button" data-export-act="confinlinedroid" class="btn btn-sm btn-primary vpn-openvpn-download"><i class="fa-solid fa-download"></i> Android</button>
				<?php endif; ?>
				<?php if (vpn_download_button_enabled($vpn_portal_config, "config_inline_connect")): ?>
				&nbsp;&nbsp; <button type="button" data-export-act="confinlineconnect" class="btn btn-sm btn-primary vpn-openvpn-download"><i class="fa-solid fa-download"></i> OpenVPN Connect (iOS/Android)</button>
				<?php endif; ?>
				<br /><br />
				<?php endif; ?>
				<?php if (vpn_download_button_enabled($vpn_portal_config, "msi_windows_64") ||
				    vpn_download_button_enabled($vpn_portal_config, "msi_windows_32")): ?>
				OpenVPN MSI-Installer (Windows, without Configuration):<br />
				<?php if (vpn_download_button_enabled($vpn_portal_config, "msi_windows_64")): ?>
				&nbsp;&nbsp; <a href="https://build.openvpn.net/downloads/releases/latest/openvpn-latest-stable-amd64.msi" class="btn btn-sm btn-primary"><i class="fa-solid fa-download"></i> 64-bit MSI</a>
				<?php endif; ?>
				<?php if (vpn_download_button_enabled($vpn_portal_config, "msi_windows_32")): ?>
				&nbsp;&nbsp; <a href="https://build.openvpn.net/downloads/releases/latest/openvpn-latest-stable-x86.msi" class="btn btn-sm btn-primary"><i class="fa-solid fa-download"></i> 32-bit MSI</a>
				<?php endif; ?>
				<br /><br />
				<?php endif; ?>
				<?php if (vpn_download_button_enabled($vpn_portal_config, "exe_windows_64") ||
				    vpn_download_button_enabled($vpn_portal_config, "exe_windows_32")): ?>
				OpenVPN EXE-Installer (Windows, with Configuration):<br />
				<?php if (vpn_download_button_enabled($vpn_portal_config, "exe_windows_64")): ?>
				&nbsp;&nbsp; <button type="button" data-export-act="inst-x64-current" class="btn btn-sm btn-primary vpn-openvpn-download"><i class="fa-solid fa-download"></i> 64-bit</button>
				<?php endif; ?>
				<?php if (vpn_download_button_enabled($vpn_portal_config, "exe_windows_32")): ?>
				&nbsp;&nbsp; <button type="button" data-export-act="inst-x86-current" class="btn btn-sm btn-primary vpn-openvpn-download"><i class="fa-solid fa-download"></i> 32-bit</button>
				<?php endif; ?>
				<br /><br />
				<?php endif; ?>
				<?php if (vpn_download_button_enabled($vpn_portal_config, "legacy_windows_10") ||
				    vpn_download_button_enabled($vpn_portal_config, "legacy_windows_7")): ?>
				Legacy Windows Installers:<br />
				<?php if (vpn_download_button_enabled($vpn_portal_config, "legacy_windows_10")): ?>
				&nbsp;&nbsp; <button type="button" data-export-act="inst-Win10" class="btn btn-sm btn-primary vpn-openvpn-download"><i class="fa-solid fa-download"></i> 10/2016/2019</button>
				<?php endif; ?>
				<?php if (vpn_download_button_enabled($vpn_portal_config, "legacy_windows_7")): ?>
				&nbsp;&nbsp; <button type="button" data-export-act="inst-Win7" class="btn btn-sm btn-primary vpn-openvpn-download"><i class="fa-solid fa-download"></i> 7/8/8.1/2012r2</button>
				<?php endif; ?>
				<br /><br />
				<?php endif; ?>
				<?php if (vpn_download_button_enabled($vpn_portal_config, "viscosity_bundle") ||
				    vpn_download_button_enabled($vpn_portal_config, "viscosity_inline")): ?>
				Viscosity (Mac OS X and Windows):<br />
				<?php if (vpn_download_button_enabled($vpn_portal_config, "viscosity_bundle")): ?>
				&nbsp;&nbsp; <button type="button" data-export-act="visc" class="btn btn-sm btn-primary vpn-openvpn-download"><i class="fa-solid fa-download"></i> Viscosity Bundle</button>
				<?php endif; ?>
				<?php if (vpn_download_button_enabled($vpn_portal_config, "viscosity_inline")): ?>
				&nbsp;&nbsp; <button type="button" data-export-act="confinlinevisc" class="btn btn-sm btn-primary vpn-openvpn-download"><i class="fa-solid fa-download"></i> Viscosity Inline Config</button>
				<?php endif; ?>
				<?php endif; ?>
			<?php else: ?>
				<div class="alert alert-warning" role="alert">
					<?= vpn_h($view["openvpn_export"]["message"] ?? gettext("No direct OpenVPN export target was found for this user.")) ?>
					<a href="/vpn_openvpn_export.php">OpenVPN Client Export</a>
				</div>
				<?php if (vpn_download_button_enabled($vpn_portal_config, "msi_windows_64") ||
				    vpn_download_button_enabled($vpn_portal_config, "msi_windows_32")): ?>
				OpenVPN MSI-Installer (Windows, without Configuration):<br />
				<?php if (vpn_download_button_enabled($vpn_portal_config, "msi_windows_64")): ?>
				&nbsp;&nbsp; <a href="https://build.openvpn.net/downloads/releases/latest/openvpn-latest-stable-amd64.msi" class="btn btn-sm btn-primary"><i class="fa-solid fa-download"></i> 64-bit MSI</a>
				<?php endif; ?>
				<?php if (vpn_download_button_enabled($vpn_portal_config, "msi_windows_32")): ?>
				&nbsp;&nbsp; <a href="https://build.openvpn.net/downloads/releases/latest/openvpn-latest-stable-x86.msi" class="btn btn-sm btn-primary"><i class="fa-solid fa-download"></i> 32-bit MSI</a>
				<?php endif; ?>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>
</div>
<?php endif; ?>

<?php if ($view["authenticated"] && $view["has_vpn_access"] && ($view["openvpn_export"]["available"] ?? false)): ?>
<script type="text/javascript">
//<![CDATA[
var vpnOpenVPNExport = <?= json_encode($view["openvpn_export"], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
var vpnPortalCsrfToken = <?= json_encode($view["csrf_token"]) ?>;

/**
 * Create one hidden form input for the OpenVPN Client Export POST request.
 *
 * The portal expects a classic form submission instead of query parameters, so
 * every selected export option is translated into a hidden input before the
 * temporary form is submitted.
 *
 * @param {string} varname Export option name expected by vpn.php.
 * @param {string} varvalue Export option value.
 * @returns {HTMLInputElement} Hidden input ready to be appended to the form.
 */
function make_form_variable(varname, varvalue) {
	var exportinput = document.createElement("input");
	exportinput.type = "hidden";
	exportinput.name = varname;
	exportinput.value = varvalue;
	return exportinput;
}

/**
 * Start an OpenVPN Client Export download for the selected export action.
 *
 * This posts only the requested action and the portal CSRF token. The server,
 * user/certificate identifiers, and export options are read back from the
 * authenticated portal session so users cannot alter them in the browser.
 *
 * @param {string} act OpenVPN export action, such as confinline or inst-x64-current.
 * @returns {void}
 */
function download_begin(act) {
	if (!vpnOpenVPNExport || !vpnOpenVPNExport.available || !vpnOpenVPNExport.server_id) {
		alert("No direct OpenVPN export target was found for this user.");
		return;
	}
	if (!vpnPortalCsrfToken) {
		alert("The OpenVPN export token could not be created. Please reload this page and try again.");
		return;
	}

	var exportform = document.createElement("form");
	exportform.method = "POST";
	exportform.action = "/vpn.php";
	exportform.target = "_self";
	exportform.style.display = "none";

	/*
	 * Keep the submitted data intentionally small. The PHP session already
	 * contains the export context that was created after LDAP/RADIUS success.
	 */
	exportform.appendChild(make_form_variable("export_act", act));
	exportform.appendChild(make_form_variable("csrf_token", vpnPortalCsrfToken));

	document.body.appendChild(exportform);
	exportform.submit();
}

/*
 * Bind OpenVPN download buttons without javascript: URLs so the HTML remains
 * closer to the pfSense/XHTML style rules.
 */
document.querySelectorAll(".vpn-openvpn-download").forEach(function(button) {
	button.addEventListener("click", function() {
		download_begin(button.getAttribute("data-export-act"));
	});
});
//]]>
</script>
<?php endif; ?>
<script type="text/javascript">
//<![CDATA[
/*
 * foot.inc normally runs after head.inc, which initializes the global events
 * queue consumed by pfSense.js. This standalone portal does not include
 * head.inc, so define the queue before foot.inc emits the pfSense scripts.
 */
var events = events || [];
//]]>
</script>
<!-- foot.inc starts by closing the normal pfSense page container. This tiny
     placeholder keeps the include balanced without pulling in head.inc or
     guiconfig.inc, which would require a pfSense administrator session. -->
<div>
<?php include("foot.inc"); ?>
