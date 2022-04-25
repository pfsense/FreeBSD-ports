<?php
/*
 * snort_check_for_rule_updates.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2009 Robert Zelaya
 * Copyright (c) 2013-2022 Bill Meeks
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
require_once("/usr/local/pkg/snort/snort.inc");
require("/usr/local/pkg/snort/snort_defs.inc");

global $g, $config, $rebuild_rules;

$snortdir = SNORTDIR;
$snortlibdir = SNORT_BASEDIR . "lib";
$snortlogdir = SNORTLOGDIR;
$snortiprepdir = SNORT_IPREP_PATH;
$snortbindir = SNORT_BINDIR;

/* define checks */
$oinkid = $config['installedpackages']['snortglobal']['oinkmastercode'];
$etproid = $config['installedpackages']['snortglobal']['etpro_code'];
$snortdownload = $config['installedpackages']['snortglobal']['snortdownload'] == 'on' ? 'on' : 'off';
$emergingthreats = $config['installedpackages']['snortglobal']['emergingthreats'] == 'on' ? 'on' : 'off';
$etpro = $config['installedpackages']['snortglobal']['emergingthreats_pro'] == 'on' ? 'on' : 'off';
$snortcommunityrules = $config['installedpackages']['snortglobal']['snortcommunityrules'] == 'on' ? 'on' : 'off';
$vrt_enabled = $config['installedpackages']['snortglobal']['snortdownload'] == 'on' ? 'on' : 'off';
$openappid_detectors = $config['installedpackages']['snortglobal']['openappid_detectors'] == 'on' ? 'on' : 'off';
$openappid_rules_detectors = $config['installedpackages']['snortglobal']['openappid_rules_detectors'] == 'on' ? 'on' : 'off';
$feodotracker_rules = $config['installedpackages']['snortglobal']['enable_feodo_botnet_c2_rules'] == 'on' ? 'on' : 'off';

/* Working directory for downloaded rules tarballs and extraction */
$tmpfname = "{$g['tmp_path']}/snort_rules_up";

/* Use the Snort binary version to construct the proper Snort Subscriber */
/* Rules tarball and md5 filenames. Save the version with decimal        */
/* delimiters for use in extracting the rules.                           */
$snort_version = SNORT_BIN_VERSION;

// Create a collapsed version string for use in the tarball filename
$snortver = str_replace(".", "", SNORT_BIN_VERSION);

// Make sure the rules version is at least 5 characters in length
// by adding trailing zeros if required.
if (strlen($snortver) < 5) {
	$snortver = str_pad($snortver, 5, '0', STR_PAD_RIGHT);
	$snort_version .= ".0";
}

$snort_filename = "snortrules-snapshot-{$snortver}.tar.gz";
$snort_filename_md5 = "{$snort_filename}.md5";
$snort_rule_url = VRT_DNLD_URL;

/* Set up Emerging Threats rules filenames and URL */
if ($etpro == "on") {
	$emergingthreats_filename = SNORT_ETPRO_DNLD_FILENAME;
	$emergingthreats_filename_md5 = SNORT_ETPRO_DNLD_FILENAME . ".md5";
	$emergingthreats_url = ETPRO_BASE_DNLD_URL;
	$emergingthreats_url .= "{$etproid}/snort-" . ET_VERSION . "/";
	$emergingthreats = "on";
	$et_name = "Emerging Threats Pro";
	$et_md5_remove = SNORT_ET_DNLD_FILENAME . ".md5";
	unlink_if_exists("{$snortdir}/{$et_md5_remove}");
}
else {
	$emergingthreats_filename = SNORT_ET_DNLD_FILENAME;
	$emergingthreats_filename_md5 = SNORT_ET_DNLD_FILENAME . ".md5";
	$emergingthreats_url = ET_BASE_DNLD_URL;
	// If using Sourcefire Subscriber rules with ET, then we should use the open-nogpl ET rules
	$emergingthreats_url .= $vrt_enabled == "on" ? "open-nogpl/" : "open/";
	$emergingthreats_url .= "snort-" . ET_VERSION . "/";
	$et_name = "Emerging Threats Open";
	$et_md5_remove = SNORT_ETPRO_DNLD_FILENAME . ".md5";
	unlink_if_exists("{$snortdir}/{$et_md5_remove}");
}

/* Snort GPLv2 Community Rules filenames and URL */
$snort_community_rules_filename = SNORT_GPLV2_DNLD_FILENAME;
$snort_community_rules_filename_md5 = SNORT_GPLV2_DNLD_FILENAME . ".md5";
$snort_community_rules_url = GPLV2_DNLD_URL;

/* Snort OpenAppID detectors filename and URL */
$snort_openappid_filename = SNORT_OPENAPPID_DNLD_FILENAME;
$snort_openappid_filename_md5 = SNORT_OPENAPPID_DNLD_FILENAME . ".md5";
$snort_openappid_url = SNORT_OPENAPPID_DNLD_URL;

/* Snort OpenAppID detectors filename and URL FOR RULES APPID */
$snort_openappid_rules_filename = SNORT_OPENAPPID_RULES_FILENAME;
$snort_openappid_rules_filename_md5 = SNORT_OPENAPPID_RULES_FILENAME . ".md5";
$snort_openappid_rules_url = SNORT_OPENAPPID_RULES_URL;

/* Set up ABUSE.ch Feodo Tracker rules filename and URL */
if ($feodotracker_rules == 'on') {
	$feodotracker_rules_filename = FEODO_TRACKER_DNLD_FILENAME;
	$feodotracker_rules_filename_md5 = FEODO_TRACKER_DNLD_FILENAME . ".md5";
	$feodotracker_rules_url = FEODO_TRACKER_DNLD_URL;
}

function snort_update_status($msg) {
	/************************************************/
	/* This function ensures we only output status  */
	/* update string messages during the package    */
	/* post-install phase.                          */
	/************************************************/

	global $g;

	if ($g['snort_postinstall'] == true) {
		update_status($msg);
	}
}

