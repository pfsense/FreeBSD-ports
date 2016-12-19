<?php
/*
 * autoconfigbackup.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2016 Rubicon Communications, LLC (Netgate)
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

require_once("filter.inc");
require_once("notices.inc");

// Plugin moved to save only
unlink_if_exists("/usr/local/pkg/parse_config/parse_config_upload.inc");
unlink_if_exists("/usr/local/pkg/parse_config/parse_config_upload.php");

/* Check whether ACB is enabled */
function acb_enabled() {
	global $config;
	$acb_enabled = false;

	if (is_array($config['installedpackages']['autoconfigbackup']['config'])) {
		if ($config['installedpackages']['autoconfigbackup']['config'][0]['enable_acb'] == "on") {
			$acb_enabled = true;
		}
	}
	return $acb_enabled;
}

// Ensures patches match
function acb_custom_php_validation_command($post, &$input_errors) {
	global $_POST, $savemsg, $config;

	// Do nothing when ACB is disabled in configuration
	// This also makes it possible to delete the credentials from config.xml
	if (!acb_enabled()) {
		// We do not need to store this value.
		unset($_POST['testconnection']);
		return;
	}

	if (!$post['username']) {
		$input_errors[] = "Username is required.";
	}

	if (!$post['password'] or !$post['passwordagain']) {
		$input_errors[] = "The subscription password is required.";
	}

	if (!$post['crypto_password'] or !$post['crypto_password2']) {
		$input_errors[] = "The encryption password is required.";
	}

	if ($post['password'] <> $post['passwordagain']) {
		$input_errors[] = "Sorry, the entered portal.pfsense.org passwords do not match.";
	}

	if ($post['crypto_password'] <> $post['crypto_password2']) {
		$input_errors[] = "Sorry, the entered encryption passwords do not match.";
	}

	if ($post['testconnection']) {
		$status = test_connection($post);
		if ($status) {
			$savemsg = "Connection to portal.pfsense.org was tested with no errors.";
		}
	}

	// We do not need to store this value.
	unset($_POST['testconnection']);
}

function acb_custom_php_resync_config_command() {
	// Do nothing when ACB is disabled in configuration
	if (!acb_enabled()) {
		return;
	}

	if (is_file("/cf/conf/lastpfSbackup.txt")) {
		conf_mount_rw();
		unlink("/cf/conf/lastpfSbackup.txt");
		conf_mount_ro();
	}
	if (!function_exists("filter_configure")) {
		require_once("filter.inc");
	}
	filter_configure();
	if ($savemsg) {
		$savemsg .= "<br/>";
	}
	$savemsg .= "A configuration backup has been queued.";
}

function configure_proxy() {
	global $config;
	$ret = array();
	if (!empty($config['system']['proxyurl'])) {
		$ret[CURLOPT_PROXY] = $config['system']['proxyurl'];
		if (!empty($config['system']['proxyport'])) {
			$ret[CURLOPT_PROXYPORT] = $config['system']['proxyport'];
		}
		if (!empty($config['system']['proxyuser']) && !empty($config['system']['proxypass'])) {
			$ret[CURLOPT_PROXYAUTH] = CURLAUTH_ANY | CURLAUTH_ANYSAFE;
			$ret[CURLOPT_PROXYUSERPWD] = "{$config['system']['proxyuser']}:{$config['system']['proxypass']}";
		}
	}
	return $ret;
}

function test_connection($post) {
	global $savemsg, $config, $g;

	// Do nothing when booting or when not enabled
	if (platform_booting() || !acb_enabled()) {
		return;
	}

	// Seperator used during client / server communications
	$oper_sep = "\|\|";

	// Encryption password
	$decrypt_password = $post['crypto_password'];

	// Defined username
	$username = $post['username'];

	// Defined password
	$password = $post['password'];

	// Set hostname
	$hostname = $config['system']['hostname'] . "." . $config['system']['domain'];

	// URL to restore.php
	$get_url = "https://portal.pfsense.org/pfSconfigbackups/restore.php";

	// Populate available backups
	$curl_session = curl_init();
	curl_setopt($curl_session, CURLOPT_URL, $get_url);
	curl_setopt($curl_session, CURLOPT_HTTPHEADER, array("Authorization: Basic " . base64_encode("{$username}:{$password}")));
	curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 1);
	curl_setopt($curl_session, CURLOPT_POST, 1);
	curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl_session, CURLOPT_CONNECTTIMEOUT, 55);
	curl_setopt($curl_session, CURLOPT_TIMEOUT, 30);
	curl_setopt($curl_session, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/etc/version")));
	// Proxy
	curl_setopt_array($curl_session, configure_proxy());

	curl_setopt($curl_session, CURLOPT_POSTFIELDS, "action=showbackups&hostname={$hostname}");
	$data = curl_exec($curl_session);

	if (curl_errno($curl_session)) {
		return("An error occurred " . curl_error($curl_session));
	} else {
		curl_close($curl_session);
	}
	return;
}

