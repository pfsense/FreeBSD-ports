<?php
/*
 * lightsquid.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2006-2012 Sergey Dvoriancev <dv_serg@mail.ru>
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

require_once('certs.inc');
require_once('config.inc');
require_once('filter.inc');
require_once('globals.inc');
require_once('pfsense-utils.inc');
require_once('pkg-utils.inc');
require_once('service-utils.inc');
require_once('util.inc');


if (file_exists('/usr/local/pkg/squid.inc')) {
	require_once('/usr/local/pkg/squid.inc');
} else {
	echo "No squid.inc found. You must have Squid package installed to use LightSquid.";
}

define('LIGHTSQUID_BASE', '/usr/local');

// configuration settings
define('LS_CONFIGPATH', LIGHTSQUID_BASE . '/etc/lightsquid');
define('LS_CONFIGFILE', 'lightsquid.cfg');
define('LS_CONFIGFILE_DIST', 'lightsquid.cfg.sample');
define('LS_WWWPATH', LIGHTSQUID_BASE . '/www/lightsquid');
define('LS_TEMPLATEPATH', LS_WWWPATH . '/tpl');
define('LS_LANGPATH', LIGHTSQUID_BASE . '/share/lightsquid/lang');
define('LS_REPORTPATH', '/var/lightsquid/report');
define('LS_SQUIDLOGPATH', config_get_path('installedpackages/squid/config/0/log_dir', '/var/squid/logs'));
define('LS_SQUIDLOG', 'access.log');
define('LS_IP2NAMEPATH', LIGHTSQUID_BASE . '/libexec/lightsquid');
define('CRONTAB_LS_TEMPLATE', '/usr/local/bin/perl ' . LIGHTSQUID_BASE . '/www/lightsquid/lightparser.pl');

// default values
define('LS_DEF_IP2NAME', 'dns');
define('LS_DEF_SQUIDLOGTYPE', '0');
define('LS_DEF_SKIPURL', 'zzz\.zzz');
define('LS_DEF_LANG', 'eng');
define('LS_DEF_TEMPLATE', 'base');
define('LS_DEF_BARCOLOR', 'orange');

define('LOG_PREFIX_PKG_LIGHTSQUID', 'lightsquid');

/*
 * Package install/uninstall
 */

function lightsquid_install() {
	// create lightsquid reports directory
	lightsquid_create_reportdir();

	// Create web server config dir
	safe_mkdir("/var/etc/lightsquid");

	// template symlinks
	foreach (array('novopf', 'novosea') as $tpl) {
		@symlink('/usr/local/share/lightsquid/tpl/' . $tpl, LS_TEMPLATEPATH . '/' . $tpl);
	}

	update_status("\nRemove any existing and create new hardlink for lightsquid lighttpd executable...");
	unlink_if_exists('/usr/local/sbin/lighttpd_ls');
	link('/usr/local/sbin/lighttpd', '/usr/local/sbin/lighttpd_ls');

	update_status(" done.\nCreating Lightsquid web server start-up script...");
	lightsquid_write_rcfile();
	update_status(" done.\n");

	update_status("Creating lightsquid web server config ...");
	lightsquid_write_webconfig();
	update_status(" done.\n");

	update_status("Starting lightsquid lighttpd Service...");
	restart_service('lightsquid_web');
	update_status(" done.\n");

}

function lightsquid_deinstall() {
	// remove cronjobs
	lightsquid_setup_cron(false);
	rmdir_recursive("/var/etc/lightsquid/");
}

function lightsquid_write_rcfile() {
	$rc = array();
	$rc['file'] = 'lighttpd_ls.sh';
	$rc['start'] = <<<EOF
# Start Lightsquid lighttpd webserver
	if [ -f /var/etc/lightsquid/lighttpd_ls.conf ]; then
		/usr/local/sbin/lighttpd_ls -f /var/etc/lightsquid/lighttpd_ls.conf
	fi
EOF;

	$rc['stop'] = <<<EOF
# Terminate lightsquid Lighttpd webserver, if found.
	pidnum="$(/bin/pgrep lighttpd_ls)"
	if [ ! -z "\${pidnum}" ]; then
		/usr/bin/killall lighttpd_ls
	fi
EOF;

	write_rcfile($rc);
}

