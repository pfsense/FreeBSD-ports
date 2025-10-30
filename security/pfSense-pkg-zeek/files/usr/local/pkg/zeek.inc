<?php
/*
* zeek.inc
* part of pfSense (https://www.pfSense.org/)
* Copyright (c) 2018-2020 Prosper Doko
* Copyright (c) 2020 Mark Overholser
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

require_once('config.inc');
require_once('globals.inc');
require_once('notices.inc');
require_once('pfsense-utils.inc');
require_once('pkg-utils.inc');
require_once('services.inc');
require_once('service-utils.inc');
require_once("functions.inc");
require_once('util.inc');
require_once("interfaces.inc");
require_once('xmlrpc_client.inc');

if (!function_exists("filter_configure")) {
	require_once("filter.inc");
}

define('LOG_PREFIX_PKG_ZEEK', 'Zeek');

$shortcut_section = "zeek";

define('ZEEK_LOCALBASE', '/usr/local');
define('ZEEKCTL_BIN', ZEEK_LOCALBASE .'/bin');
define('ZEEK_ETC', ZEEK_LOCALBASE .'/etc');
define('ZEEK_LOGDIR', ZEEK_LOCALBASE .'/logs/current');
define('ZEEK_SITEDIR', ZEEK_LOCALBASE .'/share/zeek/site');

/*
* Utility functions
*/

/* Handle base64 encoding and linebreaks in textarea configuration fields*/
function zeek_text_area_decode($text) {
	return preg_replace('/\r\n/', "\n", base64_decode($text));
}

/* Get interface IP and netmask for Zeek interfaces*/
function zeek_get_real_interface_address($iface) {
	return array(get_interface_ip($iface), gen_subnet_mask(get_interface_subnet($iface)));
}

/* Check whether Zeek is enabled*/
function zeek_enabled() {
	global $zeek_enabled;
	$zeek_enabled = false;

	$settings = config_get_path('installedpackages/zeek/config/0', []);

	// check whether Zeek is enabled ...
	if ($settings['enable'] == "on") {
		// ... and has at least one interface configured ...
		if (!empty($settings['active_interface'])) {
			$zeek_enabled = true;
		}
	}
	return $zeek_enabled;
}

// Check whether Zeek Cluster is enabled
function zeek_cluster_enabled() {
	global $zeek_cluster_enabled;
	$zeek_cluster_enabled = false;

	$settings = config_get_path('installedpackages/zeekcluster/config/0', []);

	// check whether zeek cluster is enabled
	if ($settings['enable'] == "on") {
		$zeek_cluster_enabled = true;
	}
	return $zeek_cluster_enabled;
}

// Start and/or stop services according to Zeek configuration
function zeek_restart_services() {
	// do not (re)start zeek services on boot
	if (platform_booting()) {
		return;
	}

	if (zeek_enabled()) {
		if (!is_service_running('zeek')) {
			logger(LOG_INFO, localize_text("Starting service..."), LOG_PREFIX_PKG_ZEEK);
			mwexec(ZEEK_ETC . "/rc.d/zeek.sh start");
		} else {
			logger(LOG_INFO, localize_text("Reloading for configuration sync..."), LOG_PREFIX_PKG_ZEEK);
			mwexec(ZEEK_ETC . "/rc.d/zeek.sh restart");
		}
		// sleep for a couple seconds to give zeek a chance to fire up fully.
		for ($i = 0; $i < 10; $i++) {
			if (!is_service_running('zeek')) {
				sleep(1);
			}
		}
	} else {
		// Zeek is disabled
		if (is_service_running('zeek')) {
			logger(LOG_INFO, localize_text("Stopping service..."), LOG_PREFIX_PKG_ZEEK);
			mwexec_bg(ZEEK_ETC . "/rc.d/zeek.sh stop");
		}
	}
}

/*
 * rc scripts, services and cronjobs
 */

// Handle cronjob install/uninstall
function zeek_install_cron($should_install) {
	if (platform_booting()) {
		return;
	}
	$cron_cmd = ZEEK_LOCALBASE . "/bin/zeekctl cron ";

	if (($should_install) && (zeek_enabled())) {
		logger(LOG_INFO, localize_text("Adding cronjobs ..."), LOG_PREFIX_PKG_ZEEK);
		install_cron_job("{$cron_cmd}", $should_install, "*/5", "*", "*", "*", "*", "root");
	} else {
		logger(LOG_INFO, localize_text("Removing cronjobs ..."), LOG_PREFIX_PKG_ZEEK);
		install_cron_job("{$cron_cmd}", false);
	}
}

