<?php
/*
	squid_js.inc
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2015 ESF, LLC
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
/*
 * Squid javascript helpers for GUI fields status manipulation
 */
require_once('globals.inc');
require_once('config.inc');

/*
 * Enable/disable and blank out various parts of the Authentication tab
 * depending on selected 'Authentication Method' value
 */
function squid_print_javascript_auth() {
	global $config;
	if (is_array($config['installedpackages']['squid'])) {
		$squidsettings = $config['installedpackages']['squid']['config'][0];
	} else {
		$squidsettings = array();
	}
	if (is_array($config['installedpackages']['squidauth']['config'])) {
		$settingsauth = $config['installedpackages']['squidauth']['config'][0];
	} else {
		$settingsauth = array();
	}
	$transparent_proxy = ($squidsettings['transparent_proxy'] == 'on');
	$auth_method = $settingsauth['auth_method'];

	// No authentication for transparent proxy
	if ($transparent_proxy and preg_match("/(local|ldap|radius|msnt|ntlm)/", $auth_method)) {
		$javascript = <<< EOD
<script type="text/javascript">
//<![CDATA[
function on_auth_method_changed() {
	document.iform.auth_method.disabled = 1;
	document.iform.auth_server.disabled = 1;
	document.iform.auth_ntdomain.disabled = 1;
	document.iform.auth_server_port.disabled = 1;
	document.iform.ldap_user.disabled = 1;
	document.iform.ldap_version.disabled = 1;
	document.iform.ldap_userattribute.disabled = 1;
	document.iform.ldap_filter.disabled = 1;
	document.iform.ldap_pass.disabled = 1;
	document.iform.ldap_basedomain.disabled = 1;
	document.iform.radius_secret.disabled = 1;
	document.iform.msnt_secondary.disabled = 1;
	document.iform.auth_prompt.disabled = 1;
	document.iform.auth_processes.disabled = 1;
	document.iform.auth_ttl.disabled = 1;
	document.iform.unrestricted_auth.disabled = 1;
	document.iform.no_auth_hosts.disabled = 1;
}
//]]>
</script>

EOD;

	} else {

		$javascript = <<< EOD
<script type="text/javascript">
//<![CDATA[
function on_auth_method_changed() {
	var field = document.iform.auth_method;
	var auth_method = field.options[field.selectedIndex].value;

	if (auth_method == 'none') {
		document.iform.auth_server.disabled = 1;
		document.iform.auth_server_port.disabled = 1;
		document.iform.auth_ntdomain.disabled = 1;
		document.iform.ldap_user.disabled = 1;
		document.iform.ldap_version.disabled = 1;
		document.iform.ldap_userattribute.disabled = 1;
		document.iform.ldap_filter.disabled = 1;
		document.iform.ldap_pass.disabled = 1;
		document.iform.ldap_basedomain.disabled = 1;
		document.iform.radius_secret.disabled = 1;
		document.iform.msnt_secondary.disabled = 1;
		document.iform.auth_prompt.disabled = 1;
		document.iform.auth_processes.disabled = 1;
		document.iform.auth_ttl.disabled = 1;
		document.iform.unrestricted_auth.disabled = 1;
		document.iform.no_auth_hosts.disabled = 1;
	} else {
		document.iform.auth_prompt.disabled = 0;
		document.iform.auth_processes.disabled = 0;
		document.iform.auth_ttl.disabled = 0;
		document.iform.unrestricted_auth.disabled = 0;
		document.iform.no_auth_hosts.disabled = 0;
	}

	switch (auth_method) {
	case 'local':
		document.iform.auth_server.disabled = 1;
		document.iform.auth_server_port.disabled = 1;
		document.iform.auth_ntdomain.disabled = 1;
		document.iform.ldap_user.disabled = 1;
		document.iform.ldap_pass.disabled = 1;
		document.iform.ldap_version.disabled = 1;
		document.iform.ldap_userattribute.disabled = 1;
		document.iform.ldap_filter.disabled = 1;
		document.iform.ldap_basedomain.disabled = 1;
		document.iform.radius_secret.disabled = 1;
		document.iform.msnt_secondary.disabled = 1;
		break;
	case 'ldap':
		document.iform.auth_server.disabled = 0;
		document.iform.auth_server_port.disabled = 0;
		document.iform.ldap_user.disabled = 0;
		document.iform.ldap_pass.disabled = 0;
		document.iform.ldap_version.disabled = 0;
		document.iform.ldap_userattribute.disabled = 0;
		document.iform.ldap_filter.disabled = 0;
		document.iform.ldap_basedomain.disabled = 0;
		document.iform.radius_secret.disabled = 1;
		document.iform.msnt_secondary.disabled = 1;
		document.iform.auth_ntdomain.disabled = 1;
		break;
	case 'radius':
		document.iform.auth_server.disabled = 0;
		document.iform.auth_server_port.disabled = 0;
		document.iform.ldap_user.disabled = 1;
		document.iform.ldap_pass.disabled = 1;
		document.iform.ldap_version.disabled = 1;
		document.iform.ldap_userattribute.disabled = 1;
		document.iform.ldap_filter.disabled = 1;
		document.iform.ldap_basedomain.disabled = 1;
		document.iform.radius_secret.disabled = 0;
		document.iform.msnt_secondary.disabled = 1;
		document.iform.auth_ntdomain.disabled = 1;
		break;
	case 'msnt':
		document.iform.auth_server.disabled = 0;
		document.iform.auth_server_port.disabled = 1;
		document.iform.auth_ntdomain.disabled = 0;
		document.iform.ldap_user.disabled = 1;
		document.iform.ldap_pass.disabled = 1;
		document.iform.ldap_version.disabled = 1;
		document.iform.ldap_userattribute.disabled = 1;
		document.iform.ldap_filter.disabled = 1;
		document.iform.ldap_basedomain.disabled = 1;
		document.iform.radius_secret.disabled = 1;
		document.iform.msnt_secondary.disabled = 0;
		break;
	case 'cp':
		document.iform.auth_server.disabled = 1;
		document.iform.auth_server_port.disabled = 1;
		document.iform.auth_ntdomain.disabled = 1;
		document.iform.ldap_user.disabled = 1;
		document.iform.ldap_version.disabled = 1;
		document.iform.ldap_userattribute.disabled = 1;
		document.iform.ldap_filter.disabled = 1;
		document.iform.ldap_pass.disabled = 1;
		document.iform.ldap_basedomain.disabled = 1;
		document.iform.radius_secret.disabled = 1;
		document.iform.msnt_secondary.disabled = 1;
		document.iform.auth_prompt.disabled = 1;
		document.iform.auth_processes.disabled = 0;
		document.iform.auth_ttl.disabled = 0;
		document.iform.unrestricted_auth.disabled = 1;
		document.iform.no_auth_hosts.disabled = 1;
		break;
	}
}
//]]>
</script>

EOD;

	}
	print($javascript);
}

