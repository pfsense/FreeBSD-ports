<?php
/*
 * vpn_portal.inc
 *
 * Helper functions for the standalone VPN portal at usr/local/www/vpn.php.
 *
 * FreeRADIUS provisioning only updates the local pfSense package config at
 * installedpackages/freeradius/config and then regenerates the FreeRADIUS
 * users text file with freeradius_users_resync(). SQL/MySQL/MariaDB
 * FreeRADIUS backends are not updated; no radcheck/radreply records are
 * created by this portal.
 */

require_once("auth.inc");
require_once("certs.inc");

$vpn_freeradius_include = stream_resolve_include_path("freeradius.inc");
if ($vpn_freeradius_include !== false) {
	require_once($vpn_freeradius_include);
}
$vpn_openvpn_export_include = stream_resolve_include_path("openvpn-client-export.inc");
if ($vpn_openvpn_export_include !== false) {
	require_once($vpn_openvpn_export_include);
}

/**
 * Escape a value for safe HTML output.
 *
 * LDAP attributes, RADIUS fields, usernames, and status text may contain
 * characters that are meaningful in HTML. Keeping all escaping behind one
 * small helper makes every template output path easy to audit.
 *
 * @param mixed $value Value to render.
 * @return string HTML-safe string.
 */
function vpn_h($value) {
	/* Escape every rendered value because LDAP/RADIUS data is external input. */
	return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

/**
 * Check whether a pfSense package is registered as installed in config.xml.
 *
 * pfSense stores installed package metadata under installedpackages/package.
 * Check name, internal_name, and the pfSense package-name prefix so the portal
 * can report missing dependencies before calling package helper functions.
 *
 * @param string $package_name Package name, e.g. freeradius3.
 * @return bool True when the package is registered as installed.
 */
function vpn_is_package_installed($package_name) {
	foreach (config_get_path("installedpackages/package", array()) as $package) {
		$name = $package["name"] ?? "";
		$internal_name = $package["internal_name"] ?? "";
		if (in_array($package_name, array($name, $internal_name), true) ||
		    in_array("pfSense-pkg-{$package_name}", array($name, $internal_name), true)) {
			return true;
		}
	}

	return false;
}

/**
 * Resolve the LDAP authentication server used by this portal.
 *
 * When a server name is configured, only that exact pfSense authentication
 * server is accepted. Otherwise the first LDAP server in pfSense's
 * authentication server list is used as a conservative default.
 *
 * @param string $authserver_name Optional pfSense authentication server name.
 * @param array  $errors          Output list for user-facing configuration errors.
 * @return array|null pfSense LDAP authentication server configuration.
 */
function vpn_get_ldap_authserver($authserver_name, &$errors) {
	/* Prefer the explicitly configured LDAP server name when set. */
	if (!empty($authserver_name)) {
		$authcfg = auth_get_authserver($authserver_name);
		if (!$authcfg || ($authcfg["type"] !== "ldap")) {
			$errors[] = sprintf(gettext("The configured LDAP authentication server %s was not found."), $authserver_name);
			return null;
		}

		return $authcfg;
	}

	/* Default: use the first LDAP authentication server found in pfSense. */
	foreach (auth_get_authserver_list() as $authcfg) {
		if ($authcfg["type"] === "ldap") {
			return $authcfg;
		}
	}

	$errors[] = gettext("No pfSense LDAP authentication server was found.");
	return null;
}

/**
 * Build an LDAP URI from pfSense authentication server settings.
 *
 * pfSense stores protocol style, host, and port separately. This helper keeps
 * the connection string construction in one place so STARTTLS, LDAP, and LDAPS
 * handling remain consistent with the pfSense LDAP configuration.
 *
 * @param array $authcfg pfSense LDAP authentication server configuration.
 * @return string LDAP connection URI.
 */
function vpn_ldap_uri($authcfg) {
	/* Build the URI exactly like pfSense's LDAP helper functions do. */
	$ldapproto = strstr($authcfg["ldap_urltype"], "SSL") ? "ldaps" : "ldap";
	$ldapserver = "{$ldapproto}://" . ldap_format_host($authcfg["host"]);

	if (!empty($authcfg["ldap_port"])) {
		$ldapserver .= ":" . $authcfg["ldap_port"];
	}

	return $ldapserver;
}

/**
 * Read a small allowlisted set of LDAP attributes for an authenticated user.
 *
 * Authentication is already complete before this function is called. The
 * lookup therefore exists only to fetch metadata needed by the portal, mainly
 * the memberOf group list used for VPN authorization.
 *
 * @param array  $authcfg            pfSense LDAP authentication server configuration.
 * @param string $userdn             Distinguished name returned by LDAP authentication.
 * @param string $username           Login name, used only for logging.
 * @param string $password           User password used only when no LDAP bind account is configured.
 * @param array  $allowed_attributes LDAP attributes that may be queried.
 * @param array  $errors             Output list for user-facing lookup errors.
 * @return array Normalized LDAP attributes keyed by lower-case attribute name.
 */
function vpn_read_ldap_attributes($authcfg, $userdn, $username, $password, $allowed_attributes, &$errors) {
	/*
	 * Read only explicitly allowed LDAP attributes after the user has already
	 * authenticated successfully. This keeps the portal from dumping the full
	 * LDAP entry, which may contain sensitive or noisy operational fields.
	 */
	if (!function_exists("ldap_connect")) {
		$errors[] = gettext("The PHP LDAP extension is not available.");
		return array();
	}

	if (empty($userdn)) {
		$errors[] = gettext("Could not determine the LDAP DN for the authenticated user.");
		return array();
	}

	$ldap = @ldap_connect(vpn_ldap_uri($authcfg));
	if (!$ldap) {
		log_error(sprintf(gettext("vpn.php LDAP error: Could not connect to server %s."), $authcfg["name"]));
		$errors[] = gettext("Could not connect to the LDAP server.");
		return array();
	}

	$ldaptimeout = is_numeric($authcfg["ldap_timeout"]) ? $authcfg["ldap_timeout"] : 5;
	ldap_setup_caenv($ldap, $authcfg);
	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
	ldap_set_option($ldap, LDAP_OPT_DEREF, LDAP_DEREF_SEARCHING);
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, (int)$authcfg["ldap_protver"]);
	ldap_set_option($ldap, LDAP_OPT_TIMELIMIT, (int)$ldaptimeout);
	ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, (int)$ldaptimeout);

	if (strstr($authcfg["ldap_urltype"], "STARTTLS") && !@ldap_start_tls($ldap)) {
		log_error(sprintf(gettext("vpn.php LDAP error: Could not STARTTLS to server %s."), $authcfg["name"]));
		@ldap_unbind($ldap);
		$errors[] = gettext("Could not establish a secure LDAP connection.");
		return array();
	}

	$binddn = $authcfg["ldap_binddn"];
	$bindpw = $authcfg["ldap_bindpw"];

	/*
	 * Prefer the configured LDAP bind account for reads. If pfSense is set up
	 * without one, fall back to binding as the authenticated user.
	 */
	if (!empty($binddn) && !empty($bindpw)) {
		$binddn = isset($authcfg["ldap_utf8"]) ? mb_convert_encoding($binddn, "UTF-8") : $binddn;
		$bindpw = isset($authcfg["ldap_utf8"]) ? mb_convert_encoding($bindpw, "UTF-8") : $bindpw;
		$bound = @ldap_bind($ldap, $binddn, $bindpw);
	} else {
		$userbinddn = isset($authcfg["ldap_utf8"]) ? mb_convert_encoding($userdn, "UTF-8") : $userdn;
		$userbindpw = isset($authcfg["ldap_utf8"]) ? mb_convert_encoding($password, "UTF-8") : $password;
		$bound = @ldap_bind($ldap, $userbinddn, $userbindpw);
	}

	if (!$bound) {
		log_error(sprintf(gettext("vpn.php LDAP error: Could not bind to server %s for user attribute lookup."), $authcfg["name"]));
		@ldap_unbind($ldap);
		$errors[] = gettext("Could not read LDAP user data.");
		return array();
	}

	$ldap_attributes = array_values(array_diff($allowed_attributes, array("dn")));
	$search = @ldap_read($ldap, $userdn, "(objectClass=*)", $ldap_attributes);
	if (!$search) {
		log_error(sprintf(gettext("vpn.php LDAP error: User attribute lookup failed for %s on %s."), $username, $authcfg["name"]));
		@ldap_unbind($ldap);
		$errors[] = gettext("Could not read LDAP user data.");
		return array();
	}

	$entries = @ldap_get_entries($ldap, $search);
	@ldap_unbind($ldap);

	if (!is_array($entries) || ($entries["count"] < 1)) {
		$errors[] = gettext("No LDAP user data was returned.");
		return array();
	}

	/* Normalize the PHP LDAP result shape into a simple attribute => values map. */
	$user_attributes = array("dn" => array($entries[0]["dn"]));
	foreach ($allowed_attributes as $attribute) {
		if ($attribute === "dn") {
			continue;
		}
		$key = strtolower($attribute);
		if (!isset($entries[0][$key]) || !is_array($entries[0][$key])) {
			continue;
		}
		$values = array();
		for ($i = 0; $i < $entries[0][$key]["count"]; $i++) {
			$values[] = $entries[0][$key][$i];
		}
		if (!empty($values)) {
			$user_attributes[$key] = $values;
		}
	}

	return $user_attributes;
}