function snort_download_file_url($url, $file_out) {

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

	global $g, $config, $last_curl_error, $fout, $ch;

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

	$fout = fopen($file_out, "wb");
	if ($fout) {
		$ch = curl_init($url);
		if (!$ch)
			return false;
		curl_setopt($ch, CURLOPT_FILE, $fout);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, "TLSv1.2, TLSv1");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $config['installedpackages']['snortglobal']['curl_no_verify_ssl_peer'] == "on" ? false : true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
		curl_setopt($ch, CURLOPT_TIMEOUT, 0);

		// Honor any system restrictions on sending USERAGENT info
		if (!isset($config['system']['do_not_send_host_uuid'])) {
			curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' . $g['product_version'] . ' : ' . get_single_sysctl('kern.hostuuid'));
		}
		else {
			curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' . $g['product_version']);
		}

		// Use the system proxy server setttings if configured
		if (!empty($config['system']['proxyurl'])) {
			curl_setopt($ch, CURLOPT_PROXY, $config['system']['proxyurl']);
			if (!empty($config['system']['proxyport']))
				curl_setopt($ch, CURLOPT_PROXYPORT, $config['system']['proxyport']);
			if (!empty($config['system']['proxyuser']) && !empty($config['system']['proxypass'])) {
				@curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_ANY | CURLAUTH_ANYSAFE);
				curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$config['system']['proxyuser']}:{$config['system']['proxypass']}");
			}
		}

		$counter = 0;
		$rc = true;
		// Try up to 4 times to download the file before giving up
		while ($counter < 4) {
			$counter++;
			$rc = curl_exec($ch);
			if ($rc === true)
				break;
			syslog(LOG_ERR, gettext("[Snort] Rules download error: " . curl_error($ch)));
			syslog(LOG_ERR, gettext("[Snort] Will retry in 15 seconds..."));
			sleep(15);
		}
		if ($rc === false)
			$last_curl_error = curl_error($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if (isset($rfc2616[$http_code]))
			$last_curl_error = $rfc2616[$http_code];
		curl_close($ch);
		fclose($fout);

		// If we had to try more than once, log it
		if ($counter > 1)
			syslog(LOG_NOTICE, gettext("File '" . basename($file_out) . "' download attempts: {$counter} ..."));
		return ($http_code == 200) ? true : $http_code;
	}
	else {
		$last_curl_error = gettext("Failed to create file " . $file_out);
		syslog(LOG_ERR, gettext("[Snort] Failed to create file {$file_out} ..."));
		return false;
	}
}

function snort_check_rule_md5($file_url, $file_dst, $desc = "") {

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

	global $last_curl_error, $update_errors;

	$snortdir = SNORTDIR;
	$filename_md5 = basename($file_dst);

	snort_update_status(gettext("Downloading {$desc} md5 file..."));
	error_log(gettext("\tDownloading {$desc} md5 file {$filename_md5}...\n"), 3, SNORT_RULES_UPD_LOGFILE);
	$rc = snort_download_file_url($file_url, $file_dst);

	// See if download from URL was successful
	if ($rc === true) {
		snort_update_status(gettext(" done.") . "\n");
		error_log("\tChecking {$desc} md5 file...\n", 3, SNORT_RULES_UPD_LOGFILE);
		snort_update_status(gettext("Checking {$desc} md5 file..."));

		// check md5 hash in new file against current file to see if new download is posted
		if (file_exists("{$snortdir}/{$filename_md5}")) {
			$md5_check_new = trim(file_get_contents($file_dst));
			$md5_check_old = trim(file_get_contents("{$snortdir}/{$filename_md5}"));
			snort_update_status(gettext(" done.") . "\n");
			if ($md5_check_new == $md5_check_old) {
				snort_update_status(gettext("{$desc} are current. No update required.") . "\n");
				syslog(LOG_NOTICE, gettext("[Snort] {$desc} are up to date..."));
				error_log(gettext("\t{$desc} are up to date.\n"), 3, SNORT_RULES_UPD_LOGFILE);
				return false;
			}
			else {
				return true;
			}
		}
		snort_update_status(gettext(" done.") . "\n");
		return true;
	}
	else {
		error_log(gettext("\t{$desc} md5 download failed.\n"), 3, SNORT_RULES_UPD_LOGFILE);
		$snort_err_msg = gettext("Server returned error code {$rc}.");
		snort_update_status(gettext(" FAILED!") . "\n");
		snort_update_status(gettext("{$desc} md5 error ... Server returned error code {$rc} ...") . "\n");
		snort_update_status(gettext("{$desc} will not be updated.\n{$snort_err_msg}") . "\n");
		syslog(LOG_ERR, gettext("[Snort] {$desc} md5 download failed..."));
		syslog(LOG_ERR, gettext("[Snort] Server returned error code {$rc}..."));
		error_log(gettext("\t{$snort_err_msg}\n"), 3, SNORT_RULES_UPD_LOGFILE);
		error_log(gettext("\tServer error message was: {$last_curl_error}\n"), 3, SNORT_RULES_UPD_LOGFILE);
		error_log(gettext("\t{$desc} will not be updated.\n"), 3, SNORT_RULES_UPD_LOGFILE);
		$update_errors = true;
		return false;
	}
}

