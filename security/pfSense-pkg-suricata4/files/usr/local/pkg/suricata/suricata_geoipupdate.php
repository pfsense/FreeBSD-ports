<?php
/*
 * suricata_geoipupdate.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 * Copyright (C) 2020 Bill Meeks
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
require("/usr/local/pkg/suricata/suricata_defs.inc");

/***************************************************************/
/* This function attempts to download from the specified URL   */
/* and stores the result in the file specified by $tmpfile.    */
/* The HTTP response code is retuned when $result is not NULL. */
/***************************************************************/
function suricata_download_geoip_file($url, $tmpfile, &$result = NULL) {

	global $config, $g;

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
	curl_setopt($ch, CURLOPT_NOPROGRESS, '1');
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, "TLSv1.3, TLSv1.2, TLSv1.1, TLSv1, SSLv3");
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
	curl_setopt($ch, CURLOPT_TIMEOUT, 0);

	// detect broken connection so it disconnects after +-10 minutes (with default TCP_KEEPIDLE and TCP_KEEPINTVL) to avoid waiting forever.
	curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);

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
		if (!empty($config['system']['proxyport'])) {
			curl_setopt($ch, CURLOPT_PROXYPORT, $config['system']['proxyport']);
		}
		if (!empty($config['system']['proxyuser']) && !empty($config['system']['proxypass'])) {
			@curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_ANY | CURLAUTH_ANYSAFE);
			curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$config['system']['proxyuser']}:{$config['system']['proxypass']}");
		}
	}
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
	curl_close($ch);
	if (isset($result) && $rc === TRUE) {
		$result = $response;
	}
	return $rc;
}

/**********************************************************************
 * Start of main code                                                 *
 **********************************************************************/
global $g, $config;
$suricata_geoip_dbdir = SURICATA_PBI_BASEDIR . "share/suricata/GeoLite2/";
$geoip_tmppath = "{$g['tmp_path']}/geoipup/";

// If auto-updates of GeoIP are disabled, then exit
if ($config['installedpackages']['suricata']['config'][0]['autogeoipupdate'] == "off")
	exit(0);
else
	syslog(LOG_NOTICE, gettext("[Suricata] Checking for updated MaxMind GeoLite2 IP database file..."));

// Create a temporary location to download the database to
safe_mkdir($geoip_tmppath);

// Get the MD5 hash of the current database archive file if available,
// otherwise use a MD5 zero hash.
if (file_exists($suricata_geoip_dbdir . "GeoLite2-Country.mmdb.tar.gz.md5")) {
	$md5_hash = file_get_contents($suricata_geoip_dbdir . "GeoLite2-Country.mmdb.tar.gz.md5");
}
else {
	$md5_hash = "d41d8cd98f00b204e9800998ecf8427e"; // zero hash
}

$result = "";

// Set the output filenames for the downloaded DB archives
$dbtarfile = $geoip_tmppath . "GeoLite2-Country.mmdb.tar.gz";
$dbfile = $geoip_tmppath . "GeoLite2-Country.mmdb";
$md5file = $geoip_tmppath . "GeoLite2-Country.mmdb.tar.gz.md5";

// Set the URL strings with the user's license key.
$dbfile_url = "https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&license_key=" . $config['installedpackages']['suricata']['config'][0]['maxmind_geoipdb_key'] . "&suffix=tar.gz";
$md5file_url = "https://download.maxmind.com/app/geoip_download?edition_id=GeoLite2-Country&license_key=" . $config['installedpackages']['suricata']['config'][0]['maxmind_geoipdb_key'] . "&suffix=tar.gz.md5";

// First check the MD5 of the DB we have (if any) against the latest on the 
// MaxMind site to see if we already have the most current DB file version.
if (suricata_download_geoip_file($md5file_url, $md5file, $result) && ($result == 200 || $result == 201)) {
	if (file_exists($md5file)) {
		if ($md5_hash == file_get_contents($md5file)) {
			syslog(LOG_NOTICE, "[Suricata] The GeoLite2-Country IP database is up-to-date.");

			// Cleanup the tmp directory path
			rmdir_recursive("$geoip_tmppath");
			exit(0);
		} else {
			syslog(LOG_NOTICE, "[Suricata] A new GeoLite2-Country IP database is available.");
			syslog(LOG_NOTICE, "[Suricata] Downloading new GeoLite2-Country IP database...");
		}
	}
} else {
	syslog(LOG_ERR, "[Suricata] ERROR: GeoLite2-Country IP database update check failed. The GeoIP database was not updated!");
	exit(0);
}

// If we get this far, then we either have no local DB file
// or a newer version is posted on the MaxMind site and 
// thus we need to download it.
safe_mkdir($suricata_geoip_dbdir);
$result = "";

// Attempt to download the GeoIP database from MaxMind
if (suricata_download_geoip_file($dbfile_url, $dbtarfile, $result)) {

	// If the file downloaded successfully, unpack it and store the DB
	// and MD5 files in the PBI_BASE/share/suricata/GeoLite2 directory.
	if (file_exists($dbtarfile) && ($result == 200 || $result == 201)) {
		syslog(LOG_NOTICE, "[Suricata] New GeoLite2-Country IP database gzip archive successfully downloaded.");
		syslog(LOG_NOTICE, "[Suricata] Extracting new GeoLite2-Country database from the archive...");
		mwexec("/usr/bin/tar -xzf {$dbtarfile} --strip=1 -C {$geoip_tmppath}");
		syslog(LOG_NOTICE, "[Suricata] Moving new database to {$suricata_geoip_dbdir}GeoLite2-Country.mmdb...");
		@rename($dbfile, "{$suricata_geoip_dbdir}GeoLite2-Country.mmdb");
		@rename($md5file, "{$suricata_geoip_dbdir}GeoLite2-Country.mmdb.tar.gz.md5");
		syslog(LOG_NOTICE, "[Suricata] GeoLite2-Country database update completed.");
	} else {
		syslog(LOG_ERR, "[Suricata] ERROR: GeoLite2-Country IP database download failed. The HTTP response code was '{$result}'. The GeoIP database was not updated!");
	}
} else {
	syslog(LOG_ERR, "[Suricata] ERROR: GeoLite2-Country IP database download failed. The GeoIP database was not updated!");
}

// Cleanup the tmp directory path
syslog(LOG_NOTICE, "[Suricata] Cleaning up temp files after GeoLite2-Country database update.");
rmdir_recursive("$geoip_tmppath");

?>