/**
 * Render a list of messages with the pfSense/Bootstrap alert styling.
 *
 * The function deliberately accepts preselected alert classes only from caller
 * code. Message content is escaped here so callers can pass plain strings.
 *
 * @param array  $messages Messages to render.
 * @param string $class    Bootstrap alert class suffix, such as danger or warning.
 * @return void
 */
function vpn_print_messages($messages, $class) {
	/* Render Bootstrap/pfSense alert boxes from a plain list of messages. */
	if (empty($messages)) {
		return;
	}
	echo "<div class=\"alert alert-{$class}\" role=\"alert\"><ul>";
	foreach ($messages as $message) {
		echo "<li>" . vpn_h($message) . "</li>";
	}
	echo "</ul></div>";
}

/**
 * Decide whether the authenticated user has VPN permission.
 *
 * A blank configured group means every successfully authenticated LDAP user is
 * allowed. Otherwise both pfSense's group resolution and raw LDAP memberOf DNs
 * are checked because deployments differ in how nested/remote groups appear.
 *
 * @param string $group_name      Required VPN group name, or empty to allow all.
 * @param array  $groups          pfSense group names returned by getUserGroups().
 * @param array  $ldap_attributes Normalized LDAP attributes, especially memberof.
 * @return bool True when the user is allowed to continue to RADIUS/VPN setup.
 */
function vpn_user_has_group($group_name, $groups, $ldap_attributes) {
	/*
	 * pfSense's getUserGroups() may not always include every LDAP group.
	 * Check both the pfSense group list and raw LDAP memberOf DNs.
	 */
	if ($group_name === "") {
		return true;
	}

	foreach ($groups as $group) {
		if (strcasecmp($group, $group_name) === 0) {
			return true;
		}
	}

	foreach (($ldap_attributes["memberof"] ?? array()) as $member_of) {
		if (preg_match('/(^|,)CN=' . preg_quote($group_name, '/') . '([,]|$)/i', $member_of)) {
			return true;
		}
	}

	return false;
}

/**
 * Build a human-readable user display name from the LDAP attributes.
 *
 * The portal only needs the name for the page title. Prefer givenName/sn for a
 * clean "Firstname Lastname" label, then fall back to displayName/cn/username
 * so the title remains useful when optional LDAP fields are missing.
 *
 * @param array  $ldap_attributes Normalized LDAP attributes.
 * @param string $username        Login name used as the final fallback.
 * @return string Best available display name.
 */
function vpn_get_user_display_name($ldap_attributes, $username) {
	$firstname = trim($ldap_attributes["givenname"][0] ?? "");
	$lastname = trim($ldap_attributes["sn"][0] ?? "");
	$full_name = trim("{$firstname} {$lastname}");

	if (!empty($full_name)) {
		return $full_name;
	}

	foreach (array("displayname", "cn") as $attribute) {
		$value = trim($ldap_attributes[$attribute][0] ?? "");
		if (!empty($value)) {
			return $value;
		}
	}

	return $username;
}

/**
 * Resolve the RADIUS authentication server used as FreeRADIUS context.
 *
 * The portal does not perform a RADIUS password authentication. The pfSense
 * RADIUS server entry is used as an administrative prerequisite and context
 * before local FreeRADIUS package users are read or provisioned.
 *
 * @param string $authserver_name Optional pfSense authentication server name.
 * @return array|null pfSense RADIUS authentication server configuration.
 */
function vpn_get_radius_authserver($authserver_name) {
	/* Prefer the explicitly configured RADIUS server name when set. */
	if (!empty($authserver_name)) {
		$authcfg = auth_get_authserver($authserver_name);
		if ($authcfg && ($authcfg["type"] === "radius") && !empty($authcfg["radius_auth_port"])) {
			return $authcfg;
		}

		return null;
	}

	/* Default: use the first RADIUS authentication server with an auth port. */
	foreach (auth_get_authserver_list() as $auth_server) {
		if (($auth_server["type"] === "radius") && !empty($auth_server["radius_auth_port"])) {
			return $auth_server;
		}
	}

	return null;
}

