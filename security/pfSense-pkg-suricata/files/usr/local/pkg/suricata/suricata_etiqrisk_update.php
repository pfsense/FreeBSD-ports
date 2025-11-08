<?php
/*
 * suricata_etiqrisk_update.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2023 Bill Meeks
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
require_once("functions.inc");
require_once("notices.inc");
require_once("/usr/local/pkg/suricata/suricata.inc");
require("/usr/local/pkg/suricata/suricata_defs.inc");

global $notify_message;

function suricata_check_iprep_md5($filename) {

	/**********************************************************/
	/* This function attempts to download the MD5 hash for    */
	/* the passed file and compare its contents to the        */
	/* currently stored hash file to see if a new file has    */
	/* been posted.                                           */
	/*                                                        */
	/* On Entry: $filename = IPREP file to check ('md5sum'    */
	/*                       is auto-appended to the supplied */
	/*                       filename.)                       */
	/*                                                        */
	/*  Returns: TRUE if new rule file download required.     */
	/*           FALSE if rule download not required or an    */
	/*           error occurred.                              */
	/**********************************************************/

	global $iqRisk_tmppath, $iprep_path, $notify_message;
	$new_md5 = $old_md5 = "";
	$et_iqrisk_url = str_replace("_xxx_", config_get_path('installedpackages/suricata/config/0/iqrisk_code'), ET_IQRISK_DNLD_URL);

	if (download_file("{$et_iqrisk_url}{$filename}.md5sum", "{$iqRisk_tmppath}{$filename}.md5", true, 10, 30) == true) {
		if (file_exists("{$iqRisk_tmppath}{$filename}.md5"))
			$new_md5 = trim(file_get_contents("{$iqRisk_tmppath}{$filename}.md5"));
		if (file_exists("{$iprep_path}{$filename}.md5"))
			$old_md5 = trim(file_get_contents("{$iprep_path}{$filename}.md5"));
		if ($new_md5 != $old_md5)
			return TRUE;
		else
			logger(LOG_NOTICE, localize_text("IPREP file '%s' is up to date.", $filename), LOG_PREFIX_PKG_SURICATA);
			$notify_message .= gettext("- IPREP file '{$filename}' is up to date.");
	}
	else
		logger(LOG_ERR, localize_text("An error occurred downloading %s for IPREP.  Update of %s file will be skipped.", "{$et_iqrisk_url}{$filename}.md5sum", $filename), LOG_PREFIX_PKG_SURICATA);
		$notify_message .= gettext("- An error occurred downloading {$et_iqrisk_url}{$filename}.md5sum for IPREP.  Update of {$filename} file will be skipped.\n");

	return FALSE;
}

/**********************************************************************
 * Start of main code                                                 *
 **********************************************************************/
global $g;
$iprep_path = SURICATA_IPREP_PATH;
$iqRisk_tmppath = "{$g['tmp_path']}/IQRisk/";
$success = FALSE;

// If auto-updates of ET IQRisk are disabled, then exit
if (config_get_path('installedpackages/suricata/config/0/et_iqrisk_enable') == "off")
	return(0);
else
	logger(LOG_NOTICE, localize_text("Updating the Emerging Threats IQRisk IP List..."), LOG_PREFIX_PKG_SURICATA);
	$notify_message = gettext("Suricata Emerging Threats IQRisk IP List update started: " . date("Y-m-d H:i:s") . "\n");

// Construct the download URL using the saved ET IQRisk Subscriber Code
if (!empty(config_get_path('installedpackages/suricata/config/0/iqrisk_code'))) {
	$et_iqrisk_url = str_replace("_xxx_", config_get_path('installedpackages/suricata/config/0/iqrisk_code'), ET_IQRISK_DNLD_URL);
}
else {
	logger(LOG_ALERT, localize_text("No IQRisk subscriber code found!  Aborting scheduled update of Emerging Threats IQRisk IP List."), LOG_PREFIX_PKG_SURICATA);
	return(0);
}

// Download the IP List files to a temporary location
safe_mkdir("$iqRisk_tmppath");