function lightsquid_write_webconfig() {
	$lighttpd_ls_auth_path = "/var/etc/lightsquid/.passwd";
	$lighttpd_ls_cert_path = "/var/etc/lightsquid/cert.pem";
	$lightsquid_config = config_get_path('installedpackages/lightsquid/config/0', []);

	if (empty($lightsquid_config)) {
		return;
	}

	if (empty($lightsquid_config['lighttpd_ls_port'])) {
		$lightsquid_config['lighttpd_ls_port'] = "7445";
	}
	if (empty($lightsquid_config['lighttpd_ls_user'])) {
		$lightsquid_config['lighttpd_ls_user'] = "admin";
	}
	if (empty($lightsquid_config['lighttpd_ls_password'])) {
		$lightsquid_config['lighttpd_ls_password'] = "pfsense";
	}

	touch($lighttpd_ls_auth_path);
	chmod($lighttpd_ls_auth_path, 0600);
	// Remove any existing and create new hardlink for lightsquid lighttpd executable
	// This is required to avoid undefined symbol errors when lighttpd port is upgraded
	unlink_if_exists('/usr/local/sbin/lighttpd_ls');
	link('/usr/local/sbin/lighttpd', '/usr/local/sbin/lighttpd_ls');
	file_put_contents($lighttpd_ls_auth_path, "{$lightsquid_config['lighttpd_ls_user']}:{$lightsquid_config['lighttpd_ls_password']}");

	$lighttpd_ls_conf = <<<EOF
#
# Lightsquid lighttpd configuration file
#

EOF;

	// Add this only if SSL is disabled, things break otherwise
	if ($lightsquid_config['lighttpd_ls_ssl'] != "on") {
		$lighttpd_ls_conf .= <<<EOF
server.bind			= "0.0.0.0"
server.port			= "{$lightsquid_config['lighttpd_ls_port']}"
\$SERVER["socket"]		== "[::]:{$lightsquid_config['lighttpd_ls_port']}" {  }

EOF;
	} else {
		/*
		 * We ALWAYS need to put server.port there; otherwise
		 * lighttpd thing binds to 80/HTTP no matter what
		 */
		$lighttpd_ls_conf .= <<<EOF
server.port			= "{$lightsquid_config['lighttpd_ls_port']}"

EOF;
	}
	$lighttpd_ls_conf .= <<<EOF
server.event-handler		= "freebsd-kqueue"
server.network-backend		= "freebsd-sendfile"
server.dir-listing		= "disable"
server.document-root		= "/usr/local/www/lightsquid/"
server.errorlog			= "/var/log/lighttpd_lightsquid.log"
server.pid-file			= "/var/run/lighttpd_ls.pid"
server.modules			= ( "mod_access", "mod_fastcgi", "mod_rewrite", "mod_auth", "mod_cgi" )

server.indexfiles		= ( "index.php", "index.html", "index.htm", "default.htm", "index.cgi" )

mimetype.assign			= (
	".pdf"		=>	"application/pdf",
	".sig"		=>	"application/pgp-signature",
	".spl"		=>	"application/futuresplash",
	".class"	=>	"application/octet-stream",
	".ps"		=>	"application/postscript",
	".torrent"	=>	"application/x-bittorrent",
	".dvi"		=>	"application/x-dvi",
	".gz"		=>	"application/x-gzip",
	".pac"		=>	"application/x-ns-proxy-autoconfig",
	".swf"		=>	"application/x-shockwave-flash",
	".tar.gz"	=>	"application/x-tgz",
	".tgz"		=>	"application/x-tgz",
	".tar"		=>	"application/x-tar",
	".zip"		=>	"application/zip",
	".mp3"		=>	"audio/mpeg",
	".m3u"		=>	"audio/x-mpegurl",
	".wma"		=>	"audio/x-ms-wma",
	".wax"		=>	"audio/x-ms-wax",
	".ogg"		=>	"audio/x-wav",
	".wav"		=>	"audio/x-wav",
	".gif"		=>	"image/gif",
	".jpg"		=>	"image/jpeg",
	".jpeg"		=>	"image/jpeg",
	".png"		=>	"image/png",
	".svg"		=>	"image/svg+xml",
	".xbm"		=>	"image/x-xbitmap",
	".xpm"		=>	"image/x-xpixmap",
	".xwd"		=>	"image/x-xwindowdump",
	".css"		=>	"text/css",
	".html"		=>	"text/html",
	".htm"		=>	"text/html",
	".js"		=>	"text/javascript",
	".asc"		=>	"text/plain",
	".c"		=>	"text/plain",
	".conf"		=>	"text/plain",
	".text"		=>	"text/plain",
	".txt"		=>	"text/plain",
	".dtd"		=>	"text/xml",
	".xml"		=>	"text/xml",
	".mpeg"		=>	"video/mpeg",
	".mpg"		=>	"video/mpeg",
	".mov"		=>	"video/quicktime",
	".qt"		=>	"video/quicktime",
	".avi"		=>	"video/x-msvideo",
	".asf"		=>	"video/x-ms-asf",
	".asx"		=>	"video/x-ms-asf",
	".wmv"		=>	"video/x-ms-wmv",
	".bz2"		=>	"application/x-bzip",
	".tbz"		=>	"application/x-bzip-compressed-tar",
	".tar.bz2"	=>	"application/x-bzip-compressed-tar"
 )


url.access-deny			= ( "~", ".inc" )
fastcgi.server			= ( ".php" => ( "localhost" => ( "socket" => "/var/run/php-fpm.socket", "broken-scriptfilename" => "enable" ) ) )
cgi.assign			= ( ".cgi" => "" )

debug.log-condition-handling	= "enable"

auth.backend = "plain"
auth.backend.plain.userfile = "{$lighttpd_ls_auth_path}"

auth.require = ( "/" =>
	(
	"method" => "basic",
	"realm" => "Password Required",
	"require" => "valid-user"
	)
)
EOF;

	// SSL is enabled
	if (!isset($lightsquid_config['lighttpd_ls_ssl']) || ($lightsquid_config['lighttpd_ls_ssl'] == "on")) {
		// Write out cert
		$lscert = lookup_cert(config_get_path('system/webgui/ssl-certref', ''));
		$lscert = $lscert['item'];
		if (!is_array($lscert) || !$lscert['crt'] || !$lscert['prv'] ||
		    cert_chain_has_weak_digest($lscert)) {
			$lscert = cert_create_selfsigned();
			if (is_array($lscert) && !empty($lscert)) {
				config_set_path('system/webgui/ssl-certref', $lscert['refid']);
				write_config(sprintf(gettext("Generated new self-signed SSL/TLS certificate for HTTPS (%s)"), $lscert['refid']));
			} else {
				logger(LOG_ERR, localize_text("GUI certificate is not usable and there was an error creating a new self-signed certificate."), LOG_PREFIX_PKG_LIGHTSQUID);
			}
		}
		$cert = trim(unixnewlines(base64_decode($lscert['crt']))) . "\n";
		$key = trim(unixnewlines(base64_decode($lscert['prv']))) . "\n";
		$ca = trim(unixnewlines(ca_chain($lscert))) . "\n";

		if (!empty($cert) && !empty($key)) {
			$fd = fopen($lighttpd_ls_cert_path, "w");
			if (!$fd) {
				printf(gettext("Error: cannot open certificate file in lightsquid_write_webconfig().%s"), "\n");
				return 1;
			}
			chmod($lighttpd_ls_cert_path, 0600);
			if (!empty($ca)) {
				$cert_chain = $cert . $ca;
			} else {
				$cert_chain = $cert;
			}
			fwrite($fd, $cert_chain);
			fwrite($fd, $key);
			fclose($fd);
		}

		// Add SSL config
		// Common SSL config snippet for use below
		$lighttpd_ls_conf_sslcommon = <<<EOF
ssl.engine		= "enable"
ssl.pemfile		= "{$lighttpd_ls_cert_path}"
ssl.use-sslv2		= "disable"
ssl.use-sslv3		= "disable"
ssl.honor-cipher-order	= "enable"
ssl.cipher-list		= "AES128+EECDH:AES256+EECDH:AES128+EDH:AES256+EDH:AES128-SHA:AES256-SHA:!aNULL:!eNULL:!DSS"
EOF;

		/*
		 * IPv4 HTTPS bind must NOT be a socket; otherwise lighttpd
		 * yet again binds to 80/HTTP no matter what. You enable
		 * SSL-only IPv4 by not configuring bind for IPv4 at all.
		 * IPv6 SSL must be configured as a socket
		 */
		$lighttpd_ls_conf .= <<<EOF

{$lighttpd_ls_conf_sslcommon}


\$SERVER["socket"]	== "[::]:{$lightsquid_config['lighttpd_ls_port']}" {
{$lighttpd_ls_conf_sslcommon}
}
EOF;

	} // endif - SSL is enabled

	file_put_contents('/var/etc/lightsquid/lighttpd_ls.conf', $lighttpd_ls_conf, LOCK_EX);
	unset($lighttpd_ls_conf);
}