/* onchange toggle helper for squid_print_javascript_auth() function */
function squid_print_javascript_auth2() {
	print("<script type=\"text/javascript\">on_auth_method_changed()</script>\n");
}

/*
 * Enable/disable and blank out various parts of the Antivirus tab
 * depending on selected 'Enable Manual Configuration' value
 */
function squid_print_antivirus_advanced_config() {
	$javascript = <<< EOD
<script type="text/javascript">
//<![CDATA[
function on_antivirus_advanced_config_changed() {
	var field = document.iform.enable_advanced;
	var enable_advanced = field.options[field.selectedIndex].value;

	if (enable_advanced === 'disabled') {
		document.iform['clamav_url'].disabled = 0;
		document.iform['clamav_safebrowsing'].disabled = 0;
		document.iform['clamav_update'].disabled = 0;
		document.iform['clamav_dbregion'].disabled = 0;
		document.iform['clamav_dbservers'].disabled = 0;
		document.iform['clamav_dbservers'].disabled = 0;
		document.iform['clamav_dbservers'].disabled = 0;
		document.getElementById("load_advanced").disabled = 1;
		document.iform['raw_squidclamav_conf'].disabled = 1;
		document.iform['raw_squidclamav_conf'].value = '';
		document.iform['raw_cicap_conf'].disabled = 1;
		document.iform['raw_cicap_conf'].value = '';
		document.iform['raw_cicap_magic'].disabled = 1;
		document.iform['raw_cicap_magic'].value = '';
		document.iform['raw_freshclam_conf'].disabled = 1;
		document.iform['raw_freshclam_conf'].value = '';
		document.iform['raw_clamd_conf'].disabled = 1;
		document.iform['raw_clamd_conf'].value = '';
	} else {
		document.iform['clamav_url'].disabled = 1;
		document.iform['clamav_safebrowsing'].disabled = 1;
		document.getElementById('clamav_safebrowsing').checked = 0;
		document.iform['clamav_update'].disabled = 0;
		document.iform['clamav_dbregion'].disabled = 1;
		document.getElementById("clamav_dbregion").value = '';
		document.iform['clamav_dbservers'].disabled = 1;
		document.iform['clamav_dbservers'].value = '';
		document.getElementById("load_advanced").disabled = 0;
		document.iform['raw_squidclamav_conf'].disabled = 0;
		document.iform['raw_cicap_conf'].disabled = 0;
		document.iform['raw_cicap_magic'].disabled = 0;
		document.iform['raw_freshclam_conf'].disabled = 0;
		document.iform['raw_clamd_conf'].disabled = 0;

	}

	if (document.getElementById("enable").checked == 0) {
		document.getElementById("update_av").disabled = 1;
	} else {
		document.getElementById("update_av").disabled = 0;
	}
}
//]]>
</script>

EOD;
	print($javascript);

}

/* onchange toggle helper for squid_print_antivirus_advanced_config() function */
function squid_print_antivirus_advanced_config2() {
	print("<script type=\"text/javascript\">on_antivirus_advanced_config_changed()</script>\n");
}

?>
