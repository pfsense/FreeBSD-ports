<?php
/*
 * freeradius.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013 Alexander Wilke <nachtfalkeaw@web.de>
 * Copyright (c) 2013 Marcello Coutinho
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

require_once("config.inc");
require_once("filter.inc");
require_once("globals.inc");
require_once("notices.inc");
require_once("openvpn.inc");
require_once("pfsense-utils.inc");
require_once("pkg-utils.inc");
require_once("services.inc");
require_once("service-utils.inc");
require_once("util.inc");
require_once("xmlrpc_client.inc");

define('FREERADIUS_BASE', '/usr/local');
global $bash_path;
$bash_path = FREERADIUS_BASE . "/bin/bash";
define('FREERADIUS_EXAMPLES', FREERADIUS_BASE . '/share/examples/freeradius/raddb');
define('FREERADIUS_LIB', FREERADIUS_BASE . '/lib');
define('FREERADIUS_ETC', FREERADIUS_BASE . '/etc');
define('FREERADIUS_RADDB', FREERADIUS_ETC . '/raddb');
define('FREERADIUS_CERTS', FREERADIUS_RADDB . '/certs');
define('FREERADIUS_SCRIPTS', FREERADIUS_RADDB . '/scripts');
define('FREERADIUS_MODSAVAIL', FREERADIUS_RADDB . '/mods-available');
define('FREERADIUS_MODSENABLED', FREERADIUS_RADDB . '/mods-enabled');
define('FREERADIUS_SITESAVAIL', FREERADIUS_RADDB . '/sites-available');
define('FREERADIUS_SITESENABLED', FREERADIUS_RADDB . '/sites-enabled');
define('FREERADIUS_POLICYD', FREERADIUS_RADDB . '/policy.d');
define('FREERADIUS_PKGSOURCE', FREERADIUS_BASE . '/pkg');

/*
 * List of functions that directly call restart_service("radiusd")
 * (with optional parameters to be passed to avoid that behaviour)
 * freeradius_settings_resync($restart_svc = true)
 * freeradius_users_resync($via_rpc = false)
 * freeradius_authorizedmacs_resync($restart_svc = true, $via_rpc = false)
 * freeradius_clients_resync($restart_svc = true)
 * freeradius_eapconf_resync($restart_svc = true)
 * freeradius_modulesldap_resync($restart_svc = true)
 */

// Check freeradius lib version
function freeradius_get_libdir() {
	$frlib = "";
	if (is_dir(FREERADIUS_LIB)) {
		$libfiles = scandir(FREERADIUS_LIB);
		foreach ($libfiles as $libfile) {
			if (preg_match("/freeradius-/", $libfile)) {
				$frlib = FREERADIUS_LIB . '/' . $libfile;
			}
		}
	}
	if ($frlib == "") {
		log_error("freeRADIUS: No freeradius libs found on " . FREERADIUS_LIB);
	}
	return $frlib;
}

function freeradius_deinstall_command() {
	global $config;
	$pidFile = "/var/run/radiusd.pid";
	$i = 0;

	while (isvalidpid($pidFile) && $i < 3) {
		$sig = ($i == 2 ? SIGKILL : SIGTERM);
		sigkillbypid($pidFile, $sig);
		sleep(1);
		$i++;
	}

	/* Remove package settings from config if 'Keep Settings' is disabled */
	init_config_arr(array('installedpackages', 'freeradiussettings', 'config', 0));
	if (isset($config['installedpackages']['freeradiussettings']['config'][0]['keep_settings']) &&
	    empty($config['installedpackages']['freeradiussettings']['config'][0]['keep_settings'])) {
		log_error("[freeRADIUS] Removing all FreeRADIUS settings since 'Keep Settings/Data' is disabled...");
		$uninstall = array('freeradius', 'freeradiusauthorizedmacs', 'freeradiusclients', 'freeradiusinterfaces',
		'freeradiussettings', 'freeradiuseapconf', 'freeradiussqlconf', 'freeradiusmodulesldap', 'freeradiussync');
		foreach ($uninstall as $unin) {
			if (is_array($config['installedpackages'][$unin])) {
				unset($config['installedpackages'][$unin]);
			}
		}
		rmdir_recursive(FREERADIUS_RADDB, false);
		write_config("[freeRADIUS] Package uninstalled.");
	}
}

function freeradius_chown_recursive($dir, $user = "root", $group = "wheel") {
	if (empty($dir) || ($dir == '/') || ($dir == '/usr/local') || ($dir == '/usr/local/etc') || ($dir == '/usr/local/lib') || ($dir == '/var/log') || !is_dir($dir)) {
		log_error(gettext("freeRADIUS: Attempted to recursively chown an invalid directory: '{$dir}'"));
		return;
	}
	chown($dir, $user);
	chgrp($dir, $group);
	$handle = opendir($dir);
	if ($handle) {
		while (($item = readdir($handle)) !== false) {
			if (!empty($item) && ($item != ".") && ($item != "..")) {
				$path = "{$dir}/{$item}";
				if (is_file($path)) {
					chown($path, $user);
					chgrp($path, $group);
				}
			}
		}
	} else {
		log_error(gettext("freeRADIUS: freeradius_chown_recursive() call failed; permissions not set for directory: '{$dir}'"));
	}
}

function freeradius_upgrade_config() {
	global $config;

	// FreeRADIUS built-in certificate manager was removed (Bug #7170)
	if (is_array($config['installedpackages']['freeradiuscerts'])) {
		unset($config['installedpackages']['freeradiuscerts']);
		log_error(gettext("freeRADIUS: Removing deprecated built-in certificate manager configuration."));
	}
	if (is_array($config['installedpackages']['freeradiuseapconf']['config'][0])) {
		if (isset($config['installedpackages']['freeradiuseapconf']['config'][0]['vareapconfchoosecertmanager'])) {
			unset($config['installedpackages']['freeradiuseapconf']['config'][0]['vareapconfchoosecertmanager']);
		}
		if (isset($config['installedpackages']['freeradiuseapconf']['config'][0]['vareapconfprivatekeypassword'])) {
			unset($config['installedpackages']['freeradiuseapconf']['config'][0]['vareapconfprivatekeypassword']);
		}
	}
}

function freeradius_install_command() {
	global $config;

	// We create here different folders for different counters.
	safe_mkdir("/var/log/radacct/datacounter/daily");
	safe_mkdir("/var/log/radacct/datacounter/weekly");
	safe_mkdir("/var/log/radacct/datacounter/monthly");
	safe_mkdir("/var/log/radacct/datacounter/forever");
	safe_mkdir("/var/log/radacct/timecounter");
	if (!file_exists("/var/log/radutmp")) {
		touch("/var/log/radutmp");
	}
	if (!file_exists("/var/log/radwtmp")) {
		touch("/var/log/radwtmp");
	}

	if (is_link(FREERADIUS_RADDB)) {
		/* Previous package versions were creating a symlink targeting itself here */
		@unlink(FREERADIUS_RADDB);
	} elseif (is_dir(FREERADIUS_RADDB)) {
		/* Remove older FreeRADIUS config files, they will be copied from examples or rewritten by the package */
		rmdir_recursive(FREERADIUS_RADDB, false);
	}
	/* Copy stock FreeRADIUS 3.x raddb files */
	if (is_dir(FREERADIUS_EXAMPLES)) {
		mwexec('/bin/cp -RP ' . escapeshellarg(FREERADIUS_EXAMPLES) . ' ' . escapeshellarg(FREERADIUS_RADDB));
	}
	/* As a safety belt, create some important directories in case the above copy did not. */
	safe_mkdir(FREERADIUS_SCRIPTS);
	safe_mkdir(FREERADIUS_MODSENABLED);
	safe_mkdir(FREERADIUS_POLICYD);

	freeradius_chown_recursive(FREERADIUS_RADDB);
	freeradius_chown_recursive("/var/log/radacct");
	$frlib = freeradius_get_libdir();
	if (is_dir($frlib)) {
		freeradius_chown_recursive($frlib);
	}

	// Disable virtual-server we do not need by default
	unlink_if_exists(FREERADIUS_SITESENABLED . "/control-socket");
	unlink_if_exists(FREERADIUS_SITESENABLED . "/inner-tunnel");

	// Remove leftovers of the built-in certificate manager
	$exts = array("pem", "der", "csr", "crt", "key", "p12", "cnf");
	foreach ($exts as $ext) {
		unlink_if_exists(FREERADIUS_CERTS . "/ca.{$ext}");
		unlink_if_exists(FREERADIUS_CERTS . "/server.{$ext}");
		unlink_if_exists(FREERADIUS_CERTS . "/client.{$ext}");
	}
	unlink_if_exists(FREERADIUS_CERTS . "/serial*");
	unlink_if_exists(FREERADIUS_CERTS . "/index*");
	unlink_if_exists(FREERADIUS_CERTS . "/client.tar");
	// unlink_if_exists(FREERADIUS_CERTS . "/bootstrap");
	unlink_if_exists(FREERADIUS_CERTS . "/pfsense_cert_mgr");

	// Update configuration from old versions
	freeradius_upgrade_config();

	// We run this here just to suppress some warnings on syslog if file doesn't exist
	freeradius_authorizedmacs_resync(false, false);

	// These two functions create the module and the dictionary entry for Mobile-One-Time-Password
	freeradius_dictionary_resync();
	freeradius_modulesmotp_resync();
	freeradius_modulesgoogleauth_resync();

	// Here we create the modules and scripts for the datacounter
	freeradius_modulesdatacounter_resync();
	freeradius_datacounter_acct_resync();
	freeradius_datacounter_auth_resync();

	// Some initial module configuration
	freeradius_modulesmschap_resync();
	freeradius_modulesrealm_resync();
	freeradius_modulescounter_resync();

	// Initialize some config files - the functions below call other functions
	freeradius_sqlconf_resync();
	freeradius_eapconf_resync(false);
	freeradius_clients_resync(false);
	freeradius_modulesldap_resync(false);

	$rcfile = array();
	$rcfile['file'] = 'radiusd.sh';
	$freeradius_etc_dir = FREERADIUS_ETC;
	$rcfile['start'] = <<<EOD
SERVICENAME="radiusd"
	LOCKFILE="/tmp/\${SERVICENAME}_start.lock"
	PIDFILE="/var/run/\${SERVICENAME}.pid"

	# prevent this part of script from running in parallel
	if ( set -o noclobber; echo "\$\$" > "\$LOCKFILE") 2> /dev/null; then
		# make sure lock file is removed even if script is terminated
		trap 'rm -f "\$LOCKFILE"; exit \$?' INT TERM EXIT

		{$freeradius_etc_dir}/rc.d/radiusd onestart

		# try to wait until the service starts
		if [ ! -f "\$PIDFILE" ]; then
			echo "\$SERVICENAME.sh: PID file was not found"

			for i in 1 2 3 4 5; do
				if [ -f "\$PIDFILE" ]; then
					echo "\$SERVICENAME.sh: Service started PID: `cat \$PIDFILE`"
					break
				else
					echo "\$SERVICENAME.sh: Waiting 0.5 seconds"
					sleep 0.5
				fi
			done
		else
			echo "\$SERVICENAME.sh: Service running PID: `cat \$PIDFILE`"
		fi

		rm -f "\$LOCKFILE"
		trap - INT TERM EXIT
	else
		echo "\$SERVICENAME.sh: Cannot continue at this moment, this script is already trying to start service PID: \$(cat \$LOCKFILE)"
	fi

EOD;
	$rcfile['stop'] = FREERADIUS_ETC . '/rc.d/radiusd onestop';
	write_rcfile($rcfile);
}

function freeradius_settings_resync($restart_svc = true) {
	global $config;
	$conf = '';

	// put the constant to a variable
	$varFREERADIUS_BASE = FREERADIUS_BASE;

	$frlib = freeradius_get_libdir();

	// We do some checks of some folders which will be deleted after reboot on nanobsd systems
	safe_mkdir("/var/log/radacct/datacounter/daily");
	safe_mkdir("/var/log/radacct/datacounter/weekly");
	safe_mkdir("/var/log/radacct/datacounter/monthly");
	safe_mkdir("/var/log/radacct/datacounter/forever");
	safe_mkdir("/var/log/radacct/timecounter");
	if (!file_exists("/var/log/radutmp")) {
		touch("/var/log/radutmp");
	}
	if (!file_exists("/var/log/radwtmp")) {
		touch("/var/log/radwtmp");
	}

	if (is_array($config['installedpackages']['freeradiussettings']['config'][0])) {
		$varsettings = $config['installedpackages']['freeradiussettings']['config'][0];
	} else {
		$varsettings = array();
	}

	// Variables: General configuration
	$varsettingsmaxrequests = ($varsettings['varsettingsmaxrequests'] ?: '1024');
	$varsettingsmaxrequesttime = ($varsettings['varsettingsmaxrequesttime'] ?: '30');
	$varsettingscleanupdelay = ($varsettings['varsettingscleanupdelay']?: '5');
	$varsettingshostnamelookups = ($varsettings['varsettingshostnamelookups'] ?: 'no');
	$varsettingsallowcoredumps = ($varsettings['varsettingsallowcoredumps'] ?: 'no');
	$varsettingsregularexpressions = ($varsettings['varsettingsregularexpressions'] ?: 'yes');
	$varsettingsextendedexpressions = ($varsettings['varsettingsextendedexpressions'] ?:'yes');

	// Variables: Logging options
	$varsettingslogdir = ($varsettings['varsettingslogdir'] ?: 'syslog');
	$varsettingsauth = ($varsettings['varsettingsauth'] ?: 'yes');
	$varsettingsauthbadpass = ($varsettings['varsettingsauthbadpass'] ?: 'no');
	$varsettingsauthbadpassmessage = ($varsettings['varsettingsauthbadpassmessage'] ?: '');
	$varsettingsauthgoodpass = ($varsettings['varsettingsauthgoodpass'] ?: 'no');
	$varsettingsauthgoodpassmessage = ($varsettings['varsettingsauthgoodpassmessage'] ?: '');
	$varsettingsstrippednames = ($varsettings['varsettingsstrippednames'] ?: 'no');

	// Variables: Security
	$varsettingsmaxattributes = ($varsettings['varsettingsmaxattributes'] ?: '200');
	$varsettingsrejectdelay = ($varsettings['varsettingsrejectdelay'] ?: '1');

	// Variables: Thread Pool
	$varsettingsstartservers = ($varsettings['varsettingsstartservers'] ?: '5');
	$varsettingsmaxservers = ($varsettings['varsettingsmaxservers'] ?: '32');
	$varsettingsminspareservers = ($varsettings['varsettingsminspareservers'] ?: '3');
	$varsettingsmaxspareservers = ($varsettings['varsettingsmaxspareservers'] ?: '10');
	$varsettingsmaxqueuesize = ($varsettings['varsettingsmaxqueuesize'] ?: '65536');
	$varsettingsmaxrequestsperserver = ($varsettings['varsettingsmaxrequestsperserver'] ?: '0');

	// For more details look at freeradius_sqlconf_resync()
	if (is_array($config['installedpackages']['freeradiussqlconf']['config'][0])) {
		$sqlconf = $config['installedpackages']['freeradiussqlconf']['config'][0];
	} else {
		$sqlconf = array();
	}

	// Dis-/Enable SQL in "instatiate" section in freeradius_settings_resync() and radiusd.conf SQL SERVER 2
	if ($sqlconf['varsqlconf2includeenable'] == 'on') {
		$varsqlconf2instantiate = 'sql2';
	} else {
		$varsqlconf2instantiate = '### sql2 DISABLED ###';
	}

	$varsqlconf2failover = ($varsettings['varsqlconf2failover'] ?: 'redundant');

	// Dis-/Enable SQL module in freeradius_settings_resync() and radiusd.conf SQL SERVER 1
	if ($sqlconf['varsqlconfincludeenable'] == 'on') {
		$varsqlconfinstantiate = <<<EOD

	{$varsqlconf2failover} sql {
		sql1
		{$varsqlconf2instantiate}
	}

EOD;
	} else {
		/* Remove the sql files from the active modules directory when they are not needed. */
		@unlink_if_exists(FREERADIUS_MODSENABLED . '/sql');
		@unlink_if_exists(FREERADIUS_MODSENABLED . '/sqlcounter');
		freeradius_modulescounter_resync();
		$varsqlconf2failover = '';
		$varsqlconfinstantiate = <<<EOD
	#sql
	daily
	weekly
	monthly
	forever
EOD;
	}

	$conf .= <<<EOD
prefix = {$varFREERADIUS_BASE}
exec_prefix = \${prefix}
sysconfdir = \${prefix}/etc
localstatedir = /var
sbindir = \${exec_prefix}/sbin
logdir = \${localstatedir}/log
raddbdir = \${sysconfdir}/raddb
radacctdir = \${logdir}/radacct
name = radiusd
confdir = \${raddbdir}
modconfdir = \${confdir}/mods-config
certdir = \${confdir}/certs
cadir = \${confdir}/certs
run_dir = \${localstatedir}/run
db_dir = \${raddbdir}
libdir = {$frlib}
pidfile = \${run_dir}/\${name}.pid
max_request_time = {$varsettingsmaxrequesttime}
cleanup_delay = {$varsettingscleanupdelay}
max_requests = {$varsettingsmaxrequests}
hostname_lookups = {$varsettingshostnamelookups}
regular_expressions = {$varsettingsregularexpressions}
extended_expressions = {$varsettingsextendedexpressions}

log {
	destination = {$varsettingslogdir}
	colourise = yes
	file = \${logdir}/radius.log
	syslog_facility = daemon
	stripped_names = {$varsettingsstrippednames}
	auth = {$varsettingsauth}
	auth_badpass = {$varsettingsauthbadpass}
	auth_goodpass = {$varsettingsauthgoodpass}
	msg_goodpass = "{$varsettingsauthgoodpassmessage}"
	msg_badpass = "{$varsettingsauthbadpassmessage}"
	msg_denied = "You are already logged in - access denied"
}

checkrad = \${sbindir}/checkrad
security {
	allow_core_dumps = {$varsettingsallowcoredumps}
	max_attributes = {$varsettingsmaxattributes}
	reject_delay = {$varsettingsrejectdelay}
	status_server = no
	# Disable this check since it may not be accurate due to how FreeBSD patches OpenSSL
	allow_vulnerable_openssl = yes
}

\$INCLUDE  clients.conf
thread pool {
	start_servers = {$varsettingsstartservers}
	max_servers = {$varsettingsmaxservers}
	min_spare_servers = {$varsettingsminspareservers}
	max_spare_servers = {$varsettingsmaxspareservers}
	max_queue_size = {$varsettingsmaxqueuesize}
	max_requests_per_server = {$varsettingsmaxrequestsperserver}
	auto_limit_acct = no
}

modules {
	\$INCLUDE \${confdir}/mods-enabled/
}

instantiate {
	exec
	expr
	expiration
	logintime
	### Dis-/Enable sql instatiate
{$varsqlconfinstantiate}
}
policy {
	\$INCLUDE policy.d/
}
\$INCLUDE sites-enabled/

EOD;

	file_put_contents(FREERADIUS_RADDB . '/radiusd.conf', $conf);

	// freeradius_sqlconf_resync() is pointing to this function because we need
	// to run freeradius_serverdefault_resync() and after that restart freeradius.
	freeradius_plainmacauth_resync();
	freeradius_motp_resync();
	freeradius_serverdefault_resync();
	freeradius_modulesmschap_resync();

	// Change owner of freeradius created files
	if (is_dir("/var/log/radacct/")) {
		freeradius_chown_recursive("/var/log/radacct");
	}

	if ($restart_svc === true) {
		restart_service("radiusd");
	}
}