function snort_fetch_new_rules($file_url, $file_dst, $file_md5, $desc = "") {

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

	global $last_curl_error, $update_errors;

	$snortdir = SNORTDIR;
	$filename = basename($file_dst);

	snort_update_status(gettext("There is a new set of {$desc} posted.\nDownloading {$filename}..."));
	syslog(LOG_NOTICE, gettext("[Snort] There is a new set of {$desc} posted. Downloading {$filename}..."));
	error_log(gettext("\tThere is a new set of {$desc} posted.\n"), 3, SNORT_RULES_UPD_LOGFILE);
	error_log(gettext("\tDownloading file '{$filename}'...\n"), 3, SNORT_RULES_UPD_LOGFILE);
       	$rc = snort_download_file_url($file_url, $file_dst);

	// See if the download from the URL was successful
	if ($rc === true) {
		snort_update_status(gettext(" done.") . "\n");
		syslog(LOG_NOTICE, "[Snort] {$desc} file update downloaded successfully");
		error_log(gettext("\tDone downloading rules file.\n"),3, SNORT_RULES_UPD_LOGFILE);
	
		// Test integrity of the rules file.  Turn off update if file has wrong md5 hash
		if ($file_md5 != trim(md5_file($file_dst))){
			snort_update_status(gettext("{$desc} file MD5 checksum failed...") . "\n");
			syslog(LOG_ERR, gettext("[Snort] {$desc} file download failed.  Bad MD5 checksum..."));
        	        syslog(LOG_ERR, gettext("[Snort] Downloaded File MD5: " . md5_file($file_dst)));
			syslog(LOG_ERR, gettext("[Snort] Expected File MD5: {$file_md5}"));
			error_log(gettext("\t{$desc} file download failed.  Bad MD5 checksum.\n"), 3, SNORT_RULES_UPD_LOGFILE);
			error_log(gettext("\tDownloaded {$desc} file MD5: " . md5_file($file_dst) . "\n"), 3, SNORT_RULES_UPD_LOGFILE);
			error_log(gettext("\tExpected {$desc} file MD5: {$file_md5}\n"), 3, SNORT_RULES_UPD_LOGFILE);
			error_log(gettext("\t{$desc} file download failed.  {$desc} will not be updated.\n"), 3, SNORT_RULES_UPD_LOGFILE);
			$update_errors = true;
			return false;
		}
		return true;
	}
	else {
		snort_update_status(gettext(" FAILED!") . "\n");
		snort_update_status(gettext("{$desc} file download failed... server returned error '{$rc}'.") . "\n");
		syslog(LOG_ERR, gettext("[Snort] {$desc} file download failed... server returned error '{$rc}'..."));
		error_log(gettext("\t{$desc} file download failed.  Server returned error {$rc}.\n"), 3, SNORT_RULES_UPD_LOGFILE);
		error_log(gettext("\tThe error text was: {$last_curl_error}\n"), 3, SNORT_RULES_UPD_LOGFILE);
		error_log(gettext("\t{$desc} will not be updated.\n"), 3, SNORT_RULES_UPD_LOGFILE);
		snort_update_status(gettext("{$desc} will not be updated.") . "\n");
		$update_errors = true;
		return false;
	}
}

function snort_untar($mode, $tarFile, $outputFolder = null, $extra = null){
	$cmd = "/usr/bin/tar {$mode} {$tarFile}";
	if($outputFolder != null) $cmd .= " -C {$outputFolder}";
	if($extra != null) $cmd .= " {$extra}";
	exec($cmd, $output, $ret);
	$success = $ret === 0;
	if(!$success) {
		$err_msg = gettext("Failed to extract a rules-update archive. Some snort rules might still be out-of-date. Make sure there is enough free disk space and try again. Tar file:") . $tarFile;
		error_log('\t' . $err_msg . '\n', 3, SNORT_RULES_UPD_LOGFILE);
		syslog(LOG_ERR, '[Snort] ' . $err_msg);
	}
	return $success;
}

function snort_copy($srcFilePathPattern, $destPath){
	$cmd = "/bin/cp {$srcFilePathPattern} {$destPath}";
	exec($cmd, $output, $ret);
	$success = $ret === 0;
	if(!$success) {
		$err_msg = gettext("Failed to copy some files from the rules-update archive. Some snort rules might still be out-of-date. Make sure there is enough free disk space and try again. File(s):") . $srcFilePathPattern;
		error_log('\t' . $err_msg . '\n', 3, SNORT_RULES_UPD_LOGFILE);
		syslog(LOG_ERR, '[Snort] ' . $err_msg);
	}
	return $success;
}


/**********************/
/* Start of main code */
/**********************/

/*  remove any old $tmpfname files */
if (is_dir("{$tmpfname}"))
	rmdir_recursive($tmpfname);

/*  Make sure required snortdirs exsist */
safe_mkdir("{$snortdir}/rules");
safe_mkdir("{$snortdir}/preproc_rules");
safe_mkdir("{$tmpfname}");
safe_mkdir("{$snortlibdir}/snort_dynamicrules");
safe_mkdir("{$snortlogdir}");
safe_mkdir("{$snortiprepdir}");

/* See if we need to automatically clear the Update Log based on 1024K size limit */
if (file_exists(SNORT_RULES_UPD_LOGFILE)) {
	if (1048576 < filesize(SNORT_RULES_UPD_LOGFILE))
		file_put_contents(SNORT_RULES_UPD_LOGFILE, "");
}
else {
	/* Create the file if not already present */
	file_put_contents(SNORT_RULES_UPD_LOGFILE, "");
}

/* Sleep for random number of seconds between 0 and 35 to spread load on rules site */
sleep(random_int(0, 35));

/* Log start time for this rules update */
error_log(gettext("Starting rules update...  Time: " . date("Y-m-d H:i:s") . "\n"), 3, SNORT_RULES_UPD_LOGFILE);
$last_curl_error = "";
$update_errors = false;

/* Save current state (running/not running) for each enabled Snort interface */
$active_interfaces = array();
init_config_arr(array('installedpackages', 'snortglobal', 'rule'));
foreach ($config['installedpackages']['snortglobal']['rule'] as $id => $value) {
	$if_real = get_real_interface($value['interface']);

	/* Skip processing for instances whose underlying physical        */
	/* interface has been removed in pfSense.                         */
	if ($if_real == "") {
		continue;
	}

	if ($value['enable'] = "on" && snort_is_running($value['uuid'])) {
		$active_interfaces[] = $value['interface'];
	}
}

/*  Check for and download any new Snort Subscriber Rules sigs */
if ($snortdownload == 'on') {
	if (snort_check_rule_md5("{$snort_rule_url}{$snort_filename_md5}?oinkcode={$oinkid}", "{$tmpfname}/{$snort_filename_md5}", "Snort Subscriber rules")) {
		/* download snortrules file */
		$file_md5 = trim(file_get_contents("{$tmpfname}/{$snort_filename_md5}"));
		if (!snort_fetch_new_rules("{$snort_rule_url}{$snort_filename}?oinkcode={$oinkid}", "{$tmpfname}/{$snort_filename}", $file_md5, "Snort Subscriber rules"))
			$snortdownload = 'off';
	}
	else
		$snortdownload = 'off';
}