// Test the posted MD5 checksum file against our local copy
// to see if an update has been posted for 'categories.txt'.
if (suricata_check_iprep_md5("categories.txt")) {
	logger(LOG_NOTICE, localize_text("An updated IPREP 'categories.txt' file is available...downloading new file."), LOG_PREFIX_PKG_SURICATA);
	if (download_file("{$et_iqrisk_url}categories.txt", "{$iqRisk_tmppath}categories.txt") != true) {
		logger(LOG_ERR, localize_text("An error occurred downloading the '%s' file for IQRisk.", 'categories.txt'), LOG_PREFIX_PKG_SURICATA);
		$notify_message .= gettext("- An error occurred downloading the 'categories.txt' file for IQRisk.\n");
	} else {
		// If the files downloaded successfully, unpack them and store
		// the list files in the SURICATA_IPREP_PATH directory.
		if (file_exists("{$iqRisk_tmppath}categories.txt") && file_exists("{$iqRisk_tmppath}categories.txt.md5")) {
			$new_md5 = trim(file_get_contents("{$iqRisk_tmppath}categories.txt.md5"));
			if ($new_md5 == md5_file("{$iqRisk_tmppath}categories.txt")) {
				@rename("{$iqRisk_tmppath}categories.txt", "{$iprep_path}categories.txt");
				@rename("{$iqRisk_tmppath}categories.txt.md5", "{$iprep_path}categories.txt.md5");
				$success = TRUE;
				logger(LOG_NOTICE, localize_text("Successfully updated IPREP file '%s'.", 'categories.txt'), LOG_PREFIX_PKG_SURICATA);
				$notify_message .= gettext("- Successfully updated IPREP file 'categories.txt'.\n");
			}
			else
				logger(LOG_ALERT, localize_text("MD5 integrity check of downloaded '%s' file failed!  Skipping update of this IPREP file.", 'categories.txt'), LOG_PREFIX_PKG_SURICATA);
				$notify_message .= gettext("- MD5 integrity check of downloaded 'categories.txt' file failed!  Skipping update of this IPREP file.\n");
		}
	}
}

// Test the posted MD5 checksum file against our local copy
// to see if an update has been posted for 'iprepdata.txt.gz'.
if (suricata_check_iprep_md5("iprepdata.txt.gz")) {
	logger(LOG_NOTICE, localize_text("An updated IPREP '%s' file is available...downloading new file.", 'iprepdata.txt'), LOG_PREFIX_PKG_SURICATA);
	if (download_file("{$et_iqrisk_url}iprepdata.txt.gz", "{$iqRisk_tmppath}iprepdata.txt.gz") != true) {
		logger(LOG_ERR, localize_text("An error occurred downloading the '%s' file for IQRisk.", 'iprepdata.txt.gz'), LOG_PREFIX_PKG_SURICATA);
		$notify_message .= gettext("- An error occurred downloading the 'iprepdata.txt.gz' file for IQRisk.\n");
	} else {
		// If the files downloaded successfully, unpack them and store
		// the list files in the SURICATA_IPREP_PATH directory.
		if (file_exists("{$iqRisk_tmppath}iprepdata.txt.gz") && file_exists("{$iqRisk_tmppath}iprepdata.txt.gz.md5")) {
			$new_md5 = trim(file_get_contents("{$iqRisk_tmppath}iprepdata.txt.gz.md5"));
			if ($new_md5 == md5_file("{$iqRisk_tmppath}iprepdata.txt.gz")) {
				mwexec("/usr/bin/gunzip -f {$iqRisk_tmppath}iprepdata.txt.gz");
				@rename("{$iqRisk_tmppath}iprepdata.txt", "{$iprep_path}iprepdata.txt");
				@rename("{$iqRisk_tmppath}iprepdata.txt.gz.md5", "{$iprep_path}iprepdata.txt.gz.md5");
				$success = TRUE;
				logger(LOG_NOTICE, localize_text("Successfully updated IPREP file '%s'.", 'iprepdata.txt'), LOG_PREFIX_PKG_SURICATA);
				$notify_message .= gettext("- Successfully updated IPREP file 'iprepdata.txt'.\n");
			}
			else
				logger(LOG_ALERT, localize_text("MD5 integrity check of downloaded '%s' file failed!  Skipping update of this IPREP file.", 'iprepdata.txt.gz'), LOG_PREFIX_PKG_SURICATA);
				$notify_message .= gettext("- MD5 integrity check of downloaded 'iprepdata.txt.gz' file failed!  Skipping update of this IPREP file.\n");
		}
	}
}

// Cleanup the tmp directory path
rmdir_recursive("$iqRisk_tmppath");

logger(LOG_NOTICE, localize_text("Emerging Threats IQRisk IP List update finished."), LOG_PREFIX_PKG_SURICATA);
$notify_message .= gettext("Suricata Emerging Threats IQRisk IP List update finished: " . date("Y-m-d H:i:s") . "\n");

// If successful, signal any running Suricata process to live reload the rules and IP lists
if ($success == TRUE && is_process_running("suricata")) {
	foreach (config_get_path('installedpackages/suricata/rule', []) as $value) {
		if ($value['enable_iprep'] == "on") {
			suricata_reload_config($value);
			sleep(2);
		}
	}
}

if (config_get_path('installedpackages/suricata/config/0/update_notify') == 'on') {
	notify_all_remote($notify_message);
}
return true;
?>
