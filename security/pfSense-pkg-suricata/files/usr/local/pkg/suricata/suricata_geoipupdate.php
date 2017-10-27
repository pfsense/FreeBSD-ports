<?php
/*
 * suricata_geoipupdate.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 * Copyright (C) 2016 Bill Meeks
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

/* This product includes GeoLite data created by MaxMind, available from 
 * http://www.maxmind.com
*/

require_once("config.inc");
require_once("functions.inc");
require("/usr/local/pkg/suricata/suricata_defs.inc");

/**********************************************************************
 * Start of main code                                                 *
 **********************************************************************/
global $g, $config;
$suricata_geoip_dbdir = SURICATA_PBI_BASEDIR . 'share/GeoIP/';
$geoip_tmppath = "{$g['tmp_path']}/geoipup/";

// If auto-updates of GeoIP are disabled, then exit
if ($config['installedpackages']['suricata']['config'][0]['autogeoipupdate'] == "off")
	exit(0);
else
	log_error(gettext("[Suricata] Updating the GeoIP country database files..."));

// Download the free GeoIP Legacy country name databases for IPv4 and IPv6
// to a temporary location.
safe_mkdir("$geoip_tmppath");
if (download_file("http://geolite.maxmind.com/download/geoip/database/GeoLiteCountry/GeoIP.dat.gz", "{$geoip_tmppath}GeoIP.dat.gz") != true)
	log_error(gettext("[Suricata] An error occurred downloading the 'GeoIP.dat.gz' update file for GeoIP."));
if (download_file("http://geolite.maxmind.com/download/geoip/database/GeoIPv6.dat.gz", "{$geoip_tmppath}GeoIPv6.dat.gz") != true)
	log_error(gettext("[Suricata] An error occurred downloading the 'GeoIPv6.dat.gz' update file for GeoIP."));

// If the files downloaded successfully, unpack them and store
// the DB files in the PBI_BASE/share/GeoIP directory.
if (file_exists("{$geoip_tmppath}GeoIP.dat.gz")) {
	mwexec("/usr/bin/gunzip -f {$geoip_tmppath}GeoIP.dat.gz");
	@rename("{$geoip_tmppath}GeoIP.dat", "{$suricata_geoip_dbdir}GeoIP.dat");
}

if (file_exists("{$geoip_tmppath}GeoIPv6.dat.gz")) {
	mwexec("/usr/bin/gunzip -f {$geoip_tmppath}GeoIPv6.dat.gz");
	@rename("{$geoip_tmppath}GeoIPv6.dat", "{$suricata_geoip_dbdir}GeoIPv6.dat");
}

// Cleanup the tmp directory path
rmdir_recursive("$geoip_tmppath");

log_error(gettext("[Suricata] GeoIP database update finished."));

?>