function zeek_write_rcfile() {
	$zeekctl_bin_dir = ZEEKCTL_BIN;
	$rcfile = array();
	$rcfile['file'] = 'zeek.sh';
	$rcfile['start'] = <<< EOD
/sbin/mount -t procfs proc /proc
{$zeekctl_bin_dir}/zeekctl deploy

EOD;

	$rcfile['stop'] = <<< EOD
{$zeekctl_bin_dir}/zeekctl stop

EOD;

	write_rcfile($rcfile);
}

/*
 * Zeek package install/uninstall
*/

function zeek_install_command() {
	global $g;

	/* Hard kill any running zeek processes that may have been started by any
	 of the pfSense scripts such as check_reload_status() or rc.start_packages*/
	if (is_process_running("zeek")) {
		killbyname("zeek");
		sleep(2);
	}

	// creating a backup file of the original zeekctl.cfg no matter if user checked this or not
	if (!file_exists(ZEEK_ETC . "/zeekctl.cfg.backup")) {
		logger(LOG_INFO, localize_text("Creating backup of the original file to %s", ZEEK_ETC . "/zeekctl.cfg.backup"), LOG_PREFIX_PKG_ZEEK);
		copy(ZEEK_ETC . "/zeekctl.cfg", ZEEK_ETC . "/zeekctl.cfg.backup");
	}

	// creating a backup file of the original /etc/node no matter if user checked this or not
	if (!file_exists(ZEEK_ETC . "/node.cfg.backup")) {
		logger(LOG_INFO, localize_text("Creating backup of the original file to %s", ZEEK_ETC . "/node.cfg.backup"), LOG_PREFIX_PKG_ZEEK);
		copy(ZEEK_ETC . "/node.cfg", ZEEK_ETC . "/node.cfg.backup");
	}

	// creating a backup file of the original /etc/networks no matter if user checked this or not
	if (!file_exists(ZEEK_ETC . "/networks.cfg.backup")) {
		logger(LOG_INFO, localize_text("Creating backup of the original file to %s", ZEEK_ETC . "/networks.cfg.backup"), LOG_PREFIX_PKG_ZEEK);
		copy(ZEEK_ETC . "/networks.cfg", ZEEK_ETC . "/networks.cfg.backup");
	}
	// Change owner of zeek created files
	chown("/usr/local/logs", "root");
	chown("/usr/local/spool", "root");

	unlink_if_exists("/usr/local/etc/rc.d/zeek.sh");

	// create zeek rcfile
	zeek_write_rcfile();

	write_config("[zeek] Package installed.");
}

function zeek_deinstall_command() {
	zeek_install_cron(false);
	killbyname("zeek");
	unlink_if_exists("/usr/local/etc/rc.d/zeek.sh");

	if (zeek_log_resync()) {
		if (is_dir("/usr/local/logs")) {
			rmdir_recursive("/usr/local/logs");
		}
	}
}

/*
 * Zeek input validation
*/

// Zeek NSM: General Settings input validation
function zeek_validate_general($post, &$input_errors) {
	$settings = config_get_path('installedpackages/zeek/config/0', []);

	if ($post['enable'] == "on") {
		if (!zeek_cluster_enabled()) {
			if (empty($post['active_interface'])) {
				$input_errors[] = "Select at least one interface under 'Zeek Interface(s)' to enable Zeek instance.";
				$input_errors[] = "To use Zeek as cluster, visit Zeek NSM: Zeek cluster, configure and save the cluster settings first.";
			}
		} else {
			logger(LOG_INFO, localize_text("Enabled as cluster. If this is unintended, visit Zeek NSM: General and configure zeek interfaces."), LOG_PREFIX_PKG_ZEEK);
		}
	}

	if (!empty($post['hostname']) && !is_ipaddr($post['hostname']) && !is_domain($post['hostname'])) {
		$input_errors[] = "'Hostname' must contain a valid IP address or domain name.";
	}

	$local_networks = explode("\n", $post['local_networks']);
	foreach ($local_networks as $subnet) {
		$subnet = trim($subnet);
		if (!empty($subnet) && !is_subnet($subnet)) {
			$input_errors[] = "'Local Network Address' must be a valid CIDR range. The subnet '{$subnet}' is not valid.";
		}
	}
	unset($local_networks);
}

