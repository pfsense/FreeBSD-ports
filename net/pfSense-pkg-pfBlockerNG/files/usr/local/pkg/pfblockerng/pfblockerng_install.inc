<?php
/*
 * pfblockerng_install.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2019 BBcan177@gmail.com
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

require_once('pfsense-utils.inc');
require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');
require_once('/usr/local/www/pfblockerng/pfblockerng.php');

global $config, $g, $pfb;
pfb_global();

// Set 'Install flag' to skip sync process during installations.
$g['pfblockerng_install'] = true;

// MaxMind Database is no longer pre-installed during package installation
update_status("\nMaxMind GeoIP databases are not pre-installed during installation.\nTo utilize the MaxMind GeoIP functionalities, you will be required to register for a free MaxMind user account and access key. Review the IP tab: MaxMind Settings for more details.\n\n");

if (!file_exists('/usr/local/pkg/pfblockerng/pfblockerng_TopSpammers.xml')) {

$xml = <<<EOF
<?xml version="1.0" encoding="utf-8" ?>
<!DOCTYPE packagegui SYSTEM "../schema/packages.dtd">
<?xml-stylesheet type="text/xsl" href="../xsl/package.xsl"?>
<packagegui>
	<copyright>
	<![CDATA[
/* ========================================================================== */
/*
	pfblockerng_TopSpammers.xml (placeholder file pending MaxMind License registration) 

	pfBlockerNG
	Copyright (C) 2015-2019 BBcan177@gmail.com
	All rights reserved.

	Based upon pfblocker for pfSense
	Copyright (C) 2011 Marcello Coutinho
	All rights reserved.
*/
/* ========================================================================== */
/*
	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:


	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.


	THIS SOFTWARE IS PROVIDED ``AS IS`` AND ANY EXPRESS OR IMPLIED WARRANTIES,
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
/* ========================================================================== */
]]>
	</copyright>
	<name>pfblockerngtopspammers</name>
	<title>Firewall/pfBlockerNG</title>
	<menu>
		<name>pfBlockerNG: TopSpammers</name>
		<section>Firewall</section>
		<url>pkg_edit.php?xml=pfblockerng_topspammers.xml</url>
	</menu>
	<donotsave>Do not Save</donotsave>
	<tabs>
		<tab>
			<text>General</text>
			<url>/pkg_edit.php?xml=pfblockerng.xml</url>
		</tab>
		<tab>
			<text>Update</text>
			<url>/pfblockerng/pfblockerng_update.php</url>
		</tab>
		<tab>
			<text>Alerts</text>
			<url>/pfblockerng/pfblockerng_alerts.php</url>
		</tab>
		<tab>
			<text>Reputation</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_reputation.xml</url>
		</tab>
		<tab>
			<text>IPv4</text>
			<url>/pkg.php?xml=/pfblockerng/pfblockerng_v4lists.xml</url>
		</tab>
		<tab>
			<text>IPv6</text>
			<url>/pkg.php?xml=/pfblockerng/pfblockerng_v6lists.xml</url>
		</tab>
		<tab>
			<text>DNSBL</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_dnsbl.xml</url>
		</tab>
		<tab>
			<text>GeoIP</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_TopSpammers.xml</url>
		</tab>
		<tab>
			<text>Logs</text>
			<url>/pfblockerng/pfblockerng_log.php</url>
		</tab>
		<tab>
			<text>Sync</text>
			<url>/pkg_edit.php?xml=/pfblockerng/pfblockerng_sync.xml</url>
		</tab>
	</tabs>
	<note>
		<![CDATA[<span class="text-danger"><strong>
		MaxMind now requires a free Registered account to download the MaxMind GeoIP Database!
		Review the General Tab: MaxMind settings for more details.
		<br /><br />After saving the new MaxMind License Key, a Force Update is required to download the MaxMind database.
		<br /><br />Save button is disabled!
		</strong></span>]]>
	</note>
</packagegui>

EOF;
	@file_put_contents('/usr/local/pkg/pfblockerng/pfblockerng_TopSpammers.xml', $xml, LOCK_EX);
}

