<?php
/*
 * squid_js.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Rubicon Communications, LLC (Netgate)
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

/*
 * Squid javascript helpers for GUI fields status manipulation
 */
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
	if ($transparent_proxy and preg_match("/(local|ldap|radius|msnt|ntlm|adk)/", $auth_method)) {
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
	document.iform.adk_domain.disabled = 1;
	document.iform.adk_user.disabled = 1;
	document.iform.adk_secret.disabled = 1;
	document.iform.adk_version.disabled = 1;
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
		document.iform.adk_domain.disabled = 1;
		document.iform.adk_user.disabled = 1;
		document.iform.adk_secret.disabled = 1;
		document.iform.adk_version.disabled = 1;
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
		document.iform.adk_domain.disabled = 1;
		document.iform.adk_user.disabled = 1;
		document.iform.adk_secret.disabled = 1;
		document.iform.adk_version.disabled = 1;
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
		document.iform.adk_domain.disabled = 1;
		document.iform.adk_user.disabled = 1;
		document.iform.adk_secret.disabled = 1;
		document.iform.adk_version.disabled = 1;
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
		document.iform.adk_domain.disabled = 1;
		document.iform.adk_user.disabled = 1;
		document.iform.adk_secret.disabled = 1;
		document.iform.adk_version.disabled = 1;
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
		document.iform.adk_domain.disabled = 1;
		document.iform.adk_user.disabled = 1;
		document.iform.adk_secret.disabled = 1;
		document.iform.adk_version.disabled = 1;
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
		document.iform.adk_domain.disabled = 1;
		document.iform.adk_user.disabled = 1;
		document.iform.adk_secret.disabled = 1;
		document.iform.adk_version.disabled = 1;
		break;
	case 'adk':
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
		document.iform.adk_domain.disabled = 0;
		document.iform.adk_user.disabled = 0;
		document.iform.adk_secret.disabled = 0;
		document.iform.adk_version.disabled = 0;
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
		document.iform['clamav_disable_stream_scanning'].disabled = 0;
		document.iform['clamav_update'].disabled = 0;
		document.iform['clamav_dbregion'].disabled = 0;
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
		document.iform['clamav_disable_stream_scanning'].disabled = 1;
		document.getElementById('clamav_disable_stream_scanning').checked = 0;
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