function zeek_validate_zeekctl($post, &$input_errors) {
	$min_disk_space = trim($post['min_disk_space']);
	if (!empty($min_disk_space) && (!is_numericint($min_disk_space))) {
		$input_errors[] = "Enter a valid number in the 'min disk space' field.";
	}
	unset($min_disk_space);

	$log_expiration = trim($post['log_expiration']);
	if (!empty($log_expiration) && (!is_numericint($log_expiration))) {
		$input_errors[] = "Enter a valid number in the 'log rotation interval' field.";
	}
	unset($log_expiration);

	if (substr($post['log_dir'], -1, 1) == '/') {
		$input_errors[] = 'Log location must not end with a / character.';
	}

	if ($post['log_dir'][0] != '/') {
		$input_errors[] = 'Log location must start with a / character.';
	}

	if (strlen($post['log_dir']) <= 3) {
		$input_errors[] = "Configured log location directory is not valid.";
	}

	$log_rotate = trim($post['log_rotate']);
	if (!empty($log_rotate) && (!is_numericint($log_rotate))) {
		$input_errors[] = "Enter a valid number of days in the 'Log rotate' field.";
	}
	unset($log_rotate);
}

function zeek_validate_cluster($post, &$input_errors) {
	if ($post['enable'] != 'on') {
		return;
	}

	$managerhost = trim($post['manager_host']);
	if (!is_ipaddr($managerhost) && !is_domain($managerhost)) {
		$input_errors[] = "'Manager host' must contain a valid IP address or domain name.";
	}

	$proxyhost = trim($post['proxy_host']);
	if (!is_ipaddr($proxyhost) && !is_domain($proxyhost)) {
		$input_errors[] = "'Proxy host' must contain a valid IP address or domain name.";
	}

	$proxyhost = trim($post['proxy_host']);
	if (!is_ipaddr($proxyhost) && !is_domain($proxyhost)) {
		$input_errors[] = "'Proxy host' must contain a valid IP address or domain name.";
	}

	$worker1host = trim($post['worker1_host']);
	if (!is_ipaddr($worker1host) && !is_domain($worker1host)) {
		$input_errors[] = "'Worker 1 host' must contain a valid IP address or domain name.";
	}
	if (empty($post['worker1_interface'])) {
		$input_errors[] = "Select one interface under 'Worker 1 Interface' to enable Worker 1.";
	}

	$worker2host = trim($post['worker2_host']);
	if (!is_ipaddr($worker2host) && !is_domain($worker2host)) {
		$input_errors[] = "'Worker 2' must contain a valid IP address or domain name.";
	}
	
	if (empty($post['worker2_interface'])) {
		$input_errors[] = "Select one interface under 'Worker 2 Interface' to enable Worker 2.";
	}
}

function zeek_validate_script($post, &$input_errors) {
	if (!zeek_enabled()) {
		$input_errors[] = 'Enabled Zeek instance first';
	} else {
		if (substr($post['zeekscriptpath'], -1, 1) == '/') {
			$input_errors[] = 'Script location must not end with a / character.';
		}

		if ($post['zeekscriptpath'][0] != '/') {
			$input_errors[] = 'Script location must start with a / character.';
		}

		if (!file_exists($post['zeekscriptpath'])) {
			$input_errors[] = "Configured script file does not exist.";
		}

		$ext = pathinfo($post['zeekscriptpath'], PATHINFO_EXTENSION);
		if ($ext!="zeek") {
			$input_errors[] = "Configured script file has no valid extension. The extension must be 'zeek'";
		}
	}
}