/*  Check for and download any new Snort OpenAppID detectors */
if ($openappid_detectors == 'on') {
	if (snort_check_rule_md5("{$snort_openappid_url}{$snort_openappid_filename_md5}", "{$tmpfname}/{$snort_openappid_filename_md5}","Snort OpenAppID detectors")) {
		$file_md5 = trim(file_get_contents("{$tmpfname}/{$snort_openappid_filename_md5}"));
		file_put_contents("{$tmpfname}/{$snort_openappid_filename_md5}", $file_md5);
		/* download snort-openappid file */
		if (!snort_fetch_new_rules("{$snort_openappid_url}{$snort_openappid_filename}", "{$tmpfname}/{$snort_openappid_filename}", $file_md5, "Snort OpenAppID detectors"))
			$openappid_detectors = 'off';
	}
	else
		$openappid_detectors = 'off';
}
/*  Check for and download any new Snort AppID Open Text Rules */
if ($openappid_rules_detectors == 'on') {
        if (snort_check_rule_md5("{$snort_openappid_rules_url}{$snort_openappid_rules_filename_md5}", "{$tmpfname}/{$snort_openappid_rules_filename_md5}", "Snort AppID Open Text Rules")) {
                $file_md5 = trim(file_get_contents("{$tmpfname}/{$snort_openappid_rules_filename_md5}"));
                file_put_contents("{$tmpfname}/{$snort_openappid_rules_filename_md5}", $file_md5);
                /* download snort-openappid file rules */
                if (!snort_fetch_new_rules("{$snort_openappid_rules_url}{$snort_openappid_rules_filename}", "{$tmpfname}/{$snort_openappid_rules_filename}", $file_md5, "Snort AppID Open Text Rules"))
                        $openappid_rules_detectors = 'off';
        }
        else
                $openappid_rules_detectors = 'off';
}

/*  Check for and download any new Snort GPLv2 Community Rules sigs */
if ($snortcommunityrules == 'on') {
	if (snort_check_rule_md5("{$snort_community_rules_url}{$snort_community_rules_filename_md5}", "{$tmpfname}/{$snort_community_rules_filename_md5}", "Snort GPLv2 Community Rules")) {
		/* download Snort GPLv2 Community Rules file */
		$file_md5 = trim(file_get_contents("{$tmpfname}/{$snort_community_rules_filename_md5}"));
		if (!snort_fetch_new_rules("{$snort_community_rules_url}{$snort_community_rules_filename}", "{$tmpfname}/{$snort_community_rules_filename}", $file_md5, "Snort GPLv2 Community Rules"))
			$snortcommunityrules = 'off';
	}
	else
		$snortcommunityrules = 'off';
}

/*  Check for and download any new Emerging Threats Rules sigs */
if ($emergingthreats == 'on') {
	if (snort_check_rule_md5("{$emergingthreats_url}{$emergingthreats_filename_md5}", "{$tmpfname}/{$emergingthreats_filename_md5}", "{$et_name} rules")) {
		/* download Emerging Threats rules file */
		$file_md5 = trim(file_get_contents("{$tmpfname}/{$emergingthreats_filename_md5}"));
		if (!snort_fetch_new_rules("{$emergingthreats_url}{$emergingthreats_filename}", "{$tmpfname}/{$emergingthreats_filename}", $file_md5, "{$et_name} rules"))
			$emergingthreats = 'off';
	}
	else
		$emergingthreats = 'off';
}

/*  Download any new ABUSE.ch Fedoo Tracker Rules sigs */
if ($feodotracker_rules == 'on') {
	// Grab the MD5 hash of our last successful download if available
	if (file_exists("{$snortdir}/{$feodotracker_rules_filename}.md5")) {
		$old_file_md5 = trim(file_get_contents("{$snortdir}/{$feodotracker_rules_filename}.md5"));
	}
	else {
		$old_file_md5 = "0";
	}

	snort_update_status(gettext("Downloading Feodo Tracker Botnet C2 IP rules file..."));
	error_log(gettext("\tDownloading Feodo Tracker Botnet C2 IP rules file...\n"), 3, SNORT_RULES_UPD_LOGFILE);
	$rc = snort_download_file_url("{$feodotracker_rules_url}{$feodotracker_rules_filename}", "{$tmpfname}/{$feodotracker_rules_filename}");

	// See if the download from the URL was successful
	if ($rc === true) {
		snort_update_status(gettext(" done.") . "\n");
		syslog(LOG_NOTICE, "[Snort] Feodo Tracker Botnet C2 IP rules file update downloaded successfully.");
		error_log(gettext("\tDone downloading rules file.\n"),3, SNORT_RULES_UPD_LOGFILE);

		// See if file has changed from our previously downloaded version
		if ($old_file_md5 == trim(md5_file("{$tmpfname}/{$feodotracker_rules_filename}"))) {
			// File is unchanged from previous download, so no update required
			snort_update_status(gettext("Feodo Tracker Botnet C2 IP rules are up to date.") . "\n");
			syslog(LOG_NOTICE, gettext("[Snort] Feodo Tracker Botnet C2 IP rules are up to date..."));
			error_log(gettext("\tFeodo Tracker Botnet C2 IP rules are up to date.\n"), 3, SNORT_RULES_UPD_LOGFILE);
			$feodotracker_rules = 'off';
		}
		else {
			// Downloaded file is changed, so update our local MD5 hash and extract the new rules
			file_put_contents("{$snortdir}/{$feodotracker_rules_filename}.md5", trim(md5_file("{$tmpfname}/{$feodotracker_rules_filename}")));
			snort_update_status(gettext("Installing Feodo Tracker Botnet C2 IP rules..."));
			error_log(gettext("\tExtracting and installing Feodo Tracker Botnet C2 IP rules...\n"), 3, SNORT_RULES_UPD_LOGFILE);
			if(snort_untar("xzf", "{$tmpfname}/{$feodotracker_rules_filename}", "{$snortdir}/rules/")) {
				snort_update_status(gettext("Feodo Tracker Botnet C2 IP rules were updated.") . "\n");
				syslog(LOG_NOTICE, gettext("[Snort] Feodo Tracker Botnet C2 IP rules were updated..."));
				error_log(gettext("\tFeodo Tracker Botnet C2 IP rules were updated.\n"), 3, SNORT_RULES_UPD_LOGFILE);
			}
		}
	}
	else {
		snort_update_status(gettext("Feodo Tracker Botnet C2 IP rules file download failed!") . "\n");
		syslog(LOG_ERR, gettext("[Snort] ERROR: Feodo Tracker Botnet C2 IP rules file download failed... server returned error '{$rc}'."));
		error_log(gettext("\tERROR: Feodo Tracker Botnet C2 IP rules file download failed.  Remote server returned error {$rc}.\n"), 3, SNORT_RULES_UPD_LOGFILE);
		error_log(gettext("\tThe error text was: {$last_curl_error}\n"), 3, SNORT_RULES_UPD_LOGFILE);
		error_log(gettext("\tFeodo Tracker Botnet C2 IP rules will not be updated.\n"), 3, SNORT_RULES_UPD_LOGFILE);
		$update_errors = true;
		$feodotracker_rules = 'off';
	}
}

