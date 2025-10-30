<?php
/*
 * suricata_defs.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2025 Bill Meeks
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

require_once("pkg-utils.inc");

define('LOG_PREFIX_PKG_SURICATA', 'Suricata');

/*************************************************************************/
/* This file contains definitions for various CONSTANTS used throughout  */
/* the Suricata package.  It is included via a "require_once()" call in  */
/* the "suricata.inc" and "suricata_post_install.php" files.             */
/*************************************************************************/

global $g;

/* Get installed package version for display */
$suricata_package_version = config_get_path('installedpackages/package/' . get_package_id("suricata") . '/version');

// Define the installed package version
if (!defined('SURICATA_PKG_VER'))
	define('SURICATA_PKG_VER', $suricata_package_version);

$pf_version=substr(trim(file_get_contents("/etc/version")),0,3);
// Define the PBI base directory
if (!defined('SURICATA_PBI_BASEDIR')) {
	define('SURICATA_PBI_BASEDIR', '/usr/local/');
}

// Define the suricata binary directory
if (!defined('SURICATA_PBI_BINDIR'))
	define('SURICATA_PBI_BINDIR', SURICATA_PBI_BASEDIR . 'bin/');

if (!defined("SURICATA_BIN_VERSION")) {
	// Grab the Suricata binary version programmatically
	$suricatabindir = SURICATA_PBI_BINDIR;

	// Since upstream now includes extra text after the version number, we cut by fields instead
	// of grabbing up to the EOL. Field 5 currently has the version string we need.
	$suricataver = exec_command("{$suricatabindir}suricata -V 2>&1 |/usr/bin/cut -d \" \" -f5");
	if (!empty($suricataver))
		define("SURICATA_BIN_VERSION", $suricataver);
	else
		// If we could not find a version, set a safe default
		define("SURICATA_BIN_VERSION", "7.0.8");
}

// Define the name of the pf table used for IP blocks
if (!defined('SURICATA_PF_TABLE'))
	define('SURICATA_PF_TABLE', 'snort2c');

// Create some other useful defines
if (!defined('SURICATADIR'))
	define('SURICATADIR', SURICATA_PBI_BASEDIR . 'etc/suricata/');
if (!defined('SURICATALOGDIR'))
	define('SURICATALOGDIR', "{$g['varlog_path']}/suricata/");
if (!defined('SURICATA_RULES_DIR'))
	define('SURICATA_RULES_DIR', "/usr/local/share/suricata/rules/");
if (!defined('SURICATA_RULES_UPD_LOGFILE'))
	define('SURICATA_RULES_UPD_LOGFILE', SURICATALOGDIR . 'suricata_rules_update.log');
if (!defined('SURICATA_SID_MODS_PATH'))
	define('SURICATA_SID_MODS_PATH', "{$g['vardb_path']}/suricata/sidmods/");
if (!defined('SURICATA_IPREP_PATH'))
	define('SURICATA_IPREP_PATH', "{$g['vardb_path']}/suricata/iprep/");

// Define an array consisting of Suricata default events rules
if (!defined("SURICATA_DEFAULT_RULES"))
	define("SURICATA_DEFAULT_RULES", array( "app-layer-events.rules", "decoder-events.rules", "dhcp-events.rules", "dnp3-events.rules",
						"dns-events.rules", "files.rules", "ftp-events.rules", "http-events.rules", "http2-events.rules", "ipsec-events.rules",
						"kerberos-events.rules", "modbus-events.rules", "mqtt-events.rules", "nfs-events.rules", "ntp-events.rules",
						"quic-events.rules", "rfb-events.rules", "smb-events.rules", "smtp-events.rules", "ssh-events.rules",
						"stream-events.rules", "tls-events.rules" ));

// Rule set download URLs, filenames and prefixes
if (!defined("VRT_DNLD_URL"))
	define("VRT_DNLD_URL", "https://www.snort.org/rules/");
if (!defined("ET_VERSION"))
	define("ET_VERSION", "2.9.0");
if (!defined("ET_BASE_DNLD_URL"))
	define("ET_BASE_DNLD_URL", "https://rules.emergingthreats.net/"); 
if (!defined("ETPRO_BASE_DNLD_URL"))
	define("ETPRO_BASE_DNLD_URL", "https://rules.emergingthreatspro.com/"); 
if (!defined("ET_DNLD_FILENAME"))
	define("ET_DNLD_FILENAME", "emerging.rules.tar.gz");
if (!defined("ETPRO_DNLD_FILENAME"))
	define("ETPRO_DNLD_FILENAME", "etpro.rules.tar.gz");
if (!defined("ET_IQRISK_DNLD_URL"))
	define("ET_IQRISK_DNLD_URL", "https://rules.emergingthreatspro.com/_xxx_/reputation/");
if (!defined("GPLV2_DNLD_FILENAME"))
	define("GPLV2_DNLD_FILENAME", "community-rules.tar.gz");
if (!defined("GPLV2_DNLD_URL"))
	define("GPLV2_DNLD_URL", "https://www.snort.org/downloads/community/");

if (!defined("FEODO_TRACKER_DNLD_FILENAME"))
	define("FEODO_TRACKER_DNLD_FILENAME", "feodotracker.tar.gz");
if (!defined("FEODO_TRACKER_DNLD_URL"))
	define("FEODO_TRACKER_DNLD_URL", "https://feodotracker.abuse.ch/downloads/");
if (!defined("ABUSE_SSLBL_DNLD_FILENAME"))
	define("ABUSE_SSLBL_DNLD_FILENAME", "sslblacklist_tls_cert.tar.gz");
if (!defined("ABUSE_SSLBL_DNLD_URL"))
	define("ABUSE_SSLBL_DNLD_URL", "https://sslbl.abuse.ch/blacklist/");

# MaxMind GeoIP2 database update permalink URLs
if (!defined("MAXMIND_GEOIP2_DNLD_URL"))
	define("MAXMIND_GEOIP2_DNLD_URL", "https://download.maxmind.com/geoip/databases/GeoLite2-Country/download?suffix=tar.gz");
if (!defined("MAXMIND_GEOIP2_SHA256_DNLD_URL"))
	define("MAXMIND_GEOIP2_SHA256_DNLD_URL", "https://download.maxmind.com/geoip/databases/GeoLite2-Country/download?suffix=tar.gz.sha256");

if (!defined("VRT_FILE_PREFIX"))
	define("VRT_FILE_PREFIX", "snort_");
if (!defined("GPL_FILE_PREFIX"))
	define("GPL_FILE_PREFIX", "GPLv2_");
if (!defined("ET_OPEN_FILE_PREFIX"))
	define("ET_OPEN_FILE_PREFIX", "emerging-");
if (!defined("ET_PRO_FILE_PREFIX"))
	define("ET_PRO_FILE_PREFIX", "etpro-");
if (!defined("EXTRARULE_FILE_PREFIX"))
	define("EXTRARULE_FILE_PREFIX", "extrarule-");
if (!defined('SURICATA_ENFORCING_RULES_FILENAME'))
	define('SURICATA_ENFORCING_RULES_FILENAME', 'suricata.rules');
if (!defined('FLOWBITS_FILENAME'))
	define('FLOWBITS_FILENAME', 'flowbit-required.rules');

?>