function zeek_settings_resync() {
	$networksfile =  ZEEK_ETC . '/networks.cfg';
	$nodefile =  ZEEK_ETC . '/node.cfg';

	if (!zeek_enabled()) {
		zeek_restart_services();
		zeek_install_cron(false);
		unlink_if_exists("/usr/local/etc/rc.d/zeek.sh");
		return;
	}

	$settings = config_get_path('installedpackages/zeek/config/0', []);

	$hostname = ($settings['hostname'] ? $settings['hostname'] : 'localhost');
	// Read assigned interface
	if ($settings['active_interface']) {
		$ifaces =  $settings['active_interface'];
	} else {
		$ifaces = array();
	}

	$iface = get_real_interface($ifaces);
	if ($iface) {
		$conf = '';
		$conf .= <<< EOD
# Example ZeekControl node configuration.
#
# This example has a standalone node ready to go except for possibly changing
# the sniffing interface.

# This is a complete standalone configuration.  Most likely you will
# only need to change the interface.
[zeek]
type=standalone
host={$hostname}
interface={$iface}

## Below is an example clustered configuration. If you use this,
## remove the [zeek] node above.

#[logger]
#type=logger
#host=localhost
#
#[manager]
#type=manager
#host=localhost
#
#[proxy-1]
#type=proxy
#host=localhost
#
#[worker-1]
#type=worker
#host=localhost
#interface=eth0
#
#[worker-2]
#type=worker
#host=localhost
#interface=eth0

EOD;

		file_put_contents($nodefile, $conf);
		chmod($nodefile, 0640);
	}

	$conf = '';
	$conf .= <<< EOD
# List of local networks in CIDR notation, optionally followed by a
# descriptive tag.
# For example, "10.0.0.0/8" or "fe80::/64" are valid prefixes.

EOD;

	$local_networks = preg_replace("/\s+/","\n", zeek_text_area_decode($settings['local_networks']));
	if (!empty($local_networks)) {
		$conf .= "\n{$local_networks}";
	}

	file_put_contents($networksfile, $conf);
	chmod($networksfile, 0640);

	zeek_sync_on_changes();
	zeek_write_rcfile();
	zeek_restart_services();
	zeek_install_cron(true);
}

function zeek_zeekctl_resync() {
	$settings = config_get_path('installedpackages/zeekcontrol/config/0', []);

	$email = $settings['admin_email'] ? $settings['admin_email'] : 'admin@localhost';
	$mailconnection = ($settings['mail_connection_summary'] == "on") ? 1 : 0;
	$diskspace = $settings['min_disk_space'] ? $settings['min_disk_space'] : 5;
	$mailhostupdown = ($settings['mail_host_updown'] == "on") ? 1 : 0;
	$rotate = $settings['log_rotate'] ?  $settings['log_rotate'] : 3000;
	$logexpiration = $settings['log_expiration'] ? $settings['log_expiration'] : 0;
	$statslog = ($settings['stats_log'] == "on") ? 1 : 0;
	$statsduration = $settings['stats_duration'] ? $settings['stats_duration'] : 0;
	$logdir = $settings['log_dir'] ? $settings['log_dir'] : '/usr/local/logs';
	if (!is_dir($logdir)) {
		logger(LOG_INFO, localize_text("Creating Zeek log dir '%s' ...", $logdir), LOG_PREFIX_PKG_ZEEK);
		safe_mkdir($logdir, 0755);
	}

	$conf = '';
	$conf .= <<< EOD
## Global ZeekControl configuration file.

###############################################
# Mail Options

# Recipient address for all emails sent out by Zeek and ZeekControl.
MailTo = {$email}

# Location of the sendmail binary
SendMail = /usr/sbin/sendmail

# Mail connection summary reports each log rotation interval.  A value of 1
# means mail connection summaries, and a value of 0 means do not mail
# connection summaries.  This option has no effect if the trace-summary
# script is not available.
MailConnectionSummary = {$mailconnection}

# Lower threshold (in percentage of disk space) for space available on the
# disk that holds SpoolDir. If less space is available, "zeekctl cron" starts
# sending out warning emails.  A value of 0 disables this feature.
MinDiskSpace = {$diskspace}

# Send mail when "zeekctl cron" notices the availability of a host in the
# cluster to have changed.  A value of 1 means send mail when a host status
# changes, and a value of 0 means do not send mail.
MailHostUpDown = {$mailhostupdown}

###############################################
# Logging Options

# Rotation interval in seconds for log files on manager (or standalone) node.
# A value of 0 disables log rotation.
LogRotationInterval = {$rotate}

# Expiration interval for archived log files in LogDir.  Files older than this
# will be deleted by "zeekctl cron".  The interval is an integer followed by
# one of these time units:  day, hr, min.  A value of 0 means that logs
# never expire.
LogExpireInterval = {$logexpiration}

# Enable ZeekControl to write statistics to the stats.log file.  A value of 1
# means write to stats.log, and a value of 0 means do not write to stats.log.
StatsLogEnable = {$statslog}

# Number of days that entries in the stats.log file are kept.  Entries older
# than this many days will be removed upon running "zeekctl cron".  A value of 0
# means that entries never expire.
StatsLogExpireInterval = {$statsduration}

###############################################
# Other Options

# Show all output of the zeekctl status command.  If set to 1, then all output
# is shown.  If set to 0, then zeekctl status will not collect or show the peer
# information (and the command will run faster).
StatusCmdShowAll = 0

# Site-specific policy script to load. Zeek will look for this in
# {\$PREFIX}/share/zeek/site. A default local.zeek comes preinstalled
# and can be customized as desired.
SitePolicyScripts = local.zeek

# Location of the log directory where log files will be archived each rotation
# interval.
LogDir = {$logdir}

# Location of the spool directory where files and data that are currently being
# written are stored.
SpoolDir = /usr/local/spool

# Location of other configuration files that can be used to customize
# ZeekControl operation (e.g. local networks, nodes).
CfgDir = /usr/local/etc

EOD;

	$zeekcontrolfile =  ZEEK_ETC . '/zeekctl.cfg';

	file_put_contents($zeekcontrolfile, $conf);
	chmod($zeekcontrolfile, 0640);

	zeek_sync_on_changes();
	zeek_restart_services();
}