function freeradius_users_resync($via_rpc = false) {
	global $config;
	$conf = '';

	if (is_array($config['installedpackages']['freeradius']['config'])) {
		$arrusers = $config['installedpackages']['freeradius']['config'];
	} else {
		$arrusers = array();
	}

	if (!empty($arrusers)) {
		foreach ($arrusers as $users) {
			// Variables for users file defined parameters
			$varusersusername = $users['varusersusername'];
			$varuserspassword = $users['varuserspassword'];

			// Check password encryption
			$varuserspasswordencryption = ($users['varuserspasswordencryption'] ?: 'Cleartext-Password');
			switch ($varuserspasswordencryption) {
				case "MD5-Password":
					$varuserspassword = md5($varuserspassword);
					break;
				case "MD5-Password-hashed":
					$varuserspassword = $users['varuserspassword']; // use hashed password as-is
					$varuserspasswordencryption = "MD5-Password";
					break;
				case "NT-Password-hashed":
					$varuserspassword = $users['varuserspassword'];
					$varuserspasswordencryption = "NT-Password";
					break;
				default:
					$varuserspassword = $users['varuserspassword'];
			}

			$varusersauthmethod = $users['varusersauthmethod'];
			$varusersmotpinitsecret = $users['varusersmotpinitsecret'];
			$varusersmotppin = $users['varusersmotppin'];
			$varusersmotpoffset = ($users['varusersmotpoffset'] ?: '0');

			$varuserssimultaneousconnect = ($users['varuserssimultaneousconnect'] ?: '');
			$varuserswisprredirectionurl = $users['varuserswisprredirectionurl'];
			$varusersframedipaddress = $users['varusersframedipaddress'];
			$varusersframedipnetmask = $users['varusersframedipnetmask'];
			$varusersframedroute = $users['varusersframedroute'];
			if (is_ipaddrv6($users['varusersframedip6address'])) {
				$varusersframedip6address = $users['varusersframedip6address'];
			} elseif (is_subnetv6($users['varusersframedip6address'])) {
				$varusersframedip6prefix = $users['varusersframedip6address'];
			}
			$varusersframedip6route = $users['varusersframedip6route'];
			$varusersexpiration = $users['varusersexpiration'];
			$varuserssessiontimeout = $users['varuserssessiontimeout'];
			$varuserslogintime = $users['varuserslogintime'];
			$varusersvlanid = $users['varusersvlanid'];

			// GUI uses minutes but RADIUS needs seconds so we do a multiplication
			$varusersamountoftime = ($users['varusersamountoftime'] ?: '');
			$varusersamountoftime = (int) $varusersamountoftime * 60;
			$varuserspointoftime = $users['varuserspointoftime'];

			// GUI uses MB but RADIUS needs Bytes so we do a multiplication
			$varusersmaxtotaloctets = ($users['varusersmaxtotaloctets'] ?: '');
			$varusersmaxtotaloctets = (int) $varusersmaxtotaloctets * 1024 * 1024;
			$varusersmaxtotaloctetstimerange = $users['varusersmaxtotaloctetstimerange'];

			// GUI uses Kbit but RADIUS needs bits so we do a multiplication
			// Use 1000 (Kbit) and not 1024 (Kibibit) to match the division used by Captive Portal
			$varusersmaxbandwidthup = ($users['varusersmaxbandwidthup'] ?: '');
			$varusersmaxbandwidthup = (int) $varusersmaxbandwidthup * 1000;
			$varusersmaxbandwidthdown = ($users['varusersmaxbandwidthdown'] ?: '');
			$varusersmaxbandwidthdown = (int) $varusersmaxbandwidthdown * 1000;

			// Accounting-Interim-Interval - Must not be smaller than 60 and should be bigger than 600s
			if (($users['varusersacctinteriminterval'] >= '0') && ($users['varusersacctinteriminterval'] < '60')) {
				$varusersacctinteriminterval = 60;
			} else {
				$varusersacctinteriminterval = $users['varusersacctinteriminterval'];
			}

			// Clear variables for next user foreach additional options TOP
			$varuserstopadditionaloptions = '';
			$varusersadditionaloptionstop = '';

			if (!empty($users['varuserstopadditionaloptions'])) {
				$varuserstopadditionaloptions = explode("|", ($users['varuserstopadditionaloptions']));
				foreach ($varuserstopadditionaloptions as $toptmp) {
					$varusersadditionaloptionstop .= $toptmp . "\n";
				}
			}

			// Clear variables for next user foreach additional options: CHECK-ITEMS
			$varuserscheckitemsadditionaloptions = '';
			$varusersadditionaloptionscheckitems = '';

			if (!empty($users['varuserscheckitemsadditionaloptions'])) {
				$varuserscheckitemsadditionaloptions = explode("|", ($users['varuserscheckitemsadditionaloptions']));
				$varusersadditionaloptionscheckitems .= '';
				foreach ($varuserscheckitemsadditionaloptions as $checkitemtmp) {
					$varusersadditionaloptionscheckitems .= "{$checkitemtmp}" . " ";
				}
			}

			// Clear variables for next user foreach additional options: REPLY-ITEMS
			$varusersreplyitemsadditionaloptions = '';
			$varusersadditionaloptionsreplyitems = '';

			if (!empty($users['varusersreplyitemsadditionaloptions'])) {
				$varusersreplyitemsadditionaloptions = explode("|", ($users['varusersreplyitemsadditionaloptions']));
				$varusersadditionaloptionsreplyitems .= '';
				foreach ($varusersreplyitemsadditionaloptions as $replyitemtmp) {
					$varusersadditionaloptionsreplyitems .= $replyitemtmp . "\n\t";
				}
			}

			// Empty variable
			$varuserscheckitem = '';
			$varusersreplyitem = '';

			// check if mobile otp is disabled
			if ($users['varusersmotpenable'] == '') {
				// If someone does not want to use username/password but entry "DEFAULT" instead then we can disable this
				if (($users['varusersusername'] == '') && ($users['varuserspassword'] == '')) {
					$varuserscheckitem = '';
				} else {
					// Add the user attributes to each user.
					$varuserscheckitem = '"' . $varusersusername . '"' . " {$varuserspasswordencryption} := " . '"' . $varuserspassword .'"';
				}
			// end of check if otp is enabled
			} else {
				// if otp is enabled we need to set Auth-Type to accept because password will be checked when the otp script gets executed in reply-item list
				// prevent overwrite with '' on package update
				if (empty($varusersauthmethod)) {
					$varusersauthmethod = "motp";
				}
				$varuserscheckitem = "\"{$varusersusername}\" Auth-Type = {$varusersauthmethod}";
			}

			// Add additional CHECK-ITEMS here. Different formatting in "users" file needed.
			if ($varuserssimultaneousconnect != '') {
				$varuserscheckitem .= ", Simultaneous-Use := \"{$varuserssimultaneousconnect}\"";
			}
			if ($varusersexpiration != '') {
				$varuserscheckitem .= ", Expiration := \"{$varusersexpiration}\"";
			}
			if ($varuserslogintime != '') {
				$varuserscheckitem .= ", Login-Time := \"{$varuserslogintime}\"";
			}
			if ($varusersamountoftime != '') {
				$varuserscheckitem .= ", Max-{$varuserspointoftime}-Session := {$varusersamountoftime}";
			}
			if ($varusersadditionaloptionscheckitems != '') {
				$varuserscheckitem .= ", {$varusersadditionaloptionscheckitems}";
			}

			// this is the part for mobile otp
			if ($users['varusersmotpenable'] == 'on') {
				$varusersreplyitem .= <<<EOD

	MOTP-Init-Secret = {$varusersmotpinitsecret},
	MOTP-PIN = {$varusersmotppin},
	MOTP-Offset = {$varusersmotpoffset}
EOD;
			} else {
				$varusersreplyitem .= '';
			}

			// Add additional REPLY-ITEMS here. Different formatting in "users" file needed.
			if ($varusersframedipaddress != '') {
				if ($varusersreplyitem != '') {
					$varusersreplyitem .= ",";
				}
				$varusersreplyitem .= <<<EOD

	Framed-IP-Address = {$varusersframedipaddress}
EOD;
			}
			if ($varusersframedipnetmask != '') {
				if ($varusersreplyitem != '') {
					$varusersreplyitem .= ",";
				}
				$varusersreplyitem .= <<<EOD

	Framed-IP-Netmask = {$varusersframedipnetmask}
EOD;
			}
			if ($varusersframedroute != '') {
				if ($varusersreplyitem != '') {
					$varusersreplyitem .= ",";
				}
				$varusersreplyitem .= <<<EOD

	Framed-Route = "{$varusersframedroute}"
EOD;
			}
			if ($varusersframedip6address != '') {
				if ($varusersreplyitem != '') {
					$varusersreplyitem .= ",";
				}
				$varusersreplyitem .= <<<EOD

	Framed-IPv6-Address = {$varusersframedip6address}
EOD;
			}
			if ($varusersframedip6prefix != '') {
				if ($varusersreplyitem != '') {
					$varusersreplyitem .= ",";
				}
				$varusersreplyitem .= <<<EOD

	Framed-IPv6-Prefix = {$varusersframedip6prefix}
EOD;
			}
			if ($varusersframedip6route != '') {
				if ($varusersreplyitem != '') {
					$varusersreplyitem .= ",";
				}
				$varusersreplyitem .= <<<EOD

	Framed-IPv6-Route = "{$varusersframedip6route}"
EOD;
			}
			if ($varuserssessiontimeout != '') {
				if ($varusersreplyitem != '') {
					$varusersreplyitem .= ",";
				}
				$varusersreplyitem .= <<<EOD

	Session-Timeout := {$varuserssessiontimeout}
EOD;
			}
			if ($varusersvlanid != '') {
				if ($varusersreplyitem != '') {
					$varusersreplyitem .= ",";
				}
				$varusersreplyitem .= <<<EOD

	Tunnel-Type = VLAN,
	Tunnel-Medium-Type = IEEE-802,
	Tunnel-Private-Group-ID = "{$varusersvlanid}"
EOD;
			}
			if ($varusersmaxbandwidthup != '') {
				if ($varusersreplyitem != '') {
					$varusersreplyitem .= ",";
				}
				$varusersreplyitem .= <<<EOD

	WISPr-Bandwidth-Max-Up := {$varusersmaxbandwidthup}
EOD;
			}
			if ($varusersmaxbandwidthdown != '') {
				if ($varusersreplyitem != '') {
					$varusersreplyitem .= ",";
				}
				$varusersreplyitem .= <<<EOD

	WISPr-Bandwidth-Max-Down := {$varusersmaxbandwidthdown}
EOD;
			}
			if ($varusersacctinteriminterval != '') {
				if ($varusersreplyitem != '') {
					$varusersreplyitem .= ",";
				}
				$varusersreplyitem .= <<<EOD

	Acct-Interim-Interval := {$varusersacctinteriminterval}
EOD;
			}
			if ($varuserswisprredirectionurl != '') {
				if ($varusersreplyitem != '') {
					$varusersreplyitem .= ",";
				}
				$varusersreplyitem .= <<<EOD

	WISPr-Redirection-URL := {$varuserswisprredirectionurl}
EOD;
			}
			// If an octet limit is set we create the files for the limit and the counter. Further we call an exec script which checks if the limit is reached or not
			if ($varusersmaxtotaloctets != '') {
				if ($varusersreplyitem != '') {
					$varusersreplyitem .= ",";
				}
				// create exec script
				$varFREERADIUS_SCRIPTS = FREERADIUS_SCRIPTS;
				$varusersreplyitem .= <<<EOD

	pfSense-Max-Total-Octets := {$varusersmaxtotaloctets},

	Exec-Program-Wait = "/bin/sh {$varFREERADIUS_SCRIPTS}/datacounter_auth.sh {$varusersusername} {$varusersmaxtotaloctetstimerange}"
EOD;
				// create limit file - will be always overwritten so we can increase limit from GUI
				file_put_contents("/var/log/radacct/datacounter/{$varusersmaxtotaloctetstimerange}/max-octets-{$varusersusername}", $varusersmaxtotaloctets, LOCK_EX);
				// if used-octets file exist we do NOT overwrite this file!!!
				if (!file_exists("/var/log/radacct/datacounter/{$varusersmaxtotaloctetstimerange}/used-octets-{$varusersusername}")) {
					file_put_contents("/var/log/radacct/datacounter/{$varusersmaxtotaloctetstimerange}/used-octets-{$varusersusername}", "0", LOCK_EX);
				}
			} else {
				// If an octet limit is NOT set we delete the files for the limit and the counter.
				unlink_if_exists("/var/log/radacct/datacounter/{$varusersmaxtotaloctetstimerange}/max-octets-{$varusersusername}");
				unlink_if_exists("/var/log/radacct/datacounter/{$varusersmaxtotaloctetstimerange}/used-octets-{$varusersusername}*");
			}
			if ($varusersadditionaloptionsreplyitems != '') {
				if ($varusersreplyitem != '') {
					$varusersreplyitem .= ",";
				}
				$varusersreplyitem .= "\n\t{$varusersadditionaloptionsreplyitems}";
			}

			// Cosmetic fix - This is just to make a blank new line after each user entry
			$varusersreplyitem .= "\n\n";

			$conf .= <<<EOD
{$varusersadditionaloptionstop}
{$varuserscheckitem}
{$varusersreplyitem}

EOD;
		} // endforeach
	} // endif

	$filename = FREERADIUS_RADDB . '/users';
	file_put_contents($filename, $conf);
	chmod($filename, 0640);

	freeradius_sync_on_changes();
	// Do not restart on boot
	// Will get restarted later by freeradius_clients_resync() if called via XMLRPC sync
	if ($via_rpc === false && !platform_booting()) {
		restart_service("radiusd");
	}
}


function freeradius_authorizedmacs_resync($restart_svc = true, $via_rpc = false) {
	global $config;
	$conf = '';

	if (is_array($config['installedpackages']['freeradiusauthorizedmacs']['config'])) {
		$arrmacs = $config['installedpackages']['freeradiusauthorizedmacs']['config'];
	} else {
		$arrmacs = array();
	}

	if (!empty($arrmacs)) {
		foreach ($arrmacs as $macs) {
			// Variables for authorized_macs file defined parameters
			$varmacsaddress = str_replace(":", "-", $macs['varmacsaddress']);
			// We don't need a password but we need this field to make syntac correct for CHECK-ITEMS
			$varmacspassword = str_replace(":", "-", $macs['varmacsaddress']);

			$varmacssimultaneousconnect = ($macs['varmacssimultaneousconnect'] ?: '');
			$varmacsswisprredirectionurl = $macs['varmacsswisprredirectionurl'];
			$varmacsframedipaddress = $macs['varmacsframedipaddress'];
			$varmacsframedipnetmask = $macs['varmacsframedipnetmask'];
			$varmacsframedroute = $macs['varmacsframedroute'];
			if (is_ipaddrv6($macs['varmacsframedip6address'])) {
				$varmacsframedip6address = $macs['varmacsframedip6address'];
			} elseif (is_subnetv6($macs['varmacsframedip6address'])) {
				$varmacsframedip6prefix = $macs['varmacsframedip6address'];
			}
			$varmacsframedip6route = $macs['varmacsframedip6route'];
			$varmacsexpiration = $macs['varmacsexpiration'];
			$varmacssessiontimeout = $macs['varmacssessiontimeout'];
			$varmacslogintime = $macs['varmacslogintime'];
			$varmacsvlanid = $macs['varmacsvlanid'];

			// GUI uses minutes but RADIUS needs seconds so we do a multiplication
			$varmacsamountoftime = ($macs['varmacsamountoftime'] ?: '');
			$varmacsamountoftime = (int) $varmacsamountoftime * 60;
			$varmacspointoftime = $macs['varmacspointoftime'];

			// GUI uses MB but RADIUS needs Bytes so we do a multiplication
			$varmacsmaxtotaloctets = ($macs['varmacsmaxtotaloctets'] ?: '');
			$varmacsmaxtotaloctets = (int) $varmacsmaxtotaloctets * 1024 * 1024;
			$varmacsmaxtotaloctetstimerange = $macs['varmacsmaxtotaloctetstimerange'];

			// GUI uses KiloBit but RADIUS needs Bits so we do a multiplication
			$varmacsmaxbandwidthup = ($macs['varmacsmaxbandwidthup'] ?: '');
			$varmacsmaxbandwidthup = (int) $varmacsmaxbandwidthup * 1024;
			$varmacsmaxbandwidthdown = ($macs['varmacsmaxbandwidthdown'] ?: '');
			$varmacsmaxbandwidthdown = (int) $varmacsmaxbandwidthdown * 1024;

			// Accounting-Interim-Interval
			if (($users['varmacsacctinteriminterval'] >= '0') && ($users['varmacsacctinteriminterval'] < '60')) {
				$varmacsacctinteriminterval = 60;
			} else {
				$varmacsacctinteriminterval = $users['varmacsacctinteriminterval'];
			}

			// Clear variables for next mac foreach additional options TOP
			$varmacstopadditionaloptions = '';
			$varmacsadditionaloptionstop = '';

			if (!empty($macs['varmacstopadditionaloptions'])) {
				$varmacstopadditionaloptions = explode("|", ($macs['varmacstopadditionaloptions']));
				foreach ($varmacstopadditionaloptions as $toptmp) {
					$varmacsadditionaloptionstop .= $toptmp . "\n";
				}
			}

			// Clear variables for next mac foreach additional options: CHECK-ITEMS
			$varmacscheckitemsadditionaloptions = '';
			$varmacsadditionaloptionscheckitems = '';

			if (!empty($macs['varmacscheckitemsadditionaloptions'])) {
				$varmacscheckitemsadditionaloptions = explode("|", ($macs['varmacscheckitemsadditionaloptions']));
				$varmacsadditionaloptionscheckitems .= '';
				foreach ($varmacscheckitemsadditionaloptions as $checkitemtmp) {
					$varmacsadditionaloptionscheckitems .= "{$checkitemtmp} ";
				}
			}

			// Clear variables for next mac foreach additional options: REPLY-ITEMS
			$varmacsreplyitemsadditionaloptions = '';
			$varmacsadditionaloptionsreplyitems = '';

			if (!empty($macs['varmacsreplyitemsadditionaloptions'])) {
				$varmacsreplyitemsadditionaloptions = explode("|", ($macs['varmacsreplyitemsadditionaloptions']));
				$varmacsadditionaloptionsreplyitems .= '';
				foreach ($varmacsreplyitemsadditionaloptions as $replyitemtmp) {
					$varmacsadditionaloptionsreplyitems .= "{$replyitemtmp}\n\t";
				}
			}

			// Empty variable
			$varmacscheckitem = '';
			$varmacsreplyitem = '';

			// If someone does not want to use MAC address but entry "DEFAULT" instead then we can disable this
			if ($macs['varmacsaddress'] == '') {
				$varmacscheckitem = '';
			} else {
				// Add the user attributes to each user.
				$varmacscheckitem = "{$varmacsaddress} Cleartext-Password := \"{$varmacspassword}\"";
			}

			// Add additional CHECK-ITEMS here. Different formatting in "authorized_macs" file needed.
			if ($varmacssimultaneousconnect != '') {
				$varmacscheckitem .= ", Simultaneous-Use := \"{$varmacssimultaneousconnect}\"";
			}
			if ($varmacsexpiration != '') {
				$varmacscheckitem .= ", Expiration := \"{$varmacsexpiration}\"";
			}
			if ($varmacslogintime != '') {
				$varmacscheckitem .= ", Login-Time := \"{$varmacslogintime}\"";
			}
			if ($varmacsamountoftime != '') {
				$varmacscheckitem .= ", Max-{$varmacspointoftime}-Session := {$varmacsamountoftime}";
			}
			if ($varmacsadditionaloptionscheckitems != '') {
				$varmacscheckitem .= ", {$varmacsadditionaloptionscheckitems}";
			}

			// Add additional REPLY-ITEMS here. Different formatting in "authorized_macs" file needed.
			if ($varmacsframedipaddress != '') {
				if ($varmacsreplyitem != '') {
					$varmacsreplyitem .= ",";
				}
				$varmacsreplyitem .= <<<EOD

	Framed-IP-Address = {$varmacsframedipaddress}
EOD;
			}
			if ($varmacsframedipnetmask != '') {
				if ($varmacsreplyitem != '') {
					$varmacsreplyitem .= ",";
				}
				$varmacsreplyitem .= <<<EOD

	Framed-IP-Netmask = {$varmacsframedipnetmask}
EOD;
			}
			if ($varmacsframedroute != '') {
				if ($varmacsreplyitem != '') {
					$varmacsreplyitem .= ",";
				}
				$varmacsreplyitem .= <<<EOD

	Framed-Route = "{$varmacsframedroute}"
EOD;
			}
			if ($varmacsframedip6address != '') {
				if ($varmacsreplyitem != '') {
					$varmacsreplyitem .= ",";
				}
				$varmacsreplyitem .= <<<EOD

	Framed-IPv6-Address = {$varmacsframedip6address}
EOD;
			}
			if ($varmacsframedip6prefix != '') {
				if ($varmacsreplyitem != '') {
					$varmacsreplyitem .= ",";
				}
				$varmacsreplyitem .= <<<EOD

	Framed-IPv6-Prefix = {$varmacsframedip6prefix}
EOD;
			}
			if ($varmacsframedip6route != '') {
				if ($varmacsreplyitem != '') {
					$varmacsreplyitem .= ",";
				}
				$varmacsreplyitem .= <<<EOD

	Framed-IPv6-Route = "{$varmacsframedip6route}"
EOD;
			}
			if ($varmacssessiontimeout != '') {
				if ($varmacsreplyitem != '') {
					$varmacsreplyitem .= ",";
				}
				$varmacsreplyitem .= <<<EOD

	Session-Timeout := {$varmacssessiontimeout}
EOD;
			}
			if ($varmacsvlanid != '') {
				if ($varmacsreplyitem != '') {
					$varmacsreplyitem .= ",";
				}
				$varmacsreplyitem .= <<<EOD

	Tunnel-Type = VLAN,
	Tunnel-Medium-Type = IEEE-802,
	Tunnel-Private-Group-ID = "{$varmacsvlanid}"
EOD;
			}
			if ($varmacsmaxbandwidthup != '') {
				if ($varmacsreplyitem != '') {
					$varmacsreplyitem .= ",";
				}
				$varmacsreplyitem .= <<<EOD

	WISPr-Bandwidth-Max-Up := {$varmacsmaxbandwidthup}
EOD;
			}
			if ($varmacsmaxbandwidthdown != '') {
				if ($varmacsreplyitem != '') {
					$varmacsreplyitem .= ",";
				}
				$varmacsreplyitem .= <<<EOD

	WISPr-Bandwidth-Max-Down := {$varmacsmaxbandwidthdown}
EOD;
			}
			if ($varmacsacctinteriminterval != '') {
				if ($varmacsreplyitem != '') {
					$varmacsreplyitem .= ",";
				}
				$varmacsreplyitem .= <<<EOD

	Acct-Interim-Interval := {$varmacsacctinteriminterval}
EOD;
			}
			if ($varmacswisprredirectionurl != '') {
				if ($varmacsreplyitem != '') {
					$varmacsreplyitem .= ",";
				}
				$varmacsreplyitem .= <<<EOD

	WISPr-Redirection-URL := {$varmacsswisprredirectionurl}
EOD;
			}
			// If an octet limit is set we create the files for the limit and the counter. Further we call an exec script which checks if the limit is reached or not
			if ($varmacsmaxtotaloctets != '') {
				if ($varmacsreplyitem != '') {
					$varmacsreplyitem .= ",";
				}
				//create exec script
				$varFREERADIUS_SCRIPTS = FREERADIUS_SCRIPTS;
				$varmacsreplyitem .= <<<EOD

	pfSense-Max-Total-Octets := {$varmacsmaxtotaloctets},

	Exec-Program-Wait = "/bin/sh {$varFREERADIUS_SCRIPTS}/datacounter_auth.sh {$varmacsaddress} {$varmacsmaxtotaloctetstimerange}"
EOD;
				// create limit file - will be always overwritten so we can increase limit from GUI
				file_put_contents("/var/log/radacct/datacounter/{$varmacsmaxtotaloctetstimerange}/max-octets-{$varmacsaddress}", $varmacsmaxtotaloctets, LOCK_EX);
				// if used-octets file exist we do NOT overwrite this file!!!
				if (!file_exists("/var/log/radacct/datacounter/{$varmacsmaxtotaloctetstimerange}/used-octets-{$varmacsaddress}")) {
					file_put_contents("/var/log/radacct/datacounter/{$varmacsmaxtotaloctetstimerange}/used-octets-{$varmacsaddress}", "0", LOCK_EX);
				}
			} else {
				// If an octet limit is NOT set we delete the files for the limit and the counter.
				unlink_if_exists("/var/log/radacct/datacounter/{$varmacsmaxtotaloctetstimerange}/max-octets-{$varmacsaddress}");
				unlink_if_exists("/var/log/radacct/datacounter/{$varmacsmaxtotaloctetstimerange}/used-octets-{$varmacsaddress}*");
			}
			if ($varmacsadditionaloptionsreplyitems != '') {
				if ($varmacsreplyitem != '') {
					$varmacsreplyitem .= ",";
				}
				$varmacsreplyitem .= "\n\t{$varmacsadditionaloptionsreplyitems}";
			}

			// Cosmetic fix - This is just to make a blank new line after each macs entry
			$varmacsreplyitem .= "\n\n";

			$conf .= <<<EOD
{$varmacsadditionaloptionstop}
{$varmacscheckitem}
	{$varmacsreplyitem}

EOD;
		} // endforeach
	} // endif

	$filename = FREERADIUS_RADDB . '/authorized_macs';
	file_put_contents($filename, $conf);
	chmod($filename, 0640);

	freeradius_sync_on_changes();
	if ($restart_svc === true && $via_rpc === false) {
		restart_service("radiusd");
	}
}

function freeradius_clients_resync($restart_svc = true) {
	global $config;
	$conf = '';

	if (is_array($config['installedpackages']['freeradiusclients']['config'])) {
		$arrclients = $config['installedpackages']['freeradiusclients']['config'];
	} else {
		$arrclients = array();
	}

	if (!empty($arrclients)) {
		foreach ($arrclients as $item) {
			$varclientip = $item['varclientip'];
			// Bug #7836: Add single quotes to avoid breaking configuration with certain chars in secret
			$varclientsharedsecret = "'" . $item['varclientsharedsecret'] . "'";
			$varclientipversion = $item['varclientipversion'];
			$varclientshortname = $item['varclientshortname'];
			$varclientproto = $item['varclientproto'];
			$varrequiremessageauthenticator = $item['varrequiremessageauthenticator'];
			$varclientnastype = $item['varclientnastype'];
			$varclientmaxconnections = $item['varclientmaxconnections'];
			$varclientlogininput = ($item['varclientlogininput'] ?: '### login = !root ###');
			$varclientpasswordinput = ($item['varclientpasswordinput'] ?: '### password = someadminpass ###');

			if ($item['varclientlogininput'] == '') {
				$varclientlogin = '### login = !root ###';
			} else {
				$varclientlogin = "login = {$varclientlogininput}";
			}
			if ($item['varclientpasswordinput'] == '') {
				$varclientpassword = '### password = someadminpass ###';
			} else {
				$varclientpassword = "password = {$varclientpasswordinput}";
			}
			if (preg_match('/^(.+)\/(\d+)$/',$varclientip, $matches)) {
				$varclientip = $matches[1];
				$varclientprefix = '/' . $matches[2];
			} else {
				$varclientprefix = '';
			}

			$conf .= <<<EOD

client "{$varclientshortname}" {
	{$varclientipversion} = {$varclientip}{$varclientprefix}
	proto = {$varclientproto}
	secret = {$varclientsharedsecret}
	require_message_authenticator = {$varrequiremessageauthenticator}
	nas_type = {$varclientnastype}
	{$varclientlogin}
	{$varclientpassword}
	limit {
		max_connections = {$varclientmaxconnections}
		lifetime = 0
		idle_timeout = 30
	}
}

EOD;
		} // endforeach
	} else {
		$conf .= <<<EOD
client pfsense {
	ipaddr = 127.0.0.1
	secret = pfsense
	shortname = pfsense
	limit {
		max_connections = 16
		lifetime = 0
		idle_timeout = 30
	}
}

EOD;
	}

	file_put_contents(FREERADIUS_RADDB . '/clients.conf', $conf);

	freeradius_sync_on_changes();
	if ($restart_svc === true) {
		restart_service("radiusd");
	}
}



