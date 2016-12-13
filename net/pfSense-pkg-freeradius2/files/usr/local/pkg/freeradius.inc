<?php
/*
 * freeradius.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Rubicon Communications, LLC (Netgate)
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
require_once("functions.inc");
require_once("globals.inc");
require_once("notices.inc");
require_once("pkg-utils.inc");
require_once("services.inc");
require_once("service-utils.inc");
require_once("util.inc");
require_once("xmlrpc.inc");
require_once("xmlrpc_client.inc");

define('FREERADIUS_BASE', '/usr/local');
$bash_path = FREERADIUS_BASE . "/bin/bash";
define('FREERADIUS_LIB', FREERADIUS_BASE . '/lib');
define('FREERADIUS_ETC', FREERADIUS_BASE . '/etc');

// Check freeradius lib version
	$frlib="";
	if (file_exists(FREERADIUS_LIB)) {
		$libfiles = scandir(FREERADIUS_LIB);
		foreach ($libfiles as $libfile){
			if (preg_match("/freeradius-/",$libfile))
				$frlib=FREERADIUS_LIB . '/' . $libfile;
		}
	}
	if ($frlib == ""){
		log_error("freeRADIUS - No freeradius lib found on ".FREERADIUS_LIB);
	}
	
function freeradius_deinstall_command() {
	$pidFile = "/var/run/radiusd.pid";
	$i = 0;

	while (isvalidpid($pidFile) && $i < 3) {
		$sig = ($i == 2 ? SIGKILL : SIGTERM);
		sigkillbypid($pidFile, $sig);
		sleep(1);
		$i++;
	}
	return;
}

function freeradius_install_command() {
	global $config, $frlib;

	// We create here different folders for different counters.
	@mkdir("/var/log/radacct/datacounter/daily", 0755, true);
	@mkdir("/var/log/radacct/datacounter/weekly", 0755, true);
	@mkdir("/var/log/radacct/datacounter/monthly", 0755, true);
	@mkdir("/var/log/radacct/datacounter/forever", 0755, true);
	@mkdir("/var/log/radacct/timecounter", 0755, true);
	@mkdir(FREERADIUS_ETC . "/raddb/scripts", 0755, true);

	unlink_if_exists("/usr/local/etc/raddb");
	@symlink(FREERADIUS_ETC . "/raddb", "/usr/local/etc/raddb");
	if (!file_exists("/var/log/radutmp")) { exec("touch /var/log/radutmp");	}
	if (!file_exists("/var/log/radwtmp")) {	exec("touch /var/log/radwtmp");	}
	exec("chown -R root:wheel " . FREERADIUS_ETC . "/raddb /var/log/radacct");
	if (file_exists($frlib)) {
		exec("chown -R root:wheel {$frlib}");
	}

	// creating a backup file of the original policy.conf no matter if user checked this or not
	if (!file_exists(FREERADIUS_ETC . "/raddb/policy.conf.backup")) {
		log_error("FreeRADIUS: Creating backup of the original file to " . FREERADIUS_ETC . "/raddb/policy.conf.backup");
		copy(FREERADIUS_ETC . "/raddb/policy.conf", FREERADIUS_ETC . "/raddb/policy.conf.backup");
	}

	// creating a backup file of the original /modules/files no matter if user checked this or not
	if (!file_exists(FREERADIUS_ETC . "/raddb/files.backup")) {
		log_error("FreeRADIUS: Creating backup of the original file to " . FREERADIUS_ETC . "/raddb/files.backup");
		copy(FREERADIUS_ETC . "/raddb/modules/files", FREERADIUS_ETC . "/raddb/files.backup");
	}
	
	// Install Google Authenticator scripts
	if (!file_exists(FREERADIUS_ETC . "/raddb/scripts/googleauth.py")) { 
		copy(FREERADIUS_BASE . "/pkg/googleauth.py", FREERADIUS_ETC . "/raddb/scripts/"); 
		chmod(REERADIUS_ETC . "/raddb/scripts/googleauth.py",0755);
	}
	if (!file_exists(FREERADIUS_ETC . "/raddb/modules/googleauth")) { copy(FREERADIUS_BASE . "/pkg/googleauth", FREERADIUS_ETC . "/raddb/modules/");}
	  
	// Disable virtual-server we do not need by default
	if (file_exists(FREERADIUS_ETC . "/raddb/sites-enabled/control-socket")) { unlink(FREERADIUS_ETC . "/raddb/sites-enabled/control-socket"); }
	if (file_exists(FREERADIUS_ETC . "/raddb/sites-enabled/inner-tunnel")) { unlink(FREERADIUS_ETC . "/raddb/sites-enabled/inner-tunnel"); }

	// We run this here just to suppress some warnings on syslog if file doesn't exist
	freeradius_authorizedmacs_resync();

	// These two functions create the module and the dictionary entry for Mobile-One-Time-Password
	freeradius_dictionary_resync();
	freeradius_modulesmotp_resync();

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
	freeradius_eapconf_resync();
	freeradius_clients_resync();
	freeradius_modulesldap_resync();

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
	start_service("radiusd");
}

function freeradius_settings_resync() {
	global $config;
	$conf = '';

	// put the constant to a variable
	$varFREERADIUS_BASE = FREERADIUS_BASE;

	// We do some checks of some folders which will be deleted after reboot on nanobsd systems
	if (!file_exists("/var/log/radacct/")) { exec("mkdir /var/log/radacct"); }
	if (!file_exists("/var/log/radacct/datacounter/")) { exec("mkdir /var/log/radacct/datacounter && mkdir /var/log/radacct/datacounter/daily && mkdir /var/log/radacct/datacounter/weekly && mkdir /var/log/radacct/datacounter/monthly && mkdir /var/log/radacct/datacounter/forever"); }
	if (!file_exists("/var/log/radacct/timecounter/")) { exec("mkdir /var/log/radacct/timecounter"); }
	if (!file_exists("/var/log/radutmp")) { exec("touch /var/log/radutmp");	}
	if (!file_exists("/var/log/radwtmp")) {	exec("touch /var/log/radwtmp");	}
	if (!file_exists("/var/log/radacct/")) { exec("chown -R root:wheel /var/log/radacct"); }

	$varsettings = $config['installedpackages']['freeradiussettings']['config'][0];
	
	// Variables: General configuration
	$varsettingsmaxrequests = ($varsettings['varsettingsmaxrequests']?$varsettings['varsettingsmaxrequests']:'1024');
	$varsettingsmaxrequesttime = ($varsettings['varsettingsmaxrequesttime']?$varsettings['varsettingsmaxrequesttime']:'30');
	$varsettingscleanupdelay = ($varsettings['varsettingscleanupdelay']?$varsettings['varsettingscleanupdelay']:'5');
	$varsettingshostnamelookups = ($varsettings['varsettingshostnamelookups']?$varsettings['varsettingshostnamelookups']:'no');
	$varsettingsallowcoredumps = ($varsettings['varsettingsallowcoredumps']?$varsettings['varsettingsallowcoredumps']:'no');
	$varsettingsregularexpressions = ($varsettings['varsettingsregularexpressions']?$varsettings['varsettingsregularexpressions']:'yes');
	$varsettingsextendedexpressions = ($varsettings['varsettingsextendedexpressions']?$varsettings['varsettingsextendedexpressions']:'yes');
	
	// Variables: Logging options
	$varsettingslogdir = ($varsettings['varsettingslogdir']?$varsettings['varsettingslogdir']:'syslog');
	$varsettingsauth = ($varsettings['varsettingsauth']?$varsettings['varsettingsauth']:'yes');
	$varsettingsauthbadpass = ($varsettings['varsettingsauthbadpass']?$varsettings['varsettingsauthbadpass']:'no');
	$varsettingsauthbadpassmessage = ($varsettings['varsettingsauthbadpassmessage']?$varsettings['varsettingsauthbadpassmessage']:'');
	$varsettingsauthgoodpass = ($varsettings['varsettingsauthgoodpass']?$varsettings['varsettingsauthgoodpass']:'no');
	$varsettingsauthgoodpassmessage = ($varsettings['varsettingsauthgoodpassmessage']?$varsettings['varsettingsauthgoodpassmessage']:'');
	$varsettingsstrippednames = ($varsettings['varsettingsstrippednames']?$varsettings['varsettingsstrippednames']:'no');
	
	// Variables: Security
	$varsettingsmaxattributes = ($varsettings['varsettingsmaxattributes']?$varsettings['varsettingsmaxattributes']:'200');
	$varsettingsrejectdelay = ($varsettings['varsettingsrejectdelay']?$varsettings['varsettingsrejectdelay']:'1');
	
	// Variables: Thread Pool
	$varsettingsstartservers = ($varsettings['varsettingsstartservers']?$varsettings['varsettingsstartservers']:'5');
	$varsettingsmaxservers = ($varsettings['varsettingsmaxservers']?$varsettings['varsettingsmaxservers']:'32');
	$varsettingsminspareservers = ($varsettings['varsettingsminspareservers']?$varsettings['varsettingsminspareservers']:'3');
	$varsettingsmaxspareservers = ($varsettings['varsettingsmaxspareservers']?$varsettings['varsettingsmaxspareservers']:'10');
	$varsettingsmaxqueuesize = ($varsettings['varsettingsmaxqueuesize']?$varsettings['varsettingsmaxqueuesize']:'65536');
	$varsettingsmaxrequestsperserver = ($varsettings['varsettingsmaxrequestsperserver']?$varsettings['varsettingsmaxrequestsperserver']:'0');
	
	// For more details look at "freeradius_sqlconf_resync"
	$sqlconf = $config['installedpackages']['freeradiussqlconf']['config'][0];
		
	// Dis-/Enable SQL in "instatiate" section in "freeradius_settings_resync" and radiusd.conf SQL SERVER 2
	if ($sqlconf['varsqlconf2includeenable'] == 'on') {
		$varsqlconf2instantiate = 'sql2';
	}
	else {
		$varsqlconf2instantiate = '### sql2 DISABLED ###';
	}
	
	$varsqlconf2failover = ($varsettings['varsqlconf2failover']?$varsettings['varsqlconf2failover']:'redundant');
	
	// Dis-/Enable SQL in "instatiate" section in "freeradius_settings_resync" and radiusd.conf SQL SERVER 1
	if ($sqlconf['varsqlconfincludeenable'] == 'on') {
		$varsqlconfinclude = '$INCLUDE sql.conf';
		$varsqlconfincludecounter = '$INCLUDE sql/mysql/counter.conf';
		$varsqlconfinstantiate = "$varsqlconf2failover {" . "\n\t\tsql" . "\n\t\t$varsqlconf2instantiate" . "\n\t}";
	}
	else {
		$varsqlconfinclude = '#$INCLUDE sql.conf';
		$varsqlconfincludecounter = '#$INCLUDE sql/mysql/counter.conf';
		$varsqlconf2failover = '';
		$varsqlconfinstantiate = '#sql';
	}


	$conf .= <<<EOD
prefix = $varFREERADIUS_BASE
exec_prefix = \${prefix}
sysconfdir = \${prefix}/etc
localstatedir = /var
sbindir = \${exec_prefix}/sbin
logdir = \${localstatedir}/log
raddbdir = \${sysconfdir}/raddb
radacctdir = \${logdir}/radacct
confdir = \${raddbdir}
run_dir = \${localstatedir}/run
libdir = {$frlib}
pidfile = \${run_dir}/radiusd.pid
db_dir = \${raddbdir}
name = radiusd
#chroot = /path/to/chroot/directory
#user = freeradius
#group = freeradius

###############################################################################
### Is not present in freeradius 2.x radiusd.conf anymore but it was in 1.x ###
### delete_blocked_requests = no                                            ###
### usercollide = no                                                        ###
### lower_user = no                                                         ###
### lower_pass = no                                                         ###
### nospace_user = no                                                       ###
### nospace_pass = no                                                       ###
###############################################################################

max_request_time = $varsettingsmaxrequesttime
cleanup_delay = $varsettingscleanupdelay
max_requests = $varsettingsmaxrequests
hostname_lookups = $varsettingshostnamelookups
allow_core_dumps = $varsettingsallowcoredumps
regular_expressions = $varsettingsregularexpressions
extended_expressions = $varsettingsextendedexpressions

EOD;

// Deletes virtual-server coa by default. Will be re-enabled if there is an interface-type "coa"
exec("rm -f " . FREERADIUS_ETC . "/raddb/sites-enabled/coa");

$arrinterfaces = $config['installedpackages']['freeradiusinterfaces']['config'];
	if (is_array($arrinterfaces)  && !empty($arrinterfaces)) {
		foreach ($arrinterfaces as $item) {
			$varinterfaceip = $item['varinterfaceip'];
			$varinterfaceport = $item['varinterfaceport'];
			$varinterfacetype = $item['varinterfacetype'];
			$varinterfaceipversion = $item['varinterfaceipversion'];

			// Begin "if" for interface-type = detail
			if ($item['varinterfacetype'] == 'detail') {
			$conf .= <<<EOD
listen {
		type = $varinterfacetype
		$varinterfaceipversion = $varinterfaceip
		port = $varinterfaceport
		filename = \${radacctdir}/detail-%Y%m%d:%H
		load_factor = 10
}

EOD;
			}	// End "if" for interface-type = detail

			// Begin "if" for interface-type = coa
			if ($item['varinterfacetype'] == 'coa') {
			// Enables virtual-server coa because interface-type is coa
			exec("ln -s " . FREERADIUS_ETC . "/raddb/sites-available/coa " . FREERADIUS_ETC . "/raddb/sites-enabled/");
			$conf .= <<<EOD
listen {
		type = $varinterfacetype
		$varinterfaceipversion = $varinterfaceip
		port = $varinterfaceport
		server = coa
}

EOD;
			}	// End "if" for interface-type = coa

			// Begin "if" for interface-type = auth, acct, proxy, status
			if (($item['varinterfacetype'] == 'auth') || ($item['varinterfacetype'] == 'acct') || ($item['varinterfacetype'] == 'proxy') || ($item['varinterfacetype'] == 'status')) {
			$conf .= <<<EOD
listen {
		type = $varinterfacetype
		$varinterfaceipversion = $varinterfaceip
		port = $varinterfaceport
}

EOD;
			}	// End "if" for interface-type = auth, acct, proxy, status
		}	// end foreach
	}	// end if array


$conf .= <<<EOD

log {
	destination = $varsettingslogdir
	file = \${logdir}/radius.log
	syslog_facility = daemon
	stripped_names = $varsettingsstrippednames
	auth = $varsettingsauth
	auth_badpass = $varsettingsauthbadpass
	auth_goodpass = $varsettingsauthgoodpass
	msg_goodpass = "$varsettingsauthgoodpassmessage"
	msg_badpass = "$varsettingsauthbadpassmessage"
}

checkrad = \${sbindir}/checkrad
security {
	max_attributes = $varsettingsmaxattributes
	reject_delay = $varsettingsrejectdelay
	status_server = no
}

### disbale proxy module. In most environments we do not need to proxy requests to another RADIUS PROXY server
#proxy_requests = yes
#\$INCLUDE  proxy.conf
\$INCLUDE  clients.conf
thread pool {
	start_servers = $varsettingsstartservers
	max_servers = $varsettingsmaxservers
	min_spare_servers = $varsettingsminspareservers
	max_spare_servers = $varsettingsmaxspareservers
	max_queue_size = $varsettingsmaxqueuesize
	max_requests_per_server = $varsettingsmaxrequestsperserver
}

modules {
	\$INCLUDE \${confdir}/modules/
	\$INCLUDE eap.conf
	### Dis-/Enable sql.conf INCLUDE
	$varsqlconfinclude
	
	### Dis-/Enable sql/mysql/counter.conf INCLUDE
	$varsqlconfincludecounter
		
	#\$INCLUDE sqlippool.conf
}

instantiate {

	exec
	expr
	daily
	weekly
	monthly
	forever
	expiration
	logintime
	### Dis-/Enable sql instatiate
	$varsqlconfinstantiate
}
\$INCLUDE policy.conf
\$INCLUDE sites-enabled/
EOD;

	conf_mount_rw();
	file_put_contents(FREERADIUS_ETC . '/raddb/radiusd.conf', $conf);
	conf_mount_ro();
	
	// "freeradius_sqlconf_resync" is pointing to this function because we need to run "freeradius_serverdefault_resync" and after that restart freeradius.
	freeradius_plainmacauth_resync();
	freeradius_motp_resync();
	freeradius_serverdefault_resync();
	
	// This is to fix the mysqlclient.so which gets lost after reboot
	exec("ldconfig -m /usr/local/lib/mysql");
	// Change owner of freeradius created files
	exec("chown -R root:wheel /var/log");
	restart_service("radiusd");
}

function freeradius_users_resync() {
global $config;

$conf = '';

$arrusers = $config['installedpackages']['freeradius']['config'];

if (is_array($arrusers) && !empty($arrusers)) {
	foreach ($arrusers as $users) {
	
	// Variables for users file defined parameters
	
	$varusersusername = $users['varusersusername'];
	$varuserspassword = $users['varuserspassword'];
	

	// Check password encryption
	$varuserspasswordencryption = ($users['varuserspasswordencryption']?$users['varuserspasswordencryption']:'Cleartext-Password');
		switch ($varuserspasswordencryption) {
			case "MD5-Password":
				$varuserspassword = md5($varuserspassword);
				break;
			default:
				$varuserspassword = $users['varuserspassword'];
		}
	
	
	$varusersauthmethod = $users['varusersauthmethod'];  	
	$varusersmotpinitsecret = $users['varusersmotpinitsecret'];
	$varusersmotppin = $users['varusersmotppin'];
	$varusersmotpoffset = ($users['varusersmotpoffset']?$users['varusersmotpoffset']:'0');
	
	$varuserssimultaneousconnect = ($users['varuserssimultaneousconnect']?$users['varuserssimultaneousconnect']:'');
	$varuserswisprredirectionurl = $users['varuserswisprredirectionurl'];
	$varusersframedipaddress = $users['varusersframedipaddress'];
	$varusersframedipnetmask = $users['varusersframedipnetmask'];
	$varusersframedroute = $users['varusersframedroute'];
	$varusersexpiration = $users['varusersexpiration'];
	$varuserssessiontimeout = $users['varuserssessiontimeout'];
	$varuserslogintime = $users['varuserslogintime'];
	$varusersvlanid = $users['varusersvlanid'];
	
	// GUI uses minutes but RADIUS needs seconds so we do a multiplication
	$varusersamountoftime = ($users['varusersamountoftime']?$users['varusersamountoftime']:'');
	$varusersamountoftime = $varusersamountoftime * 60;
	$varuserspointoftime = $users['varuserspointoftime'];
	
	// GUI uses MB but RADIUS needs Bytes so we do a multiplication
	$varusersmaxtotaloctets = ($users['varusersmaxtotaloctets']?$users['varusersmaxtotaloctets']:'');
	$varusersmaxtotaloctets = $varusersmaxtotaloctets * 1024 * 1024;
	$varusersmaxtotaloctetstimerange = $users['varusersmaxtotaloctetstimerange'];
	
	// GUI uses KiloBit but RADIUS needs Bits so we do a multiplication
	$varusersmaxbandwidthup = ($users['varusersmaxbandwidthup']?$users['varusersmaxbandwidthup']:'');
	$varusersmaxbandwidthup = $varusersmaxbandwidthup * 1024;
	$varusersmaxbandwidthdown = ($users['varusersmaxbandwidthdown']?$users['varusersmaxbandwidthdown']:'');
	$varusersmaxbandwidthdown = $varusersmaxbandwidthdown * 1024;
	
	// Accounting-Interim-Interval - Must not be smaller than 60 and should be bigger than 600s
	if (($users['varusersacctinteriminterval'] >= '0') && ($users['varusersacctinteriminterval'] < '60')) {
		$varusersacctinteriminterval = 60;
	} else {
		$varusersacctinteriminterval = $users['varusersacctinteriminterval'];
	}
	
	// Clear variables for next user foreach additional options TOP
	$varuserstopadditionaloptions = '';
	$varusersadditionaloptionstop = '';
	
	if(!empty($users['varuserstopadditionaloptions'])) {
		$varuserstopadditionaloptions = explode("|", ($users['varuserstopadditionaloptions']));
		foreach ($varuserstopadditionaloptions as $toptmp) {
			$varusersadditionaloptionstop .= $toptmp . "\n";
		}
	}
	
	// Clear variables for next user foreach additional options: CHECK-ITEMS
	$varuserscheckitemsadditionaloptions = '';
	$varusersadditionaloptionscheckitems = '';
	
	if(!empty($users['varuserscheckitemsadditionaloptions'])) {
		$varuserscheckitemsadditionaloptions = explode("|", ($users['varuserscheckitemsadditionaloptions']));
		$varusersadditionaloptionscheckitems .= '';
		foreach ($varuserscheckitemsadditionaloptions as $checkitemtmp) {
			$varusersadditionaloptionscheckitems .= "$checkitemtmp" . " ";
		}
	}
		
	// Clear variables for next user foreach additional options: REPLY-ITEMS
	$varusersreplyitemsadditionaloptions = '';
	$varusersadditionaloptionsreplyitems = '';
	
	if(!empty($users['varusersreplyitemsadditionaloptions'])) {
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
			}
			else {
				// Add the user attributes to each user.
				$varuserscheckitem = '"' . $varusersusername . '"' . " $varuserspasswordencryption := " . '"' . $varuserspassword .'"';
			}
		} // end of check if otp is enabled
	
		// if otp is enabled we need to set Auth-Type to accept because password will be checked when the otp script gets executed in reply-item list
		else {
			$varuserscheckitem = '"' . $varusersusername . '"' . " Auth-Type = motp";
		}
	
	// Add additional CHECK-ITEMS here. Different formatting in "users" file needed.
	if ($varuserssimultaneousconnect != '') {
	$varuserscheckitem .= ", Simultaneous-Use := " . '"' . $varuserssimultaneousconnect . '"';
	}
	if ($varusersexpiration != '') {
	$varuserscheckitem .= ", Expiration := " . '"' . $varusersexpiration . '"';
	}
	if ($varuserslogintime != '') {
	$varuserscheckitem .= ", Login-Time := " . '"' . $varuserslogintime . '"';
	}	
	if ($varusersamountoftime != '') {
	$varuserscheckitem .= ", Max-" . "$varuserspointoftime" . "-Session := " . "$varusersamountoftime";
	}
	if ($varusersadditionaloptionscheckitems != '') {
	$varuserscheckitem .= ", $varusersadditionaloptionscheckitems";
	}

		// this is the part for mobile otp
		if ($users['varusersmotpenable'] == 'on') {
			$varusersreplyitem .= "MOTP-Init-Secret = $varusersmotpinitsecret," . "\n\tMOTP-PIN = $varusersmotppin," . "\n\tMOTP-Offset = $varusersmotpoffset";
		}
		else {
			$varusersreplyitem .= '';
		}
		
	// Add additional REPLY-ITEMS here. Different formatting in "users" file needed.
	if ($varusersframedipaddress != '') {
		if ($varusersreplyitem != '') { $varusersreplyitem .=","; }
		$varusersreplyitem .= "\n\tFramed-IP-Address = $varusersframedipaddress";
	}
	if ($varusersframedipnetmask != '') {
		if ($varusersreplyitem != '') { $varusersreplyitem .=","; }
		$varusersreplyitem .= "\n\tFramed-IP-Netmask = $varusersframedipnetmask";
	}
	if ($varusersframedroute != '') {
		if ($varusersreplyitem != '') { $varusersreplyitem .=","; }
		$varusersreplyitem .= "\n\tFramed-Route = " . '"' . $varusersframedroute . '"';
	}
	if ($varuserssessiontimeout != '') {
		if ($varusersreplyitem != '') { $varusersreplyitem .=","; }
		$varusersreplyitem .= "\n\tSession-Timeout := $varuserssessiontimeout";
	}
	if ($varusersvlanid != '') {
		if ($varusersreplyitem != '') { $varusersreplyitem .=","; }
		$varusersreplyitem .= "\n\tTunnel-Type = VLAN,\n\tTunnel-Medium-Type = IEEE-802,\n\tTunnel-Private-Group-ID = " . '"' . $varusersvlanid . '"';
	}
	if ($varusersmaxbandwidthup != '') {
		if ($varusersreplyitem != '') { $varusersreplyitem .=","; }
		$varusersreplyitem .= "\n\tWISPr-Bandwidth-Max-Up := $varusersmaxbandwidthup";
	}
	if ($varusersmaxbandwidthdown != '') {
		if ($varusersreplyitem != '') { $varusersreplyitem .=","; }
		$varusersreplyitem .= "\n\tWISPr-Bandwidth-Max-Down := $varusersmaxbandwidthdown";
	}
	if ($varusersacctinteriminterval != '') {
		if ($varusersreplyitem != '') { $varusersreplyitem .=","; }
		$varusersreplyitem .= "\n\tAcct-Interim-Interval := $varusersacctinteriminterval";
	}
	if ($varuserswisprredirectionurl != '') {
		if ($varusersreplyitem != '') { $varusersreplyitem .=","; }
		$varusersreplyitem .= "\n\tWISPr-Redirection-URL := $varuserswisprredirectionurl";
	}
	// If an octet limit is set we create the files for the limit and the counter. Further we call an exec script which checks if the limit is reached or not
	if ($varusersmaxtotaloctets != '') {
		if ($varusersreplyitem != '') { $varusersreplyitem .=","; }
		//create exec script
		$varusersreplyitem .= "\n\tExec-Program-Wait = " . '"/bin/sh ' . FREERADIUS_ETC . '/raddb/scripts/datacounter_auth.sh ' . "$varusersusername $varusersmaxtotaloctetstimerange" . '"';
		// create limit file - will be always overwritten so we can increase limit from GUI
		exec("`echo $varusersmaxtotaloctets > /var/log/radacct/datacounter/$varusersmaxtotaloctetstimerange/max-octets-$varusersusername`");
		// if used-octets file exist we do NOT overwrite this file!!!
		if (!file_exists("/var/log/radacct/datacounter/$varusersmaxtotaloctetstimerange/used-octets-$varusersusername")) { exec("echo 0 > /var/log/radacct/datacounter/$varusersmaxtotaloctetstimerange/used-octets-$varusersusername"); }
	}
	// If an octet limit is NOT set we delete the files for the limit and the counter.
	else {
		if (file_exists("/var/log/radacct/datacounter/$varusersmaxtotaloctetstimerange/max-octets-$varusersusername")) { unlink("/var/log/radacct/datacounter/$varusersmaxtotaloctetstimerange/max-octets-$varusersusername"); }
		if (file_exists("/var/log/radacct/datacounter/$varusersmaxtotaloctetstimerange/used-octets-$varusersusername")) { unlink("/var/log/radacct/datacounter/$varusersmaxtotaloctetstimerange/used-octets-$varusersusername*"); }
	}
	if ($varusersadditionaloptionsreplyitems != '') {
		if ($varusersreplyitem != '') { $varusersreplyitem .=","; }
		$varusersreplyitem .= "\n\t$varusersadditionaloptionsreplyitems";
	}
	
	// Cosmetic fix - This is just to make a blank new line after each user entry
	$varusersreplyitem .= "\n\n";
	
	
	$conf .= <<<EOD
$varusersadditionaloptionstop
$varuserscheckitem
	$varusersreplyitem
EOD;
	} //end foreach
} // end if

	$filename = FREERADIUS_ETC . '/raddb/users';
	conf_mount_rw();
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
	conf_mount_ro();
	
	freeradius_sync_on_changes();
	restart_service('radiusd');
}


function freeradius_authorizedmacs_resync() {
global $config;

$conf = '';
$arrmacs = $config['installedpackages']['freeradiusauthorizedmacs']['config'];

if (is_array($arrmacs) && !empty($arrmacs)) {
	foreach ($arrmacs as $macs) {
	
	// Variables for authorized_macs file defined parameters
	$varmacsaddress = $macs['varmacsaddress'];
	// We don't need a password but we need this field to make syntac correct for CHECK-ITEMS
	$varmacspassword = $macs['varmacsaddress'];

	$varmacssimultaneousconnect = ($macs['varmacssimultaneousconnect']?$macs['varmacssimultaneousconnect']:'');
	$varmacsswisprredirectionurl = $macs['varmacsswisprredirectionurl'];
	$varmacsframedipaddress = $macs['varmacsframedipaddress'];
	$varmacsframedipnetmask = $macs['varmacsframedipnetmask'];
	$varmacsframedroute = $macs['varmacsframedroute'];
	$varmacsexpiration = $macs['varmacsexpiration'];
	$varmacssessiontimeout = $macs['varmacssessiontimeout'];
	$varmacslogintime = $macs['varmacslogintime'];
	$varmacsvlanid = $macs['varmacsvlanid'];
	
	// GUI uses minutes but RADIUS needs seconds so we do a multiplication
	$varmacsamountoftime = ($macs['varmacsamountoftime']?$macs['varmacsamountoftime']:'');
	$varmacsamountoftime = $varmacsamountoftime * 60;
	$varmacspointoftime = $macs['varmacspointoftime'];
	
	// GUI uses MB but RADIUS needs Bytes so we do a multiplication
	$varmacsmaxtotaloctets = ($macs['varmacsmaxtotaloctets']?$macs['varmacsmaxtotaloctets']:'');
	$varmacsmaxtotaloctets = $varmacsmaxtotaloctets * 1024 * 1024;
	$varmacsmaxtotaloctetstimerange = $macs['varmacsmaxtotaloctetstimerange'];
	
	// GUI uses KiloBit but RADIUS needs Bits so we do a multiplication
	$varmacsmaxbandwidthup = ($macs['varmacsmaxbandwidthup']?$macs['varmacsmaxbandwidthup']:'');
	$varmacsmaxbandwidthup = $varmacsmaxbandwidthup * 1024;
	$varmacsmaxbandwidthdown = ($macs['varmacsmaxbandwidthdown']?$macs['varmacsmaxbandwidthdown']:'');
	$varmacsmaxbandwidthdown = $varmacsmaxbandwidthdown * 1024;
	
		
	// Accounting-Interim-Interval
	if (($users['varmacsacctinteriminterval'] >= '0') && ($users['varmacsacctinteriminterval'] < '60')) {
		$varmacsacctinteriminterval = 60;
	} else {
		$varmacsacctinteriminterval = $users['varmacsacctinteriminterval'];
	}
	
	
	// Clear variables for next mac foreach additional options TOP
	$varmacstopadditionaloptions = '';
	$varmacsadditionaloptionstop = '';
	
	if(!empty($macs['varmacstopadditionaloptions'])) {
		$varmacstopadditionaloptions = explode("|", ($macs['varmacstopadditionaloptions']));
		foreach ($varmacstopadditionaloptions as $toptmp) {
			$varmacsadditionaloptionstop .= $toptmp . "\n";
		}
	}
	
	// Clear variables for next mac foreach additional options: CHECK-ITEMS
	$varmacscheckitemsadditionaloptions = '';
	$varmacsadditionaloptionscheckitems = '';
	
	if(!empty($macs['varmacscheckitemsadditionaloptions'])) {
		$varmacscheckitemsadditionaloptions = explode("|", ($macs['varmacscheckitemsadditionaloptions']));
		$varmacsadditionaloptionscheckitems .= '';
		foreach ($varmacscheckitemsadditionaloptions as $checkitemtmp) {
			$varmacsadditionaloptionscheckitems .= "$checkitemtmp" . " ";
		}
	}
		
	// Clear variables for next mac foreach additional options: REPLY-ITEMS
	$varmacsreplyitemsadditionaloptions = '';
	$varmacsadditionaloptionsreplyitems = '';
	
	if(!empty($macs['varmacsreplyitemsadditionaloptions'])) {
		$varmacsreplyitemsadditionaloptions = explode("|", ($macs['varmacsreplyitemsadditionaloptions']));
		$varmacsadditionaloptionsreplyitems .= '';
		foreach ($varmacsreplyitemsadditionaloptions as $replyitemtmp) {
			$varmacsadditionaloptionsreplyitems .= $replyitemtmp . "\n\t";
		}
	}

	// Empty variable
	$varmacscheckitem = '';
	$varmacsreplyitem = '';
	
	// If someone does not want to use MAC address but entry "DEFAULT" instead then we can disable this
	if ($macs['varmacsaddress'] == '') {
			$varmacscheckitem = '';
	}
	else {
		// Add the user attributes to each user.
		$varmacscheckitem = "$varmacsaddress" . " Cleartext-Password := " . '"' . $varmacspassword . '"';
	}
	
	// Add additional CHECK-ITEMS here. Different formatting in "authorized_macs" file needed.
	if ($varmacssimultaneousconnect != '') {
	$varmacscheckitem .= ", Simultaneous-Use := " . '"' . $varmacssimultaneousconnect . '"';
	}
	if ($varmacsexpiration != '') {
	$varmacscheckitem .= ", Expiration := " . '"' . $varmacsexpiration . '"';
	}
	if ($varmacslogintime != '') {
	$varmacscheckitem .= ", Login-Time := " . '"' . $varmacslogintime . '"';
	}	
	if ($varmacsamountoftime != '') {
	$varmacscheckitem .= ", Max-" . "$varmacspointoftime" . "-Session := " . "$varmacsamountoftime";
	}
	if ($varmacsadditionaloptionscheckitems != '') {
	$varmacscheckitem .= ", $varmacsadditionaloptionscheckitems";
	}

	// Add additional REPLY-ITEMS here. Different formatting in "authorized_macs" file needed.
	if ($varmacsframedipaddress != '') {
		if ($varmacsreplyitem != '') { $varmacsreplyitem .=","; }
		$varmacsreplyitem .= "\n\tFramed-IP-Address = $varmacsframedipaddress";
	}
	if ($varmacsframedipnetmask != '') {
		if ($varmacsreplyitem != '') { $varmacsreplyitem .=","; }
		$varmacsreplyitem .= "\n\tFramed-IP-Netmask = $varmacsframedipnetmask";
	}
	if ($varmacsframedroute != '') {
		if ($varmacsreplyitem != '') { $varmacsreplyitem .=","; }
		$varmacsreplyitem .= "\n\tFramed-Route = " . '"' . $varmacsframedroute . '"';
	}
	if ($varmacssessiontimeout != '') {
		if ($varmacsreplyitem != '') { $varmacsreplyitem .=","; }
		$varmacsreplyitem .= "\n\tSession-Timeout := $varmacssessiontimeout";
	}
	if ($varmacsvlanid != '') {
		if ($varmacsreplyitem != '') { $varmacsreplyitem .=","; }
		$varmacsreplyitem .= "\n\tTunnel-Type = VLAN,\n\tTunnel-Medium-Type = IEEE-802,\n\tTunnel-Private-Group-ID = " . '"' . $varmacsvlanid . '"';
	}
	if ($varmacsmaxbandwidthup != '') {
		if ($varmacsreplyitem != '') { $varmacsreplyitem .=","; }
		$varmacsreplyitem .= "\n\tWISPr-Bandwidth-Max-Up := $varmacsmaxbandwidthup";
	}
	if ($varmacsmaxbandwidthdown != '') {
		if ($varmacsreplyitem != '') { $varmacsreplyitem .=","; }
		$varmacsreplyitem .= "\n\tWISPr-Bandwidth-Max-Down := $varmacsmaxbandwidthdown";
	}
	if ($varmacsacctinteriminterval != '') {
		if ($varmacsreplyitem != '') { $varmacsreplyitem .=","; }
		$varmacsreplyitem .= "\n\tAcct-Interim-Interval := $varmacsacctinteriminterval";
	}
	if ($varmacswisprredirectionurl != '') {
		if ($varmacsreplyitem != '') { $varmacsreplyitem .=","; }
		$varmacsreplyitem .= "\n\tWISPr-Redirection-URL := $varmacsswisprredirectionurl";
	}
	// If an octet limit is set we create the files for the limit and the counter. Further we call an exec script which checks if the limit is reached or not
	if ($varmacsmaxtotaloctets != '') {
		if ($varmacsreplyitem != '') { $varmacsreplyitem .=","; }
		//create exec script
		$varmacsreplyitem .= "\n\tExec-Program-Wait = " . '"/bin/sh ' . FREERADIUS_ETC . '/raddb/scripts/datacounter_auth.sh ' . "$varmacsaddress $varmacsmaxtotaloctetstimerange" . '"';
		// create limit file - will be always overwritten so we can increase limit from GUI
		exec("`echo $varmacsmaxtotaloctets > /var/log/radacct/datacounter/$varmacsmaxtotaloctetstimerange/max-octets-$varmacsaddress`");
		// if used-octets file exist we do NOT overwrite this file!!!
		if (!file_exists("/var/log/radacct/datacounter/$varmacsmaxtotaloctetstimerange/used-octets-$varmacsaddress")) { exec("echo 0 > /var/log/radacct/datacounter/$varmacsmaxtotaloctetstimerange/used-octets-$varmacsaddress"); }
	}
	// If an octet limit is NOT set we delete the files for the limit and the counter.
	else {
		if (file_exists("/var/log/radacct/datacounter/$varmacsmaxtotaloctetstimerange/max-octets-$varmacsaddress")) { unlink("/var/log/radacct/datacounter/$varmacsmaxtotaloctetstimerange/max-octets-$varmacsaddress"); }
		if (file_exists("/var/log/radacct/datacounter/$varmacsmaxtotaloctetstimerange/used-octets-$varmacsaddress")) { unlink("/var/log/radacct/datacounter/$varmacsmaxtotaloctetstimerange/used-octets-$varmacsaddress*"); }
	}
	if ($varmacsadditionaloptionsreplyitems != '') {
		if ($varmacsreplyitem != '') { $varmacsreplyitem .=","; }
		$varmacsreplyitem .= "\n\t$varmacsadditionaloptionsreplyitems";
	}
	
	// Cosmetic fix - This is just to make a blank new line after each macs entry
	$varmacsreplyitem .= "\n\n";
	
	
	$conf .= <<<EOD
$varmacsadditionaloptionstop
$varmacscheckitem
	$varmacsreplyitem
EOD;
	} //end foreach
} // end if

	$filename = FREERADIUS_ETC . '/raddb/authorized_macs';
	conf_mount_rw();
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
	conf_mount_ro();
	
	freeradius_sync_on_changes();
	restart_service('radiusd');
}

function freeradius_clients_resync() {
	global $config;

	$conf = '';
	$arrclients = $config['installedpackages']['freeradiusclients']['config'];
	if (is_array($arrclients) && !empty($arrclients)) {
		foreach ($arrclients as $item) {
			$varclientip = $item['varclientip'];
			$varclientsharedsecret = $item['varclientsharedsecret'];
			$varclientipversion = $item['varclientipversion'];
			$varclientshortname = $item['varclientshortname'];
			$varclientproto = $item['varclientproto'];
			$varrequiremessageauthenticator = $item['varrequiremessageauthenticator'];
			$varclientnastype = $item['varclientnastype'];
			$varclientmaxconnections = $item['varclientmaxconnections'];
			$varclientlogininput = ($item['varclientlogininput']?$item['varclientlogininput']:'### login = !root ###');
			$varclientpasswordinput = ($item['varclientpasswordinput']?$item['varclientpasswordinput']:'### password = someadminpass ###');
			
			if ($item['varclientlogininput'] == '') {
				$varclientlogin = '### login = !root ###';
			}
			else {
				$varclientlogin = "login = $varclientlogininput";
			}
			if ($item['varclientpasswordinput'] == '') {
				$varclientpassword = '### password = someadminpass ###';
			}
			else {
				$varclientpassword = "password = $varclientpasswordinput";
			}
			
			
			$conf .= <<<EOD

client "$varclientshortname" {
	$varclientipversion = $varclientip
	proto = $varclientproto
	secret = $varclientsharedsecret
	require_message_authenticator = $varrequiremessageauthenticator
	max_connections = $varclientmaxconnections
	shortname = $varclientshortname
	nastype = $varclientnastype
	$varclientlogin
	$varclientpassword
}

EOD;
		}
	}
	else {
		$conf .= <<<EOD
client pfsense {
	ipaddr = 127.0.0.1
	secret = pfsense
	shortname = pfsense
}

EOD;
	}

	conf_mount_rw();
	file_put_contents(FREERADIUS_ETC . '/raddb/clients.conf', $conf);
	conf_mount_ro();
	
	freeradius_sync_on_changes();
	restart_service("radiusd");
}



function freeradius_eapconf_resync() {
	global $config;
		// We make this write enabled here because embedded systems need to write certs in ../raddb/certs/ folder
		conf_mount_rw();
	$conf = '';

	$eapconf = $config['installedpackages']['freeradiuseapconf']['config'][0];
	
	// Disable weak EAP types like MD5, GTC, LEAP
	if ($eapconf['vareapconfdisableweakeaptypes'] == '') {
		$vareapconfweakeaptypes = "md5 {" . "\n\t\t}" . "\n\t\tleap {" . "\n\t\t}" . "\n\t\tgtc {" . "\n\t\t\t#challenge = " . '"Password: "' . "\n\t\t\tauth_type = PAP" . "\n\t\t}";
	}
	else {
		$vareapconfweakeaptypes = '### DISABLED WEAK EAP TYPES MD5, GTC, LEAP ###';
	}
	
	// Variables: EAP
	$vareapconfdefaulteaptype = ($eapconf['vareapconfdefaulteaptype']?$eapconf['vareapconfdefaulteaptype']:'md5');
	$vareapconftimerexpire = ($eapconf['vareapconftimerexpire']?$eapconf['vareapconftimerexpire']:'60');
	$vareapconfignoreunknowneaptypes = ($eapconf['vareapconfignoreunknowneaptypes']?$eapconf['vareapconfignoreunknowneaptypes']:'no');
	$vareapconfciscoaccountingusernamebug = ($eapconf['vareapconfciscoaccountingusernamebug']?$eapconf['vareapconfciscoaccountingusernamebug']:'no');
	$vareapconfmaxsessions = ($eapconf['vareapconfmaxsessions']?$eapconf['vareapconfmaxsessions']:'4096');
	
	// Variables: EAP-TLS
	$vareapconfprivatekeypassword = ($eapconf['vareapconfprivatekeypassword']?$eapconf['vareapconfprivatekeypassword']:'whatever');
	$vareapconffragmentsize = ($eapconf['vareapconffragmentsize']?$eapconf['vareapconffragmentsize']:'1024');
	$vareapconfincludelength = ($eapconf['vareapconfincludelength']?$eapconf['vareapconfincludelength']:'yes');
	$vareapconfcountry = ($eapconf['vareapconfcountry']?$eapconf['vareapconfcountry']:'US');
	$vareapconfstate = ($eapconf['vareapconfstate']?$eapconf['vareapconfstate']:'Texas');
	$vareapconfcity = ($eapconf['vareapconfcity']?$eapconf['vareapconfcity']:'Austin');
	$vareapconforganization = ($eapconf['vareapconforganization']?$eapconf['vareapconforganization']:'My Company Ltd');
	$vareapconfemail = ($eapconf['vareapconfemail']?$eapconf['vareapconfemail']:'admin@mycompany.com');
	$vareapconfcommonname = ($eapconf['vareapconfcommonname']?$eapconf['vareapconfcommonname']:'internal-ca');
		
	// Variables: Cache
	$vareapconfcacheenablecache = ($eapconf['vareapconfcacheenablecache']?$eapconf['vareapconfcacheenablecache']:'no');
	$vareapconfcachelifetime = ($eapconf['vareapconfcachelifetime']?$eapconf['vareapconfcachelifetime']:'24');
	$vareapconfcachemaxentries = ($eapconf['vareapconfcachemaxentries']?$eapconf['vareapconfcachemaxentries']:'255');
	
	// Variables OSCP	
	$vareapconfocspenable = ($eapconf['vareapconfocspenable']?$eapconf['vareapconfocspenable']:'no');
	$vareapconfocspoverridecerturl = ($eapconf['vareapconfocspoverridecerturl']?$eapconf['vareapconfocspoverridecerturl']:'no');
	$vareapconfocspurl = ($eapconf['vareapconfocspurl']?$eapconf['vareapconfocspurl']:'http://127.0.0.1/ocsp/');
	
	// Variables: EAP-TTLS
	$vareapconfttlsdefaulteaptype = ($eapconf['vareapconfttlsdefaulteaptype']?$eapconf['vareapconfttlsdefaulteaptype']:'md5');
	$vareapconfttlscopyrequesttotunnel = ($eapconf['vareapconfttlscopyrequesttotunnel']?$eapconf['vareapconfttlscopyrequesttotunnel']:'no');
	$vareapconfttlsusetunneledreply = ($eapconf['vareapconfttlsusetunneledreply']?$eapconf['vareapconfttlsusetunneledreply']:'no');
	$vareapconfttlsincludelength = ($eapconf['vareapconfttlsincludelength']?$eapconf['vareapconfttlsincludelength']:'yes');
	
	// Variables: EAP-PEAP with MSCHAPv2
	$vareapconfpeapdefaulteaptype = ($eapconf['vareapconfpeapdefaulteaptype']?$eapconf['vareapconfpeapdefaulteaptype']:'mschapv2');
	$vareapconfpeapcopyrequesttotunnel = ($eapconf['vareapconfpeapcopyrequesttotunnel']?$eapconf['vareapconfpeapcopyrequesttotunnel']:'no');
	$vareapconfpeapusetunneledreply = ($eapconf['vareapconfpeapusetunneledreply']?$eapconf['vareapconfpeapusetunneledreply']:'no');
	$vareapconfpeapsohenable = ($eapconf['vareapconfpeapsohenable']?$eapconf['vareapconfpeapsohenable']:'Disable');
	
	// This is for enable/disbable MS SoH in EAP-PEAP and the virtuial-server "soh-server"
	if ($eapconf['vareapconfpeapsohenable'] == 'Enable') {
		$vareapconfpeapsoh = 'soh = yes' . "\n\t\t\tsoh_virtual_server = " . '"' . "soh-server" . '"';
		exec("ln -s " . FREERADIUS_ETC . "/raddb/sites-available/soh " . FREERADIUS_ETC . "/raddb/sites-enabled/");
	}
	else {
		$vareapconfpeapsoh = '### MS SoH Server is disabled ###';
			if (file_exists(FREERADIUS_ETC . "/raddb/sites-enabled/soh")) {
			exec("rm -f " . FREERADIUS_ETC . "/raddb/sites-enabled/soh");
			}
	}
		

// The filenames of pfsense cert manager are different from freeradius cert manager so it is possible to store both in the same folder at any time.
// This is for the pfsense cert manager
// Depends on "freeradius_get_server_certs" and "freeradius_get_ca_certs"

if ($eapconf['vareapconfchoosecertmanager'] == 'on') {
		
		$ca_cert = lookup_ca($eapconf["ssl_ca_cert"]);
		if ($ca_cert != false) {
			if(base64_decode($ca_cert['prv'])) {
				file_put_contents(FREERADIUS_ETC . "/raddb/certs/ca_key.pem", 
					base64_decode($ca_cert['prv']));
				$conf['ssl_ca_key'] = FREERADIUS_ETC . '/raddb/certs/ca_key.pem';
			}


			if(base64_decode($ca_cert['crt'])) {
				$crl_cert = lookup_crl($eapconf["ssl_ca_crl"]);
				if ($crl_cert != false){
					$crl=base64_decode($crl_cert['text']);
					$check_crl="check_crl = yes";
				}
				else{
					$check_crl="check_crl = no";
				}
				file_put_contents(FREERADIUS_ETC . "/raddb/certs/ca_cert.pem", 
					base64_decode($ca_cert['crt']). $crl);
				$conf['ssl_ca_cert'] = FREERADIUS_ETC . "/raddb/certs/ca_cert.pem";
			}
			$svr_cert = lookup_cert($eapconf["ssl_server_cert"]);
			if ($svr_cert != false) {
				if(base64_decode($svr_cert['prv'])) {
					file_put_contents(FREERADIUS_ETC . "/raddb/certs/server_key.pem", 
						base64_decode($svr_cert['prv']));
					$conf['ssl_key'] = FREERADIUS_ETC . '/raddb/certs/server_key.pem';
				}
			}


			if(base64_decode($svr_cert['crt'])) {
				file_put_contents(FREERADIUS_ETC . "/raddb/certs/server_cert.pem", 
					base64_decode($svr_cert['crt']));
				$conf['ssl_server_cert'] = FREERADIUS_ETC . "/raddb/certs/server_cert.pem";
			}
			
			/* Not needed anymore because pfsense can do this by default
			if ($eapconf['vareapconfenableclientp12'] == 'on') {
				$svr_cert = lookup_cert($eapconf["ssl_client_cert"]);
				if ($svr_cert != false) {
					if(base64_decode($svr_cert['prv'])) {
						file_put_contents(FREERADIUS_ETC . "/raddb/certs/client_key.pem", 
							base64_decode($svr_cert['prv']));
						$conf['ssl_key'] = FREERADIUS_ETC . '/raddb/certs/client_key.pem';
					}
				}


				if(base64_decode($svr_cert['crt'])) {
					file_put_contents(FREERADIUS_ETC . "/raddb/certs/client_cert.pem", 
						base64_decode($svr_cert['crt']));
					$conf['ssl_client_cert'] = FREERADIUS_ETC . "/raddb/certs/client_cert.pem";
				}
			
			exec("openssl pkcs12 -export -in " . FREERADIUS_ETC . "/raddb/certs/client_cert.pem -inkey " . FREERADIUS_ETC . "/raddb/certs/client_key.pem -out " . FREERADIUS_ETC . "/raddb/certs/client_cert.p12 -passout pass\:");	
			}
			*/
			$conf['ssl_cert_dir'] = FREERADIUS_ETC . '/raddb/certs';
		}

	$vareapconfprivatekeyfile = 'server_key.pem';
	$vareapconfcertificatefile = 'server_cert.pem';
	$vareapconfcafile = 'ca_cert.pem';
	
	// generate new DH and RANDOM file
	// We create a single empty file just to check if there is really a change from one to another cert manager to avoid building ne DH and random files
	if (!file_exists(FREERADIUS_ETC . "/raddb/certs/pfsense_cert_mgr")) {
		log_error("freeRADIUS: Switched to pfSense Cert-Manager. Creating new DH and random file in " . FREERADIUS_ETC . "/raddb/certs");
		exec("cd " . FREERADIUS_ETC . "/raddb/certs && openssl dhparam -out dh 1024");
		exec("cd " . FREERADIUS_ETC . "/raddb/certs && dd if=/dev/urandom of=./random count=10");
		exec("touch " . FREERADIUS_ETC . "/raddb/certs/pfsense_cert_mgr");
	}
}