/**
 * Find a local FreeRADIUS package user by exact username.
 *
 * Exact matching prevents accidental reuse of a different user when case or
 * aliases differ. This mirrors the requirement that the LDAP login name must
 * match varusersusername exactly.
 *
 * @param string $username LDAP login name.
 * @return array|null Local FreeRADIUS user configuration.
 */
function vpn_get_freeradius_user($username) {
	/* FreeRADIUS package users are stored locally in the pfSense config XML. */
	foreach (config_get_path("installedpackages/freeradius/config", array()) as $user) {
		/* Exact match is intentional: do not collapse case or aliases. */
		if (($user["varusersusername"] ?? null) === $username) {
			return $user;
		}
	}

	return null;
}

/**
 * Check whether the FreeRADIUS package can be managed through its local users file.
 *
 * The portal provisions users only through the pfSense FreeRADIUS package config
 * and freeradius_users_resync(). If the package is missing, or SQL
 * authorization is enabled, provisioning would either fail or write to the
 * wrong backend, so stop before showing OTP/download setup.
 *
 * @param array $errors Output list for user-facing configuration errors.
 * @return bool True when local FreeRADIUS users-file provisioning is supported.
 */
function vpn_validate_freeradius_file_backend(&$errors) {
	if (!vpn_is_package_installed("freeradius3")) {
		$errors[] = gettext("The freeradius3 package is not installed.");
		return false;
	}

	if (!function_exists("freeradius_users_resync")) {
		$errors[] = gettext("The freeradius3 package is not available.");
		return false;
	}

	$sqlconf = config_get_path("installedpackages/freeradiussqlconf/config/0", array());
	$sql_authorization_enabled = (
		(($sqlconf["varsqlconfincludeenable"] ?? "") === "on") &&
		(($sqlconf["varsqlconfenableauthorize"] ?? "") === "on")
	) || (
		(($sqlconf["varsqlconf2includeenable"] ?? "") === "on") &&
		(($sqlconf["varsqlconf2enableauthorize"] ?? "") === "on")
	);

	if ($sql_authorization_enabled) {
		$errors[] = gettext("FreeRADIUS is configured to use SQL authorization. This VPN portal only supports the local FreeRADIUS users file backend.");
		return false;
	}

	return true;
}

/**
 * Check whether the OpenVPN Client Export package is installed and usable.
 *
 * Direct downloads are generated through package helper functions. If the
 * package is missing, the portal can still show the login/2FA flow, but it must
 * not offer broken OpenVPN configuration or installer downloads.
 *
 * @param array $errors Output list for user-facing configuration errors.
 * @return bool True when OpenVPN Client Export helpers are available.
 */
function vpn_validate_openvpn_client_export_package(&$errors) {
	if (!vpn_is_package_installed("openvpn-client-export")) {
		$errors[] = gettext("The openvpn-client-export package is not installed.");
		return false;
	}

	$required_functions = array(
		"openvpn_client_export_config",
		"openvpn_client_export_installer",
		"openvpn_client_export_prefix",
		"get_openvpnserver_by_id"
	);
	foreach ($required_functions as $function_name) {
		if (!function_exists($function_name)) {
			$errors[] = gettext("The openvpn-client-export package is not available.");
			return false;
		}
	}

	return true;
}

/**
 * Generate a Google Authenticator compatible Base32 OTP secret.
 *
 * The FreeRADIUS package expects the TOTP seed in Base32. Ten random bytes are
 * exactly 80 bits; Base32 encoding that data produces the requested 16
 * characters without padding.
 *
 * @return string Sixteen-character Base32 secret using A-Z and 2-7.
 */
function vpn_generate_base32_secret() {
	/* Generate exactly 80 bits and encode them as 16 RFC 4648 Base32 chars. */
	$alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
	$bytes = random_bytes(10);
	$bits = "";
	$secret = "";

	for ($i = 0; $i < strlen($bytes); $i++) {
		$bits .= str_pad(decbin(ord($bytes[$i])), 8, "0", STR_PAD_LEFT);
	}

	for ($i = 0; $i < strlen($bits); $i += 5) {
		$secret .= $alphabet[bindec(substr($bits, $i, 5))];
	}

	return $secret;
}

/**
 * Build a new local FreeRADIUS package user for VPN OTP.
 *
 * The array intentionally includes many GUI fields with empty-string defaults.
 * The FreeRADIUS package resync code expects the same shape produced by the
 * pfSense GUI, so leaving these keys out can cause notices or incomplete files.
 *
 * @param string $username LDAP login name to provision in FreeRADIUS.
 * @param array  $defaults FreeRADIUS user defaults from the portal config.
 * @return array Complete FreeRADIUS package user configuration.
 */
function vpn_build_freeradius_user($username, $defaults) {
	/*
	 * Keep the shape close to the FreeRADIUS package GUI so its resync code can
	 * read every expected key without warnings.
	 */
	return array(
		"sortable" => "",
		"varusersusername" => $username,
		"varuserspassword" => "",
		"varuserspasswordencryption" => $defaults["varuserspasswordencryption"] ?? "Cleartext-Password",
		"varusersmotpenable" => $defaults["varusersmotpenable"] ?? "on",
		"varusersauthmethod" => $defaults["varusersauthmethod"] ?? "googleauth",
		"varusersmotpinitsecret" => vpn_generate_base32_secret(),
		"varusersmotppin" => $defaults["varusersmotppin"],
		"varusersmotpoffset" => $defaults["varusersmotpoffset"] ?? "0",
		"qrcodetext" => "",
		"varuserswisprredirectionurl" => "",
		"varuserssimultaneousconnect" => "",
		"description" => $defaults["description"] ?? "Created by vpn.php",
		"varusersframedipaddress" => "",
		"varusersframedipnetmask" => "",
		"varusersframedroute" => "",
		"varusersframedip6address" => "",
		"varusersframedip6route" => "",
		"varusersvlanid" => "",
		"varusersexpiration" => "",
		"varuserssessiontimeout" => "",
		"varuserslogintime" => "",
		"varusersamountoftime" => "",
		"varuserspointoftime" => $defaults["varuserspointoftime"] ?? "Daily",
		"varusersmaxtotaloctets" => "",
		"varusersmaxtotaloctetstimerange" => $defaults["varusersmaxtotaloctetstimerange"] ?? "daily",
		"varusersmaxbandwidthdown" => "",
		"varusersmaxbandwidthup" => "",
		"varusersacctinteriminterval" => "",
		"varuserstopadditionaloptions" => "",
		"varuserscheckitemsadditionaloptions" => "",
		"varusersreplyitemsadditionaloptions" => ""
	);
}