/* Untar Snort rules file to tmp and install the rules */
if ($snortdownload == 'on') {
	if (file_exists("{$tmpfname}/{$snort_filename}")) {
		snort_update_status(gettext("Installing Snort Subscriber ruleset..."));

		/* Determine the platform FreeBSD major version so we can unpack  */
		/* the corresponding SO rules. Default to FreeBSD-13 for now.     */
		$freebsd_version_so = 'FreeBSD-13';

# Leave the automated OS version determination commented-out until pfSense moves off of FreeBSD-12
#		$major_os_ver = strcspn(php_uname('r'), ".-");
#		if ($major_os_ver > 0) {
#			$freebsd_version_so = 'FreeBSD-' . substr(php_uname('r'), 0, $major_os_ver);
#		}

		/* Remove the old Snort rules files */
		$vrt_prefix = VRT_FILE_PREFIX;
		unlink_if_exists("{$snortdir}/rules/{$vrt_prefix}*.rules");

		error_log(gettext("\tExtracting and installing Snort Subscriber Ruleset...\n"), 3, SNORT_RULES_UPD_LOGFILE);

		/* extract snort.org rules and add VRT_FILE_PREFIX prefix to all snort.org files */
		safe_mkdir("{$tmpfname}/snortrules");
		if(snort_untar("xzf", "{$tmpfname}/{$snort_filename}", "{$tmpfname}/snortrules", "rules/")) {
			$files = glob("{$tmpfname}/snortrules/rules/*.rules");
			foreach ($files as $file) {
				$newfile = basename($file);
				@copy($file, "{$snortdir}/rules/" . VRT_FILE_PREFIX . "{$newfile}");
			}
			/* Extract any IP lists */
			$files = glob("{$tmpfname}/snortrules/rules/*.txt");
			foreach ($files as $file) {
				$newfile = basename($file);
				@copy($file, "{$snortdir}/rules/{$newfile}");
			}
		}
		rmdir_recursive("{$tmpfname}/snortrules");

		/* Extract the Snort preprocessor rules */
		if(snort_untar("xzf", "{$tmpfname}/{$snort_filename}", "{$tmpfname}", "preproc_rules/")) {
			$files = glob("{$tmpfname}/preproc_rules/*.rules");
			foreach ($files as $file) {
				$newfile = basename($file);
				@copy($file, "{$snortdir}/preproc_rules/{$newfile}");
			}
		}
		rmdir_recursive("{$tmpfname}/preproc_rules");

		/* extract SO rules */
		$snort_arch = php_uname("m");
		$nosorules = true;

		/****************************************************************************/
		/* Snort SO rules only exist for Intel/AMD 64-bit architecture on FreeBSD,  */
		/* so check if our platform is compatible, else set flag for "no SO rules". */
		/****************************************************************************/
		if ($snort_arch == 'amd64') {
			error_log(gettext("\tUsing Snort Subscriber precompiled SO rules for {$freebsd_version_so} ...\n"), 3, SNORT_RULES_UPD_LOGFILE);
			if(snort_untar("xzf", "{$tmpfname}/{$snort_filename}", "{$tmpfname}", "so_rules/precompiled/{$freebsd_version_so}/x86_64/{$snort_version}/")) {
				snort_copy("{$tmpfname}/so_rules/precompiled/{$freebsd_version_so}/x86_64/{$snort_version}/*.so", "{$snortlibdir}/snort_dynamicrules/");
				$nosorules = false;
			}
		}
		rmdir_recursive("{$tmpfname}/so_rules/");

		/* If we extracted the SO rule library objects, then also extract the rules stubs */
		if ($nosorules == false) {
			/* extract Shared Object stub rules, rename and copy to the rules folder. */
			if(snort_untar("xzf", "{$tmpfname}/{$snort_filename}", "{$tmpfname}", "--exclude precompiled/ --exclude src/ so_rules/")) {
				$files = glob("{$tmpfname}/so_rules/*.rules");
				foreach ($files as $file) {
					$newfile = basename($file, ".rules");
					@copy($file, "{$snortdir}/rules/" . VRT_FILE_PREFIX . "{$newfile}.so.rules");
				}
			}
			rmdir_recursive("{$tmpfname}/so_rules/");
		}

		/* extract base etc files */
		if(snort_untar("xzf", "{$tmpfname}/{$snort_filename}", "{$tmpfname}", "etc/")) {
			foreach (array("classification.config", "reference.config", "gen-msg.map", "unicode.map") as $file) {
				if (file_exists("{$tmpfname}/etc/{$file}"))
					@copy("{$tmpfname}/etc/{$file}", "{$tmpfname}/VRT_{$file}");
			}
		}
		rmdir_recursive("{$tmpfname}/etc");

		if (file_exists("{$tmpfname}/{$snort_filename_md5}")) {
			snort_update_status(gettext("Copying md5 signature to snort directory..."));
			@copy("{$tmpfname}/{$snort_filename_md5}", "{$snortdir}/{$snort_filename_md5}");
		}
		snort_update_status(gettext(" done.") . "\n");
		error_log(gettext("\tInstallation of Snort Subscriber rules completed.\n"), 3, SNORT_RULES_UPD_LOGFILE);
	}
}