if ($pfb['keep'] == 'on' && isset($pfb['widgets']) && strpos($pfb['widgets'], 'pfblockerng') !== FALSE) {
	update_status("Restoring previous pfBlockerNG Widget settings...");
	// Restore previous widget setting if 'keep' is enabled.
	$config['widgets']['sequence'] = $pfb['widgets'];
	write_config('pfBlockerNG: Save widget');
} else {
	update_status("Adding pfBlockerNG Widget to the Dashboard...");
	$widgets = $config['widgets']['sequence'];
	if (strpos($widgets, 'pfblockerng') === FALSE) {
		if (empty($widgets)) {
			$config['widgets']['sequence'] = 'pfblockerng:col2:show';
		} else {
			$config['widgets']['sequence'] .= ',pfblockerng:col2:show';
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

	# Check if DNSBL is enabled
	dnsblcheck="\$(/usr/bin/grep '<pfb_dnsbl>' /conf/config.xml)"
	if [ -z "\${dnsblcheck}" ]; then
		exit
	fi

	# Start DNSBL Lighttpd webserver
	if [ -f /var/unbound/pfb_dnsbl_lighty.conf ]; then
		/usr/local/sbin/lighttpd_pfb -f /var/unbound/pfb_dnsbl_lighty.conf
	fi

	# Terminate DNSBL HTTPS Daemon if found
	pidnum="\$(/bin/ps -wax | /usr/bin/grep '[p]fblockerng.inc dnsbl' | /usr/bin/awk '{print \$1}')"
	if [ ! -z "\${pidnum}" ]; then
		for i in \${pidnum}; do
			/bin/kill -9 "\${i}"
		done
		/bin/sleep 2
	fi

	# Start DNSBL HTTPS Daemon
	/usr/local/bin/php -f /usr/local/pkg/pfblockerng/pfblockerng.inc dnsbl &

EOF;

$rc['stop'] = <<<EOF

	# Terminate DNSBL Lighttpd webserver, if found.
	pidnum="\$(/bin/pgrep lighttpd_pfb)"
	if [ ! -z "\${pidnum}" ]; then
		/usr/bin/killall lighttpd_pfb
	fi

	# Terminate DNSBL HTTPS Daemon, if found.
	pidnum="\$(/bin/ps -wax | /usr/bin/grep '[p]fblockerng.inc dnsbl' | /usr/bin/awk '{print \$1}')"
	if [ ! -z "\${pidnum}" ]; then
		for i in \${pidnum}; do
			/bin/kill -9 "\${i}"
		done
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

	// Create new DNSBL Certificate
	update_status("Creating DNSBL Certificate...");
	$dn = array (	'countryName'		=> 'CA',
			'stateOrProvinceName'	=> 'ST_DNSBL',
			'localityName'		=> 'LN_DNSBL',
			'organizationName'	=> 'ON_DNSBL',
			'organizationalUnitName'=> 'OU_DNSBL',
			'commonName'		=> 'CN_DNSBL',
			'emailAddress'		=> 'dnsbl@example.com'
			);

	$pkey	= openssl_pkey_new();
	$csr	= openssl_csr_new($dn, $pkey);
	$cert	= openssl_csr_sign($csr, NULL, $pkey, 3650);

	openssl_pkey_export($pkey, $privatekey);
	openssl_x509_export($cert, $publickey);
	@file_put_contents("{$pfb['dnsbl_cert']}", "{$privatekey}{$publickey}", LOCK_EX);
	update_status(" done.\n");

	update_status("Starting DNSBL Service...");
	restart_service('dnsbl');
	update_status(" done.\n");
}


// Upgrade - Adv. Inbound settings to new variable names.
update_status("Upgrading Adv. Inbound firewall rule settings ...");
$ufound = FALSE;
$upgrade_type = array('pfblockernglistsv4', 'pfblockernglistsv6', 'pfblockerngdnsblsettings', 'pfblockerngafrica', 'pfblockerngantarctica',
		    'pfblockerngasia', 'pfblockerngeurope', 'pfblockerngnorthamerica', 'pfblockerngoceania', 'pfblockerngsouthamerica',
		    'pfblockerngtopspammers', 'pfblockerngproxyandsatellite');

foreach ($upgrade_type as $type) {
	if (is_array($config['installedpackages'][$type]['config'])) {
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
}

if ($ufound) {
	write_config('pfBlockerNG: Upgrade Advanced Inbound Settings.');
	update_status(" saving new changes ... done.\n");
} else {
	update_status(" no changes required ... done.\n");
}

// Remove incorrect xml setting
if (isset($config['installedpackages']['pfblockerngantartica'])) {
	unset($config['installedpackages']['pfblockerngantartica']);
}

// Remove discontinued pfBlockerNG CRON job
install_cron_job('pfblockerng.php dc >>', false);

unset($g['pfblockerng_install']);	// Remove 'Install flag'
update_status("Custom commands completed ... ");
return TRUE;

?>