function zeek_cluster_resync() {
	$settings = config_get_path('installedpackages/zeekcluster/config/0', []);

	$managerhost = ($settings['manager_host'] ? $settings['manager_host'] : 'localhost');
	$proxyhost = ($settings['proxy_host'] ? $settings['proxy_host'] : 'localhost');
	$worker1host = ($settings['worker1_host'] ? $settings['worker1_host'] : 'localhost');
	$worker2host = ($settings['worker2_host'] ? $settings['worker2_host'] : 'localhost');

	if ($settings['worker1_interface']) {
		$worker1iface =  $settings['worker1_interface'];
	} else {
		$worker1iface = array();
	}
	$iface1 = get_real_interface($worker1iface);

	if ($settings['worker2_interface']) {
		$worker2iface =  $settings['worker2_interface'];
	} else {
		$worker2iface = array();
	}
	$iface2 = get_real_interface($worker2iface);

	if ($iface1 && $iface2) {
		$conf = '';
		$conf .= <<< EOD
# Example ZeekControl node configuration.
#
# This example has a standalone node ready to go except for possibly changing
# the sniffing interface.

# This is a complete standalone configuration.  Most likely you will
# only need to change the interface.
#[zeek]
#type=standalone
#host=localhost
#interface=eth0

## Below is an example clustered configuration. If you use this,
## remove the [zeek] node above.

#[logger]
#type=logger
#host=localhost
#
[manager]
type=manager
host={$managerhost}
#
[proxy-1]
type=proxy
host={$proxyhost}
#
[worker-1]
type=worker
host={$worker1host}
interface={$iface1}
#
[worker-2]
type=worker
host={$worker2host}
interface={$iface2}

EOD;

		$nodefile =  ZEEK_ETC . '/node.cfg';

		file_put_contents($nodefile, $conf);
		chmod($nodefile, 0640);

	}
	zeek_sync_on_changes();
	zeek_restart_services();
}

function zeek_log_resync() {
	global $zeeklogstate;
	$zeeklogstate = false;

	$settings = config_get_path('installedpackages/zeeklog/config/0', []);

	if ($settings['remove_zeek_log'] == "on") {
		$zeeklogstate = true;
	}
	return $zeeklogstate;
}

