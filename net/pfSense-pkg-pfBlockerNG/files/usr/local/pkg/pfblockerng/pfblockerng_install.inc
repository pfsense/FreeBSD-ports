<?php
/*
	pfBlockerNG_install.inc

	pfBlockerNG
	Copyright (c) 2015 BBcan177@gmail.com
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

require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');
require_once('/usr/local/www/pfblockerng/pfblockerng.php');

global $config, $pfb, $static_output;
pfb_global();

function update_static_output($text) {
	global $static_output;

	$static_output .= "{$text}";
	update_output_window("{$static_output}");
	return;
}

// Set 'Install flag' to skip sync process during installations.
$g['pfblockerng_install'] = true;

// Remove previous ccdir location files if exist
$old_ccfiles = glob('/usr/pbi/pfblockerng-' . php_uname('m') . '/share/GeoIP/*_v?.txt');
if (!empty($old_ccfiles)) {
	foreach ($old_ccfiles as $oldfile) {
		unlink_if_exists("{$oldfile}");
	}
}

$pfs_version = substr(trim(file_get_contents('/etc/version')), 0, 3);
if ($pfs_version == '2.2') {
	$pfb['prefix'] = '/usr/pbi/pfblockerng-' . php_uname('m');
} else {
	$pfb['prefix'] = '/usr/local';
}
$pfb['geoipshare'] = "{$pfb['prefix']}/share/GeoIP";

$pfb['maxmind'][0]['url']	= 'http://geolite.maxmind.com/download/geoip/database/GeoLiteCountry/GeoIP.dat.gz';
$pfb['maxmind'][0]['file_dwn']	= 'GeoIP.dat.gz';
$pfb['maxmind'][0]['file']	= 'GeoIP.dat';
$pfb['maxmind'][1]['url']	= 'http://geolite.maxmind.com/download/geoip/database/GeoIPv6.dat.gz';
$pfb['maxmind'][1]['file_dwn']	= 'GeoIPv6.dat.gz';
$pfb['maxmind'][1]['file']	= 'GeoIPv6.dat';
$pfb['maxmind'][2]['url']	= 'http://geolite.maxmind.com/download/geoip/database/GeoIPCountryCSV.zip';
$pfb['maxmind'][2]['file_dwn']	= 'GeoIPCountryCSV.zip';
$pfb['maxmind'][2]['file']	= 'GeoIPCountryWhois.csv';
$pfb['maxmind'][3]['url']	= 'http://dev.maxmind.com/static/csv/codes/country_continent.csv';
$pfb['maxmind'][3]['file_dwn']	= 'country_continent.csv';
$pfb['maxmind'][3]['file']	= 'country_continent.csv';
$pfb['maxmind'][4]['url']	= 'http://geolite.maxmind.com/download/geoip/database/GeoIPv6.csv.gz';
$pfb['maxmind'][4]['file_dwn']	= 'GeoIPv6.csv.gz';
$pfb['maxmind'][4]['file']	= 'GeoIPv6.csv';

// Only download MaxMind Database if files do not exist.
$maxmind_verify = 0;
if (!file_exists("{$pfb['geoipshare']}/{$pfb['maxmind'][0]['file']}") ||
    !file_exists("{$pfb['geoipshare']}/{$pfb['maxmind'][1]['file']}") ||
    !file_exists("{$pfb['geoipshare']}/{$pfb['maxmind'][2]['file']}") ||
    !file_exists("{$pfb['geoipshare']}/{$pfb['maxmind'][3]['file']}") ||
    !file_exists("{$pfb['geoipshare']}/{$pfb['maxmind'][4]['file']}")) {

	update_static_output("\nDownloading MaxMind Country databases. This may take a minute...\n");
	foreach ($pfb['maxmind'] as $feed) {

		$file_dwn = "{$pfb['geoipshare']}/{$feed['file_dwn']}";
		if (($fhandle = fopen("{$file_dwn}", 'w')) !== FALSE) {
			update_static_output(" {$feed['file']}...");
			if (!($ch = curl_init("{$feed['url']}"))) {
				update_static_output(" Failed to create cURL resource.\n");
				break;
			}

			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 Chrome/43.0.2357.65 Safari/537.36');
			curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'TLSv1.2, TLSv1');
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, true);
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
			curl_setopt($ch, CURLOPT_FILE, $fhandle);
			curl_setopt($ch, CURLOPT_TIMEOUT, 600);

			// Attempt 3 Downloads before failing.
			for ($retries = 1; $retries <= 3; $retries++) {
				if (curl_exec($ch)) {
					break;	// Break on success
				}

				$curl_error = curl_errno($ch);
				update_static_output(" cURL Error: {$curl_error}. ", 1);
			}

			$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if ($http_status == '200 OK') {
				if (strpos($file_dwn, '.gz') !== FALSE) {
					exec("/usr/bin/gunzip -dfq {$file_dwn} {$pfb['geoipshare']}");
				} elseif (strpos($file_dwn, '.zip') !== FALSE) {
					exec("/usr/bin/tar -xOf {$file_dwn} > {$pfb['geoipshare']}/{$feed['file']}");
					unlink_if_exists("{$file_dwn}");
				}
				update_static_output(" done.\n");
				$maxmind_verify += 1;
			}
			else {
				update_static_output(" failed!\n");
				break;
			}
		}
		curl_close($ch);
		fclose($fhandle);
	}

	if ($maxmind_verify != 5) {
		update_static_output(" MaxMind download failed!\nFetching MaxMind archive from pfSense package repo...");
		// Fetch archived MaxMind database
		$url = 'https://packages.pfsense.org/packages/config/pfblockerng/countrycodes.tar.bz2';
		exec("/usr/bin/fetch -o /tmp/countrycodes.tar.bz2 {$url}");
		// Uncompress archived Country code file
		exec("/usr/bin/tar -jx -C {$pfb['ccdir']} -f /tmp/countrycodes.tar.bz2");
		update_static_output(" done.\n");
	}
	else {
		update_static_output("Downloading MaxMind Country databases... done.\n");
	}
}
else {
	update_static_output("\nMaxMind Country databases previously downloaded.\n");
}

update_static_output("Converting MaxMind Country databases for pfBlockerNG.\n This may take a few minutes...");
pfblockerng_uc_countries();
update_static_output(" done.\nCreating pfBlockerNG Continent XML files...");
pfblockerng_get_countries();

if ($pfb['keep'] == 'on' && isset($pfb['widgets']) && strpos($pfb['widgets'], 'pfblockerng-container') !== FALSE) {
	update_static_output(" done.\nRestoring previous pfBlockerNG Widget settings...");
	// Restore previous widget setting if 'keep' is enabled.
	$config['widgets']['sequence'] = $pfb['widgets'];
	write_config('pfBlockerNG: Save widget');
} else {
	update_static_output(" done.\nAdding pfBlockerNG Widget to the Dashboard...");
	$widgets = $config['widgets']['sequence'];
	if (strpos($widgets, 'pfblockerng-container') === FALSE) {
		if (empty($widgets)) {
			$config['widgets']['sequence'] = 'pfblockerng-container:col2:show';
		} else {
			$config['widgets']['sequence'] .= ',pfblockerng-container:col2:show';
		}
		write_config('pfBlockerNG: Save widget');
	}
}

update_static_output(" done.\nRemove any existing and create link for DNSBL lighttpd executable...");
unlink_if_exists('/usr/local/sbin/lighttpd_pfb');
link('/usr/local/sbin/lighttpd', '/usr/local/sbin/lighttpd_pfb');

update_static_output(" done.\nCreating DNSBL web server start-up script...");
$rc = array();
$rc['file'] = 'dnsbl.sh';
$rc['start'] = <<<EOF

	# Start DNSBL Lighttpd webserver
	if [ -f '{$pfb['dnsbl_conf']}' ]; then
		/usr/local/sbin/lighttpd_pfb -f '{$pfb['dnsbl_conf']}'
	fi

	# Terminate DNSBL HTTPS Daemon if found
	pidnum="$(/bin/ps -wax | /usr/bin/grep '[p]fblockerng.inc dnsbl' | /usr/bin/awk '{print $1}')"
	if [ ! -z "\${pidnum}" ]; then
		/bin/kill -9 "\${pidnum}"
		/bin/sleep 2
	fi

	# Start DNSBL HTTPS Daemon
	/usr/local/bin/php-cgi -f /usr/local/pkg/pfblockerng/pfblockerng.inc dnsbl &

EOF;

$rc['stop'] = <<<EOF

	# Terminate DNSBL Lighttpd webserver, if found.
	pidnum="$(/bin/pgrep lighttpd_pfb)"
	if [ ! -z "\${pidnum}" ]; then
		/usr/bin/killall lighttpd_pfb
	fi

	# Terminate DNSBL HTTPS Daemon, if found.
	pidnum="$(/bin/ps -wax | /usr/bin/grep '[p]fblockerng.inc dnsbl' | /usr/bin/awk '{print $1}')"
	if [ ! -z "\${pidnum}" ]; then
		/bin/kill -9 "\${pidnum}"
		/bin/sleep 2
	fi

EOF;

write_rcfile($rc);
update_static_output(" done.\n");

if ($pfb['enable'] == 'on' && $pfb['dnsbl'] == 'on' && !empty($pfb['dnsbl_port']) && !empty($pfb['dnsbl_port_ssl'])) {
	update_static_output("Creating DNSBL web server config ...");

	$pfb_conf = <<<EOF
#
#pfBlockerNG Lighttpd DNSBL configuration file
#
server.bind			= "0.0.0.0"
server.port			= "{$pfb['dnsbl_port']}"
server.event-handler		= "freebsd-kqueue"
server.network-backend		= "freebsd-sendfile"
server.dir-listing		= "disable"
server.document-root		= "/usr/local/www/pfblockerng/www/"
server.errorlog			= "/var/log/pfblockerng/dnsbl_error.log"
server.pid-file			= "/var/run/dnsbl.pid"
server.modules			= ( "mod_access", "mod_fastcgi", "mod_rewrite" )

server.indexfiles		= ( "index.php" )
mimetype.assign			= ( ".html" => "text/html", ".gif" => "image/gif" )
url.access-deny			= ( "~", ".inc" )
fastcgi.server			= ( ".php" => ( "localhost" => ( "socket" => "/var/run/php-fpm.socket", "broken-scriptfilename" => "enable" ) ) )

debug.log-condition-handling	= "enable"

\$HTTP["host"] =~ ".*" {
	url.rewrite-once = ( ".*" => "index.php" )
}

\$SERVER["socket"] == "0.0.0.0:{$pfb['dnsbl_port_ssl']}" {
	ssl.engine		= "enable"
	ssl.pemfile		= "{$pfb['dnsbl_cert']}"
	ssl.use-sslv2		= "disable"
	ssl.use-sslv3		= "disable"
	ssl.honor-cipher-order	= "enable"
	ssl.cipher-list		= "AES128+EECDH:AES256+EECDH:AES128+EDH:AES256+EDH:AES128-SHA:AES256-SHA:!aNULL:!eNULL:!DSS"

	\$HTTP["host"] =~ ".*" {
		url.rewrite-once = ( ".*" => "index.php" )
	}
}

EOF;

	@file_put_contents($pfb['dnsbl_conf'], $pfb_conf, LOCK_EX);
	unset($pfb_conf);
	update_static_output(" done.\n");

	update_static_output("Starting DNSBL Service...");
	start_service('dnsbl');
	update_static_output(" done.\n");
}

unset($g['pfblockerng_install']);	// Remove 'Install flag'
update_static_output("Custom commands completed ... ");
return TRUE;

?>