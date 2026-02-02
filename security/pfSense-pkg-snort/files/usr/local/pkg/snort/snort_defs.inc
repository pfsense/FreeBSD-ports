<?php
/*
 * snort_defs.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2009-2010 Robert Zelaya
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

global $g;

define('LOG_PREFIX_PKG_SNORT', 'Snort');

/* Define some useful constants for Snort */
if (!defined("SNORT_BASEDIR")) {
	define("SNORT_BASEDIR", "/usr/local/");
}
if (!defined("SNORT_BINDIR"))
	define("SNORT_BINDIR", SNORT_BASEDIR . "bin/");
if (!defined("SNORTDIR"))
	define("SNORTDIR", SNORT_BASEDIR . "etc/snort");
if (!defined("SNORTLOGDIR"))
	define("SNORTLOGDIR", "{$g['varlog_path']}/snort");
if (!defined("SNORT_BIN_VERSION")) {
	// Grab the Snort binary version programmatically by
	// running the binary with the command-line argument
	// to display the version information.
	$snortbindir = SNORT_BINDIR;
	$snortver = exec_command("{$snortbindir}/snort -V 2>&1 |/usr/bin/grep Version | /usr/bin/cut -c20-31");

	// Extract just the numbers and decimal point
	// delimiters at the front of the version string.
	$matches = array();
	if (preg_match('/^[^\s]+/', $snortver, $matches)) {
		define("SNORT_BIN_VERSION", $matches[0]);
	}
	else {
		define("SNORT_BIN_VERSION", "2.9.20");
	}
}

if (!defined("SNORT_SID_MODS_PATH"))
	define('SNORT_SID_MODS_PATH', "{$g['vardb_path']}/snort/sidmods/");
if (!defined("SNORT_IPREP_PATH"))
	define("SNORT_IPREP_PATH", "{$g['vardb_path']}/snort/iprep/");
if (!defined('SNORT_APPID_ODP_PATH'))
	define('SNORT_APPID_ODP_PATH', SNORTDIR . "/appid/");
if (!defined('SNORT_APPID_RULES_PATH'))
        define('SNORT_APPID_RULES_PATH', SNORTDIR . "/rules/");

// Rules filenames, download URLs and prefixes.
// Be sure to include the trailing backslash on URLs.
if (!defined("SNORT_ENFORCING_RULES_FILENAME"))
	define("SNORT_ENFORCING_RULES_FILENAME", "snort.rules");
if (!defined("FLOWBITS_FILENAME"))
	define("FLOWBITS_FILENAME", "flowbit-required.rules");
if (!defined("SNORT_RULES_UPD_LOGFILE"))
	define("SNORT_RULES_UPD_LOGFILE", SNORTLOGDIR . "/snort_rules_update.log");
if (!defined("VRT_DNLD_URL"))
	define("VRT_DNLD_URL", "https://www.snort.org/rules/");
if (!defined("ET_VERSION"))
	define("ET_VERSION", "2.9.0");
if (!defined("ET_BASE_DNLD_URL"))
	define("ET_BASE_DNLD_URL", "https://rules.emergingthreats.net/"); 
if (!defined("ETPRO_BASE_DNLD_URL"))
	define("ETPRO_BASE_DNLD_URL", "https://rules.emergingthreatspro.com/"); 
if (!defined("SNORT_ET_DNLD_FILENAME"))
	define("SNORT_ET_DNLD_FILENAME", "emerging.rules.tar.gz");
if (!defined("SNORT_ETPRO_DNLD_FILENAME"))
	define("SNORT_ETPRO_DNLD_FILENAME", "etpro.rules.tar.gz");
if (!defined("SNORT_GPLV2_DNLD_FILENAME"))
	define("SNORT_GPLV2_DNLD_FILENAME", "community-rules.tar.gz");
if (!defined("GPLV2_DNLD_URL"))
	define("GPLV2_DNLD_URL", "https://www.snort.org/downloads/community/");
if (!defined("SNORT_OPENAPPID_DNLD_URL"))
	define("SNORT_OPENAPPID_DNLD_URL", "https://www.snort.org/downloads/openappid/");
if (!defined("SNORT_OPENAPPID_DNLD_FILENAME"))
	define("SNORT_OPENAPPID_DNLD_FILENAME", "snort-openappid.tar.gz");
if (!defined("SNORT_OPENAPPID_RULES_URL"))
	define("SNORT_OPENAPPID_RULES_URL", "https://files.netgate.com/openappid/");
if (!defined("SNORT_OPENAPPID_RULES_FILENAME"))
	define("SNORT_OPENAPPID_RULES_FILENAME", "appid_rules.tar.gz");
if (!defined("SNORT_RULES_UPD_LOGFILE"))
	define("SNORT_RULES_UPD_LOGFILE", SNORTLOGDIR . "/snort_rules_update.log");
if (!defined("VRT_FILE_PREFIX"))
	define("VRT_FILE_PREFIX", "snort_");
if (!defined("GPL_FILE_PREFIX"))
	define("GPL_FILE_PREFIX", "GPLv2_");
if (!defined("ET_OPEN_FILE_PREFIX"))
	define("ET_OPEN_FILE_PREFIX", "emerging-");
if (!defined("ET_PRO_FILE_PREFIX"))
	define("ET_PRO_FILE_PREFIX", "etpro-");
if (!defined("OPENAPPID_FILE_PREFIX"))
	define("OPENAPPID_FILE_PREFIX", "openappid-");
if (!defined("FEODO_TRACKER_DNLD_FILENAME"))
	define("FEODO_TRACKER_DNLD_FILENAME", "feodotracker.tar.gz");
if (!defined("FEODO_TRACKER_DNLD_URL"))
	define("FEODO_TRACKER_DNLD_URL", "https://feodotracker.abuse.ch/downloads/");

?>