function zeek_script_resync() {
	$conf = '';
	$conf .= <<<EOD
##! Local site policy. Customize as appropriate.
##!
##! This file will not be overwritten when upgrading or reinstalling!

# This script logs which scripts were loaded during each run.
@load misc/loaded-scripts

# Apply the default tuning scripts for common tuning settings.
@load tuning/defaults

# Load the scan detection script.
@load misc/scan

# Log some information about web applications being used by users
# on your network.
@load misc/app-stats

# Detect traceroute being run on the network.
@load misc/detect-traceroute

# Generate notices when vulnerable versions of software are discovered.
# The default is to only monitor software found in the address space defined
# as "local".  Refer to the software framework's documentation for more
# information.
@load frameworks/software/vulnerable

# Detect software changing (e.g. attacker installing hacked SSHD).
@load frameworks/software/version-changes

# This adds signatures to detect cleartext forward and reverse windows shells.
@load-sigs frameworks/signatures/detect-windows-shells

# Load all of the scripts that detect software in various protocols.
@load protocols/ftp/software
@load protocols/smtp/software
@load protocols/ssh/software
@load protocols/http/software
# The detect-webapps script could possibly cause performance trouble when
# running on live traffic.  Enable it cautiously.
#@load protocols/http/detect-webapps

# This script detects DNS results pointing toward your Site::local_nets
# where the name is not part of your local DNS zone and is being hosted
# externally.  Requires that the Site::local_zones variable is defined.
@load protocols/dns/detect-external-names

# Script to detect various activity in FTP sessions.
@load protocols/ftp/detect

# Scripts that do asset tracking.
@load protocols/conn/known-hosts
@load protocols/conn/known-services
@load protocols/ssl/known-certs

# This script enables SSL/TLS certificate validation.
@load protocols/ssl/validate-certs

# This script prevents the logging of SSL CA certificates in x509.log
@load protocols/ssl/log-hostcerts-only

# Uncomment the following line to check each SSL certificate hash against the ICSI
# certificate notary service; see http://notary.icsi.berkeley.edu .
# @load protocols/ssl/notary

# If you have libGeoIP support built in, do some geographic detections and
# logging for SSH traffic.
@load protocols/ssh/geo-data
# Detect hosts doing SSH bruteforce attacks.
@load protocols/ssh/detect-bruteforcing
# Detect logins using "interesting" hostnames.
@load protocols/ssh/interesting-hostnames

# Detect SQL injection attacks.
@load protocols/http/detect-sqli

#### Network File Handling ####

# Enable MD5 and SHA1 hashing for all files.
@load frameworks/files/hash-all-files

# Detect SHA1 sums in Team Cymru's Malware Hash Registry.
@load frameworks/files/detect-MHR

# Uncomment the following line to enable detection of the heartbleed attack. Enabling
# this might impact performance a bit.
# @load policy/protocols/ssl/heartbleed

EOD;

	if (zeek_enabled()) {
		foreach (config_get_path('installedpackages/zeekscript/config', []) as $script) {
			$path = $script['zeekscriptpath'];
			$filename = '';
			if ($script['zeekscriptpath'] == '') {
				$filename = '';
			} else {
				$filename = "@load ".basename($path,".zeek")."";
			}

			$srcpath =  escapeshellarg($path);
			$dstpath = escapeshellarg('/usr/local/share/zeek/site');
			copy($srcpath,  $dstpath);
			$newline = "\n";

			$conf .= <<<EOD
{$filename}
{$newline}

EOD;
		}

	$destpath = '/usr/local/share/zeek/site/local.zeek';

	file_put_contents($destpath, $conf);
	chmod($destpath, 0640);

	} else {
		logger(LOG_WARNING, localize_text("Enable Zeek instance first."), LOG_PREFIX_PKG_ZEEK);
	}
	zeek_sync_on_changes();
	zeek_restart_services();
}

/*
* Zeek XMLRPC sync
*/

// Uses XMLRPC to synchronize the changes to a remote node
function zeek_sync_on_changes() {
	$zeek_sync = config_get_path('installedpackages/zeeksync/config/0', []);
	$synconchanges = $zeek_sync['synconchanges'];
	$synctimeout = $zeek_sync['synctimeout'] ?: '250';
	switch ($synconchanges) {
		case "manual":
			if (is_array($zeek_sync['row'])) {
				$rs = $zeek_sync['row'];
			} else {
				logger(LOG_WARNING, localize_text("XMLRPC sync is enabled but there are no hosts configured as replication targets."), LOG_PREFIX_PKG_ZEEK);
				return;
			}
		break;
		default:
			return;
		break;
	}
	if (is_array($rs)) {
		logger(LOG_INFO, localize_text("XMLRPC sync is starting."), LOG_PREFIX_PKG_ZEEK);
		foreach ($rs as $sh) {
			// Only sync enabled replication targets
			if ($sh['syncdestinenable']) {
				$sync_to_ip = $sh['ipaddress'];
				$port = $sh['syncport'];
				$username = $sh['username'] ?: 'admin';
				$password = $sh['password'];
				$protocol = $sh['syncprotocol'];

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
				if (!is_port($port)) {
					$error .= "Misconfigured Replication Target Port. ";
					$valid = FALSE;
				}
				if ($valid) {
					zeek_do_xmlrpc_sync($sync_to_ip, $username, $password, $port, $protocol, $synctimeout);
				} else {
					logger(LOG_ERR, localize_text("XMLRPC sync with '%s' aborted due to the following error(s): %s", $sync_to_ip, $error), LOG_PREFIX_PKG_ZEEK);
				}
			}
		}
		logger(LOG_INFO, localize_text("XMLRPC sync completed."), LOG_PREFIX_PKG_ZEEK);
	}
}