// This is for freeradius cert manager
else {
	$vareapconfprivatekeyfile = 'server.pem';
	$vareapconfcertificatefile = 'server.pem';
	$vareapconfcafile = 'ca.pem';
}

// check if the common name of the certificate must match the username
if($eapconf['vareapconfenablecheckcertcn'] == 'on') {
	$vareapconfcheckcertcn = 'check_cert_cn = %{User-Name}';
}
else {
	$vareapconfcheckcertcn = '### check_cert_cn = %{User-Name} ###';
}

// check if cert issuer of CA and certs match
if($eapconf['vareapconfenablecheckcertissuer'] == 'on') {
	$vareapconfcheckcertissuer = "check_cert_issuer = " . '"' . "/C=$vareapconfcountry/ST=$vareapconfstate/L=$vareapconfcity/O=$vareapconforganization/emailAddress=$vareapconfemail/CN=$vareapconfcommonname" . '"';
}
else {
	$vareapconfcheckcertissuer = '### check_cert_issuer = "/C=GB/ST=Berkshire/L=Newbury/O=My Company Ltd/emailAddress=test@mycomp.com/CN=myca" ###';
}

	$conf .= <<<EOD
	### EAP
	eap {
		default_eap_type = $vareapconfdefaulteaptype
		timer_expire     = $vareapconftimerexpire
		ignore_unknown_eap_types = $vareapconfignoreunknowneaptypes
		cisco_accounting_username_bug = $vareapconfciscoaccountingusernamebug
		max_sessions = $vareapconfmaxsessions
		
		$vareapconfweakeaptypes


		### EAP-TLS and EAP-TLS with OCSP support
		tls {
			certdir = \${confdir}/certs
			cadir = \${confdir}/certs
			private_key_password = $vareapconfprivatekeypassword
			private_key_file = \${certdir}/$vareapconfprivatekeyfile
			certificate_file = \${certdir}/$vareapconfcertificatefile
			CA_file = \${cadir}/$vareapconfcafile
			dh_file = \${certdir}/dh
			random_file = \${certdir}/random
			fragment_size = $vareapconffragmentsize
			include_length = $vareapconfincludelength
			{$check_crl}
			CA_path = \${cadir}
			$vareapconfcheckcertissuer
			$vareapconfcheckcertcn
			cipher_list = "DEFAULT"
			ecdh_curve = "prime256v1"
			cache {
			      enable = $vareapconfcacheenablecache
			      lifetime = $vareapconfcachelifetime
			      max_entries = $vareapconfcachemaxentries
			}
			verify {
		#     		tmpdir = /tmp/radiusd
		#    		client = "/path/to/openssl verify -CApath ${CA_path} %{TLS-Client-Cert-Filename}"
			}
			ocsp {
			      enable = $vareapconfocspenable
			      override_cert_url = $vareapconfocspoverridecerturl
			      url = "$vareapconfocspurl"
			}
		}
	
		### EAP-TTLS
		ttls {
			default_eap_type = $vareapconfttlsdefaulteaptype
			copy_request_to_tunnel = $vareapconfttlscopyrequesttotunnel
			use_tunneled_reply = $vareapconfttlsusetunneledreply
			include_length = $vareapconfttlsincludelength
		}	### end ttls	
	
		### EAP-PEAP
		peap {
			default_eap_type = $vareapconfpeapdefaulteaptype
			copy_request_to_tunnel = $vareapconfpeapcopyrequesttotunnel
			use_tunneled_reply = $vareapconfpeapusetunneledreply
		#	proxy_tunneled_request_as_eap = yes
			$vareapconfpeapsoh
		}
		mschapv2 {
		#	send_error = no
		}	
	}
