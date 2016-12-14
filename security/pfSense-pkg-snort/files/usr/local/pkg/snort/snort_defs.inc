<?php
/*
 * snort_defs.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2009-2010 Robert Zelaya
 * Copyright (c) 2013-2016 Bill Meeks
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

global $g, $config;

/* Define some useful constants for Snort */
if (!defined("SNORT_PBI_BASEDIR")) {
	define("SNORT_PBI_BASEDIR", "/usr/local/");
}
if (!defined("SNORT_PBI_BINDIR"))
	define("SNORT_PBI_BINDIR", SNORT_PBI_BASEDIR . "bin/");
if (!defined("SNORTDIR"))
	define("SNORTDIR", SNORT_PBI_BASEDIR . "etc/snort");
if (!defined("SNORTLOGDIR"))
	define("SNORTLOGDIR", "{$g['varlog_path']}/snort");
if (!defined("SNORT_BIN_VERSION")) {
	// Grab the Snort binary version programmatically
	$snortbindir = SNORT_PBI_BINDIR;
	$snortver = exec_command("{$snortbindir}/snort -V 2>&1 |/usr/bin/grep Version | /usr/bin/cut -c20-26");
	if (!empty($snortver))
		define("SNORT_BIN_VERSION", $snortver);
	else
		define("SNORT_BIN_VERSION", "2.9.8.3");
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
	define("ET_BASE_DNLD_URL", "http://rules.emergingthreats.net/"); 
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
	define("SNORT_OPENAPPID_RULES_URL", "http://www.ifs.edu.br/");
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

?>
