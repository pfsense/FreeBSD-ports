<?php
/*
 * suricata_geoipupdate.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2019 Rubicon Communications, LLC (Netgate)
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

/* This product includes GeoLite2 data created by MaxMind, available from 
 * https://www.maxmind.com
*/

require_once("config.inc");
require_once("functions.inc");
require("/usr/local/pkg/suricata/suricata_defs.inc");

/**********************************************************************
 * Start of main code                                                 *
 **********************************************************************/
global $g, $config;
$suricata_geoip_dbdir = SURICATA_PBI_BASEDIR . 'share/suricata/GeoLite2/';
$geoip_tmppath = "{$g['tmp_path']}/geoipup/";

// If auto-updates of GeoIP are disabled, then exit
if ($config['installedpackages']['suricata']['config'][0]['autogeoipupdate'] == "off")
	exit(0);
else
	log_error(gettext("[Suricata] Checking for updated MaxMind GeoLite2 IP database file..."));

// Create a temporary location to download the database to
safe_mkdir($geoip_tmppath);

// Get the MD5 hash of the current database file if available,
// otherwise use a MD5 zero hash.
if (file_exists($suricata_geoip_dbdir . "GeoLite2-Country.mmdb")) {
	$md5_hash = md5_file($suricata_geoip_dbdir . "GeoLite2-Country.mmdb");
}
else {
	$md5_hash = "d41d8cd98f00b204e9800998ecf8427e"; // zero hash
}

// Set the output filename for the downloaded DB archive
$tmpfile = $geoip_tmppath . "GeoLite2-Country.mmdb.gz";

// Set the URL string.  We supply the MD5 hash for our
// current file.  The server will compare it to the
// value for the latest file and return the HTTP
// Response Code "304" if no newer file is available.
// If a newer file is available, the server will send
// it to us.  We obtain and examine the CURLINFO_RESPONSE_CODE
// value to see which result occurs.
$url = "https://updates.maxmind.com/geoip/databases/GeoLite2-Country/update?db_md5=" . $md5_hash;

// Get a file handle for CURL and then start the CURL
// transfer.
$fout = fopen($tmpfile, "wb");
$ch = curl_init($url);
if (!$ch) {
	log_error(gettext("[Suricata] An error occurred attempting to download the 'GeoLite2-Country.mmdb' database file for Geo-Location by IP."));
	exit(0);
}

curl_setopt($ch, CURLOPT_FILE, $fout);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_NOPROGRESS, '1');
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, "TLSv1.2, TLSv1");
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

		case 304:  // No new update available
			log_error("[Suricata] GeoLite2-Country IP database is up-to-date.");
			break;

		case 401:  // Account ID or License Key invalid
			log_error("[Suricata] The Account ID or License Key for MaxMind GeoLite2 is invalid.");
			break;

		case 200:  // Successful file download
			log_error(gettext("[Suricata] New GeoLite2-Country IP database gzip archive successfully downloaded."));
			break;

		default:
			log_error("[Suricata] Received an unexpected HTTP response code " . $response . " during GeoLite2-Country database update check.");
	}
} else {
	log_error("[Suricata] GeoLite2-Country IP database download failed.  The HTTP Response Code was " . $response . ".");
}
fclose($fout);
curl_close($ch);

// Mount filesystem read-write since we need to write the
// extracted databases to usr/local/share/suricata/GeoLite2.
conf_mount_rw();
safe_mkdir($suricata_geoip_dbdir);

// If the file downloaded successfully, unpack it and store
// the DB file in the PBI_BASE/share/suricata/GeoLite2 directory.
if (file_exists("{$geoip_tmppath}GeoLite2-Country.mmdb.gz") && $response == 200) {
	log_error("[Suricata] Unzipping new GeoLit2-Country database archive...");
	mwexec("/usr/bin/gunzip -f {$geoip_tmppath}GeoLite2-Country.mmdb.gz");
	log_error("[Suricata] Copying new database to {$suricata_geoip_dbdir}GeoLite2-Country.mmdb...");
	@rename("{$geoip_tmppath}GeoLite2-Country.mmdb", "{$suricata_geoip_dbdir}GeoLite2-Country.mmdb");
}

// Finished with filesystem mods, so remount read-only
conf_mount_ro();

// Cleanup the tmp directory path
rmdir_recursive("$geoip_tmppath");

log_error(gettext("[Suricata] GeoLite2-Country database update check finished."));

?>