/* Untar Snort OpenAppID detectors file to SNORT_APPID_ODP_PATH */
if ($openappid_detectors == 'on') {
	// If we have a valid downloaded file, then first cleanup the old directory
	if (file_exists("{$tmpfname}/{$snort_openappid_filename}")) {
		snort_update_status(gettext("Installing Snort OpenAppID detectors..."));
		$snort_openappid_path = SNORT_APPID_ODP_PATH;
		rmdir_recursive("{$snort_openappid_path}odp");
		error_log(gettext("\tExtracting and installing Snort OpenAppID detectors...\n"), 3, SNORT_RULES_UPD_LOGFILE);
		safe_mkdir(SNORT_APPID_ODP_PATH);
		if(snort_untar("oxzf", "{$tmpfname}/{$snort_openappid_filename}", "{$snort_openappid_path}")) {
			if (file_exists("{$tmpfname}/{$snort_openappid_filename_md5}")) {
				snort_update_status(gettext("Copying md5 signature to snort directory..."));
				@copy("{$tmpfname}/{$snort_openappid_filename_md5}", "{$snortdir}/{$snort_openappid_filename_md5}");
			}
			if (!is_dir("{$snort_openappid_path}custom")) {
				safe_mkdir("{$snort_openappid_path}custom");
				safe_mkdir("{$snort_openappid_path}custom/lua");
				touch("{$snort_openappid_path}custom/userappid.conf");
			}
			snort_update_status(gettext(" done.") . "\n");
			unlink_if_exists("{$tmpfname}/{$snort_openappid_filename}");
			error_log(gettext("\tInstallation of Snort OpenAppID detectors completed.\n"), 3, SNORT_RULES_UPD_LOGFILE);
		}
	}
}
/* Untar Snort AppID Open Text Rules file to SNORT_APPID_RULES_PATH */
if ($openappid_rules_detectors == 'on') {
        // If we have a valid downloaded file, then first cleanup the old directory
        if (file_exists("{$tmpfname}/{$snort_openappid_rules_filename}")) {
			snort_update_status(gettext("Installing Snort OpenAppID Rules..."));
			$snort_openappid_rules_path = SNORT_APPID_RULES_PATH;
			error_log(gettext("\tExtracting and installing Snort AppID Open Text Rules...\n"), 3, SNORT_RULES_UPD_LOGFILE);
			if(snort_untar("oxzf", "{$tmpfname}/{$snort_openappid_rules_filename}", "{$snort_openappid_rules_path}")) {
				if (file_exists("{$tmpfname}/{$snort_openappid_rules_filename_md5}")) {
					snort_update_status(gettext("Copying md5 signature to snort directory..."));
					@copy("{$tmpfname}/{$snort_openappid_rules_filename_md5}", "{$snortdir}/{$snort_openappid_rules_filename_md5}");
				}
				snort_update_status(gettext(" done.") . "\n");
				unlink_if_exists("{$tmpfname}/{$snort_openappid_rules_filename}");
				error_log(gettext("\tInstallation of Snort AppID Open Text Rules completed.\n"), 3, SNORT_RULES_UPD_LOGFILE);
			}
        }
}


/* Untar Snort GPLv2 Community rules file to tmp and install the rules */
if ($snortcommunityrules == 'on') {
	safe_mkdir("{$tmpfname}/community");
	if (file_exists("{$tmpfname}/{$snort_community_rules_filename}")) {
		snort_update_status(gettext("Installing Snort GPLv2 Community Rules..."));
		error_log(gettext("\tExtracting and installing Snort GPLv2 Community Rules...\n"), 3, SNORT_RULES_UPD_LOGFILE);
		if(snort_untar("xzf", "{$tmpfname}/{$snort_community_rules_filename}", "{$tmpfname}/community/")) {
			$files = glob("{$tmpfname}/community/community-rules/*.rules");
			foreach ($files as $file) {
				$newfile = basename($file);
				@copy($file, "{$snortdir}/rules/" . GPL_FILE_PREFIX . "{$newfile}");
			}
			/* Base etc files for Snort GPLv2 Community rules */
			foreach (array("classification.config", "reference.config", "gen-msg.map", "unicode.map") as $file) {
				if (file_exists("{$tmpfname}/community/community-rules/{$file}"))
					@copy("{$tmpfname}/community/community-rules/{$file}", "{$tmpfname}/" . GPL_FILE_PREFIX . "{$file}");
			}
			/* Copy snort community md5 sig to snort dir */
			if (file_exists("{$tmpfname}/{$snort_community_rules_filename_md5}")) {
				@copy("{$tmpfname}/{$snort_community_rules_filename_md5}", "{$snortdir}/{$snort_community_rules_filename_md5}");
			}
			snort_update_status(gettext(" done.") . "\n");
			error_log(gettext("\tInstallation of Snort GPLv2 Community Rules completed.\n"), 3, SNORT_RULES_UPD_LOGFILE);
		}
		rmdir_recursive("{$tmpfname}/community/");
	}
}

