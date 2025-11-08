<?php
/*
 * suricata_check_for_rule_updates.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2024 Bill Meeks
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

require_once("functions.inc");
require_once("service-utils.inc");
require_once("pfsense-utils.inc");
require_once("notices.inc");
require_once("/usr/local/pkg/suricata/suricata.inc");
require_once("/usr/local/pkg/suricata/suricata_defs.inc");

global $g, $rebuild_rules, $notify_message;

$suricatadir = SURICATADIR;
$suricatalogdir = SURICATALOGDIR;
$suricata_rules_dir = SURICATA_RULES_DIR;
$suri_eng_ver = filter_var(SURICATA_BIN_VERSION, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

/* define checks */
$oinkid = config_get_path('installedpackages/suricata/config/0/oinkcode');
$snort_filename = config_get_path('installedpackages/suricata/config/0/snort_rules_file');
$etproid = config_get_path('installedpackages/suricata/config/0/etprocode');
$snortdownload = config_get_path('installedpackages/suricata/config/0/enable_vrt_rules') == 'on' ? 'on' : 'off';
$etpro = config_get_path('installedpackages/suricata/config/0/enable_etpro_rules') == 'on' ? 'on' : 'off';
$eto = config_get_path('installedpackages/suricata/config/0/enable_etopen_rules') == 'on' ? 'on' : 'off';
$vrt_enabled = config_get_path('installedpackages/suricata/config/0/enable_vrt_rules') == 'on' ? 'on' : 'off';
$snortcommunityrules = config_get_path('installedpackages/suricata/config/0/snortcommunityrules') == 'on' ? 'on' : 'off';
$feodotracker_rules = config_get_path('installedpackages/suricata/config/0/enable_feodo_botnet_c2_rules') == 'on' ? 'on' : 'off';
$sslbl_rules = config_get_path('installedpackages/suricata/config/0/enable_abuse_ssl_blacklist_rules') == 'on' ? 'on' : 'off';
$enable_extra_rules = config_get_path('installedpackages/suricata/config/0/enable_extra_rules') == "on" ? 'on' : 'off';
$extra_rules = config_get_path('installedpackages/suricata/config/0/extra_rules/rule', []);

/* Working directory for downloaded rules tarballs */
$tmpfname = "{$g['tmp_path']}/suricata_rules_up";

/* Snort Rules filenames and URL */
if (config_get_path('installedpackages/suricata/config/0/enable_snort_custom_url') == 'on') {
	$snort_rule_url = trim(substr(config_get_path('installedpackages/suricata/config/0/snort_custom_url'), 0, strrpos(config_get_path('installedpackages/suricata/config/0/snort_custom_url'), '/') + 1));
	$snort_filename = trim(substr(config_get_path('installedpackages/suricata/config/0/snort_custom_url'), strrpos(config_get_path('installedpackages/suricata/config/0/snort_custom_url'), '/') + 1));
	$snort_filename_md5 = "{$snort_filename}.md5";
}
else {
	$snort_filename_md5 = "{$snort_filename}.md5";
	$snort_rule_url = VRT_DNLD_URL;
}

/* Snort GPLv2 Community Rules filenames and URL */
if (config_get_path('installedpackages/suricata/config/0/enable_gplv2_custom_url') == 'on') {
	$snort_community_rules_filename = trim(substr(config_get_path('installedpackages/suricata/config/0/gplv2_custom_url'), strrpos(config_get_path('installedpackages/suricata/config/0/gplv2_custom_url'), '/') + 1));
	$snort_community_rules_filename_md5 = $snort_community_rules_filename . ".md5";
	$snort_community_rules_url = trim(substr(config_get_path('installedpackages/suricata/config/0/gplv2_custom_url'), 0, strrpos(config_get_path('installedpackages/suricata/config/0/gplv2_custom_url'), '/') + 1));
}
else {
	$snort_community_rules_filename = GPLV2_DNLD_FILENAME;
	$snort_community_rules_filename_md5 = GPLV2_DNLD_FILENAME . ".md5";
	$snort_community_rules_url = GPLV2_DNLD_URL;
}

/* Set up ABUSE.ch Feodo Tracker and SSL Blacklist rules filenames and URLs */
if (config_get_path('installedpackages/suricata/config/0/enable_feodo_botnet_c2_rules') == 'on') {
	$feodotracker_rules_filename = FEODO_TRACKER_DNLD_FILENAME;
	$feodotracker_rules_filename_md5 = FEODO_TRACKER_DNLD_FILENAME . ".md5";
	$feodotracker_rules_url = FEODO_TRACKER_DNLD_URL;

}
if (config_get_path('installedpackages/suricata/config/0/enable_abuse_ssl_blacklist_rules') == 'on') {
	$sslbl_rules_filename = ABUSE_SSLBL_DNLD_FILENAME;
	$sslbl_rules_filename_md5 = ABUSE_SSLBL_DNLD_FILENAME . ".md5";
	$sslbl_rules_url = ABUSE_SSLBL_DNLD_URL;

}

/* Set up Emerging Threats rules filenames and URL */
if ($etpro == "on") {
	$et_name = "Emerging Threats Pro";
	if (config_get_path('installedpackages/suricata/config/0/enable_etpro_custom_url') == 'on') {
		$emergingthreats_url = trim(substr(config_get_path('installedpackages/suricata/config/0/etpro_custom_rule_url'), 0, strrpos(config_get_path('installedpackages/suricata/config/0/etpro_custom_rule_url'), '/') + 1));
		$emergingthreats_filename = trim(substr(config_get_path('installedpackages/suricata/config/0/etpro_custom_rule_url'), strrpos(config_get_path('installedpackages/suricata/config/0/etpro_custom_rule_url'), '/') + 1));
		$emergingthreats_filename_md5 = $emergingthreats_filename . ".md5";
		$et_md5_remove = ET_DNLD_FILENAME . ".md5";
	}
	else {
		$emergingthreats_filename = ETPRO_DNLD_FILENAME;
		$emergingthreats_filename_md5 = ETPRO_DNLD_FILENAME . ".md5";
		$emergingthreats_url = ETPRO_BASE_DNLD_URL;
		$emergingthreats_url .= "{$etproid}/suricata-{$suri_eng_ver}/";
		$et_md5_remove = ET_DNLD_FILENAME . ".md5";
	}
	unlink_if_exists("{$suricatadir}{$et_md5_remove}");
}
else {
	$et_name = "Emerging Threats Open";
	if (config_get_path('installedpackages/suricata/config/0/enable_etopen_custom_url') == 'on') {
		$emergingthreats_url = trim(substr(config_get_path('installedpackages/suricata/config/0/etopen_custom_rule_url'), 0, strrpos(config_get_path('installedpackages/suricata/config/0/etopen_custom_rule_url'), '/') + 1));
		$emergingthreats_filename = trim(substr(config_get_path('installedpackages/suricata/config/0/etopen_custom_rule_url'), strrpos(config_get_path('installedpackages/suricata/config/0/etopen_custom_rule_url'), '/') + 1));
		$emergingthreats_filename_md5 = $emergingthreats_filename . ".md5";
		$et_md5_remove = ETPRO_DNLD_FILENAME . ".md5";
	}
	else {
		$emergingthreats_filename = ET_DNLD_FILENAME;
		$emergingthreats_filename_md5 = ET_DNLD_FILENAME . ".md5";
		$emergingthreats_url = ET_BASE_DNLD_URL;
		// If using Snort rules with ET, then we should use the open-nogpl ET rules
		$emergingthreats_url .= $vrt_enabled == "on" ? "open-nogpl/" : "open/";
		$emergingthreats_url .= "suricata-{$suri_eng_ver}/";
		$et_md5_remove = ETPRO_DNLD_FILENAME . ".md5";
	}
	unlink_if_exists("{$suricatadir}{$et_md5_remove}");
}