function freeradius_eapconf_resync($restart_svc = true) {
	global $config;
	$conf = '';

	if (!is_array($config['installedpackages']['freeradiuseapconf']['config'][0])) {
		$config['installedpackages']['freeradiuseapconf']['config'][0] = array();
	}
	$eapconf = & $config['installedpackages']['freeradiuseapconf']['config'][0];

	// Disable weak EAP types like MD5, GTC, LEAP
	if ($eapconf['vareapconfdisableweakeaptypes'] == '') {
		$vareapconfweakeaptypes = <<<EOD
	md5 {
	}
	leap {
	}
	gtc {
		#challenge = "Password: "
		auth_type = PAP
	}

EOD;

	} else {
		$vareapconfweakeaptypes = '### DISABLED WEAK EAP TYPES MD5, GTC, LEAP ###';
	}

	// Variables: EAP
	$vareapconfdefaulteaptype = ($eapconf['vareapconfdefaulteaptype'] ?: 'md5');
	$vareapconftimerexpire = ($eapconf['vareapconftimerexpire'] ?: '60');
	$vareapconfignoreunknowneaptypes = ($eapconf['vareapconfignoreunknowneaptypes'] ?: 'no');
	$vareapconfciscoaccountingusernamebug = ($eapconf['vareapconfciscoaccountingusernamebug'] ?: 'no');
	$vareapconfmaxsessions = ($eapconf['vareapconfmaxsessions'] ?: '4096');

	// Variables: EAP-TLS
	$vareapconffragmentsize = ($eapconf['vareapconffragmentsize'] ?: '1024');
	$vareapconfincludelength = ($eapconf['vareapconfincludelength'] ?: 'yes');
	$vareapconfcountry = ($eapconf['vareapconfcountry'] ?: '');
	$vareapconfstate = ($eapconf['vareapconfstate'] ?: '');
	$vareapconfcity = ($eapconf['vareapconfcity'] ?: '');
	$vareapconforganization = ($eapconf['vareapconforganization'] ?: '');
	$vareapconfemail = ($eapconf['vareapconfemail'] ?: '');
	$vareapconfcommonname = ($eapconf['vareapconfcommonname'] ?: 'internal-ca');

	// Variables: Cache
	$vareapconfcacheenablecache = ($eapconf['vareapconfcacheenablecache'] ?: 'no');
	$vareapconfcachelifetime = ($eapconf['vareapconfcachelifetime'] ?: '24');
	$vareapconfcachemaxentries = ($eapconf['vareapconfcachemaxentries'] ?: '255');

	// Variables OSCP
	$vareapconfocspenable = ($eapconf['vareapconfocspenable'] ?: 'no');
	$vareapconfocspoverridecerturl = ($eapconf['vareapconfocspoverridecerturl'] ?: 'no');
	$vareapconfocspurl = ($eapconf['vareapconfocspurl'] ?: 'http://127.0.0.1/ocsp/');

	// Variables: EAP-TTLS
	$vareapconfttlsdefaulteaptype = ($eapconf['vareapconfttlsdefaulteaptype'] ?: 'md5');
	$vareapconfttlscopyrequesttotunnel = ($eapconf['vareapconfttlscopyrequesttotunnel'] ?: 'no');
	$vareapconfttlsusetunneledreply = ($eapconf['vareapconfttlsusetunneledreply'] ? 1 : 0);
	$vareapconfttlsincludelength = ($eapconf['vareapconfttlsincludelength'] ?: 'yes');

	// Variables: EAP-PEAP with MSCHAPv2
	$vareapconfpeapdefaulteaptype = ($eapconf['vareapconfpeapdefaulteaptype'] ?: 'mschapv2');
	$vareapconfpeapcopyrequesttotunnel = ($eapconf['vareapconfpeapcopyrequesttotunnel'] ?: 'no');
	$vareapconfpeapusetunneledreply = ($eapconf['vareapconfpeapusetunneledreply'] ? 1 : 0);
	$vareapconfpeapsohenable = ($eapconf['vareapconfpeapsohenable'] ?: 'Disable');

	// This is for enable/disbable MS SoH in EAP-PEAP and the virtuial-server "soh-server"
	if ($eapconf['vareapconfpeapsohenable'] == 'Enable') {
		$vareapconfpeapsoh = <<<EOD

		soh = yes
		soh_virtual_server = "soh-server"

EOD;

		symlink(FREERADIUS_SITESAVAIL . "/soh", FREERADIUS_SITESENABLED);
	} else {
		$vareapconfpeapsoh = '### MS SoH Server is disabled ###';
		unlink_if_exists(FREERADIUS_SITESENABLED . "/soh");
	}


	// For pfSense cert manager
	$ca_cert = lookup_ca($eapconf["ssl_ca_cert"]);
	if ($ca_cert == false) {
		if (!is_array($config['ca'])) {
			$config['ca'] = array();
		}
		$a_ca =& $config['ca'];

		if (!is_array($config['cert'])) {
			$config['cert'] = array();
		}
		$a_cert =& $config['cert'];

		/* Generate CA with generic details */
		$ca = array();
		$ca['refid'] = uniqid();
		$ca['descr'] = "FreeRADIUS CA";
		$ca['serial'] = 0;
		$dn = array('commonName' => 'freeradius-temp-ca');
		if (!ca_create($ca, "2048", "3650", $dn, "sha256")) {
			file_notice("FreeRADIUS", gettext("Cannot create temporary FreeRADIUS certificate authority. Visit Services &gt; FreeRADIUS &gt; EAP tab and configure server certificates in the 'Certificates for TLS' section: " . openssl_error_string()));
			$cert_error = true;
		} else {
			$eapconf["ssl_ca_cert"] = $ca['refid'];
			$ca_cert = $ca;
			$a_ca[] = $ca;
			$cert = array();
			$cert['refid'] = uniqid();
			$cert['descr'] = "FreeRADIUS Server Certificate";

			/* Generate server certificate against that CA */
			$dn['commonName'] = "freeradius-temp-server";
			//$dn['subjectAltName'] = "IP:{$dn['commonName']}";
			if (!cert_create($cert, $ca['refid'], "2048", "3650", $dn, "server")) {
				file_notice("FreeRADIUS", gettext("Cannot create temporary FreeRADIUS certificate. Visit Services &gt; FreeRADIUS &gt; EAP tab and configure server certificates in the 'Certificates for TLS' section: " . openssl_error_string()));
				$cert_error = true;
			} else {
				$a_cert[] = $cert;
				$eapconf["ssl_server_cert"] = $cert['refid'];
			}
		}
	}
	if (($ca_cert != false) && !$cert_error) {
		if (base64_decode($ca_cert['prv'])) {
			file_put_contents(FREERADIUS_CERTS . "/ca_key.pem", base64_decode($ca_cert['prv']));
		}

		if (base64_decode($ca_cert['crt'])) {
			$crl_cert = lookup_crl($eapconf["ssl_ca_crl"]);
			if ($crl_cert != false) {
				crl_update($crl_cert);
				$crl = base64_decode($crl_cert['text']);
				$check_crl = "check_crl = yes";
			} else {
				$check_crl="check_crl = no";
			}
			file_put_contents(FREERADIUS_CERTS . "/ca_cert.pem", base64_decode($ca_cert['crt']) . "\n" . $crl);
		}

		$svr_cert = lookup_cert($eapconf["ssl_server_cert"]);
		if ($svr_cert != false) {
			if (base64_decode($svr_cert['prv'])) {
				file_put_contents(FREERADIUS_CERTS . "/server_key.pem", base64_decode($svr_cert['prv']));
			}
		}
		if (base64_decode($svr_cert['crt'])) {
			file_put_contents(FREERADIUS_CERTS . "/server_cert.pem", base64_decode($svr_cert['crt']));
		}
	}

	$vareapconfprivatekeyfile = 'server_key.pem';
	$vareapconfcertificatefile = 'server_cert.pem';
	$vareapconfcafile = 'ca_cert.pem';

	// generate new DH and RANDOM file if needed
	// use openvpn_create_dhparams() from openvpn.inc to generate DH params
	if (!file_exists(FREERADIUS_CERTS . "/dh")) {
		log_error("freeRADIUS: Creating new DH file in " . FREERADIUS_CERTS);
		file_put_contents(FREERADIUS_ETC . "/dh", openvpn_create_dhparams('1024'), LOCK_EX);
	}

	// check if the common name of the certificate must match the username
	if ($eapconf['vareapconfenablecheckcertcn'] == 'on') {
		$vareapconfcheckcertcn = 'check_cert_cn = %{User-Name}';
	} else {
		$vareapconfcheckcertcn = '### check_cert_cn = %{User-Name} ###';
	}

	// check if cert issuer of CA and certs match
	if ($eapconf['vareapconfenablecheckcertissuer'] == 'on') {
		$ca_subject = "";
		if (empty($eapconf['vareapconfcasubject'] &&
		    ($ca_cert != false) &&
		    !$cert_error)) {
			$ca_details = openssl_x509_parse(base64_decode($ca_cert['crt']));
			if (is_array($ca_details) && !empty($ca_details['name'])) {
				$ca_subject = $ca_details['name'];
			}
		} else {
			$ca_subject = trim($eapconf['vareapconfcasubject']);
		}
		if (!empty($ca_subject)) {
			$vareapconfcheckcertissuer = "check_cert_issuer = \"{$ca_subject}\"";
		}
	} else {
		$vareapconfcheckcertissuer = '### check_cert_issuer = "/C=GB/ST=Berkshire/L=Newbury/O=My Company Ltd/emailAddress=test@mycomp.com/CN=myca" ###';
	}

	$conf .= <<<EOD
### EAP
eap {
	default_eap_type = {$vareapconfdefaulteaptype}
	timer_expire     = {$vareapconftimerexpire}
	ignore_unknown_eap_types = {$vareapconfignoreunknowneaptypes}
	cisco_accounting_username_bug = {$vareapconfciscoaccountingusernamebug}
	max_sessions = {$vareapconfmaxsessions}

{$vareapconfweakeaptypes}

#	pwd {
#		group = 19
#		server_id = theserver@example.com
#		fragment_size = 1020
#		virtual_server = "inner-tunnel"
#	}

	tls-config tls-common {
		# private_key_password = whatever
		private_key_file = \${certdir}/{$vareapconfprivatekeyfile}
		certificate_file = \${certdir}/{$vareapconfcertificatefile}
		ca_path = \${confdir}/certs
		ca_file = \${ca_path}/{$vareapconfcafile}
	#	auto_chain = yes
	#	psk_identity = "test"
	#	psk_hexphrase = "036363823"
		dh_file = \${certdir}/dh
		random_file = /dev/urandom
		fragment_size = {$vareapconffragmentsize}
		include_length = {$vareapconfincludelength}
		{$check_crl}
		{$vareapconfcheckcertissuer}
		{$vareapconfcheckcertcn}
		cipher_list = "DEFAULT"
		cipher_server_preference = no
#		disable_tlsv1_2 = no
		ecdh_curve = "prime256v1"
		cache {
			enable = {$vareapconfcacheenablecache}
			lifetime = {$vareapconfcachelifetime}
			max_entries = {$vareapconfcachemaxentries}
			#name = "EAP module"
			#persist_dir = "${logdir}/tlscache"
		}
		verify {
	#		skip_if_ocsp_ok = no
	#		tmpdir = /tmp/radiusd
	#		client = "/path/to/openssl verify -CApath \${..ca_path} %{TLS-Client-Cert-Filename}"
		}
		ocsp {
			enable = {$vareapconfocspenable}
			override_cert_url = {$vareapconfocspoverridecerturl}
			url = "{$vareapconfocspurl}"
			# use_nonce = yes
			# timeout = 0
			# softfail = no
		}
	}
	tls {
		tls = tls-common
	#	virtual_server = check-eap-tls
	}
	ttls {
		tls = tls-common
		default_eap_type = {$vareapconfttlsdefaulteaptype}
		copy_request_to_tunnel = {$vareapconfttlscopyrequesttotunnel}
		include_length = {$vareapconfttlsincludelength}
	#	require_client_cert = yes
		virtual_server = "inner-tunnel-ttls"
		#use_tunneled_reply is deprecated, new method happens in virtual-server
	}	### end ttls
	peap {
		tls = tls-common
		default_eap_type = {$vareapconfpeapdefaulteaptype}
		copy_request_to_tunnel = {$vareapconfpeapcopyrequesttotunnel}
	#	proxy_tunneled_request_as_eap = yes
	#	require_client_cert = yes
{$vareapconfpeapsoh}

		virtual_server = "inner-tunnel-peap"
		#use_tunneled_reply is deprecated, new method happens in virtual-server
	}
	mschapv2 {
#		send_error = no
#		identity = "FreeRADIUS"
	}
#	fast {
#		tls = tls-common
#		pac_lifetime = 604800
#		authority_identity = "1234"
#		pac_opaque_key = "0123456789abcdef0123456789ABCDEF"
#		virtual_server = inner-tunnel
#	}
}

EOD;

	$filename = FREERADIUS_MODSENABLED . '/eap';
	/* Remove what could be a symlink first before writing */
	@unlink_if_exists($filename);
	safe_mkdir(FREERADIUS_MODSENABLED);
	file_put_contents($filename, $conf);
	chmod($filename, 0640);

	/* Write out TTLS inner tunnel server */
	$conf = <<<EOD

server inner-tunnel-ttls {
listen {
	ipaddr = 127.0.0.1
	port = 18127
	type = auth
}
authorize {
#	filter_username
#	filter_inner_identity
	chap
	mschap
#	unix
#	IPASS
#	ntdomain
	update control {
		&Proxy-To-Realm := LOCAL
	}
	eap {
		ok = return
	}
	files
	-sql
#	smbpasswd
	-ldap
#	daily
	expiration
	logintime
	pap
}

authenticate {
	Auth-Type PAP {
		pap
	}
	Auth-Type CHAP {
		chap
	}
	Auth-Type MS-CHAP {
		mschap
	}
	mschap
#	pam
#	Auth-Type LDAP {
#		ldap
#	}
	eap
}

session {
	radutmp
#	sql
}

post-auth {
#       cui-inner
#	reply_log
	-sql
#	ldap
#	moonshot_host_tid
#	moonshot_realm_tid
#	moonshot_coi_tid
	if ($vareapconfttlsusetunneledreply) {
		update reply {
			User-Name !* ANY
			Message-Authenticator !* ANY
			EAP-Message !* ANY
			Proxy-State !* ANY
			MS-MPPE-Encryption-Types !* ANY
			MS-MPPE-Encryption-Policy !* ANY
			MS-MPPE-Send-Key !* ANY
			MS-MPPE-Recv-Key !* ANY
		}
		update {
			&outer.session-state: += &reply:
		}
	}

	Post-Auth-Type REJECT {
		-sql
		attr_filter.access_reject
		update outer.session-state {
			&Module-Failure-Message := &request:Module-Failure-Message
		}
	}
}

pre-proxy {
#	files
#	attr_filter.pre-proxy
#	pre_proxy_log
}

post-proxy {
#	post_proxy_log
#	attr_filter.post-proxy
	eap
}

} # inner-tunnel-ttls server block

EOD;

	$filename = FREERADIUS_SITESENABLED . '/inner-tunnel-ttls';
	/* Remove what could be a symlink first before writing */
	@unlink_if_exists($filename);
	file_put_contents($filename, $conf);
	chmod($filename, 0640);

	/* Write out PEAP inner tunnel server */
	$conf = <<<EOD

server inner-tunnel-peap {
listen {
	ipaddr = 127.0.0.1
	port = 18128
	type = auth
}
authorize {
#	filter_username
#	filter_inner_identity
	chap
	mschap
#	unix
#	IPASS
#	ntdomain
	update control {
		&Proxy-To-Realm := LOCAL
	}
	eap {
		ok = return
	}
	files
	-sql
#	smbpasswd
	-ldap
#	daily
	expiration
	logintime
	pap
}

authenticate {
	Auth-Type PAP {
		pap
	}
	Auth-Type CHAP {
		chap
	}
	Auth-Type MS-CHAP {
		mschap
	}
	mschap
#	pam
#	Auth-Type LDAP {
#		ldap
#	}
	eap
}

session {
	radutmp
#	sql
}

post-auth {
#       cui-inner
#	reply_log
	-sql
#	ldap
#	moonshot_host_tid
#	moonshot_realm_tid
#	moonshot_coi_tid
	if ($vareapconfpeapusetunneledreply) {
		update reply {
			User-Name !* ANY
			Message-Authenticator !* ANY
			EAP-Message !* ANY
			Proxy-State !* ANY
			MS-MPPE-Encryption-Types !* ANY
			MS-MPPE-Encryption-Policy !* ANY
			MS-MPPE-Send-Key !* ANY
			MS-MPPE-Recv-Key !* ANY
		}
		update {
			&outer.session-state: += &reply:
		}
	}

	Post-Auth-Type REJECT {
		-sql
		attr_filter.access_reject
		update outer.session-state {
			&Module-Failure-Message := &request:Module-Failure-Message
		}
	}
}

pre-proxy {
#	files
#	attr_filter.pre-proxy
#	pre_proxy_log
}

post-proxy {
#	post_proxy_log
#	attr_filter.post-proxy
	eap
}

} # inner-tunnel-peap server block

EOD;

	$filename = FREERADIUS_SITESENABLED . '/inner-tunnel-peap';
	/* Remove what could be a symlink first before writing */
	@unlink_if_exists($filename);
	file_put_contents($filename, $conf);
	chmod($filename, 0640);


	if ($restart_svc === true) {
		restart_service("radiusd");
	}
}

// Gets started from freeradiuseapconf.xml
function freeradius_get_ca_crl() {
	global $config;
	$crl_arr = array();
	$crl_arr[] = array('refid' => 'none', 'descr' => 'none');

	if (is_array($config['crl'])) {
		foreach ($config['crl'] as $crl) {
			$crl_arr[] = array('refid' => $crl['refid'], 'descr' => $crl['descr']);
		}
	}
	return $crl_arr;
}

function freeradius_sqlconf_resync() {
	global $config;
	$conf = '';

	if (is_array($config['installedpackages']['freeradiussqlconf']['config'][0])) {
		$sqlconf = $config['installedpackages']['freeradiussqlconf']['config'][0];
	} else {
		$sqlconf = array();
	}

	// Variables: SQL DATABASE 1
	$varsqlconfdatabase = ($sqlconf['varsqlconfdatabase'] ?: 'mysql');
	$varsqlconfserver = ($sqlconf['varsqlconfserver'] ?: 'localhost');
	$varsqlconfport = ($sqlconf['varsqlconfport'] ?: '3306');
	$varsqlconflogin = ($sqlconf['varsqlconflogin'] ?: 'radius');
	$varsqlconfpassword = ($sqlconf['varsqlconfpassword'] ?: 'radpass');
	$varsqlconfradiusdb = ($sqlconf['varsqlconfradiusdb'] ?: 'radius');
	$varsqlconfaccttable1 = ($sqlconf['varsqlconfaccttable1'] ?: 'radacct');
	$varsqlconfaccttable2 = ($sqlconf['varsqlconfaccttable2'] ?: 'radacct');
	$varsqlconfpostauthtable = ($sqlconf['varsqlconfpostauthtable'] ?: 'radpostauth');
	$varsqlconfauthchecktable = ($sqlconf['varsqlconfauthchecktable'] ?: 'radcheck');
	$varsqlconfauthreplytable = ($sqlconf['varsqlconfauthreplytable'] ?: 'radreply');
	$varsqlconfgroupchecktable = ($sqlconf['varsqlconfgroupchecktable'] ?: 'radgroupcheck');
	$varsqlconfgroupreplytable = ($sqlconf['varsqlconfgroupreplytable'] ?: 'radgroupreply');
	$varsqlconfusergrouptable = ($sqlconf['varsqlconfusergrouptable'] ?: 'radusergroup');
	$varsqlconfreadgroups = ($sqlconf['varsqlconfreadgroups'] ?: 'yes');
	$varsqlconfdeletestalesessions = ($sqlconf['varsqlconfdeletestalesessions'] ?: 'yes');
	$varsqlconfsqltrace = ($sqlconf['varsqlconfsqltrace'] ? '${logdir}/sqltrace.sql' : '');
	$varsqlconfnumsqlsocks = ($sqlconf['varsqlconfnumsqlsocks'] ?: '5');
	$varsqlconfconnectfailureretrydelay = ($sqlconf['varsqlconfconnectfailureretrydelay'] ?: '60');
	$varsqlconflifetime = ($sqlconf['varsqlconflifetime'] ?: '0');
	$varsqlconfmaxqueries = ($sqlconf['varsqlconfmaxqueries'] ?: '0');
	$varsqlconfreadclients = ($sqlconf['varsqlconfreadclients'] ?: 'yes');
	$varsqlconfnastable = ($sqlconf['varsqlconfnastable'] ?: 'nas');

	// Additional changes were made in freeradius_settings_resync()

	// Variables: SQL DATABASE 2
	$varsqlconf2database = ($sqlconf['varsqlconf2database'] ?: 'mysql');
	$varsqlconf2server = ($sqlconf['varsqlconf2server'] ?: 'localhost');
	$varsqlconf2port = ($sqlconf['varsqlconf2port'] ?: '3306');
	$varsqlconf2login = ($sqlconf['varsqlconf2login'] ?: 'radius');
	$varsqlconf2password = ($sqlconf['varsqlconf2password'] ?: 'radpass');
	$varsqlconf2radiusdb = ($sqlconf['varsqlconf2radiusdb'] ?: 'radius');
	$varsqlconf2accttable1 = ($sqlconf['varsqlconf2accttable1'] ?: 'radacct');
	$varsqlconf2accttable2 = ($sqlconf['varsqlconf2accttable2'] ?: 'radacct');
	$varsqlconf2postauthtable = ($sqlconf['varsqlconf2postauthtable'] ?: 'radpostauth');
	$varsqlconf2authchecktable = ($sqlconf['varsqlconf2authchecktable'] ?: 'radcheck');
	$varsqlconf2authreplytable = ($sqlconf['varsqlconf2authreplytable'] ?: 'radreply');
	$varsqlconf2groupchecktable = ($sqlconf['varsqlconf2groupchecktable'] ?: 'radgroupcheck');
	$varsqlconf2groupreplytable = ($sqlconf['varsqlconf2groupreplytable'] ?: 'radgroupreply');
	$varsqlconf2usergrouptable = ($sqlconf['varsqlconf2usergrouptable'] ?: 'radusergroup');
	$varsqlconf2readgroups = ($sqlconf['varsqlconf2readgroups'] ?: 'yes');
	$varsqlconf2deletestalesessions = ($sqlconf['varsqlconf2deletestalesessions'] ?: 'yes');
	$varsqlconf2sqltrace = ($sqlconf['varsqlconfsqltrace'] ? '${logdir}/sqltrace.sql' : '');
	$varsqlconf2numsqlsocks = ($sqlconf['varsqlconf2numsqlsocks'] ?: '5');
	$varsqlconf2connectfailureretrydelay = ($sqlconf['varsqlconf2connectfailureretrydelay'] ?: '60');
	$varsqlconf2lifetime = ($sqlconf['varsqlconf2lifetime'] ?: '0');
	$varsqlconf2maxqueries = ($sqlconf['varsqlconf2maxqueries'] ?: '0');
	$varsqlconf2readclients = ($sqlconf['varsqlconf2readclients'] ?: 'yes');
	$varsqlconf2nastable = ($sqlconf['varsqlconf2nastable'] ?: 'nas');

	// Additional changes were made in freeradius_settings_resync()

	$conf .= <<<EOD

sql sql1 {
	database = "{$varsqlconfdatabase}"
	driver = "rlm_sql_\${database}"
	dialect = "\${database}"
	server = "{$varsqlconfserver}"
	port = {$varsqlconfport}
	login = "{$varsqlconflogin}"
	password = "{$varsqlconfpassword}"
	radius_db = "{$varsqlconfradiusdb}"
	acct_table1 = "{$varsqlconfaccttable1}"
	acct_table2 = "{$varsqlconfaccttable2}"
	postauth_table = "{$varsqlconfpostauthtable}"
	authcheck_table = "{$varsqlconfauthchecktable}"
	authreply_table = "{$varsqlconfauthreplytable}"
	groupcheck_table = "{$varsqlconfgroupchecktable}"
	groupreply_table = "{$varsqlconfgroupreplytable}"
	usergroup_table = "{$varsqlconfusergrouptable}"
	read_groups = {$varsqlconfreadgroups}
	delete_stale_sessions = {$varsqlconfdeletestalesessions}
	logfile = {$varsqlconfsqltrace}
	read_clients = {$varsqlconfreadclients}
	client_table = "{$varsqlconfnastable}"
	pool {
		start = \${thread[pool].start_servers}
		min = \${thread[pool].min_spare_servers}
		max = {$varsqlconfnumsqlsocks}
		spare = \${thread[pool].max_spare_servers}
		uses = {$varsqlconfmaxqueries}
		retry_delay = {$varsqlconfconnectfailureretrydelay}
		lifetime = {$varsqlconflifetime}
		idle_timeout = 60
	}
	group_attribute = "\${.:instance}-SQL-Group"
	\$INCLUDE \${modconfdir}/\${.:name}/main/\${dialect}/queries.conf
}

EOD;
	if ($sqlconf['varsqlconf2includeenable'] == 'on') {
		$conf .= <<<EOD
sql sql2 {
	database = "{$varsqlconf2database}"
	driver = "rlm_sql_\${database}"
	dialect = "\${database}"
	server = "{$varsqlconf2server}"
	port = {$varsqlconf2port}
	login = "{$varsqlconf2login}"
	password = "{$varsqlconf2password}"
	radius_db = "{$varsqlconf2radiusdb}"
	acct_table1 = "{$varsqlconf2accttable1}"
	acct_table2 = "{$varsqlconf2accttable2}"
	postauth_table = "{$varsqlconf2postauthtable}"
	authcheck_table = "{$varsqlconf2authchecktable}"
	authreply_table = "{$varsqlconf2authreplytable}"
	groupcheck_table = "{$varsqlconf2groupchecktable}"
	groupreply_table = "{$varsqlconf2groupreplytable}"
	usergroup_table = "{$varsqlconf2usergrouptable}"
	read_groups = {$varsqlconf2readgroups}
	delete_stale_sessions = {$varsqlconf2deletestalesessions}
	logfile = {$varsqlconf2sqltrace}
	read_clients = {$varsqlconf2readclients}
	client_table = "{$varsqlconf2nastable}"
	pool {
		start = \${thread[pool].start_servers}
		min = \${thread[pool].min_spare_servers}
		max = {$varsqlconf2numsqlsocks}
		spare = \${thread[pool].max_spare_servers}
		uses = {$varsqlconf2maxqueries}
		retry_delay = {$varsqlconf2connectfailureretrydelay}
		lifetime = {$varsqlconf2lifetime}
		idle_timeout = 60
	}
	group_attribute = "\${.:instance}-SQL-Group"
	\$INCLUDE \${modconfdir}/\${.:name}/main/\${dialect}/queries.conf
}

EOD;
	}

	$filename = FREERADIUS_MODSENABLED . '/sql';
	/* Remove what could be a symlink first before writing */
	@unlink_if_exists($filename);
	safe_mkdir(FREERADIUS_MODSENABLED);
	file_put_contents($filename, $conf);
	chmod($filename, 0640);

	/* SQL Counters */
	$conf = <<<EOD
sqlcounter dailycounter {
	sql_module_instance = sql
	dialect = "{$varsqlconf2database}"
	counter_name = Daily-Session-Time
	check_name = Max-Daily-Session
	reply_name = Session-Timeout
	key = User-Name
	reset = daily
	\$INCLUDE \${modconfdir}/sql/counter/\${dialect}/\${.:instance}.conf
}

sqlcounter monthlycounter {
	sql_module_instance = sql
	dialect = "{$varsqlconf2database}"
	counter_name = Monthly-Session-Time
	check_name = Max-Monthly-Session
	reply_name = Session-Timeout
	key = User-Name
	reset = monthly
	\$INCLUDE \${modconfdir}/sql/counter/\${dialect}/\${.:instance}.conf
}

sqlcounter noresetcounter {
	sql_module_instance = sql
	dialect = "{$varsqlconf2database}"
	counter_name = Max-All-Session-Time
	check_name = Max-All-Session
	key = User-Name
	reset = never
	\$INCLUDE \${modconfdir}/sql/counter/\${dialect}/\${.:instance}.conf
}

sqlcounter expire_on_login {
	sql_module_instance = sql
	dialect = "{$varsqlconf2database}"
	counter_name = Expire-After-Initial-Login
	check_name = Expire-After
	key = User-Name
	reset = never
	\$INCLUDE \${modconfdir}/sql/counter/\${dialect}/\${.:instance}.conf
}


EOD;

	$filename = FREERADIUS_MODSENABLED . '/sqlcounter';
	/* Remove what could be a symlink first before writing */
	@unlink_if_exists($filename);

	/* Only write this out if accounting is enabled on one of the SQL instances */
	if ((($sqlconf['varsqlconfincludeenable'] == 'on') && ($sqlconf['varsqlconfenableaccounting'] == 'Enable')) ||
	    (($sqlconf['varsqlconf2includeenable'] == 'on') && ($sqlconf['varsqlconf2enableaccounting'] == 'Enable'))) {
		safe_mkdir(FREERADIUS_MODSENABLED);
		file_put_contents($filename, $conf);
		chmod($filename, 0640);
		/* counter and sqlcounter are incompatible */
		@unlink_if_exists(FREERADIUS_MODSENABLED . '/counter');
	} else {
		freeradius_modulescounter_resync();
	}

	// We don't need a restart at this time because there are
	// additional changes needed in freeradius_settings_resync()
	freeradius_settings_resync();
}

