<?php
/*
	pfBlockerNG_install.inc

	pfBlockerNG
	Copyright (C) 2015 BBcan177@gmail.com
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:


	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.


	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.

*/

// Install pfBlockerNG package, launched from pfblockerng.xml

require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');
require_once('/usr/local/www/pfblockerng/pfblockerng.php');

global $config, $pfb;
pfb_global();

// Remove previously used CC folder location if exists
@rmdir_recursive("{$pfb['dbdir']}/cc");

// Uncompress Country Code File
@copy("{$pfb['dbdir']}/countrycodes.tar.bz2", "{$pfb['ccdir']}/countrycodes.tar.bz2");
exec("/usr/bin/tar -jx -C {$pfb['ccdir']} -f {$pfb['ccdir']}/countrycodes.tar.bz2");
// Download MaxMind Files and Create Country Code files and Build Continent XML Files
update_output_window(gettext("Downloading MaxMind Country Databases. This may take a minute..."));
exec("/bin/sh /usr/local/pkg/pfblockerng/geoipupdate.sh all >> {$pfb['geolog']} 2>&1");

update_output_window(gettext("MaxMind Country Database downloads completed..."));
update_output_window(gettext("Converting MaxMind Country Databases for pfBlockerNG. This may take a few minutes..."));
pfblockerng_uc_countries();
update_output_window(gettext("Creating pfBlockerNG Continent XML Files..."));
pfblockerng_get_countries();
update_output_window(gettext("Completed Creating pfBlockerNG Continent XML Files..."));

// Remove Original Maxmind Database Files
@unlink_if_exists("{$pfb['dbdir']}/GeoIPCountryCSV.zip");
@unlink_if_exists("{$pfb['dbdir']}/GeoIPCountryWhois.csv");
@unlink_if_exists("{$pfb['dbdir']}/GeoIPv6.csv");
@unlink_if_exists("{$pfb['dbdir']}/country_continent.csv");

// Add Widget to Dashboard
update_output_window(gettext("Adding pfBlockerNG Widget to Dashboard."));
if ($pfb['keep'] == "on" && !empty($pfb['widgets'])) {
	// Restore previous Widget setting if "Keep" is enabled.
	$config['widgets']['sequence'] = $pfb['widgets'];
} else {
	$widgets = $config['widgets']['sequence'];
	if (!preg_match("/pfblockerng-container/", $widgets)) {
		if (empty($widgets)) {
			$config['widgets']['sequence'] = "pfblockerng-container:col2:show";
		} else {
			$config['widgets']['sequence'] .= ",pfblockerng-container:col2:show";
		}
	}
}
return TRUE;

?>