// Set a common flag for all Emerging Threats rules (open and pro).
if ($etpro == 'on' || $eto == 'on')
	$emergingthreats = 'on';
else
	$emergingthreats = 'off';

function suricata_update_status($msg) {
	/************************************************/
	/* This function ensures we only output status  */
	/* update string messages during the package    */
	/* post-install phase.                          */
	/************************************************/

	global $g;

	if ($g['suricata_postinstall'] == true) {
		update_status($msg);
	}
}

function suricata_download_file_url($url, $file_out) {

	/************************************************/
	/* This function downloads the file specified   */
	/* by $url using the CURL library functions and */
	/* saves the content to the file specified by   */
	/* $file.                                       */
	/*                                              */
	/* This is needed so console output can be      */
	/* suppressed to prevent XMLRPC sync errors.    */
	/*                                              */
	/* It provides logging of returned CURL errors. */
	/************************************************/

	global $g, $last_curl_error;

	$rfc2616 = array(
			100 => "100 Continue",
			101 => "101 Switching Protocols",
			200 => "200 OK",
			201 => "201 Created",
			202 => "202 Accepted",
			203 => "203 Non-Authoritative Information",
			204 => "204 No Content",
			205 => "205 Reset Content",
			206 => "206 Partial Content",
			300 => "300 Multiple Choices",
			301 => "301 Moved Permanently",
			302 => "302 Found",
			303 => "303 See Other",
			304 => "304 Not Modified",
			305 => "305 Use Proxy",
			306 => "306 (Unused)",
			307 => "307 Temporary Redirect",
			400 => "400 Bad Request",
			401 => "401 Unauthorized",
			402 => "402 Payment Required",
			403 => "403 Forbidden",
			404 => "404 Not Found",
			405 => "405 Method Not Allowed",
			406 => "406 Not Acceptable",
			407 => "407 Proxy Authentication Required",
			408 => "408 Request Timeout",
			409 => "409 Conflict",
			410 => "410 Gone",
			411 => "411 Length Required",
			412 => "412 Precondition Failed",
			413 => "413 Request Entity Too Large",
			414 => "414 Request-URI Too Long",
			415 => "415 Unsupported Media Type",
			416 => "416 Requested Range Not Satisfiable",
			417 => "417 Expectation Failed",
			500 => "500 Internal Server Error",
			501 => "501 Not Implemented",
			502 => "502 Bad Gateway",
			503 => "503 Service Unavailable",
			504 => "504 Gateway Timeout",
			505 => "505 HTTP Version Not Supported"
		);

	$last_curl_error = "";

	$fout = fopen($file_out, 'wb');
	if ($fout) {
		$ch = curl_init($url);
		if (!$ch)
			return false;
		curl_setopt($ch, CURLOPT_FILE, $fout);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
		curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_NONE);
		curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
		curl_setopt($ch, CURLOPT_SSL_ENABLE_ALPN, true);
		curl_setopt($ch, CURLOPT_SSL_ENABLE_NPN, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);

		// detect broken connection so it disconnects after +-10 minutes (with default TCP_KEEPIDLE and TCP_KEEPINTVL) to avoid waiting forever.
		curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);

		// Honor any system restrictions on sending USERAGENT info
		if (config_path_enabled('system', 'do_not_send_host_uuid')) {
			curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' . $g['product_version'] . ' : ' . get_single_sysctl('kern.hostuuid'));
		}
		else {
			curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' . $g['product_version']);
		}

		// Use the system proxy server setttings if configured
		set_curlproxy($ch);

		$counter = 0;
		$rc = true;
		// Try up to 4 times to download the file before giving up
		while ($counter < 4) {
			$counter++;
			$rc = curl_exec($ch);
			if ($rc === true)
				break;
			logger(LOG_ERR, localize_text("Rules download error: %s", curl_error($ch)), LOG_PREFIX_PKG_SURICATA);
			logger(LOG_NOTICE, localize_text("Will retry the download in 15 seconds..."), LOG_PREFIX_PKG_SURICATA);
			sleep(15);
		}
		if ($rc === false)
			$last_curl_error = curl_error($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if (isset($rfc2616[$http_code]))
			$last_curl_error = $rfc2616[$http_code];
		fclose($fout);

		// If we had to try more than once, log it
		if ($counter > 1)
			logger(LOG_NOTICE, localize_text("File '%s' download attempts: %s ...", basename($file_out), $counter), LOG_PREFIX_PKG_SURICATA);
		return ($http_code == 200) ? true : $http_code;
	}
	else {
		$last_curl_error = gettext("Failed to create file " . $file_out);
		logger(LOG_ERR, localize_text("Failed to create file %s ...", $file_out), LOG_PREFIX_PKG_SURICATA);
		return false;
	}
}