function freeradius_serverdefault_resync() {
	global $config;
	$conf = '';

	// Get Variables from freeradiusmodulesldap.xml
	if (is_array($config['installedpackages']['freeradiusmodulesldap']['config'][0])) {
		$arrmodulesldap = $config['installedpackages']['freeradiusmodulesldap']['config'][0];
	} else {
		$arrmodulesldap = array();
	}

	// failover/loadbalancing mode
	$varmodulesldap2failover = ($arrmodulesldap['varmodulesldap2failover'] ?: 'redundant');

	// If unchecked then disable authorize ldap2
	if (!$arrmodulesldap['varmodulesldap2enableauthorize']) {
		$varmodulesldap2enableauthorize = '### ldap2 disabled ###';
	} else {
		$varmodulesldap2enableauthorize = 'ldap2';
	}

	// If unchecked then disable authorize ldap1
	if (!$arrmodulesldap['varmodulesldapenableauthorize']) {
		$varmodulesldapenableauthorize = '### ldap ###';
	} else {
		$varmodulesldapenableauthorize = <<<EOD

{$varmodulesldap2failover} {
	ldap
	# this line adds ldap2 when activated
	{$varmodulesldap2enableauthorize}
}

EOD;
	}

	// If unchecked then disable authenticate for ldap1
	if (!$arrmodulesldap['varmodulesldap2enableauthenticate']) {
		$varmodulesldap2enableauthenticate = "### ldap2 disabled ###";
	} else {
		$varmodulesldap2enableauthenticate = "ldap2";
	}

	// If unchecked then disable authenticate ldap2
	if (!$arrmodulesldap['varmodulesldapenableauthenticate']) {
		$varmodulesldapenableauthenticate = <<<EOD

	#Auth-Type LDAP {
		#ldap
		#{$varmodulesldap2enableauthenticate}
	#}

EOD;
	} else {
		$varmodulesldapenableauthenticate = <<<EOD

	Auth-Type LDAP {
		ldap
		{$varmodulesldap2enableauthenticate}
	}

EOD;
	}

	/* If neither LDAP mode or server is configured, do not activate the LDAP module */
	if (!$arrmodulesldap['varmodulesldapenableauthorize'] && !$arrmodulesldap['varmodulesldapenableauthenticate'] &&
	    !$arrmodulesldap['varmodulesldap2enableauthorize'] && !$arrmodulesldap['varmodulesldap2enableauthenticate']) {
		/* Deactivate LDAP module if it is not enabled */
		@unlink_if_exists(FREERADIUS_MODSENABLED . '/ldap');
	}


	// Get Variables from freeradiussqlconf.xml for DATABASE 1
	if (is_array($config['installedpackages']['freeradiussqlconf']['config'][0])) {
		$sqlconf = $config['installedpackages']['freeradiussqlconf']['config'][0];
	} else {
		$sqlconf = array();
	}
	$varsqlconfenableauthorize = ($sqlconf['varsqlconfenableauthorize'] ?: 'Disable');
	$varsqlconfenableaccounting = ($sqlconf['varsqlconfenableaccounting'] ?: 'Disable');
	$varsqlconfenablesession = ($sqlconf['varsqlconfenablesession'] ?: 'Disable');
	$varsqlconfenablepostauth = ($sqlconf['varsqlconfenablepostauth'] ?: 'Disable');

	// Get Variables from freeradiussqlconf.xml for DATABASE 2
	$varsqlconf2enableauthorize = ($sqlconf['varsqlconf2enableauthorize'] ?: 'Disable');
	$varsqlconf2enableaccounting = ($sqlconf['varsqlconf2enableaccounting'] ?: 'Disable');
	$varsqlconf2enablesession = ($sqlconf['varsqlconf2enablesession'] ?: 'Disable');
	$varsqlconf2enablepostauth = ($sqlconf['varsqlconf2enablepostauth'] ?: 'Disable');

	// authorize section DATABASE 2
	if (($sqlconf['varsqlconf2includeenable'] == 'on') && ($sqlconf['varsqlconf2enableauthorize'] == 'Enable')) {
		$varsqlconf2authorize = 'sql2';
	} else {
		$varsqlconf2authorize = '### sql2 DISABLED ###';
	}
	// accounting section DATABASE 2
	if (($sqlconf['varsqlconf2includeenable'] == 'on') && ($sqlconf['varsqlconf2enableaccounting'] == 'Enable')) {
		$varsqlconf2accounting = 'sql2';
	} else {
		$varsqlconf2accounting = '### sql2 DISABLED ###';
	}
	// session section DATABASE 2
	if (($sqlconf['varsqlconf2includeenable'] == 'on') && ($sqlconf['varsqlconf2enablesession'] == 'Enable')) {
		$varsqlconf2session = 'sql2';
	} else {
		$varsqlconf2session = '### sql2 DISABLED ###';
	}
	// post-auth section DATABASE 2
	if (($sqlconf['varsqlconf2includeenable'] == 'on') && ($sqlconf['varsqlconf2enablepostauth'] == 'Enable')) {
		$varsqlconf2postauth = 'sql2';
	} else {
		$varsqlconf2postauth = '### sql2 DISABLED ###';
	}

	// Failover mode
	$varsqlconf2failover = ($sqlconf['varsqlconf2failover'] ?: 'redundant');

	// authorize section DATABASE 1
	if (($sqlconf['varsqlconfincludeenable'] == 'on') && ($sqlconf['varsqlconfenableauthorize'] == 'Enable')) {
		$varsqlconfauthorize = <<<EOD

	{$varsqlconf2failover} sql {
		sql1
		{$varsqlconf2authorize}
	}

EOD;
	} else {
		$varsqlconfauthorize = <<<EOD
	### sql DISABLED ###
	-daily
	-weekly
	-monthly
	-forever
EOD;
	}

	// accounting section DATABASE 1
	if (($sqlconf['varsqlconfincludeenable'] == 'on') && ($sqlconf['varsqlconfenableaccounting'] == 'Enable')) {
		$varsqlconfaccounting = <<<EOD

	{$varsqlconf2failover} sql {
		sql1
		{$varsqlconf2accounting}
	}

EOD;
	} else {
		$varsqlconfaccounting = <<<EOD
	### sql DISABLED ###
	daily
	weekly
	monthly
	forever
EOD;
	}

	// session section DATABASE 1
	if (($sqlconf['varsqlconfincludeenable'] == 'on') && ($sqlconf['varsqlconfenablesession'] == 'Enable')) {
		$varsqlconfsession = <<<EOD

	{$varsqlconf2failover} sql {
		sql1
		{$varsqlconf2session}
	}

EOD;
	} else {
		$varsqlconfsession = 'radutmp';
	}

	// post-auth section DATABASE 1
	if (($sqlconf['varsqlconfincludeenable'] == 'on') && ($sqlconf['varsqlconfenablepostauth'] == 'Enable')) {
		$varsqlconfpostauth = <<<EOD

	{$varsqlconf2failover} sql {
		sql1
		{$varsqlconf2postauth}
	}

EOD;
		$varsqlconfpostauthtypereject = 'sql';
	} else {
		$varsqlconfpostauth = '### sql DISABLED ###';
		$varsqlconfpostauthtypereject = '# sql';
	}

	// Changing authorize section for plain mac auth
	// Variables: If not using 802.1x, mac address must be known
	if (is_array($config['installedpackages']['freeradiussettings']['config'][0])) {
		$varsettings = $config['installedpackages']['freeradiussettings']['config'][0];
	} else {
		$varsettings = array();
	}

	// If unchecked we need the normal EAP section.
	if (!$varsettings['varsettingsenablemacauth']) {
		$varplainmacauthenable = '##### AUTHORIZE FOR PLAIN MAC-AUTH IS DISABLED #####';

		$varplainmacpreacctenable = '##### ACCOUNTING FOR PLAIN MAC-AUTH DISABLED #####';
	} else {
		// If checked we need to check if it is plain mac or eap
		$varplainmacauthenable = <<<EOD

	### FIRST check MAC address in authorized_macs and if that fails proceed with other checks below in else-section ###";
	### if cleaning up the Calling-Station-Id...###";
	pfs_rewrite_calling_station_id

	# now check against the authorized_macs file
	authorized_macs
	if (ok) {
		update control {
			Auth-Type := Accept
		}
	}
	### Here we have to place all other authorize modules which should be check when MAC fails ###";

EOD;

		$varplainmacpreacctenable = <<<EOD

	##### ACCOUNTING FOR PLAIN MAC-AUTH ENABLED #####
	pfs_rewrite_calling_station_id

EOD;
	}

	// Disable acct_unique in preacct section
	if ($varsettings['varsettingsenableacctunique'] == 'on') {
		$varsettingsacctuniqueenabled = '##### DISABLE acct_unique DISABLE #####';
	} else {
		$varsettingsacctuniqueenabled = 'acct_unique';
	}

	$conf .= <<<EOD
server default {

EOD;

	// Deletes virtual-server coa by default. Will be re-enabled if there is an interface-type "coa"
	unlink_if_exists(FREERADIUS_SITESENABLED . "/coa");

	if (is_array($config['installedpackages']['freeradiusinterfaces']['config'])) {
		$arrinterfaces = $config['installedpackages']['freeradiusinterfaces']['config'];
	} else {
		$arrinterfaces = array();
	}

	if (!empty($arrinterfaces)) {
		foreach ($arrinterfaces as $item) {
			$varinterfaceip = $item['varinterfaceip'];
			$varinterfaceport = $item['varinterfaceport'];
			$varinterfacetype = $item['varinterfacetype'];
			$varinterfaceipversion = $item['varinterfaceipversion'];

			// if interface-type = detail
			if ($item['varinterfacetype'] == 'detail') {
				$conf .= <<<EOD
listen {
	type = {$varinterfacetype}
	{$varinterfaceipversion} = {$varinterfaceip}
	port = {$varinterfaceport}
	filename = \${radacctdir}/detail-%Y%m%d:%H
	load_factor = 10
}

EOD;
			} // endif - interface-type = detail

			// if interface-type = coa
			if ($item['varinterfacetype'] == 'coa') {
			// Enables virtual-server coa because interface-type is coa
				symlink(FREERADIUS_SITESAVAIL . "/coa", FREERADIUS_SITESENABLED);
				$conf .= <<<EOD
listen {
	type = {$varinterfacetype}
	{$varinterfaceipversion} = {$varinterfaceip}
	port = {$varinterfaceport}
	server = coa
}

EOD;
			} // endif interface-type = coa

			// if interface-type = auth, acct, proxy, status
			if (($item['varinterfacetype'] == 'auth') || ($item['varinterfacetype'] == 'acct') || ($item['varinterfacetype'] == 'proxy') || ($item['varinterfacetype'] == 'status')) {
				$conf .= <<<EOD
listen {
	type = {$varinterfacetype}
	{$varinterfaceipversion} = {$varinterfaceip}
	port = {$varinterfaceport}
}

EOD;
			} // endif interface-type = auth, acct, proxy, status
		} // endforeach
	} // endif empty


	$conf .= <<<EOD

authorize {
#	filter_username
#	filter_password
	preprocess
#	operator-name
#	cui
{$varplainmacauthenable}
#	auth_log
	chap
	mschap
	digest
#	wimax
#	IPASS
	suffix
	ntdomain
	eap {
		ok = return
#		updated = return
	}
#	unix
	files
{$varsqlconfauthorize}
#	smbpasswd
{$varmodulesldapenableauthorize}
	# Formerly checkval
	if (&request:Calling-Station-Id == &control:Calling-Station-Id) {
		ok
	}
	expiration
	logintime
	pap
	Autz-Type Status-Server {

	}
}

authenticate {
	Auth-Type PAP {
		pap
	}
	Auth-Type CHAP {
		chap
	}
	Auth-Type MS-CHAP {
		mschap
	}
	mschap
	Auth-Type MOTP {
		motp
	}
	Auth-Type GOOGLEAUTH {
		googleauth
	}
	digest
#	pam
#	unix
{$varmodulesldapenableauthenticate}
	eap
#	Auth-Type eap {
#		eap {
#			handled = 1
#		}
#		if (handled && (Response-Packet-Type == Access-Challenge)) {
#			attr_filter.access_challenge.post-auth
#			handled  # override the "updated" code from attr_filter
#		}
#	}
}

preacct {
	preprocess
{$varplainmacpreacctenable}
#	acct_counters64
	update request {
		&FreeRADIUS-Acct-Session-Start-Time = "%{expr: %l - %{%{Acct-Session-Time}:-0} - %{%{Acct-Delay-Time}:-0}}"
	}
{$varsettingsacctuniqueenabled}
#	IPASS
	suffix
	ntdomain
	files
}

accounting {
#	cui
	detail
	### This makes it possible to run the datacounter_acct module only on accounting-stop and interim-updates
	if ((request:Acct-Status-Type == Stop) || (request:Acct-Status-Type == Interim-Update)) {
		datacounterdaily
		datacounterweekly
		datacountermonthly
		datacounterforever
	}
#	unix
	radutmp
#	sradutmp
#	main_pool
{$varsqlconfaccounting}
#	if (noop) {
#		ok
#	}
#	pgsql-voip
	exec
	attr_filter.accounting_response
	Acct-Type Status-Server {

	}
}

session {
#	radutmp
	{$varsqlconfsession}
}

post-auth {
#	if (!&reply:State) {
#		update reply {
#			State := "0x%{randstr:16h}"
#		}
#	}
	update {
		&reply: += &session-state:
	}
#	main_pool
#	cui
#	reply_log
{$varsqlconfpostauth}
#	ldap
	exec
#	wimax
#	update reply {
#		Reply-Message += "%{TLS-Cert-Serial}"
#		Reply-Message += "%{TLS-Cert-Expiration}"
#		Reply-Message += "%{TLS-Cert-Subject}"
#		Reply-Message += "%{TLS-Cert-Issuer}"
#		Reply-Message += "%{TLS-Cert-Common-Name}"
#		Reply-Message += "%{TLS-Cert-Subject-Alt-Name-Email}"
#
#		Reply-Message += "%{TLS-Client-Cert-Serial}"
#		Reply-Message += "%{TLS-Client-Cert-Expiration}"
#		Reply-Message += "%{TLS-Client-Cert-Subject}"
#		Reply-Message += "%{TLS-Client-Cert-Issuer}"
#		Reply-Message += "%{TLS-Client-Cert-Common-Name}"
#		Reply-Message += "%{TLS-Client-Cert-Subject-Alt-Name-Email}"
#	}
#	insert_acct_class
#	if (&reply:EAP-Session-Id) {
#		update reply {
#			EAP-Key-Name := &reply:EAP-Session-Id
#		}
#	}
	remove_reply_message_if_eap
	Post-Auth-Type REJECT {
		# log failed authentications in SQL, too.
		{$varsqlconfpostauthtypereject}
		attr_filter.access_reject
		eap
		remove_reply_message_if_eap
	}
	Post-Auth-Type Challenge {

	}
}

pre-proxy {
#	operator-name
#	cui
#	files
	attr_filter.pre-proxy
#	pre_proxy_log
}

post-proxy {

#	post_proxy_log
	attr_filter.post-proxy
	eap
#	Post-Proxy-Type Fail-Accounting {
#			detail
#	}
}
}

EOD;

	$filename = FREERADIUS_SITESENABLED . '/default';
	/* Remove what could be a symlink first before writing */
	@unlink_if_exists($filename);
	file_put_contents($filename, $conf);
	chmod($filename, 0640);

	// No need to restart here because the restart of the service
	// will be done in freeradius_settings_resync()
}

/* Uses XMLRPC to synchronize the changes to a remote node */
function freeradius_sync_on_changes() {
	global $config;

	if (is_array($config['installedpackages']['freeradiussync'])) {
		$synconchanges = $config['installedpackages']['freeradiussync']['config'][0]['varsyncenablexmlrpc'];
		$varsynctimeout = $config['installedpackages']['freeradiussync']['config'][0]['varsynctimeout'] ?: '150';
	} else {
		return;
	}

	// if checkbox is NOT checked do nothing
	switch ($synconchanges) {
		case "manual":
			if (is_array($config['installedpackages']['freeradiussync']['config'][0]['row'])) {
				$rs = $config['installedpackages']['freeradiussync']['config'][0]['row'];
			} else {
				log_error("[FreeRADIUS]: XMLRPC sync is enabled but there are no hosts configured as replication targets.");
				return;
			}
			break;
		case "auto":
			if (is_array($config['hasync'])) {
				$system_carp = $config['hasync'];
				$rs[0]['varsyncipaddress'] = $system_carp['synchronizetoip'];
				$rs[0]['varsyncusername'] = $system_carp['username'];
				$rs[0]['varsyncpassword'] = $system_carp['password'];
				$rs[0]['varsyncdestinenable'] = FALSE;

				// XMLRPC sync is currently only supported over connections using the same protocol and port as this system
				if ($config['system']['webgui']['protocol'] == "http") {
					$rs[0]['varsyncprotocol'] = "http";
					$rs[0]['varsyncport'] = $config['system']['webgui']['port'] ?: '80';
				} else {
					$rs[0]['varsyncprotocol'] = "https";
					$rs[0]['varsyncport'] = $config['system']['webgui']['port'] ?: '443';
				}
				if ($system_carp['synchronizetoip'] == "") {
					log_error("[FreeRADIUS]: XMLRPC CARP/HA sync is enabled but there are no system backup hosts configured as replication targets.");
					return;
				} else {
					$rs[0]['varsyncdestinenable'] = TRUE;
				}
			} else {
				log_error("[FreeRADIUS]: XMLRPC CARP/HA sync is enabled but there are no system backup hosts configured as replication targets.");
				return;
			}
			break;
		default:
			return;
			break;
	}
	if (is_array($rs)) {
		log_error("[FreeRADIUS]: XMLRPC sync is starting with timeout {$varsynctimeout} seconds.");
		foreach ($rs as $sh) {
			if ($sh['varsyncdestinenable']) {
				$sync_to_ip = $sh['varsyncipaddress'];
				$varsyncport = $sh['varsyncport'];
				$varsyncprotocol = $sh['varsyncprotocol'];
				$username = $sh['varsyncusername'] ?: 'admin';
				$password = $sh['varsyncpassword'];

				$error = '';
				$valid = TRUE;

				if ($password == "") {
					$error = "Password parameter is empty. ";
					$valid = FALSE;
				}
				if (!is_ipaddr($sync_to_ip) && !is_hostname($sync_to_ip) && !is_domain($sync_to_ip)) {
					$error .= "Misconfigured Replication Target IP Address or Hostname. ";
					$valid = FALSE;
				}
				if (!is_port($varsyncport)) {
					$error .= "Misconfigured Replication Target Port. ";
					$valid = FALSE;
				}
				if ($valid) {
					freeradius_do_xmlrpc_sync($sync_to_ip, $username, $password, $varsyncport, $varsyncprotocol, $varsynctimeout);
				} else {
					log_error("[FreeRADIUS]: XMLRPC Sync with '{$sync_to_ip}' aborted due to the following error(s): {$error}");
				}
			} else {
				log_error("[FreeRADIUS]: XMLRPC Sync with {$sh['varsyncipaddress']} is disabled");
			}
		}
		log_error("[FreeRADIUS]: XMLRPC sync is ending.");
	}
}

if (!function_exists('pf_version')) {
	function pf_version() {
		return substr(trim(file_get_contents("/etc/version")), 0, 3);
	}
}

/* Do the actual XMLRPC sync */
function freeradius_do_xmlrpc_sync($sync_to_ip, $username, $password, $varsyncport, $varsyncprotocol, $varsynctimeout) {
	global $config, $g;

	/* Detect boot process, do nothing during boot. */
	if (platform_booting()) {
		return;
	}

	if ($username == "" || $password == "" || $sync_to_ip == "" || $varsyncport == "" || $varsyncprotocol == "") {
		log_error("[FreeRADIUS]: A required XMLRPC sync parameter (username, password, replication target, port or protocol) is empty ... aborting pkg sync");
		return;
	}

	/* XML will hold the sections to sync. */
	$xml = array();
	$xml['freeradius'] = $config['installedpackages']['freeradius'];
	$xml['freeradiusauthorizedmacs'] = $config['installedpackages']['freeradiusauthorizedmacs'];
	$xml['freeradiusclients'] = $config['installedpackages']['freeradiusclients'];

	$execcmd  = "require_once('/usr/local/pkg/freeradius.inc');\n";
	$execcmd .= "freeradius_all_after_XMLRPC_resync();";

	if (pf_version() >= "2.4") {
		// xmlrpc cannot encode NULL objects/arrays
		foreach($xml as $xmlkey => $xmlvalue) {
			if (gettype($xmlvalue) == "NULL") {
				$xml[$xmlkey] = array();
			}
		}
		$synctimeout = intval($synctimeout);
		$rpc_client = new pfsense_xmlrpc_client();
		$rpc_client->setConnectionData($sync_to_ip, $varsyncport, $username, $password, $varsyncprotocol);
		$resp = $rpc_client->xmlrpc_method('merge_installedpackages_section', $xml, $synctimeout);
		$resp = $rpc_client->xmlrpc_exec_php($execcmd, $synctimeout);
	} else {
		// pfSense before 2.4
		require_once('xmlrpc.inc');

		/* Take care of IPv6 literal address */
		if (is_ipaddrv6($sync_to_ip)) {
			$sync_to_ip = "[{$sync_to_ip}]";
		}

		$url = "{$varsyncprotocol}://{$sync_to_ip}";
		$port = $varsyncport;

		/* Assemble XMLRPC payload. */
		$params = array(XML_RPC_encode($password), XML_RPC_encode($xml));

		/* Set a few variables needed for sync code */
		log_error("[FreeRADIUS]: Beginning FreeRADIUS XMLRPC sync with {$url}:{$port}.");
		$method = 'pfsense.merge_installedpackages_section_xmlrpc';
		$msg = new XML_RPC_Message($method, $params);
		$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
		$cli->setCredentials($username, $password);
		if ($g['debug']) {
			$cli->setDebug(1);
		}
		/* Send our XMLRPC message and timeout after defined sync timeout value */
		$resp = $cli->send($msg, $varsynctimeout);
		if (!$resp) {
			$error = "A communications error occurred while FreeRADIUS was attempting XMLRPC sync with {$url}:{$port}.";
			log_error("[FreeRADIUS]: {$error}");
			file_notice("sync_settings", $error, "FreeRADIUS Settings Sync", "");
		} elseif ($resp->faultCode()) {
			$cli->setDebug(1);
			$resp = $cli->send($msg, $varsynctimeout);
			$error = "An error code was received while FreeRADIUS XMLRPC was attempting to sync with {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString();
			log_error("[FreeRADIUS]: {$error}");
			file_notice("sync_settings", $error, "FreeRADIUS Settings Sync", "");
		} else {
			log_error("[FreeRADIUS]: XMLRPC has synced data successfully with {$url}:{$port}.");
		}

		/* Tell FreeRADIUS to reload our settings on the destionation sync host. */
		$method = 'pfsense.exec_php';

		/* Assemble XMLRPC payload. */
		$params = array(XML_RPC_encode($password), XML_RPC_encode($execcmd));

		log_error("[FreeRADIUS]: XMLRPC is reloading data on {$url}:{$port}.");
		$msg = new XML_RPC_Message($method, $params);
		$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
		$cli->setCredentials($username, $password);
		$resp = $cli->send($msg, $varsynctimeout);
		if (!$resp) {
			$error = "A communications error occurred while FreeRADIUS was attempting XMLRPC sync with {$url}:{$port} (exec_php).";
			log_error("[FreeRADIUS]: {$error}");
			file_notice("sync_settings", $error, "FreeRADIUS Settings Sync", "");
		} elseif ($resp->faultCode()) {
			$cli->setDebug(1);
			$resp = $cli->send($msg, $varsynctimeout);
			$error = "An error code was received while FreeRADIUS XMLRPC was attempting to sync with {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString();
			log_error("[FreeRADIUS]: {$error}");
			file_notice("sync_settings", $error, "FreeRADIUS Settings Sync", "");
		} else {
			log_error("[FreeRADIUS]: XMLRPC has reloaded data successfully on {$url}:{$port} (exec_php).");
		}
	}
}

