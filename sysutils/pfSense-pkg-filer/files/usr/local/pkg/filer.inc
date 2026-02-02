<?php
/*
 * filer.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2012 Marcello Coutinho
 * Copyright (c) 2012 Brian Scholer
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

require_once("config.inc");
require_once("globals.inc");
require_once("notices.inc");
require_once("util.inc");
require_once("xmlrpc_client.inc");

define('LOG_PREFIX_PKG_FILER', 'Filer');

function filer_text_area_decode($text) {
	return preg_replace('/\r\n/', "\n", base64_decode($text));
}

function filer_sync($post = array()) {
	global $g;
	$update_conf = 0;

	/* Loop on configured files. */
	$count = 0;
	foreach (config_get_path('installedpackages/filer/config', []) as $file) {
		if ($file['filedata'] == "" && file_exists($file['fullfile'])) {
			config_set_path("installedpackages/filer/config/{$count}/filedata", base64_encode(file_get_contents($file['fullfile'])));
			$file['filedata'] = base64_encode(file_get_contents($file['fullfile']));
			$update_conf++;
		}
		$count++;
		if (!empty($post) && ($post['id'] != ($count - 1))) {
			continue;
		}
		if (preg_match("/0?[0-7]{3}/", $file['mod'])) {
			$mod = octdec($file['mod']);
		} else {
			$mod = 0644;
		}

		/* Write file. */
		safe_mkdir(dirname($file['fullfile']));
		file_put_contents($file['fullfile'], filer_text_area_decode($file['filedata']), LOCK_EX);
		chmod($file['fullfile'], $mod);

		/* Check if there is a script to run after file save. */
		if (!empty($file['cmd'])) {
			switch ($file['background']) {
				case "background":
					mwexec_bg($file['cmd']);
					break;
				case "foreground":
					mwexec($file['cmd']);
					break;
				default:
					break;
			}
		}
	}

	/* Write config if any file from filesystem was loaded. */
	if ($update_conf > 0) {
		write_config("Filer: Settings saved");
	}
	
	filer_sync_on_changes();
}

function checkUnique($value) {
	foreach (config_get_path('installedpackages/filer/config', []) as $file) {
		if ($file['fullfile'] == $value) {
			return false;
		}
	}

	return true;
}

function filer_validate_input($post, &$input_errors) {
	foreach ($post as $key => $value) {
		if (empty($value)) {
			continue;
		}
		if (substr($key, 0, 3) == "mod" && !preg_match("/^0?[0-7]{3}$/", $value)) {
			$input_errors[] = "{$value} is not valid permissions mode number.";
		}
		if (substr($key, 0, 11) == "description" && !preg_match("@^[a-zA-Z0-9 _/.-]+$@", $value)) {
			$input_errors[] = "Do not use special characters in description.";
		}
		if (substr($key, 0, 8) == "fullfile") {
			if (!preg_match("@^[a-zA-Z0-9_/.-]+$@", $value)) {
				$input_errors[] = "Do not use special characters in filename.";
			}
			if (($_REQUEST['act'] !== 'edit')
			    && (!checkUnique($value))) {
					$input_errors[] = $value . " is already managed by filer.";
			}  
		}
	}
}