// Do the actual XMLRPC sync
function zeek_do_xmlrpc_sync($sync_to_ip, $username, $password, $varsyncport, $varsyncprotocol, $varsynctimeout) {
	global $g;

	if ($username == "" || $password == "" || $sync_to_ip == "" || $varsyncport == "" || $varsyncprotocol == "") {
		logger(LOG_WARNING, localize_text("A required XMLRPC sync parameter (username, password, replication target, port or protocol) is empty ... aborting pkg sync"), LOG_PREFIX_PKG_ZEEK);
		return;
	}
	/* XML will hold the sections to sync */
	$xml = array();
	$xml['zeek'] = config_get_path('installedpackages/zeek');
	$xml['zeekcontrol'] = config_get_path('installedpackages/zeekcontrol');
	$xml['zeekcluster'] = config_get_path('installedpackages/zeekcluster');
	$xml['zeekscript'] = config_get_path('installedpackages/zeekscript');

	$synctimeout = intval($varsynctimeout);
	$execcmd = "require_once('/usr/local/pkg/zeek.inc');\n";
	$execcmd .= "zeek_all_after_XMLRPC_resync();";
	$rpc_client = new pfsense_xmlrpc_client();
	$rpc_client->setConnectionData($sync_to_ip, $varsyncport, $username, $password, $varsyncprotocol);
	$resp = $rpc_client->xmlrpc_method('merge_installedpackages_section', $xml, $synctimeout);
	/* Tell zeek to reload our settings on the destination sync host. */
	$resp = $rpc_client->xmlrpc_exec_php($execcmd, $synctimeout);
}

function zeek_plugin_xmlrpc_send() {
	if (config_get_path('installedpackages/zeeksync/config/0/synconchanges') != 'auto') {
		return [];
	}

	return [
		'installedpackages/zeeksync/config/0/synconchanges',
		'installedpackages/zeek',
		'installedpackages/zeekcontrol',
		'installedpackages/zeekcluster',
		'installedpackages/zeekscript'
	];
}

function zeek_plugin_xmlrpc_recv($new_sections) {
	if (array_get_path($new_sections, 'installedpackages/zeeksync/config/0/synconchanges') != 'auto') {
		return [];
	}

	$section_paths = [
		'installedpackages/zeek',
		'installedpackages/zeekcontrol',
		'installedpackages/zeekcluster',
		'installedpackages/zeekscript'
	];

	$ret = [
		'xmlrpc_recv_result' => false
	];
	foreach ($section_paths as $path) {
		$old_section = config_get_path($path, []);
		$new_section = array_get_path($new_sections, $path, []);

		// Don't save empty configuration sections.
		if (empty($old_section) && empty($new_section)) {
			continue;
		}
		// Ignore unchanged configuration sections.
		if ($old_section === $new_section) {
			continue;
		}

		$ret[$path] = array_merge($old_section, $new_section);
		$ret['xmlrpc_recv_result'] = true;
	}

	return $ret;
}

function zeek_plugin_xmlrpc_recv_done($xmlrpc_recv_result) {
	if (array_get_path($xmlrpc_recv_result, 'zeek/xmlrpc_recv_result') !== true) {
		return;
	}
	zeek_all_after_XMLRPC_resync();
}

function zeek_all_after_XMLRPC_resync() {
	zeek_zeekctl_resync();
	zeek_cluster_resync();
	zeek_script_resync();

	logger(LOG_INFO, localize_text("Finished XMLRPC process. For more information look at the host which started sync."), LOG_PREFIX_PKG_ZEEK);

	mwexec_bg(ZEEK_ETC . "/rc.d/zeek.sh restart");
}

?>