// This function restarts all other needed functions after XMLRPC
// so that the content of .XML + .INC will be written in the files (clients.conf, users)
// Adding more functions will increase the to sync
function freeradius_all_after_XMLRPC_resync() {
	// Make sure we only (re)start the service once below
	// Do not restart service yet - $via_rpc = true
	freeradius_users_resync(true);
	// Do not restart service yet - $restart_svc = false, $via_rpc = true
	freeradius_authorizedmacs_resync(false, true);
	// Restart now when XMLRPC sync is completely finished
	freeradius_clients_resync();

	log_error("[FreeRADIUS]: Finished XMLRPC process. It should be OK. For more information look at the host which started sync.");
}

function freeradius_modulescounter_resync() {
	global $config;
	$conf = '';

	$conf .= <<<EOD

counter daily {
	filename = /var/log/radacct/timecounter/db.daily
	key = User-Name
	count_attribute = Acct-Session-Time
	reset = daily
	counter_name = Daily-Session-Time
	check_name = Max-Daily-Session
	reply_name = Session-Timeout
	cache_size = 5000
}

counter weekly {
	filename = /var/log/radacct/timecounter/db.weekly
	key = User-Name
	count_attribute = Acct-Session-Time
	reset = weekly
	counter_name = Weekly-Session-Time
	check_name = Max-Weekly-Session
	reply_name = Session-Timeout
	cache_size = 5000
}

counter monthly {
	filename = /var/log/radacct/timecounter/db.monthly
	key = User-Name
	count_attribute = Acct-Session-Time
	reset = monthly
	counter_name = Monthly-Session-Time
	check_name = Max-Monthly-Session
	reply_name = Session-Timeout
	cache_size = 5000
}

counter forever {
	filename = /var/log/radacct/timecounter/db.forever
	key = User-Name
	count_attribute = Acct-Session-Time
	reset = never
	counter_name = Forever-Session-Time
	check_name = Max-Forever-Session
	reply_name = Session-Timeout
	cache_size = 5000
}

EOD;

	$filename = FREERADIUS_MODSENABLED . '/counter';
	/* Remove what could be a symlink first before writing */
	@unlink_if_exists($filename);
	safe_mkdir(FREERADIUS_MODSENABLED);
	file_put_contents($filename, $conf);
	chmod($filename, 0640);

}

function freeradius_modulesmschap_resync() {
	global $config;
	$conf = '';

	$conf .= <<<EOD
mschap {
#	use_mppe = no
#	require_encryption = yes
#	require_strong = yes
	with_ntdomain_hack = yes
#	ntlm_auth = "/path/to/ntlm_auth --request-nt-key --username=%{%{Stripped-User-Name}:-%{%{User-Name}:-None}} --challenge=%{%{mschap:Challenge}:-00} --nt-response=%{%{mschap:NT-Response}:-00}"
#	ntlm_auth_timeout = 10
#	winbind_username = "%{mschap:User-Name}"
#	winbind_domain = "%{mschap:NT-Domain}"
#	winbind_retry_with_normalised_username = no
	pool {
		start = \${thread[pool].start_servers}
		min = \${thread[pool].min_spare_servers}
		max = \${thread[pool].max_servers}
		spare = \${thread[pool].max_spare_servers}
		uses = 0
		retry_delay = 30
		lifetime = 86400
		cleanup_interval = 300
		idle_timeout = 600
	}
	passchange {
#		ntlm_auth = "/usr/bin/ntlm_auth --helper-protocol=ntlm-change-password-1"
#		ntlm_auth_username = "username: %{mschap:User-Name}"
#		ntlm_auth_domain = "nt-domain: %{mschap:NT-Domain}"
#		local_cpw = "%{exec:/path/to/script %{mschap:User-Name} %{MS-CHAP-New-Cleartext-Password}}"
#		local_cpw = "%{sql:UPDATE radcheck set value='%{MS-CHAP-New-NT-Password}' where username='%{SQL-User-Name}' and attribute='NT-Password'}"
	}

#	use_open_directory = yes
#	allow_retry = yes
#	retry_msg = "Re-enter (or reset) the password"
}

EOD;

	/* We never want this NTLM module to be active */
	@unlink_if_exists(FREERADIUS_MODSENABLED . '/ntlm_auth');
	$filename = FREERADIUS_MODSENABLED . '/mschap';
	/* Remove what could be a symlink first before writing */
	@unlink_if_exists($filename);
	safe_mkdir(FREERADIUS_MODSENABLED);
	file_put_contents($filename, $conf);
	chmod($filename, 0640);

}

function freeradius_modulesrealm_resync() {
	global $config;
	$conf = '';

	$conf .= <<<EOD
#  'realm/username'
realm IPASS {
		format = prefix
		delimiter = "/"
		ignore_null = yes
		ignore_default = no
}
#  'username@realm'
realm suffix {
		format = suffix
		delimiter = "@"
		ignore_null = yes
		ignore_default = no
}
#  'username%realm'
realm realmpercent {
		format = suffix
		delimiter = "%"
		ignore_null = yes
		ignore_default = no
}
#
#  'domain\user'
realm ntdomain {
		format = prefix
		delimiter = '\\\\'
		ignore_null = yes
		ignore_default = no
}

EOD;

	$filename = FREERADIUS_MODSENABLED . '/realm';
	/* Remove what could be a symlink first before writing */
	@unlink_if_exists($filename);
	safe_mkdir(FREERADIUS_MODSENABLED);
	file_put_contents($filename, $conf);
	chmod($filename, 0640);

}

function freeradius_modulesldap_resync($restart_svc = true) {
	global $config;
	$raddb = FREERADIUS_RADDB;
	$conf = '';

	if (is_array($config['installedpackages']['freeradiusmodulesldap']['config'][0])) {
		$arrmodulesldap = $config['installedpackages']['freeradiusmodulesldap']['config'][0];
	} else {
		$arrmodulesldap = array();
	}

	// Enable and Disable LDAP for "authorize" and "authenticate" will be done in freeradius_serverdefault_resync()
	// redundatnt-load-balancing will there be done, too

	// Variables for General Configuration ldap1
	$varmodulesldapserver = ($arrmodulesldap['varmodulesldapserver'] ?: 'ldap.example.com');
	$varmodulesldapserverport = ($arrmodulesldap['varmodulesldapserverport'] ?: '389');
	$varmodulesldapidentity = ($arrmodulesldap['varmodulesldapidentity'] ?: 'cn=admin,o=My Company Ltd,c=US');
	$varmodulesldappassword = (escapeshellarg($arrmodulesldap['varmodulesldappassword']) ?: 'mypass');
	$varmodulesldapbasedn = ($arrmodulesldap['varmodulesldapbasedn'] ?: 'o=My Company Ltd,c=US');
	$varmodulesldapfilter = ($arrmodulesldap['varmodulesldapfilter'] ?: '(uid=%{%{Stripped-User-Name}:-%{User-Name}})');
	$varmodulesldapbasefilter = ($arrmodulesldap['varmodulesldapbasefilter'] ?: '(objectclass=radiusprofile)');
	$varmodulesldapldapconnectionsnumber = ($arrmodulesldap['varmodulesldapldapconnectionsnumber'] ?: '5');
	$varmodulesldaptimeout = ($arrmodulesldap['varmodulesldaptimeout'] ?: '4');
	$varmodulesldaptimelimit = ($arrmodulesldap['varmodulesldaptimelimit'] ?: '3');
	$varmodulesldapnettimeout = ($arrmodulesldap['varmodulesldapnettimeout'] ?: '1');

	// Variables for General Configuration ldap2
	$varmodulesldap2server = ($arrmodulesldap['varmodulesldap2server'] ?: 'ldap.example.com');
	$varmodulesldap2serverport = ($arrmodulesldap['varmodulesldap2serverport'] ?: '389');
	$varmodulesldap2identity = ($arrmodulesldap['varmodulesldap2identity'] ?: 'cn=admin,o=My Company Ltd,c=US');
	$varmodulesldap2password = (escapeshellarg($arrmodulesldap['varmodulesldap2password']) ?: 'mypass');
	$varmodulesldap2basedn = ($arrmodulesldap['varmodulesldap2basedn'] ?: 'o=My Company Ltd,c=US');
	$varmodulesldap2filter = ($arrmodulesldap['varmodulesldap2filter'] ?: '(uid=%{%{Stripped-User-Name}:-%{User-Name}})');
	$varmodulesldap2basefilter = ($arrmodulesldap['varmodulesldap2basefilter'] ?: '(objectclass=radiusprofile)');
	$varmodulesldap2ldapconnectionsnumber = ($arrmodulesldap['varmodulesldap2ldapconnectionsnumber'] ?: '5');
	$varmodulesldap2timeout = ($arrmodulesldap['varmodulesldap2timeout'] ?: '4');
	$varmodulesldap2timelimit = ($arrmodulesldap['varmodulesldap2timelimit'] ?: '3');
	$varmodulesldap2nettimeout = ($arrmodulesldap['varmodulesldap2nettimeout'] ?: '1');

	// if enabled then create the certs in ../raddb/certs/ and enable "Start_tls" in ldap1 module
	if ($arrmodulesldap['varmodulesldapenabletlssupport'] == 'on') {

		$ca_cert = lookup_ca($arrmodulesldap["ssl_ca_cert1"]);
		if ($ca_cert != false) {
			if (base64_decode($ca_cert['crt'])) {
				file_put_contents(FREERADIUS_CERTS . "/ca_ldap1_cert.pem", base64_decode($ca_cert['crt']));
			}
			$svr_cert = lookup_cert($arrmodulesldap["ssl_server_cert1"]);
			if ($svr_cert != false) {
				if (base64_decode($svr_cert['prv'])) {
					file_put_contents(FREERADIUS_CERTS . "/radius_ldap1_cert.key", base64_decode($svr_cert['prv']));
				}
			}
			if (base64_decode($svr_cert['crt'])) {
				file_put_contents(FREERADIUS_CERTS . "/radius_ldap1_cert.crt", base64_decode($svr_cert['crt']));
			}
		}
		// Variables for TLS / Certificates - ldap1
		$varmodulesldaprequirecert = ($arrmodulesldap['varmodulesldaprequirecert'] ?: 'never');
		$varmodulesldapstarttls = ($arrmodulesldap['varmodulesldapenablestarttls'] == 'on') ? "yes" : "no";

		$varmodulesldap_tlsconfig = <<<EOD

	tls {
		start_tls = {$varmodulesldapstarttls}
		ca_file = {$raddb}/certs/ca_ldap1_cert.pem
		ca_path = {$raddb}/certs/
		certificate_file = {$raddb}/certs/radius_ldap1_cert.crt
		private_key_file = {$raddb}/certs/radius_ldap1_cert.key
		random_file = /dev/urandom
		require_cert = "{$varmodulesldaprequirecert}"
	}

EOD;
	}

	// if enabled then create the certs in ../raddb/certs/ and enable "Start_tls" in ldap2 module
	if ($arrmodulesldap['varmodulesldap2enabletlssupport'] == 'on') {

		$ca_cert = lookup_ca($arrmodulesldap["ssl_ca_cert2"]);
		if ($ca_cert != false) {
			if (base64_decode($ca_cert['crt'])) {
				file_put_contents(FREERADIUS_CERTS . "/ca_ldap2_cert.pem", base64_decode($ca_cert['crt']));
			}

			$svr_cert = lookup_cert($arrmodulesldap["ssl_server_cert2"]);
			if ($svr_cert != false) {
				if (base64_decode($svr_cert['prv'])) {
					file_put_contents(FREERADIUS_CERTS . "/radius_ldap2_cert.key", base64_decode($svr_cert['prv']));
				}
			}
			if (base64_decode($svr_cert['crt'])) {
				file_put_contents(FREERADIUS_CERTS . "/radius_ldap2_cert.crt", base64_decode($svr_cert['crt']));
			}
		}
		// Variables for TLS / Certificates - ldap2
		$varmodulesldap2requirecert = ($arrmodulesldap['varmodulesldap2requirecert'] ?: 'never');
		$varmodulesldap2starttls = ($arrmodulesldap['varmodulesldap2enablestarttls'] == 'on') ? "yes" : "no";

		$varmodulesldap2_tlsconfig = <<<EOD

	tls {
		start_tls = {$varmodulesldap2starttls}
		ca_file = {$raddb}/certs/ca_ldap1_cert.pem
		ca_path = {$raddb}/certs/
		certificate_file = {$raddb}/certs/radius_ldap1_cert.crt
		private_key_file = {$raddb}/certs/radius_ldap1_cert.key
		random_file = /dev/urandom
		require_cert = "{$varmodulesldap2requirecert}"
	}

EOD;
	}

	// Miscellaneous Configuration + MS Active Directory Compatibility ldap1
	$varmodulesldapmsadcompatibilityenable = ($arrmodulesldap['varmodulesldapmsadcompatibilityenable'] ?: 'Disable');
	if ($arrmodulesldap['varmodulesldapmsadcompatibilityenable'] == 'Disable') {
		$varmodulesldapmsadcompatibility = '### MS Active Directory Compatibility is disabled ###';
	} else {
		$varmodulesldapmsadcompatibility = <<<EOD

		chase_referrals = yes
		rebind = yes

EOD;
	}

	// Miscellaneous Configuration + MS Active Directory Compatibility ldap2
	$varmodulesldap2msadcompatibilityenable = ($arrmodulesldap['varmodulesldap2msadcompatibilityenable'] ?: 'Disable');
	if ($arrmodulesldap['varmodulesldap2msadcompatibilityenable'] == 'Disable') {
		$varmodulesldap2msadcompatibility = '### MS Active Directory Compatibility is disabled ###';
	} else {
		$varmodulesldap2msadcompatibility = <<<EOD

		chase_referrals = yes
		rebind = yes

EOD;
	}

	// When disabled we put this in the file but commented (#) like in the default installation ldap1
	if (!$arrmodulesldap['varmodulesldapdmiscenable']) {
		$varmodulesldapdefaultprofile = '### default_profile = "cn=radprofile,ou=dialup,o=My Company Ltd,c=US" ###';
		$varmodulesldapprofileattribute = '### profile_attribute = "radiusProfileDn" ###';
		$varmodulesldapaccessattr = '### access_attr = "dialupAccess" ###';
	} else {
		// When enabled we put in the default values so there is no empty entry if there is not input from GUI
		$varmodulesldapdefaultprofile = ($arrmodulesldap['varmodulesldapdefaultprofile'] ?: 'cn=radprofile,ou=dialup,o=My Company Ltd,c=US');
		$varmodulesldapdefaultprofile = "default_profile = \"{$varmodulesldapdefaultprofile}\"";
		$varmodulesldapprofileattribute = ($arrmodulesldap['varmodulesldapprofileattribute'] ?: 'radiusProfileDn');
		$varmodulesldapprofileattribute = "profile_attribute = \"{$varmodulesldapprofileattribute}\"";
		$varmodulesldapaccessattr = ($arrmodulesldap['varmodulesldapaccessattr'] ?: 'dialupAccess');
		$varmodulesldapaccessattr = "access_attr = \"{$varmodulesldapaccessattr}\"";
	}

	// When disabled we put this in the file but commented (#) like in the default installation ldap2
	if (!$arrmodulesldap['varmodulesldap2dmiscenable']) {
		$varmodulesldap2defaultprofile = '### default_profile = "cn=radprofile,ou=dialup,o=My Company Ltd,c=US" ###';
		$varmodulesldap2profileattribute = '### profile_attribute = "radiusProfileDn" ###';
		$varmodulesldap2accessattr = '### access_attr = "dialupAccess" ###';
	} else {
		// When enabled we put in the default values so there is no empty entry if there is not input from GUI
		$varmodulesldap2defaultprofile = ($arrmodulesldap['varmodulesldap2defaultprofile'] ?: 'cn=radprofile,ou=dialup,o=My Company Ltd,c=US');
		$varmodulesldap2defaultprofile = "default_profile = \"{$varmodulesldap2defaultprofile}\"";
		$varmodulesldap2profileattribute = ($arrmodulesldap['varmodulesldap2profileattribute'] ?: 'radiusProfileDn');
		$varmodulesldap2profileattribute = "profile_attribute = \"{$varmodulesldap2profileattribute}\"";
		$varmodulesldap2accessattr = ($arrmodulesldap['varmodulesldap2accessattr'] ?: 'dialupAccess');
		$varmodulesldap2accessattr = "access_attr = \"{$varmodulesldap2accessattr}\"";
	}

	// Group membership checking
	// When disabled we put this in the file but commented (#) like in the default installation ldap1
	if (!$arrmodulesldap['varmodulesldapgroupenable']) {
		$varmodulesldapgroupnameattribute = '### name_attribute = cn ###';
		$varmodulesldapgroupmembershipfilter = '### membership_filter = "(|(&(objectClass=GroupOfNames)(member=%{control:Ldap-UserDn}))(&(objectClass=GroupOfUniqueNames)(uniquemember=%{control:Ldap-UserDn})))" ###';
		$varmodulesldapgroupmembershipattribute = '### membership_attribute = radiusGroupName ###';
		$varmodulesldapcomparecheckitems = '### compare_check_items = yes ###';
		$varmodulesldapdoxlat = '### do_xlat = yes ###';
		$varmodulesldapaccessattrusedforallow = '### access_attr_used_for_allow = yes ###';
	} else {
		// When enabled we put in the default values so there is no empty entry if there is not input from GUI
		$varmodulesldapgroupnameattribute = ($arrmodulesldap['varmodulesldapgroupnameattribute'] ?: 'cn');
		$varmodulesldapgroupnameattribute = "name_attribute = {$varmodulesldapgroupnameattribute}";
		$varmodulesldapgroupmembershipfilter = ($arrmodulesldap['varmodulesldapgroupmembershipfilter'] ?: '(|(&(objectClass=GroupOfNames)(member=%{control:Ldap-UserDn}))(&(objectClass=GroupOfUniqueNames)(uniquemember=%{control:Ldap-UserDn})))');
		$varmodulesldapgroupmembershipfilter = "membership_filter = \"{$varmodulesldapgroupmembershipfilter}\"";
		$varmodulesldapgroupmembershipattribute = ($arrmodulesldap['varmodulesldapgroupmembershipattribute'] ?: 'radiusGroupName');
		$varmodulesldapgroupmembershipattribute = "membership_attribute = {$varmodulesldapgroupmembershipattribute}";
		$varmodulesldapcomparecheckitems = ($arrmodulesldap['varmodulesldapcomparecheckitems'] ?: 'yes');
		$varmodulesldapcomparecheckitems = "compare_check_items = {$varmodulesldapcomparecheckitems}";
		$varmodulesldapdoxlat = ($arrmodulesldap['varmodulesldapdoxlat'] ?: 'yes');
		$varmodulesldapdoxlat = "do_xlat = {$varmodulesldapdoxlat}";
		$varmodulesldapaccessattrusedforallow = ($arrmodulesldap['varmodulesldapaccessattrusedforallow'] ?: 'yes');
		$varmodulesldapaccessattrusedforallow = "access_attr_used_for_allow = {$varmodulesldapaccessattrusedforallow}";
	}

	// Group membership checking
	// When disabled we put this in the file but commented (#) like in the default installation ldap2
	if (!$arrmodulesldap['varmodulesldap2groupenable']) {
		$varmodulesldap2groupnameattribute = '### name_attribute = cn ###';
		$varmodulesldap2groupmembershipfilter = '### membership_filter = "(|(&(objectClass=GroupOfNames)(member=%{control:Ldap-UserDn}))(&(objectClass=GroupOfUniqueNames)(uniquemember=%{control:Ldap-UserDn})))" ###';
		$varmodulesldap2groupmembershipattribute = '### membership_attribute = radiusGroupName ###';
		$varmodulesldap2comparecheckitems = '### compare_check_items = yes ###';
		$varmodulesldap2doxlat = '### do_xlat = yes ###';
		$varmodulesldap2accessattrusedforallow = '### access_attr_used_for_allow = yes ###';
	} else {
		// When enabled we put in the default values so there is no empty entry if there is not input from GUI
		$varmodulesldap2groupnameattribute = ($arrmodulesldap['varmodulesldap2groupnameattribute'] ?: 'cn');
		$varmodulesldap2groupnameattribute = "name_attribute = {$varmodulesldap2groupnameattribute}";
		$varmodulesldap2groupmembershipfilter = ($arrmodulesldap['varmodulesldap2groupmembershipfilter'] ?: '(|(&(objectClass=GroupOfNames)(member=%{control:Ldap-UserDn}))(&(objectClass=GroupOfUniqueNames)(uniquemember=%{control:Ldap-UserDn})))');
		$varmodulesldap2groupmembershipfilter = "membership_filter = \"{$varmodulesldap2groupmembershipfilter}\"";
		$varmodulesldap2groupmembershipattribute = ($arrmodulesldap['varmodulesldap2groupmembershipattribute'] ?: 'radiusGroupName');
		$varmodulesldap2groupmembershipattribute = "membership_attribute = {$varmodulesldap2groupmembershipattribute}";
		$varmodulesldap2comparecheckitems = ($arrmodulesldap['varmodulesldap2comparecheckitems'] ?: 'yes');
		$varmodulesldap2comparecheckitems = "compare_check_items = {$varmodulesldap2comparecheckitems}";
		$varmodulesldap2doxlat = ($arrmodulesldap['varmodulesldap2doxlat'] ?: 'yes');
		$varmodulesldap2doxlat = "do_xlat = {$varmodulesldap2doxlat}";
		$varmodulesldap2accessattrusedforallow = ($arrmodulesldap['varmodulesldap2accessattrusedforallow'] ?: 'yes');
		$varmodulesldap2accessattrusedforallow = "access_attr_used_for_allow = {$varmodulesldap2accessattrusedforallow}";
	}

	// Keepalive variables ldap1
	$varmodulesldapkeepaliveidle = ($arrmodulesldap['varmodulesldapkeepaliveidle'] ?: '60');
	$varmodulesldapkeepaliveprobes = ($arrmodulesldap['varmodulesldapkeepaliveprobes'] ?: '3');
	$varmodulesldapkeepaliveinterval = ($arrmodulesldap['varmodulesldapkeepaliveinterval'] ?: '3');

	// Keepalive variables ldap2
	$varmodulesldap2keepaliveidle = ($arrmodulesldap['varmodulesldap2keepaliveidle'] ?: '60');
	$varmodulesldap2keepaliveprobes = ($arrmodulesldap['varmodulesldap2keepaliveprobes'] ?: '3');
	$varmodulesldap2keepaliveinterval = ($arrmodulesldap['varmodulesldap2keepaliveinterval'] ?: '3');

	$conf .= <<<EOD
ldap {
	server = "{$varmodulesldapserver}"
	port = "{$varmodulesldapserverport}"
	identity = "{$varmodulesldapidentity}"
	password = {$varmodulesldappassword}
	base_dn = "{$varmodulesldapbasedn}"

	user {
		base_dn = "\${..base_dn}"
		filter = "{$varmodulesldapfilter}"
		{$varmodulesldapaccessattr}
	}
	group {
		base_dn = "\${..base_dn}"
		filter = '(objectClass=posixGroup)'
		{$varmodulesldapgroupnameattribute}
		{$varmodulesldapgroupmembershipfilter}
		{$varmodulesldapgroupmembershipattribute}
		{$varmodulesldapcomparecheckitems}
		{$varmodulesldapdoxlat}
		{$varmodulesldapaccessattrusedforallow}
	}
	profile {
		filter = "{$varmodulesldapbasefilter}"
		{$varmodulesldapdefaultprofile}
		{$varmodulesldapprofileattribute}
	}
{$varmodulesldap_tlsconfig}

#	valuepair_attribute = 'radiusAttribute'
	update {
		control:Auth-Type		:= 'radiusAuthType'
		control:Simultaneous-Use	:= 'radiusSimultaneousUse'
		control:Called-Station-Id	:= 'radiusCalledStationId'
		control:Calling-Station-Id	:= 'radiusCallingStationId'
		control:LM-Password		:= 'lmPassword'
		control:NT-Password		:= 'ntPassword'
		control:LM-Password		:= 'sambaLmPassword'
		control:NT-Password		:= 'sambaNtPassword'
		control:NT-Password		:= 'ipaNTHash'
		control:LM-Password		:= 'dBCSPwd'
		control:Password-With-Header	+= 'userPassword'
		control:SMB-Account-CTRL-TEXT	:= 'acctFlags'
		control:Expiration		:= 'radiusExpiration'
		control:NAS-IP-Address		:= 'radiusNASIpAddress'
		reply:Service-Type		:= 'radiusServiceType'
		reply:Framed-Protocol		:= 'radiusFramedProtocol'
		reply:Framed-IP-Address		:= 'radiusFramedIPAddress'
		reply:Framed-IP-Netmask		:= 'radiusFramedIPNetmask'
		reply:Framed-Route		:= 'radiusFramedRoute'
		reply:Framed-Routing		:= 'radiusFramedRouting'
		reply:Filter-Id			:= 'radiusFilterId'
		reply:Framed-MTU		:= 'radiusFramedMTU'
		reply:Framed-Compression	:= 'radiusFramedCompression'
		reply:Login-IP-Host		:= 'radiusLoginIPHost'
		reply:Login-Service		:= 'radiusLoginService'
		reply:Login-TCP-Port		:= 'radiusLoginTCPPort'
		reply:Callback-Number		:= 'radiusCallbackNumber'
		reply:Callback-Id		:= 'radiusCallbackId'
		reply:Framed-IPX-Network	:= 'radiusFramedIPXNetwork'
		reply:Class			:= 'radiusClass'
		reply:Session-Timeout		:= 'radiusSessionTimeout'
		reply:Idle-Timeout		:= 'radiusIdleTimeout'
		reply:Termination-Action	:= 'radiusTerminationAction'
		reply:Login-LAT-Service		:= 'radiusLoginLATService'
		reply:Login-LAT-Node		:= 'radiusLoginLATNode'
		reply:Login-LAT-Group		:= 'radiusLoginLATGroup'
		reply:Framed-AppleTalk-Link	:= 'radiusFramedAppleTalkLink'
		reply:Framed-AppleTalk-Network	:= 'radiusFramedAppleTalkNetwork'
		reply:Framed-AppleTalk-Zone	:= 'radiusFramedAppleTalkZone'
		reply:Port-Limit		:= 'radiusPortLimit'
		reply:Login-LAT-Port		:= 'radiusLoginLATPort'
		reply:Reply-Message		:= 'radiusReplyMessage'
		reply:Tunnel-Type		:= 'radiusTunnelType'
		reply:Tunnel-Medium-Type	:= 'radiusTunnelMediumType'
		reply:Tunnel-Private-Group-Id	:= 'radiusTunnelPrivateGroupId'
		control:			+= 'radiusControlAttribute'
		request:			+= 'radiusRequestAttribute'
		reply:				+= 'radiusReplyAttribute'
	}

	edir_account_policy_check = no

	options {
		idle = {$varmodulesldapkeepaliveidle}
		probes = {$varmodulesldapkeepaliveprobes}
		interval = {$varmodulesldapkeepaliveinterval}
{$varmodulesldapmsadcompatibility}
		# ldap_debug = 0x0028
		res_timeout = {$varmodulesldaptimeout}
		srv_timelimit = {$varmodulesldaptimelimit}
		net_timeout = {$varmodulesldapnettimeout}
	}

	pool {
		start = 0
		min = {$varmodulesldapldapconnectionsnumber}
		max = {$varmodulesldapldapconnectionsnumber}
		spare = \${thread[pool].max_spare_servers}
		uses = 0
		retry_delay = 30
		lifetime = 0
		idle_timeout = 60
	}
	accounting {
		reference = "%{tolower:type.%{Acct-Status-Type}}"
		type {
			start {
				update {
					description := "Online at %S"
				}
			}
			interim-update {
				update {
					description := "Last seen at %S"
				}
			}
			stop {
				update {
					description := "Offline at %S"
				}
			}
		}
	}
	post-auth {
		update {
			description := "Authenticated at %S"
		}
	}
}

ldap ldap2 {
	server = "{$varmodulesldap2server}"
	port = "{$varmodulesldap2serverport}"
	identity = "{$varmodulesldap2identity}"
	password = {$varmodulesldap2password}
	base_dn = "{$varmodulesldap2basedn}"

	user {
		base_dn = "\${..base_dn}"
		filter = "{$varmodulesldap2filter}"
		{$varmodulesldap2accessattr}
	}
	group {
		base_dn = "\${..base_dn}"
		filter = '(objectClass=posixGroup)'
		{$varmodulesldap2groupnameattribute}
		{$varmodulesldap2groupmembershipfilter}
		{$varmodulesldap2groupmembershipattribute}
		{$varmodulesldap2comparecheckitems}
		{$varmodulesldap2doxlat}
		{$varmodulesldap2accessattrusedforallow}
	}
	profile {
		filter = "{$varmodulesldap2basefilter}"
		{$varmodulesldap2defaultprofile}
		{$varmodulesldap2profileattribute}
	}
{$varmodulesldap2_tlsconfig}

#	valuepair_attribute = 'radiusAttribute'
	update {
		control:Auth-Type		:= 'radiusAuthType'
		control:Simultaneous-Use	:= 'radiusSimultaneousUse'
		control:Called-Station-Id	:= 'radiusCalledStationId'
		control:Calling-Station-Id	:= 'radiusCallingStationId'
		control:LM-Password		:= 'lmPassword'
		control:NT-Password		:= 'ntPassword'
		control:LM-Password		:= 'sambaLmPassword'
		control:NT-Password		:= 'sambaNtPassword'
		control:LM-Password		:= 'dBCSPwd'
		control:Password-With-Header	+= 'userPassword'
		control:SMB-Account-CTRL-TEXT	:= 'acctFlags'
		control:Expiration		:= 'radiusExpiration'
		control:NAS-IP-Address		:= 'radiusNASIpAddress'
		reply:Service-Type		:= 'radiusServiceType'
		reply:Framed-Protocol		:= 'radiusFramedProtocol'
		reply:Framed-IP-Address		:= 'radiusFramedIPAddress'
		reply:Framed-IP-Netmask		:= 'radiusFramedIPNetmask'
		reply:Framed-Route		:= 'radiusFramedRoute'
		reply:Framed-Routing		:= 'radiusFramedRouting'
		reply:Filter-Id			:= 'radiusFilterId'
		reply:Framed-MTU		:= 'radiusFramedMTU'
		reply:Framed-Compression	:= 'radiusFramedCompression'
		reply:Login-IP-Host		:= 'radiusLoginIPHost'
		reply:Login-Service		:= 'radiusLoginService'
		reply:Login-TCP-Port		:= 'radiusLoginTCPPort'
		reply:Callback-Number		:= 'radiusCallbackNumber'
		reply:Callback-Id		:= 'radiusCallbackId'
		reply:Framed-IPX-Network	:= 'radiusFramedIPXNetwork'
		reply:Class			:= 'radiusClass'
		reply:Session-Timeout		:= 'radiusSessionTimeout'
		reply:Idle-Timeout		:= 'radiusIdleTimeout'
		reply:Termination-Action	:= 'radiusTerminationAction'
		reply:Login-LAT-Service		:= 'radiusLoginLATService'
		reply:Login-LAT-Node		:= 'radiusLoginLATNode'
		reply:Login-LAT-Group		:= 'radiusLoginLATGroup'
		reply:Framed-AppleTalk-Link	:= 'radiusFramedAppleTalkLink'
		reply:Framed-AppleTalk-Network	:= 'radiusFramedAppleTalkNetwork'
		reply:Framed-AppleTalk-Zone	:= 'radiusFramedAppleTalkZone'
		reply:Port-Limit		:= 'radiusPortLimit'
		reply:Login-LAT-Port		:= 'radiusLoginLATPort'
		reply:Reply-Message		:= 'radiusReplyMessage'
		reply:Tunnel-Type		:= 'radiusTunnelType'
		reply:Tunnel-Medium-Type	:= 'radiusTunnelMediumType'
		reply:Tunnel-Private-Group-Id	:= 'radiusTunnelPrivateGroupId'
		control:			+= 'radiusControlAttribute'
		request:			+= 'radiusRequestAttribute'
		reply:				+= 'radiusReplyAttribute'
	}

	edir_account_policy_check = no

	options {
		idle = {$varmodulesldap2keepaliveidle}
		probes = {$varmodulesldap2keepaliveprobes}
		interval = {$varmodulesldap2keepaliveinterval}
{$varmodulesldap2msadcompatibility}
		# ldap_debug = 0x0028
		res_timeout = {$varmodulesldap2timeout}
		srv_timelimit = {$varmodulesldap2timelimit}
		net_timeout = {$varmodulesldap2nettimeout}
	}
	pool {
		start = 0
		min = {$varmodulesldap2ldapconnectionsnumber}
		max = {$varmodulesldap2ldapconnectionsnumber}
		spare = \${thread[pool].max_spare_servers}
		uses = 0
		retry_delay = 30
		lifetime = 0
		idle_timeout = 60
	}
	accounting {
		reference = "%{tolower:type.%{Acct-Status-Type}}"
		type {
			start {
				update {
					description := "Online at %S"
				}
			}
			interim-update {
				update {
					description := "Last seen at %S"
				}
			}
			stop {
				update {
					description := "Offline at %S"
				}
			}
		}
	}
	post-auth {
		update {
			description := "Authenticated at %S"
		}
	}
}

EOD;

	$filename = FREERADIUS_MODSENABLED . '/ldap';
	/* Remove what could be a symlink first before writing */
	@unlink_if_exists($filename);
	safe_mkdir(FREERADIUS_MODSENABLED);
	file_put_contents($filename, $conf);
	chmod($filename, 0640);

	// We need to rebuild freeradius_serverdefault_resync() before restart service
	// freeradius_serverdefault_resync() needs to restart other dependencies so
	// we are pointing directly to freeradius_settings_resync()
	freeradius_serverdefault_resync();
	if ($restart_svc === true) {
		restart_service("radiusd");
	}

}

