<?php
/*
 * suricata_geoipupdate.php
 *
 * Significant portions of this code are based on original work done
 * for the Snort package for pfSense from the following contributors:
 * 
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2006 Scott Ullrich
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 * Copyright (C) 2012 Ermal Luci
 * All rights reserved.
 *
 * Adapted for Suricata by:
 * Copyright (C) 2016 Bill Meeks
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:

 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
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

// Mount filesystem read-write since we need to write
// the extracted databases to PBI_BASE/share/GeoIP.
conf_mount_rw();

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

// Finished with filesystem mods, so remount read-only
conf_mount_ro();

// Cleanup the tmp directory path
rmdir_recursive("$geoip_tmppath");

log_error(gettext("[Suricata] GeoIP database update finished."));

?>