function suricata_check_rule_md5($file_url, $file_dst, $desc = "") {

	/**********************************************************/
	/* This function attempts to download the passed MD5 hash */
	/* file and compare its contents to the currently stored  */
	/* hash file to see if a new rules file has been posted.  */
	/*                                                        */
	/* On Entry: $file_url = URL for md5 hash file            */
	/*           $file_dst = Temp destination to store the    */
	/*                       downloaded hash file             */
	/*           $desc     = Short text string used to label  */
	/*                       log messages with rules type     */
	/*                                                        */
	/*  Returns: TRUE if new rule file download required.     */
	/*           FALSE if rule download not required or an    */
	/*           error occurred.                              */
	/**********************************************************/

	global $last_curl_error, $update_errors, $notify_message;
	$suricatadir = SURICATADIR;
	$filename_md5 = basename($file_dst);

	suricata_update_status(gettext("Downloading {$desc} md5 file..."));
	error_log(gettext("\tDownloading {$desc} md5 file...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
	$rc = suricata_download_file_url($file_url, $file_dst);

	// See if download from URL was successful
	if ($rc === true) {
		suricata_update_status(gettext(" done.") . "\n");
		error_log("\tChecking {$desc} md5 file...\n", 3, SURICATA_RULES_UPD_LOGFILE);
		// check md5 hash in new file against current file to see if new download is posted
		if (file_exists("{$suricatadir}{$filename_md5}")) {
			$md5_check_new = trim(file_get_contents($file_dst));
			$md5_check_old = trim(file_get_contents("{$suricatadir}{$filename_md5}"));
			if ($md5_check_new == $md5_check_old) {
				suricata_update_status(gettext("{$desc} are up to date.") . "\n");
				logger(LOG_NOTICE, localize_text("%s are up to date...", $desc), LOG_PREFIX_PKG_SURICATA);
				error_log(gettext("\t{$desc} are up to date.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
				$notify_message .= gettext("- {$desc} are up to date.\n");
				return false;
			}
			else
				return true;
		}
		return true;
	}
	else {
		error_log(gettext("\t{$desc} md5 download failed.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
		$suricata_err_msg = gettext("Server returned error code {$rc}.");
		suricata_update_status(gettext("{$desc} md5 error ... Server returned error code {$rc}") . "\n");
		suricata_update_status(gettext("{$desc} will not be updated.") . "\n");
		logger(LOG_ERR, localize_text("%s md5 download failed...", $desc), LOG_PREFIX_PKG_SURICATA);
		logger(LOG_ERR, localize_text("Remote server returned error code %s...", $rc), LOG_PREFIX_PKG_SURICATA);
		error_log(gettext("\t{$suricata_err_msg}\n"), 3, SURICATA_RULES_UPD_LOGFILE);
		error_log(gettext("\tServer error message was: {$last_curl_error}\n"), 3, SURICATA_RULES_UPD_LOGFILE);
		error_log(gettext("\t{$desc} will not be updated.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
		$notify_message .= gettext("- {$desc} will not be updated, md5 download failed!\n");
		$update_errors = true;
		return false;
	}
}

function suricata_fetch_new_rules($file_url, $file_dst, $file_md5, $desc = "") {

	/**********************************************************/
	/* This function downloads the passed rules file and      */
	/* compares its computed md5 hash to the passed md5 hash  */
	/* to verify the file's integrity.                        */
	/*                                                        */
	/* On Entry: $file_url = URL of rules file                */
	/*           $file_dst = Temp destination to store the    */
	/*                       downloaded rules file            */
	/*           $file_md5 = Expected md5 hash for the new    */
	/*                       downloaded rules file            */
	/*           $desc     = Short text string for use in     */
	/*                       log messages                     */
	/*                                                        */
	/*  Returns: TRUE if download was successful.             */
	/*           FALSE if download was not successful.        */
	/**********************************************************/

	global $last_curl_error, $update_errors, $notify_message;

	$suricatadir = SURICATADIR;
	$filename = basename($file_dst);

	suricata_update_status(gettext("There is a new set of {$desc} posted. Downloading..."));
	logger(LOG_NOTICE, localize_text("There is a new set of %s posted. Downloading %s...", $desc, $filename), LOG_PREFIX_PKG_SURICATA);
	error_log(gettext("\tThere is a new set of {$desc} posted.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
	error_log(gettext("\tDownloading file '{$filename}'...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
       	$rc = suricata_download_file_url($file_url, $file_dst);

	// See if the download from the URL was successful
	if ($rc === true) {
		suricata_update_status(gettext(" done.") . "\n");
		logger(LOG_NOTICE, localize_text("%s file update downloaded successfully.", $desc), LOG_PREFIX_PKG_SURICATA);
		error_log(gettext("\tDone downloading rules file.\n"),3, SURICATA_RULES_UPD_LOGFILE);

		// Test integrity of the rules file.  Turn off update if file has wrong md5 hash
/*
    PHP ERROR: Type: 1, File: /usr/local/pkg/suricata/suricata_check_for_rule_updates.php, Line: 379, Message: Uncaught ValueError: gettext(): Argument #1 ($message) is too long in /usr/local/pkg/suricata/suricata_check_for_rule_updates.php:379
    Stack trace:
    #0 /usr/local/pkg/suricata/suricata_check_for_rule_updates.php(379): gettext(', but expected ...')
    #1 /usr/local/pkg/suricata/suricata_check_for_rule_updates.php(456): suricata_fetch_new_rules('https://rules.e...', '/tmp/suricata_r...', '<!DOCTYPE html>...', 'Emerging Threat...')
    #2 /usr/local/pkg/suricata/suricata_post_install.php(159): include('/usr/local/pkg/...')
    #3 /etc/inc/pkg-utils.inc(800) : eval()'d code(1): include_once('/usr/local/pkg/...')
    #4 /etc/inc/pkg-utils.inc(800): eval()
    #5 /etc/inc/pkg-utils.inc(917): eval_once('include_once("/...')
    #6 /etc/rc.packages(76): install_package_xml('suricata')
    #7 {main}
*/
		if ($file_md5 != trim(md5_file($file_dst))){
			suricata_update_status(gettext("{$desc} file MD5 checksum failed!") . "\n");
			logger(LOG_ERR, localize_text("%s file download failed.  Bad MD5 checksum.", $desc), LOG_PREFIX_PKG_SURICATA);
        	logger(LOG_ERR, localize_text("Downloaded file has MD5: %s, but expected MD5: %s", md5_file($file_dst), $file_md5), LOG_PREFIX_PKG_SURICATA);
			error_log(gettext("\t{$desc} file download failed.  Bad MD5 checksum.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
			error_log(gettext("\tDownloaded {$desc} file MD5: " . md5_file($file_dst) . "\n"), 3, SURICATA_RULES_UPD_LOGFILE);
			error_log(gettext("\tExpected {$desc} file MD5: ") . $file_md5 . PHP_EOL, 3, SURICATA_RULES_UPD_LOGFILE);
			error_log(gettext("\t{$desc} file download failed.  {$desc} will not be updated.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
			$notify_message .= gettext("- {$desc} will not be updated, bad MD5 checksum.\n");
			$update_errors = true;
			return false;
		}
		$notify_message .= gettext("- {$desc} rules were updated.\n");
		return true;
	}
	else {
		suricata_update_status(gettext("{$desc} file download failed!") . "\n");
		logger(LOG_ERR, localize_text("%s file download failed... server returned error '%s'.", $desc, $rc), LOG_PREFIX_PKG_SURICATA);
		error_log(gettext("\tERROR: {$desc} file download failed.  Remote server returned error {$rc}.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
		error_log(gettext("\tThe error text was: {$last_curl_error}\n"), 3, SURICATA_RULES_UPD_LOGFILE);
		error_log(gettext("\t{$desc} will not be updated.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
		$notify_message .= gettext("- {$desc} will not be updated, rules file download failed.\n");
		$update_errors = true;
		return false;
	}

}

/* Start of main code */

/*  remove old $tmpfname files if present */
if (is_dir("{$tmpfname}"))
	rmdir_recursive("{$tmpfname}");

/*  Make sure required suricatadirs exsist */
safe_mkdir("{$suricata_rules_dir}");
safe_mkdir("{$tmpfname}");
safe_mkdir("{$suricatalogdir}");

/* See if we need to automatically clear the Update Log based on 1024K size limit */
if (file_exists(SURICATA_RULES_UPD_LOGFILE)) {
	if (1048576 < filesize(SURICATA_RULES_UPD_LOGFILE)) {
		file_put_contents(SURICATA_RULES_UPD_LOGFILE, "");
	}
}
else {
	file_put_contents(SURICATA_RULES_UPD_LOGFILE, "");
}

/* Sleep for random number of seconds between 0 and 35 to spread load on rules site */
sleep(random_int(0, 35));

/* Log start time for this rules update */
error_log(gettext("Starting rules update...  Time: " . date("Y-m-d H:i:s") . "\n"), 3, SURICATA_RULES_UPD_LOGFILE);
$notify_message = gettext("Suricata rules update started: " . date("Y-m-d H:i:s") . "\n");
$notify_new_message = '';
$last_curl_error = "";
$update_errors = false;

/* Save current state (running/not running) for each enabled Suricatat interface */
$active_interfaces = array();
foreach (config_get_path('installedpackages/suricata/rule', []) as $value) {
	$if_real = get_real_interface($value['interface']);

	/* Skip processing for instances whose underlying physical        */
	/* interface has been removed in pfSense.                         */
	if ($if_real == "") {
		continue;
	}

	if ($value['enable'] = "on" && suricata_is_running($value['uuid'], $if_real)) {
		$active_interfaces[] = $value['interface'];
	}
}

/*  Check for and download any new Emerging Threats Rules sigs */
if ($emergingthreats == 'on') {
	if (suricata_check_rule_md5("{$emergingthreats_url}{$emergingthreats_filename_md5}", "{$tmpfname}/{$emergingthreats_filename_md5}", "{$et_name} rules")) {
		/* download Emerging Threats rules file */
		$file_md5 = trim(file_get_contents("{$tmpfname}/{$emergingthreats_filename_md5}"));
		if (!suricata_fetch_new_rules("{$emergingthreats_url}{$emergingthreats_filename}", "{$tmpfname}/{$emergingthreats_filename}", $file_md5, "{$et_name} rules")) {
			$emergingthreats = 'off';
		}
	} else {
		$emergingthreats = 'off';
	}
}

/*  Check for and download any new Snort rule sigs */
if ($snortdownload == 'on') {
	$snort_custom_url = config_get_path('installedpackages/suricata/config/0/enable_snort_custom_url') == 'on' ? TRUE : FALSE;
	if (empty($snort_filename)) {
		logger(LOG_WARNING, localize_text("No snortrules-snapshot filename has been set on Snort pkg GLOBAL SETTINGS tab.  Snort rules cannot be updated."), LOG_PREFIX_PKG_SURICATA);
		error_log(gettext("\tWARNING-- No snortrules-snapshot filename set on GLOBAL SETTINGS tab. Snort rules cannot be updated!\n"), 3, SURICATA_RULES_UPD_LOGFILE);
		$snortdownload = 'off';
	}
	elseif (suricata_check_rule_md5("{$snort_rule_url}{$snort_filename_md5}" . ($snort_custom_url ? "" : "?oinkcode={$oinkid}"), "{$tmpfname}/{$snort_filename_md5}", "Snort VRT rules")) {
		/* download snortrules file */
		$file_md5 = trim(file_get_contents("{$tmpfname}/{$snort_filename_md5}"));
		if (!suricata_fetch_new_rules("{$snort_rule_url}{$snort_filename}" . ($snort_custom_url ? "" : "?oinkcode={$oinkid}"), "{$tmpfname}/{$snort_filename}", $file_md5, "Snort rules")) {
			$snortdownload = 'off';
		}
	} else {
		$snortdownload = 'off';
	}
}

/*  Check for and download any new Snort GPLv2 Community Rules sigs */
if ($snortcommunityrules == 'on') {
	if (suricata_check_rule_md5("{$snort_community_rules_url}{$snort_community_rules_filename_md5}", "{$tmpfname}/{$snort_community_rules_filename_md5}", "Snort GPLv2 Community Rules")) {
		/* download Snort GPLv2 Community Rules file */
		$file_md5 = trim(file_get_contents("{$tmpfname}/{$snort_community_rules_filename_md5}"));
		if (!suricata_fetch_new_rules("{$snort_community_rules_url}{$snort_community_rules_filename}", "{$tmpfname}/{$snort_community_rules_filename}", $file_md5, "Snort GPLv2 Community Rules")) {
			$snortcommunityrules = 'off';
		}
	} else {
		$snortcommunityrules = 'off';
	}
}

/*  Download any new ABUSE.ch Fedoo Tracker Rules sigs */
if ($feodotracker_rules == 'on') {
	// Grab the MD5 hash of our last successful download if available
	if (file_exists("{$suricatadir}{$feodotracker_rules_filename}.md5")) {
		$old_file_md5 = trim(file_get_contents("{$suricatadir}{$feodotracker_rules_filename}.md5"));
	}
	else {
		$old_file_md5 = "0";
	}

	suricata_update_status(gettext("Downloading Feodo Tracker Botnet C2 IP rules file..."));
	error_log(gettext("\tDownloading Feodo Tracker Botnet C2 IP rules file...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
	$rc = suricata_download_file_url("{$feodotracker_rules_url}{$feodotracker_rules_filename}", "{$tmpfname}/{$feodotracker_rules_filename}");

	// See if the download from the URL was successful
	if ($rc === true) {
		suricata_update_status(gettext(" done.") . "\n");
		logger(LOG_NOTICE, localize_text("Feodo Tracker Botnet C2 IP rules file update downloaded successfully."), LOG_PREFIX_PKG_SURICATA);
		error_log(gettext("\tDone downloading rules file.\n"),3, SURICATA_RULES_UPD_LOGFILE);

		// See if file has changed from our previously downloaded version
		if ($old_file_md5 == trim(md5_file("{$tmpfname}/{$feodotracker_rules_filename}"))) {
			// File is unchanged from previous download, so no update required
			suricata_update_status(gettext("Feodo Tracker Botnet C2 IP rules are up to date.") . "\n");
			logger(LOG_NOTICE, localize_text("Feodo Tracker Botnet C2 IP rules are up to date..."), LOG_PREFIX_PKG_SURICATA);
			error_log(gettext("\tFeodo Tracker Botnet C2 IP rules are up to date.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
			$notify_message .= gettext("- Feodo Tracker Botnet C2 IP rules are up to date.\n");
			$feodotracker_rules = 'off';
		}
		else {
			// Downloaded file is changed, so update our local MD5 hash and extract the new rules
			file_put_contents("{$suricatadir}{$feodotracker_rules_filename}.md5", trim(md5_file("{$tmpfname}/{$feodotracker_rules_filename}")));
			suricata_update_status(gettext("Installing Feodo Tracker Botnet C2 IP rules..."));
			error_log(gettext("\tExtracting and installing Feodo Tracker Botnet C2 IP rules...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
			exec("/usr/bin/tar xzf {$tmpfname}/{$feodotracker_rules_filename} -C {$suricata_rules_dir}");
			suricata_update_status(gettext("Feodo Tracker Botnet C2 IP rules were updated.") . "\n");
			logger(LOG_NOTICE, localize_text("Feodo Tracker Botnet C2 IP rules were updated..."), LOG_PREFIX_PKG_SURICATA);
			error_log(gettext("\tFeodo Tracker Botnet C2 IP rules were updated.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
			$notify_message .= gettext("- Feodo Tracker Botnet C2 IP rules were updated.\n");
		}
	}
	else {
		suricata_update_status(gettext("Feodo Tracker Botnet C2 IP rules file download failed!") . "\n");
		logger(LOG_ERR, localize_text("Feodo Tracker Botnet C2 IP rules file download failed... server returned error '%s'.", $rc), LOG_PREFIX_PKG_SURICATA);
		error_log(gettext("\tERROR: Feodo Tracker Botnet C2 IP rules file download failed.  Remote server returned error {$rc}.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
		error_log(gettext("\tThe error text was: {$last_curl_error}\n"), 3, SURICATA_RULES_UPD_LOGFILE);
		error_log(gettext("\tFeodo Tracker Botnet C2 IP rules will not be updated.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
		$notify_message .= gettext("- Feodo Tracker Botnet C2 IP rules will not be updated, rules file download failed!\n");
		$update_errors = true;
		$feodotracker_rules = 'off';
	}
}

/*  Download any new ABUSE.ch SSL Blacklist Rules sigs */
if ($sslbl_rules == 'on') {
	// Grab the MD5 hash of our last successful download if available
	if (file_exists("{$suricatadir}{$sslbl_rules_filename}.md5")) {
		$old_file_md5 = trim(file_get_contents("{$suricatadir}{$sslbl_rules_filename}.md5"));
	}
	else {
		$old_file_md5 = "0";
	}

	suricata_update_status(gettext("Downloading ABUSE.ch SSL Blacklist rules file..."));
	error_log(gettext("\tDownloading ABUSE.ch SSL Blacklist rules file...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
	$rc = suricata_download_file_url("{$sslbl_rules_url}{$sslbl_rules_filename}", "{$tmpfname}/{$sslbl_rules_filename}");

	// See if the download from the URL was successful
	if ($rc === true) {
		suricata_update_status(gettext(" done.") . "\n");
		logger(LOG_NOTICE, localize_text("ABUSE.ch SSL Blacklist rules file update downloaded successfully."), LOG_PREFIX_PKG_SURICATA);
		error_log(gettext("\tDone downloading rules file.\n"),3, SURICATA_RULES_UPD_LOGFILE);

		// See if file has changed from our previously downloaded version
		if ($old_file_md5 == trim(md5_file("{$tmpfname}/{$sslbl_rules_filename}"))) {
			// File is unchanged from previous download, so no update required
			suricata_update_status(gettext("ABUSE.ch SSL Blacklist rules are up to date.") . "\n");
			logger(LOG_NOTICE, localize_text("ABUSE.ch SSL Blacklist rules are up to date..."), LOG_PREFIX_PKG_SURICATA);
			error_log(gettext("\tABUSE.ch SSL Blacklist rules are up to date.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
			$notify_message .= gettext("- ABUSE.ch SSL Blacklist rules are up to date.\n");
			$sslbl_rules = 'off';
		}
		else {
			// Downloaded file is changed, so update our local MD5 hash and extract the new rules
			file_put_contents("{$suricatadir}{$sslbl_rules_filename}.md5", trim(md5_file("{$tmpfname}/{$sslbl_rules_filename}")));
			suricata_update_status(gettext("Installing ABUSE.ch SSL Blacklist rules..."));
			error_log(gettext("\tExtracting and installing ABUSE.ch SSL Blacklist rules...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
			exec("/usr/bin/tar xzf {$tmpfname}/{$sslbl_rules_filename} -C {$suricata_rules_dir}");
			suricata_update_status(gettext("ABUSE.ch SSL Blacklist rules were updated.") . "\n");
			logger(LOG_NOTICE, localize_text("ABUSE.ch SSL Blacklist rules were updated..."), LOG_PREFIX_PKG_SURICATA);
			error_log(gettext("\tABUSE.ch SSL Blacklist rules were updated.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
			$notify_message .= gettext("- ABUSE.ch SSL Blacklist rules were updated.\n");
		}
	}
	else {
		suricata_update_status(gettext("ABUSE.ch SSL Blacklist rules file download failed!") . "\n");
		logger(LOG_ERR, localize_text("ABUSE.ch SSL Blacklist rules file download failed... server returned error '%s'.", $rc), LOG_PREFIX_PKG_SURICATA);
		error_log(gettext("\tERROR: ABUSE.ch SSL Blacklist rules file download failed.  Remote server returned error {$rc}.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
		error_log(gettext("\tThe error text was: {$last_curl_error}\n"), 3, SURICATA_RULES_UPD_LOGFILE);
		error_log(gettext("\tABUSE.ch SSL Blacklist rules will not be updated.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
		$notify_message .= gettext("- ABUSE.ch SSL Blacklist rules will not be updated, file download failed!\n");
		$update_errors = true;
		$sslbl_rules = 'off';
	}
}

/*  Download any new Extra Rules */
if (($enable_extra_rules == 'on') && !empty($extra_rules)) {
	$extraupdated = 'off';
	safe_mkdir("{$tmpfname}/extra");
	$tmpextradir = "{$tmpfname}/extra";
	$existing_extra_rules = array();
	foreach ($extra_rules as $exrule) {
		$format = (substr($exrule['url'], strrpos($exrule['url'], 'rules')) == 'rules') ? ".rules" : ".tar.gz";
		$rulesfilename = EXTRARULE_FILE_PREFIX . $exrule['name'] . $format;
		if (file_exists("{$suricatadir}{$rulesfilename}.md5")) {
			$old_file_md5 = trim(file_get_contents("{$suricatadir}{$rulesfilename}.md5"));
		} else {
			$old_file_md5 = "0";
		}

		if (($exrule['md5'] == 'on') &&
		    !suricata_check_rule_md5($exrule['url'] . '.md5', "{$tmpextradir}/{$rulesfilename}.md5", "Extra {$exrule['name']} rules")) {

			continue;
		}

		suricata_update_status(gettext("Downloading Extra {$exrule['name']} rules file..."));
		error_log(gettext("\tDownloading Extra {$exrule['name']} rules file...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
		$rc = suricata_download_file_url($exrule['url'], "{$tmpextradir}/{$rulesfilename}");

		// See if the download from the URL was successful
		if ($rc === true) {
			suricata_update_status(gettext(" done.") . "\n");
			logger(LOG_NOTICE, localize_text("Extra %s rules file update downloaded successfully.", $exrule['name']), LOG_PREFIX_PKG_SURICATA);
			error_log(gettext("\tDone downloading rules file.\n"),3, SURICATA_RULES_UPD_LOGFILE);

			// See if file has changed from our previously downloaded version
			if ($old_file_md5 == trim(md5_file("{$tmpextradir}/{$rulesfilename}"))) {
				// File is unchanged from previous download, so no update required
				suricata_update_status(gettext("Extra {$exrule['name']} rules are up to date.") . "\n");
				logger(LOG_NOTICE, localize_text("Extra %s rules are up to date...", $exrule['name']), LOG_PREFIX_PKG_SURICATA);
				error_log(gettext("\tExtra {$exrule['name']} rules are up to date.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
				$notify_message .= gettext("- Extra {$exrule['name']} rules are up to date.\n");
			} else {
				file_put_contents("{$suricatadir}{$rulesfilename}.md5", trim(md5_file("{$tmpextradir}/{$rulesfilename}")));
				suricata_update_status(gettext("Installing Extra {$exrule['name']} rules..."));
				error_log(gettext("\tExtracting and installing {$exrule['name']} IP rules...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
				if ($format == '.rules') { 
					@copy("{$tmpextradir}/{$rulesfilename}", "{$suricata_rules_dir}{$rulesfilename}");
				} else {
					safe_mkdir("{$tmpextradir}/{$exrule['name']}");
					exec("/usr/bin/tar xzf {$tmpextradir}/{$rulesfilename} -C {$tmpextradir}/{$exrule['name']}/");
					unlink_if_exists("{$suricata_rules_dir}" . EXTRARULE_FILE_PREFIX . $exrule['name'] . "-*.rules");
					$downloaded_rules = array();
					$files = suricata_listfiles("{$tmpextradir}/{$exrule['name']}");
					foreach ($files as $file) {
						$newfile = basename($file);
						$downloaded_rules[] = $newfile;
						if (substr($newfile, -6) == ".rules") {
							@copy($file, $suricata_rules_dir . EXTRARULE_FILE_PREFIX . $exrule['name'] . "-" . $newfile);
						}
					}
					if (file_exists("{$suricatadir}{$rulesfilename}.ruleslist")) {
						$existing_rules = unserialize(file_get_contents("{$suricatadir}{$rulesfilename}.ruleslist"));
						$newrules = array_diff($downloaded_rules, $existing_rules);
						if (!empty($newrules)) {
							$tmpstring = implode(', ', $newrules);
							// There is a 4096 character limit enforced for strings sent to gettext() !!
							// Make sure we don't exceed that if there are lots of new rule categories.
							$tmpstring = (strlen($tmpstring) > 4096) ? substr($tmpstring,0,4000).'... msg truncated - too long to translate' : $tmpstring;
							$notify_new_message .= gettext("- Extra {$exrule['name']} rules: " . $tmpstring . "\n");
							@file_put_contents("{$suricatadir}{$rulesfilename}.ruleslist", serialize($downloaded_rules));
						}
					} else {
						@file_put_contents("{$suricatadir}{$rulesfilename}.ruleslist", serialize($downloaded_rules));
					}
				}

				suricata_update_status(gettext("Extra {$exrule['name']} rules were updated.") . "\n");
				logger(LOG_NOTICE, localize_text("Extra %s rules were updated...", $exrule['name']), LOG_PREFIX_PKG_SURICATA);
				error_log(gettext("\tExtra {$exrule['name']} rules were updated.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
				$notify_message .= gettext("- Extra {$exrule['name']} rules were updated.\n");
				$extraupdated = 'on';
			}
		} else {
			suricata_update_status(gettext("Extra {$exrule['name']} rules file download failed!") . "\n");
			logger(LOG_ERR, localize_text("Extra %s rules file download failed... server returned error '%s'.", $exrule['name'], $rc), LOG_PREFIX_PKG_SURICATA);
			error_log(gettext("\tERROR: Extra {$exrule['name']} rules file download failed. Remote server returned error {$rc}.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
			error_log(gettext("\tThe error text was: {$last_curl_error}\n"), 3, SURICATA_RULES_UPD_LOGFILE);
			error_log(gettext("\tExtra {$exrule['name']} rules will not be updated.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
			$notify_message .= gettext("- Extra {$exrule['name']} rules will not be updated, file download failed!\n");
			$update_errors = true;
		}
		$existing_extra_rules[] = $exrule['name'];
	}
	rmdir_recursive($tmpextradir);
}

/* Untar Emerging Threats rules file to tmp if downloaded */
if ($emergingthreats == 'on') {
	safe_mkdir("{$tmpfname}/emerging");
	if (file_exists("{$tmpfname}/{$emergingthreats_filename}")) {
		suricata_update_status(gettext("Installing {$et_name} rules..."));
		error_log(gettext("\tExtracting and installing {$et_name} rules...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
		exec("/usr/bin/tar xzf {$tmpfname}/{$emergingthreats_filename} -C {$tmpfname}/emerging rules/");

		/* Remove the old Emerging Threats rules files */
		$eto_prefix = ET_OPEN_FILE_PREFIX;
		$etpro_prefix = ET_PRO_FILE_PREFIX;
		unlink_if_exists("{$suricata_rules_dir}{$eto_prefix}*.rules");
		unlink_if_exists("{$suricata_rules_dir}{$etpro_prefix}*.rules");
		unlink_if_exists("{$suricata_rules_dir}{$eto_prefix}*ips.txt");
		unlink_if_exists("{$suricata_rules_dir}{$etpro_prefix}*ips.txt");

		// The code below renames ET files with a prefix, so we
		// skip renaming the Suricata default events rule files
		// that are also bundled in the ET rules.
		$default_rules = array( "decoder-events.rules", "dns-events.rules", "files.rules", "http-events.rules", "smtp-events.rules", "stream-events.rules", "tls-events.rules" );
		$files = glob("{$tmpfname}/emerging/rules/*.rules");
		$downloaded_rules = array();
		// Determine the correct prefix to use based on which
		// Emerging Threats rules package is enabled.
		if ($etpro == "on")
			$prefix = ET_PRO_FILE_PREFIX;
		else
			$prefix = ET_OPEN_FILE_PREFIX;
		foreach ($files as $file) {
			$newfile = basename($file);
			$downloaded_rules[] = $newfile;
			if (in_array($newfile, $default_rules))
				@copy($file, "{$suricata_rules_dir}{$newfile}");
			else {
				if (strpos($newfile, $prefix) === FALSE)
					@copy($file, "{$suricata_rules_dir}{$prefix}{$newfile}");
				else
					@copy($file, "{$suricata_rules_dir}{$newfile}");
			}
		}
		/* IP lists for Emerging Threats rules */
		$files = glob("{$tmpfname}/emerging/rules/*ips.txt");
		foreach ($files as $file) {
			$newfile = basename($file);
			if ($etpro == "on")
				@copy($file, "{$suricata_rules_dir}" . ET_PRO_FILE_PREFIX . "{$newfile}");
			else
				@copy($file, "{$suricata_rules_dir}" . ET_OPEN_FILE_PREFIX . "{$newfile}");
		}
                /* base etc files for Emerging Threats rules */
		foreach (array("classification.config", "reference.config", "gen-msg.map", "unicode.map") as $file) {
			if (file_exists("{$tmpfname}/emerging/rules/{$file}"))
				@copy("{$tmpfname}/emerging/rules/{$file}", "{$tmpfname}/ET_{$file}");
		}

		/*  Copy emergingthreats md5 sig to Suricata dir */
		if (file_exists("{$tmpfname}/{$emergingthreats_filename_md5}")) {
			@copy("{$tmpfname}/{$emergingthreats_filename_md5}", "{$suricatadir}{$emergingthreats_filename_md5}");
		}
		if (file_exists("{$suricatadir}{$emergingthreats_filename}.ruleslist")) {
			$existing_rules = unserialize(file_get_contents("{$suricatadir}{$emergingthreats_filename}.ruleslist"));
			$newrules = array_diff($downloaded_rules, $existing_rules);
			if (!empty($newrules)) {
				$tmpstring = implode(', ', $newrules);
				$tmpstring = (strlen($tmpstring) > 4096) ? substr($tmpstring,0,4000).'... msg truncated - too long to translate' : $tmpstring;
				$notify_new_message .= gettext("- {$et_name} rules: " . $tmpstring . "\n");
				@file_put_contents("{$suricatadir}{$emergingthreats_filename}.ruleslist", serialize($downloaded_rules));
			}
		} else {
			@file_put_contents("{$suricatadir}{$emergingthreats_filename}.ruleslist", serialize($downloaded_rules));
		}
		suricata_update_status(gettext(" done.") . "\n");
		error_log(gettext("\tInstallation of {$et_name} rules completed.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
		rmdir_recursive("{$tmpfname}/emerging");
	}
}

/* Untar Snort rules file to tmp */
if ($snortdownload == 'on') {
	if (file_exists("{$tmpfname}/{$snort_filename}")) {
		/* Remove the old Snort rules files */
		$vrt_prefix = VRT_FILE_PREFIX;
		unlink_if_exists("{$suricata_rules_dir}{$vrt_prefix}*.rules");
		suricata_update_status(gettext("Installing Snort rules..."));
		error_log(gettext("\tExtracting and installing Snort rules...\n"), 3, SURICATA_RULES_UPD_LOGFILE);

		/* extract snort.org rules and add prefix to all snort.org files */
		safe_mkdir("{$tmpfname}/snortrules");
		exec("/usr/bin/tar xzf {$tmpfname}/{$snort_filename} -C {$tmpfname}/snortrules rules/");
		$files = glob("{$tmpfname}/snortrules/rules/*.rules");
		$downloaded_rules = array();
		foreach ($files as $file) {
			$newfile = basename($file);
			$downloaded_rules[] = $file;
			@copy($file, "{$suricata_rules_dir}" . VRT_FILE_PREFIX . "{$newfile}");
		}

		/* IP lists */
		$files = glob("{$tmpfname}/snortrules/rules/*.txt");
		foreach ($files as $file) {
			$newfile = basename($file);
			@copy($file, "{$suricata_rules_dir}{$newfile}");
		}
		rmdir_recursive("{$tmpfname}/snortrules");

		/* extract base etc files */
		exec("/usr/bin/tar xzf {$tmpfname}/{$snort_filename} -C {$tmpfname} etc/");
		foreach (array("classification.config", "reference.config", "gen-msg.map", "unicode.map") as $file) {
			if (file_exists("{$tmpfname}/etc/{$file}"))
				@copy("{$tmpfname}/etc/{$file}", "{$tmpfname}/VRT_{$file}");
		}
		rmdir_recursive("{$tmpfname}/etc");
		if (file_exists("{$tmpfname}/{$snort_filename_md5}")) {
			@copy("{$tmpfname}/{$snort_filename_md5}", "{$suricatadir}{$snort_filename_md5}");
		}
		if (file_exists("{$suricatadir}{$snort_filename}.ruleslist")) {
			$existing_rules = unserialize(file_get_contents("{$suricatadir}{$snort_filename}.ruleslist"));
			$newrules = array_diff($downloaded_rules, $existing_rules);
			if (!empty($newrules)) {
				$tmpstring = implode(', ', $newrules);
				$tmpstring = (strlen($tmpstring) > 4096) ? substr($tmpstring,0,4000).'... msg truncated - too long to translate' : $tmpstring;
				$notify_new_message .= gettext("- Snort rules: " . $tmpstring . "\n");
				@file_put_contents("{$suricatadir}{$snort_filename}.ruleslist", serialize($downloaded_rules));
			}
		} else {
			@file_put_contents("{$suricatadir}{$snort_filename}.ruleslist", serialize($downloaded_rules));
		}
		suricata_update_status(gettext(" done.") . "\n");
		error_log(gettext("\tInstallation of Snort rules completed.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
	}
}

/* Untar Snort GPLv2 Community rules file to tmp */
if ($snortcommunityrules == 'on') {
	safe_mkdir("{$tmpfname}/community");
	if (file_exists("{$tmpfname}/{$snort_community_rules_filename}")) {
		suricata_update_status(gettext("Installing Snort GPLv2 Community Rules..."));
		error_log(gettext("\tExtracting and installing Snort GPLv2 Community Rules...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
		exec("/usr/bin/tar xzf {$tmpfname}/{$snort_community_rules_filename} -C {$tmpfname}/community/");

		$files = glob("{$tmpfname}/community/community-rules/*.rules");
		$downloaded_rules = array();
		foreach ($files as $file) {
			$newfile = basename($file);
			$downloaded_rules[] = $newfile;
			@copy($file, "{$suricata_rules_dir}" . GPL_FILE_PREFIX . "{$newfile}");
		}
                /* base etc files for Snort GPLv2 Community rules */
		foreach (array("classification.config", "reference.config", "gen-msg.map", "unicode.map") as $file) {
			if (file_exists("{$tmpfname}/community/community-rules/{$file}"))
				@copy("{$tmpfname}/community/community-rules/{$file}", "{$tmpfname}/" . GPL_FILE_PREFIX . "{$file}");
		}
		/*  Copy snort community md5 sig to suricata dir */
		if (file_exists("{$tmpfname}/{$snort_community_rules_filename_md5}")) {
			@copy("{$tmpfname}/{$snort_community_rules_filename_md5}", "{$suricatadir}{$snort_community_rules_filename_md5}");
		}
		if (file_exists("{$suricatadir}{$snort_community_rules_filename}.ruleslist")) {
			$existing_rules = unserialize(file_get_contents("{$suricatadir}{$snort_community_rules_filename}.ruleslist"));
			$newrules = array_diff($downloaded_rules, $existing_rules);
			if (!empty($newrules)) {
				$tmpstring = implode(', ', $newrules);
				$tmpstring = (strlen($tmpstring) > 4096) ? substr($tmpstring,0,4000).'... msg truncated - too long to translate' : $tmpstring;
				$notify_new_message .= gettext("- Snort GPLv2 Community Rules: " . $tmpstring . "\n");
				@file_put_contents("{$suricatadir}{$snort_community_rules_filename}.ruleslist", serialize($downloaded_rules));
			}
		} else {
			@file_put_contents("{$suricatadir}{$snort_community_rules_filename}.ruleslist", serialize($downloaded_rules));
		}
		suricata_update_status(gettext(" done.") . "\n");
		error_log(gettext("\tInstallation of Snort GPLv2 Community Rules completed.\n"), 3, SURICATA_RULES_UPD_LOGFILE);
		rmdir_recursive("{$tmpfname}/community");
	}
}

// If removing deprecated rules categories, then do it
if (config_get_path('installedpackages/suricata/config/0/hide_deprecated_rules') == "on") {
	logger(LOG_NOTICE, localize_text("Hide Deprecated Rules is enabled.  Removing obsoleted rules categories."), LOG_PREFIX_PKG_SURICATA);
	suricata_remove_dead_rules();
}

function suricata_apply_customizations($suricatacfg, $if_real) {

	global $vrt_enabled, $rebuild_rules;
	$suricatadir = SURICATADIR;

	suricata_prepare_rule_files($suricatacfg, "{$suricatadir}suricata_{$suricatacfg['uuid']}_{$if_real}");

	/* Copy the master config and map files to the interface directory */
	@copy("{$suricatadir}classification.config", "{$suricatadir}suricata_{$suricatacfg['uuid']}_{$if_real}/classification.config");
	@copy("{$suricatadir}reference.config", "{$suricatadir}suricata_{$suricatacfg['uuid']}_{$if_real}/reference.config");
	@copy("{$suricatadir}gen-msg.map", "{$suricatadir}suricata_{$suricatacfg['uuid']}_{$if_real}/gen-msg.map");
	@copy("{$suricatadir}unicode.map", "{$suricatadir}suricata_{$suricatacfg['uuid']}_{$if_real}/unicode.map");
}

/* If we updated any rules, then refresh all the Suricata interfaces */
if ($snortdownload == 'on' || $emergingthreats == 'on' || $snortcommunityrules == 'on' || $feodotracker_rules == 'on' || $sslbl_rules == 'on' || $extraupdated == 'on') {

	/* If we updated Snort or ET rules, rebuild the config and map files as nescessary */
	if ($snortdownload == 'on' || $emergingthreats == 'on' || $snortcommunityrules == 'on') {

		error_log(gettext("\tCopying new config and map files...\n"), 3, SURICATA_RULES_UPD_LOGFILE);

		/******************************************************************/
		/* Build the classification.config and reference.config files     */
		/* using the ones from all the downloaded rules plus the default  */
		/* files installed with Suricata.                                 */
		/******************************************************************/
		$cfgs = glob("{$tmpfname}/*reference.config");
		$cfgs[] = "{$suricatadir}reference.config";
		suricata_merge_reference_configs($cfgs, "{$suricatadir}reference.config");
		$cfgs = glob("{$tmpfname}/*classification.config");
		$cfgs[] = "{$suricatadir}classification.config";
		suricata_merge_classification_configs($cfgs, "{$suricatadir}classification.config");

		/* Determine which map files to use for the master copy. */
		/* The Snort VRT ones are preferred, if available.       */
		if ($snortdownload == 'on')
			$prefix = "VRT_";
		elseif ($emergingthreats == 'on')
			$prefix = "ET_";
		elseif ($snortcommunityrules == 'on')
			$prefix = GPL_FILE_PREFIX;
		if (file_exists("{$tmpfname}/{$prefix}unicode.map"))
			@copy("{$tmpfname}/{$prefix}unicode.map", "{$suricatadir}unicode.map");
		if (file_exists("{$tmpfname}/{$prefix}gen-msg.map"))
			@copy("{$tmpfname}/{$prefix}gen-msg.map", "{$suricatadir}gen-msg.map");
	}

	/* Start the rules rebuild proccess for each configured interface */
	if (count(config_get_path('installedpackages/suricata/rule', [])) > 0) {

		/* Set the flag to force rule rebuilds since we downloaded new rules,    */
		/* except when in post-install mode.  Post-install does its own rebuild. */
		if ($g['suricata_postinstall'])
			$rebuild_rules = false;
		else
			$rebuild_rules = true;

		/* Create configuration for each active Suricata interface */
		foreach (config_get_path('installedpackages/suricata/rule', []) as $value) {
			$if_real = get_real_interface($value['interface']);

			/* Skip processing for instances whose underlying physical       */
			/* interface has been removed in pfSense.                        */
			if ($if_real == "") {
				continue;
			}

			// Make sure the interface subdirectory exists.  We need to re-create
			// it during a pkg reinstall on the initial rules set download.
			if (!is_dir("{$suricatadir}suricata_{$value['uuid']}_{$if_real}"))
				safe_mkdir("{$suricatadir}suricata_{$value['uuid']}_{$if_real}");
			if (!is_dir("{$suricatadir}suricata_{$value['uuid']}_{$if_real}/rules"))
				safe_mkdir("{$suricatadir}suricata_{$value['uuid']}_{$if_real}/rules");
			$tmp = "Updating rules configuration for: " . convert_friendly_interface_to_friendly_descr($value['interface']) . " ...";
			suricata_update_status(gettext($tmp));
			suricata_apply_customizations($value, $if_real);
			$tmp = "\t" . $tmp . "\n";
			error_log($tmp, 3, SURICATA_RULES_UPD_LOGFILE);
			suricata_update_status(gettext(" done.") . "\n");

			// If running, reload the rules for this interface
			if (in_array($value['interface'], $active_interfaces) && !$g['suricata_postinstall']) {
				// If running and "Live Reload" is enabled, just reload the configuration;
				// otherwise, start/restart the interface instance of Suricata.
				if (suricata_is_running($value['uuid'], $if_real) && config_get_path('installedpackages/suricata/config/0/live_swap_updates') == 'on') {
					logger(LOG_NOTICE, localize_text("Live-Reload of rules from auto-update is enabled..."), LOG_PREFIX_PKG_SURICATA);
					error_log(gettext("\tLive-Reload of updated rules is enabled...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
					suricata_update_status(gettext("Signaling Suricata to live-load the new set of rules for " . convert_friendly_interface_to_friendly_descr($value['interface']) . "..."));
					suricata_reload_config($value);
					suricata_update_status(gettext(" done.") . "\n");
					error_log(gettext("\tLive-Reload of updated rules requested for " . convert_friendly_interface_to_friendly_descr($value['interface']) . ".\n"), 3, SURICATA_RULES_UPD_LOGFILE);
				}
				else {
					suricata_update_status(gettext("Restarting Suricata to activate the new set of rules for " . convert_friendly_interface_to_friendly_descr($value['interface']) . "..."));
					error_log(gettext("\tRestarting Suricata to activate the new set of rules for " . convert_friendly_interface_to_friendly_descr($value['interface']) . "...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
					suricata_stop($value, $if_real);
					sleep(5);
					suricata_start($value, $if_real);
					suricata_update_status(gettext(" done.") . "\n");
					logger(LOG_NOTICE, localize_text("Suricata has restarted with your new set of rules for %s...", convert_friendly_interface_to_friendly_descr($value['interface'])), LOG_PREFIX_PKG_SURICATA);
					error_log(gettext("\tSuricata has restarted with your new set of rules for " . convert_friendly_interface_to_friendly_descr($value['interface']) . ".\n"), 3, SURICATA_RULES_UPD_LOGFILE);
				}
			}
		}
	}
	else {
		suricata_update_status(gettext("Warning:  No interfaces configured for Suricata were found!") . "\n");
		error_log(gettext("\tWarning:  No interfaces configured for Suricata were found...\n"), 3, SURICATA_RULES_UPD_LOGFILE);
	}

	/* Clear the rebuild rules flag.  */
	$rebuild_rules = false;
}

// Remove old $tmpfname files
if (is_dir("{$tmpfname}")) {
	suricata_update_status(gettext("Cleaning up after rules extraction..."));
	rmdir_recursive("{$tmpfname}");
	suricata_update_status(gettext(" done.") . "\n");
}

suricata_update_status(gettext("The Rules update has finished.") . "\n");
logger(LOG_NOTICE, localize_text("The Rules update has finished."), LOG_PREFIX_PKG_SURICATA);
error_log(gettext("The Rules update has finished.  Time: " . date("Y-m-d H:i:s"). "\n\n"), 3, SURICATA_RULES_UPD_LOGFILE);
$notify_message .= gettext("Suricata rules update finished: " . date("Y-m-d H:i:s"));

/* Save this update status to the rulesupd_status file */
$status = time() . '|';
if ($update_errors) {
	$status .= gettext("failed");
}
else {
	$status .= gettext("success");
}
@file_put_contents(SURICATADIR . "rulesupd_status", $status);

if (config_get_path('installedpackages/suricata/config/0/update_notify') == 'on') {
	notify_all_remote($notify_message);
}
if ((config_get_path('installedpackages/suricata/config/0/rule_categories_notify') == 'on') &&
    ($notify_new_message)) {
	notify_all_remote("Suricata new rule categories are available:\n" . $notify_new_message);
}

// Returns true when no errors occurred
return !$update_errors;
?>
