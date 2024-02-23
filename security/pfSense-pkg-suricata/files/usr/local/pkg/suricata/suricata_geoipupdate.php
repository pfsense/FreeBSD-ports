<?php
/*
 * suricata_geoipupdate.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2024 Rubicon Communications, LLC (Netgate)
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

/* This product includes GeoLite2 data created by MaxMind, available from 
 * https://www.maxmind.com
*/

require_once("config.inc");
require_once("functions.inc");
require_once("notices.inc");
require("/usr/local/pkg/suricata/suricata_defs.inc");

/***************************************************************/
/* This function attempts to download from the specified URL   */
/* and stores the result in the file specified by $tmpfile.    */
/* The HTTP response code is retuned when $result is not NULL. */
/***************************************************************/
function suricata_download_geoip_file($url, $tmpfile, $user, $pwd, &$result = NULL) {

	global $g;

	// Get a file handle for CURL and then start the CURL
	// transfer.
	$fout = fopen($tmpfile, "wb");
	$ch = curl_init($url);
	if (!$ch) {
		syslog(LOG_ERR, gettext("[Suricata] ERROR: An error occurred attempting to download the database file for Geo-Location by IP."));
		return FALSE;
	}

	curl_setopt($ch, CURLOPT_FILE, $fout);
	curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
	curl_setopt($ch, CURLOPT_HEADER, false);
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
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

	// detect broken connection so it disconnects after +-10 minutes (with default TCP_KEEPIDLE and TCP_KEEPINTVL) to avoid waiting forever.
	curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);

	// Honor any system restrictions on sending USERAGENT info
	if (config_get_path('system/do_not_send_host_uuid')) {
		curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' . $g['product_version'] . ' : ' . get_single_sysctl('kern.hostuuid'));
	}
	else {
		curl_setopt($ch, CURLOPT_USERAGENT, $g['product_name'] . '/' . $g['product_version']);
	}

	// Use the system proxy server setttings if configured
	if (!empty(config_get_path('system/proxyurl'))) {
		curl_setopt($ch, CURLOPT_PROXY, config_get_path('system/proxyurl'));
		if (!empty(config_get_path('system/proxyport'))) {
			curl_setopt($ch, CURLOPT_PROXYPORT, config_get_path('system/proxyport'));
		}
		if (config_get_path('system/proxyuser') && config_get_path('system/proxypass')) {
			@curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_ANY | CURLAUTH_ANYSAFE);
			curl_setopt($ch, CURLOPT_PROXYUSERPWD, config_get_path('system/proxyuser') . ":" . config_get_path('system/proxypass'));
		}
	}

	// Set the MaxMind Account ID and Password fields
	curl_setopt($ch, CURLOPT_USERPWD, "{$user}:{$pwd}");

	$rc = curl_exec($ch);
	if ($rc === true) {
		switch ($response = curl_getinfo($ch, CURLINFO_RESPONSE_CODE)) {

			case 401:  // Account ID or License Key invalid
				syslog(LOG_ALERT, "[Suricata] ALERT: The Account ID or License Key for MaxMind GeoLite2 is invalid.");
				break;

			case 200:  // Successful file download
			case 201:  // Successful file download (resource created)
				break;

			default:
				syslog(LOG_WARNING, "[Suricata] WARNING: Received an unexpected HTTP response code " . $response . " during GeoLite2-Country database update check.");
		}
	} else {
		syslog(LOG_ERR, "[Suricata] ERROR: GeoLite2-Country IP database download failed.  The HTTP Response Code was " . $response . ".");
	}
	fclose($fout);
	if (isset($result) && $rc === TRUE) {
		$result = $response;
	}
	return $rc;
}

/**********************************************************************
 * Start of main code                                                 *
 **********************************************************************/
global $g;
$suricata_geoip_dbdir = SURICATA_PBI_BASEDIR . "share/suricata/GeoLite2/";
$geoip_tmppath = "{$g['tmp_path']}/geoipup/";

// If auto-updates of GeoIP are disabled, then exit
if (config_get_path('installedpackages/suricata/config/0/autogeoipupdate') == "off")
	return;
else
	syslog(LOG_NOTICE, gettext("[Suricata] Checking for updated MaxMind GeoLite2 IP database file..."));
	$notify_message = gettext("Suricata MaxMind GeoLite2 IP database update started: " . date("Y-m-d H:i:s") . "\n");

// Create a temporary location to download the database to
safe_mkdir($geoip_tmppath);

// Get the SHA256 hash of the current database archive file if available,
// otherwise use a SHA256 zero-length file hash.
if (file_exists($suricata_geoip_dbdir . "GeoLite2-Country.mmdb.tar.gz.sha256")) {
	$sha256_hash = file_get_contents($suricata_geoip_dbdir . "GeoLite2-Country.mmdb.tar.gz.sha256");
}
else {
	$sha256_hash = "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855  GeoLite2-Country_20240206.tar.gz"; // zero-length file hash
}