function upload_config($reasonm = "") {
	global $config, $g, $input_errors;

	// Do nothing when booting or when not enabled
	if (platform_booting() || !acb_enabled()) {
		return;
	}

	/*
	 * pfSense upload config to pfSense.org script
	 * This file plugs into config.inc (/usr/local/pkg/parse_config)
	 * and runs every time the running firewall filter changes.
	 *
	 */

	if (file_exists("/tmp/acb_nooverwrite")) {
		unlink("/tmp/acb_nooverwrite");
		$nooverwrite = "true";
	} else {
		$nooverwrite = "false";
	}

	// Define some needed variables
	if (file_exists("/cf/conf/lastpfSbackup.txt")) {
		$last_backup_date = str_replace("\n", "", file_get_contents("/cf/conf/lastpfSbackup.txt"));
	} else {
		$last_backup_date = "";
	}

	$last_config_change = $config['revision']['time'];
	$hostname = $config['system']['hostname'] . "." . $config['system']['domain'];
	if ($reasonm) {
		$reason = $reasonm;
	} else {
		$reason	= $config['revision']['description'];
	}
	$username = $config['installedpackages']['autoconfigbackup']['config'][0]['username'];
	$password = $config['installedpackages']['autoconfigbackup']['config'][0]['password'];
	$encryptpw = $config['installedpackages']['autoconfigbackup']['config'][0]['crypto_password'];

	// Define upload_url, must be present after other variable definitions due to username, password
	$upload_url = "https://portal.pfsense.org/pfSconfigbackups/backup.php";

	if (!$username or !$password or !$encryptpw) {
		if (!file_exists("/cf/conf/autoconfigback.notice")) {
			$notice_text = "Either the username, password or encryption password is not set for Automatic Configuration Backup.";
			$notice_text .= " Please correct this in Diagnostics -> AutoConfigBackup -> Settings.";
			//log_error($notice_text);
			//file_notice("AutoConfigBackup", $notice_text, $notice_text, "");
			conf_mount_rw();
			touch("/cf/conf/autoconfigback.notice");
			conf_mount_ro();
		}
	} else {
		/* If configuration has changed, upload to pfS */
		if ($last_backup_date <> $last_config_change) {

			// Mount RW (if needed)
			conf_mount_rw();

			$notice_text = "Beginning https://portal.pfsense.org configuration backup.";
			log_error($notice_text);
			update_filter_reload_status($notice_text);

			// Encrypt config.xml
			$data = file_get_contents("/cf/conf/config.xml");
			$raw_config_sha256_hash = trim(shell_exec("/sbin/sha256 /cf/conf/config.xml | /usr/bin/awk '{ print $4 }'"));
			$data = encrypt_data($data, $encryptpw);
			tagfile_reformat($data, $data, "config.xml");

			$post_fields = array(
				'reason' => urlencode((string)$reason),
				'hostname' => urlencode($hostname),
				'configxml' => urlencode($data),
				'nooverwrite' => urlencode($nooverwrite),
				'raw_config_sha256_hash' => urlencode($raw_config_sha256_hash)
			);

			// URL-ify the data for the POST
			foreach ($post_fields as $key=>$value) {
				$fields_string .= $key . '=' . $value . '&';
			}
			rtrim($fields_string,'&');

			// Check configuration into the ESF repo
			$curl_session = curl_init();
			curl_setopt($curl_session, CURLOPT_URL, $upload_url);
			curl_setopt($curl_session, CURLOPT_HTTPHEADER, array("Authorization: Basic " . base64_encode("{$username}:{$password}")));
			curl_setopt($curl_session, CURLOPT_POST, count($post_fields));
			curl_setopt($curl_session, CURLOPT_POSTFIELDS, $fields_string);
			curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl_session, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($curl_session, CURLOPT_CONNECTTIMEOUT, 55);
			curl_setopt($curl_session, CURLOPT_TIMEOUT, 30);
			curl_setopt($curl_session, CURLOPT_USERAGENT, $g['product_name'] . '/' . rtrim(file_get_contents("/etc/version")));
			// Proxy
			curl_setopt_array($curl_session, configure_proxy());

			$data = curl_exec($curl_session);
			if (curl_errno($curl_session)) {
				$fd = fopen("/tmp/backupdebug.txt", "w");
				fwrite($fd, $upload_url . "" . $fields_string . "\n\n");
				fwrite($fd, $data);
				fwrite($fd, curl_error($curl_session));
				fclose($fd);
			} else {
				curl_close($curl_session);
			}

			if (!strstr($data, "500")) {
				$notice_text = "An error occurred while uploading your pfSense configuration to portal.pfsense.org (" . htmlspecialchars($data) . ")";
				log_error($notice_text . " - " . $data);
				file_notice("AutoConfigBackup", $notice_text, "Backup Failure", "/pkg_edit.php?xml=autoconfigbackup.xml&id=0");
				update_filter_reload_status($notice_text);
			} else {
				// Update last pfS backup time
				$fd = fopen("/cf/conf/lastpfSbackup.txt", "w");
				fwrite($fd, $config['revision']['time']);
				fclose($fd);
				$notice_text = "End of portal.pfsense.org configuration backup (success).";
				log_error($notice_text);
				update_filter_reload_status($notice_text);
			}

			// Mount image RO (if needed)
			conf_mount_ro();

		} else {
			// Debugging
			//log_error("No https://portal.pfsense.org backup required.");
		}
	}
}