/*
 * Package configuration routines
 */
function lightsquid_resync() {
	lightsquid_create_reportdir();
	safe_mkdir("/var/etc/lightsquid");
	mwexec("/bin/chmod -R u+w " . LIGHTSQUID_BASE . "/etc/lightsquid");

	// Set up variables for configuration update
	$lsconf_var = array();
	$lsconf_var['cfgpath'] = "\"" . LS_CONFIGPATH . "\"";
	$lsconf_var['logpath'] = "\"" . LS_SQUIDLOGPATH . "\"";

	$lsconf_var['tplpath'] = "\"" . LS_TEMPLATEPATH . "\"";
	$lsconf_var['langpath'] = "\"" . LS_LANGPATH . "\"";
	$lsconf_var['reportpath'] = "\"" . LS_REPORTPATH . "\"";
	$lsconf_var['ip2namepath'] = "\"" . LS_IP2NAMEPATH . "\"";

	$lsconf_var['lang'] = "\"" . LS_DEF_LANG . "\"";
	$lsconf_var['templatename'] = "\"" . LS_DEF_TEMPLATE . "\"";
	$lsconf_var['ip2name'] = "\"" . LS_DEF_IP2NAME . "\"";
	$lsconf_var['skipurl'] = "'" . LS_DEF_SKIPURL . "'";
	$lsconf_var['squidlogtype'] = LS_DEF_SQUIDLOGTYPE;

	// Update variables from package GUI config
	$lightsquid_config = config_get_path('installedpackages/lightsquid/config/0');
	if (is_array($lightsquid_config)) {
		if (isset($lightsquid_config['lightsquid_lang']) and !empty($lightsquid_config['lightsquid_lang'])) {
			$lsconf_var['lang'] = "\"" . $lightsquid_config['lightsquid_lang'] . "\"";
		}

		if (isset($lightsquid_config['lightsquid_skipurl']) and !empty($lightsquid_config['lightsquid_skipurl'])) {
			$lsconf_var['skipurl'] = "'" . str_replace(".", "\\.", $lightsquid_config['lightsquid_skipurl']) . "'";
		}

		if (isset($lightsquid_config['lightsquid_ip2name']) and !empty($lightsquid_config['lightsquid_ip2name'] )) {
			$lsconf_var['ip2name'] = "\"" . $lightsquid_config['lightsquid_ip2name'] . "\"";
		}

		if (isset($lightsquid_config['lightsquid_template']) and !empty($lightsquid_config['lightsquid_template'])) {
			$tpl_val = $lightsquid_config['lightsquid_template'];
			// check template path
			if (!file_exists(LS_TEMPLATEPATH."/$tpl_val")) {
				$tpl_val = 'base';
			}
			$lsconf_var['templatename'] = "\"" . $tpl_val . "\"";
		}

		if (isset($lightsquid_config['lightsquid_barcolor']) and !empty($lightsquid_config['lightsquid_barcolor'])) {
			$lsconf_var['barcolor'] = "\"" . $lightsquid_config['lightsquid_barcolor'] . "\"";
		}
	}

	// Create or update lightsquid.cfg
	$lsconf = "";
	$lsconf_file = LS_CONFIGPATH . "/" . LS_CONFIGFILE;
	// Always use the lightsquid.cfg.dist template to avoid issues with GUI values reconfiguration
	$lsconf_dist_file = LS_CONFIGPATH . "/" . LS_CONFIGFILE_DIST;
	if (file_exists($lsconf_dist_file)) {
		$lsconf = file_get_contents($lsconf_dist_file);
		logger(LOG_INFO, localize_text("Loaded default '%s' configuration file.", $lsconf_dist_file), LOG_PREFIX_PKG_LIGHTSQUID);
	} else {
		logger(LOG_ERR, localize_text("Could not load default '%s' configuration file.", $lsconf_dist_file), LOG_PREFIX_PKG_LIGHTSQUID);
	}

	// Update lightsquid.cfg
	if (!empty($lsconf)) {
		$lsconf = explode("\n", $lsconf);
		foreach ($lsconf_var as $key => $val) {
			for ($i = 0; $i < count($lsconf); $i++) {
				$s = trim($lsconf[$i]);
				$e_key = "/^[$]" . $key . "[ ]*[=]+/i";
				if (preg_match($e_key, $s)) {
					$lsconf[$i] = '$' . "$key = $val;";
				}
			}
		}

		$lsconf = implode("\n", $lsconf);
		if (file_put_contents($lsconf_file, $lsconf)) {
			logger(LOG_INFO, localize_text("Successfully created '%s' configuration file.", $lsconf_file), LOG_PREFIX_PKG_LIGHTSQUID);
		} else {
			logger(LOG_ERR, localize_text("Could not create '%s' configuration file.", $lsconf_file), LOG_PREFIX_PKG_LIGHTSQUID);
		}
	} else {
		logger(LOG_ERR, localize_text("Could not create '%s' configuration file.", $lsconf_file), LOG_PREFIX_PKG_LIGHTSQUID);
	}

	// Set up scheduled reports updates
	lightsquid_setup_cron(true);
	lightsquid_write_webconfig();
	restart_service('lightsquid_web');
}