/**
 * Compare a local hostname/IP candidate with the configured primary node.
 *
 * Comparisons are case-insensitive so short hostnames and FQDN hostnames are
 * treated naturally. Empty candidates never match.
 *
 * @param string $candidate Local hostname, FQDN, or IP address candidate.
 * @param string $primary   Configured primary HA hostname or IP address.
 * @return bool True when both values describe the same configured primary node.
 */
function vpn_host_matches($candidate, $primary) {
	if (empty($candidate) || empty($primary)) {
		return false;
	}

	$candidate = strtolower(trim($candidate));
	$primary = strtolower(trim($primary));
	$candidate_short = explode(".", $candidate)[0];
	$primary_short = explode(".", $primary)[0];

	return (($candidate === $primary) ||
	    (!is_ipaddr($candidate) && !is_ipaddr($primary) && ($candidate_short === $primary_short)));
}

/**
 * Check whether the portal appears to run on the configured primary HA node.
 *
 * The HA warning is intentionally driven by an explicit configured primary
 * hostname/IP. pfSense HA state is deployment-specific, so this helper only
 * compares known local names and addresses against that configured value.
 *
 * @param string $primary Primary HA hostname, FQDN, or IP address; empty disables the warning.
 * @return bool True when the current node matches the configured primary or no primary is configured.
 */
function vpn_is_running_on_primary_ha_node($primary) {
	/* Empty config disables the HA warning entirely. */
	if (empty($primary)) {
		return true;
	}

	if (vpn_host_matches(gethostname(), $primary) ||
	    vpn_host_matches(config_get_path("system/hostname", ""), $primary)) {
		return true;
	}

	$fqdn = config_get_path("system/hostname", "");
	if (!empty(config_get_path("system/domain", ""))) {
		$fqdn .= "." . config_get_path("system/domain", "");
	}
	if (vpn_host_matches($fqdn, $primary) ||
	    vpn_host_matches($_SERVER["SERVER_ADDR"] ?? "", $primary)) {
		return true;
	}

	foreach (config_get_path("interfaces", array()) as $ifcfg) {
		if (vpn_host_matches($ifcfg["ipaddr"] ?? "", $primary)) {
			return true;
		}
	}

	return false;
}

/**
 * Create a local FreeRADIUS package user while holding a pfSense lock.
 *
 * Provisioning updates config.xml and regenerates the FreeRADIUS users text
 * file. The lock prevents two concurrent first logins from writing conflicting
 * user lists, and the user is looked up again inside the lock to avoid
 * duplicate entries.
 *
 * @param string $username LDAP login name to provision.
 * @param array  $config   Portal configuration from the top of this file.
 * @param array  $errors   Output list for user-facing provisioning errors.
 * @return array|null Existing or newly created FreeRADIUS user configuration.
 */
function vpn_create_freeradius_user($username, $config, &$errors) {
	if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
		log_error(sprintf(gettext("vpn.php FreeRADIUS error: Refused to create user %s because the username is invalid for OTP."), $username));
		$errors[] = gettext("The username is not valid for automatic RADIUS OTP provisioning.");
		return null;
	}

	$lock = try_lock("vpn_portal_freeradius", 30);
	if (!$lock) {
		log_error(sprintf(gettext("vpn.php FreeRADIUS error: Timed out waiting for provisioning lock while creating user %s."), $username));
		$errors[] = gettext("RADIUS provisioning is busy. Please try again.");
		return null;
	}

	try {
		$users = config_get_path("installedpackages/freeradius/config", array());
		foreach ($users as $user) {
			if (($user["varusersusername"] ?? null) === $username) {
				return $user;
			}
		}

		$user = vpn_build_freeradius_user($username, $config["freeradius_user_defaults"] ?? array());
		$users[] = $user;
		config_set_path("installedpackages/freeradius/config", $users);

		$write_result = write_config(sprintf(gettext("VPN Portal created FreeRADIUS user %s."), $username));
		if ($write_result === false || $write_result === -1) {
			log_error(sprintf(gettext("vpn.php FreeRADIUS error: Could not write configuration after creating user %s."), $username));
			$errors[] = gettext("Could not save the new RADIUS user.");
			return null;
		}

		freeradius_users_resync();
		return $user;
	} finally {
		unlock($lock);
	}
}

/**
 * Convert a FreeRADIUS field value into printable text.
 *
 * This is kept for diagnostics/admin display paths and preserves full values.
 * It does not redact secrets; sensitivity marking is handled separately.
 *
 * @param mixed $value FreeRADIUS field value.
 * @return string Printable value.
 */