/* Untar Emerging Threats rules file to tmp and install the rules */
if ($emergingthreats == 'on') {
	safe_mkdir("{$tmpfname}/emerging");
	if (file_exists("{$tmpfname}/{$emergingthreats_filename}")) {
		snort_update_status(gettext("Installing {$et_name} rules..."));
		error_log(gettext("\tExtracting and installing {$et_name} rules...\n"), 3, SNORT_RULES_UPD_LOGFILE);
		if(snort_untar("xzf", "{$tmpfname}/{$emergingthreats_filename}", "{$tmpfname}/emerging", "rules/")) {
			/* Remove the old Emerging Threats rules files */
			$eto_prefix = ET_OPEN_FILE_PREFIX;
			$etpro_prefix = ET_PRO_FILE_PREFIX;
			unlink_if_exists("{$snortdir}/rules/{$eto_prefix}*.rules");
			unlink_if_exists("{$snortdir}/rules/{$etpro_prefix}*.rules");
			unlink_if_exists("{$snortdir}/rules/{$eto_prefix}*ips.txt");
			unlink_if_exists("{$snortdir}/rules/{$etpro_prefix}*ips.txt");

			$files = glob("{$tmpfname}/emerging/rules/*.rules");
			foreach ($files as $file) {
				$newfile = basename($file);
				if ($etpro == "on")
					@copy($file, "{$snortdir}/rules/" . ET_PRO_FILE_PREFIX . "{$newfile}");
				else
					@copy($file, "{$snortdir}/rules/{$newfile}");
			}
			/* IP lists for Emerging Threats rules */
			$files = glob("{$tmpfname}/emerging/rules/*ips.txt");
			foreach ($files as $file) {
				$newfile = basename($file);
				if ($etpro == "on") {
					@copy($file, SNORT_IPREP_PATH . ET_PRO_FILE_PREFIX . "{$newfile}");
					@copy($file, "{$snortdir}/rules/" . ET_PRO_FILE_PREFIX . "{$newfile}");
				}
				else {
					@copy($file, SNORT_IPREP_PATH . ET_OPEN_FILE_PREFIX . "{$newfile}");
					@copy($file, "{$snortdir}/rules/" . ET_OPEN_FILE_PREFIX . "{$newfile}");
				}
			}
			/* Base etc files for Emerging Threats rules */
			foreach (array("classification.config", "reference.config", "gen-msg.map", "unicode.map") as $file) {
				if (file_exists("{$tmpfname}/emerging/rules/{$file}"))
					@copy("{$tmpfname}/emerging/rules/{$file}", "{$tmpfname}/ET_{$file}");
			}

			/* Copy emergingthreats md5 sig to snort dir */
			if (file_exists("{$tmpfname}/{$emergingthreats_filename_md5}")) {
				snort_update_status(gettext("Copying md5 signature to snort directory..."));
				@copy("{$tmpfname}/{$emergingthreats_filename_md5}", "{$snortdir}/{$emergingthreats_filename_md5}");
			}
			snort_update_status(gettext(" done.") . "\n");
			error_log(gettext("\tInstallation of {$et_name} rules completed.\n"), 3, SNORT_RULES_UPD_LOGFILE);
		}
		rmdir_recursive("{$tmpfname}/emerging/");
	}
}

// If removing deprecated rules categories, then do it
if ($config['installedpackages']['snortglobal']['hide_deprecated_rules'] == "on") {
	syslog(LOG_NOTICE, gettext("[Snort] Hide Deprecated Rules is enabled.  Removing obsoleted rules categories."));
	snort_remove_dead_rules();
}

function snort_apply_customizations($snortcfg, $if_real) {

	global $vrt_enabled, $rebuild_rules;
	$snortdir = SNORTDIR;

	/* Update the Preprocessor rules from the master configuration for the interface if Snort */
	/* Subscriber rules are in use and the interface's preprocessor rules are not protected.  */
	if ($vrt_enabled == 'on' && ($snortcfg['protect_preproc_rules'] != 'on' || $g['snort_postinstall'])) {
		$preproc_files = glob("{$snortdir}/preproc_rules/*.rules");
		foreach ($preproc_files as $file) {
			$newfile = basename($file);
			@copy($file, "{$snortdir}/snort_{$snortcfg['uuid']}_{$if_real}/preproc_rules/{$newfile}");
		}
	}

	if ($rebuild_rules == true)
		snort_prepare_rule_files($snortcfg, "{$snortdir}/snort_{$snortcfg['uuid']}_{$if_real}");

	/* Copy the master config and map files to the interface directory */
	@copy("{$snortdir}/classification.config", "{$snortdir}/snort_{$snortcfg['uuid']}_{$if_real}/classification.config");
	@copy("{$snortdir}/reference.config", "{$snortdir}/snort_{$snortcfg['uuid']}_{$if_real}/reference.config");
	@copy("{$snortdir}/gen-msg.map", "{$snortdir}/snort_{$snortcfg['uuid']}_{$if_real}/gen-msg.map");
	@copy("{$snortdir}/unicode.map", "{$snortdir}/snort_{$snortcfg['uuid']}_{$if_real}/unicode.map");
}