EOD;

	$filename = FREERADIUS_ETC . '/raddb/eap.conf';
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
	conf_mount_ro();

	restart_service('radiusd');
}

// Gets started from freeradiuseapconf.xml
function freeradius_get_ca_certs() {
	global $config;
	$ca_arr = array();
	$ca_arr[] = array('refid' => 'none', 'descr' => 'none');

	if (is_array($config['ca'])) {
		foreach ($config['ca'] as $ca) {
			$ca_arr[] = array('refid' => $ca['refid'], 'descr' => $ca['descr']);
		}
	}
	return $ca_arr;
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

// Gets started from freeradiuseapconf.xml
function freeradius_get_server_certs() {
	global $config;
	$cert_arr = array();
	$cert_arr[] = array('refid' => 'none', 'descr' => 'none');

	if (is_array($config['cert'])) {
		foreach ($config['cert'] as $cert) {
			$cert_arr[] = array('refid' => $cert['refid'], 'descr' => $cert['descr']);
		}
	}
	return $cert_arr;
}

function freeradius_sqlconf_resync() {
	global $config;
	$conf = '';

	$sqlconf = $config['installedpackages']['freeradiussqlconf']['config'][0];
	
	// Variables: SQL DATABASE 1
	$varsqlconfdatabase = ($sqlconf['varsqlconfdatabase']?$sqlconf['varsqlconfdatabase']:'mysql');
	$varsqlconfserver = ($sqlconf['varsqlconfserver']?$sqlconf['varsqlconfserver']:'localhost');
	$varsqlconfport = ($sqlconf['varsqlconfport']?$sqlconf['varsqlconfport']:'3306');
	$varsqlconflogin = ($sqlconf['varsqlconflogin']?$sqlconf['varsqlconflogin']:'radius');
	$varsqlconfpassword = ($sqlconf['varsqlconfpassword']?$sqlconf['varsqlconfpassword']:'radpass');
	$varsqlconfradiusdb = ($sqlconf['varsqlconfradiusdb']?$sqlconf['varsqlconfradiusdb']:'radius');
	$varsqlconfaccttable1 = ($sqlconf['varsqlconfaccttable1']?$sqlconf['varsqlconfaccttable1']:'radacct');
	$varsqlconfaccttable2 = ($sqlconf['varsqlconfaccttable2']?$sqlconf['varsqlconfaccttable2']:'radacct');
	$varsqlconfpostauthtable = ($sqlconf['varsqlconfpostauthtable']?$sqlconf['varsqlconfpostauthtable']:'radpostauth');
	$varsqlconfauthchecktable = ($sqlconf['varsqlconfauthchecktable']?$sqlconf['varsqlconfauthchecktable']:'radcheck');
	$varsqlconfauthreplytable = ($sqlconf['varsqlconfauthreplytable']?$sqlconf['varsqlconfauthreplytable']:'radreply');
	$varsqlconfgroupchecktable = ($sqlconf['varsqlconfgroupchecktable']?$sqlconf['varsqlconfgroupchecktable']:'radgroupcheck');
	$varsqlconfgroupreplytable = ($sqlconf['varsqlconfgroupreplytable']?$sqlconf['varsqlconfgroupreplytable']:'radgroupreply');
	$varsqlconfusergrouptable = ($sqlconf['varsqlconfusergrouptable']?$sqlconf['varsqlconfusergrouptable']:'radusergroup');
	$varsqlconfreadgroups = ($sqlconf['varsqlconfreadgroups']?$sqlconf['varsqlconfreadgroups']:'yes');
	$varsqlconfdeletestalesessions = ($sqlconf['varsqlconfdeletestalesessions']?$sqlconf['varsqlconfdeletestalesessions']:'yes');
	$varsqlconfsqltrace = ($sqlconf['varsqlconfsqltrace']?$sqlconf['varsqlconfsqltrace']:'no');
	$varsqlconfnumsqlsocks = ($sqlconf['varsqlconfnumsqlsocks']?$sqlconf['varsqlconfnumsqlsocks']:'5');
	$varsqlconfconnectfailureretrydelay = ($sqlconf['varsqlconfconnectfailureretrydelay']?$sqlconf['varsqlconfconnectfailureretrydelay']:'60');
	$varsqlconflifetime = ($sqlconf['varsqlconflifetime']?$sqlconf['varsqlconflifetime']:'0');
	$varsqlconfmaxqueries = ($sqlconf['varsqlconfmaxqueries']?$sqlconf['varsqlconfmaxqueries']:'0');
	$varsqlconfreadclients = ($sqlconf['varsqlconfreadclients']?$sqlconf['varsqlconfreadclients']:'yes');
	$varsqlconfnastable = ($sqlconf['varsqlconfnastable']?$sqlconf['varsqlconfnastable']:'nas');
	
	// Additional changes were made in "freeradius_settings_resync"

	// Variables: SQL DATABASE 2
	$varsqlconf2database = ($sqlconf['varsqlconf2database']?$sqlconf['varsqlconf2database']:'mysql');
	$varsqlconf2server = ($sqlconf['varsqlconf2server']?$sqlconf['varsqlconf2server']:'localhost');
	$varsqlconf2port = ($sqlconf['varsqlconf2port']?$sqlconf['varsqlconf2port']:'3306');
	$varsqlconf2login = ($sqlconf['varsqlconf2login']?$sqlconf['varsqlconf2login']:'radius');
	$varsqlconf2password = ($sqlconf['varsqlconf2password']?$sqlconf['varsqlconf2password']:'radpass');
	$varsqlconf2radiusdb = ($sqlconf['varsqlconf2radiusdb']?$sqlconf['varsqlconf2radiusdb']:'radius');
	$varsqlconf2accttable1 = ($sqlconf['varsqlconf2accttable1']?$sqlconf['varsqlconf2accttable1']:'radacct');
	$varsqlconf2accttable2 = ($sqlconf['varsqlconf2accttable2']?$sqlconf['varsqlconf2accttable2']:'radacct');
	$varsqlconf2postauthtable = ($sqlconf['varsqlconf2postauthtable']?$sqlconf['varsqlconf2postauthtable']:'radpostauth');
	$varsqlconf2authchecktable = ($sqlconf['varsqlconf2authchecktable']?$sqlconf['varsqlconf2authchecktable']:'radcheck');
	$varsqlconf2authreplytable = ($sqlconf['varsqlconf2authreplytable']?$sqlconf['varsqlconf2authreplytable']:'radreply');
	$varsqlconf2groupchecktable = ($sqlconf['varsqlconf2groupchecktable']?$sqlconf['varsqlconf2groupchecktable']:'radgroupcheck');
	$varsqlconf2groupreplytable = ($sqlconf['varsqlconf2groupreplytable']?$sqlconf['varsqlconf2groupreplytable']:'radgroupreply');
	$varsqlconf2usergrouptable = ($sqlconf['varsqlconf2usergrouptable']?$sqlconf['varsqlconf2usergrouptable']:'radusergroup');
	$varsqlconf2readgroups = ($sqlconf['varsqlconf2readgroups']?$sqlconf['varsqlconf2readgroups']:'yes');
	$varsqlconf2deletestalesessions = ($sqlconf['varsqlconf2deletestalesessions']?$sqlconf['varsqlconf2deletestalesessions']:'yes');
	$varsqlconf2sqltrace = ($sqlconf['varsqlconf2sqltrace']?$sqlconf['varsqlconf2sqltrace']:'no');
	$varsqlconf2numsqlsocks = ($sqlconf['varsqlconf2numsqlsocks']?$sqlconf['varsqlconf2numsqlsocks']:'5');
	$varsqlconf2connectfailureretrydelay = ($sqlconf['varsqlconf2connectfailureretrydelay']?$sqlconf['varsqlconf2connectfailureretrydelay']:'60');
	$varsqlconf2lifetime = ($sqlconf['varsqlconf2lifetime']?$sqlconf['varsqlconf2lifetime']:'0');
	$varsqlconf2maxqueries = ($sqlconf['varsqlconf2maxqueries']?$sqlconf['varsqlconf2maxqueries']:'0');
	$varsqlconf2readclients = ($sqlconf['varsqlconf2readclients']?$sqlconf['varsqlconf2readclients']:'yes');
	$varsqlconf2nastable = ($sqlconf['varsqlconf2nastable']?$sqlconf['varsqlconf2nastable']:'nas');
	
	// Additional changes were made in "freeradius_settings_resync"
	
	
	$conf .= <<<EOD

sql {
	database = "$varsqlconfdatabase"
	driver = "rlm_sql_\${database}"
	server = "$varsqlconfserver"
	port = $varsqlconfport
	login = "$varsqlconflogin"
	password = "$varsqlconfpassword"
	radius_db = "$varsqlconfradiusdb"
	acct_table1 = "$varsqlconfaccttable1"
	acct_table2 = "$varsqlconfaccttable2"
	postauth_table = "$varsqlconfpostauthtable"
	authcheck_table = "$varsqlconfauthchecktable"
	authreply_table = "$varsqlconfauthreplytable"
	groupcheck_table = "$varsqlconfgroupchecktable"
	groupreply_table = "$varsqlconfgroupreplytable"
	usergroup_table = "$varsqlconfusergrouptable"
	read_groups = $varsqlconfreadgroups
	deletestalesessions = $varsqlconfdeletestalesessions
	sqltrace = $varsqlconfsqltrace
	sqltracefile = \${logdir}/sqltrace.sql
	num_sql_socks = $varsqlconfnumsqlsocks
	connect_failure_retry_delay = $varsqlconfconnectfailureretrydelay
	lifetime = $varsqlconflifetime
	max_queries = $varsqlconfmaxqueries
	readclients = $varsqlconfreadclients
	nas_table = "$varsqlconfnastable"
	\$INCLUDE sql/\${database}/dialup.conf
}

sql sql2 {
	database = "$varsqlconf2database"
	driver = "rlm_sql_\${database}"
	server = "$varsqlconf2server"
	port = $varsqlconf2port
	login = "$varsqlconf2login"
	password = "$varsqlconf2password"
	radius_db = "$varsqlconf2radiusdb"
	acct_table1 = "$varsqlconf2accttable1"
	acct_table2 = "$varsqlconf2accttable2"
	postauth_table = "$varsqlconf2postauthtable"
	authcheck_table = "$varsqlconf2authchecktable"
	authreply_table = "$varsqlconf2authreplytable"
	groupcheck_table = "$varsqlconf2groupchecktable"
	groupreply_table = "$varsqlconf2groupreplytable"
	usergroup_table = "$varsqlconf2usergrouptable"
	read_groups = $varsqlconf2readgroups
	deletestalesessions = $varsqlconf2deletestalesessions
	sqltrace = $varsqlconf2sqltrace
	sqltracefile = \${logdir}/sqltrace.sql
	num_sql_socks = $varsqlconf2numsqlsocks
	connect_failure_retry_delay = $varsqlconf2connectfailureretrydelay
	lifetime = $varsqlconf2lifetime
	max_queries = $varsqlconf2maxqueries
	readclients = $varsqlconf2readclients
	nas_table = "$varsqlconf2nastable"
	\$INCLUDE sql/\${database}/dialup.conf
}
EOD;

	$filename = FREERADIUS_ETC . '/raddb/sql.conf';
	conf_mount_rw();
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
	conf_mount_ro();
	
	// We don't need a restart at this time because there are additional changes needed in:
	// "freeradius_settings_resync" and "freeradius_serverdefault_resync".
	// restart_service('radiusd');
	freeradius_settings_resync();
}

function freeradius_serverdefault_resync() {
	global $config;
	$conf = '';
		
	// Get Variables from freeradiusmodulesldap.xml
	$arrmodulesldap = $config['installedpackages']['freeradiusmodulesldap']['config'][0];
	// failover/loadbalancing mode
	$varmodulesldap2failover = ($arrmodulesldap['varmodulesldap2failover']?$arrmodulesldap['varmodulesldap2failover']:'redundant');
	
	// If unchecked then disable authorize ldap2
	if (!$arrmodulesldap['varmodulesldap2enableauthorize']) {
		$varmodulesldap2enableauthorize = '### ldap2 disabled ###';
	}
	else {
		$varmodulesldap2enableauthorize = 'ldap2';
	}
	
	// If unchecked then disable authorize ldap1
	if (!$arrmodulesldap['varmodulesldapenableauthorize']) {
		$varmodulesldapenableauthorize = '### ldap ###';
	}
	else {
		$varmodulesldapenableauthorize = '';
		$varmodulesldapenableauthorize .= "$varmodulesldap2failover {";
		$varmodulesldapenableauthorize .= "\n\t\tldap";
		// this line adds ldap2 when activated
		$varmodulesldapenableauthorize .= "\n\t\t$varmodulesldap2enableauthorize";
		$varmodulesldapenableauthorize .= "\n\t}";
	}
	
	// If unchecked then disable authenticate for ldap1
	if (!$arrmodulesldap['varmodulesldap2enableauthenticate']) {
		$varmodulesldap2enableauthenticate = "### ldap2 disabled ###";
	}
	else {
		$varmodulesldap2enableauthenticate = "ldap2";
	}
	
	// If unchecked then disable authenticate ldap2
	if (!$arrmodulesldap['varmodulesldapenableauthenticate']) {
		$varmodulesldapenableauthenticate = "#Auth-Type LDAP {" . "\n\t\t\t#ldap" . "\n\t\t\t$varmodulesldap2enableauthenticate" . "\n\t#}";
	}
	else {
		$varmodulesldapenableauthenticate = "Auth-Type LDAP {" . "\n\t\t\tldap" . "\n\t\t\t$varmodulesldap2enableauthenticate" . "\n\t}";
	}
	
	// Get Variables from freeradiussqlconf.xml for DATABASE 1
	$sqlconf = $config['installedpackages']['freeradiussqlconf']['config'][0];
	$varsqlconfenableauthorize = ($sqlconf['varsqlconfenableauthorize']?$sqlconf['varsqlconfenableauthorize']:'Disable');
	$varsqlconfenableaccounting = ($sqlconf['varsqlconfenableaccounting']?$sqlconf['varsqlconfenableaccounting']:'Disable');
	$varsqlconfenablesession = ($sqlconf['varsqlconfenablesession']?$sqlconf['varsqlconfenablesession']:'Disable');
	$varsqlconfenablepostauth = ($sqlconf['varsqlconfenablepostauth']?$sqlconf['varsqlconfenablepostauth']:'Disable');
	
	// Get Variables from freeradiussqlconf.xml for DATABASE 2
	$varsqlconf2enableauthorize = ($sqlconf['varsqlconf2enableauthorize']?$sqlconf['varsqlconf2enableauthorize']:'Disable');
	$varsqlconf2enableaccounting = ($sqlconf['varsqlconf2enableaccounting']?$sqlconf['varsqlconf2enableaccounting']:'Disable');
	$varsqlconf2enablesession = ($sqlconf['varsqlconf2enablesession']?$sqlconf['varsqlconf2enablesession']:'Disable');
	$varsqlconf2enablepostauth = ($sqlconf['varsqlconf2enablepostauth']?$sqlconf['varsqlconf2enablepostauth']:'Disable');
	
	// authorize section DATABASE 2
	if ($sqlconf['varsqlconf2enableauthorize'] == 'Enable') {
		$varsqlconf2authorize = 'sql2';
	}
	else {
		$varsqlconf2authorize = '### sql2 DISABLED ###';
	}
	// accounting section DATABASE 2
	if ($sqlconf['varsqlconf2enableaccounting'] == 'Enable') {
		$varsqlconf2accounting = 'sql2';
	}
	else {
		$varsqlconf2accounting = '### sql2 DISABLED ###';
	}
	// session section DATABASE 2
	if ($sqlconf['varsqlconf2enablesession'] == 'Enable') {
		$varsqlconf2session = 'sql2';
	}
	else {
		$varsqlconf2session = '### sql2 DISABLED ###';
	}
	// post-auth section DATABASE 2
	if ($sqlconf['varsqlconf2enablepostauth'] == 'Enable') {
		$varsqlconf2postauth = 'sql2';
	}
	else {
		$varsqlconf2postauth = '### sql2 DISABLED ###';
	}	
	
	// Failover mode
	$varsqlconf2failover = ($sqlconf['varsqlconf2failover']?$sqlconf['varsqlconf2failover']:'redundant');

	// authorize section DATABASE 1
	if (($sqlconf['varsqlconfincludeenable'] == 'on') && ($sqlconf['varsqlconfenableauthorize'] == 'Enable')) {
		$varsqlconfauthorize = "$varsqlconf2failover {" . "\n\t\t\tsql" . "\n\t\t\t$varsqlconf2authorize" . "\n\t}";
	}
	else {
		$varsqlconfauthorize = '### sql DISABLED ###';
	}
	
	// accounting section DATABASE 1
	if (($sqlconf['varsqlconfincludeenable'] == 'on') && ($sqlconf['varsqlconfenableaccounting'] == 'Enable')) {
		$varsqlconfaccounting = "$varsqlconf2failover {" . "\n\t\t\tsql" . "\n\t\t\t$varsqlconf2accounting" . "\n\t}";
	}
	else {
		$varsqlconfaccounting = '### sql DISABLED ###';
	}
	
	// session section DATABASE 1
	if (($sqlconf['varsqlconfincludeenable'] == 'on') && ($sqlconf['varsqlconfenablesession'] == 'Enable')) {
		$varsqlconfsession = "$varsqlconf2failover {" . "\n\t\t\tsql" . "\n\t\t\t$varsqlconf2session" . "\n\t}";
	}
	else {
		$varsqlconfsession = 'radutmp';
	}

	// post-auth section DATABASE 1
	if (($sqlconf['varsqlconfincludeenable'] == 'on') && ($sqlconf['varsqlconfenablepostauth'] == 'Enable')) {
		$varsqlconfpostauth = "$varsqlconf2failover {" . "\n\t\t\tsql" . "\n\t\t\t$varsqlconf2postauth" . "\n\t}";
	}
	else {
		$varsqlconfpostauth = '### sql DISABLED ###';
	}
	
	// Changing authorize section for plain mac auth
	// Variables: If not using 802.1x, mac address must be known
	$varsettings = $config['installedpackages']['freeradiussettings']['config'][0];
	
	// If unchecked we need the normal EAP section.
	if (!$varsettings['varsettingsenablemacauth']) {
		$varplainmacauthenable = '##### AUTHORIZE FOR PLAIN MAC-AUTH IS DISABLED #####';

		$varplainmacpreacctenable = '##### ACCOUNTING FOR PLAIN MAC-AUTH DISABLED #####';
	}
	// If checked we need to check if it is plain mac or eap
	else {
		$varplainmacauthenable = '';
		$varplainmacauthenable .= "### FIRST check MAC address in authorized_macs and if that fails proceed with other checks below in else-section ###";
		$varplainmacauthenable .= "\n\t### if cleaning up the Calling-Station-Id...###";
		$varplainmacauthenable .= "\n\trewrite_calling_station_id";
		$varplainmacauthenable .= "\n\t";
		$varplainmacauthenable .= "\n\t# now check against the authorized_macs file";
		$varplainmacauthenable .= "\n\tauthorized_macs";
		$varplainmacauthenable .= "\n\tif (ok) {";
		$varplainmacauthenable .= "\n\t\t\tupdate control {";
		$varplainmacauthenable .= "\n\t\t\tAuth-Type := Accept";
		$varplainmacauthenable .= "\n\t\t}";
		$varplainmacauthenable .= "\n\t}";
		$varplainmacauthenable .= "\n\t### Here we have to place all other authorize modules which should be check when MAC fails ###";

		$varplainmacpreacctenable = '';
		$varplainmacpreacctenable .= '##### ACCOUNTING FOR PLAIN MAC-AUTH ENABLED #####';
		$varplainmacpreacctenable .= "\n\trewrite_calling_station_id";
	}
	
	// Disable acct_unique in preacct section
	$varsettings = $config['installedpackages']['freeradiussettings']['config'][0];

	if ($varsettings['varsettingsenableacctunique'] == 'on') {
		$varsettingsacctuniqueenabled = '##### DISABLE acct_unique DISABLE #####';
	}
	else {
		$varsettingsacctuniqueenabled = 'acct_unique';
	}
	
	$conf .= <<<EOD
######################################################################
#
#	As of 2.0.0, FreeRADIUS supports virtual hosts using the
#	"server" section, and configuration directives.
#
#	Virtual hosts should be put into the "sites-available"
#	directory.  Soft links should be created in the "sites-enabled"
#	directory to these files.  This is done in a normal installation.
#
#	If you are using 802.1X (EAP) authentication, please see also
#	the "inner-tunnel" virtual server.  You wll likely have to edit
#	that, too, for authentication to work.
#
#	$Id$
#
######################################################################
#
#	Read "man radiusd" before editing this file.  See the section
#	titled DEBUGGING.  It outlines a method where you can quickly
#	obtain the configuration you want, without running into
#	trouble.  See also "man unlang", which documents the format
#	of this file.
#
#	This configuration is designed to work in the widest possible
#	set of circumstances, with the widest possible number of
#	authentication methods.  This means that in general, you should
#	need to make very few changes to this file.
#
#	The best way to configure the server for your local system
#	is to CAREFULLY edit this file.  Most attempts to make large
#	edits to this file will BREAK THE SERVER.  Any edits should
#	be small, and tested by running the server with "radiusd -X".
#	Once the edits have been verified to work, save a copy of these
#	configuration files somewhere.  (e.g. as a "tar" file).  Then,
#	make more edits, and test, as above.
#
#	There are many "commented out" references to modules such
#	as ldap, sql, etc.  These references serve as place-holders.
#	If you need the functionality of that module, then configure
#	it in radiusd.conf, and un-comment the references to it in
#	this file.  In most cases, those small changes will result
#	in the server being able to connect to the DB, and to
#	authenticate users.
#
######################################################################

#
#	In 1.x, the "authorize", etc. sections were global in
#	radiusd.conf.  As of 2.0, they SHOULD be in a server section.
#
#	The server section with no virtual server name is the "default"
#	section.  It is used when no server name is specified.
#
#	We don't indent the rest of this file, because doing so
#	would make it harder to read.
#

#  Authorization. First preprocess (hints and huntgroups files),
#  then realms, and finally look in the "users" file.
#
#  Any changes made here should also be made to the "inner-tunnel"
#  virtual server.
#
#  The order of the realm modules will determine the order that
#  we try to find a matching realm.
#
#  Make *sure* that 'preprocess' comes before any realm if you 
#  need to setup hints for the remote radius server
authorize {
	#
	#  Security settings.  Take a User-Name, and do some simple
	#  checks on it, for spaces and other invalid characters.  If
	#  it looks like the user is trying to play games, reject it.
	#
	#  This should probably be enabled by default.
	#
	#  See policy.conf for the definition of the filter_username policy.
	#
#	filter_username

	#
	#  The preprocess module takes care of sanitizing some bizarre
	#  attributes in the request, and turning them into attributes
	#  which are more standard.
	#
	#  It takes care of processing the 'raddb/hints' and the
	#  'raddb/huntgroups' files.
	preprocess
	
	#
	#
	$varplainmacauthenable
	
	#
	#  If you want to have a log of authentication requests,
	#  un-comment the following line, and the 'detail auth_log'
	#  section, above.
#	auth_log

	#
	#  The chap module will set 'Auth-Type := CHAP' if we are
	#  handling a CHAP request and Auth-Type has not already been set
	chap

	#
	#  If the users are logging in with an MS-CHAP-Challenge
	#  attribute for authentication, the mschap module will find
	#  the MS-CHAP-Challenge attribute, and add 'Auth-Type := MS-CHAP'
	#  to the request, which will cause the server to then use
	#  the mschap module for authentication.
	mschap

	#
	#  If you have a Cisco SIP server authenticating against
	#  FreeRADIUS, uncomment the following line, and the 'digest'
	#  line in the 'authenticate' section.
	digest

	#
	#  The WiMAX specification says that the Calling-Station-Id
	#  is 6 octets of the MAC.  This definition conflicts with
	#  RFC 3580, and all common RADIUS practices.  Un-commenting
	#  the "wimax" module here means that it will fix the
	#  Calling-Station-Id attribute to the normal format as
	#  specified in RFC 3580 Section 3.21
#	wimax

	#
	#  Look for IPASS style 'realm/', and if not found, look for
	#  '@realm', and decide whether or not to proxy, based on
	#  that.
#	IPASS

	#
	#  If you are using multiple kinds of realms, you probably
	#  want to set "ignore_null = yes" for all of them.
	#  Otherwise, when the first style of realm doesn't match,
	#  the other styles won't be checked.
	#
	
	suffix
	ntdomain
	
	#
	#  This module takes care of EAP-MD5, EAP-TLS, and EAP-LEAP
	#  authentication.
	#
	#  It also sets the EAP-Type attribute in the request
	#  attribute list to the EAP type from the packet.
	#
	#  As of 2.0, the EAP module returns "ok" in the authorize stage
	#  for TTLS and PEAP.  In 1.x, it never returned "ok" here, so
	#  this change is compatible with older configurations.
	#
	#  The example below uses module failover to avoid querying all
	#  of the following modules if the EAP module returns "ok".
	#  Therefore, your LDAP and/or SQL servers will not be queried
	#  for the many packets that go back and forth to set up TTLS
	#  or PEAP.  The load on those servers will therefore be reduced.
	#
	#
	
	eap {
		ok = return
	}
	
	#
	#  Pull crypt'd passwords from /etc/passwd or /etc/shadow,
	#  using the system API's to get the password.  If you want
	#  to read /etc/passwd or /etc/shadow directly, see the
	#  passwd module in radiusd.conf.
	#
#	unix

	#
	#  Read the 'users' file
	files

	#
	#  Look in an SQL database.  The schema of the database
	#  is meant to mirror the "users" file.
	#
	#  See "Authorization Queries" in sql.conf
	$varsqlconfauthorize

	#
	#  If you are using /etc/smbpasswd, and are also doing
	#  mschap authentication, the un-comment this line, and
	#  configure the 'etc_smbpasswd' module, above.
#	etc_smbpasswd

	#
	#  The ldap module will set Auth-Type to LDAP if it has not
	#  already been set
	
	$varmodulesldapenableauthorize

	#
	#  Enforce daily limits on time spent logged in.
	daily
	weekly
	monthly
	forever

	#
	# Use the checkval module
	checkval

	expiration
	logintime

	#
	#  If no other module has claimed responsibility for
	#  authentication, then try to use PAP.  This allows the
	#  other modules listed above to add a "known good" password
	#  to the request, and to do nothing else.  The PAP module
	#  will then see that password, and use it to do PAP
	#  authentication.
	#
	#  This module should be listed last, so that the other modules
	#  get a chance to set Auth-Type for themselves.
	#
	pap
	
	#
	#  If "status_server = yes", then Status-Server messages are passed
	#  through the following section, and ONLY the following section.
	#  This permits you to do DB queries, for example.  If the modules
	#  listed here return "fail", then NO response is sent.
	#
	Autz-Type Status-Server {

	}
}


#  Authentication.
#
#
#  This section lists which modules are available for authentication.
#  Note that it does NOT mean 'try each module in order'.  It means
#  that a module from the 'authorize' section adds a configuration
#  attribute 'Auth-Type := FOO'.  That authentication type is then
#  used to pick the apropriate module from the list below.
#

#  In general, you SHOULD NOT set the Auth-Type attribute.  The server
#  will figure it out on its own, and will do the right thing.  The
#  most common side effect of erroneously setting the Auth-Type
#  attribute is that one authentication method will work, but the
#  others will not.
#
#  The common reasons to set the Auth-Type attribute by hand
#  is to either forcibly reject the user (Auth-Type := Reject),
#  or to or forcibly accept the user (Auth-Type := Accept).
#
#  Note that Auth-Type := Accept will NOT work with EAP.
#
#  Please do not put "unlang" configurations into the "authenticate"
#  section.  Put them in the "post-auth" section instead.  That's what
#  the post-auth section is for.
#
authenticate {
	#
	#  PAP authentication, when a back-end database listed
	#  in the 'authorize' section supplies a password.  The
	#  password can be clear-text, or encrypted.
	Auth-Type PAP {
		pap
	}

	#
	#  Most people want CHAP authentication
	#  A back-end database listed in the 'authorize' section
	#  MUST supply a CLEAR TEXT password.  Encrypted passwords
	#  won't work.
	Auth-Type CHAP {
		chap
	}

	#
	#  MSCHAP authentication.
	Auth-Type MS-CHAP {
		mschap
	}

	#
	#  Mobile-One-Time-Password (MOTP) authentication.
	Auth-Type MOTP {
		motp
	}
	
	#
	# Google-Authenticator authentication.
	Auth-Type GOOGLEAUTH {
		googleauth
	}
	
	#
	#  If you have a Cisco SIP server authenticating against
	#  FreeRADIUS, uncomment the following line, and the 'digest'
	#  line in the 'authorize' section.
	digest

	#
	#  Pluggable Authentication Modules.
#	pam

	#
	#  See 'man getpwent' for information on how the 'unix'
	#  module checks the users password.  Note that packets
	#  containing CHAP-Password attributes CANNOT be authenticated
	#  against /etc/passwd!  See the FAQ for details.
	#
	#  For normal "crypt" authentication, the "pap" module should
	#  be used instead of the "unix" module.  The "unix" module should
	#  be used for authentication ONLY for compatibility with legacy
	#  FreeRADIUS configurations.
	#
	unix

	# Uncomment it if you want to use ldap for authentication
	#
	# Note that this means "check plain-text password against
	# the ldap database", which means that EAP won't work,
	# as it does not supply a plain-text password.
	$varmodulesldapenableauthenticate

	#
	#  Allow EAP authentication.
	eap

	#
	#  The older configurations sent a number of attributes in
	#  Access-Challenge packets, which wasn't strictly correct.
	#  If you want to filter out these attributes, uncomment
	#  the following lines.
	#
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


#
#  Pre-accounting.  Decide which accounting type to use.
#
preacct {
	preprocess
	
	$varplainmacpreacctenable
	
	#
	#  Session start times are *implied* in RADIUS.
	#  The NAS never sends a "start time".  Instead, it sends
	#  a start packet, *possibly* with an Acct-Delay-Time.
	#  The server is supposed to conclude that the start time
	#  was "Acct-Delay-Time" seconds in the past.
	#
	#  The code below creates an explicit start time, which can
	#  then be used in other modules.
	#
	#  The start time is: NOW - delay - session_length
	#

	update request {
		FreeRADIUS-Acct-Session-Start-Time = "%{expr: %l - %{%{Acct-Session-Time}:-0} - %{%{Acct-Delay-Time}:-0}}"
	}

	#
	#  Ensure that we have a semi-unique identifier for every
	#  request, and many NAS boxes are broken.
	$varsettingsacctuniqueenabled

	#
	#  Look for IPASS-style 'realm/', and if not found, look for
	#  '@realm', and decide whether or not to proxy, based on
	#  that.
	#
	#  Accounting requests are generally proxied to the same
	#  home server as authentication requests.
#	IPASS
	suffix
	ntdomain

	#
	#  Read the 'acct_users' file
	files
}

#
#  Accounting.  Log the accounting data.
#
accounting {
	#
	#  Create a 'detail'ed log of the packets.
	#  Note that accounting requests which are proxied
	#  are also logged in the detail file.
	detail
	daily
	weekly
	monthly
	forever

	### This makes it possible to run the datacounter_acct module only on accounting-stop and interim-updates
	if ((request:Acct-Status-Type == Stop) || (request:Acct-Status-Type == Interim-Update)) {
		datacounterdaily
		datacounterweekly
		datacountermonthly
		datacounterforever
	}
	
	#  Update the wtmp file
	#
	#  If you don't use "radlast", you can delete this line.
	unix

	#
	#  For Simultaneous-Use tracking.
	#
	#  Due to packet losses in the network, the data here
	#  may be incorrect.  There is little we can do about it.
	radutmp
#	sradutmp

	#  Return an address to the IP Pool when we see a stop record.
#	main_pool

	#
	#  Log traffic to an SQL database.
	#
	#  See "Accounting queries" in sql.conf
	$varsqlconfaccounting

	#
	#  If you receive stop packets with zero session length,
	#  they will NOT be logged in the database.  The SQL module
	#  will print a message (only in debugging mode), and will
	#  return "noop".
	#
	#  You can ignore these packets by uncommenting the following
	#  three lines.  Otherwise, the server will not respond to the
	#  accounting request, and the NAS will retransmit.
	#
#	if (noop) {
#		ok
#	}

	#
	#  Instead of sending the query to the SQL server,
	#  write it into a log file.
	#
#	sql_log

	#  Cisco VoIP specific bulk accounting
#	pgsql-voip

	# For Exec-Program and Exec-Program-Wait
	exec

	#  Filter attributes from the accounting response.
	attr_filter.accounting_response

	#
	#  See "Autz-Type Status-Server" for how this works.
	#
	Acct-Type Status-Server {

	}
}


#  Session database, used for checking Simultaneous-Use. Either the radutmp 
#  or rlm_sql module can handle this.
#  The rlm_sql module is *much* faster
session {
	### choose radutmp or sql
	$varsqlconfsession
}


#  Post-Authentication
#  Once we KNOW that the user has been authenticated, there are
#  additional steps we can take.
post-auth {
	#  Get an address from the IP Pool.
#	main_pool

	#
	#  If you want to have a log of authentication replies,
	#  un-comment the following line, and the 'detail reply_log'
	#  section, above.
#	reply_log

	#
	#  After authenticating the user, do another SQL query.
	#
	#  See "Authentication Logging Queries" in sql.conf
	$varsqlconfpostauth

	#
	#  Instead of sending the query to the SQL server,
	#  write it into a log file.
	#
#	sql_log

	#
	#  Un-comment the following if you have set
	#  'edir_account_policy_check = yes' in the ldap module sub-section of
	#  the 'modules' section.
	#
#	ldap

	# For Exec-Program and Exec-Program-Wait
	exec

	#
	#  Calculate the various WiMAX keys.  In order for this to work,
	#  you will need to define the WiMAX NAI, usually via
	#
	#	update request {
	#	       WiMAX-MN-NAI = "%{User-Name}"
	#	}
	#
	#  If you want various keys to be calculated, you will need to
	#  update the reply with "template" values.  The module will see
	#  this, and replace the template values with the correct ones
	#  taken from the cryptographic calculations.  e.g.
	#
	# 	update reply {
	#		WiMAX-FA-RK-Key = 0x00
	#		WiMAX-MSK = "%{EAP-MSK}"
	#	}
	#
	#  You may want to delete the MS-MPPE-*-Keys from the reply,
	#  as some WiMAX clients behave badly when those attributes
	#  are included.  See "raddb/modules/wimax", configuration
	#  entry "delete_mppe_keys" for more information.
	#
#	wimax

	#  If there is a client certificate (EAP-TLS, sometimes PEAP
	#  and TTLS), then some attributes are filled out after the
	#  certificate verification has been performed.  These fields
	#  MAY be available during the authentication, or they may be
	#  available only in the "post-auth" section.
	#
	#  The first set of attributes contains information about the
	#  issuing certificate which is being used.  The second
	#  contains information about the client certificate (if
	#  available).
#
#	update reply {
#	       Reply-Message += "%{TLS-Cert-Serial}"
#	       Reply-Message += "%{TLS-Cert-Expiration}"
#	       Reply-Message += "%{TLS-Cert-Subject}"
#	       Reply-Message += "%{TLS-Cert-Issuer}"
#	       Reply-Message += "%{TLS-Cert-Common-Name}"
#
#	       Reply-Message += "%{TLS-Client-Cert-Serial}"
#	       Reply-Message += "%{TLS-Client-Cert-Expiration}"
#	       Reply-Message += "%{TLS-Client-Cert-Subject}"
#	       Reply-Message += "%{TLS-Client-Cert-Issuer}"
#	       Reply-Message += "%{TLS-Client-Cert-Common-Name}"
#	}


	#  If the WiMAX module did it's work, you may want to do more
	#  things here, like delete the MS-MPPE-*-Key attributes.
	#
	#	if (updated) {
	#		update reply {
	#			MS-MPPE-Recv-Key !* 0x00
	#			MS-MPPE-Send-Key !* 0x00
	#		}
	#	}

	#
	#  Access-Reject packets are sent through the REJECT sub-section of the
	#  post-auth section.
	#
	#  Add the ldap module name (or instance) if you have set 
	#  'edir_account_policy_check = yes' in the ldap module configuration
	#
	Post-Auth-Type REJECT {

		# log failed authentications in SQL, too.
		# sql
		attr_filter.access_reject
	}
}
#
#  When the server decides to proxy a request to a home server,
#  the proxied request is first passed through the pre-proxy
#  stage.  This stage can re-write the request, or decide to
#  cancel the proxy.
#
#  Only a few modules currently have this method.
#
pre-proxy {
#	attr_rewrite

	#  Uncomment the following line if you want to change attributes
	#  as defined in the preproxy_users file.
#	files

	#  Uncomment the following line if you want to filter requests
	#  sent to remote servers based on the rules defined in the
	#  'attrs.pre-proxy' file.
	attr_filter.pre-proxy

	#  If you want to have a log of packets proxied to a home
	#  server, un-comment the following line, and the
	#  'detail pre_proxy_log' section, above.
#	pre_proxy_log
}

#
#  When the server receives a reply to a request it proxied
#  to a home server, the request may be massaged here, in the
#  post-proxy stage.
#
post-proxy {

	#  If you want to have a log of replies from a home server,
	#  un-comment the following line, and the 'detail post_proxy_log'
	#  section, above.
#	post_proxy_log

#	attr_rewrite

	#  Uncomment the following line if you want to filter replies from
	#  remote proxies based on the rules defined in the 'attrs' file.
	attr_filter.post-proxy

	#
	#  If you are proxying LEAP, you MUST configure the EAP
	#  module, and you MUST list it here, in the post-proxy
	#  stage.
	#
	#  You MUST also use the 'nostrip' option in the 'realm'
	#  configuration.  Otherwise, the User-Name attribute
	#  in the proxied request will not match the user name
	#  hidden inside of the EAP packet, and the end server will
	#  reject the EAP request.
	#
	eap

	#
	#  If the server tries to proxy a request and fails, then the
	#  request is processed through the modules in this section.
	#
	#  The main use of this section is to permit robust proxying
	#  of accounting packets.  The server can be configured to
	#  proxy accounting packets as part of normal processing.
	#  Then, if the home server goes down, accounting packets can
	#  be logged to a local "detail" file, for processing with
	#  radrelay.  When the home server comes back up, radrelay
	#  will read the detail file, and send the packets to the
	#  home server.
	#
	#  With this configuration, the server always responds to
	#  Accounting-Requests from the NAS, but only writes
	#  accounting packets to disk if the home server is down.
	#
#	Post-Proxy-Type Fail {
#			detail
#	}
}
EOD;

	$filename = FREERADIUS_ETC . '/raddb/sites-available/default';
	conf_mount_rw();
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
	conf_mount_ro();
	
	// No need to restart here because the restart of the service will be done in "freeradius_settings_resync"
	// restart_service('radiusd');
}

function freeradius_cacertcnf_resync() {
	global $config;
	$conf = '';

	$arrcerts = $config['installedpackages']['freeradiuscerts']['config'][0];
	
	// General variables: CA, Server, Client
	$varcertsdefaultdays = ($arrcerts['varcertsdefaultdays']?$arrcerts['varcertsdefaultdays']:'3650');
	$varcertsdefaultmd = ($arrcerts['varcertsdefaultmd']?$arrcerts['varcertsdefaultmd']:'md5');
	$varcertsdefaultbits = ($arrcerts['varcertsdefaultbits']?$arrcerts['varcertsdefaultbits']:'2048');
	$varcertspassword = ($arrcerts['varcertspassword']?$arrcerts['varcertspassword']:'whatever');
	$varcertscountryname = ($arrcerts['varcertscountryname']?$arrcerts['varcertscountryname']:'US');
	$varcertsstateorprovincename = ($arrcerts['varcertsstateorprovincename']?$arrcerts['varcertsstateorprovincename']:'Texas');
	$varcertslocalityname = ($arrcerts['varcertslocalityname']?$arrcerts['varcertslocalityname']:'Austin');
	$varcertsorganizationname = ($arrcerts['varcertsorganizationname']?$arrcerts['varcertsorganizationname']:'My Company Inc');

	// Variables: Only for CA
	$varcertscaemailaddress = ($arrcerts['varcertscaemailaddress']?$arrcerts['varcertscaemailaddress']:'admin@mycompany.com');
	$varcertscacommonname = ($arrcerts['varcertscacommonname']?$arrcerts['varcertscacommonname']:'internal-ca');
	
	
	

	$conf .= <<<EOD
[ ca ]
default_ca		= CA_default

[ CA_default ]
dir			= ./
certs			= \$dir
crl_dir			= \$dir/crl
database		= \$dir/index.txt
new_certs_dir		= \$dir
certificate		= \$dir/ca.pem
serial			= \$dir/serial
crl			= \$dir/crl.pem
private_key		= \$dir/ca.key
RANDFILE		= \$dir/.rand
name_opt		= ca_default
cert_opt		= ca_default
default_days		= $varcertsdefaultdays
default_crl_days	= 30
default_md		= $varcertsdefaultmd
preserve		= no
policy			= policy_match

[ policy_match ]
countryName		= match
stateOrProvinceName	= match
organizationName	= match
organizationalUnitName	= optional
commonName		= supplied
emailAddress		= optional

[ policy_anything ]
countryName		= optional
stateOrProvinceName	= optional
localityName		= optional
organizationName	= optional
organizationalUnitName	= optional
commonName		= supplied
emailAddress		= optional

[ req ]
prompt			= no
distinguished_name	= certificate_authority
default_bits		= $varcertsdefaultbits
input_password		= $varcertspassword
output_password		= $varcertspassword
x509_extensions		= v3_ca

[certificate_authority]
countryName		= $varcertscountryname
stateOrProvinceName	= $varcertsstateorprovincename
localityName		= $varcertslocalityname
organizationName	= $varcertsorganizationname
emailAddress		= $varcertscaemailaddress
commonName		= "$varcertscacommonname"

[v3_ca]
subjectKeyIdentifier	= hash
authorityKeyIdentifier	= keyid:always,issuer:always
basicConstraints	= CA:true
EOD;

	$filename = FREERADIUS_ETC . '/raddb/certs/ca.cnf';
	conf_mount_rw();
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
	conf_mount_ro();

}

function freeradius_servercertcnf_resync() {
	global $config;
	$conf = '';

	$arrcerts = $config['installedpackages']['freeradiuscerts']['config'][0];
	
	// General variables: CA, Server, Client
	$varcertsdefaultdays = ($arrcerts['varcertsdefaultdays']?$arrcerts['varcertsdefaultdays']:'3650');
	$varcertsdefaultmd = ($arrcerts['varcertsdefaultmd']?$arrcerts['varcertsdefaultmd']:'md5');
	$varcertsdefaultbits = ($arrcerts['varcertsdefaultbits']?$arrcerts['varcertsdefaultbits']:'2048');
	$varcertspassword = ($arrcerts['varcertspassword']?$arrcerts['varcertspassword']:'whatever');
	$varcertscountryname = ($arrcerts['varcertscountryname']?$arrcerts['varcertscountryname']:'US');
	$varcertsstateorprovincename = ($arrcerts['varcertsstateorprovincename']?$arrcerts['varcertsstateorprovincename']:'Texas');
	$varcertslocalityname = ($arrcerts['varcertslocalityname']?$arrcerts['varcertslocalityname']:'Austin');
	$varcertsorganizationname = ($arrcerts['varcertsorganizationname']?$arrcerts['varcertsorganizationname']:'My Company Inc');

	// Variables: Only for Server
	$varcertsserveremailaddress = ($arrcerts['varcertsserveremailaddress']?$arrcerts['varcertsserveremailaddress']:'webadmin@mycompany.com');
	$varcertsservercommonname = ($arrcerts['varcertsservercommonname']?$arrcerts['varcertsservercommonname']:'server-cert');
	

	$conf .= <<<EOD
[ ca ]
default_ca		= CA_default

[ CA_default ]
dir			= ./
certs			= \$dir
crl_dir			= \$dir/crl
database		= \$dir/index.txt
new_certs_dir		= \$dir
certificate		= \$dir/server.pem
serial			= \$dir/serial
crl			= \$dir/crl.pem
private_key		= \$dir/server.key
RANDFILE		= \$dir/.rand
name_opt		= ca_default
cert_opt		= ca_default
default_days		= $varcertsdefaultdays
default_crl_days	= 30
default_md		= $varcertsdefaultmd
preserve		= no
policy			= policy_match

[ policy_match ]
countryName		= match
stateOrProvinceName	= match
organizationName	= match
organizationalUnitName	= optional
commonName		= supplied
emailAddress		= optional

[ policy_anything ]
countryName		= optional
stateOrProvinceName	= optional
localityName		= optional
organizationName	= optional
organizationalUnitName	= optional
commonName		= supplied
emailAddress		= optional

[ req ]
prompt			= no
distinguished_name	= server
default_bits		= $varcertsdefaultbits
input_password		= $varcertspassword
output_password		= $varcertspassword

[server]
countryName		= $varcertscountryname
stateOrProvinceName	= $varcertsstateorprovincename
localityName		= $varcertslocalityname
organizationName	= $varcertsorganizationname
emailAddress		= $varcertsserveremailaddress
commonName		= "$varcertsservercommonname"
EOD;

	$filename = FREERADIUS_ETC . '/raddb/certs/server.cnf';
	conf_mount_rw();
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
	conf_mount_ro();

}

function freeradius_clientcertcnf_resync() {
	global $config;
	$conf = '';

	$arrcerts = $config['installedpackages']['freeradiuscerts']['config'][0];
	
	// General variables: CA, Server, Client
	$varcertsdefaultdays = ($arrcerts['varcertsdefaultdays']?$arrcerts['varcertsdefaultdays']:'3650');
	$varcertsdefaultmd = ($arrcerts['varcertsdefaultmd']?$arrcerts['varcertsdefaultmd']:'md5');
	$varcertsdefaultbits = ($arrcerts['varcertsdefaultbits']?$arrcerts['varcertsdefaultbits']:'2048');
	$varcertspassword = ($arrcerts['varcertspassword']?$arrcerts['varcertspassword']:'whatever');
	$varcertscountryname = ($arrcerts['varcertscountryname']?$arrcerts['varcertscountryname']:'US');
	$varcertsstateorprovincename = ($arrcerts['varcertsstateorprovincename']?$arrcerts['varcertsstateorprovincename']:'Texas');
	$varcertslocalityname = ($arrcerts['varcertslocalityname']?$arrcerts['varcertslocalityname']:'Austin');
	$varcertsorganizationname = ($arrcerts['varcertsorganizationname']?$arrcerts['varcertsorganizationname']:'My Company Inc');

	// Variables: Only for Client
	$varcertsclientemailaddress = ($arrcerts['varcertsclientemailaddress']?$arrcerts['varcertsclientemailaddress']:'user@mycompany.com');
	$varcertsclientcommonname = ($arrcerts['varcertsclientcommonname']?$arrcerts['varcertsclientcommonname']:'client-cert');
	

	$conf .= <<<EOD
[ ca ]
default_ca		= CA_default

[ CA_default ]
dir			= ./
certs			= \$dir
crl_dir			= \$dir/crl
database		= \$dir/index.txt
new_certs_dir		= \$dir
certificate		= \$dir/server.pem
serial			= \$dir/serial
crl			= \$dir/crl.pem
private_key		= \$dir/server.key
RANDFILE		= \$dir/.rand
name_opt		= ca_default
cert_opt		= ca_default
default_days		= $varcertsdefaultdays
default_crl_days	= 30
default_md		= $varcertsdefaultmd
preserve		= no
policy			= policy_match

[ policy_match ]
countryName		= match
stateOrProvinceName	= match
organizationName	= match
organizationalUnitName	= optional
commonName		= supplied
emailAddress		= optional

[ policy_anything ]
countryName		= optional
stateOrProvinceName	= optional
localityName		= optional
organizationName	= optional
organizationalUnitName	= optional
commonName		= supplied
emailAddress		= optional

[ req ]
prompt			= no
distinguished_name	= client
default_bits		= $varcertsdefaultbits
input_password		= $varcertspassword
output_password		= $varcertspassword

[client]
countryName		= $varcertscountryname
stateOrProvinceName	= $varcertsstateorprovincename
localityName		= $varcertslocalityname
organizationName	= $varcertsorganizationname
emailAddress		= $varcertsclientemailaddress
commonName		= "$varcertsclientcommonname"
EOD;

	$filename = FREERADIUS_ETC . '/raddb/certs/client.cnf';
	conf_mount_rw();
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
	conf_mount_ro();

}

function freeradius_allcertcnf_resync() {
	global $config;
		// We need to make this write enabled for embedded systems to write certs
		conf_mount_rw();
	
	
// Only proceed these steps if freeRADIUS Cert-Manager is activated. if pfSense cert manager is used skip this.
$eapconf = $config['installedpackages']['freeradiuseapconf']['config'][0];
if ($eapconf['vareapconfchoosecertmanager'] == '') {
	

	$arrcerts = $config['installedpackages']['freeradiuscerts']['config'][0];
	
	// General variable for deleting and generation of further Client-Cert
	$varcertscreateclient = ($arrcerts['varcertscreateclient']?$arrcerts['varcertscreateclient']:'no');
	
	// General variables for deleting: CA, Server, Client
	$varcertsdeleteall = ($arrcerts['varcertsdeleteall']?$arrcerts['varcertsdeleteall']:'no');
		
	// If all certs should be deleted, we do not need to delete and recreate client-certs first.
	if ($arrcerts['varcertsdeleteall'] == 'no') {

		if ($arrcerts['varcertscreateclient'] == 'yes') {
		
		// delete all old certificates and keys
		log_error("freeRADIUS: deleting all client.csr .crt .key .pem .tar in " . FREERADIUS_ETC . "/raddb/certs");
		exec("rm -f " . FREERADIUS_ETC . "/raddb/certs/client.csr");
		exec("rm -f " . FREERADIUS_ETC . "/raddb/certs/client.crt");
		exec("rm -f " . FREERADIUS_ETC . "/raddb/certs/client.key");
		exec("rm -f " . FREERADIUS_ETC . "/raddb/certs/client.pem");
		exec("rm -f " . FREERADIUS_ETC . "/raddb/certs/client.tar");
				
			
		// run fuction to create ONLY new client.cnf files based on user input from freeradiuscert.xml
		freeradius_clientcertcnf_resync();
	
	
		// make bootstrap executable and run to create cert based on client.cnf files
		exec("chmod 0770 " . FREERADIUS_ETC . "/raddb/certs/bootstrap");
		exec(FREERADIUS_ETC . "/raddb/certs/bootstrap");
			
		// rename client generated XX.pem to client.pem // use regex to replace spaces and so on.
		$varserial = preg_replace("/\s/","",file_get_contents(FREERADIUS_ETC . '/raddb/certs/serial.old'));
		if (file_exists(FREERADIUS_ETC . "/raddb/certs/$varserial.pem"))
		rename(FREERADIUS_ETC . "/raddb/certs/$varserial.pem",FREERADIUS_ETC . "/raddb/certs/client.pem");
		
		
		// tar client-cert files
		exec("cd " . FREERADIUS_ETC . "/raddb/certs && tar -cf client.tar client.crt client.csr client.key ca.der client.pem");
		
		// Make all files in certs folder read/write only for root
		exec("chmod -R 0600 " . FREERADIUS_ETC . "/raddb/certs/");
		log_error("freeRADIUS: Created new client.csr .crt .key .pem and added them together with ca.der in " . FREERADIUS_ETC . "/raddb/certs/client.tar");
		}
	}
	else {
		
		if ($arrcerts['varcertsdeleteall'] == 'yes') {
	
		// delete all old certificates and keys - deletes certs from pfsense cert-manager IN THIS FOLDER, too.
		log_error("freeRADIUS: deleting all CA, Server and Client certs, DH, random and database files in " . FREERADIUS_ETC . "/raddb/certs");
		exec("rm -f " . FREERADIUS_ETC . "/raddb/certs/ca.pem && rm -f " . FREERADIUS_ETC . "/raddb/certs/server.pem && rm -f " . FREERADIUS_ETC . "/raddb/certs/client.pem");
		exec("rm -f " . FREERADIUS_ETC . "/raddb/certs/ca.der && rm -f " . FREERADIUS_ETC . "/raddb/certs/server.der && rm -f " . FREERADIUS_ETC . "/raddb/certs/client.der");
		exec("rm -f " . FREERADIUS_ETC . "/raddb/certs/ca.csr && rm -f " . FREERADIUS_ETC . "/raddb/certs/server.csr && rm -f " . FREERADIUS_ETC . "/raddb/certs/client.csr");
		exec("rm -f " . FREERADIUS_ETC . "/raddb/certs/ca.crt && rm -f " . FREERADIUS_ETC . "/raddb/certs/server.crt && rm -f " . FREERADIUS_ETC . "/raddb/certs/client.crt");
		exec("rm -f " . FREERADIUS_ETC . "/raddb/certs/ca.key && rm -f " . FREERADIUS_ETC . "/raddb/certs/server.key && rm -f " . FREERADIUS_ETC . "/raddb/certs/client.key");
		exec("rm -f " . FREERADIUS_ETC . "/raddb/certs/ca.p12 && rm -f " . FREERADIUS_ETC . "/raddb/certs/server.p12 && rm -f " . FREERADIUS_ETC . "/raddb/certs/client.p12");
		exec("rm -f " . FREERADIUS_ETC . "/raddb/certs/serial*");
		exec("rm -f " . FREERADIUS_ETC . "/raddb/certs/index*");
		exec("rm -f " . FREERADIUS_ETC . "/raddb/certs/dh");
		exec("rm -f " . FREERADIUS_ETC . "/raddb/certs/random");
		exec("rm -f " . FREERADIUS_ETC . "/raddb/certs/client.tar");
		
	
		// run fuctions to create new .cnf files based on user input from freeradiuscert.xml
		freeradius_cacertcnf_resync();
		freeradius_servercertcnf_resync();
		freeradius_clientcertcnf_resync();
		
		// this command deletes the pfsense_cert_mgr checkfile so when we change back to pfsense cert manager a new DH + random file will be created
		if (file_exists(FREERADIUS_ETC . "/raddb/certs/pfsense_cert_mgr")) {
			unlink(FREERADIUS_ETC . "/raddb/certs/pfsense_cert_mgr");
		}
		
		// generate new DH and RANDOM file
		log_error("freeRADIUS: Creating new DH and random file in " . FREERADIUS_ETC . "/raddb/certs");
		exec("cd " . FREERADIUS_ETC . "/raddb/certs && openssl dhparam -out dh 1024");
		exec("cd " . FREERADIUS_ETC . "/raddb/certs && dd if=/dev/urandom of=./random count=10");
		
		log_error("freeRADIUS: Creating new CA, Server and Client certs in " . FREERADIUS_ETC . "/raddb/certs");
		// make bootstrap executable and run to create certs based on .cnf files
		exec("chmod 0770 " . FREERADIUS_ETC . "/raddb/certs/bootstrap");
		exec(FREERADIUS_ETC . "/raddb/certs/bootstrap");
		
		// rename client generated 02.pem to client.pem
		if (file_exists(FREERADIUS_ETC . "/raddb/certs/02.pem"))
		rename(FREERADIUS_ETC . "/raddb/certs/02.pem",FREERADIUS_ETC . "/raddb/certs/client.pem");
		
		// tar client-cert files
		exec("cd " . FREERADIUS_ETC . "/raddb/certs && tar -cf client.tar client.crt client.csr client.key ca.der client.pem");
		exec("chmod -R 0600 " . FREERADIUS_ETC . "/raddb/certs/");
		log_error("freeRADIUS: Added client.csr .crt .key .pem together with ca.der in " . FREERADIUS_ETC . "/raddb/certs/client.tar");
		
		// If there were changes on the certificates we need to restart freeradius
		restart_service('radiusd');
		}
	}
} //end choose pfSense cert-manager
else {
	return;
}
// Read-only because of embedded systems
conf_mount_ro();
} //end of function

// ##### The following part is based on the code of pfblocker #####

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

/* Do the actual XMLRPC sync */
function freeradius_do_xmlrpc_sync($sync_to_ip, $username, $password, $varsyncport, $varsyncprotocol, $varsynctimeout) {
	global $config, $g;

	/* Detect boot process, do nothing during boot. */
	if (function_exists("platform_booting")) {
		if (platform_booting()) {
			return;
		}
	} elseif ($g['booting']) {
		return;
	}

	if ($username == "" || $password == "" || $sync_to_ip == "" || $varsyncport == "" || $varsyncprotocol == "") {
		log_error("[FreeRADIUS]: A required XMLRPC sync parameter (username, password, replication target, port or protocol) is empty ... aborting pkg sync");
		return;
	}

	/* Take care of IPv6 literal address */
	if (is_ipaddrv6($sync_to_ip)) {
		$sync_to_ip = "[{$sync_to_ip}]";
	}

	$url = "{$varsyncprotocol}://{$sync_to_ip}";
	$port = $varsyncport;

	/* XML will hold the sections to sync. */
	$xml = array();
	$xml['freeradius'] = $config['installedpackages']['freeradius'];
	$xml['freeradiusauthorizedmacs'] = $config['installedpackages']['freeradiusauthorizedmacs'];
	$xml['freeradiusclients'] = $config['installedpackages']['freeradiusclients'];
	
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
	$execcmd  = "require_once('/usr/local/pkg/freeradius.inc');\n";
	/* pfblocker just needed one fuction to reload after XMLRPC. FreeRADIUS needs more so we point to a fuction below which contains all fuctions */
	$execcmd .= "freeradius_all_after_XMLRPC_resync();";
	
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

// This function restarts all other needed functions after XMLRPC so that the content of .XML + .INC will be written in the files (clients.conf, users)
// Adding more functions will increase the to sync
function freeradius_all_after_XMLRPC_resync() {
	
	freeradius_users_resync();
	freeradius_authorizedmacs_resync();
	freeradius_clients_resync();
	
	log_error("[FreeRADIUS]: Finished XMLRPC process. It should be OK. For more information look at the host which started sync.");
	
	exec(FREERADIUS_ETC . "/rc.d/radiusd onerestart");
}

function freeradius_modulescounter_resync() {
	global $config;
	$conf = '';

	$conf .= <<<EOD
# -*- text -*-
#
#  $Id$

#  counter module:
#  This module takes an attribute (count-attribute).
#  It also takes a key, and creates a counter for each unique
#  key.  The count is incremented when accounting packets are
#  received by the server.  The value of the increment depends
#  on the attribute type.
#  If the attribute is Acct-Session-Time or of an integer type we add
#  the value of the attribute. If it is anything else we increase the
#  counter by one.
#
#  The 'reset' parameter defines when the counters are all reset to
#  zero.  It can be hourly, daily, weekly, monthly or never.
#
#  hourly: Reset on 00:00 of every hour
#  daily: Reset on 00:00:00 every day
#  weekly: Reset on 00:00:00 on sunday
#  monthly: Reset on 00:00:00 of the first day of each month
#
#  It can also be user defined. It should be of the form:
#  num[hdwm] where:
#  h: hours, d: days, w: weeks, m: months
#  If the letter is ommited days will be assumed. In example:
#  reset = 10h (reset every 10 hours)
#  reset = 12  (reset every 12 days)
#
#
#  The check-name attribute defines an attribute which will be
#  registered by the counter module and can be used to set the
#  maximum allowed value for the counter after which the user
#  is rejected.
#  Something like:
#
#  DEFAULT Max-Daily-Session := 36000
#          Fall-Through = 1
#
#  You should add the counter module in the instantiate
#  section so that it registers check-name before the files
#  module reads the users file.
#
#  If check-name is set and the user is to be rejected then we
#  send back a Reply-Message and we log a Failure-Message in
#  the radius.log
#
#  If the count attribute is Acct-Session-Time then on each
#  login we send back the remaining online time as a
#  Session-Timeout attribute ELSE and if the reply-name is
#  set, we send back that attribute.  The reply-name attribute
#  MUST be of an integer type.
#
#  The counter-name can also be used instead of using the check-name
#  like below:
#
#  DEFAULT  Daily-Session-Time > 3600, Auth-Type = Reject
#      Reply-Message = "You've used up more than one hour today"
#
#  The allowed-servicetype attribute can be used to only take
#  into account specific sessions. For example if a user first
#  logs in through a login menu and then selects ppp there will
#  be two sessions. One for Login-User and one for Framed-User
#  service type. We only need to take into account the second one.
#
#  The module should be added in the instantiate, authorize and
#  accounting sections.  Make sure that in the authorize
#  section it comes after any module which sets the
#  'check-name' attribute.
#
counter daily {
	filename = /var/log/radacct/timecounter/db.daily
	key = User-Name
	count-attribute = Acct-Session-Time
	reset = daily
	counter-name = Daily-Session-Time
	check-name = Max-Daily-Session
	reply-name = Session-Timeout
	cache-size = 5000
}

counter weekly {
	filename = /var/log/radacct/timecounter/db.weekly
	key = User-Name
	count-attribute = Acct-Session-Time
	reset = weekly
	counter-name = Weekly-Session-Time
	check-name = Max-Weekly-Session
	reply-name = Session-Timeout
	cache-size = 5000
}

counter monthly {
	filename = /var/log/radacct/timecounter/db.monthly
	key = User-Name
	count-attribute = Acct-Session-Time
	reset = monthly
	counter-name = Monthly-Session-Time
	check-name = Max-Monthly-Session
	reply-name = Session-Timeout
	cache-size = 5000
}

counter forever {
	filename = /var/log/radacct/timecounter/db.forever
	key = User-Name
	count-attribute = Acct-Session-Time
	reset = never
	counter-name = Forever-Session-Time
	check-name = Max-Forever-Session
	reply-name = Session-Timeout
	cache-size = 5000
}
EOD;

	$filename = FREERADIUS_ETC . '/raddb/modules/counter';
	conf_mount_rw();
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
	conf_mount_ro();

}

function freeradius_modulesmschap_resync() {
	global $config;
	$conf = '';

	$conf .= <<<EOD
# -*- text -*-
#
#  $Id$

# Microsoft CHAP authentication
#
#  This module supports MS-CHAP and MS-CHAPv2 authentication.
#  It also enforces the SMB-Account-Ctrl attribute.
#
mschap {
        #
        #  If you are using /etc/smbpasswd, see the 'passwd'
        #  module for an example of how to use /etc/smbpasswd

        # if use_mppe is not set to no mschap will
        # add MS-CHAP-MPPE-Keys for MS-CHAPv1 and
        # MS-MPPE-Recv-Key/MS-MPPE-Send-Key for MS-CHAPv2
        #
#       use_mppe = no

        # if mppe is enabled require_encryption makes
        # encryption moderate
        #
#       require_encryption = yes

        # require_strong always requires 128 bit key
        # encryption
        #
#       require_strong = yes

        # Windows sends us a username in the form of
        # DOMAIN\user, but sends the challenge response
        # based on only the user portion.  This hack
        # corrects for that incorrect behavior.
        #
		with_ntdomain_hack = yes

        # The module can perform authentication itself, OR
        # use a Windows Domain Controller.  This configuration
        # directive tells the module to call the ntlm_auth
        # program, which will do the authentication, and return
        # the NT-Key.  Note that you MUST have "winbindd" and
        # "nmbd" running on the local machine for ntlm_auth
        # to work.  See the ntlm_auth program documentation
        # for details.
        #
        # If ntlm_auth is configured below, then the mschap
        # module will call ntlm_auth for every MS-CHAP
        # authentication request.  If there is a cleartext
        # or NT hashed password available, you can set
        # "MS-CHAP-Use-NTLM-Auth := No" in the control items,
        # and the mschap module will do the authentication itself,
        # without calling ntlm_auth.
        #
        # Be VERY careful when editing the following line!
        #
        # You can also try setting the user name as:
        #
        #       ... --username=%{mschap:User-Name} ...
        #
        # In that case, the mschap module will look at the User-Name
        # attribute, and do prefix/suffix checks in order to obtain
        # the "best" user name for the request.
        #
#       ntlm_auth = "/path/to/ntlm_auth --request-nt-key --username=%{%{Stripped-User-Name}:-%{%{User-Name}:-None}} --challenge=%{%{mschap:Challenge}:-00} --
nt-response=%{%{mschap:NT-Response}:-00}"

        # For Apple Server, when running on the same machine as
        # Open Directory.  It has no effect on other systems.
        #
#       use_open_directory = yes

        # On failure, set (or not) the MS-CHAP error code saying
        # "retries allowed".
#       allow_retry = yes

        # An optional retry message.
#       retry_msg = "Re-enter (or reset) the password"
}
EOD;

	$filename = FREERADIUS_ETC . '/raddb/modules/mschap';
	conf_mount_rw();
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
	conf_mount_ro();

}

function freeradius_modulesrealm_resync() {
	global $config;
	$conf = '';

	$conf .= <<<EOD
#  'realm/username'
#  Using this entry, IPASS users have their realm set to "IPASS".
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
#  'domain\user'
realm ntdomain {
		format = prefix
		### 3 backslash in .inc will be 2 backslash in file and after starting radiusd just only one
		delimiter = "\\\"
		ignore_null = yes
		ignore_default = no
}
EOD;

	$filename = FREERADIUS_ETC . '/raddb/modules/realm';
	conf_mount_rw();
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
	conf_mount_ro();

}

function freeradius_modulesldap_resync() {
	global $config;
	$conf = '';
	
	$arrmodulesldap = $config['installedpackages']['freeradiusmodulesldap']['config'][0];
	
	// Enable and Disable LDAP for "authorize" and "authenticate" will be done in "freeradius_serverdefault_resync"
	// redundatnt-load-balancing will there be done, too
	
	
	// Variables for General Configuration ldap1
	$varmodulesldapserver = ($arrmodulesldap['varmodulesldapserver']?$arrmodulesldap['varmodulesldapserver']:'ldap.your.domain');
	$varmodulesldapserverport = ($arrmodulesldap['varmodulesldapserverport']?$arrmodulesldap['varmodulesldapserverport']:'389');
	$varmodulesldapidentity = ($arrmodulesldap['varmodulesldapidentity']?$arrmodulesldap['varmodulesldapidentity']:'cn=admin,o=My Org,c=UA');
	$varmodulesldappassword = ($arrmodulesldap['varmodulesldappassword']?$arrmodulesldap['varmodulesldappassword']:'mypass');
	$varmodulesldapbasedn = ($arrmodulesldap['varmodulesldapbasedn']?$arrmodulesldap['varmodulesldapbasedn']:'o=My Org,c=UA');
	$varmodulesldapfilter = ($arrmodulesldap['varmodulesldapfilter']?$arrmodulesldap['varmodulesldapfilter']:'(uid=%{%{Stripped-User-Name}:-%{User-Name}})');
	$varmodulesldapbasefilter = ($arrmodulesldap['varmodulesldapbasefilter']?$arrmodulesldap['varmodulesldapbasefilter']:'(objectclass=radiusprofile)');
	$varmodulesldapldapconnectionsnumber = ($arrmodulesldap['varmodulesldapldapconnectionsnumber']?$arrmodulesldap['varmodulesldapldapconnectionsnumber']:'5');
	$varmodulesldaptimeout = ($arrmodulesldap['varmodulesldaptimeout']?$arrmodulesldap['varmodulesldaptimeout']:'4');
	$varmodulesldaptimelimit = ($arrmodulesldap['varmodulesldaptimelimit']?$arrmodulesldap['varmodulesldaptimelimit']:'3');
	$varmodulesldapnettimeout = ($arrmodulesldap['varmodulesldapnettimeout']?$arrmodulesldap['varmodulesldapnettimeout']:'1');
	
	// Variables for General Configuration ldap2
	$varmodulesldap2server = ($arrmodulesldap['varmodulesldap2server']?$arrmodulesldap['varmodulesldap2server']:'ldap.your.domain');
	$varmodulesldap2serverport = ($arrmodulesldap['varmodulesldap2serverport']?$arrmodulesldap['varmodulesldap2serverport']:'389');
	$varmodulesldap2identity = ($arrmodulesldap['varmodulesldap2identity']?$arrmodulesldap['varmodulesldap2identity']:'cn=admin,o=My Org,c=UA');
	$varmodulesldap2password = ($arrmodulesldap['varmodulesldap2password']?$arrmodulesldap['varmodulesldap2password']:'mypass');
	$varmodulesldap2basedn = ($arrmodulesldap['varmodulesldap2basedn']?$arrmodulesldap['varmodulesldap2basedn']:'o=My Org,c=UA');
	$varmodulesldap2filter = ($arrmodulesldap['varmodulesldap2filter']?$arrmodulesldap['varmodulesldap2filter']:'(uid=%{%{Stripped-User-Name}:-%{User-Name}})');
	$varmodulesldap2basefilter = ($arrmodulesldap['varmodulesldap2basefilter']?$arrmodulesldap['varmodulesldap2basefilter']:'(objectclass=radiusprofile)');
	$varmodulesldap2ldapconnectionsnumber = ($arrmodulesldap['varmodulesldap2ldapconnectionsnumber']?$arrmodulesldap['varmodulesldap2ldapconnectionsnumber']:'5');
	$varmodulesldap2timeout = ($arrmodulesldap['varmodulesldap2timeout']?$arrmodulesldap['varmodulesldap2timeout']:'4');
	$varmodulesldap2timelimit = ($arrmodulesldap['varmodulesldap2timelimit']?$arrmodulesldap['varmodulesldap2timelimit']:'3');
	$varmodulesldap2nettimeout = ($arrmodulesldap['varmodulesldap2nettimeout']?$arrmodulesldap['varmodulesldap2nettimeout']:'1');	
	
	// Variables for TLS / Certificates - ldap1
	$varmodulesldaprequirecert = ($arrmodulesldap['varmodulesldaprequirecert']?$arrmodulesldap['varmodulesldaprequirecert']:'never');

// if enabled then create the certs in ../raddb/certs/ and enable "Start_tls" in ldap1 module
if($arrmodulesldap['varmodulesldapenabletlssupport'] == 'on') {
		
		$ca_cert = lookup_ca($arrmodulesldap["ssl_ca_cert1"]);
		if ($ca_cert != false) {
			if(base64_decode($ca_cert['prv'])) {
				file_put_contents(FREERADIUS_ETC . "/raddb/certs/ca_ldap1_key.pem", 
					base64_decode($ca_cert['prv']));
				$conf['ssl_ca_key'] = FREERADIUS_ETC . '/raddb/certs/ca_ldap1_key.pem';
			}


			if(base64_decode($ca_cert['crt'])) {
				file_put_contents(FREERADIUS_ETC . "/raddb/certs/ca_ldap1_cert.pem", 
					base64_decode($ca_cert['crt']));
				$conf['ssl_ca_cert1'] = FREERADIUS_ETC . "/raddb/certs/ca_ldap1_cert.pem";
			}


			$svr_cert = lookup_cert($arrmodulesldap["ssl_server_cert1"]);
			if ($svr_cert != false) {
				if(base64_decode($svr_cert['prv'])) {
					file_put_contents(FREERADIUS_ETC . "/raddb/certs/radius_ldap1_cert.key", 
						base64_decode($svr_cert['prv']));
					$conf['ssl_key'] = FREERADIUS_ETC . '/raddb/certs/radius_ldap1_cert.key';
				}
			}


			if(base64_decode($svr_cert['crt'])) {
				file_put_contents(FREERADIUS_ETC . "/raddb/certs/radius_ldap1_cert.crt", 
					base64_decode($svr_cert['crt']));
				$conf['ssl_server_cert1'] = FREERADIUS_ETC . "/raddb/certs/radius_ldap1_cert.crt";
			}


			$conf['ssl_cert_dir'] = FREERADIUS_ETC . '/raddb/certs';
		}
	$varmodulesldapstarttls = "yes";
}
else {
	$varmodulesldapstarttls = "no";
}
	
	// Variables for TLS / Certificates - ldap2
	$varmodulesldap2requirecert = ($arrmodulesldap['varmodulesldap2requirecert']?$arrmodulesldap['varmodulesldap2requirecert']:'never');

// if enabled then create the certs in ../raddb/certs/ and enable "Start_tls" in ldap2 module
if($arrmodulesldap['varmodulesldap2enabletlssupport'] == 'on') {	
		
		$ca_cert = lookup_ca($arrmodulesldap["ssl_ca_cert2"]);
		if ($ca_cert != false) {
			if(base64_decode($ca_cert['prv'])) {
				file_put_contents(FREERADIUS_ETC . "/raddb/certs/ca_ldap2_key.pem", 
					base64_decode($ca_cert['prv']));
				$conf['ssl_ca_key'] = FREERADIUS_ETC . '/raddb/certs/ca_ldap2_key.pem';
			}


			if(base64_decode($ca_cert['crt'])) {
				file_put_contents(FREERADIUS_ETC . "/raddb/certs/ca_ldap2_cert.pem", 
					base64_decode($ca_cert['crt']));
				$conf['ssl_ca_cert2'] = FREERADIUS_ETC . "/raddb/certs/ca_ldap2_cert.pem";
			}


			$svr_cert = lookup_cert($arrmodulesldap["ssl_server_cert2"]);
			if ($svr_cert != false) {
				if(base64_decode($svr_cert['prv'])) {
					file_put_contents(FREERADIUS_ETC . "/raddb/certs/radius_ldap2_cert.key", 
						base64_decode($svr_cert['prv']));
					$conf['ssl_key'] = FREERADIUS_ETC . '/raddb/certs/radius_ldap2_cert.key';
				}
			}


			if(base64_decode($svr_cert['crt'])) {
				file_put_contents(FREERADIUS_ETC . "/raddb/certs/radius_ldap2_cert.crt", 
					base64_decode($svr_cert['crt']));
				$conf['ssl_server_cert2'] = FREERADIUS_ETC . "/raddb/certs/radius_ldap2_cert.crt";
			}


			$conf['ssl_cert_dir'] = FREERADIUS_ETC . '/raddb/certs';
		}
	$varmodulesldap2starttls = "yes";
}
else {
	$varmodulesldap2starttls = "no";
}	

	// Miscellaneous Configuration + MS Active Directory Compatibility ldap1
	$varmodulesldapmsadcompatibilityenable = ($arrmodulesldap['varmodulesldapmsadcompatibilityenable']?$arrmodulesldap['varmodulesldapmsadcompatibilityenable']:'Disable');
	if ($arrmodulesldap['varmodulesldapmsadcompatibilityenable'] == 'Disable') {
		$varmodulesldapmsadcompatibility = '### MS Active Directory Compatibility is disabled ###';
	}
	else {
		$varmodulesldapmsadcompatibility = 'chase_referrals = yes' . "\n\trebind = yes";
	}
	
	// Miscellaneous Configuration + MS Active Directory Compatibility ldap2
	$varmodulesldap2msadcompatibilityenable = ($arrmodulesldap['varmodulesldap2msadcompatibilityenable']?$arrmodulesldap['varmodulesldap2msadcompatibilityenable']:'Disable');
	if ($arrmodulesldap['varmodulesldap2msadcompatibilityenable'] == 'Disable') {
		$varmodulesldap2msadcompatibility = '### MS Active Directory Compatibility is disabled ###';
	}
	else {
		$varmodulesldap2msadcompatibility = 'chase_referrals = yes' . "\n\trebind = yes";
	}

	// When disabled we put this in the file but commented (#) like in the default installation ldap1
	if (!$arrmodulesldap['varmodulesldapdmiscenable']) {
		$varmodulesldapdefaultprofile = '### default_profile = "cn=radprofile,ou=dialup,o=My Org,c=UA" ###';
		$varmodulesldapprofileattribute = '### profile_attribute = "radiusProfileDn" ###';
		$varmodulesldapaccessattr = '### access_attr = "dialupAccess" ###';
	}
	// When enabled we put in the default values so there is no empty entry if there is not input from GUI
	else {
		$varmodulesldapdefaultprofile = ($arrmodulesldap['varmodulesldapdefaultprofile']?$arrmodulesldap['varmodulesldapdefaultprofile']:'cn=radprofile,ou=dialup,o=My Org,c=UA');
		$varmodulesldapdefaultprofile = "default_profile = " . '"' . "$varmodulesldapdefaultprofile" . '"';
		$varmodulesldapprofileattribute = ($arrmodulesldap['varmodulesldapprofileattribute']?$arrmodulesldap['varmodulesldapprofileattribute']:'radiusProfileDn');
		$varmodulesldapprofileattribute = "profile_attribute = " . '"' . "$varmodulesldapprofileattribute" . '"';
		$varmodulesldapaccessattr = ($arrmodulesldap['varmodulesldapaccessattr']?$arrmodulesldap['varmodulesldapaccessattr']:'dialupAccess');
		$varmodulesldapaccessattr = "access_attr = " . '"' . "$varmodulesldapaccessattr" . '"';
	}

	// When disabled we put this in the file but commented (#) like in the default installation ldap2
	if (!$arrmodulesldap['varmodulesldap2dmiscenable']) {
		$varmodulesldap2defaultprofile = '### default_profile = "cn=radprofile,ou=dialup,o=My Org,c=UA" ###';
		$varmodulesldap2profileattribute = '### profile_attribute = "radiusProfileDn" ###';
		$varmodulesldap2accessattr = '### access_attr = "dialupAccess" ###';
	}
	// When enabled we put in the default values so there is no empty entry if there is not input from GUI
	else {
		$varmodulesldap2defaultprofile = ($arrmodulesldap['varmodulesldap2defaultprofile']?$arrmodulesldap['varmodulesldap2defaultprofile']:'cn=radprofile,ou=dialup,o=My Org,c=UA');
		$varmodulesldap2defaultprofile = "default_profile = " . '"' . "$varmodulesldap2defaultprofile" . '"';
		$varmodulesldap2profileattribute = ($arrmodulesldap['varmodulesldap2profileattribute']?$arrmodulesldap['varmodulesldap2profileattribute']:'radiusProfileDn');
		$varmodulesldap2profileattribute = "profile_attribute = " . '"' . "$varmodulesldap2profileattribute" . '"';
		$varmodulesldap2accessattr = ($arrmodulesldap['varmodulesldap2accessattr']?$arrmodulesldap['varmodulesldap2accessattr']:'dialupAccess');
		$varmodulesldap2accessattr = "access_attr = " . '"' . "$varmodulesldap2accessattr" . '"';
	}	
	
	// Group membership checking
	// When disabled we put this in the file but commented (#) like in the default installation ldap1
	if (!$arrmodulesldap['varmodulesldapgroupenable']) {
		$varmodulesldapgroupnameattribute = '### groupname_attribute = cn ###';
		$varmodulesldapgroupmembershipfilter = '### groupmembership_filter = "(|(&(objectClass=GroupOfNames)(member=%{control:Ldap-UserDn}))(&(objectClass=GroupOfUniqueNames)(uniquemember=%{control:Ldap-UserDn})))" ###';
		$varmodulesldapgroupmembershipattribute = '### groupmembership_attribute = radiusGroupName ###';
		$varmodulesldapcomparecheckitems = '### compare_check_items = yes ###';
		$varmodulesldapdoxlat = '### do_xlat = yes ###';
		$varmodulesldapaccessattrusedforallow = '### access_attr_used_for_allow = yes ###';
	}

	// When enabled we put in the default values so there is no empty entry if there is not input from GUI
	else {
		$varmodulesldapgroupnameattribute = ($arrmodulesldap['varmodulesldapgroupnameattribute']?$arrmodulesldap['varmodulesldapgroupnameattribute']:'cn');
		$varmodulesldapgroupnameattribute = "groupname_attribute = $varmodulesldapgroupnameattribute";
		$varmodulesldapgroupmembershipfilter = ($arrmodulesldap['varmodulesldapgroupmembershipfilter']?$arrmodulesldap['varmodulesldapgroupmembershipfilter']:'(|(&(objectClass=GroupOfNames)(member=%{control:Ldap-UserDn}))(&(objectClass=GroupOfUniqueNames)(uniquemember=%{control:Ldap-UserDn})))');
		$varmodulesldapgroupmembershipfilter = "groupmembership_filter = " . '"' . "$varmodulesldapgroupmembershipfilter" . '"';
		$varmodulesldapgroupmembershipattribute = ($arrmodulesldap['varmodulesldapgroupmembershipattribute']?$arrmodulesldap['varmodulesldapgroupmembershipattribute']:'radiusGroupName');
		$varmodulesldapgroupmembershipattribute = "groupmembership_attribute = $varmodulesldapgroupmembershipattribute";
		
		$varmodulesldapcomparecheckitems = ($arrmodulesldap['varmodulesldapcomparecheckitems']?$arrmodulesldap['varmodulesldapcomparecheckitems']:'yes');
		$varmodulesldapcomparecheckitems = "compare_check_items = $varmodulesldapcomparecheckitems";
		$varmodulesldapdoxlat = ($arrmodulesldap['varmodulesldapdoxlat']?$arrmodulesldap['varmodulesldapdoxlat']:'yes');
		$varmodulesldapdoxlat = "do_xlat = $varmodulesldapdoxlat";
		$varmodulesldapaccessattrusedforallow = ($arrmodulesldap['varmodulesldapaccessattrusedforallow']?$arrmodulesldap['varmodulesldapaccessattrusedforallow']:'yes');	
		$varmodulesldapaccessattrusedforallow = "access_attr_used_for_allow = $varmodulesldapaccessattrusedforallow";
	}
	
	// Group membership checking
	// When disabled we put this in the file but commented (#) like in the default installation ldap2
	if (!$arrmodulesldap['varmodulesldap2groupenable']) {
		$varmodulesldap2groupnameattribute = '### groupname_attribute = cn ###';
		$varmodulesldap2groupmembershipfilter = '### groupmembership_filter = "(|(&(objectClass=GroupOfNames)(member=%{control:Ldap-UserDn}))(&(objectClass=GroupOfUniqueNames)(uniquemember=%{control:Ldap-UserDn})))" ###';
		$varmodulesldap2groupmembershipattribute = '### groupmembership_attribute = radiusGroupName ###';
		$varmodulesldap2comparecheckitems = '### compare_check_items = yes ###';
		$varmodulesldap2doxlat = '### do_xlat = yes ###';
		$varmodulesldap2accessattrusedforallow = '### access_attr_used_for_allow = yes ###';
	}

	// When enabled we put in the default values so there is no empty entry if there is not input from GUI
	else {
		$varmodulesldap2groupnameattribute = ($arrmodulesldap['varmodulesldap2groupnameattribute']?$arrmodulesldap['varmodulesldap2groupnameattribute']:'cn');
		$varmodulesldap2groupnameattribute = "groupname_attribute = $varmodulesldap2groupnameattribute";
		$varmodulesldap2groupmembershipfilter = ($arrmodulesldap['varmodulesldap2groupmembershipfilter']?$arrmodulesldap['varmodulesldap2groupmembershipfilter']:'(|(&(objectClass=GroupOfNames)(member=%{control:Ldap-UserDn}))(&(objectClass=GroupOfUniqueNames)(uniquemember=%{control:Ldap-UserDn})))');
		$varmodulesldap2groupmembershipfilter = "groupmembership_filter = " . '"' . "$varmodulesldap2groupmembershipfilter" . '"';
		$varmodulesldap2groupmembershipattribute = ($arrmodulesldap['varmodulesldap2groupmembershipattribute']?$arrmodulesldap['varmodulesldap2groupmembershipattribute']:'radiusGroupName');
		$varmodulesldap2groupmembershipattribute = "groupmembership_attribute = $varmodulesldap2groupmembershipattribute";
		
		$varmodulesldap2comparecheckitems = ($arrmodulesldap['varmodulesldap2comparecheckitems']?$arrmodulesldap['varmodulesldap2comparecheckitems']:'yes');
		$varmodulesldap2comparecheckitems = "compare_check_items = $varmodulesldap2comparecheckitems";
		$varmodulesldap2doxlat = ($arrmodulesldap['varmodulesldap2doxlat']?$arrmodulesldap['varmodulesldap2doxlat']:'yes');
		$varmodulesldap2doxlat = "do_xlat = $varmodulesldap2doxlat";
		$varmodulesldap2accessattrusedforallow = ($arrmodulesldap['varmodulesldap2accessattrusedforallow']?$arrmodulesldap['varmodulesldap2accessattrusedforallow']:'yes');	
		$varmodulesldap2accessattrusedforallow = "access_attr_used_for_allow = $varmodulesldap2accessattrusedforallow";
	}	

	// Keepalive variables ldap1
	$varmodulesldapkeepaliveidle = ($arrmodulesldap['varmodulesldapkeepaliveidle']?$arrmodulesldap['varmodulesldapkeepaliveidle']:'60');
	$varmodulesldapkeepaliveprobes = ($arrmodulesldap['varmodulesldapkeepaliveprobes']?$arrmodulesldap['varmodulesldapkeepaliveprobes']:'3');
	$varmodulesldapkeepaliveinterval = ($arrmodulesldap['varmodulesldapkeepaliveinterval']?$arrmodulesldap['varmodulesldapkeepaliveinterval']:'3');

	// Keepalive variables ldap2
	$varmodulesldap2keepaliveidle = ($arrmodulesldap['varmodulesldap2keepaliveidle']?$arrmodulesldap['varmodulesldap2keepaliveidle']:'60');
	$varmodulesldap2keepaliveprobes = ($arrmodulesldap['varmodulesldap2keepaliveprobes']?$arrmodulesldap['varmodulesldap2keepaliveprobes']:'3');
	$varmodulesldap2keepaliveinterval = ($arrmodulesldap['varmodulesldap2keepaliveinterval']?$arrmodulesldap['varmodulesldap2keepaliveinterval']:'3');
$raddb = FREERADIUS_ETC . '/raddb';
$conf .= <<<EOD
# -*- text -*-
#
#  $Id$

# Lightweight Directory Access Protocol (LDAP)
#
#  This module definition allows you to use LDAP for
#  authorization and authentication.
#
#  See raddb/sites-available/default for reference to the
#  ldap module in the authorize and authenticate sections.
#
#  However, LDAP can be used for authentication ONLY when the
#  Access-Request packet contains a clear-text User-Password
#  attribute.  LDAP authentication will NOT work for any other
#  authentication method.
#
#  This means that LDAP servers don't understand EAP.  If you
#  force "Auth-Type = LDAP", and then send the server a
#  request containing EAP authentication, then authentication
#  WILL NOT WORK.
#
#  The solution is to use the default configuration, which does
#  work.
#
#  Setting "Auth-Type = LDAP" is ALMOST ALWAYS WRONG.  We
#  really can't emphasize this enough.
#	
ldap {
	#
	#  Note that this needs to match the name in the LDAP
	#  server certificate, if you're using ldaps.
	server = "$varmodulesldapserver"
	port = "$varmodulesldapserverport"
	identity = "$varmodulesldapidentity"
	password = $varmodulesldappassword
	basedn = "$varmodulesldapbasedn"
	filter = "$varmodulesldapfilter"
	base_filter = "$varmodulesldapbasefilter"

	#  How many connections to keep open to the LDAP server.
	#  This saves time over opening a new LDAP socket for
	#  every authentication request.
	ldap_connections_number = $varmodulesldapldapconnectionsnumber

	# seconds to wait for LDAP query to finish. default: 20
	timeout = $varmodulesldaptimeout

	#  seconds LDAP server has to process the query (server-side
	#  time limit). default: 20
	#
	#  LDAP_OPT_TIMELIMIT is set to this value.
	timelimit = $varmodulesldaptimelimit

	#
	#  seconds to wait for response of the server. (network
	#   failures) default: 10
	#
	#  LDAP_OPT_NETWORK_TIMEOUT is set to this value.
	net_timeout = $varmodulesldapnettimeout

	#
	#  This subsection configures the tls related items
	#  that control how FreeRADIUS connects to an LDAP
	#  server.  It contains all of the "tls_*" configuration
	#  entries used in older versions of FreeRADIUS.  Those
	#  configuration entries can still be used, but we recommend
	#  using these.
	#
	tls {
		# Set this to 'yes' to use TLS encrypted connections
		# to the LDAP database by using the StartTLS extended
		# operation.
		#			
		# The StartTLS operation is supposed to be
		# used with normal ldap connections instead of
		# using ldaps (port 689) connections
		start_tls = $varmodulesldapstarttls

		cacertfile = {$raddb}/certs/ca_ldap1_cert.pem
		cacertdir = {$raddb}/certs/
		certfile = {$raddb}/certs/radius_ldap1_cert.crt
		keyfile = {$raddb}/certs/radius_ldap1_cert.key
		randfile = {$raddb}/certs/random

		#  Certificate Verification requirements.  Can be:
		#    "never" (don't even bother trying)
		#    "allow" (try, but don't fail if the certificate
		#		can't be verified)
		#    "demand" (fail if the certificate doesn't verify.)
		#
		#	The default is "allow"
		require_cert = "$varmodulesldaprequirecert"
	}

	$varmodulesldapdefaultprofile
	$varmodulesldapprofileattribute
	$varmodulesldapaccessattr

	# Mapping of RADIUS dictionary attributes to LDAP
	# directory attributes.
	dictionary_mapping = \${confdir}/ldap.attrmap
	################## THE BELOW IS NOT COMPILED WITH FREERADIUS #################################
	#  Set password_attribute = nspmPassword to get the
	#  user's password from a Novell eDirectory
	#  backend. This will work ONLY IF FreeRADIUS has been
	#  built with the --with-edir configure option.
	#
	#  See also the following links:
	#
	#  http://www.novell.com/coolsolutions/appnote/16745.html
	#  https://secure-support.novell.com/KanisaPlatform/Publishing/558/3009668_f.SAL_Public.html
	#
	#  Novell may require TLS encrypted sessions before returning
	#  the user's password.
	#
	# password_attribute = userPassword

	#  Un-comment the following to disable Novell
	#  eDirectory account policy check and intruder
	#  detection. This will work *only if* FreeRADIUS is
	#  configured to build with --with-edir option.
	#
	edir_account_policy_check = no
	################## THE ABOVE IS NOT COMPILED WITH FREERADIUS #################################
	#
	#  Group membership checking.  Disabled by default.
	#
	$varmodulesldapgroupnameattribute
	$varmodulesldapgroupmembershipfilter
	$varmodulesldapgroupmembershipattribute

	$varmodulesldapcomparecheckitems
	$varmodulesldapdoxlat
	$varmodulesldapaccessattrusedforallow

	#
	#  The following two configuration items are for Active Directory
	#  compatibility.  If you see the helpful "operations error"
	#  being returned to the LDAP module, uncomment the next
	#  two lines.
	#

	$varmodulesldapmsadcompatibility

	#
	#  By default, if the packet contains a User-Password,
	#  and no other module is configured to handle the
	#  authentication, the LDAP module sets itself to do
	#  LDAP bind for authentication.
	#
	#  THIS WILL ONLY WORK FOR PAP AUTHENTICATION.
	#
	#  THIS WILL NOT WORK FOR CHAP, MS-CHAP, or 802.1x (EAP). 
	#
	#  You can disable this behavior by setting the following
	#  configuration entry to "no".
	#
	#  allowed values: {no, yes}
	# set_auth_type = yes

	#  ldap_debug: debug flag for LDAP SDK
	#  (see OpenLDAP documentation).  Set this to enable
	#  huge amounts of LDAP debugging on the screen.
	#  You should only use this if you are an LDAP expert.
	#
	#	default: 0x0000 (no debugging messages)
	#	Example:(LDAP_DEBUG_FILTER+LDAP_DEBUG_CONNS)
	#ldap_debug = 0x0028 

	#
	#  Keepalive configuration.  This MAY NOT be supported by your
	#  LDAP library.  If these configuration entries appear in the
	#  output of "radiusd -X", then they are supported.  Otherwise,
	#  they are unsupported, and changing them will do nothing.
	#
	keepalive {
		# LDAP_OPT_X_KEEPALIVE_IDLE
		idle = $varmodulesldapkeepaliveidle

		# LDAP_OPT_X_KEEPALIVE_PROBES
		probes = $varmodulesldapkeepaliveprobes

		# LDAP_OPT_X_KEEPALIVE_INTERVAL
		interval = $varmodulesldapkeepaliveinterval
	}
}

ldap ldap2{
	#
	#  Note that this needs to match the name in the LDAP
	#  server certificate, if you're using ldaps.
	server = "$varmodulesldap2server"
	port = "$varmodulesldap2serverport"
	identity = "$varmodulesldap2identity"
	password = $varmodulesldap2password
	basedn = "$varmodulesldap2basedn"
	filter = "$varmodulesldap2filter"
	base_filter = "$varmodulesldap2basefilter"

	#  How many connections to keep open to the LDAP server.
	#  This saves time over opening a new LDAP socket for
	#  every authentication request.
	ldap_connections_number = $varmodulesldap2ldapconnectionsnumber

	# seconds to wait for LDAP query to finish. default: 20
	timeout = $varmodulesldap2timeout

	#  seconds LDAP server has to process the query (server-side
	#  time limit). default: 20
	#
	#  LDAP_OPT_TIMELIMIT is set to this value.
	timelimit = $varmodulesldap2timelimit

	#
	#  seconds to wait for response of the server. (network
	#   failures) default: 10
	#
	#  LDAP_OPT_NETWORK_TIMEOUT is set to this value.
	net_timeout = $varmodulesldap2nettimeout

	#
	#  This subsection configures the tls related items
	#  that control how FreeRADIUS connects to an LDAP
	#  server.  It contains all of the "tls_*" configuration
	#  entries used in older versions of FreeRADIUS.  Those
	#  configuration entries can still be used, but we recommend
	#  using these.
	#
	tls {
		# Set this to 'yes' to use TLS encrypted connections
		# to the LDAP database by using the StartTLS extended
		# operation.
		#			
		# The StartTLS operation is supposed to be
		# used with normal ldap connections instead of
		# using ldaps (port 689) connections
		start_tls = $varmodulesldap2starttls

		cacertfile = {$raddb}/certs/ca_ldap2_cert.pem
		cacertdir = {$raddb}/certs/
		certfile = {$raddb}/certs/radius_ldap2_cert.crt
		keyfile = {$raddb}/certs/radius_ldap2_cert.key
		randfile = {$raddb}/certs/random

		#  Certificate Verification requirements.  Can be:
		#    "never" (don't even bother trying)
		#    "allow" (try, but don't fail if the certificate
		#		can't be verified)
		#    "demand" (fail if the certificate doesn't verify.)
		#
		#	The default is "allow"
		require_cert = "$varmodulesldap2requirecert"
	}

	$varmodulesldap2defaultprofile
	$varmodulesldap2profileattribute
	$varmodulesldap2accessattr

	# Mapping of RADIUS dictionary attributes to LDAP
	# directory attributes.
	dictionary_mapping = \${confdir}/ldap.attrmap
	################## THE BELOW IS NOT COMPILED WITH FREERADIUS #################################
	#  Set password_attribute = nspmPassword to get the
	#  user's password from a Novell eDirectory
	#  backend. This will work ONLY IF FreeRADIUS has been
	#  built with the --with-edir configure option.
	#
	#  See also the following links:
	#
	#  http://www.novell.com/coolsolutions/appnote/16745.html
	#  https://secure-support.novell.com/KanisaPlatform/Publishing/558/3009668_f.SAL_Public.html
	#
	#  Novell may require TLS encrypted sessions before returning
	#  the user's password.
	#
	# password_attribute = userPassword

	#  Un-comment the following to disable Novell
	#  eDirectory account policy check and intruder
	#  detection. This will work *only if* FreeRADIUS is
	#  configured to build with --with-edir option.
	#
	edir_account_policy_check = no
	################## THE ABOVE IS NOT COMPILED WITH FREERADIUS #################################
	#
	#  Group membership checking.  Disabled by default.
	#
	$varmodulesldap2groupnameattribute
	$varmodulesldap2groupmembershipfilter
	$varmodulesldap2groupmembershipattribute

	$varmodulesldap2comparecheckitems
	$varmodulesldap2doxlat
	$varmodulesldap2accessattrusedforallow

	#
	#  The following two configuration items are for Active Directory
	#  compatibility.  If you see the helpful "operations error"
	#  being returned to the LDAP module, uncomment the next
	#  two lines.
	#

	$varmodulesldap2msadcompatibility

	#
	#  By default, if the packet contains a User-Password,
	#  and no other module is configured to handle the
	#  authentication, the LDAP module sets itself to do
	#  LDAP bind for authentication.
	#
	#  THIS WILL ONLY WORK FOR PAP AUTHENTICATION.
	#
	#  THIS WILL NOT WORK FOR CHAP, MS-CHAP, or 802.1x (EAP). 
	#
	#  You can disable this behavior by setting the following
	#  configuration entry to "no".
	#
	#  allowed values: {no, yes}
	# set_auth_type = yes

	#  ldap_debug: debug flag for LDAP SDK
	#  (see OpenLDAP documentation).  Set this to enable
	#  huge amounts of LDAP debugging on the screen.
	#  You should only use this if you are an LDAP expert.
	#
	#	default: 0x0000 (no debugging messages)
	#	Example:(LDAP_DEBUG_FILTER+LDAP_DEBUG_CONNS)
	#ldap_debug = 0x0028 

	#
	#  Keepalive configuration.  This MAY NOT be supported by your
	#  LDAP library.  If these configuration entries appear in the
	#  output of "radiusd -X", then they are supported.  Otherwise,
	#  they are unsupported, and changing them will do nothing.
	#
	keepalive {
		# LDAP_OPT_X_KEEPALIVE_IDLE
		idle = $varmodulesldap2keepaliveidle

		# LDAP_OPT_X_KEEPALIVE_PROBES
		probes = $varmodulesldap2keepaliveprobes

		# LDAP_OPT_X_KEEPALIVE_INTERVAL
		interval = $varmodulesldap2keepaliveinterval
	}
}
EOD;

	$filename = FREERADIUS_ETC . '/raddb/modules/ldap';
	conf_mount_rw();
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
	conf_mount_ro();
	
	// We need to rebuild "freeradius_serverdefault_resync" before restart service
	// "freeradius_serverdefault_resync" needs to restart other dependencies so we are pointing directly to "freeradius_settings_resync()"
	freeradius_serverdefault_resync();
	restart_service("radiusd");
	
}

function freeradius_plainmacauth_resync() {
	global $config;
	$conf = '';
	
	// Variables: If not using 802.1x, mac address must be known
	$varsettings = $config['installedpackages']['freeradiussettings']['config'][0];
	
	// defining variables with filename path
	$filepolicyconf = FREERADIUS_ETC . '/raddb/policy.conf';
	$filepolicyconfbackup = FREERADIUS_ETC . '/raddb/policy.conf.backup';
	$filemodulesfiles = FREERADIUS_ETC . '/raddb/modules/files';
	$filemodulesfilesbackup = FREERADIUS_ETC . '/raddb/files.backup';
	
	// If unchecked then plain mac auth is disabled and backups of the original files will be restored
	if ($varsettings['varsettingsenablemacauth'] == '') {
		// This is a check - only restore files if they aren't already
		if (file_exists(FREERADIUS_ETC . "/raddb/plain_macauth_enabled")) {
			log_error("FreeRADIUS: Plain-MAC-Auth disabled. Restoring the original file from {$filepolicyconfbackup} and {$filemodulesfilesbackup}");
			copy($filepolicyconfbackup, $filepolicyconf);
			copy($filemodulesfilesbackup, $filemodulesfiles);
			unlink(FREERADIUS_ETC . "/raddb/plain_macauth_enabled");
			freeradius_serverdefault_resync();
		}
	}
	// If checked then plain mac auth is enabled
	else {
		// This is a check - only modify files if they aren't already
		if (!file_exists(FREERADIUS_ETC . "/raddb/plain_macauth_enabled")) {
			freeradius_modulesfiles_resync();
			freeradius_policyconf_resync();
			exec("cd " . FREERADIUS_ETC . "/raddb && touch " . FREERADIUS_ETC . "/raddb/plain_macauth_enabled");
			log_error("FreeRADIUS: Plain-MAC-Auth enabled. Modified {$filepolicyconf} and {$filemodulesfiles}");
			freeradius_serverdefault_resync();
		}
	}
}

function freeradius_modulesfiles_resync() {
	global $config;
	$conf = '';

	$conf .= <<<EOD
# -*- text -*-
#
#  \$Id\$

# Livingston-style 'users' file
#
files {
	# The default key attribute to use for matches.  The content
	# of this attribute is used to match the "name" of the
	# entry.
	#key = "%{Stripped-User-Name:-%{User-Name}}"

	usersfile = \${confdir}/users
	acctusersfile = \${confdir}/acct_users
	preproxy_usersfile = \${confdir}/preproxy_users

	#  If you want to use the old Cistron 'users' file
	#  with FreeRADIUS, you should change the next line
	#  to 'compat = cistron'.  You can the copy your 'users'
	#  file from Cistron.
	compat = no
}

#  An example which defines a second instance of the "files" module.
#  This instance is named "second_files".  In order for it to be used
#  in a virtual server, it needs to be listed as "second_files"
#  inside of the "authorize" section (or other section).  If you just
#  list "files", that will refer to the configuration defined above.
#

#  The two names here mean:
#	"files" - this is a configuration for the "rlm_files" module
#	"second_files" - this is a named configuration, which isn't
#			the default configuration.
files authorized_macs {
        # The default key attribute to use for matches.  The content
        # of this attribute is used to match the "name" of the
        # entry.
        key = "%{Calling-Station-ID}"

        usersfile = \${confdir}/authorized_macs

        #  If you want to use the old Cistron 'users' file
        #  with FreeRADIUS, you should change the next line
        #  to 'compat = cistron'.  You can the copy your 'users'
        #  file from Cistron.
        compat = no
}
EOD;

	$filename = FREERADIUS_ETC . '/raddb/modules/files';
	conf_mount_rw();
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
	conf_mount_ro();

}

function freeradius_policyconf_resync() {
	global $config;
	$conf = '';

	$conf .= <<<EOD
# -*- text -*-
##
## policy.conf	-- FreeRADIUS server configuration file.
##
##	http://www.freeradius.org/
##	\$Id\$
##

#
#  Policies are virtual modules, similar to those defined in the
#  "instantate" section of radiusd.conf.
#
#  Defining a policy here means that it can be referenced in multiple
#  places as a *name*, rather than as a series of conditions to match,
#  and actions to take.
#
#  Policies are something like subroutines in a normal language, but
#  they cannot be called recursively.  They MUST be defined in order.
#  If policy A calls policy B, then B MUST be defined before A.
#
policy {
	#
	#	Forbid all EAP types.
	#
	forbid_eap {
		if (EAP-Message) {
			reject
		}
	}

	#
	#	Forbid all non-EAP types outside of an EAP tunnel.
	#
	permit_only_eap {
		if (!EAP-Message) {
			#  We MAY be inside of a TTLS tunnel.
			#  PEAP and EAP-FAST require EAP inside of
			#  the tunnel, so this check is OK.
			#  If so, then there MUST be an outer EAP message.
			if (!"%{outer.request:EAP-Message}") {
				reject
			}
		}
	}

	#
	#	Forbid all attempts to login via realms.
	#
	deny_realms {
		if (User-Name =~ /@|\\\\/) {
			reject
		}
	}

	#
	#	If you want the server to pretend that it is dead,
	#	then use the "do_not_respond" policy.
	#
	do_not_respond {
		update control {
			Response-Packet-Type := Do-Not-Respond
		}

		handled
	}

	#
	#  Force some sanity on User-Name.  This helps to avoid issues
	#  issues where the back-end database is "forgiving" about
	#  what constitutes a user name.
	#
	filter_username {
		# spaces at the start: reject
		if (User-Name =~ /^ /) {
			reject
		}

		# spaces at the end: reject
		if (User-Name =~ / \$\$/) {
			reject
		}

		# Mixed case: reject
		if (User-Name != "%{tolower:%{User-Name}}") {
			reject
		}
	}


	#	
	#  The following policies are for the Chargeable-User-Identity
	#  (CUI) configuration.
	#

	#
	#  The client indicates it can do CUI by sending a CUI attribute	
	#  containing one zero byte
	#
	cui_authorize {
		update request {
			Chargeable-User-Identity:='\\\\000'
		}
	}

	#
	#  Add a CUI attribute based on the User-Name, and a secret key
	#  known only to this server.
	#
	cui_postauth {
		if (FreeRadius-Proxied-To == 127.0.0.1) {
			if (outer.request:Chargeable-User-Identity) {
				update outer.reply {
					Chargeable-User-Identity:="%{md5:%{config:cui_hash_key}%{User-Name}}"
				}
			}
		}
		else {
			if (Chargeable-User-Identity) {
				update reply {
					Chargeable-User-Identity="%{md5:%{config:cui_hash_key}%{User-Name}}"
				}
			}
		}
	}

	#
	#  If there is a CUI attribute in the reply, add it to the DB.
	#
	cui_updatedb {
		if (reply:Chargeable-User-Identity) {
			cui
		}
	}

	#
	#  If we had stored a CUI for the User, add it to the request.
	#
	cui_accounting {
		#
		#  If the CUI isn't in the packet, see if we can find it
		#  in the DB.
		#
		if (!Chargeable-User-Identity) {
			update control {
				Chargable-User-Identity := "%{cui: SELECT cui FROM cui WHERE clientipaddress = '%{Client-IP-Address}' AND callingstationid = '%{Calling-Station-Id}' AND username = '%{User-Name}'}"
			}
		}

		#
		#  If it exists now, then write out when we last saw
		#  this CUI.
		#
		if (Chargeable-User-Identity && (Chargeable-User-Identity != "")) {
			cui
		}
	}

	#
	#  Normalize the MAC Addresses in the Calling/Called-Station-Id
	#
	mac-addr = ([0-9a-f]{2})[^0-9a-f]?([0-9a-f]{2})[^0-9a-f]?([0-9a-f]{2})[^0-9a-f]?([0-9a-f]{2})[^0-9a-f]?([0-9a-f]{2})[^0-9a-f]?([0-9a-f]{2})

	#  Add "rewrite.called_station_id" in the "authorize" and "preacct"
	#  sections.
	rewrite.called_station_id {
		if((Called-Station-Id) && "%{Called-Station-Id}" =~ /^%{config:policy.mac-addr}(:(.+))?\$/i) {
			update request {
				Called-Station-Id := "%{tolower:%{1}-%{2}-%{3}-%{4}-%{5}-%{6}}"
			}

			# SSID component?
			if ("%{8}") {
				update request {
					Called-Station-Id := "%{Called-Station-Id}:%{8}"
				}
			}
			updated
		}
		else {
			noop
		}
	}

	#  Add "rewrite.calling_station_id" in the "authorize" and "preacct"
	#  sections.
	#rewrite.calling_station_id {
	#	if((Calling-Station-Id) && "%{Calling-Station-Id}" =~ /^%{config:policy.mac-addr}\$/i) {
	#		update request {
	#			Calling-Station-Id := "%{tolower:%{1}-%{2}-%{3}-%{4}-%{5}-%{6}}"
	#		}
	#		updated
	#	}
	#	else {
	#		noop
	#	}
	#}
	
	#####  MODIFIED FOR http://wiki.freeradius.org/Mac-Auth#Mac-Auth+or+802.1x #####
	#  Add "rewrite_calling_station_id" in the "authorize" and "preacct"
	#  sections.
	rewrite_calling_station_id {
        if (Calling-Station-Id =~ /([0-9a-f]{2})[-:]?([0-9a-f]{2})[-:]?([0-9a-f]{2})[-:]?([0-9a-f]{2})[-:]?([0-9a-f]{2})[-:]?([0-9a-f]{2})/i){
                update request {
                        Calling-Station-Id := "%{tolower:%{1}-%{2}-%{3}-%{4}-%{5}-%{6}}"
                }
        }
        else {
            noop
		}
	}
}
EOD;

	$filename = FREERADIUS_ETC . '/raddb/policy.conf';
	conf_mount_rw();
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
	conf_mount_ro();

}

function freeradius_motp_resync() {
	global $config, $bash_path;
	$conf = '';

	$varsettings = $config['installedpackages']['freeradiussettings']['config'][0];
	
	$varsettingsmotptimespan = ($varsettings['varsettingsmotptimespan']?$varsettings['varsettingsmotptimespan']:'2');
	$varsettingsmotptimespanbeforeafter = $varsettingsmotptimespan + $varsettingsmotptimespan;
	$varsettingsmotpdeleteoldpasswords = $varsettingsmotptimespanbeforeafter + 1;
	$varsettingsmotppasswordattempts = ($varsettings['varsettingsmotppasswordattempts']?$varsettings['varsettingsmotppasswordattempts']:'5');
	$varsettingsmotpchecksumtype = ($varsettings['varsettingsmotpchecksumtype']?$varsettings['varsettingsmotpchecksumtype']:'md5');
	$varsettingsmotptokenlength = ($varsettings['varsettingsmotptokenlength']?$varsettings['varsettingsmotptokenlength']:'1-6');
	
	// check if disabled then we delete otpverify.sh script
	if ($varsettings['varsettingsmotpenable'] == '') {
		if (file_exists(FREERADIUS_ETC . "/raddb/scripts/otpverify.sh")) {
			@unlink(FREERADIUS_ETC . "/raddb/scripts/otpverify.sh");
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

    alias checksum=$varsettingsmotpchecksumtype
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
find /var/log/motp/cache -type f -cmin +$varsettingsmotpdeleteoldpasswords | xargs rm 2>/dev/null

if [ -e "/var/log/motp/cache/\$PASSWD" ]; then
	echo "FAIL"
	logger -f /var/log/system.log "FreeRADIUS: Authentication failed! Mobile-One-Time-Password \$PASSWD is already used!"
	exit 15
fi

# account locked?
if [ "`cat /var/log/motp/users/\$USERNAME 2>/dev/null`" == "$varsettingsmotppasswordattempts" ]; then
	echo "FAIL"
	logger -f /var/log/system.log "FreeRADIUS: Authentication failed! Too many wrong password attempts. User is locked! To unlock delete /var/log/motp/users/\$USERNAME"
	exit 13
fi

I=0
EPOCHTIME=`expr \$EPOCHTIME - $varsettingsmotptimespan`
EPOCHTIME=`expr \$EPOCHTIME + \$OFFSET`
while [ \$I -lt $varsettingsmotptimespanbeforeafter ] ; do # `$varsettingsmotptimespan * 10` seconds before and after
	OTP=`printf \$EPOCHTIME\$SECRET\$PIN|checksum|cut -b $varsettingsmotptokenlength`
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
		$filename = FREERADIUS_ETC . '/raddb/scripts/otpverify.sh';
		conf_mount_rw();
		file_put_contents($filename, $conf);
		chmod($filename, 0750);
		conf_mount_ro();
	}

}

function freeradius_modulesmotp_resync() {
	global $config, $bash_path;
	$conf = '';

	// put the constant to a variable
	$varFREERADIUS_ETC = FREERADIUS_ETC;

	$conf .= <<<EOD
exec motp {
		wait = yes
		program = "{$bash_path} {$varFREERADIUS_ETC}/raddb/scripts/otpverify.sh %{request:User-Name} %{request:User-Password} %{reply:MOTP-Init-Secret} %{reply:MOTP-PIN} %{reply:MOTP-Offset}"
	}	
EOD;

	$filename = FREERADIUS_ETC . '/raddb/modules/motp';
	conf_mount_rw();
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
	conf_mount_ro();

}

function freeradius_modulesdatacounter_resync() {
	global $config;
	$conf = '';

	// put the constant to a variable
	$varFREERADIUS_ETC = FREERADIUS_ETC;

	$conf .= <<<EOD
exec datacounterdaily {
		wait = yes
		program = "/bin/sh {$varFREERADIUS_ETC}/raddb/scripts/datacounter_acct.sh %{request:User-Name} daily %{request:Acct-Input-Octets} %{request:Acct-Output-Octets} %{request:Acct-Status-Type} %{request:Acct-Session-Id}"
	}
exec datacounterweekly {
		wait = yes
		program = "/bin/sh {$varFREERADIUS_ETC}/raddb/scripts/datacounter_acct.sh %{request:User-Name} weekly %{request:Acct-Input-Octets} %{request:Acct-Output-Octets} %{request:Acct-Status-Type} %{request:Acct-Session-Id}"
	}
exec datacountermonthly {
		wait = yes
		program = "/bin/sh {$varFREERADIUS_ETC}/raddb/scripts/datacounter_acct.sh %{request:User-Name} monthly %{request:Acct-Input-Octets} %{request:Acct-Output-Octets} %{request:Acct-Status-Type} %{request:Acct-Session-Id}"
	}
exec datacounterforever {
		wait = yes
		program = "/bin/sh {$varFREERADIUS_ETC}/raddb/scripts/datacounter_acct.sh %{request:User-Name} forever %{request:Acct-Input-Octets} %{request:Acct-Output-Octets} %{request:Acct-Status-Type} %{request:Acct-Session-Id}"
	}
EOD;

	$filename = FREERADIUS_ETC . '/raddb/modules/datacounter_acct';
	conf_mount_rw();
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
	conf_mount_ro();

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

	$filename = FREERADIUS_ETC . '/raddb/scripts/datacounter_auth.sh';
	conf_mount_rw();
	file_put_contents($filename, $conf);
	chmod($filename, 0750);
	conf_mount_ro();

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

	$filename = FREERADIUS_ETC . '/raddb/scripts/datacounter_acct.sh';
	conf_mount_rw();
	file_put_contents($filename, $conf);
	chmod($filename, 0750);
	conf_mount_ro();

}

function freeradius_dictionary_resync() {
	global $config;
	$conf = '';

	$conf .= <<<EOD

#
#       This is the master dictionary file, which references the
#       pre-defined dictionary files included with the server.
#
#       Any new/changed attributes MUST be placed in this file, as
#       the pre-defined dictionaries SHOULD NOT be edited.
#
#       \$Id\$
#

#
#  The DHCP dictionary is used only when the server is built with
#  "configure --with-dhcp".  It is not (and should not) be used in
#  other situations.  If you are running just a RADIUS server, this
#  line can be deleted.  If you are using DHCP, the following line
#  should be uncommented.
#
#  Ideally, the "configure" process should automatically enable this
#  dictionary, but we don't yet do that.
#
#\$INCLUDE       /usr/local/dictionary.dhcp

#
#       The filename given here should be an absolute path.
#
\$INCLUDE        /usr/local/share/freeradius/dictionary

#
#       Place additional attributes or \$INCLUDEs here.  They will
#       over-ride the definitions in the pre-defined dictionaries.
#
#       See the 'man' page for 'dictionary' for information on
#       the format of the dictionary files.

#
#       If you want to add entries to the dictionary file,
#       which are NOT going to be placed in a RADIUS packet,
#       add them here.  The numbers you pick should be between
#       3000 and 4000.
#

#ATTRIBUTE      My-Local-String         3000    string
#ATTRIBUTE      My-Local-IPAddr         3001    ipaddr
#ATTRIBUTE      My-Local-Integer        3002    integer

### Attributes for mobile-One-Time-Password
ATTRIBUTE 		MOTP-Init-Secret 		900 	string
ATTRIBUTE 		MOTP-PIN 			901 	string
ATTRIBUTE 		MOTP-Offset 			902 	string



EOD;

	$filename = FREERADIUS_ETC . '/raddb/dictionary';
	conf_mount_rw();
	file_put_contents($filename, $conf);
	chmod($filename, 0640);
	conf_mount_ro();

}

?>