function vpn_format_radius_value($value) {
	/* Make every FreeRADIUS config value renderable without hiding any fields. */
	if (is_array($value)) {
		return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

	if (is_bool($value)) {
		return $value ? "true" : "false";
	}

	return $value;
}

/**
 * Determine whether a FreeRADIUS field name is likely sensitive.
 *
 * The portal currently avoids rendering RADIUS data tables, but this helper
 * remains useful if administrative output is re-enabled later.
 *
 * @param string $field FreeRADIUS field name.
 * @return bool True when the field name indicates secret or private material.
 */
function vpn_radius_field_is_sensitive($field) {
	/*
	 * Flag fields that commonly contain passwords, OTP material, private keys,
	 * shared secrets, PINs, or arbitrary FreeRADIUS option strings.
	 */
	$field_lc = strtolower($field);
	$sensitive_fields = array(
		"varuserscheckitemsadditionaloptions",
		"varusersreplyitemsadditionaloptions",
		"varuserstopadditionaloptions"
	);

	return in_array($field_lc, $sensitive_fields, true) ||
	    preg_match("/(password|secret|pin|motp|otp|privatekey)/i", $field);
}

/**
 * Prepare FreeRADIUS user fields for a possible diagnostic table.
 *
 * The current user-facing portal does not render this array, but the request
 * flow still prepares it so future admin views can mark sensitive fields
 * consistently without guessing at render time.
 *
 * @param array $user Local FreeRADIUS user configuration.
 * @return array Field metadata with printable value and sensitivity flag.
 */
function vpn_prepare_radius_user_data($user) {
	/*
	 * Intentionally include every local FreeRADIUS user field, including private
	 * and secret values. Sensitive fields are marked in the rendered table so
	 * administrators can tell which values require extra care.
	 */
	$prepared = array();
	foreach ($user as $field => $value) {
		$prepared[$field] = array(
			"value" => vpn_format_radius_value($value),
			"sensitive" => vpn_radius_field_is_sensitive($field)
		);
	}

	return $prepared;
}

/**
 * Load OpenVPN Client Export defaults for a selected OpenVPN server.
 *
 * The direct download buttons post back to vpn.php. These defaults mimic the
 * package page's common options so generated exports behave like the
 * administrator-facing export page unless saved package settings override them.
 *
 * @param string $vpnid OpenVPN server ID.
 * @return array Export option names and values.
 */
function vpn_openvpn_default_export_options($vpnid) {
	/* Match the defaults used by the OpenVPN Client Export page where possible. */
	$options = array(
		"useaddr" => "serveraddr",
		"useaddr_hostname" => "",
		"verifyservercn" => "auto",
		"blockoutsidedns" => "0",
		"legacy" => "0",
		"silent" => "0",
		"bindmode" => "nobind",
		"usetoken" => "0",
		"usepkcs11" => "0",
		"pkcs11providers" => "",
		"pkcs11id" => "",
		"p12encryption" => "high",
		"advancedoptions" => ""
	);

	foreach (config_get_path("installedpackages/vpn_openvpn_export/serverconfig/item", array()) as $item) {
		if (($item["server"] ?? null) == $vpnid) {
			foreach ($options as $field => $value) {
				if (isset($item[$field])) {
					$options[$field] = $item[$field];
				}
			}
			break;
		}
	}

	return $options;
}

/**
 * Convert pfSense checkbox-style values to the numeric flags expected by the exporter.
 *
 * The OpenVPN Client Export page converts checkbox state to 1/0 in JavaScript
 * before posting. vpn.php reads saved package defaults directly, so this helper
 * prevents false-like strings such as "no" or "off" from becoming truthy.
 *
 * @param mixed $value Saved option value.
 * @return string "1" for enabled options, otherwise "0".
 */
function vpn_openvpn_export_checkbox_value($value) {
	if (is_bool($value)) {
		return $value ? "1" : "0";
	}

	$value = strtolower(trim((string)$value));
	return in_array($value, array("1", "yes", "true", "on"), true) ? "1" : "0";
}

/**
 * Resolve the OpenVPN remote address option exactly like vpn_openvpn_export.php.
 *
 * The export page lets administrators choose "Other" in the UI, but its
 * JavaScript posts the actual hostname from useaddr_hostname. Since vpn.php
 * posts directly to its own export handler, it must perform that substitution
 * server-side before calling the OpenVPN export helpers.
 *
 * @param array $options Saved OpenVPN Client Export options.
 * @return string Valid OpenVPN export remote address option.
 */
function vpn_openvpn_export_resolve_useaddr($options) {
	$useaddr = trim($options["useaddr"] ?? "serveraddr");

	if ($useaddr === "other") {
		$useaddr = trim($options["useaddr_hostname"] ?? "");
	}

	return $useaddr;
}

/**
 * Check whether a user certificate can be exported for a given OpenVPN server.
 *
 * Server certificates, certificates from the wrong CA, revoked certificates,
 * and keys incompatible with OpenVPN must not be offered as direct user
 * downloads.
 *
 * @param array $cert     Candidate certificate configuration.
 * @param array $server   OpenVPN server configuration.
 * @param array $ecdsagood Certificate refids with compatible private keys.
 * @return bool True when the certificate is suitable for client export.
 */
function vpn_openvpn_cert_is_exportable($cert, $server, $ecdsagood) {
	if (($cert["caref"] != $server["caref"]) ||
	    ($cert["refid"] == $server["certref"]) ||
	    !in_array($cert["refid"], $ecdsagood, true)) {
		return false;
	}

	$purpose = cert_get_purpose($cert["crt"]);
	return ($purpose["server"] != "Yes");
}

/**
 * Find the direct OpenVPN export target for the logged-in user.
 *
 * Auth-only OpenVPN servers can export without a local user/certificate match.
 * Certificate-based servers need an exact local pfSense user and certificate
 * pairing, so this helper returns an unavailable context when the portal cannot
 * identify a safe direct export row.
 *
 * @param string $username LDAP login name.
 * @return array Export context consumed by the download JavaScript.
 */
function vpn_get_openvpn_export_context($username) {
	/*
	 * Build only the one export target needed by this portal. The full
	 * vpn_openvpn_export.php page supports many rows and interactive settings;
	 * this helper chooses the first safe target and stores fixed/default options.
	 */
	$users = config_get_path("system/user", array());
	$certs = config_get_path("cert", array());

	foreach (config_get_path("openvpn/openvpn-server", array()) as $server) {
		if (isset($server["disable"]) || (stripos($server["mode"], "server") === false)) {
			continue;
		}

		$vpnid = $server["vpnid"];
		$context = array(
			"available" => false,
			"message" => "",
			"server_id" => $vpnid,
			"user_id" => "",
			"cert_id" => "",
			"options" => vpn_openvpn_default_export_options($vpnid)
		);

		if ($server["mode"] == "server_user") {
			$context["available"] = true;
			return $context;
		}

		if (($server["mode"] != "server_tls_user") || ($server["authmode"] != "Local Database")) {
			continue;
		}

		$ecdsagood = array();
		foreach ($certs as $cert) {
			if (!empty($cert["prv"]) && cert_check_pkey_compatibility($cert["prv"], "OpenVPN")) {
				$ecdsagood[] = $cert["refid"];
			}
		}

		foreach ($users as $uindex => $user) {
			if (($user["name"] ?? "") !== $username || !is_array($user["cert"] ?? null)) {
				continue;
			}

			foreach ($user["cert"] as $cindex => $cert) {
				if (!is_array($cert)) {
					$cert_entry = lookup_cert($cert);
					$cert = $cert_entry["item"] ?? array();
				}
				if (!is_array($cert) || !vpn_openvpn_cert_is_exportable($cert, $server, $ecdsagood)) {
					continue;
				}
				if (!empty($server["crlref"]) && is_cert_revoked($cert, $server["crlref"])) {
					continue;
				}

				$context["available"] = true;
				$context["user_id"] = (string)$uindex;
				$context["cert_id"] = (string)$cindex;
				return $context;
			}
		}
	}

	return array(
		"available" => false,
		"message" => gettext("No direct OpenVPN export target was found for this user. Please use the pfSense OpenVPN Client Export page."),
		"server_id" => "",
		"user_id" => "",
		"cert_id" => "",
		"options" => vpn_openvpn_default_export_options("")
	);
}

/**
 * Send a simple error response for direct OpenVPN export requests.
 *
 * Download requests happen outside the normal HTML render path. A plain text
 * response keeps failures understandable without accidentally rendering a
 * partial portal page as a downloaded file.
 *
 * @param string $message User-facing error message.
 * @param int    $status  HTTP status code.
 * @return void
 */
function vpn_openvpn_export_error($message, $status = 400) {
	http_response_code($status);
	header("Content-Type: text/plain; charset=utf-8");
	echo $message;
	exit;
}

/**
 * Return the filename and export format for OpenVPN config download actions.
 *
 * This mirrors the filename/format mapping from vpn_openvpn_export.php for the
 * subset of actions exposed by the VPN portal.
 *
 * @param string $act      OpenVPN export action.
 * @param string $exp_name Export prefix from openvpn_client_export_prefix().
 * @return array Filename and export format.
 */
function vpn_openvpn_config_export_target($act, $exp_name) {
	switch ($act) {
		case "confinlinedroid":
			return array($exp_name . "-android-config.ovpn", "inlinedroid");
		case "confinlineconnect":
			return array($exp_name . "-connect-config.ovpn", "inlineconnect");
		case "confinlinevisc":
			return array($exp_name . "-viscosity.ovpn", "inlinevisc");
		case "confinline":
		default:
			return array($exp_name . ".ovpn", "inline");
	}
}

/**
 * Keep downloaded profile names readable while preventing header/path surprises.
 *
 * @param string $value    Filename component to sanitize.
 * @param string $fallback Value used when the sanitized component would be empty.
 * @return string Safe filename component.
 */
function vpn_download_filename_component($value, $fallback) {
	$value = preg_replace("/[^A-Za-z0-9_.-]/", "-", trim((string)$value));
	$value = trim($value, ".-");

	return ($value !== "") ? $value : $fallback;
}

/**
 * Pick the OpenVPN host part used for friendly download filenames.
 *
 * The filename should follow the host configured for OpenVPN exports. When the
 * export option has already resolved to a concrete host/IP value, use it. For
 * symbolic export modes, fall back to the pfSense hostname and domain.
 *
 * @param string $useaddr Resolved OpenVPN export remote address option.
 * @return string Domain/host filename component.
 */
function vpn_download_filename_domain($useaddr) {
	$symbolic_useaddr_values = array("serveraddr", "servermagic", "servermagichost", "serverhostname");
	if (!in_array($useaddr, $symbolic_useaddr_values, true) &&
	    (is_hostname($useaddr) || is_ipaddr($useaddr))) {
		return vpn_download_filename_component($useaddr, "vpn");
	}

	$system_hostname = trim(config_get_path("system/hostname", ""));
	$system_domain = trim(config_get_path("system/domain", ""));
	$fallback_domain = trim($system_hostname . (($system_hostname && $system_domain) ? "." : "") . $system_domain);

	return vpn_download_filename_component($fallback_domain, "vpn");
}

/**
 * Build the user-facing base name used by imported VPN clients as profile name.
 *
 * @param string $username Login username.
 * @param string $domain   VPN profile domain/host.
 * @return string Friendly filename base, e.g. username@vpn.example.com.
 */
function vpn_openvpn_download_name_base($username, $domain) {
	$safe_username = vpn_download_filename_component($username, "user");
	$safe_domain = vpn_download_filename_component($domain, "vpn");

	return "{$safe_username}@{$safe_domain}";
}

/**
 * Check whether a configured download button is enabled.
 *
 * Missing keys default to false so newly added buttons do not appear unless the
 * administrator explicitly enables them in the portal configuration.
 *
 * @param array  $config Portal configuration.
 * @param string $key    Download button config key.
 * @return bool True when the configured button should be available.
 */
function vpn_download_button_enabled($config, $key) {
	return !empty($config["download_buttons"][$key]);
}

/**
 * Return OpenVPN export actions enabled by the configured download buttons.
 *
 * The same mapping is used for the UI and the direct POST guard so hidden
 * buttons cannot be triggered by manually posting export_act values.
 *
 * @param array $config Portal configuration.
 * @return array Enabled OpenVPN export action names.
 */
function vpn_get_enabled_openvpn_export_actions($config) {
	$action_map = array(
		"config_inline_most_clients" => "confinline",
		"config_inline_android" => "confinlinedroid",
		"config_inline_connect" => "confinlineconnect",
		"exe_windows_64" => "inst-x64-current",
		"exe_windows_32" => "inst-x86-current",
		"legacy_windows_10" => "inst-Win10",
		"legacy_windows_7" => "inst-Win7",
		"viscosity_bundle" => "visc",
		"viscosity_inline" => "confinlinevisc"
	);
	$actions = array();

	foreach ($action_map as $button_key => $action) {
		if (vpn_download_button_enabled($config, $button_key)) {
			$actions[] = $action;
		}
	}

	return $actions;
}

/**
 * Stream one OpenVPN export generated from the logged-in portal session.
 *
 * The normal vpn_openvpn_export.php page requires a pfSense GUI session because
 * it includes guiconfig.inc. The portal cannot rely on that, so it stores a
 * constrained export context after LDAP/RADIUS success and calls the package
 * export functions directly for the allowed button actions.
 *
 * @param array $config Portal configuration.
 * @return void
 */
function vpn_handle_openvpn_download_request($config) {
	global $current_openvpn_version, $current_openvpn_version_rev;
	global $legacy_openvpn_version, $legacy_openvpn_version_rev;
	global $legacy_incompatible_ciphers, $p12_encryption_levels;

	phpsession_begin();

	$csrf_token = $_POST["csrf_token"] ?? "";
	if (empty($_SESSION["vpn_csrf_token"]) ||
	    !hash_equals($_SESSION["vpn_csrf_token"], $csrf_token)) {
		phpsession_end(true);
		vpn_openvpn_export_error(gettext("Invalid form token. Please reload this page and try again."), 403);
	}

	if (empty($_SESSION["vpn_has_vpn_access"]) ||
	    empty($_SESSION["vpn_openvpn_export"]["available"])) {
		phpsession_end(true);
		vpn_openvpn_export_error(gettext("No direct OpenVPN export target was found for this user."), 403);
	}

	$package_errors = array();
	if (!vpn_validate_openvpn_client_export_package($package_errors)) {
		phpsession_end(true);
		vpn_openvpn_export_error($package_errors[0], 500);
	}

	$act = $_POST["export_act"] ?? "";
	$allowed_actions = vpn_get_enabled_openvpn_export_actions($config);
	if (!in_array($act, $allowed_actions, true)) {
		phpsession_end(true);
		vpn_openvpn_export_error(gettext("Invalid OpenVPN export action."), 400);
	}

	$context = $_SESSION["vpn_openvpn_export"];
	$options = $context["options"] ?? array();
	$srvid = $context["server_id"] ?? "";
	$usrid = $context["user_id"] ?? "";
	$crtid = $context["cert_id"] ?? "";
	$srvcfg = get_openvpnserver_by_id($srvid);

	if (!$srvcfg) {
		phpsession_end(true);
		vpn_openvpn_export_error(gettext("The configured OpenVPN server was not found."), 400);
	}
	if (($srvcfg["mode"] != "server_user") && (($usrid === "") || ($crtid === ""))) {
		phpsession_end(true);
		vpn_openvpn_export_error(gettext("No matching OpenVPN user certificate was found."), 400);
	}

	$useaddr = vpn_openvpn_export_resolve_useaddr($options);
	if (!(is_ipaddr($useaddr) || is_hostname($useaddr) ||
	    in_array($useaddr, array("serveraddr", "servermagic", "servermagichost", "serverhostname"), true))) {
		phpsession_end(true);
		vpn_openvpn_export_error(gettext("An IP address or hostname must be specified."), 400);
	}

	$verifyservercn = $options["verifyservercn"] ?? "auto";
	$blockoutsidedns = vpn_openvpn_export_checkbox_value($options["blockoutsidedns"] ?? "0");
	$legacy = vpn_openvpn_export_checkbox_value($options["legacy"] ?? "0");
	$silent = vpn_openvpn_export_checkbox_value($options["silent"] ?? "0");
	$bindmode = $options["bindmode"] ?? "nobind";
	$usetoken = vpn_openvpn_export_checkbox_value($options["usetoken"] ?? "0");
	$usepkcs11 = vpn_openvpn_export_checkbox_value($options["usepkcs11"] ?? "0");
	$pkcs11providers = $options["pkcs11providers"] ?? "";
	$pkcs11id = $options["pkcs11id"] ?? "";
	$p12encryption = (isset($options["p12encryption"]) &&
	    array_key_exists($options["p12encryption"], $p12_encryption_levels)) ? $options["p12encryption"] : "high";
	$advancedoptions = $options["advancedoptions"] ?? "";
	$password = "";
	$proxy = "";
	$nokeys = ($srvcfg["mode"] == "server_user");

	if ($usetoken && (substr($act, 0, 10) == "confinline")) {
		phpsession_end(true);
		vpn_openvpn_export_error(gettext("Microsoft Certificate Storage cannot be used with an Inline configuration."), 400);
	}
	if ($usepkcs11 && empty($pkcs11providers)) {
		phpsession_end(true);
		vpn_openvpn_export_error(gettext("You must provide the PKCS#11 providers."), 400);
	}
	if ($usepkcs11 && empty($pkcs11id)) {
		phpsession_end(true);
		vpn_openvpn_export_error(gettext("You must provide the PKCS#11 ID."), 400);
	}

	if (($srvcfg["mode"] == "server_tls_user") && ($srvcfg["authmode"] == "Local Database")) {
		$user = config_get_path("system/user/{$usrid}", array());
		if (!isset($user["cert"][$crtid])) {
			phpsession_end(true);
			vpn_openvpn_export_error(gettext("Invalid user/certificate index value."), 400);
		}
		$cert_entry = lookup_cert($user["cert"][$crtid]);
		$cert = $cert_entry["item"] ?? array();
		if (empty($cert) || (!$usepkcs11 && !$usetoken && empty($cert["prv"]))) {
			phpsession_end(true);
			vpn_openvpn_export_error(gettext("A private key cannot be empty if PKCS#11 or Microsoft Certificate Storage is not used."), 400);
		}
	}

	$exp_name = openvpn_client_export_prefix($srvid, $usrid, $crtid);
	$download_name_base = vpn_openvpn_download_name_base(
		$_SESSION["vpn_username"] ?? $exp_name,
		vpn_download_filename_domain($useaddr)
	);
	$exp_path = false;

	if (substr($act, 0, 4) == "conf") {
		list($download_name, $expformat) = vpn_openvpn_config_export_target($act, $download_name_base);
		$exp_path = openvpn_client_export_config($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $blockoutsidedns, $legacy, $bindmode, $usetoken, $nokeys, $proxy, $expformat, $password, $p12encryption, false, false, $advancedoptions, $usepkcs11, $pkcs11providers, $pkcs11id);
	} elseif ($act == "visc") {
		$download_name = $download_name_base . "-Viscosity.visc.zip";
		$exp_path = viscosity_openvpn_client_config_exporter($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $blockoutsidedns, $legacy, $bindmode, $usetoken, $password, $p12encryption, $proxy, $advancedoptions, $usepkcs11, $pkcs11providers, $pkcs11id);
	} elseif (substr($act, 0, 4) == "inst") {
		$openvpn_version = substr($act, 5);
		$download_name = "openvpn-{$download_name_base}-install-";
		switch ($openvpn_version) {
			case "Win7":
				$legacy = true;
				$download_name .= "{$legacy_openvpn_version}-I{$legacy_openvpn_version_rev}-Win7.exe";
				break;
			case "Win10":
				$legacy = true;
				$download_name .= "{$legacy_openvpn_version}-I{$legacy_openvpn_version_rev}-Win10.exe";
				break;
			case "x86-current":
				$download_name .= "{$current_openvpn_version}-I{$current_openvpn_version_rev}-x86.exe";
				break;
			case "x64-current":
			default:
				$download_name .= "{$current_openvpn_version}-I{$current_openvpn_version_rev}-amd64.exe";
				break;
		}
		$exp_path = openvpn_client_export_installer($srvid, $usrid, $crtid, $useaddr, $verifyservercn, $blockoutsidedns, $legacy, $bindmode, $usetoken, $password, $p12encryption, $proxy, $advancedoptions, $openvpn_version, $usepkcs11, $pkcs11providers, $pkcs11id, $silent);
	}

	if ($legacy) {
		$settings = get_openvpnserver_by_id($srvid);
		if (in_array($settings["data_ciphers_fallback"], $legacy_incompatible_ciphers)) {
			phpsession_end(true);
			vpn_openvpn_export_error(gettext("The Fallback Data Encryption Algorithm for the selected server is not compatible with Legacy clients."), 400);
		}
	}

	if (!$exp_path) {
		phpsession_end(true);
		vpn_openvpn_export_error(gettext("Failed to export config files."), 500);
	}

	phpsession_end(true);

	if (($act == "conf") || (substr($act, 0, 10) == "confinline")) {
		$exp_size = strlen($exp_path);
	} else {
		$exp_size = filesize($exp_path);
	}

	header("Pragma: ");
	header("Cache-Control: ");
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=\"{$download_name}\"");
	header("Content-Length: {$exp_size}");
	if (($act == "conf") || (substr($act, 0, 10) == "confinline")) {
		echo $exp_path;
	} else {
		readfile($exp_path);
		@unlink($exp_path);
	}
	exit;
}