/*
 * Reports
 */

/* Configure scheduled reports updates via cron */
function lightsquid_setup_cron($active=false) {
	$cron_schedule = config_get_path('installedpackages/lightsquid/config/0/lightsquid_refreshsheduler_time', '');
	$lightsquid_parser_today = CRONTAB_LS_TEMPLATE . " today";
	$lightsquid_parser_yesterday = CRONTAB_LS_TEMPLATE . " yesterday";

	if ($active && $cron_schedule) {
		$on = false;
		$update_cron = true;
		$lightsquid_cron_entry = false;
		$opt = array("*", "*", "*", "*", "*", "root");
		switch ($cron_schedule) {
			case 'lhp_none': $on = false; break;
			case 'lhp_10m':  $on = true; $opt[0]= "*/10"; break;
			case 'lhp_20m':  $on = true; $opt[0]= "*/20"; break;
			case 'lhp_30m':  $on = true; $opt[0]= "*/30"; break;
			case 'lhp_40m':  $on = true; $opt[0]= "*/40"; break;
			case 'lhp_50m':  $on = true; $opt[0]= "*/50"; break;
			case 'lhp_60m':  $on = true; $opt[0]= "*/60"; break;
			case 'lhp_2h':   $on = true; $opt[0]= "0"; $opt[1]= "*/2";  break;
			case 'lhp_3h':   $on = true; $opt[0]= "0"; $opt[1]= "*/3";  break;
			case 'lhp_4h':   $on = true; $opt[0]= "0"; $opt[1]= "*/4";  break;
			case 'lhp_6h':   $on = true; $opt[0]= "0"; $opt[1]= "*/6";  break;
			case 'lhp_8h':   $on = true; $opt[0]= "0"; $opt[1]= "*/8";  break;
			case 'lhp_12h':  $on = true; $opt[0]= "0"; $opt[1]= "*/12"; break;
			case 'lhp_24h':  $on = true; $opt[0]= "45"; $opt[1]= "23";   break; // daily at 23:45
		}
		foreach (config_get_path('cron/item', []) as $item) {
			if ($item['command'] == $lightsquid_parser_today) {
				if (($item['minute'] == $opt[0]) && ($item['hour'] == $opt[1])) {
					$update_cron = false;
				}
				$lightsquid_cron_entry = true;
				break;
			}
		}
		if ((!$on && $lightsquid_cron_entry) || $update_cron) {
			// remove old cronjobs first ...
			logger(LOG_INFO, localize_text("Removing old cronjobs..."), LOG_PREFIX_PKG_LIGHTSQUID);
			install_cron_job($lightsquid_parser_today, false);
			install_cron_job($lightsquid_parser_yesterday, false);
		}
		// ... and configure updated cronjobs if needed
		if ($on && $update_cron) {
			logger(LOG_INFO, localize_text("Updating cronjobs..."), LOG_PREFIX_PKG_LIGHTSQUID);
			install_cron_job($lightsquid_parser_today, $on, $opt[0], $opt[1], $opt[2], $opt[3], $opt[4], $opt[5]);
			// fix possible data lost with 00:00 script start - rescan yesterday
			install_cron_job($lightsquid_parser_yesterday, true, "15", "0", "*", "*", "*", "root");
		}
	} else {
		logger(LOG_INFO, localize_text("Removing all cronjobs..."), LOG_PREFIX_PKG_LIGHTSQUID);
		install_cron_job($lightsquid_parser_today, false);
		install_cron_job($lightsquid_parser_yesterday, false);
	}
}

