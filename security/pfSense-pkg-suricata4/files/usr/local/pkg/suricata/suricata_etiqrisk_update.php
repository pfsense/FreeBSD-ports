<?php
/*
 * suricata_etiqrisk_update.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 * Copyright (C) 2019 Bill Meeks
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
require_once("/usr/local/pkg/suricata/suricata.inc");
require("/usr/local/pkg/suricata/suricata_defs.inc");

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

	global $config, $iqRisk_tmppath, $iprep_path;
	$new_md5 = $old_md5 = "";
	$et_iqrisk_url = str_replace("_xxx_", $config['installedpackages']['suricata']['config'][0]['iqrisk_code'], ET_IQRISK_DNLD_URL);

	if (download_file("{$et_iqrisk_url}{$filename}.md5sum", "{$iqRisk_tmppath}{$filename}.md5", true, 10, 30) == true) {
		if (file_exists("{$iqRisk_tmppath}{$filename}.md5"))
			$new_md5 = trim(file_get_contents("{$iqRisk_tmppath}{$filename}.md5"));
		if (file_exists("{$iprep_path}{$filename}.md5"))
			$old_md5 = trim(file_get_contents("{$iprep_path}{$filename}.md5"));
		if ($new_md5 != $old_md5)
			return TRUE;
		else
			syslog(LOG_NOTICE, gettext("[Suricata] IPREP file '{$filename}' is up to date."));
	}
	else
		syslog(LOG_ERR, gettext("[Suricata] ERROR: An error occurred downloading {$et_iqrisk_url}{$filename}.md5sum for IPREP.  Update of {$filename} file will be skipped."));

	return FALSE;
}

/**********************************************************************
 * Start of main code                                                 *
 **********************************************************************/
global $g, $config;
$iprep_path = SURICATA_IPREP_PATH;
$iqRisk_tmppath = "{$g['tmp_path']}/IQRisk/";
$success = FALSE;

if (!is_array($config['installedpackages']['suricata']))
	$config['installedpackages']['suricata'] = array();
if (!is_array($config['installedpackages']['suricata']['config']))
	$config['installedpackages']['suricata']['config'] = array();
if (!is_array($config['installedpackages']['suricata']['config'][0]))
	$config['installedpackages']['suricata']['config'][0] = array();

// If auto-updates of ET IQRisk are disabled, then exit
if ($config['installedpackages']['suricata']['config'][0]['et_iqrisk_enable'] == "off")
	return(0);
else
	syslog(LOG_NOTICE, gettext("[Suricata] Updating the Emerging Threats IQRisk IP List..."));

// Construct the download URL using the saved ET IQRisk Subscriber Code
if (!empty($config['installedpackages']['suricata']['config'][0]['iqrisk_code'])) {
	$et_iqrisk_url = str_replace("_xxx_", $config['installedpackages']['suricata']['config'][0]['iqrisk_code'], ET_IQRISK_DNLD_URL);
}
else {
	syslog(gettext(LOG_ALERT, "[Suricata] ALERT: No IQRisk subscriber code found!  Aborting scheduled update of Emerging Threats IQRisk IP List."));
	return(0);
}

// Download the IP List files to a temporary location
safe_mkdir("$iqRisk_tmppath");

// Test the posted MD5 checksum file against our local copy
// to see if an update has been posted for 'categories.txt'.
if (suricata_check_iprep_md5("categories.txt")) {
	syslog(LOG_NOTICE, gettext("[Suricata] An updated IPREP 'categories.txt' file is available...downloading new file."));
	if (download_file("{$et_iqrisk_url}categories.txt", "{$iqRisk_tmppath}categories.txt") != true)
		syslog(LOG_ERR, gettext("[Suricata] ERROR: An error occurred downloading the 'categories.txt' file for IQRisk."));
	else {
		// If the files downloaded successfully, unpack them and store
		// the list files in the SURICATA_IPREP_PATH directory.
		if (file_exists("{$iqRisk_tmppath}categories.txt") && file_exists("{$iqRisk_tmppath}categories.txt.md5")) {
			$new_md5 = trim(file_get_contents("{$iqRisk_tmppath}categories.txt.md5"));
			if ($new_md5 == md5_file("{$iqRisk_tmppath}categories.txt")) {
				@rename("{$iqRisk_tmppath}categories.txt", "{$iprep_path}categories.txt");
				@rename("{$iqRisk_tmppath}categories.txt.md5", "{$iprep_path}categories.txt.md5");
				$success = TRUE;
				syslog(LOG_NOTICE, gettext("[Suricata] Successfully updated IPREP file 'categories.txt'."));
			}
			else
				syslog(gettext(LOG_ALERT, "[Suricata] ALERT: MD5 integrity check of downloaded 'categories.txt' file failed!  Skipping update of this IPREP file."));
		}
	}
}

// Test the posted MD5 checksum file against our local copy
// to see if an update has been posted for 'iprepdata.txt.gz'.
if (suricata_check_iprep_md5("iprepdata.txt.gz")) {
	syslog(LOG_NOTICE, gettext("[Suricata] An updated IPREP 'iprepdata.txt' file is available...downloading new file."));
	if (download_file("{$et_iqrisk_url}iprepdata.txt.gz", "{$iqRisk_tmppath}iprepdata.txt.gz") != true)
		syslog(LOG_ERR, gettext("[Suricata] ERROR: An error occurred downloading the 'iprepdata.txt.gz' file for IQRisk."));
	else {
		// If the files downloaded successfully, unpack them and store
		// the list files in the SURICATA_IPREP_PATH directory.
		if (file_exists("{$iqRisk_tmppath}iprepdata.txt.gz") && file_exists("{$iqRisk_tmppath}iprepdata.txt.gz.md5")) {
			$new_md5 = trim(file_get_contents("{$iqRisk_tmppath}iprepdata.txt.gz.md5"));
			if ($new_md5 == md5_file("{$iqRisk_tmppath}iprepdata.txt.gz")) {
				mwexec("/usr/bin/gunzip -f {$iqRisk_tmppath}iprepdata.txt.gz");
				@rename("{$iqRisk_tmppath}iprepdata.txt", "{$iprep_path}iprepdata.txt");
				@rename("{$iqRisk_tmppath}iprepdata.txt.gz.md5", "{$iprep_path}iprepdata.txt.gz.md5");
				$success = TRUE;
				syslog(LOG_NOTICE, gettext("[Suricata] Successfully updated IPREP file 'iprepdata.txt'."));
			}
			else
				syslog(LOG_ALERT, gettext("[Suricata] ALERT: MD5 integrity check of downloaded 'iprepdata.txt.gz' file failed!  Skipping update of this IPREP file."));
		}
	}
}

// Cleanup the tmp directory path
rmdir_recursive("$iqRisk_tmppath");

syslog(LOG_NOTICE, gettext("[Suricata] Emerging Threats IQRisk IP List update finished."));

// If successful, signal any running Suricata process to live reload the rules and IP lists
if ($success == TRUE && is_process_running("suricata")) {
	foreach ($config['installedpackages']['suricata']['rule'] as $value) {
		if ($value['enable_iprep'] == "on") {
			suricata_reload_config($value);
			sleep(2);
		}
	}
}

?>