/**
 * Create the initial view model used by both POST handling and the template.
 *
 * Keeping every template variable in one array makes it clear which values may
 * be rendered later and avoids accidental reliance on scattered globals.
 *
 * @return array Default portal state.
 */
function vpn_default_view() {
	/* Keep all template state in one array instead of many global variables. */
	return array(
		"errors" => array(),
		"notices" => array(),
		"warnings" => array(),
		"ldap_attributes" => array(),
		"groups" => array(),
		"radius_user" => null,
		"radius_user_data" => array(),
		"radius_status" => "",
		"radius_otp_secret" => "",
		"radius_auth_method" => "",
		"radius_user_created" => false,
		"openvpn_export" => array(),
		"username" => "",
		"display_name" => "",
		"authenticated" => false,
		"has_vpn_access" => false,
		"portal_blocked" => false,
		"csrf_token" => ""
	);
}

/**
 * Handle one portal request and return all data needed by the template.
 *
 * This function owns session setup, CSRF validation, LDAP authentication,
 * VPN authorization, local FreeRADIUS provisioning, HA warning creation, and
 * OpenVPN export context discovery. Rendering remains outside the function so
 * state changes and HTML stay separate.
 *
 * @param array $config Portal configuration from the top of this file.
 * @return array View model for the login or authenticated portal page.
 */