/* Parse today's entires only in access.log via the GUI button */
function lightsquid_refresh_now() {
	$cmd = CRONTAB_LS_TEMPLATE . " today";
	$lg = LS_SQUIDLOG;
	lightsquid_create_reportdir();

	if (file_exists(LS_SQUIDLOGPATH . "/{$lg}")) {
		logger(LOG_INFO, localize_text("Parsing today's entries in %s using '%s'", 'access.log', $cmd), LOG_PREFIX_PKG_LIGHTSQUID);
		mwexec_bg($cmd);
	} else {
		logger(LOG_ERR, localize_text("Could not parse '%s' - log does not exist!", $lg), LOG_PREFIX_PKG_LIGHTSQUID);
	}
}

/* Parse all entries in all access logs, including the rotated ones via the GUI button */
function lightsquid_refresh_full() {
	$cmd = CRONTAB_LS_TEMPLATE;
	lightsquid_create_reportdir();

	logger(LOG_INFO, localize_text("Parsing all access log(s) entries using '%s'...", $cmd), LOG_PREFIX_PKG_LIGHTSQUID);
	// parse access.log
	$lg = LS_SQUIDLOG;
	if (file_exists(LS_SQUIDLOGPATH . "/{$lg}")) {
		logger(LOG_INFO, localize_text("Parsing log entries in '%s'...", $lg), LOG_PREFIX_PKG_LIGHTSQUID);
		mwexec_bg("{$cmd} {$lg}");
	} else {
		logger(LOG_ERR, localize_text("Could not parse '%s' - log does not exist!", $lg), LOG_PREFIX_PKG_LIGHTSQUID);
	}
	// parse access.log.x; if user configured some insane amount of rotated logs, only parse the first 100 of them
	for ($i = 0; $i < 100; $i++) {
		$lg = LS_SQUIDLOG . ".{$i}";
		if (file_exists(LS_SQUIDLOGPATH . "/{$lg}")) {
			logger(LOG_INFO, localize_text("Parsing log entries in '%s'...", $lg), LOG_PREFIX_PKG_LIGHTSQUID);
			mwexec_bg("{$cmd} {$lg}");
		} else {
			break;
		}
	}
}