if ($snortdownload == 'on' || $emergingthreats == 'on' || $snortcommunityrules == 'on' || $openappid_rules_detectors == 'on' || $feodotracker_rules == 'on') {

	error_log(gettext("\tCopying new config and map files...\n"), 3, SNORT_RULES_UPD_LOGFILE);

	/******************************************************************/
	/* Build the classification.config and reference.config files     */
	/* using the ones from all the downloaded rules plus the default  */
	/* files installed with Snort.                                    */
	/******************************************************************/
	$cfgs = glob("{$tmpfname}/*reference.config");
	$cfgs[] = "{$snortdir}/reference.config";
	snort_merge_reference_configs($cfgs, "{$snortdir}/reference.config");
	$cfgs = glob("{$tmpfname}/*classification.config");
	$cfgs[] = "{$snortdir}/classification.config";
	snort_merge_classification_configs($cfgs, "{$snortdir}/classification.config");

	/**********************************************************************/
	/* Determine which map files set to use for the master copy.  If the  */
	/* Snort Subscriber rules are not enabled, then use Emerging Threats  */
	/* or Snort Community Rules, in that order, if either is enabled.     */
	/**********************************************************************/
	if ($snortdownload == 'on' || $vrt_enabled == 'on')
		$prefix = "VRT_";
	elseif ($emergingthreats == 'on')
		$prefix = "ET_";
	elseif ($snortcommunityrules == 'on')
		$prefix = GPL_FILE_PREFIX;
	if (file_exists("{$tmpfname}/{$prefix}unicode.map"))
		@copy("{$tmpfname}/{$prefix}unicode.map", "{$snortdir}/unicode.map");
	if (file_exists("{$tmpfname}/{$prefix}gen-msg.map"))
		@copy("{$tmpfname}/{$prefix}gen-msg.map", "{$snortdir}/gen-msg.map");

	/* Start the rules rebuild proccess for each configured interface */
	if (is_array($config['installedpackages']['snortglobal']['rule']) &&
	    !empty($config['installedpackages']['snortglobal']['rule'])) {

		/* Set the flag to force rule rebuilds since we downloaded new rules,    */
		/* except when in post-install mode.  Post-install does its own rebuild. */
		if ($g['snort_postinstall'])
			$rebuild_rules = false;
		else
			$rebuild_rules = true;

		/* Create configuration for each active Snort interface */
		foreach ($config['installedpackages']['snortglobal']['rule'] as $id => $value) {
			$if_real = get_real_interface($value['interface']);

			/* Skip processing for instances whose underlying physical        */
			/* interface has been removed in pfSense.                         */
			if ($if_real == "") {
				continue;
			}

			$tmp = "Updating rules configuration for: " . convert_friendly_interface_to_friendly_descr($value['interface']) . " ...";
			snort_update_status(gettext($tmp));

			// Make sure the interface subdirectory and required sub-directories exists.
			// We need to re-create them during a pkg reinstall for the intial rules set
			// download and configuration done as part of restoring saved settings.
			if (!is_dir("{$snortdir}/snort_{$value['uuid']}_{$if_real}"))
				safe_mkdir("{$snortdir}/snort_{$value['uuid']}_{$if_real}");
			if (!is_dir("{$snortdir}/snort_{$value['uuid']}_{$if_real}/rules"))
				safe_mkdir("{$snortdir}/snort_{$value['uuid']}_{$if_real}/rules");
			if (!is_dir("{$snortdir}/snort_{$value['uuid']}_{$if_real}/preproc_rules"))
				safe_mkdir("{$snortdir}/snort_{$value['uuid']}_{$if_real}/preproc_rules");
			if (!is_dir("{$snortdir}/snort_{$value['uuid']}_{$if_real}/snort_dynamicpreprocessor"))
				safe_mkdir("{$snortdir}/snort_{$value['uuid']}_{$if_real}/snort_dynamicpreprocessor");

			snort_apply_customizations($value, $if_real);

			/* Log a message in Update Log if protecting customized preprocessor rules. */
			$tmp = "\t" . $tmp . "\n";
			if ($value['protect_preproc_rules'] == 'on') {
				$tmp .= gettext("\tPreprocessor text rules flagged as protected and not updated for ");
				$tmp .= convert_friendly_interface_to_friendly_descr($value['interface']) . "...\n";
			}
			error_log($tmp, 3, SNORT_RULES_UPD_LOGFILE);
			snort_update_status(gettext(" done.") . "\n");

			/* Restart Snort on the interface if it was previously running, and we are not in Post Install mode */
			if (!$g['snort_postinstall'] && in_array($value['interface'], $active_interfaces)) {
				snort_update_status(gettext('Restarting Snort on ' . convert_friendly_interface_to_friendly_descr($value['interface']) . ' to activate the new set of rules...'));
				error_log(gettext("\tRestarting Snort on " . convert_friendly_interface_to_friendly_descr($value['interface']) . " to activate the new set of rules...\n"), 3, SNORT_RULES_UPD_LOGFILE);
				snort_start($value, $if_real);
				snort_update_status(gettext(" done.") . "\n");
       				syslog(LOG_NOTICE, gettext("[Snort] Snort has restarted on " . convert_friendly_interface_to_friendly_descr($value['interface']) . " with your new set of rules..."));
				error_log(gettext("\tSnort has restarted on " . convert_friendly_interface_to_friendly_descr($value['interface']) . " with your new set of rules.\n"), 3, SNORT_RULES_UPD_LOGFILE);
			}
		}
	}
	else {
		snort_update_status(gettext("Warning:  No interfaces configured for Snort were found...") . "\n");
		error_log(gettext("\tWarning:  No interfaces configured for Snort were found...\n"), 3, SNORT_RULES_UPD_LOGFILE);
	}

	/* Clear the rebuild rules flag.  */
	$rebuild_rules = false;
}
elseif ($openappid_detectors == 'on') {
	/**************************************************************************************/
	/* Only updated OpenAppID detectors, so do not need to rebuild all interface rules.   */
	/* Restart snort if running, and not in post-install, so as to pick up the detectors. */
	/**************************************************************************************/
	foreach ($config['installedpackages']['snortglobal']['rule'] as $id => $value) {
		$if_real = get_real_interface($value['interface']);

		/* Skip processing for instances whose underlying physical        */
		/* interface has been removed in pfSense.                         */
		if ($if_real == "") {
			continue;
		}

		if (!$g['snort_postinstall'] && in_array($value['interface'], $active_interfaces)) {
			snort_update_status(gettext('Restarting Snort on ' . convert_friendly_interface_to_friendly_descr($value['interface']) . ' to activate the new set of OpenAppID detectors...'));
			error_log(gettext("\tRestarting Snort on " . convert_friendly_interface_to_friendly_descr($value['interface']) . " to activate the new set of OpenAppID detectors...\n"), 3, SNORT_RULES_UPD_LOGFILE);
			snort_start($value, $if_real);
			snort_update_status(gettext(" done.") . "\n");
			syslog(LOG_NOTICE, gettext("[Snort] Snort has restarted on " . convert_friendly_interface_to_friendly_descr($value['interface']) . " with your new set of OpenAppID detectors..."));
			error_log(gettext("\tSnort has restarted on " . convert_friendly_interface_to_friendly_descr($value['interface']) . " with your new set of OpenAppID detectors.\n"), 3, SNORT_RULES_UPD_LOGFILE);
		}
	}
}

/*  remove $tmpfname files */
if (is_dir("{$tmpfname}")) {
	snort_update_status(gettext("Cleaning up temp dirs and files..."));
	rmdir_recursive($tmpfname);
	snort_update_status(gettext(" done.") . "\n");
}

snort_update_status(gettext("The Rules update has finished.") . "\n");
syslog(LOG_NOTICE, gettext("[Snort] The Rules update has finished."));
error_log(gettext("The Rules update has finished.  Time: " . date("Y-m-d H:i:s"). "\n\n"), 3, SNORT_RULES_UPD_LOGFILE);

/* Save this update status to the rulesupd_status file */
$status = time() . '|';
if ($update_errors) {
	$status .= gettext("failed");
}
else {
	$status .= gettext("success");
}
@file_put_contents(SNORTDIR . "/rulesupd_status", $status);
?>
