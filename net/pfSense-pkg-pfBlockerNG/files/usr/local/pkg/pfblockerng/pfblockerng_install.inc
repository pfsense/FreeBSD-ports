<?php
/*
	pfBlockerNG_install.inc

	pfBlockerNG
	Copyright (c) 2015-2016 BBcan177@gmail.com
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

require_once('pfsense-utils.inc');
require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');
require_once('/usr/local/www/pfblockerng/pfblockerng.php');

global $config, $g, $pfb;
pfb_global();

// Set 'Install flag' to skip sync process during installations.
$g['pfblockerng_install'] = true;

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
$pfb['geoipshare'] = '/usr/local/share/GeoIP';
if (!file_exists("{$pfb['geoipshare']}/{$pfb['maxmind'][0]['file']}") ||
    !file_exists("{$pfb['geoipshare']}/{$pfb['maxmind'][1]['file']}") ||
    !file_exists("{$pfb['geoipshare']}/{$pfb['maxmind'][2]['file']}") ||
    !file_exists("{$pfb['geoipshare']}/{$pfb['maxmind'][3]['file']}") ||
    !file_exists("{$pfb['geoipshare']}/{$pfb['maxmind'][4]['file']}")) {

	update_status("\nDownloading MaxMind Country databases. This may take a minute...\n");
	foreach ($pfb['maxmind'] as $feed) {

		$file_dwn = "{$pfb['geoipshare']}/{$feed['file_dwn']}";
		if (($fhandle = @fopen("{$file_dwn}", 'w')) !== FALSE) {
			update_status(" {$feed['file']}...");
			if (!($ch = curl_init("{$feed['url']}"))) {
				update_status(" Failed to create cURL resource.\n");
				break;
			}

			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 Chrome/43.0.2357.65 Safari/537.36');
			curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'TLSv1.2, TLSv1.1, TLSv1');
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
				update_status(" cURL Error: {$curl_error}. ", 1);
			}

			$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if ($http_status == '200 OK') {
				if (strpos($file_dwn, '.gz') !== FALSE) {
					exec("/usr/bin/gunzip -dfq {$file_dwn} {$pfb['geoipshare']}");
				} elseif (strpos($file_dwn, '.zip') !== FALSE) {
					exec("/usr/bin/tar -xOf {$file_dwn} > {$pfb['geoipshare']}/{$feed['file']}");
					unlink_if_exists("{$file_dwn}");
				}
				update_status(" done.\n");
				$maxmind_verify += 1;
			}
			else {
				update_status(" failed!\n");
				break;
			}
		}
		curl_close($ch);
		@fclose($fhandle);
	}

	if ($maxmind_verify != 5) {
		update_status(" MaxMind download failed!\nFetching MaxMind archive from pfSense package repo...");
		// Fetch archived MaxMind database
		$url = 'https://packages.pfsense.org/packages/config/pfblockerng/countrycodes.tar.bz2';
		exec("/usr/bin/fetch -o /tmp/countrycodes.tar.bz2 {$url}");
		// Uncompress archived Country code file
		exec("/usr/bin/tar -jx -C {$pfb['ccdir']} -f /tmp/countrycodes.tar.bz2");
		update_status(" done.\n");
	}
	else {
		update_status("Downloading MaxMind Country databases... done.\n");
	}
}
else {
	update_status("\nMaxMind Country databases previously downloaded.\n");
}

update_status("Converting MaxMind Country databases for pfBlockerNG.\n This may take a few minutes...\n");
pfblockerng_uc_countries();
update_status("Creating pfBlockerNG Continent XML files...\n");
pfblockerng_get_countries();

if ($pfb['keep'] == 'on' && isset($pfb['widgets']) && strpos($pfb['widgets'], 'pfblockerng-container') !== FALSE) {
	update_status("Restoring previous pfBlockerNG Widget settings...");
	// Restore previous widget setting if 'keep' is enabled.
	$config['widgets']['sequence'] = $pfb['widgets'];
	write_config('pfBlockerNG: Save widget');
} else {
	update_status("Adding pfBlockerNG Widget to the Dashboard...");
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

update_status(" done.\nRemove any existing and create link for DNSBL lighttpd executable...");
unlink_if_exists('/usr/local/sbin/lighttpd_pfb');
link('/usr/local/sbin/lighttpd', '/usr/local/sbin/lighttpd_pfb');

update_status(" done.\nCreating DNSBL web server start-up script...");
$rc = array();
$rc['file'] = 'dnsbl.sh';
$rc['start'] = <<<EOF

	# Start DNSBL Lighttpd webserver
	if [ -f /var/unbound/pfb_dnsbl_lighty.conf ]; then
		/usr/local/sbin/lighttpd_pfb -f /var/unbound/pfb_dnsbl_lighty.conf
	fi

	# Terminate DNSBL HTTPS Daemon if found
	pidnum="$(/bin/ps -wax | /usr/bin/grep '[p]fblockerng.inc dnsbl' | /usr/bin/awk '{print $1}')"
	if [ ! -z "\${pidnum}" ]; then
		/bin/kill -9 "\${pidnum}"
		/bin/sleep 2
	fi

	# Start DNSBL HTTPS Daemon
	/usr/local/bin/php -f /usr/local/pkg/pfblockerng/pfblockerng.inc dnsbl &

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
update_status(" done.\n");

if ($pfb['enable'] == 'on' && $pfb['dnsbl'] == 'on' && !empty($pfb['dnsbl_port']) && !empty($pfb['dnsbl_port_ssl'])) {
	update_status("Creating DNSBL web server config ...");

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
	ssl.pemfile		= "/var/unbound/dnsbl_cert.pem"
	ssl.use-sslv2		= "disable"
	ssl.use-sslv3		= "disable"
	ssl.honor-cipher-order	= "enable"
	ssl.cipher-list		= "AES128+EECDH:AES256+EECDH:AES128+EDH:AES256+EDH:AES128-SHA:AES256-SHA:!aNULL:!eNULL:!DSS"

	\$HTTP["host"] =~ ".*" {
		url.rewrite-once = ( ".*" => "index.php" )
	}
}

EOF;

	@file_put_contents('/var/unbound/pfb_dnsbl_lighty.conf', $pfb_conf, LOCK_EX);
	unset($pfb_conf);
	update_status(" done.\n");

	update_status("Starting DNSBL Service...");
	restart_service('dnsbl');
	update_status(" done.\n");
}


// Upgrade - Adv. Inbound settings to new variable names. 
update_status("Upgrading Adv. Inbound firewall rule settings ...");
$ufound = FALSE;
$upgrade_type = array('pfblockernglistsv4', 'pfblockernglistsv6', 'pfblockerngdnsblsettings', 'pfblockerngafrica', 'pfblockerngantartica',
		    'pfblockerngasia', 'pfblockerngeurope', 'pfblockerngnorthamerica', 'pfblockerngoceania', 'pfblockerngsouthamerica',
		    'pfblockerngtopspammers', 'pfblockerngproxyandsatellite');

foreach ($upgrade_type as $type) {
	$conf_config = &$config['installedpackages'][$type]['config'];
	if (isset($conf_config)) {
		foreach ($conf_config as $key => $utype) {
			if (isset($utype['autoports'])) {
				$ufound = TRUE;
				if ($utype['autoports'] == 'on' && !empty($utype['aliasports']) && !isset($conf_config[$key]['autoports_in'])) {
					$conf_config[$key]['autoports_in'] = 'on';
					$conf_config[$key]['aliasports_in'] = $utype['aliasports'];
				}
				unset($conf_config[$key]['autoports']);
				unset($conf_config[$key]['aliasports']);
			}
			if (isset($utype['autodest'])) {
				$ufound = TRUE;
				if ($utype['autodest'] == 'on' && !empty($utype['aliasdest']) && !isset($conf_config[$key]['autoaddr_in'])) {
					$conf_config[$key]['autoaddr_in'] = 'on';
					$conf_config[$key]['aliasaddr_in'] = $utype['aliasdest'];
				}
				unset($conf_config[$key]['autodest']);
				unset($conf_config[$key]['aliasdest']);
			}
			if (isset($utype['autonot'])) {
				$ufound = TRUE;
				if ($utype['autonot'] == 'on' && !isset($conf_config[$key]['autonot_in'])) {
					$conf_config[$key]['autonot_in'] = $utype['autonot'];
				}
				unset($conf_config[$key]['autonot']);
			}
			if (isset($utype['autoproto'])) {
				$ufound = TRUE;
				$conf_config[$key]['autoproto_in'] = $utype['autoproto'];
				unset($conf_config[$key]['autoproto']);
			}
		}
	}
}

if ($ufound) {
	write_config('pfBlockerNG: Upgrade Adv. Inbound Settings.');
	update_status(" saving new changes ... done.\n");
} else {
	update_status(" no changes required ... done.\n");
}

unset($g['pfblockerng_install']);	// Remove 'Install flag'
update_status("Custom commands completed ... ");
return TRUE;

?>
