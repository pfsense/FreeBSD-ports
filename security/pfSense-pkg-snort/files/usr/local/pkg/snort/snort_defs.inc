<?php
/*
 * snort_defs.inc
 *
 * Copyright (C) 2006 Scott Ullrich
 * Copyright (C) 2009-2010 Robert Zelaya
 * Copyright (C) 2011-2012 Ermal Luci
 * Copyright (C) 2013-2015 Bill Meeks
 * part of pfSense
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
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

global $g, $config;

/* Define some useful constants for Snort */
if (!defined("SNORT_PBI_BASEDIR")) {
	$pf_version=substr(trim(file_get_contents("/etc/version")),0,3);
	if ($pf_version == "2.1" || $pf_version == "2.2")
		define("SNORT_PBI_BASEDIR", "/usr/pbi/snort-" . php_uname("m") . "/");
	else
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
	$snortver = array();
	$snortbindir = SNORT_PBI_BINDIR;
	mwexec("{$snortbindir}/snort -V 2>&1 |/usr/bin/grep Version | /usr/bin/cut -c20-26", $snortver);
	if (!empty($snortver[0]))
		define("SNORT_BIN_VERSION", $snortver[0]);
	else
		define("SNORT_BIN_VERSION", "2.9.7.3");
}
if (!defined("SNORT_SID_MODS_PATH"))
	define('SNORT_SID_MODS_PATH', "{$g['vardb_path']}/snort/sidmods/");
if (!defined("SNORT_IPREP_PATH"))
	define("SNORT_IPREP_PATH", "{$g['vardb_path']}/snort/iprep/");
if (!defined('SNORT_APPID_ODP_PATH'))
	define('SNORT_APPID_ODP_PATH', SNORTDIR . "/appid/");

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

?>