/* Helper function to create lightsquid reports directory if needed */
function lightsquid_create_reportdir() {
	if (!is_dir(LS_REPORTPATH)) {
		logger(LOG_INFO, localize_text("Creating report dir %s", LS_REPORTPATH), LOG_PREFIX_PKG_LIGHTSQUID);
		safe_mkdir(LS_REPORTPATH);
	}
}


/*
 * Input validation
 * Check required Squid configuration and provide instructions to users,
 * instead of trying to mess with Squid's settings directly.
 */
function lightsquid_validate_input($post, &$input_errors) {
	if ($post['navto_lightsquid'] == 'Open Lightsquid') {
		$lightsquid_config = config_get_path('installedpackages/lightsquid/config/0', []);
		if (empty($lightsquid_config)) {
			return;
		}
		list($server_host, $server_port) = explode(':', $_SERVER['HTTP_HOST']);
		if (empty($lightsquid_config['lighttpd_ls_port'])) {
			$lightsquid_config['lighttpd_ls_port'] = "7445";
		}
		if (!isset($lightsquid_config['lighttpd_ls_ssl']) || ($lightsquid_config['lighttpd_ls_ssl'] == "on")) {
			$navto_proto = "https";
		} else {
			$navto_proto = "http";
		}
		header("Location: {$navto_proto}://{$server_host}:{$lightsquid_config['lighttpd_ls_port']}/");
		exit;
	}
	if ($post['navto_sqstat'] == 'Open sqstat') {
		header("Location: /sqstat/sqstat.php");
		exit;
	}
	/* Manual reports refresh; only allow to run if the configuration file exists already! */
	if (!empty($post['refreshnow']) || !empty($post['refreshfull'])) {
		$lsconf_file = LS_CONFIGPATH . "/" . LS_CONFIGFILE;
		if (file_exists($lsconf_file)) {
			if (!empty($post['refreshnow'])) {
				lightsquid_refresh_now();
				return;
			} elseif (!empty($post['refreshfull'])) {
				lightsquid_refresh_full();
				return;
			}
		} else {
			$input_errors[] = "Please, configure LightSquid package first and Save settings before trying to manually refresh reports.";
		}
	}

	/* Check whether Squid is configured at all */
	$squid_settings = config_get_path('installedpackages/squid/config/0', []);
	if (empty($squid_settings)) {
		$input_errors[] = "Please, configure Squid package 'General' settings first.";
	}

	/* Check whether logging is enabled in Squid */
	if ($squid_settings['log_enabled'] != "on") {
		$input_errors[] = "Please, enable Access Logging in Squid package 'General' settings first.";
	}

	/* Check whether log directory is configured in Squid */
	if ($squid_settings['log_dir'] == "") {
		$input_errors[] = "Please, configure Log Store Directory in Squid package 'General' settings first.";
	}

	/* SqStat - check that Squid listens on loopback unless the proxy is set as transparent (which makes it listen on localhost automatically) */
	$active_interfaces = config_get_path('installedpackages/squid/config/0/active_interface', '');
	$transparent = (config_get_path('installedpackages/squid/config/0/transparent_proxy', '') == 'on') ? true : false;
	if (!$transparent) {
		if (strpos($active_interfaces, "lo0") === false) {
			$input_errors[] = "Please, configure Squid - General - Proxy Interface(s) to include 'loopback' interface.";
		}
	}

	/* 'Skip URL(s)' validation */
	if ($post['lightsquid_skipurl'] != "") {
		$hosts = explode("|", $post['lightsquid_skipurl']);
		foreach ($hosts as $host) {
			$host = trim($host);
			if (!is_ipaddr($host) && !is_hostname($host) && !is_domain($host)) {
				$input_errors[] = "'Skip URL(s)' entry '{$host}' is not a valid IP address, hostname, domain or subnet.";
			}
		}
	}
}

?>