function freeradius_plainmacauth_resync() {
	global $config;
	$conf = '';

	// Variables: If not using 802.1x, mac address must be known
	if (is_array($config['installedpackages']['freeradiussettings']['config'][0])) {
		$varsettings = $config['installedpackages']['freeradiussettings']['config'][0];
	} else {
		$varsettings = array();
	}

	// defining variables with filename path
	$filemodulesfiles = FREERADIUS_MODSENABLED . '/files';
	$filemodulesfilesdefault = FREERADIUS_MODSAVAIL . '/files';

	// If unchecked then plain mac auth is disabled and backups of the original files will be restored
	if ($varsettings['varsettingsenablemacauth'] == '') {
		// This is a check - only restore files if they aren't already
		if (file_exists(FREERADIUS_RADDB . "/plain_macauth_enabled")) {
			log_error("FreeRADIUS: Plain-MAC-Auth disabled. Restoring the original file {$filemodulesfilesdefault}");
			unlink(FREERADIUS_RADDB . "/plain_macauth_enabled");
		}
		@unlink_if_exists($filemodulesfiles);
		symlink( $filemodulesfilesdefault , $filemodulesfiles );
	} else {
		// If checked then plain mac auth is enabled
		// This is a check - only modify files if they aren't already
		if (!file_exists(FREERADIUS_RADDB . "/plain_macauth_enabled")) {
			log_error("FreeRADIUS: Plain-MAC-Auth enabled. Modified {$filemodulesfiles}");
			touch(FREERADIUS_RADDB . "/plain_macauth_enabled");
		}
		freeradius_modulesfiles_resync();
	}
	freeradius_policyd_resync();
}

function freeradius_policyd_resync() {
	$conf = <<<EOD
pfs_rewrite_calling_station_id {
	if (&Calling-Station-Id && (&Calling-Station-Id =~ /([0-9a-f]{2})[^0-9a-f]?([0-9a-f]{2})[^0-9a-f]?([0-9a-f]{2})[^0-9a-f]?([0-9a-f]{2})[^0-9a-f]?([0-9a-f]{2})[^0-9a-f]?([0-9a-f]{2})/i)) {
		update request {
			&Calling-Station-Id := "%{tolower:%{1}-%{2}-%{3}-%{4}-%{5}-%{6}}"
		}
		updated
	}
	else {
		noop
	}
}

EOD;

	$filename = FREERADIUS_POLICYD . '/pfs_custom_policies';
	/* Remove what could be a symlink first before writing */
	@unlink_if_exists($filename);
	safe_mkdir(FREERADIUS_POLICYD);
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
}

function freeradius_modulesfiles_resync() {
	global $config;
	$conf = '';

	$conf .= <<<EOD
files {
	moddir = \${modconfdir}/\${.:instance}
	#key = "%{%{Stripped-User-Name}:-%{User-Name}}"
	filename = \${moddir}/authorize
#	usersfile = \${moddir}/authorize
	acctusersfile = \${moddir}/accounting
	preproxy_usersfile = \${moddir}/pre-proxy
}

files authorized_macs {
	key = "%{Calling-Station-ID}"
	usersfile = \${confdir}/authorized_macs
}

EOD;

	$filename = FREERADIUS_MODSENABLED . '/files';
	/* Remove what could be a symlink first before writing */
	@unlink_if_exists($filename);
	safe_mkdir(FREERADIUS_MODSENABLED);
	file_put_contents($filename, $conf);
	chmod($filename, 0640);

}