/* Uses XMLRPC to synchronize the changes to a remote node. */
function filer_sync_on_changes() {
	if (is_array(config_get_path('installedpackages/filersync/config'))) {
		$filer_sync = config_get_path('installedpackages/filersync/config/0');
		$synconchanges = $filer_sync['synconchanges'];
		$synctimeout = $filer_sync['synctimeout'] ?: '250';
		switch ($synconchanges) {
			case "manual":
				if (is_array($filer_sync['row'])) {
					$rs = $filer_sync['row'];
				} else {
					logger(LOG_WARNING, localize_text("XMLRPC sync is enabled but there are no hosts configured as replication targets."), LOG_PREFIX_PKG_FILER);
					return;
				}
				break;
			default:
				return;
				break;
		}
		if (is_array($rs)) {
			logger(LOG_INFO, localize_text("XMLRPC sync is starting."), LOG_PREFIX_PKG_FILER);
			foreach ($rs as $sh) {
				// Only sync enabled replication targets
				if ($sh['syncdestinenable']) {
					$sync_to_ip = $sh['ipaddress'];
					$port = $sh['syncport'];
					$username = $sh['username'] ?: 'admin';
					$password = $sh['password'];
					$protocol = $sh['syncprotocol'];

					$error = '';
					$valid = TRUE;

					if ($password == "") {
						$error = "Password parameter is empty. ";
						$valid = FALSE;
					}
					if (!is_ipaddr($sync_to_ip) && !is_hostname($sync_to_ip) && !is_domain($sync_to_ip)) {
						$error .= "Misconfigured Replication Target IP Address or Hostname. ";
						$valid = FALSE;
					}
					if (!is_port($port)) {
						$error .= "Misconfigured Replication Target Port. ";
						$valid = FALSE;
					}
					if ($valid) {
						filer_do_xmlrpc_sync($sync_to_ip, $port, $protocol, $username, $password, $synctimeout);
					} else {
						logger(LOG_ERR, localize_text("XMLRPC sync with '%s' aborted due to the following error(s): %s", $sync_to_ip, $error), LOG_PREFIX_PKG_FILER);
					}
				}
			}
			logger(LOG_INFO, localize_text("XMLRPC sync completed."), LOG_PREFIX_PKG_FILER);
		}
 	}
} 

if(!function_exists('pf_version')) {
	function pf_version() {
		return substr(trim(file_get_contents("/etc/version")), 0, 3);
	}
}

/* Do the actual XMLRPC sync. */
function filer_do_xmlrpc_sync($sync_to_ip, $port, $protocol, $username, $password, $synctimeout) {
	global $g;
	if ($username == "" || $password == "" || $sync_to_ip == "" || $port == "" || $protocol == "") {
		logger(LOG_WARNING, localize_text("A required XMLRPC sync parameter (username, password, replication target, port or protocol) is empty ... aborting pkg sync"), LOG_PREFIX_PKG_FILER);
		return;
	}
	/* XML will hold the sections to sync */
	$xml = array();
	$xml['filer'] = config_get_path('installedpackages/filer');

	$execcmd = "require_once('/usr/local/pkg/filer.inc');\n";
	$execcmd .= "filer_sync();";
	
	// xmlrpc cannot encode NULL objects/arrays..
	foreach($xml as $xmlkey => $xmlvalue) {
		if (gettype($xmlvalue) == "NULL") {
			$xml[$xmlkey] = array();
		}
	}
	$synctimeout = intval($synctimeout);
	$rpc_client = new pfsense_xmlrpc_client();
	$rpc_client->setConnectionData($sync_to_ip, $port, $username, $password, $protocol);
	$resp = $rpc_client->xmlrpc_method('merge_installedpackages_section', $xml, $synctimeout);
	$resp = $rpc_client->xmlrpc_exec_php($execcmd, $synctimeout);
}

function filer_plugin_xmlrpc_send() {
	if (config_get_path('installedpackages/filersync/config/0/synconchanges') != 'auto') {
		return [];
	}

	return [
		'installedpackages/filersync/config/0/synconchanges',
		'installedpackages/filer'
	];
}

function filer_plugin_xmlrpc_recv($new_sections) {
	if (array_get_path($new_sections, 'installedpackages/filersync/config/0/synconchanges') != 'auto') {
		return [];
	}

	$section_paths = [
		'installedpackages/filer'
	];

	$ret = [
		'xmlrpc_recv_result' => false
	];
	foreach ($section_paths as $path) {
		$old_section = config_get_path($path, []);
		$new_section = array_get_path($new_sections, $path, []);

		// Don't save empty configuration sections.
		if (empty($old_section) && empty($new_section)) {
			continue;
		}
		// Ignore unchanged configuration sections.
		if ($old_section === $new_section) {
			continue;
		}

		$ret[$path] = array_merge($old_section, $new_section);
		$ret['xmlrpc_recv_result'] = true;
	}

	return $ret;
}

function filer_plugin_xmlrpc_recv_done($xmlrpc_recv_result) {
	if (array_get_path($xmlrpc_recv_result, 'filer/xmlrpc_recv_result') !== true) {
		return;
	}
	filer_sync();
}
