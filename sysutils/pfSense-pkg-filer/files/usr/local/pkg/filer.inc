<?php
/*
 * filer.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017-2022 Rubicon Communications, LLC (Netgate)
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

function filer_text_area_decode($text) {
	return preg_replace('/\r\n/', "\n", base64_decode($text));
}

function sync_package_filer($post = array()) {
	global $config, $g;
	$update_conf = 0;

	/* Loop on configured files. */
	if ($config['installedpackages']['filer']['config'] != "") {
		$count = 0;
		foreach ($config['installedpackages']['filer']['config'] as $file) {
			if ($file['filedata'] == "" && file_exists($file['fullfile'])) {
				$config['installedpackages']['filer']['config'][$count]['filedata'] = base64_encode(file_get_contents($file['fullfile']));
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
	}
	
	filer_sync_on_changes();
}

function checkUnique($value) {
	global $config;

	if ($config['installedpackages']['filer']['config'] != "") {
		foreach ($config['installedpackages']['filer']['config'] as $file) {
			if ($file['fullfile'] == $value) {
				return false;
			}
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
			if (!checkUnique($value)) {
				$input_errors[] = $value . " is already managed by filer.";
			}  
		}
	}
}


/* Uses XMLRPC to synchronize the changes to a remote node. */
function filer_sync_on_changes() {
	global $config;

	if (is_array($config['installedpackages']['filersync']['config'])) {
		$filer_sync = $config['installedpackages']['filersync']['config'][0];
		$synconchanges = $filer_sync['synconchanges'];
		$synctimeout = $filer_sync['synctimeout'] ?: '250';
		switch ($synconchanges) {
			case "manual":
				if (is_array($filer_sync['row'])) {
					$rs = $filer_sync['row'];
				} else {
					log_error("[filer] XMLRPC sync is enabled but there are no hosts configured as replication targets.");
					return;
				}
				break;
			case "auto":
				if (is_array($config['hasync'])) {
					$system_carp = $config['hasync'];
					$rs[0]['ipaddress'] = $system_carp['synchronizetoip'];
					$rs[0]['username'] = $system_carp['username'];
					$rs[0]['password'] = $system_carp['password'];
					$rs[0]['syncdestinenable'] = FALSE;

					// XMLRPC sync is currently only supported over connections using the same protocol and port as this system
					if ($config['system']['webgui']['protocol'] == "http") {
						$rs[0]['syncprotocol'] = "http";
						$rs[0]['syncport'] = $config['system']['webgui']['port'] ?: '80';
					} else {
						$rs[0]['syncprotocol'] = "https";
						$rs[0]['syncport'] = $config['system']['webgui']['port'] ?: '443';
					}
					if ($system_carp['synchronizetoip'] == "") {
						log_error("[filer] XMLRPC CARP/HA sync is enabled but there are no system backup hosts configured as replication targets.");
						return;
					} else {
						$rs[0]['syncdestinenable'] = TRUE;
					}
				} else {
					log_error("[filer] XMLRPC CARP/HA sync is enabled but there are no system backup hosts configured as replication targets.");
					return;
				}
				break;
			default:
				return;
				break;
		}
		if (is_array($rs)) {
			log_error("[filer] XMLRPC sync is starting.");
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
						log_error("[filer] XMLRPC sync with '{$sync_to_ip}' aborted due to the following error(s): {$error}");
					}
				}
			}
			log_error("[filer] XMLRPC sync completed.");
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
	global $config, $g;
	if ($username == "" || $password == "" || $sync_to_ip == "" || $port == "" || $protocol == "") {
		log_error("[filer] A required XMLRPC sync parameter (username, password, replication target, port or protocol) is empty ... aborting pkg sync");
		return;
	}
	/* XML will hold the sections to sync */
	$xml = array();
	$xml['filer'] = $config['installedpackages']['filer'];

	$execcmd = "require_once('/usr/local/pkg/filer.inc');\n";
	$execcmd .= "sync_package_filer();";	
	
	if (pf_version() >= "2.4") {
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
	} else {
		// pfSense before 2.4
		require_once('xmlrpc.inc');
		// Take care of IPv6 literal address
		if (is_ipaddrv6($sync_to_ip)) {
			$sync_to_ip = "[{$sync_to_ip}]";
		}
		$url = "{$protocol}://{$sync_to_ip}";
		/* Assemble XMLRPC payload */
		$params = array(XML_RPC_encode($password), XML_RPC_encode($xml));
		/* Set a few variables needed for sync */
		$method = 'pfsense.merge_installedpackages_section_xmlrpc';
		$msg = new XML_RPC_Message($method, $params);
		$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
		$cli->setCredentials($username, $password);
		if ($g['debug']) {
			$cli->setDebug(1);
		}
		/* Send our XMLRPC message and timeout after defined sync timeout value*/
		$resp = $cli->send($msg, $synctimeout);
		if (!$resp) {
			$error = "A communication error occurred while attempting XMLRPC sync with {$url}:{$port}.";
			log_error("[filer] {$error}");
			file_notice("sync_settings", $error, "filer Settings Sync", "");
		} elseif ($resp->faultCode()) {
			$cli->setDebug(1);
			$resp = $cli->send($msg, $synctimeout);
			$error = "An error code was received while attempting XMLRPC sync with {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString();
			log_error("[filer] {$error}");
			file_notice("sync_settings", $error, "filer Settings Sync", "");
		} else {
			log_error("[filer] XMLRPC sync successfully completed with {$url}:{$port}.");
		}
		/* Tell filer to reload our settings on the destination sync host. */
		$method = 'pfsense.exec_php';


		/* Assemble XMLRPC payload */
		$params = array(XML_RPC_encode($password), XML_RPC_encode($execcmd));
		log_error("[filer] XMLRPC reload data {$url}:{$port}.");
		$msg = new XML_RPC_Message($method, $params);
		$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
		$cli->setCredentials($username, $password);
		$resp = $cli->send($msg, $synctimeout);
		if (!$resp) {
			$error = "A communication error occurred while attempting XMLRPC sync with {$url}:{$port} (pfsense.exec_php).";
			log_error("[filer] {$error}");
			file_notice("sync_settings", $error, "filer Settings Sync", "");
		} elseif ($resp->faultCode()) {
			$cli->setDebug(1);
			$resp = $cli->send($msg, $synctimeout);
			$error = "An error code was received while attempting XMLRPC sync with {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString();
			log_error("[filer] {$error}");
			file_notice("sync_settings", $error, "filer Settings Sync", "");
		} else {
			log_error("[filer] XMLRPC reload data success with {$url}:{$port} (pfsense.exec_php).");
		}
	}
}

?>
