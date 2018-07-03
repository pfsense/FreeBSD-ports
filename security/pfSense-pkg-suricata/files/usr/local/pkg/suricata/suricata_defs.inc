<?php
/*
 * suricata_defs.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 * Copyright (C) 2017 Bill Meeks
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

/*************************************************************************/
/* This file contains definitions for various CONSTANTS used throughout  */
/* the Suricata package.  It is included via a "require_once()" call in  */
/* the "suricata.inc" and "suricata_post_install.php" files.             */
/*************************************************************************/

global $g, $config;

if (!is_array($config['installedpackages']['suricata']))
	$config['installedpackages']['suricata'] = array();

/* Get installed package version for display */
$suricata_package_version = "{$config['installedpackages']['package'][get_package_id("suricata")]['version']}";

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
	$suricataver = exec_command("{$suricatabindir}suricata -V 2>&1 |/usr/bin/cut -c26-");
	if (!empty($suricataver))
		define("SURICATA_BIN_VERSION", $suricataver);
	else
		define("SURICATA_BIN_VERSION", "");
}

// Define the name of the pf table used for IP blocks
if (!defined('SURICATA_PF_TABLE'))
	define('SURICATA_PF_TABLE', 'snort2c');

// Create some other useful defines
if (!defined('SURICATADIR'))
	define('SURICATADIR', SURICATA_PBI_BASEDIR . 'etc/suricata/');
if (!defined('SURICATALOGDIR'))
	define('SURICATALOGDIR', "{$g['varlog_path']}/suricata/");
if (!defined('SURICATA_RULES_UPD_LOGFILE'))
	define('SURICATA_RULES_UPD_LOGFILE', SURICATALOGDIR . 'suricata_rules_update.log');
if (!defined('SURICATA_SID_MODS_PATH'))
	define('SURICATA_SID_MODS_PATH', "{$g['vardb_path']}/suricata/sidmods/");
if (!defined('SURICATA_IPREP_PATH'))
	define('SURICATA_IPREP_PATH', "{$g['vardb_path']}/suricata/iprep/");

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
if (!defined("VRT_FILE_PREFIX"))
	define("VRT_FILE_PREFIX", "snort_");
if (!defined("GPL_FILE_PREFIX"))
	define("GPL_FILE_PREFIX", "GPLv2_");
if (!defined("ET_OPEN_FILE_PREFIX"))
	define("ET_OPEN_FILE_PREFIX", "emerging-");
if (!defined("ET_PRO_FILE_PREFIX"))
	define("ET_PRO_FILE_PREFIX", "etpro-");
if (!defined('SURICATA_ENFORCING_RULES_FILENAME'))
	define('SURICATA_ENFORCING_RULES_FILENAME', 'suricata.rules');
if (!defined('FLOWBITS_FILENAME'))
	define('FLOWBITS_FILENAME', 'flowbit-required.rules');

?>