function vpn_handle_request($config) {
	$view = vpn_default_view();

	/*
	 * The portal has its own CSRF token because it intentionally does not load
	 * guiconfig.inc; loading guiconfig.inc would force a pfSense admin session.
	 */
	phpsession_begin();

	if (empty($_SESSION["vpn_csrf_token"])) {
		$_SESSION["vpn_csrf_token"] = bin2hex(random_bytes(32));
	}

	$authcfg = vpn_get_ldap_authserver($config["ldap_authserver_name"], $view["errors"]);

	if ($_SERVER["REQUEST_METHOD"] === "POST") {
		/* Normalize once before the value is used for LDAP, FreeRADIUS, session, and OpenVPN export. */
		$view["username"] = trim($_POST["username"] ?? "");
		if (!empty($config["lowercase_usernames"])) {
			$view["username"] = strtolower($view["username"]);
		}
		$password = $_POST["password"] ?? "";
		$csrf_token = $_POST["csrf_token"] ?? "";

		if (!hash_equals($_SESSION["vpn_csrf_token"], $csrf_token)) {
			$view["errors"][] = gettext("Invalid form token. Please try again.");
		} elseif (empty($view["username"]) || empty($password)) {
			$view["errors"][] = gettext("A username and password must be specified.");
		} elseif ($authcfg) {
			/*
			 * LDAP remains the primary login source. RADIUS/FreeRADIUS is checked
			 * only after LDAP authentication succeeds.
			 */
			$attributes = array("nas_identifier" => "vpn-php-" . gethostname());
			$remoteauth = authenticate_user($view["username"], $password, $authcfg, $attributes);

			if ($remoteauth === true) {
				$view["authenticated"] = true;
				$userdn = $_SESSION["ldapdn"] ?? "";

				$view["ldap_attributes"] = vpn_read_ldap_attributes(
					$authcfg,
					$userdn,
					$view["username"],
					$password,
					array("memberof", "givenname", "sn", "displayname", "cn"),
					$view["errors"]
				);
				$view["display_name"] = vpn_get_user_display_name($view["ldap_attributes"], $view["username"]);
				$view["groups"] = getUserGroups($view["username"], $authcfg, $attributes);
				$view["has_vpn_access"] = vpn_user_has_group(
					$config["vpn_group_name"],
					$view["groups"],
					$view["ldap_attributes"]
				);

				if ($view["has_vpn_access"]) {
					/*
					 * RADIUS presence is checked against the local FreeRADIUS package
					 * configuration because these users may not have RADIUS passwords.
					 */
					if (vpn_validate_freeradius_file_backend($view["errors"])) {
						$radius_authcfg = vpn_get_radius_authserver($config["radius_authserver_name"]);
					} else {
						$view["portal_blocked"] = true;
						$radius_authcfg = null;
					}
					if ($radius_authcfg) {
						$view["radius_user"] = vpn_get_freeradius_user($view["username"]);
						if ($view["radius_user"]) {
							$view["radius_status"] = gettext("The user exists in RADIUS.");
						} else {
							$view["radius_user"] = vpn_create_freeradius_user($view["username"], $config, $view["errors"]);
							if ($view["radius_user"]) {
								$view["radius_status"] = gettext("The user was created in RADIUS.");
								$view["radius_user_created"] = true;
								if (!vpn_is_running_on_primary_ha_node($config["primary_ha_hostname_or_ip"])) {
									$view["warnings"][] = gettext("The primary firewall appears to be unavailable. This VPN-user was created temporarily on the backup-node! Please repeat the setup when the primary firewall is back online.");
								}
							}
						}

						if ($view["radius_user"]) {
							$view["radius_user_data"] = vpn_prepare_radius_user_data($view["radius_user"]);
							$view["radius_otp_secret"] = $view["radius_user"]["varusersmotpinitsecret"] ?? "";
							$view["radius_auth_method"] = $view["radius_user"]["varusersauthmethod"] ?? "";
							if (empty($view["radius_otp_secret"])) {
								$view["errors"][] = gettext("The RADIUS user does not have an OTP secret.");
							} else {
								if (vpn_validate_openvpn_client_export_package($view["errors"])) {
									$view["openvpn_export"] = vpn_get_openvpn_export_context($view["username"]);
								} else {
									$view["portal_blocked"] = true;
								}
							}
						}
					} else {
						if (empty($view["errors"])) {
							$view["radius_status"] = gettext("No pfSense RADIUS authentication server was found.");
						}
					}
				}
			} else {
				$view["errors"][] = gettext("Authentication failed.");
			}
		}

		/* Rotate the form token after every POST attempt. */
		$_SESSION["vpn_csrf_token"] = bin2hex(random_bytes(32));
	}

	if ($view["authenticated"] && $view["has_vpn_access"] && ($view["openvpn_export"]["available"] ?? false)) {
		$_SESSION["vpn_username"] = $view["username"];
		$_SESSION["vpn_has_vpn_access"] = true;
		$_SESSION["vpn_openvpn_export"] = $view["openvpn_export"];
	} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && empty($_POST["export_act"])) {
		unset($_SESSION["vpn_username"]);
		unset($_SESSION["vpn_has_vpn_access"]);
		unset($_SESSION["vpn_openvpn_export"]);
	}

	$view["csrf_token"] = $_SESSION["vpn_csrf_token"];
	phpsession_end(true);

	return $view;
}
