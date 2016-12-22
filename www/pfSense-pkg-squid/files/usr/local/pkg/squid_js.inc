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
	if ($transparent_proxy and preg_match("/(local|ldap|radius|ntlm)/", $auth_method)) {
		$javascript = <<< EOD
<script type="text/javascript">
//<![CDATA[
function on_auth_method_changed() {
	$('#auth_method').prop("disabled", true);
	$('#auth_server').prop("disabled", true);
	$('#auth_server_port').prop("disabled", true);
	$('#ldap_user').prop("disabled", true);
	$('#ldap_version').prop("disabled", true);
	$('#ldap_userattribute').prop("disabled", true);
	$('#ldap_filter').prop("disabled", true);
	$('#ldap_pass').prop("disabled", true);
	$('#ldap_basedomain').prop("disabled", true);
	$('#radius_secret').prop("disabled", true);
	$('#auth_prompt').prop("disabled", true);
	$('#auth_processes').prop("disabled", true);
	$('#auth_ttl').prop("disabled", true);
	$('#unrestricted_auth').prop("disabled", true);
	$('#no_auth_hosts').prop("disabled", true);
}
//]]>
</script>

EOD;

	} else {

		$javascript = <<< EOD
<script type="text/javascript">
//<![CDATA[
function on_auth_method_changed() {
	auth_method = $('#auth_method').val();

	if (auth_method == 'none') {
		$('#auth_server').prop("disabled", true);
		$('#auth_server_port').prop("disabled", true);
		$('#ldap_user').prop("disabled", true);
		$('#ldap_version').prop("disabled", true);
		$('#ldap_userattribute').prop("disabled", true);
		$('#ldap_filter').prop("disabled", true);
		$('#ldap_pass').prop("disabled", true);
		$('#ldap_basedomain').prop("disabled", true);
		$('#radius_secret').prop("disabled", true);
		$('#auth_prompt').prop("disabled", true);
		$('#auth_processes').prop("disabled", true);
		$('#auth_ttl').prop("disabled", true);
		$('#unrestricted_auth').prop("disabled", true);
		$('#no_auth_hosts').prop("disabled", true);
	} else {
		$('#auth_prompt').prop("disabled", false);
		$('#auth_processes').prop("disabled", false);
		$('#auth_ttl').prop("disabled", false);
		$('#unrestricted_auth').prop("disabled", false);
		$('#no_auth_hosts').prop("disabled", false);
	}

	switch (auth_method) {
	case 'local':
		$('#auth_server').prop("disabled", true);
		$('#auth_server_port').prop("disabled", true);
		$('#ldap_user').prop("disabled", true);
		$('#ldap_pass').prop("disabled", true);
		$('#ldap_version').prop("disabled", true);
		$('#ldap_userattribute').prop("disabled", true);
		$('#ldap_filter').prop("disabled", true);
		$('#ldap_basedomain').prop("disabled", true);
		$('#radius_secret').prop("disabled", true);
		break;
	case 'ldap':
		$('#auth_server').prop("disabled", false);
		$('#auth_server_port').prop("disabled", false);
		$('#ldap_user').prop("disabled", false);
		$('#ldap_pass').prop("disabled", false);
		$('#ldap_version').prop("disabled", false);
		$('#ldap_userattribute').prop("disabled", false);
		$('#ldap_filter').prop("disabled", false);
		$('#ldap_basedomain').prop("disabled", false);
		$('#radius_secret').prop("disabled", true);
		break;
	case 'radius':
		$('#auth_server').prop("disabled", false);
		$('#auth_server_port').prop("disabled", false);
		$('#ldap_user').prop("disabled", true);
		$('#ldap_pass').prop("disabled", true);
		$('#ldap_version').prop("disabled", true);
		$('#ldap_userattribute').prop("disabled", true);
		$('#ldap_filter').prop("disabled", true);
		$('#ldap_basedomain').prop("disabled", true);
		$('#radius_secret').prop("disabled", false);
		break;
	case 'cp':
		$('#auth_server').prop("disabled", true);
		$('#auth_server_port').prop("disabled", true);
		$('#ldap_user').prop("disabled", true);
		$('#ldap_version').prop("disabled", true);
		$('#ldap_userattribute').prop("disabled", true);
		$('#ldap_filter').prop("disabled", true);
		$('#ldap_pass').prop("disabled", true);
		$('#ldap_basedomain').prop("disabled", true);
		$('#radius_secret').prop("disabled", true);
		$('#auth_prompt').prop("disabled", true);
		$('#auth_processes').prop("disabled", false);
		$('#auth_ttl').prop("disabled", false);
		$('#unrestricted_auth').prop("disabled", true);
		$('#no_auth_hosts').prop("disabled", true);
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
	print("<script type=\"text/javascript\">events.push(function () {on_auth_method_changed();});</script>\n");
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
	var enable_advanced = $('#enable_advanced').val();

	if (enable_advanced === 'disabled') {
		$('#clamav_url').prop("disabled", false);
		$('#clamav_safebrowsing').prop("disabled", false);
		$('#clamav_disable_stream_scanning').prop("disabled", false);
		$('#clamav_update').prop("disabled", false);
		$('#clamav_dbregion').prop("disabled", false);
		$('#clamav_dbservers').prop("disabled", false);
		$('#load_advanced').prop("disabled", true);
		$('#raw_squidclamav_conf').prop("disabled", true);
		$('#raw_squidclamav_conf').val("");
		$('#raw_cicap_conf').prop("disabled", true);
		$('#raw_cicap_conf').val("");
		$('#raw_cicap_magic').prop("disabled", true);
		$('#raw_cicap_magic').val("");
		$('#raw_freshclam_conf').prop("disabled", true);
		$('#raw_freshclam_conf').val("");
		$('#raw_clamd_conf').prop("disabled", true);
		$('#raw_clamd_conf').val("");
	} else {
		$('#clamav_url').prop("disabled", true);
		$('#clamav_safebrowsing').prop("disabled", true);
		$('#clamav_safebrowsing').prop('checked', false);
		$('#clamav_disable_stream_scanning').prop("disabled", true);
		$('#clamav_disable_stream_scanning').prop('checked', false);
		$('#clamav_update').prop("disabled", false);
		$('#clamav_dbregion').prop("disabled", true);
		$('#clamav_dbregion').val("");
		$('#clamav_dbservers').prop("disabled", true);
		$('#clamav_dbservers').val("");
		$('#load_advanced').prop("disabled", false);
		$('#raw_squidclamav_conf').prop("disabled", false);
		$('#raw_cicap_conf').prop("disabled", false);
		$('#raw_cicap_magic').prop("disabled", false);
		$('#raw_freshclam_conf').prop("disabled", false);
		$('#raw_clamd_conf').prop("disabled", false);

	}

	if ($('#enable').prop('checked') == false) {
		$('#update_av').prop("disabled", true);
	} else {
		$('#update_av').prop("disabled", false);
	}
}
//]]>
</script>

EOD;
	print($javascript);

}

/* onchange toggle helper for squid_print_antivirus_advanced_config() function */
function squid_print_antivirus_advanced_config2() {
	print("<script type=\"text/javascript\">events.push(function () {on_antivirus_advanced_config_changed();});</script>\n");
}

?>