$result = "";

// Set the output filenames for the downloaded DB archives
$dbtarfile = $geoip_tmppath . "GeoLite2-Country.mmdb.tar.gz";
$dbfile = $geoip_tmppath . "GeoLite2-Country.mmdb";
$sha256file = $geoip_tmppath . "GeoLite2-Country.mmdb.tar.gz.sha256";

// Set the URL strings with the user's license key.
$dbfile_url = MAXMIND_GEOIP2_DNLD_URL;
$sha256file_url = MAXMIND_GEOIP2_SHA256_DNLD_URL;

// Set MaxMind GeoLite2 Account ID and Password variables
$user = config_get_path('installedpackages/suricata/config/0/maxmind_geoipdb_uid', "");
$pwd = config_get_path('installedpackages/suricata/config/0/maxmind_geoipdb_key', "");

// First check the SHA256 hash of the DB we have (if any) against the latest on 
// the MaxMind site to see if we already have the most current DB file version.
if (suricata_download_geoip_file($sha256file_url, $sha256file, $user, $pwd, $result) && ($result == 200 || $result == 201)) {
	if (file_exists($sha256file)) {
		if ($sha256_hash == file_get_contents($sha256file)) {
			syslog(LOG_NOTICE, "[Suricata] The GeoLite2-Country IP database is up-to-date.");
			$notify_message .= gettext("- MaxMind GeoLite2 IP database is up-to-date.\n");
		} else {
			syslog(LOG_NOTICE, "[Suricata] A new GeoLite2-Country IP database is available.");
			syslog(LOG_NOTICE, "[Suricata] Downloading new GeoLite2-Country IP database...");
			$needs_update = true;
		}
	}
} else {
	syslog(LOG_ERR, "[Suricata] ERROR: GeoLite2-Country IP database update check failed. The GeoIP database was not updated!");
	$notify_message .= gettext("- MaxMind GeoLite2 IP database update check failed. The GeoIP database was not updated!\n");
}

// If we get this far, then we either have no local DB file
// or a newer version is posted on the MaxMind site and 
// thus we need to download it.
safe_mkdir($suricata_geoip_dbdir);
$result = "";

// Attempt to download the GeoIP database from MaxMind
if ($needs_update && suricata_download_geoip_file($dbfile_url, $dbtarfile, $user, $pwd, $result)) {

	// If the file downloaded successfully, unpack it and store the DB
	// and SHA256 files in the PBI_BASE/share/suricata/GeoLite2 directory.
	if (file_exists($dbtarfile) && ($result == 200 || $result == 201)) {
		syslog(LOG_NOTICE, "[Suricata] New GeoLite2-Country IP database gzip archive successfully downloaded.");
		syslog(LOG_NOTICE, "[Suricata] Extracting new GeoLite2-Country database from the archive...");
		mwexec("/usr/bin/tar -xzf {$dbtarfile} --strip=1 -C {$geoip_tmppath}");
		syslog(LOG_NOTICE, "[Suricata] Moving new database to {$suricata_geoip_dbdir}GeoLite2-Country.mmdb...");
		@rename($dbfile, "{$suricata_geoip_dbdir}GeoLite2-Country.mmdb");
		@rename($sha256file, "{$suricata_geoip_dbdir}GeoLite2-Country.mmdb.tar.gz.sha256");
		syslog(LOG_NOTICE, "[Suricata] GeoLite2-Country database update completed.");
		$notify_message .= gettext("- MaxMind GeoLite2 IP database update completed.\n");
	} else {
		syslog(LOG_ERR, "[Suricata] ERROR: GeoLite2-Country IP database download failed. The HTTP response code was '{$result}'. The GeoIP database was not updated!");
		$notify_message .= gettext("- MaxMind GeoLite2 IP database download failed. The HTTP response code was '{$result}'. The GeoIP database was not updated!\n");
	}
} elseif ($needs_update) {
	syslog(LOG_ERR, "[Suricata] ERROR: GeoLite2-Country IP database download failed. The GeoIP database was not updated!");
	$notify_message .= gettext("- MaxMind GeoLite2 IP database download failed. The GeoIP database was not updated!\n");
}

// Cleanup the tmp directory path
syslog(LOG_NOTICE, "[Suricata] Cleaning up temp files after GeoLite2-Country database update.");
rmdir_recursive("$geoip_tmppath");
$notify_message .= gettext("Suricata MaxMind GeoLite2 IP database update finished: " . date("Y-m-d H:i:s") . "\n");

if (config_get_path('installedpackages/suricata/config/0/update_notify') == 'on') {
	notify_all_remote($notify_message);
}
return true;
?>