function freeradius_motp_resync() {
	global $config, $bash_path;
	$conf = '';

	if (is_array($config['installedpackages']['freeradiussettings']['config'][0])) {
		$varsettings = $config['installedpackages']['freeradiussettings']['config'][0];
	} else {
		$varsettings = array();
	}

	$varsettingsmotptimespan = ($varsettings['varsettingsmotptimespan'] ?: '2');
	$varsettingsmotptimespanbeforeafter = $varsettingsmotptimespan + $varsettingsmotptimespan;
	$varsettingsmotpdeleteoldpasswords = $varsettingsmotptimespanbeforeafter + 1;
	$varsettingsmotppasswordattempts = ($varsettings['varsettingsmotppasswordattempts'] ?: '5');
	$varsettingsmotpchecksumtype = ($varsettings['varsettingsmotpchecksumtype'] ?: 'md5');
	$varsettingsmotptokenlength = ($varsettings['varsettingsmotptokenlength'] ?: '1-6');

	// check if disabled then we delete otpverify.sh script
	if ($varsettings['varsettingsmotpenable'] == '') {
		if (file_exists(FREERADIUS_SCRIPTS . "/otpverify.sh")) {
			@unlink(FREERADIUS_SCRIPTS . "/otpverify.sh");
		}
	} else {
		$conf .= <<<EOD
#!{$bash_path}
#
# Mobile One Time Passwords (Mobile-OTP) for Java 2 Micro Edition, J2ME
# written by Matthias Straub, Heilbronn, Germany, 2003
# (c) 2003 by Matthias Straub
# Modified 2012 by Alexander Wilke <nachtfalkeaw@web.de>
#
# Version 1.05a
#
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU Library General Public
# License as published by the Free Software Foundation; either
# version 2 of the License, or (at your option) any later version.
#
# This software is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
# Library General Public License for more details.
#
# arguments:  \$1 \$2 \$3 \$4 \$5
# \$1 - username
# \$2 - one-time-password that is to be checked
# \$3 - init-secred from token (to init token: #**#)
# \$4 - user PIN
# \$5 - time difference between token and server in 10s of seconds (360 = 1 hour)
#
# one-time-password must match md5(EPOCHTIME+SECRET+PIN)
#
#
# otpverify.sh version 1.04b, Feb. 2003
# otpverify.sh version 1.04c, Nov. 2008
#  changed line 1 to ksh because of problems with todays bash an sh
# otpverify.sh version 1.05a, Jan. 2011
#  changed back to bash and added in shopts line to ensure aliases handled
#  correctly (bash is always available on any modern *nix unlike ksh)
#

PATH=\$PATH:/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin

    alias checksum={$varsettingsmotpchecksumtype}
    have_md5="true"

# ensure aliases are expanded by bash
shopt -s expand_aliases

function chop
{
	num=`echo -n "\$1" | wc -c | sed 's/ //g' `
	nummin1=`expr \$num "-" 1`
	echo -n "\$1" | cut -b 1-\$nummin1
}

if [ ! \$# -eq 5 ] ; then
echo "USAGE: otpverify.sh Username, OTP, Init-Secret, PIN, Offset"
logger -f /var/log/system.log "FreeRADIUS: Mobile-One-Time-Password - wrong syntax - USAGE: otpverify.sh Username, OTP, Init-Secret, PIN, Offset";
exit 14
fi

mkdir /var/log/motp 2>/dev/null
mkdir /var/log/motp/cache 2>/dev/null
mkdir /var/log/motp/users 2>/dev/null
chmod og-rxw /var/log/motp 2>/dev/null || { echo "FAIL! Need write-access to /var/log/motp";logger -f /var/log/system.log "FreeRADIUS: Mobile-One-Time-Password - need write-access to /var/log/motp"; exit 17; }
chmod og-rxw /var/log/motp/cache
chmod og-rxw /var/log/motp/users

USERNAME=`echo -n "\$1" | sed 's/[^0-9a-zA-Z._-]/X/g' `
PASSWD=`echo -n "\$2" | sed 's/[^0-9a-f]/0/g' `
SECRET=`echo -n "\$3" | sed 's/[^0-9a-f]/0/g' `
PIN=`echo -n "\$4" | sed 's/[^0-9]/0/g' `
OFFSET=`echo -n "\$5" | sed 's/[^0-9-]/0/g' `
EPOCHTIME=`date +%s` ; EPOCHTIME=`chop \$EPOCHTIME`

# delete old logins
find /var/log/motp/cache -type f -cmin +{$varsettingsmotpdeleteoldpasswords} | xargs rm 2>/dev/null

if [ -e "/var/log/motp/cache/\$PASSWD" ]; then
	echo "FAIL"
	logger -f /var/log/system.log "FreeRADIUS: Authentication failed! Mobile-One-Time-Password \$PASSWD is already used!"
	exit 15
fi

# account locked?
if [ "`cat /var/log/motp/users/\$USERNAME 2>/dev/null`" == "{$varsettingsmotppasswordattempts}" ]; then
	echo "FAIL"
	logger -f /var/log/system.log "FreeRADIUS: Authentication failed! Too many wrong password attempts. User is locked! To unlock delete /var/log/motp/users/\$USERNAME"
	exit 13
fi

I=0
EPOCHTIME=`expr \$EPOCHTIME - {$varsettingsmotptimespan}`
EPOCHTIME=`expr \$EPOCHTIME + \$OFFSET`
while [ \$I -lt {$varsettingsmotptimespanbeforeafter} ] ; do # `$varsettingsmotptimespan * 10` seconds before and after
	OTP=`printf \$EPOCHTIME\$SECRET\$PIN|checksum|cut -b {$varsettingsmotptokenlength}`
	if [ "\$OTP" = "\$PASSWD" ] ; then
		touch /var/log/motp/cache/\$OTP || { echo "FAIL! Need write-access to /var/log/motp";logger -f /var/log/system.log "FreeRADIUS: Mobile-One-Time-Password - need write-access to /var/log/motp/cache"; exit 17; }
		echo "ACCEPT"
		logger -f /var/log/system.log "FreeRADIUS: Authentication success! Mobile-One-Time-Password \$PASSWD for user \$USERNAME is correct!"
		rm "/var/log/motp/users/\$USERNAME" 2>/dev/null
		exit 0
	fi
	I=`expr \$I + 1`
	EPOCHTIME=`expr \$EPOCHTIME + 1`
done

echo "FAIL"
NUMFAILS=`cat "/var/log/motp/users/\$USERNAME" 2>/dev/null`
if [ "\$NUMFAILS" = "" ]; then
	NUMFAILS=0
fi
NUMFAILS=`expr \$NUMFAILS + 1`
echo \$NUMFAILS > "/var/log/motp/users/\$USERNAME"
NUMFAILSLEFT=`expr $varsettingsmotppasswordattempts - \$NUMFAILS`
logger -f /var/log/system.log "FreeRADIUS: Authentication failed! Mobile-One-Time-Password incorrect. \$NUMFAILSLEFT attempts left. "
exit 11

EOD;

	$filename = FREERADIUS_SCRIPTS . '/otpverify.sh';
	file_put_contents($filename, $conf);
	chmod($filename, 0750);
	}
}

function freeradius_modulesmotp_resync() {
	global $config, $bash_path;
	$conf = '';

	// put the constant to a variable
	$varFREERADIUS_SCRIPTS = FREERADIUS_SCRIPTS;

	$conf .= <<<EOD
exec motp {
		wait = yes
		program = "{$bash_path} {$varFREERADIUS_SCRIPTS}/otpverify.sh %{request:User-Name} %{request:User-Password} %{reply:MOTP-Init-Secret} %{reply:MOTP-PIN} %{reply:MOTP-Offset}"
}

EOD;

	$filename = FREERADIUS_MODSENABLED . '/motp';
	/* Remove what could be a symlink first before writing */
	@unlink_if_exists($filename);
	safe_mkdir(FREERADIUS_MODSENABLED);
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
}

function freeradius_modulesgoogleauth_resync() {
	global $config;
	$conf = '';

	// put the constant to a variable
	$varFREERADIUS_SCRIPTS = FREERADIUS_SCRIPTS;

	$conf .= <<<EOD
exec googleauth {
	wait = yes
	program = "{$varFREERADIUS_SCRIPTS}/googleauth.py %{request:User-Name} %{reply:MOTP-Init-Secret} %{reply:MOTP-PIN} %{request:User-Password}"
}

EOD;

	$filename = FREERADIUS_MODSENABLED . '/googleauth';
	/* Remove what could be a symlink first before writing */
	@unlink_if_exists($filename);
	safe_mkdir(FREERADIUS_MODSENABLED);
	file_put_contents($filename, $conf);
	chmod($filename, 0640);

	$googleauth_script = FREERADIUS_SCRIPTS . '/googleauth.py';
	if (!is_link($googleauth_script) && is_file(FREERADIUS_PKGSOURCE . '/googleauth.py')) {
		@unlink_if_exists($googleauth_script);
		chmod(FREERADIUS_PKGSOURCE . '/googleauth.py', 0755);
		symlink(FREERADIUS_PKGSOURCE . '/googleauth.py', $googleauth_script);
	}
}

function freeradius_modulesdatacounter_resync() {
	global $config;
	$conf = '';

	// put the constant to a variable
	$varFREERADIUS_SCRIPTS = FREERADIUS_SCRIPTS;

	$conf .= <<<EOD
exec datacounterdaily {
		wait = yes
		program = "/bin/sh {$varFREERADIUS_SCRIPTS}/datacounter_acct.sh %{request:User-Name} daily %{request:Acct-Input-Octets} %{request:Acct-Output-Octets} %{request:Acct-Status-Type} %{request:Acct-Session-Id}"
}
exec datacounterweekly {
		wait = yes
		program = "/bin/sh {$varFREERADIUS_SCRIPTS}/datacounter_acct.sh %{request:User-Name} weekly %{request:Acct-Input-Octets} %{request:Acct-Output-Octets} %{request:Acct-Status-Type} %{request:Acct-Session-Id}"
}
exec datacountermonthly {
		wait = yes
		program = "/bin/sh {$varFREERADIUS_SCRIPTS}/datacounter_acct.sh %{request:User-Name} monthly %{request:Acct-Input-Octets} %{request:Acct-Output-Octets} %{request:Acct-Status-Type} %{request:Acct-Session-Id}"
}
exec datacounterforever {
		wait = yes
		program = "/bin/sh {$varFREERADIUS_SCRIPTS}/datacounter_acct.sh %{request:User-Name} forever %{request:Acct-Input-Octets} %{request:Acct-Output-Octets} %{request:Acct-Status-Type} %{request:Acct-Session-Id}"
}

EOD;

	$filename = FREERADIUS_MODSENABLED . '/datacounter_acct';
	safe_mkdir(FREERADIUS_MODSENABLED);
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
}

function freeradius_datacounter_auth_resync() {
	global $config;
	$conf = '';

	$conf .= <<<EOD
#!/bin/sh
### USAGE: datacounter_auth.sh USERNAME TIMERANGE
### We need this parameters from freeradius users file and ../raddb/modules/datacounter_acct
USERNAME=`echo -n "\\$1" | sed 's/[^0-9a-zA-Z._:-]/X/g' `
TIMERANGE=`echo -n "\\$2" | sed 's/[^a-z]//g' `


### This is to make sure there is a used-octets file after the cronjob resetted the counter
if [ -e "/var/log/radacct/datacounter/\$TIMERANGE/max-octets-\$USERNAME" ] && [ ! -e "/var/log/radacct/datacounter/\$TIMERANGE/used-octets-\$USERNAME" ]; then
	echo 0 > "/var/log/radacct/datacounter/\$TIMERANGE/used-octets-\$USERNAME"
	rm "/var/log/radacct/datacounter/\$TIMERANGE/used-octets-\$USERNAME-"*
fi

### The next two lines are just for getting values for logging output
MAXOCTETSUSERNAMEMB=$((`/bin/cat "/var/log/radacct/datacounter/\$TIMERANGE/max-octets-\$USERNAME"`/1024/1024))
USEDOCTETSUSERNAMEMB=`/bin/cat "/var/log/radacct/datacounter/\$TIMERANGE/used-octets-\${USERNAME}"* | /usr/bin/awk '{ SUM += \$1; } END { print int(SUM/1024/1024); }'`

### We check if MAX-OCTETS-USERNAME is greater than USED-OCTETS-USERNAME and accept or reject the user
if [ \$MAXOCTETSUSERNAMEMB -gt \$USEDOCTETSUSERNAMEMB ]; then
	logger -f /var/log/system.log "FreeRADIUS: User \$USERNAME has used \$USEDOCTETSUSERNAMEMB MB of \$MAXOCTETSUSERNAMEMB MB \$TIMERANGE allotted traffic. The login request was accepted."
	exit 0
else
	logger -f /var/log/system.log "FreeRADIUS: User \$USERNAME has reached the \$TIMERANGE amount of upload and download traffic (\$USEDOCTETSUSERNAMEMB MB of \$MAXOCTETSUSERNAMEMB MB). The login request was denied."
	exit 99
fi

EOD;

	$filename = FREERADIUS_SCRIPTS . '/datacounter_auth.sh';
	file_put_contents($filename, $conf);
	chmod($filename, 0750);
}

function freeradius_datacounter_acct_resync() {
	global $config;
	$conf = '';

	$conf .= <<<EOD
#!/bin/sh
### USAGE: datacounter_acct.sh USERNAME TIMERANGE ACCTINPUTOCTETS ACCTOUTPUTOCTETS
### We need this from an Accounting-Request packet to count the octets
USERNAME=`echo -n "\\$1" | sed 's/[^0-9a-zA-Z.:_-]/X/g' `
TIMERANGE=`echo -n "\\$2" | sed 's/[^a-z]//g' `
ACCTINPUTOCTETS=`echo -n "\\$3" | sed 's/[^0-9]/0/g' `
ACCTOUTPUTOCTETS=`echo -n "\\$4" | sed 's/[^0-9]/0/g' `
UPDATETYPE=\$5
SESSIONID=\$6

### If we do not get Octets we set some default values
if [ ! \$ACCTINPUTOCTETS ]; then
	ACCTINPUTOCTETS=0
fi
if [ ! \$ACCTOUTPUTOCTETS ]; then
	ACCTOUTPUTOCTETS=0
fi

### We only write this to the file if username exists
### If all counters are activated (daily, weekly, monthly, forever) we need to check which is active for the user
if [ ! -e "/var/log/radacct/datacounter/\$TIMERANGE/max-octets-\$USERNAME" ]; then
	exit 0
else
	### If no used-octets file exist then we assume that it was deleted by cron job and we need to create a new file starting from zero
	if [ ! -e "/var/log/radacct/datacounter/\$TIMERANGE/used-octets-\$USERNAME" ]; then
		echo 0 > "/var/log/radacct/datacounter/\$TIMERANGE/used-octets-\$USERNAME"
	fi

	USEDOCTETS=\$((\$ACCTINPUTOCTETS+\$ACCTOUTPUTOCTETS))

	# If this is an interim update, track it in a separate session file
	# since the incoming data is a gauge not a counter.
	if [ \$UPDATETYPE = "Interim-Update" ]; then
		echo \$USEDOCTETS > "/var/log/radacct/datacounter/\$TIMERANGE/used-octets-\$USERNAME-\$SESSIONID"
	else
		USEDOCTETS=\$((\$USEDOCTETS+`cat "/var/log/radacct/datacounter/\$TIMERANGE/used-octets-\$USERNAME"`))

		# If there was a session file for this session (from interim updates) clear it since the equivalent
		# value was just added to the total.
		if [ -e "/var/log/radacct/datacounter/\$TIMERANGE/used-octets-\$USERNAME-\$SESSIONID" ]; then
			rm "/var/log/radacct/datacounter/\$TIMERANGE/used-octets-\$USERNAME-\$SESSIONID"
		fi
		echo "\$USEDOCTETS" > "/var/log/radacct/datacounter/\$TIMERANGE/used-octets-\$USERNAME"
	fi
	exit 0
fi

EOD;

	$filename = FREERADIUS_SCRIPTS . '/datacounter_acct.sh';
	file_put_contents($filename, $conf);
	chmod($filename, 0750);
}

function freeradius_dictionary_resync() {
	global $config;
	$conf = '';

	$conf .= <<<EOD
# Local dictionary, does not need to include the master dictionary
ATTRIBUTE 		MOTP-Init-Secret 		900 	string
ATTRIBUTE 		MOTP-PIN 			901 	string
ATTRIBUTE 		MOTP-Offset 			902 	string

\$INCLUDE /usr/share/doc/radius/dictionary.pfsense

EOD;

	$filename = FREERADIUS_ETC . '/raddb/dictionary';
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
}

/*
 * FreeRADIUS input validation
 */

/* Users input validation */
function freeradius_validate_users($post, &$input_errors) {

	// Username
	if (($post['varusersmotpenable'] == 'on') && !preg_match('/^[a-zA-Z0-9_.-]*$/', $post['varusersusername'])) {
		$input_errors[] = "The 'Username' field may only contain a-z, A-Z, 0-9, underscore, period and hyphen (regex /^[a-zA-Z0-9_.-]*$/).";
	}

	// Password
	if (($post['varusersmotpenable'] == 'on') && ($post['varuserspassword'] != '')) {
		$input_errors[] = "The 'Password' field must be left empty when 'Enable One-Time-Password for this user' is checked.";
	}

	// OTP method
	if ($post['varusersmotpenable'] == 'on') {
		if ($post['varusersauthmethod'] == '') {
			$input_errors[] = "The 'Authentication method' field may not be empty when 'Enable One-Time-Password for this user' is checked.";
		} elseif (!preg_match('/^(motp|googleauth)$/', $post['varusersauthmethod'])) {
			$input_errors[] = "The 'Authentication method' field contains an invalid selection.";
		}
	}

	// Init-Secret
	if ($post['varusersmotpenable'] == 'on') {
		if ($post['varusersmotpinitsecret'] == '') {
			$input_errors[] = "The 'Init-Secret' field may not be empty when 'Enable One-Time-Password for this user' is checked.";
		} else {
			if (preg_match('/^(motp)$/', $post['varusersauthmethod'])) {
				if (!preg_match('/^[0-9a-f]{16,}$/', $post['varusersmotpinitsecret'])) {
					$input_errors[] = "The 'Init-Secret' field may only contain 0-9 and a-f (regex /^[0-9a-f]*$/) and must contain at least 16 characters.";
				}
			} elseif (preg_match('/^(googleauth)$/', $post['varusersauthmethod'])) {
				if (!preg_match('/^[0-9a-zA-Z]{16,}$/', $post['varusersmotpinitsecret'])) {
					$input_errors[] = "The 'Init-Secret' field may only contain 0-9, a-z and A-Z (regex /^[0-9a-zA-Z]*$/) and must contain at least 16 characters.";
				}
			}
		}
	}

	// PIN
	if ($post['varusersmotpenable'] == 'on') {
		if ($post['varusersmotppin'] == '') {
			$input_errors[] = "The 'PIN' field may not be empty when 'Enable One-Time-Password for this user' is checked.";
		} elseif (!preg_match('/^[0-9]{4,8}$/', $post['varusersmotppin'])) {
			$input_errors[] = "The 'PIN' field may only contain a PIN consisting of 4-8 digits.";
		}
	}

	// Time Offset
	if ($post['varusersmotpoffset']) {
		if (!preg_match('/^-?[0-9]{2,4}$/', $post['varusersmotpoffset'])) {
			$input_errors[] = "The 'Time Offset' field may only contain a valid TZ offset in minutes (must be divisible by 15), with optional leading minus character.";
		} else {
			// Only accept sane values divisible by 15
			$offset = str_replace('-', '', $post['varusersmotpoffset']);
			if ((is_numericint($offset)) && ($offset %15 != 0)) {
				$input_errors[] = "The 'Time Offset' value '{$offset}' is not a valid TZ offset - this must be divisible by 15.";
			}
		}
	}

	// Redirection URL
	if (!empty($post['varuserswisprredirectionurl'])) {
		if (!filter_var($post['varuserswisprredirectionurl'], FILTER_VALIDATE_URL)) {
			$input_errors[] = "The 'Redirection URL' field must contain a valid URL.";
		}
	}

	// Number of Simultaneous Connections
	if ($post['varuserssimultaneousconnect'] != '' && !is_numericint($post['varuserssimultaneousconnect'])) {
		$input_errors[] = "The 'Number of Simultaneous Connections' field must contain an integer value.";
	}

	// Description
	if ($post['description'] && !preg_match("/^[a-zA-Z0-9 _,.;:+=()-]*$/", $post['description'])) {
		$input_errors[] = "Do not use special characters in the 'Description' field; only /^[a-zA-Z0-9 _,.;:+=()-]*$/ allowed.";
	}

	// IP Address; may contain a single trailing '+' for simultaneous connections
	if ($post['varusersframedipaddress']) {
		$framedip = str_replace('+', '', $post['varusersframedipaddress'], $pluscnt);
		if (!is_ipaddrv4($framedip)) {
			$input_errors[] = "The 'IPv4 Address' field must contain a valid IPv4 address.";
		}
		if ($pluscnt > 1) {
			$input_errors[] = "The 'IPv4 Address' field may optionally contain only a single trailing '+'.";
		}
	}

	// Subnet Mask
	if ($post['varusersframedipnetmask']) {
		if ($post['varusersframedipaddress'] == '') {
			$input_errors[] = "To specify a 'Subnet Mask', the 'IPv4 Address' field must not be empty.";
		} elseif (is_ipaddrv4($framedip)) {
			$ip = long2ip(ip2long($framedip) & ip2long($post['varusersframedipnetmask']));
			$mask = 32 - log((ip2long($post['varusersframedipnetmask']) ^ ip2long('255.255.255.255')) +1, 2);
			if (!is_subnetv4("{$ip}/{$mask}")) {
				$input_errors[] = "The 'Subnet Mask' field must contain a valid subnet mask.";
			}
		}
	}

	// Gateway
	if ($post['varusersframedroute'] != '') {
		$framedroute = explode(" ", $post['varusersframedroute']);
		$cnt = count($framedroute);
		// One or more metrics are allowed per RFC2865 (5.22)
		if ($cnt < 3) {
			$input_errors[] = "The 'IPv4 Gateway' field must match the required format: Subnet Gateway Metric(s) (e.g. 192.168.10.0/24 192.168.10.1 1).";
		}
		// The subnet CIDR is optional per RFC2865 (5.22)
		if (!is_ipaddrv4($framedroute[0]) && !is_subnetv4($framedroute[0])) {
			$input_errors[] = "The 'IPv4 Gateway' field's subnet part '{$framedroute[0]}' must be a valid IPv4 subnet.";
		}
		if (!is_ipaddrv4($framedroute[1])) {
			$input_errors[] = "The 'IPv4 Gateway' field's gateway part '{$framedroute[1]}' must be a valid IPv4 gateway address.";
		}
		// One or more metrics are allowed per RFC2865 (5.22)
		for ($i = 2; $i < $cnt; $i++) {
			if (!is_numericint($framedroute[$i])) {
				$input_errors[] = "The 'IPv4 Gateway' field's metric part '{$framedroute[$i]}' must contain an integer value.";
			}
		}
	}

	// IPv6 Address
	if ($post['varusersframedip6address'] && !is_subnetv6($post['varusersframedip6address']) &&
	    !is_ipaddrv6($post['varusersframedip6address'])) {
		$input_errors[] = "The 'IPv6 Address' field must contain a valid IPv6 address or IPv6 prefix.";
	}

	// IPv6 Gateway
	if ($post['varusersframedip6route'] != '') {
		$framed6route = explode(" ", $post['varusersframedip6route']);
		$cnt = count($framed6route);
		if ($cnt < 3) {
			$input_errors[] = "The 'IPv6 Gateway' field must match the required format: Prefix Gateway Metric(s) (e.g. 2001:db8:0:106::/64 2001:db8::106:a00:20ff:fe99:a998 1).";
		}
		// The prefix is optional per RFC3162 (2.5)
		if (!is_ipaddrv6($framed6route[0]) && !is_subnetv6($framed6route[0])) {
			$input_errors[] = "The 'IPv6 Gateway' field's prefix part '{$framed6route[0]}' must be a valid IPv6 prefix.";
		}
		if (!is_ipaddrv6($framed6route[1])) {
			$input_errors[] = "The 'IPv6 Gateway' field's gateway part '{$framed6route[1]}' must be a valid IPv6 gateway address.";
		}
		for ($i = 2; $i < $cnt; $i++) {
			if (!is_numericint($framed6route[$i])) {
				$input_errors[] = "The 'IPv6 Gateway' field's metric part '{$framed6route[$i]}' must contain an integer value.";
			}
		}
	}

	// Expiration Date
	if ($post['varusersexpiration'] != '') {
		$expires = date_parse_from_format("M d Y", $post['varusersexpiration']);
		if ($expires['error_count'] > 0 || $expires['warning_count'] > 0) {
			$input_errors[] = "The 'Expiration Date' format is invalid. | Error(s): " .
			    implode('. ', $expires['errors']) . " | Warning(s): " .
			    implode('. ', $expires['warnings']);
		}
		// Hack around date_parse_from_format() bugs, such as expanding "Jan 199" to "Jan 1 99"
		if ($expires['year'] < 1970) {
			$input_errors[] = "The 'Expiration Date' contains an invalid year.";
		}
	}

	// Session Timeout
	if ($post['varuserssessiontimeout'] != '' && !is_numericint($post['varuserssessiontimeout'])) {
		$input_errors[] = "The 'Session Timeout' field must contain an integer value.";
	}

	// Possible Login Times
	// TODO: Produce some regex or better check here
	if ($post['varuserslogintime'] && !preg_match("/^[a-zA-Z0-9,|-]*$/", $post['varuserslogintime'])) {
		$input_errors[] = "The 'Possible Login Times' field may only contain a-z, A-Z, 0-9, comma, vertical bar and hyphen (regex /^[a-zA-Z0-9,|-]*$/)";
	}

	// Amount of Time
	if ($post['varusersamountoftime'] != '' && !is_numericint($post['varusersamountoftime'])) {
		$input_errors[] = "The 'Amount of Time' field must contain an integer value.";
	}

	// Amount of Download and Upload Traffic
	if ($post['varusersmaxtotaloctets'] != '' && !is_numericint($post['varusersmaxtotaloctets'])) {
		$input_errors[] = "The 'Amount of Download and Upload Traffic' field must contain an integer value.";
	}

	// Maximum Bandwidth Down
	if ($post['varusersmaxbandwidthdown'] != '' && !is_numericint($post['varusersmaxbandwidthdown'])) {
		$input_errors[] = "The 'Maximum Bandwidth Down' field must contain an integer value.";
	}

	// Maximum Bandwidth Up
	if ($post['varusersmaxbandwidthup'] != '' && !is_numericint($post['varusersmaxbandwidthup'])) {
		$input_errors[] = "The 'Maximum Bandwidth Up' field must contain an integer value.";
	}

	// Accounting Interim Interval
	if ($post['varusersacctinteriminterval'] != '') {
		if (!is_numericint($post['varusersacctinteriminterval']) || $post['varusersacctinteriminterval'] <= 60) {
			$input_errors[] = "The 'Accounting Interim Interval' field must contain an integer with a value larger than 60 (seconds).";
		}
	}

	/*
	 * TODO:
	 * Additional RADIUS Attributes on the TOP of this entry, Additional RADIUS Attributes (CHECK-ITEM), Additional RADIUS Attributes (REPLY-ITEM)
	 */

}

/* MACs input validation */
function freeradius_validate_macs($post, &$input_errors) {

	// MAC Address
	if (!empty($post['varmacsaddress'])) {
		if (!preg_match('/^[0-9A-F]{2}(?:[-:][0-9A-F]{2}){5}$/i', $post['varmacsaddress'])) {
			$input_errors[] = "The 'MAC Address' field must contain a valid MAC address, delimited with '-' or ':' character.";
		}
	}

	// Redirection URL
	if (!empty($post['varmacsswisprredirectionurl'])) {
		if (!filter_var($post['varmacsswisprredirectionurl'], FILTER_VALIDATE_URL)) {
			$input_errors[] = "The 'Redirection URL' field must contain a valid URL.";
		}
	}

	// Number of Simultaneous Connections
	if ($post['varmacssimultaneousconnect'] != '' && !is_numericint($post['varmacssimultaneousconnect'])) {
		$input_errors[] = "The 'Number of Simultaneous Connections' field must contain an integer value.";
	}

	// Description
	if ($post['description'] && !preg_match("/^[a-zA-Z0-9 _,.;:+=()-]*$/", $post['description'])) {
		$input_errors[] = "Do not use special characters in the 'Description' field; only /^[a-zA-Z0-9 _,.;:+=()-]*$/ allowed.";
	}

	// IP Address; may contain a single trailing '+' for simultaneous connections
	if ($post['varmacsframedipaddress']) {
		$framedip = str_replace('+', '', $post['varmacsframedipaddress'], $pluscnt);
		if (!is_ipaddrv4($framedip)) {
			$input_errors[] = "The 'IPv4 Address' field must contain a valid IPv4 address.";
		}
		if ($pluscnt > 1) {
			$input_errors[] = "The 'IPv4 Address' field may optionally contain only a single trailing '+'.";
		}
	}

	// Subnet Mask
	if ($post['varmacsframedipnetmask']) {
		if ($post['varmacsframedipaddress'] == '') {
			$input_errors[] = "To specify a 'Subnet Mask', the 'IPv4 Address' field must not be empty.";
		} elseif (is_ipaddrv4($framedip)) {
			$ip = long2ip(ip2long($framedip) & ip2long($post['varmacsframedipnetmask']));
			$mask = 32 - log((ip2long($post['varmacsframedipnetmask']) ^ ip2long('255.255.255.255')) +1, 2);
			if (!is_subnetv4("{$ip}/{$mask}")) {
				$input_errors[] = "The 'Subnet Mask' field must contain a valid subnet mask.";
			}
		}
	}

	// Gateway
	if ($post['varmacsframedroute'] != '') {
		$framedroute = explode(" ", $post['varmacsframedroute']);
		$cnt = count($framedroute);
		// One or more metrics are allowed per RFC2865 (5.22)
		if ($cnt < 3) {
			$input_errors[] = "The 'IPv4 Gateway' field must match the required format: Subnet Gateway Metric(s) (e.g. 192.168.10.0/24 192.168.10.1 1).";
		}
		// The subnet CIDR is optional per RFC2865 (5.22)
		if (!is_ipaddrv4($framedroute[0]) && !is_subnetv4($framedroute[0])) {
			$input_errors[] = "The 'IPv4 Gateway' field's subnet part '{$framedroute[0]}' must be a valid IPv4 subnet.";
		}
		if (!is_ipaddrv4($framedroute[1])) {
			$input_errors[] = "The 'IPv4 Gateway' field's gateway part '{$framedroute[1]}' must be a valid IPv4 gateway address.";
		}
		// One or more metrics are allowed per RFC2865 (5.22)
		for ($i = 2; $i < $cnt; $i++) {
			if (!is_numericint($framedroute[$i])) {
				$input_errors[] = "The 'IPv4 Gateway' field's metric part '{$framedroute[$i]}' must contain an integer value.";
			}
		}
	}

	// IPv6 Address
	if ($post['varmacsframedip6address'] && !is_subnetv6($post['varmacsframedip6address']) &&
	    !is_ipaddrv6($post['varmacsframedip6address'])) {
		$input_errors[] = "The 'IPv6 Address' field must contain a valid IPv6 address or IPv6 prefix.";
	}

	// IPv6 Gateway
	if ($post['varmacsframedip6route'] != '') {
		$framed6route = explode(" ", $post['varmacsframedip6route']);
		$cnt = count($framed6route);
		if ($cnt < 3) {
			$input_errors[] = "The 'IPv6 Gateway' field must match the required format: Prefix Gateway Metric(s) (e.g. 2001:db8:0:106::/64 2001:db8::106:a00:20ff:fe99:a998 1).";
		}
		// The prefix is optional per RFC3162 (2.5)
		if (!is_ipaddrv6($framed6route[0]) && !is_subnetv6($framed6route[0])) {
			$input_errors[] = "The 'IPv6 Gateway' field's prefix part '{$framed6route[0]}' must be a valid IPv6 prefix.";
		}
		if (!is_ipaddrv6($framed6route[1])) {
			$input_errors[] = "The 'IPv6 Gateway' field's gateway part '{$framed6route[1]}' must be a valid IPv6 gateway address.";
		}
		for ($i = 2; $i < $cnt; $i++) {
			if (!is_numericint($framed6route[$i])) {
				$input_errors[] = "The 'IPv6 Gateway' field's metric part '{$framed6route[$i]}' must contain an integer value.";
			}
		}
	}

	// VLAN ID
	if ($post['varmacsvlanid'] != '') {
		if (!is_numericint($post['varmacsvlanid'])) {
			$input_errors[] = "The 'VLAN ID' field must contain an integer value.";
		} elseif ($post['varmacsvlanid'] < 1 || $post['varmacsvlanid'] > 4095) {
			$input_errors[] = "The 'VLAN ID' must be in the 1-4095 range.";
		}
	}

	// Expiration Date
	if ($post['varmacsexpiration'] != '') {
		$expires = date_parse_from_format("M d Y", $post['varmacsexpiration']);
		if ($expires['error_count'] > 0 || $expires['warning_count'] > 0) {
			$input_errors[] = "The 'Expiration Date' format is invalid. | Error(s): " .
			    implode('. ', $expires['errors']) . " | Warning(s): " .
			    implode('. ', $expires['warnings']);
		}
		// Hack around date_parse_from_format() bugs, such as expanding "Jan 199" to "Jan 1 99"
		if ($expires['year'] < 1970) {
			$input_errors[] = "The 'Expiration Date' contains an invalid year.";
		}
	}

	// Session Timeout
	if ($post['varmacssessiontimeout'] != '' && !is_numericint($post['varmacssessiontimeout'])) {
		$input_errors[] = "The 'Session Timeout' field must contain an integer value.";
	}

	// Possible Login Times
	// TODO: Produce some regex or better check here
	if ($post['varmacslogintime'] && !preg_match("/^[a-zA-Z0-9,|-]*$/", $post['varmacslogintime'])) {
		$input_errors[] = "The 'Possible Login Times' field may only contain a-z, A-Z, 0-9, comma, vertical bar and hyphen (regex /^[a-zA-Z0-9,|-]*$/)";
	}

	// Amount of Time
	if ($post['varmacsamountoftime'] != '' && !is_numericint($post['varmacsamountoftime'])) {
		$input_errors[] = "The 'Amount of Time' field must contain an integer value.";
	}

	// Amount of Download and Upload Traffic
	if ($post['varmacsmaxtotaloctets'] != '' && !is_numericint($post['varmacsmaxtotaloctets'])) {
		$input_errors[] = "The 'Amount of Download and Upload Traffic' field must contain an integer value.";
	}

	// Maximum Bandwidth Down
	if ($post['varmacsmaxbandwidthdown'] != '' && !is_numericint($post['varmacsmaxbandwidthdown'])) {
		$input_errors[] = "The 'Maximum Bandwidth Down' field must contain an integer value.";
	}

	// Maximum Bandwidth Up
	if ($post['varmacsmaxbandwidthup'] != '' && !is_numericint($post['varmacsmaxbandwidthup'])) {
		$input_errors[] = "The 'Maximum Bandwidth Up' field must contain an integer value.";
	}

	// Accounting Interim Interval
	if ($post['varmacsacctinteriminterval'] != '') {
		if (!is_numericint($post['varmacsacctinteriminterval']) || $post['varmacsacctinteriminterval'] <= 60) {
			$input_errors[] = "The 'Accounting Interim Interval' field must contain an integer with a value larger than 60 (seconds).";
		}
	}

	/*
	 * TODO:
	 * Additional RADIUS Attributes on the TOP of this entry, Additional RADIUS Attributes (CHECK-ITEM), Additional RADIUS Attributes (REPLY-ITEM)
	 */

}

/* NAS/Clients input validation */
function freeradius_validate_clients($post, &$input_errors) {

	// Client IP Address
	if ($post['varclientip'] != '*') {
		if (preg_match('/^(.+)\/(\d+)$/',$post['varclientip'], $matches)) {
			if ($post['varclientipversion'] == 'ipaddr' && (!is_ipaddrv4($matches[1]) ||
			    !is_numeric($matches[2]) || ($matches[2] > 32) || ($matches[2] < 1))) {
				$input_errors[] = "The 'Client IP Address' field must contain a valid IPv4 address and CIDR prefix when IPv4 is selected under 'Client IP Version'.";
			}
			if ($post['varclientipversion'] == 'ipv6addr' && (!is_ipaddrv6($matches[1]) ||
			    !is_numeric($matches[2]) || ($matches[2] > 128) || ($matches[2] < 1))) {
				$input_errors[] = "The 'Client IP Address' field must contain a valid IPv6 address and prefix when IPv6 is selected under 'Client IP Version'.";
			}
		} else {
			if ($post['varclientipversion'] == 'ipaddr' && !is_ipaddrv4($post['varclientip'])) {
				$input_errors[] = "The 'Client IP Address' field must contain a valid IPv4 address when IPv4 is selected under 'Client IP Version'.";
			}
			if ($post['varclientipversion'] == 'ipv6addr' && !is_ipaddrv6($post['varclientip'])) {
				$input_errors[] = "The 'Client IP Address' field must contain a valid IPv6 address when IPv6 is selected under 'Client IP Version'.";
			}
		}
	}

	// Client Shortname
	if ($post['varclientshortname'] && !preg_match("/^[a-zA-Z0-9_.-]*$/", $post['varclientshortname'])) {
		$input_errors[] = "The 'Client Shortname' field may only contain a-z, A-Z, 0-9, underscore, period and hyphen (regex /^[a-zA-Z0-9_.-]*$/)";
	}

	// Client Shared Secret
	if (strlen($post['varclientsharedsecret']) > 31) {
		$input_errors[] = "The 'Client Shared Secret' fields contains too many characters. FreeRADIUS is limited to 31 characters for shared secret.";
	}

	// Description
	if ($post['description'] && !preg_match("/^[a-zA-Z0-9 _,.;:+=()-]*$/", $post['description'])) {
		$input_errors[] = "Do not use special characters in the 'Description' field; only /^[a-zA-Z0-9 _,.;:+=()-]*$/ allowed.";
	}

	/*
	 * TODO: Check NAS Login for sanity.
	 */

}

/* Interfaces input validation */
function freeradius_validate_interfaces($post, &$input_errors) {

	// Interface IP Address
	if (empty($post['varinterfaceip'])) {
		$input_errors[] = "The 'Interface IP Address' field must not be empty.";
	} elseif ($post['varinterfaceip'] != '*') {
		if ($post['varinterfaceipversion'] == 'ipaddr' && !is_ipaddrv4($post['varinterfaceip'])) {
			$input_errors[] = "The 'Interface IP Address' must contain a valid IPv4 address when IPv4 is selected under 'IP Version'.";
		}
		if ($post['varinterfaceipversion'] == 'ipv6addr' && !is_ipaddrv6($post['varinterfaceip'])) {
			$input_errors[] = "The 'Interface IP Address' must contain a valid IPv6 address when IPv6 is selected under 'IP Version'.";
		}
		if (!is_ipaddr_configured($post['varinterfaceip']) && $post['varinterfaceip'] != "127.0.0.1") {
			$input_errors[] = "The 'Interface IP Address' must contain a valid, locally configured IP address!";
		}
	}

	// Port
	if (empty($post['varinterfaceport'])) {
		$input_errors[] = "The 'Port' field must not be empty.";
	} elseif (!is_port($post['varinterfaceport'])) {
		$input_errors[] = "The 'Port' field must contain a valid port.";
	}

	// Description
	if ($post['description'] && !preg_match("/^[a-zA-Z0-9 _,.;:+=()-]*$/", $post['description'])) {
		$input_errors[] = "Do not use special characters in the 'Description' field; only /^[a-zA-Z0-9 _,.;:+=()-]*$/ allowed.";
	}

	/*
	 * TODO: Check that the configured port is unique for the selected Interface Type/IP address.
	 */

}

/* General Settings input validation */
function freeradius_validate_settings($post, &$input_errors) {
	global $config;
	// Maximum Requests Tracked
	if ($post['varsettingsmaxrequests'] != '' && !is_numericint($post['varsettingsmaxrequests'])) {
		$input_errors[] = "The 'Maximum Requests Tracked' field must contain an integer value.";
	}

	// Maximum Request Timeout
	if ($post['varsettingsmaxrequesttime'] != '' && !is_numericint($post['varsettingsmaxrequesttime'])) {
		$input_errors[] = "The 'Maximum Request Timeout' field must contain an integer value.";
	}

	// Cleanup Delay
	if ($post['varsettingscleanupdelay'] != '' && !is_numericint($post['varsettingscleanupdelay'])) {
		$input_errors[] = "The 'Cleanup Delay' field must contain an integer value.";
	}

	// Maximum Number of Attributes
	if ($post['varsettingsmaxattributes'] != '' && !is_numericint($post['varsettingsmaxattributes'])) {
		$input_errors[] = "The 'Maximum Number of Attributes' field must contain an integer value.";
	}

	// Access-Reject Delay
	if ($post['varsettingsrejectdelay'] != '' && !is_numericint($post['varsettingsrejectdelay'])) {
		$input_errors[] = "The 'Access-Reject Delay' field must contain an integer value.";
	}

	// Number of Threads After Start
	if ($post['varsettingsstartservers'] != '' && !is_numericint($post['varsettingsstartservers'])) {
		$input_errors[] = "The 'Number of Threads After Start' field must contain an integer value.";
	}

	// Maximum Number of Threads
	if ($post['varsettingsmaxservers'] != '' && !is_numericint($post['varsettingsmaxservers'])) {
		$input_errors[] = "The 'Maximum Number of Threads' field must contain an integer value.";
	}

	// Minimum Spare Servers
	if ($post['varsettingsminspareservers'] != '' && !is_numericint($post['varsettingsminspareservers'])) {
		$input_errors[] = "The 'Minimum Spare Servers' field must contain an integer value.";
	}

	// Maximum Spare Servers
	if ($post['varsettingsmaxspareservers'] != '' && !is_numericint($post['varsettingsmaxspareservers'])) {
		$input_errors[] = "The 'Maximum Spare Servers' field must contain an integer value.";
	}

	// Server Packet Queue Size
	if ($post['varsettingsmaxqueuesize'] != '' && !is_numericint($post['varsettingsmaxqueuesize'])) {
		$input_errors[] = "The 'Server Packet Queue Size' field must contain an integer value.";
	}

	// Maximum Requests Per Server
	if ($post['varsettingsmaxrequestsperserver'] != '' && !is_numericint($post['varsettingsmaxrequestsperserver'])) {
		$input_errors[] = "The 'Maximum Requests Per Server' field must contain an integer value.";
	}

	// OTP Lifetime
	if ($post['varsettingsmotpenable'] == 'on') {
		if (empty($post['varsettingsmotptimespan'])) {
			$input_errors[] = "The 'OTP Lifetime' field must not be 0 or empty.";
		} elseif (!is_numericint($post['varsettingsmotptimespan'])) {
			$input_errors[] = "The 'OTP Lifetime' field must contain an integer value.";
		} elseif ($post['varsettingsmotptimespan'] > 12) {
			$input_errors[] = "The 'OTP Lifetime' field should contain only secure values. Values higher than 12 (~120 seconds) are not allowed.";
		}
	}

	// Number of Invalid Password Attempts
	if ($post['varsettingsmotpenable'] == 'on') {
		if (empty($post['varsettingsmotppasswordattempts'])) {
			$input_errors[] = "The 'Invalid Password Attempts' field must not be 0 or empty.";
		} elseif (!is_numericint($post['varsettingsmotppasswordattempts'])) {
			$input_errors[] = "The 'Invalid Password Attempts' field must contain an integer value.";
		} elseif ($post['varsettingsmotppasswordattempts'] > 20) {
			$input_errors[] = "The 'Invalid Password Attempts' field should contain only secure values. Values higher than 20 are not allowed.";
		}
	}

	// Token Password Length
	if ($post['varsettingsmotptokenlength'] != '') {
		if (!preg_match('/^[0-9]{1,2}(?:[-][0-9]{1,2}){1}$/i', $post['varsettingsmotptokenlength'])) {
			$input_errors[] = "The 'Token Password Length' field must contain a valid integer range between 1 and 16, separated with a '-' character; e.g.: 1-6.";
		} elseif (!is_intrange($post['varsettingsmotptokenlength'], 1, 16)) {
			$input_errors[] = "The 'Token Password Length' field must contain a valid integer range between 1 and 16, separated with a '-' character; e.g.: 1-6.";
		}
	}

	/*
	 * TODO:
	 * Logging Configuration - Additional Information for Bad Attempts, Additional Information for Good Attempts
	 * Miscellaneous Configuration - Default Profile, Profile Attribute, Access Attribute
	 * Group Membership Options - Groupname Attribute, Group Membership Filter, Group Membership Attribute
	 */

}

/* EAP settings input validation */
function freeradius_validate_eap($post, &$input_errors) {

	// Disable Weak EAP Types sanity check
	if ($post['vareapconfdisableweakeaptypes'] == 'on') {
		if (in_array($post['vareapconfdefaulteaptype'], array('md5', 'gtc', 'leap'))) {
			$input_errors[] = "The 'Default EAP Type' field must not contain any of the weak types when 'Disable Weak EAP Types' is enabled.";
		}
	}

	// EAP Maximum Sessions Tracking Per Server
	if ($post['vareapconfmaxsessions'] != '' && !is_numericint($post['vareapconfmaxsessions'])) {
		$input_errors[] = "The 'Maximum Sessions Tracking Per Server' field must contain an integer value.";
	}

/*
	// Certificates for TLS
	if ($post['ssl_ca_cert'] == 'none') {
		$input_errors[] = "SSL CA Certificate must not be set to 'none'. Create a CA certificate if needed and select it here.";
	}
	if ($post['ssl_server_cert'] == 'none') {
		$input_errors[] = "SSL Server Certificate must not be set to 'none'. Create a server certificate if needed and select it here.";
	}
*/

	// EAP-TLS Fragment Size
	if ($post['vareapconffragmentsize'] != '') {
		if (!is_numericint($post['vareapconffragmentsize']) || $post['vareapconffragmentsize'] > 4096) {
			$input_errors[] = "The 'Fragment Size' field must contain an integer value not higher than 4096.";
		}
	}

	// EAP-TLS Cache Lifetime
	if ($post['vareapconfcachelifetime'] != '' && !is_numericint($post['vareapconfcachelifetime'])) {
		$input_errors[] = "The EAP-TLS Cache 'Lifetime' field must contain an integer value.";
	}

	// EAP-TLS Cache Max Entries
	if ($post['vareapconfcachemaxentries'] != '' && !is_numericint($post['vareapconfcachemaxentries'])) {
		$input_errors[] = "The EAP-TLS Cache 'Max Entries' field must contain an integer value.";
	}

	// EAP-TLS OCSP Responder
	if (!empty($post['vareapconfocspurl'])) {
		if (!filter_var($post['vareapconfocspurl'], FILTER_VALIDATE_URL)) {
			$input_errors[] = "The 'OCSP Responder' field must contain a valid URL.";
		}
	}

	/*
	 * TODO:
	 * EAP-TLS - Country, State or Province, City, Organization, E-Mail Address, Common Name
	 */

}

/* SQL settings input validation */
function freeradius_validate_sql($post, &$input_errors) {

	// SQL Server Address
	if ($post['varsqlconfincludeenable'] == 'on') {
		if (empty($post['varsqlconfserver'])) {
			$input_errors[] = "The 'Server Address' field for SQL Server 1 must not be empty when 'SQL Support' for Server 1 is enabled.";
		} elseif (!is_ipaddr($post['varsqlconfserver']) && !is_hostname($post['varsqlconfserver'])) {
			$input_errors[] = "The 'Server Address' field for SQL Server 1 must contain a valid FQDN or IP address.";
		}
	}
	if ($post['varsqlconf2includeenable'] == 'on') {
		if (empty($post['varsqlconf2server'])) {
			$input_errors[] = "The 'Server Address' field for SQL Server 2 must not be empty when 'SQL Support' for Server 2 is enabled.";
		} elseif (!is_ipaddr($post['varsqlconf2server']) && !is_hostname($post['varsqlconf2server'])) {
			$input_errors[] = "The 'Server Address' field for SQL Server 2 must contain a valid FQDN or IP address.";
		}
	}

	// SQL Server Port
	if ($post['varsqlconfincludeenable'] == 'on') {
		if (empty($post['varsqlconfport'])) {
			$input_errors[] = "The 'Server Port' field for SQL Server 1 must not be empty when 'SQL Support' for Server 1 is enabled.";
		} elseif (!is_port($post['varsqlconfport'])) {
			$input_errors[] = "The 'Server Port' field for SQL Server 1 must contain a valid port.";
		}
	}
	if ($post['varsqlconf2includeenable'] == 'on') {
		if (empty($post['varsqlconf2port'])) {
			$input_errors[] = "The 'Server Port' field for SQL Server 2 must not be empty when 'SQL Support' for Server 1 is enabled.";
		} elseif (!is_port($post['varsqlconf2port'])) {
			$input_errors[] = "The 'Server Port' field for SQL Server 2 must contain a valid port.";
		}
	}

	// SQL Server Database Username
	if ($post['varsqlconfincludeenable'] == 'on') {
		if (empty($post['varsqlconflogin'])) {
			$input_errors[] = "The 'Database Username' field for SQL Server 1 must not be empty when 'SQL Support' for Server 1 is enabled.";
		}
		/* TODO: Username sanity checking */
	}
	if ($post['varsqlconf2includeenable'] == 'on') {
		if (empty($post['varsqlconf2login'])) {
			$input_errors[] = "The 'Database Username' field for SQL Server 2 must not be empty when 'SQL Support' for Server 1 is enabled.";
		}
		/* TODO: Username sanity checking */
	}

	// SQL Server Database Password
	if ($post['varsqlconfincludeenable'] == 'on') {
		if (empty($post['varsqlconfpassword'])) {
			$input_errors[] = "The 'Database Password' field for SQL Server 1 must not be empty when 'SQL Support' for Server 1 is enabled.";
		}
	}
	if ($post['varsqlconf2includeenable'] == 'on') {
		if (empty($post['varsqlconf2password'])) {
			$input_errors[] = "The 'Database Password' field for SQL Server 2 must not be empty when 'SQL Support' for Server 1 is enabled.";
		}
	}

	// SQL Server Database Table Configuration
	if ($post['varsqlconfincludeenable'] == 'on') {
		if (empty($post['varsqlconfradiusdb'])) {
			$input_errors[] = "The 'Database Table Configuration' field for SQL Server 1 must not be empty when 'SQL Support' for Server 1 is enabled.";
		}
		/* TODO: DB table name sanity checking */
	}
	if ($post['varsqlconf2includeenable'] == 'on') {
		if (empty($post['varsqlconf2radiusdb'])) {
			$input_errors[] = "The 'Database Table Configuration' field for SQL Server 2 must not be empty when 'SQL Support' for Server 1 is enabled.";
		}
		/* TODO: DB table name sanity checking */
	}

	// SQL Server Number of SQL Connections
	if ($post['varsqlconfnumsqlsocks'] != '' && !is_numericint($post['varsqlconfnumsqlsocks'])) {
		$input_errors[] = "The 'Number of SQL Connections' field for SQL Server 1 must contain an integer value.";
	}
	if ($post['varsqlconf2numsqlsocks'] != '' && !is_numericint($post['varsqlconf2numsqlsocks'])) {
		$input_errors[] = "The 'Number of SQL Connections' field for SQL Server 2 must contain an integer value.";
	}

	// SQL Server Failed Database Connection Delay
	if ($post['varsqlconfconnectfailureretrydelay'] != '' && !is_numericint($post['varsqlconfconnectfailureretrydelay'])) {
		$input_errors[] = "The 'Failed Database Connection Delay' field for SQL Server 1 must contain an integer value.";
	}
	if ($post['varsqlconf2connectfailureretrydelay'] != '' && !is_numericint($post['varsqlconf2connectfailureretrydelay'])) {
		$input_errors[] = "The 'Failed Database Connection Delay' field for SQL Server 2 must contain an integer value.";
	}

	// SQL Server SQL Socket Lifetime
	if ($post['varsqlconflifetime'] != '' && !is_numericint($post['varsqlconflifetime'])) {
		$input_errors[] = "The 'SQL Socket Lifetime' field for SQL Server 1 must contain an integer value.";
	}
	if ($post['varsqlconf2lifetime'] != '' && !is_numericint($post['varsqlconf2lifetime'])) {
		$input_errors[] = "The 'SQL Socket Lifetime' field for SQL Server 2 must contain an integer value.";
	}

	// SQL Server SQL Socket Maximum Queries
	if ($post['varsqlconfmaxqueries'] != '' && !is_numericint($post['varsqlconfmaxqueries'])) {
		$input_errors[] = "The 'SQL Socket Maximum Queries' field for SQL Server 1 must contain an integer value.";
	}
	if ($post['varsqlconf2maxqueries'] != '' && !is_numericint($post['varsqlconf2maxqueries'])) {
		$input_errors[] = "The 'SQL Socket Maximum Queries' field for SQL Server 2 must contain an integer value.";
	}

	/*
	 * TODO:
	 * SQL Database Configuration
	 * - Accounting Table 1 (Start), Accounting Table 2 (Stop), Post Auth Table, Auth Check Table, Auth Reply Table,
	 * - Group Check Table, Group Reply Table, User Group Table, RADIUS Client Table
	 */

}


/* LDAP settings input validation */
function freeradius_validate_ldap($post, &$input_errors) {
	global $config;

	// LDAP Server Address
	if ($post['varmodulesldapenableauthorize'] == 'on') {
		if (empty($post['varmodulesldapserver'])) {
			$input_errors[] = "The 'Server Address' field for LDAP Server 1 must not be empty when 'LDAP Authorization Support' for Server 1 is enabled.";
		} elseif (!is_ipaddr($post['varmodulesldapserver']) && !is_hostname($post['varmodulesldapserver'])) {
			$input_errors[] = "The 'Server Address' field for LDAP Server 1 must contain a valid FQDN or IP address.";
		}
	}
	if ($post['varmodulesldap2enableauthorize'] == 'on') {
		if (empty($post['varmodulesldap2server'])) {
			$input_errors[] = "The 'Server Address' field for LDAP Server 2 must not be empty when 'LDAP Authorization Support' for Server 2 is enabled.";
		} elseif (!is_ipaddr($post['varmodulesldap2server']) && !is_hostname($post['varmodulesldap2server'])) {
			$input_errors[] = "The 'Server Address' field for LDAP Server 2 must contain a valid FQDN or IP address.";
		}
	}

	// LDAP Server Port
	if ($post['varmodulesldapenableauthorize'] == 'on') {
		if (empty($post['varmodulesldapserverport'])) {
			$input_errors[] = "The 'Server Port' field for LDAP Server 1 must not be empty when 'LDAP Authorization Support' for Server 1 is enabled.";
		} elseif (!is_port($post['varmodulesldapserverport'])) {
			$input_errors[] = "The 'Server Port' field for LDAP Server 1 must contain a valid port.";
		}
	}
	if ($post['varmodulesldap2enableauthorize'] == 'on') {
		if (empty($post['varmodulesldap2serverport'])) {
			$input_errors[] = "The 'Server Port' field for LDAP Server 2 must not be empty when 'LDAP Authorization Support' for Server 2 is enabled.";
		} elseif (!is_port($post['varmodulesldap2serverport'])) {
			$input_errors[] = "The 'Server Port' field for LDAP Server 2 must contain a valid port.";
		}
	}

	// LDAP Server Identity
	if ($post['varmodulesldapenableauthorize'] == 'on') {
		if (empty($post['varmodulesldapidentity'])) {
			$input_errors[] = "The 'Identity' field for LDAP Server 1 must not be empty when 'LDAP Authorization Support' for Server 1 is enabled.";
		}
		/* TODO: Identity sanity checking */
	}
	if ($post['varmodulesldap2enableauthorize'] == 'on') {
		if (empty($post['varmodulesldap2identity'])) {
			$input_errors[] = "The 'Identity' field for LDAP Server 2 must not be empty when 'LDAP Authorization Support' for Server 2 is enabled.";
		}
		/* TODO: Identity sanity checking */
	}

	// LDAP Server Password
	if ($post['varmodulesldapenableauthorize'] == 'on') {
		if (empty($post['varmodulesldappassword'])) {
			$input_errors[] = "The 'Server Password' field for LDAP Server 1 must not be empty when 'LDAP Authorization Support' for Server 1 is enabled.";
		}
	}
	if ($post['varmodulesldap2enableauthorize'] == 'on') {
		if (empty($post['varmodulesldap2password'])) {
			$input_errors[] = "The 'Server Password' field for LDAP Server 2 must not be empty when 'LDAP Authorization Support' for Server 2 is enabled.";
		}
	}

	// LDAP Connections Number
	if ($post['varmodulesldapldapconnectionsnumber'] != '' && !is_numericint($post['varmodulesldapldapconnectionsnumber'])) {
		$input_errors[] = "The 'LDAP Connections Number' field for LDAP Server 1 must contain an integer value.";
	}
	if ($post['varmodulesldap2ldapconnectionsnumber'] != '' && !is_numericint($post['varmodulesldap2ldapconnectionsnumber'])) {
		$input_errors[] = "The 'LDAP Connections Number' field for LDAP Server 2 must contain an integer value.";
	}

	// LDAP Timeout
	if ($post['varmodulesldaptimeout'] != '' && !is_numericint($post['varmodulesldaptimeout'])) {
		$input_errors[] = "The 'Timeout' field for LDAP Server 1 must contain an integer value.";
	}
	if ($post['varmodulesldap2timeout'] != '' && !is_numericint($post['varmodulesldap2timeout'])) {
		$input_errors[] = "The 'Timeout' field for LDAP Server 2 must contain an integer value.";
	}

	// LDAP Time Limit
	if ($post['varmodulesldaptimelimit'] != '' && !is_numericint($post['varmodulesldaptimelimit'])) {
		$input_errors[] = "The 'Time Limit' field for LDAP Server 1 must contain an integer value.";
	}
	if ($post['varmodulesldap2timelimit'] != '' && !is_numericint($post['varmodulesldap2timelimit'])) {
		$input_errors[] = "The 'Time Limit' field for LDAP Server 2 must contain an integer value.";
	}

	// LDAP Network Timeout
	if ($post['varmodulesldapnettimeout'] != '' && !is_numericint($post['varmodulesldapnettimeout'])) {
		$input_errors[] = "The 'Network Timeout' field for LDAP Server 1 must contain an integer value.";
	}
	if ($post['varmodulesldap2nettimeout'] != '' && !is_numericint($post['varmodulesldap2nettimeout'])) {
		$input_errors[] = "The 'Network Timeout' field for LDAP Server 2 must contain an integer value.";
	}

	// LDAP KeepAlive Configuration
	if ($post['varmodulesldapkeepaliveidle'] != '' && !is_numericint($post['varmodulesldapkeepaliveidle'])) {
		$input_errors[] = "The 'LDAP OPT X KeepAlive Idle' field for LDAP Server 1 must contain an integer value.";
	}
	if ($post['varmodulesldap2keepaliveidle'] != '' && !is_numericint($post['varmodulesldap2keepaliveidle'])) {
		$input_errors[] = "The 'LDAP OPT X KeepAlive Idle' field for LDAP Server 2 must contain an integer value.";
	}
	if ($post['varmodulesldapkeepaliveprobes'] != '' && !is_numericint($post['varmodulesldapkeepaliveprobes'])) {
		$input_errors[] = "The 'LDAP OPT X KeepAlive Probes' field for LDAP Server 1 must contain an integer value.";
	}
	if ($post['varmodulesldap2keepaliveprobes'] != '' && !is_numericint($post['varmodulesldap2keepaliveprobes'])) {
		$input_errors[] = "The 'LDAP OPT X KeepAlive Probes' field for LDAP Server 2 must contain an integer value.";
	}
	if ($post['varmodulesldapkeepaliveinterval'] != '' && !is_numericint($post['varmodulesldapkeepaliveinterval'])) {
		$input_errors[] = "The 'LDAP OPT X KeepAlive Interval' field for LDAP Server 1 must contain an integer value.";
	}
	if ($post['varmodulesldap2keepaliveinterval'] != '' && !is_numericint($post['varmodulesldap2keepaliveinterval'])) {
		$input_errors[] = "The 'LDAP OPT X KeepAlive Interval' field for LDAP Server 2 must contain an integer value.";
	}

	/*
	 * TODO:
	 * General Configuration - Base DN, Filter, Base Filter
	 * Miscellaneous Configuration - Default Profile, Profile Attribute, Access Attribute
	 * Group Membership Options - Groupname Attribute, Group Membership Filter, Group Membership Attribute
	 */

}

/* Simple dummy copy to use old 2.4.x style array. */
if (!function_exists('cert_build_list')) {
	function cert_build_list($type = 'cert', $consumer = '', $selectsource = false, $addnone = false) {
		global $config;
		$list = ($type == 'ca') ? $config['ca'] : $config['cert'];
		array_shift($list, array('refid' => 'none', 'descr' => 'None'));
		return $list;
	}
}

?>
