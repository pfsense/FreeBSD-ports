<?php
/*
 * e2guardian.inc
 *
 * part of Unofficial packages for pfSense(R) softwate
 * Copyright (c) 2015-2017 Marcello Coutinho
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
require_once("functions.inc");
require_once("globals.inc");
require_once("interfaces.inc");
require_once("notices.inc");
require_once("pkg-utils.inc");
require_once("services.inc");
require_once("util.inc");
require_once("filter.inc");
$pfs_version = substr(trim(file_get_contents("/etc/version")),0,3);

require_once("xmlrpc_client.inc");

if (version_compare(PHP_VERSION, "7.3") < 0 && !function_exists("is_countable")) {
    function is_countable($var): bool{
        return (is_array($var) || is_object($var) || is_iterable($var) || $var instanceof Countable);
    }
}

$shortcut_section = "e2guardian";

$pf_version = substr(trim(file_get_contents("/etc/version")), 0, 3);

define('E2GUARDIAN_DIR', '/usr/local');

if ($_POST['clear_cert_cache'] == 'clear_cert_cache') {
    //clear MITM cert cache data
    $tc_dir = "/usr/local/etc/e2guardian/ssl/generatedcerts";
    if (e2g_delTree($tc_dir)) {
        mkdir ($tc_dir, "0755", true);
        mwexec_bg("/usr/local/sbin/e2guardian -Q");
        clear_subsystem_dirty('e2guardian');
    }
}

function e2g_delTree($dir) {
    //at least two slashes, two dirs and 10 char long
    if(preg_match("@/\w+/\w+@", $dir) && strlen($dir) > 10) {
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? e2g_delTree("$dir/$file") : unlink("$dir/$file");
        }
        
        return rmdir($dir);
    } else {
        return false;
    }
} 

function e2_text_area_decode($text) {
	return preg_replace('/\r\n/', "\n", base64_decode($text));
}

function e2g_check_sched($iface_sched){
	global $config, $e2g_sched_in_use;
	$a_schedules = &$config['schedules']['schedule'];
	$e2g_scd = true;
	foreach ($a_schedules as $schedule){
		if ($schedule['name'] == $iface_sched && $iface_sched <> 'Always active') {
			$e2g_scd = filter_get_time_based_rule_status($schedule);
		}
	}
	$e2g_sched_in_use[$iface_sched] = $e2g_scd;
}

function e2g_check_logdeniedcgi($logdeniedcgi) {
	$e2g_file = "/usr/local/pkg/e2guardian/e2gerror.php";
	if ($logdeniedcgi == 'on') {
		$e2g_log = "/var/log/e2guardian/denied.log";
	} else {
		 $e2g_log = "none";
	}
	exec("/usr/bin/sed -i 'bak' \"s@LOG_DENIED', '.*'@LOG_DENIED', '$e2g_log'@\" $e2g_file",$out);
	foreach (glob("/usr/local/www/wpad*/e2gerror.php") as $e2g_wpad) {
		copy ($e2g_file, $e2g_wpad);
	}
}

function e2g_watchdog_script($wpid, $wcmd, $winfo){
	$wlog = "/var/log/e2guardian/start.log";
	$wscript = <<<EOF
# watchdog script for {$winfo}
	if [ -f {$wpid} ];then
		cat {$wpid} | xargs ps
		if [ $? -ne 0 ]; then
			{$wcmd}
			echo "`date +'%a %b %d %T %Y'` {$winfo} start" >> {$wlog}
		fi
	else
		{$wcmd}
		echo "`date +'%a %b %d %T %Y'` {$winfo} start" >> {$wlog}
	fi
EOF;
	return($wscript);
}

function dg_get_real_interface_address($iface) {
	global $config;
	$iface = convert_friendly_interface_to_real_interface_name($iface);
	$line = trim(shell_exec("ifconfig $iface | grep inet | grep -v inet6"));
	list($dummy, $ip, $dummy2, $netmask) = explode(" ", $line);
	return array($ip, long2ip(hexdec($netmask)));
}

function check_ca_hashes() {
	global $config, $g;

	//check certificates
	$cert_count = 0;
	if (is_dir('/usr/local/share/certs')) {
		if ($handle = opendir('/usr/local/share/certs')) {
			while (false !== ($file = readdir($handle))) {
				if (preg_match ("/\d+.0/", $file)) {
					$cert_count++;
				}
			}
		}
	}
	closedir($handle);
	if ($cert_count < 10) {
		//create ca-root hashes from ca-root-nss package
		log_error("Creating root certificate bundle hashes from the Mozilla Project");
		$cas = file('/usr/local/share/certs/ca-root-nss.crt');
		$cert = 0;
		foreach ($cas as $ca) {
			if (preg_match("/--BEGIN CERTIFICATE--/", $ca)) {
				$cert = 1;
			}
			if ($cert == 1) {
				$crt .= $ca;
			}
			if (preg_match("/-END CERTIFICATE-/", $ca)) {
				file_put_contents("/tmp/cert.pem", $crt, LOCK_EX);
				$cert_hash = array();
				exec("/usr/bin/openssl x509 -hash -noout -in /tmp/cert.pem", $cert_hash);
				file_put_contents("/usr/local/share/certs/". $cert_hash[0] . ".0", $crt, LOCK_EX);
				$crt = "";
				$cert = 0;
			}
		}
	}
}

/*
 * E2guardian firewall rules configuration
 */

function e2g_generate_rules($type) {
	global $config;
	$e2g_conf = $config['installedpackages']['e2guardian']['config'][0];

	// Do not install any firewall rules if E2guardian is disabled
	if ($e2g_conf['enable'] != "on") {
		log_error("[E2guardian] Installed but disabled. Not installing '{$type}' rules.");
		return;
	}

	// Proxy Interface(s)
	$proxy_ifaces = explode(",", $e2g_conf['interface']);
	$proxy_ifaces = array_map('convert_friendly_interface_to_real_interface_name', $proxy_ifaces);
	// Transparent Proxy Interface(s)
	if ($e2g_conf['transparent_proxy'] == "on") {
	    if (! in_array('lo0',$proxy_ifaces)) {
	        $proxy_ifaces[] = 'lo0';
	    }
		$transparent_ifaces = explode(",", $e2g_conf['transparent_active_interface']);
		$transparent_ifaces = array_map('convert_friendly_interface_to_real_interface_name', $transparent_ifaces);
	} else {
		$transparent_ifaces = array();
	}
	// SSL Intercept Interface(s)
	if ($e2g_conf['enablessl'] == "on") {
		$ssl_ifaces = $transparent_ifaces;
	} else {
		$ssl_ifaces = array();
	}

	// Define proxy ports
	$port = ($e2g_conf['filterports'] ? $e2g_conf['filterports'] : 8080);
	$ssl_port = ($e2g_conf['ssl_proxy_port'] ? $e2g_conf['ssl_proxy_port'] : 8081);
	$pf_rule_ports = "{{$port},{$ssl_port}}";

	// Define NAT ports - 80 and 443 if SSL filtering is enabled
	$pf_nat_ports = ($e2g_conf['enablessl'] == "on" ? "{80,443}" : "80");

	/*
	 * When transparent proxy is enabled and we are doing NAT, use rdr pass to pass traffic
	 * For PPPoE server, the $pppoe below is a pf macro, not a PHP variable, needs to be escaped.
	 */
	switch($type) {
		case 'nat':
			// No NAT rules if transparent proxy is not enabled
			if ($e2g_conf['transparent_proxy'] != 'on') {
				break;
			}
			$rules .= "\n# Setup E2guardian proxy redirect\n";
			/* Bypass Proxy for Private Address Destination - RFC1918 */
			if ($e2g_conf['private_subnet_proxy_off'] == 'on') {
				foreach ($transparent_ifaces as $iface) {
					$pf_transparent_rule_port = (in_array($iface, $ssl_ifaces) ? "{80,443}" : "80");
					$rules .= "no rdr on $iface proto tcp from any to { 192.168.0.0/16, 172.16.0.0/12, 10.0.0.0/8 } port {$pf_transparent_rule_port}\n";
				}
				/* Handle PPPOE case */
				if (($config['pppoe']['mode'] == "server" && $config['pppoe']['localip']) || (function_exists("is_pppoe_server_enabled") && is_pppoe_server_enabled())) {
					$rules .= "no rdr on \$pppoe proto tcp from any to { 192.168.0.0/16, 172.16.0.0/12, 10.0.0.0/8 } port {$pf_nat_ports}\n";
				}
			}
			/* Bypass Proxy for These Source IPs */
			if (!empty($e2g_conf['defined_ip_proxy_off'])) {
				$defined_ip_proxy_off = explode(";", $e2g_conf['defined_ip_proxy_off']);
				$exempt_ip = "";
				foreach ($defined_ip_proxy_off as $ip_proxy_off) {
					if (!empty($ip_proxy_off)) {
						$ip_proxy_off = trim($ip_proxy_off);
						if (is_alias($ip_proxy_off)) {
							$ip_proxy_off = '$' . $ip_proxy_off;
						}
						$exempt_ip .= ", $ip_proxy_off";
					}
				}
				$exempt_ip = substr($exempt_ip, 2);
				foreach ($transparent_ifaces as $iface) {
					$pf_transparent_rule_port = (in_array($iface, $ssl_ifaces) ? "{80,443}" : "80");
					$rules .= "no rdr on $iface proto tcp from { $exempt_ip } to any port {$pf_transparent_rule_port}\n";
				}
				/* Handle PPPOE case */
				if (($config['pppoe']['mode'] == "server" && $config['pppoe']['localip']) || (function_exists("is_pppoe_server_enabled") && is_pppoe_server_enabled())) {
					$rules .= "no rdr on \$pppoe proto tcp from { $exempt_ip } to any port {$pf_nat_ports}\n";
				}
			}
			/* Bypass Proxy for These Destination IPs */
			if (!empty($e2g_conf['defined_ip_proxy_off_dest'])) {
				$defined_ip_proxy_off_dest = explode(";", $e2g_conf['defined_ip_proxy_off_dest']);
				$exempt_dest = "";
				foreach ($defined_ip_proxy_off_dest as $ip_proxy_off_dest) {
					if (!empty($ip_proxy_off_dest)) {
						$ip_proxy_off_dest = trim($ip_proxy_off_dest);
						if (is_alias($ip_proxy_off_dest)) {
							$ip_proxy_off_dest = '$' . $ip_proxy_off_dest;
						}
						$exempt_dest .= ", $ip_proxy_off_dest";
					}
				}
				$exempt_dest = substr($exempt_dest, 2);
				foreach ($transparent_ifaces as $iface) {
					$pf_transparent_rule_port = (in_array($iface, $ssl_ifaces) ? "{80,443}" : "80");
					$rules .= "no rdr on $iface proto tcp from any to { $exempt_dest } port {$pf_transparent_rule_port}\n";
				}
				/* Handle PPPOE case */
				if (($config['pppoe']['mode'] == "server" && $config['pppoe']['localip']) || (function_exists("is_pppoe_server_enabled") && is_pppoe_server_enabled())) {
					$rules .= "no rdr on \$pppoe proto tcp from any to { $exempt_dest } port {$pf_nat_ports}\n";
				}
			}
			/* Transparent Proxy Interface(s) */
			foreach ($transparent_ifaces as $t_iface) {
				$rules .= "rdr on $t_iface proto tcp from any to ! 127.0.0.1 port 80 -> 127.0.0.1 port {$port}\n";
				if (in_array($t_iface, $ssl_ifaces)) {
					$rules .= "rdr on $t_iface proto tcp from any to ! 127.0.0.1 port 443 -> 127.0.0.1 port {$ssl_port}\n";
				}
			}
			/*
			 * Transparent Proxy Interface(s) - handle the PPPOE case
			 * For PPPoE server, mpd uses a group of different _local_ interfaces
			 * The rules below are needed so that the clients can be transparently proxied
			 */
			if (($config['pppoe']['mode'] == "server" && $config['pppoe']['localip']) || (function_exists("is_pppoe_server_enabled") && is_pppoe_server_enabled())) {
				$rules .= "rdr pass on \$pppoe proto tcp from any to ! 127.0.0.1 port 80 -> 127.0.0.1 port {$port}\n";
				if ($e2g_conf['ssl_proxy'] == "on") {
					$rules .= "rdr pass on \$pppoe proto tcp from any to ! 127.0.0.1 port 443 -> 127.0.0.1 port {$ssl_port}\n";
				}
			}
			$rules .= "\n";
			break;
		case 'filter':
		case 'rule':
			/*
			 * Non-Transparent Proxy Interface(s)
			 * Pass traffic to proxy ports on the proxy interface(s) when 'Allow Users on Interface' is enabled
			 * XXX: Consider ACLs > Allowed Subnets
			 */
			if ($e2g_conf['allow_interface'] == 'on') {
				foreach ($proxy_ifaces as $iface) {
				    //TODO verify bandwidth under group settings too
				    if (preg_match("/(\d+)/", $e2g_conf['inpipe'], $d1)){
				        $pipe = "dnpipe ( {$d1[1]}";
				        if (preg_match("/(\d+)/", $e2g_conf['outpipe'], $d2)){
				            $pipe .= ",{$d2[1]}";
				        }
				        $pipe .= ")";
				    }
					$rules .= "# Setup E2guardian pass rules for proxy\n";
					$rules .= "pass in quick on $iface proto tcp from any to ($iface) port {$pf_rule_ports} flags S/SA keep state {$pipe}\n";
					$rules .= "\n";
				}
				/* Handle PPPOE case */
				if ($config['pppoe']['mode'] == "server" && $config['pppoe']['localip']) {
					$rules .= "pass in quick on \$pppoe proto tcp from any to {$config['pppoe']['localip']} port {$pf_rule_ports} flags S/SA keep state {$pipe}\n";
				}
			}
			break;
		default:
			break;
	}
	return $rules;
}

function sync_package_e2guardian($via_rpc = "no", $install_process = false) {
	global $config, $g, $savemsg, $e2g_sched_in_use;

	// detect boot process
	if (is_array($_POST)) {
		if (preg_match("/\w+/", $_POST['__csrf_magic'])) {
			unset($boot_process);
		} else {
			$boot_process = "on";
		}
	}

	if (is_process_running('e2guardian') && isset($boot_process) && $via_rpc == "no") {
		log_error("[E2guardian] - Detected boot process pr:" . is_process_running('e2guardian') . " bp:" . isset($boot_process) . " rpc:" . $via_rpc);
        log_error("[E2guardian] - Aborting config file generation");
		return;
	} else {
		log_error("[E2guardian] - Save settings package call pr:" . is_process_running('e2guardian') . " bp:" . isset($boot_process) . " rpc:" . $via_rpc);
	}

	// assign xml arrays
	if (!is_array($config['installedpackages']['e2guardian'])) {
		$config['installedpackages']['e2guardian']['config'][0] = array(
			'interface' => 'lo0',
			'filterports' => '8080',
			'parentmode' => 'direct',
			'watchdog'   => 'on',
			'applyaction' => '-Q',
			'httpworkers' => '256',
			'daemon_options' => 'softrestart');
	}
	$e2guardian = $config['installedpackages']['e2guardian']['config'][0];
	if (!is_array($config['installedpackages']['e2guardianconfig'])) {
		$config['installedpackages']['e2guardianconfig']['config'][0] = array(
			'auth_plugin' => '',
			'scan_options' => 'scancleancache,createlistcachefiles,deletedownloadedtempfiles',
			'filtermode' => '1',
			'weightedphrasemode' => '2',
			'preservecase' => '0',
			'phrasefiltermode' => '2',
			'cron' => 'day');
	}
	$e2guardian_config= $config['installedpackages']['e2guardianconfig']['config'][0];
	if (!is_array($config['installedpackages']['e2guardianlog'])) {
		$config['installedpackages']['e2guardianlog']['config'][0] = array(
			'report_level' => '3',
			'report_language' => 'ukenglish',
			'report_options' => 'showweightedfound,usecustombannedimage,nonstandarddelimiter',
			'logging_options' => 'logconnectionhandlingerrors',
			'loglevel' => '3',
			'logexceptionhits' => '2',
			'logfileformat' => '1',
            'logrotate' => 'on',
		    'cronminute' => '0',
		    'cronhour' => '0'
		);
	}
	$e2guardian_log = $config['installedpackages']['e2guardianlog']['config'][0];
	if (is_array($config['installedpackages']['e2guardianlimits'])) {
		$e2guardian_limits = $config['installedpackages']['e2guardianlimits']['config'][0];
	}
	if (is_array($config['installedpackages']['e2guardianusers'])) {
		$e2guardian_users = $config['installedpackages']['e2guardianusers']['config'][0];
	}
	if (is_array($config['installedpackages']['e2guardianblacklist']['config'])) {
		$e2guardian_blacklist = $config['installedpackages']['e2guardianblacklist']['config'][0];
	}

	// daemon options
	$e2guardian_enabled = $e2guardian['enable_dg'];
	$filterport = ($e2guardian['filterports'] ? $e2guardian['filterports'] : "8080");
	$softrestart = (preg_match('/softrestart/', $e2guardian['daemon_options']) ? "on" : "off");
	$nodaemon = (preg_match('/nodaemon/', $e2guardian['daemon_options']) ? "yes" : "off");
	$httpworkers=($e2guardian['httpworkers'] ? $e2guardian['httpworkers'] : "100");
	$preforkchildren = ($e2guardian['preforkchildren']? $e2guardian['preforkchildren'] : "10");
	switch ($e2guardian['parentmode']) { 
		case "remote":
			$proxyip = ($e2guardian['proxyip'] ? $e2guardian['proxyip'] : "127.0.0.1");
			$proxyport = ($e2guardian['proxyport'] ? $e2guardian['proxyport'] : "8888");
			break;
		case "direct":
			$proxyip = "";
        	        $proxyport = "";
			break;
		case "local":
			$proxyip = "127.0.0.1";
			$proxyport = "8888";
			break;
	}
	$proxytimeout = ($e2guardian['proxytimeout'] ? $e2guardian['proxytimeout'] : "30");

	// general options
	$urlcachenumber = ($e2guardian_config['urlcachenumber'] ? $e2guardian_config['urlcachenumber'] : "1000");
	$urlcacheage = ($e2guardian_config['urlcacheage'] ? $e2guardian_config['urlcacheage'] : "900");
	$scancleancache = (preg_match('/scancleancache/', $e2guardian_config['scan_options']) ? "on" : "off");
	$hexdecodecontent = (preg_match('/hexdecodecontent/', $e2guardian_config['scan_options']) ? "on" : "off");
	$forcequicksearch = (preg_match('/forcequicksearch/', $e2guardian_config['scan_options']) ? "on" : "off");
	$reverseaddresslookups = (preg_match('/reverseaddresslookups/', $e2guardian_config['scan_options']) ? "on" : "off");
	$reverseclientiplookups = (preg_match('/reverseclientiplookups/', $e2guardian_config['scan_options']) ? "on" : "off");
	$logclienthostnames = (preg_match('/logclienthostnames/', $e2guardian_config['scan_options']) ? "on" : "off");
	$createlistcachefiles = (preg_match('/createlistcachefiles/', $e2guardian_config['scan_options']) ? "on" : "off");
	$prefercachedlists = (preg_match('/prefercachedlists/', $e2guardian_config['scan_options']) ? "on" : "off");
	$deletedownloadedtempfiles = (preg_match('/deletedownloadedtempfiles/', $e2guardian_config['scan_options']) ? "on" : "off");
	$weightedphrasemode = ($e2guardian_config['weightedphrasemode'] ? $e2guardian_config['weightedphrasemode'] : "2");
	$phrasefiltermode = ($e2guardian_config['phrasefiltermode'] ? $e2guardian_config['phrasefiltermode'] : "2");
	$preservecase = ($e2guardian_config['preservecase'] ? $e2guardian_config['preservecase'] : "0");
	$clamdscan = (preg_match('/clamdscan/', $e2guardian_config['content_scanners']) ? "on" : "off");
	$icapscan = (preg_match('/icapscan/', $e2guardian_config['content_scanners']) ? "on" : "off");
	$contentscannertimeout = ($e2guardian_config['contentscannertimeout'] ? $e2guardian_config['contentscannertimeout'] : "60");
	$contentscanexceptions = ($e2guardian_config['contentscanexceptions'] ? "on" : "off");
	$recheckreplacedurls = (preg_match('/recheckreplacedurls/', $e2guardian_config['misc_options']) ? "on" : "off");
	$forwardedfor = (preg_match('/(^|\W)forwardedfor/', $e2guardian_config['misc_options']) ? "on" : "off");
	$recheckreplacedurls = (preg_match('/icapscan/', $e2guardian_config['misc_options']) ? "on" : "off");
	$usexforwardedfor = (preg_match('/usexforwardedfor/', $e2guardian_config['misc_options']) ? "on" : "off");
	$authplugin = (preg_match('/usr/', $e2guardian_config['auth_plugin']) ? "authplugin = '" . $e2guardian_config['auth_plugin'] . "'" : "");
	if ($e2guardian_config['auth_plugin'] != "") {
		$auth_plugins = explode(",", $e2guardian_config['auth_plugin']);
		$authplugin = "";
		$auth_prefix = E2GUARDIAN_DIR . "/etc/e2guardian/authplugins/";
		foreach ($auth_plugins as $auth_selected) {
			if ($auth_selected != "none") {
				if ($auth_selected == "dnsauth.conf") {
					$dns_conf = file_get_contents($auth_prefix.$auth_selected);
					if (preg_match("/redirect_to_auth = .no/",$dns_conf)) {
						 $authplugin = "authplugin= '{$auth_prefix}{$auth_selected}'\n" . $authplugin;
					} else {
						$authplugin .= "authplugin= '{$auth_prefix}{$auth_selected}'\n";
					}
				} else {
					$authplugin .= "authplugin= '{$auth_prefix}{$auth_selected}'\n";
				}
			}
		}
	}

	$xforwardedforfilterip = "";
	if ($e2guardian_config['xforwardedforfilterip'] != "") {
		$xforwardip_list= preg_replace("/\s+/",",",base64_decode($e2guardian_config['xforwardedforfilterip']));
		if (preg_match("/\w+/",$xforwardip_list)) {
 			$xforwardip_array= explode(",",$xforwardip_list);
			foreach ($xforwardip_array as $xforwardip) {
				$xforwardedforfilterip .= "xforwardedforfilterip = {$xforwardip}\n";
			}
		} 
	}

	if ($e2guardian_config['contentscannericapurl'] != "") {
		include("/usr/local/pkg/e2guardian/templates/icapscan.conf.template");
		file_put_contents(E2GUARDIAN_DIR . "/etc/e2guardian/contentscanners/icapscan.conf", $icap_conf_file , LOCK_EX);
	}
	// limits
	// moved to groups
	//$maxuploadsize = ($e2guardian_limits['maxuploadsize'] ? $e2guardian_limits['maxuploadsize'] : "-1");
	$maxcontentfiltersize = ($e2guardian_limits['maxcontentfiltersize'] ? $e2guardian_limits['maxcontentfiltersize'] : "256");
	$maxcontentramcachescansize = ($e2guardian_limits['maxcontentramcachescansize'] ? $e2guardian_limits['maxcontentramcachescansize'] : "1000");
	$maxcontentfilecachescansize = ($e2guardian_limits['maxcontentfilecachescansize'] ? $e2guardian_limits['maxcontentfilecachescansize'] : "2000");
	$initialtrickledelay = ($e2guardian_limits['initialtrickledelay'] ? $e2guardian_limits['initialtrickledelay'] : "20");
	$trickledelay = ($e2guardian_limits['trickledelay'] ? $e2guardian_limits['trickledelay'] : "20");

	// report and log
	$reportlevel = ($e2guardian_log['report_level'] ? $e2guardian_log['report_level'] : "-1");
	if ($reportlevel == 1 || $reportlevel == 2) {
		if (is_URL($e2guardian_log['reportingcgi'])) {
			$accessdeniedaddress = "accessdeniedaddress = '" . $e2guardian_log['reportingcgi'] . "'";
			e2g_check_logdeniedcgi($e2guardian_log['logdeniedcgi']);
		} else {
			log_error("e2guardian - {$e2guardian_log['reportingcgi']} is not a valid access denied url");
			file_notice("e2guardian",$error,"E2guardian - ". $e2guardian_log['reportingcgi'] . " " . gettext("is not a valid access denied url"), "");
		}
	}
	$accessdenied = ($e2guardian_log['reportingcgi'] ? $e2guardian_log['report_level'] : "3");
	$reportlanguage = ($e2guardian_log['report_language'] ? $e2guardian_log['report_language'] : "ukenglish");
	$showweightedfound = (preg_match('/showweightedfound/', $e2guardian_log['report_options']) ? "on" : "off");
	$usecustombannedflash = (preg_match('/usecustombannedflash/', $e2guardian_log['report_options']) ? "on" : "off");
	if (file_exists(E2GUARDIAN_DIR . '/share/e2guardian/blockedflash.swf')) {
		$custombannedflashfile = "custombannedflashfile = '" . E2GUARDIAN_DIR . "/share/e2guardian/blockedflash.swf'";
	}
	$usecustombannedimage = (preg_match('/usecustombannedimage/', $e2guardian_log['report_options'])? "on" : "off");
	$nonstandarddelimiter = (preg_match('/nonstandarddelimiter/', $e2guardian_log['report_options'])? "on" : "off");

	$logchildprocesshandling = (preg_match('/logchildprocesshandling/', $e2guardian_log['logging_options']) ? "on" : "off");
	$logconnectionhandlingerrors = (preg_match('/logconnectionhandlingerrors/', $e2guardian_log['logging_options']) ? "on" : "off");
	$nologger = (preg_match('/nologger/', $e2guardian_log['logging_options']) ? "on" : "off");
	$logadblocks = (preg_match('/logadblocks/', $e2guardian_log['logging_options']) ? "on" : "off");
	$anonymizelogs = (preg_match('/anonymizelogs/', $e2guardian_log['logging_options']) ? "on" : "off");
	$logsslerrors = (preg_match('/logsslerrors/', $e2guardian_log['logging_options']) ? "on" : "off");
	$loglevel = ($e2guardian_log['loglevel'] ? $e2guardian_log['loglevel'] : "2");
	$logexceptionhits = ($e2guardian_log['logexceptionhits'] ? $e2guardian_log['logexceptionhits'] : "2");
	$logfileformat = ($e2guardian_log['logfileformat'] ? $e2guardian_log['logfileformat'] : "1");
	$logrotate = ($e2guardian_log['logrotate']  ? "on" : "off");
	$cronminute = ($e2guardian_log['cronminute'] ? $e2guardian_log['cronminute'] : "0");
	$cronhour = ($e2guardian_log['cronhour']  ? $e2guardian_log['cronhour'] : "0");
	$logfilecount = ($e2guardian_log['logcount'] ? $e2guardian_log['logcount'] : "30");

	
	// check files
	#create sample files
$files = array(
		"/e2guardianf1.conf",
		"/lists/filtergroupslist",
		"/lists/bannedphraselist",
		"/lists/exceptionphraselist",
		"/lists/weightedphraselist",
		"/lists/exceptionsitelist",
		"/lists/bannedsitelist",
		"/lists/bannedsitewithbypasslist",
		"/lists/greysitelist",
		"/lists/logsitelist",
		"/lists/bannedregexpurllist",
		"/lists/sslsiteregexplist",
		"/lists/bannedurllist",
		"/lists/exceptionregexpurllist",
		"/lists/exceptionurllist",
		"/lists/greyurllist",
		"/lists/logregexpurllist",
		"/lists/logurllist",
		"/lists/urlregexplist",
		"/lists/exceptionfilesitelist",
		"/lists/exceptionfileurllist",
		"/lists/searchregexplist",
		"/lists/bannedsearchlist",
		"/lists/contentregexplist",
		"/lists/exceptionextensionlist",
		"/lists/bannedextensionlist",
		"/lists/exceptionmimetypelist",
		"/lists/bannedmimetypelist",
		"/lists/headerregexplist",
		"/lists/bannedregexpheaderlist",
        "/lists/exceptionregexpheaderlist",
		"/lists/authplugins/ipgroups",
		"/lists/contentscanners/exceptionvirusextensionlist",
		"/lists/contentscanners/exceptionvirusmimetypelist",
		"/lists/contentscanners/exceptionvirussitelist",
		"/lists/contentscanners/exceptionvirusurllist",
		"/lists/exceptioniplist",
		"/lists/pics");

	$e2guardian_dir = E2GUARDIAN_DIR . "/etc/e2guardian";

	if (file_exists("{$e2guardian_dir}/lists/bannedsitelistwithbypass")) {
		rename("{$e2guardian_dir}/lists/bannedsitelistwithbypass", "{$e2guardian_dir}/lists/bannedsitewithbypasslist");
		unlink_if_exists("{$e2guardian_dir}/lists/bannedsitewithbypasslist.sample");
        }

	foreach ($files as $file) {
		if (!file_exists($e2guardian_dir . $file.'.sample')) {
			$new_file = "";
			$install_file = file($e2guardian_dir . $file);
			foreach ($install_file as $line) {
				if (!preg_match("/Include/", $line)) {
					$new_file .= $line;
				}
			}
			file_put_contents($e2guardian_dir . $file.'.sample', $new_file, LOCK_EX);
		}
	}

	$load_samples = 0;

	#ssl men-in-the-middle feature
	$dirs = array("/var/log/e2guardian", "/var/log/e2guardian/stats", "/etc/ssl/demoCA", "/etc/ssl/demoCA/private", "/etc/ssl/demoCA/crl", "/etc/ssl/demoCA/certs", $e2guardian_dir . "/ssl/generatedcerts", $e2guardian_dir . "/ssl/generatedlinks");
	foreach ($dirs as $dir) {
		if (!is_dir($dir)) {
			mkdir ($dir, 0755, true);
		}
	}

	$enablessl=($e2guardian["enablessl"] == "on" ? "on" : "off");
	$ca_cert = lookup_ca($e2guardian["dca"]);
	if ($ca_cert != false) {
		if (base64_decode($ca_cert['prv'])) {
			file_put_contents("/etc/ssl/demoCA/private/cakey.pem", base64_decode($ca_cert['prv']));
			$ca_pk = "caprivatekeypath = '/etc/ssl/demoCA/private/cakey.pem'";
		}
		if (base64_decode($ca_cert['crt'])) {
			$cert_hash = array();
			file_put_contents("/etc/ssl/demoCA/cacert.pem", base64_decode($ca_cert['crt']));
			exec("/usr/bin/openssl x509 -hash -noout -in /etc/ssl/demoCA/cacert.pem", $cert_hash);
			file_put_contents("/usr/local/share/certs/" . $cert_hash[0] . ".0", base64_decode($ca_cert['crt']));
			$ca_pem = "cacertificatepath = '/etc/ssl/demoCA/cacert.pem'";
			$generatedcertpath= "generatedcertpath = '" . $e2guardian_dir . "/ssl/generatedcerts'";
		}
		$certprivatekeypath='/etc/ssl/demoCA/private/serverkey.pem';
		if (! file_exists($certprivatekeypath)) {
			system("openssl genrsa 4096 > $certprivatekeypath");		
		}
		$cert_key = "certprivatekeypath = '{$certprivatekeypath}' ";
		/*
		$svr_cert = lookup_cert($e2guardian_config["dcert"]);
		if ($svr_cert != false) {
			if (base64_decode($svr_cert['prv'])) {
				file_put_contents("/etc/ssl/demoCA/private/serverkey.pem", base64_decode($svr_cert['prv']) . base64_decode($svr_cert['crt']));
				$cert_key = "certprivatekeypath = '/etc/ssl/demoCA/private/serverkey.pem' ";
			}
		}
		*/
	}

	//contentscanners preg_replace patterns
	$match[0] = "/(conf)/";
	$match[1] = "/(\/usr.local)/";
	$match[2] = "/,/";
	$replace[0] = "$1'";
	$replace[1] = "contentscanner = '" . E2GUARDIAN_DIR;
	$replace[2] = "\n";

	$contentscanners = preg_replace($match, $replace, $e2guardian_config['content_scanners']);

	//includes preg_replace patterns
	$match[0] = "/(.)$/";
	$match[1] = "/\/usr.local/";
	$match[2] = "/,/";
	$replace[0] = "$1>\n";
	$replace[1] = "\n.Include<" . E2GUARDIAN_DIR;
	$replace[2] = ">";

	//phrase ACL
	//create a default setup if not exists
	if (!is_array($config['installedpackages']['e2guardianphraseacl']['config'])) {
		$banned_file = file(E2GUARDIAN_DIR . "/etc/e2guardian/lists/bannedphraselist");
		foreach ($banned_file as $file_line) {
			if (preg_match ("/^.Include<(\S+)>/", $file_line, $matches)) {
				$banned_includes .= $matches[1] . ",";
			}
		}

		$weighted_file = file(E2GUARDIAN_DIR . "/etc/e2guardian/lists/weightedphraselist");
		foreach ($weighted_file as $file_line) {
			if (preg_match ("/^.Include<(\S+)>/", $file_line, $matches)) {
				$weighted_includes .= $matches[1] . ",";
			}
		}
		$config['installedpackages']['e2guardianphraseacl']['config'][0] = array(
			'name' => 'Default',
			'aclschedule' => 'Always active',
			'description' => 'Default Phrase access list setup',
			'banned_enabled' => "on",
			'weighted_enabled' => "on",
			'exception_enabled' => "on",
			'banned_includes' => substr($banned_includes, 0, -1),
			'weighted_includes' => substr($weighted_includes, 0, -1));
	}
	//loop on array
	$acl_schedule = array();
	$e2g_sched_in_use = array();
	$count = 0;
	if (is_array($config['installedpackages']['e2guardianphraseacl']['config'])) {
		foreach ($config['installedpackages']['e2guardianphraseacl']['config'] as $e2guardian_phrase) {
			//chech schedule
			e2g_check_sched($e2guardian_phrase['aclschedule']);

			//bannedphraselist
			$aclschedule[$e2guardian_phrase['name']]['bannedphrase'] = $e2guardian_phrase['aclschedule'];
			if ($e2guardian_phrase['banned_phraselist'] == "" && file_exists($e2guardian_dir . '/lists/bannedphraselist.sample')) {
				$config['installedpackages']['e2guardianphraseacl']['config'][$count]['banned_phraselist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/bannedphraselist.sample'));
				$load_samples++;
			}
			$includes = preg_replace($match, $replace, $e2guardian_phrase['banned_includes']);
			file_put_contents($e2guardian_dir . "/lists/bannedphraselist." . $e2guardian_phrase['name'], ($e2guardian_phrase['banned_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianphraseacl']['config'][$count]['banned_phraselist']) . $includes : ""), LOCK_EX);

			//weightedphraselist
			$aclschedule[$e2guardian_phrase['name']]['weightedphrase'] = $e2guardian_phrase['aclschedule'];
			if ($e2guardian_phrase['weighted_phraselist'] == "" && file_exists($e2guardian_dir . '/lists/weightedphraselist.sample')) {
				$config['installedpackages']['e2guardianphraseacl']['config'][$count]['weighted_phraselist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/weightedphraselist.sample'));
				$load_samples++;
			}
			$includes = preg_replace($match, $replace, $e2guardian_phrase['weighted_includes']);
			file_put_contents($e2guardian_dir . "/lists/weightedphraselist." . $e2guardian_phrase['name'], ($e2guardian_phrase['weighted_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianphraseacl']['config'][$count]['weighted_phraselist']) . $includes : ""), LOCK_EX);

			//exceptionphraselist
			$aclschedule[$e2guardian_phrase['name']]['exceptionphrase'] = $e2guardian_phrase['aclschedule'];
			if ($e2guardian_phrase['exception_phraselist'] == "" && file_exists($e2guardian_dir . '/lists/exceptionphraselist.sample')) {
				$config['installedpackages']['e2guardianphraseacl']['config'][$count]['exception_phraselist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/exceptionphraselist.sample'));
				$load_samples++;
			}
			file_put_contents($e2guardian_dir . "/lists/exceptionphraselist." . $e2guardian_phrase['name'], ($e2guardian_phrase['exception_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianphraseacl']['config'][$count]['exception_phraselist']) : ""), LOCK_EX);
			$count++;
		}
	}

	//site ACL
	//create a default setup if not exists
	if (!is_array($config['installedpackages']['e2guardiansiteacl']['config'])) {
		$config['installedpackages']['e2guardiansiteacl']['config'][0] = array(
			'name' => 'Default',
			'aclschedule' => 'Always active',
			'description' => 'Default Site access list setup',
			'exceptionsite_enabled' => "on",
			'bannedsite_enabled' => "on",
			'greysite_enabled' => "on",
			'urlsite_enabled' => "on");
	}

	//Blacklist 
	if (!is_array($config['installedpackages']['e2guardianblacklist']['config'])) {
		$config['installedpackages']['e2guardianblacklist']['config'][0] = array(
			'liston' => 'both',
			'cron' => '02days',
			'url' => "http://www.shallalist.de/Downloads/shallalist.tar.gz",
 			'enable_custom_script' => "",
			'update_script' => "");
	}

	//loop on array
	$count = 0;
	foreach ($config['installedpackages']['e2guardiansiteacl']['config'] as $e2guardian_site) {
		//chech schedule
		e2g_check_sched($e2guardian_site['aclschedule']);

		//exceptionsitelist
		$aclschedule[$e2guardian_site['name']]['exceptionsite'] = $e2guardian_site['aclschedule'];
		if ($e2guardian_site['exception_sitelist'] == "" && file_exists($e2guardian_dir . '/lists/exceptionsitelist.sample')) {
			$config['installedpackages']['e2guardiansiteacl']['config'][$count]['exception_sitelist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/exceptionsitelist.sample'));
			$load_samples++;
		}
		$includes = preg_replace($match, $replace, $e2guardian_site['exception_includes']);
		file_put_contents($e2guardian_dir . "/lists/exceptionsitelist." . $e2guardian_site['name'], ($e2guardian_site['exceptionsite_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardiansiteacl']['config'][$count]['exception_sitelist']) . $includes : ""), LOCK_EX);

		//exceptionfilesitelist
		$aclschedule[$e2guardian_site['name']]['exceptionfilesite'] = $e2guardian_site['aclschedule'];
		if ($e2guardian_site['exceptionfile_sitelist'] == "" && file_exists($e2guardian_dir . '/lists/exceptionfilesitelist.sample')) {
			$config['installedpackages']['e2guardiansiteacl']['config'][$count]['exceptionfile_sitelist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/exceptionfilesitelist.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/exceptionfilesitelist." . $e2guardian_site['name'], ($e2guardian_site['exceptionsite_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardiansiteacl']['config'][$count]['exceptionfile_sitelist']) : ""), LOCK_EX);

		//bannedsitelist
		$aclschedule[$e2guardian_site['name']]['bannedsite'] = $e2guardian_site['aclschedule'];
		if ($e2guardian_site['banned_sitelist'] == "" && file_exists($e2guardian_dir . '/lists/bannedsitelist.sample')) {
			$config['installedpackages']['e2guardiansiteacl']['config'][$count]['banned_sitelist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/bannedsitelist.sample'));
			$load_samples++;
		}
		//bannedsitelistwithbypass
		$aclschedule[$e2guardian_site['name']]['bannedsitewithbypass'] = $e2guardian_site['aclschedule'];
		if ($e2guardian_site['banned_sitelist_withbypass'] == "" && file_exists($e2guardian_dir . '/lists/bannedsitewithbypasslist.sample')) {
			$config['installedpackages']['e2guardiansiteacl']['config'][$count]['banned_sitelist_withbypass'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/bannedsitewithbypasslist.sample'));
			$load_samples++;
		}
		$includes = preg_replace($match, $replace, $e2guardian_site['banned_includes']);
		file_put_contents($e2guardian_dir . "/lists/bannedsitelist." . $e2guardian_site['name'], ($e2guardian_site['bannedsite_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardiansiteacl']['config'][$count]['banned_sitelist']) . $includes : ""), LOCK_EX);

		//greysitelist
		$aclschedule[$e2guardian_site['name']]['greysite'] = $e2guardian_site['aclschedule'];
		if ($e2guardian_site['grey_sitelist'] == "" && file_exists($e2guardian_dir . '/lists/greysitelist.sample')) {
			$config['installedpackages']['e2guardiansiteacl']['config'][$count]['grey_sitelist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/greysitelist.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/greysitelist." . $e2guardian_site['name'], ($e2guardian_site['greysite_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardiansiteacl']['config'][$count]['grey_sitelist']) : ""), LOCK_EX);

		//sslregex_sitelist
		$aclschedule[$e2guardian_site['name']]['sslsiteregexp'] = $e2guardian_site['aclschedule'];
		if ($e2guardian_site['sslregex_sitelist'] == "" && file_exists($e2guardian_dir . '/lists/sslsiteregexplist.sample')) {
			$config['installedpackages']['e2guardiansiteacl']['config'][$count]['sslregex_sitelist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/sslsiteregexplist.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/sslsiteregexplist." . $e2guardian_site['name'], ($e2guardian_site['sslregex_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardiansiteacl']['config'][$count]['sslregex_sitelist']) : ""), LOCK_EX);
		
		//logsitelist
		$aclschedule[$e2guardian_site['name']]['logsite'] = $e2guardian_site['aclschedule'];
		if ($e2guardian_site['log_sitelist'] == "" && file_exists($e2guardian_dir . '/lists/logsitelist.sample')) {
			$config['installedpackages']['e2guardiansiteacl']['config'][$count]['log_sitelist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/logsitelist.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/logsitelist." . $e2guardian_site['name'], ($e2guardian_site['urlsite_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardiansiteacl']['config'][$count]['log_sitelist']) : ""), LOCK_EX);
		$count++;
	}

	$files_5 = array("/downloadmanagers/default.conf",
			"/authplugins/ident.conf",
			"/authplugins/ip.conf",
			"/authplugins/port.conf",
			"/authplugins/proxy-basic.conf",
			"/authplugins/proxy-digest.conf");

        foreach ($files_5 as $f_5) {
                if (! file_exists($e2guardian_dir . $f_5) && file_exists($e2guardian_dir . "{$f_5}.sample")) {
                        copy ($e2guardian_dir . "{$f_5}.sample",$e2guardian_dir . $f_5);
                }
        }

	//URL ACL
	//create a default setup if not exists
	if (!is_array($config['installedpackages']['e2guardianurlacl']['config'])) {
		$config['installedpackages']['e2guardianurlacl']['config'][0] = array(
			'name' => 'Default',
			'aclschedule' => 'Always active',
			'description' => 'Default Url access list setup',
			'bannedurl_enabled' => "on",
			'exceptionurl_enabled' => "on",
			'contenturl_enabled' => "on",
			'greyurl_enabled' => "on");
	}
	//loop on array
	$count = 0;
	foreach ($config['installedpackages']['e2guardianurlacl']['config'] as $e2guardian_url) {
		//chech schedule
		e2g_check_sched($e2guardian_url['aclschedule']);

		//bannedurllist
		$aclschedule[$e2guardian_url['name']]['bannedurl'] = $e2guardian_url['aclschedule'];
		if ($e2guardian_url['banned_urllist'] == "" && file_exists($e2guardian_dir . '/lists/bannedurllist.sample')) {
			$config['installedpackages']['e2guardianurlacl']['config'][$count]['banned_urllist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/bannedurllist.sample'));
			$load_samples++;
		}
		$includes = preg_replace($match, $replace, $e2guardian_url['banned_includes']);
		file_put_contents($e2guardian_dir . "/lists/bannedurllist." . $e2guardian_url['name'], ($e2guardian_url['bannedurl_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianurlacl']['config'][$count]['banned_urllist']) . $includes : ""), LOCK_EX);

		//bannedregexpurllist
		$aclschedule[$e2guardian_url['name']]['bannedregexpurl'] = $e2guardian_url['aclschedule'];
		if ($e2guardian_url['bannedregexp_urllist'] == "" && file_exists($e2guardian_dir . '/lists/bannedregexpurllist.sample')) {
			$config['installedpackages']['e2guardianurlacl']['config'][$count]['bannedregexp_urllist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/bannedregexpurllist.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/bannedregexpurllist." . $e2guardian_url['name'], ($e2guardian_url['bannedurl_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianurlacl']['config'][$count]['bannedregexp_urllist']) : ""), LOCK_EX);

		//greyurllist
		$aclschedule[$e2guardian_url['name']]['greyurl'] = $e2guardian_url['aclschedule'];
		if ($e2guardian_url['grey_urllist'] == "" && file_exists($e2guardian_dir . '/lists/greyurllist.sample')) {
			$config['installedpackages']['e2guardianurlacl']['config'][$count]['grey_urllist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/greyurllist.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/greyurllist." . $e2guardian_url['name'], ($e2guardian_url['greyurl_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianurlacl']['config'][$count]['grey_urllist']) : ""), LOCK_EX);

		//exceptionfileurllist
		$aclschedule[$e2guardian_url['name']]['exceptionfileurl'] = $e2guardian_url['aclschedule'];
		if ($e2guardian_url['exceptionfile_urllist'] == "" && file_exists($e2guardian_dir . '/lists/exceptionfileurllist.sample')) {
			$config['installedpackages']['e2guardianurlacl']['config'][$count]['exceptionfile_urllist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/exceptionfileurllist.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/exceptionfileurllist." . $e2guardian_url['name'], ($e2guardian_url['exceptionurl_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianurlacl']['config'][$count]['exceptionfile_urllist']) : ""), LOCK_EX);

		//exceptionregexpurllist
		$aclschedule[$e2guardian_url['name']]['exceptionregexpurl'] = $e2guardian_url['aclschedule'];
		if ($e2guardian_url['exceptionregexp_urllist'] == "" && file_exists($e2guardian_dir . '/lists/exceptionregexpurllist.sample')) {
			$config['installedpackages']['e2guardianurlacl']['config'][$count]['exceptionregexp_urllist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/exceptionregexpurllist.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/exceptionregexpurllist." . $e2guardian_url['name'], ($e2guardian_url['exceptionurl_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianurlacl']['config'][$count]['exceptionregexp_urllist']) : ""), LOCK_EX);

		//exceptionurllist
		$aclschedule[$e2guardian_url['name']]['exceptionurl'] = $e2guardian_url['aclschedule'];
		if ($e2guardian_url['exception_urllist'] == "" && file_exists($e2guardian_dir . '/lists/exceptionurllist.sample')) {
			$config['installedpackages']['e2guardianurlacl']['config'][$count]['exception_urllist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/exceptionurllist.sample'));
			$load_samples++;
		}
		$includes = preg_replace($match, $replace, $e2guardian_url['exception_includes']);
		file_put_contents($e2guardian_dir . "/lists/exceptionurllist." . $e2guardian_url['name'], ($e2guardian_url['exceptionurl_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianurlacl']['config'][$count]['exception_urllist']) . $includes : ""), LOCK_EX);

		//urlregexplist
		$aclschedule[$e2guardian_url['name']]['urlregexp'] = $e2guardian_url['aclschedule'];
		if ($e2guardian_url['modify_urllist'] == "" && file_exists($e2guardian_dir . '/lists/urlregexplist.sample')) {
			$config['installedpackages']['e2guardianurlacl']['config'][$count]['modify_urllist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/urlregexplist.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/urlregexplist." . $e2guardian_url['name'], ($e2guardian_url['contenturl_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianurlacl']['config'][$count]['modify_urllist']) : ""), LOCK_EX);

		//logurllist
		$aclschedule[$e2guardian_url['name']]['logurl'] = $e2guardian_url['aclschedule'];
		if ($e2guardian_url['log_urllist'] == "" && file_exists($e2guardian_dir . '/lists/logurllist.sample')) {
			$config['installedpackages']['e2guardianurlacl']['config'][$count]['log_urllist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/logurllist.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/logurllist." . $e2guardian_url['name'], ($e2guardian_url['logurl_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianurlacl']['config'][$count]['log_urllist']) : ""), LOCK_EX);

		//logregexpurllist
		$aclschedule[$e2guardian_url['name']]['logregexpurl'] = $e2guardian_url['aclschedule'];
		if ($e2guardian_url['logregexp_urllist'] == "" && file_exists($e2guardian_dir . '/lists/logregexpurllist.sample')) {
			$config['installedpackages']['e2guardianurlacl']['config'][$count]['logregexp_urllist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/logregexpurllist.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/logregexpurllist." . $e2guardian_url['name'], ($e2guardian_url['logurl_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianurlacl']['config'][$count]['logregexp_urllist']) : ""), LOCK_EX);
		$count++;
	}

	//Pics ACL
	//create a default setup if not exists
	if (!is_array($config['installedpackages']['e2guardianpicsacl']['config'])) {
		$config['installedpackages']['e2guardianpicsacl']['config'][0] = array(
			'name' => 'Default',
			'aclschedule' => 'Always active',
			'description' => 'Default file access list setup');
	}
	//loop on array
	$count = 0;
	foreach ($config['installedpackages']['e2guardianpicsacl']['config'] as $e2guardian_pics) {
		//pics
		if ($e2guardian_pics['pics'] == "" && file_exists($e2guardian_dir . '/lists/pics.sample')) {
			$config['installedpackages']['e2guardianpicsacl']['config'][$count]['pics'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/pics.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/pics." . $e2guardian_pics['name'], ($e2guardian_pics['pics_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianpicsacl']['config'][$count]['pics']) : ""), LOCK_EX);
		$count++;
	}

	//Search ACL
	//create a default setup if not exists
	if (!is_array($config['installedpackages']['e2guardiansearchacl']['config'])) {
		$config['installedpackages']['e2guardiansearchacl']['config'][0] = array(
			'name' => 'Default',
			'aclschedule' => 'Always active',
			'description' => 'Default search engine list setup');
	}
	//loop on array
	$count = 0;
	foreach ($config['installedpackages']['e2guardiansearchacl']['config'] as $e2guardian_search) {
		//chech schedule
		e2g_check_sched($e2guardian_search['aclschedule']);

		//searchengineregexp
		$aclschedule[$e2guardian_search['name']]['searchlistregexp'] = $e2guardian_search['aclschedule'];
		if ($e2guardian_search['searchlistregexp'] == "" && file_exists($e2guardian_dir . '/lists/searchregexplist.sample')) {
			$config['installedpackages']['e2guardiansearchacl']['config'][$count]['searchlistregexp'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/searchregexplist.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/searchlistregexplist." . $e2guardian_search['name'], ($e2guardian_search['regexp_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardiansearchacl']['config'][$count]['searchlistregexp']) : ""), LOCK_EX);

		//overidesearchlist
		$aclschedule[$e2guardian_search['name']]['searchlistoveride'] = $e2guardian_search['aclschedule'];
		file_put_contents($e2guardian_dir . "/lists/searchlistoveridelist." . $e2guardian_search['name'], ($e2guardian_search['banned_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardiansearchacl']['config'][$count]['searchlistoveride']) : ""), LOCK_EX);

		//bannedsearchlist
		$aclschedule[$e2guardian_search['name']]['searchlistbanned'] = $e2guardian_search['aclschedule'];
		if ($e2guardian_search['searchlistbanned'] == "" && file_exists($e2guardian_dir . '/lists/bannedsearchlist.sample')) {
			$config['installedpackages']['e2guardiansearchacl']['config'][$count]['searchlistbanned'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/bannedsearchlist.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/searchlistbannedlist." . $e2guardian_search['name'], ($e2guardian_search['banned_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardiansearchacl']['config'][$count]['searchlistbanned']) : ""), LOCK_EX);

		$count++;
		}

	//File ACL
	//create a default setup if not exists
	if (!is_array($config['installedpackages']['e2guardianfileacl']['config'])) {
		$config['installedpackages']['e2guardianfileacl']['config'][0] = array(
			'name' => 'Default',
			'aclschedule' => 'Always active',
			'description' => 'Default file access list setup',
			'exception_enabled' => "on",
			'banned_enabled' => "on");
	}
	//loop on array
	$count = 0;
	foreach ($config['installedpackages']['e2guardianfileacl']['config'] as $e2guardian_file) {
		//chech schedule
		e2g_check_sched($e2guardian_file['aclschedule']);

		//exceptionextensionlist
		$aclschedule[$e2guardian_file['name']]['exceptionextension'] = $e2guardian_file['aclschedule'];
		if ($e2guardian_file['exception_extensionlist'] == "" && file_exists($e2guardian_dir . '/lists/exceptionextensionlist.sample')) {
			$config['installedpackages']['e2guardianfileacl']['config'][$count]['exception_extensionlist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/exceptionextensionlist.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/exceptionextensionlist." . $e2guardian_file['name'], ($e2guardian_file['exception_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianfileacl']['config'][$count]['exception_extensionlist']) : ""), LOCK_EX);

		//exceptionmimetypelist
		$aclschedule[$e2guardian_file['name']]['exceptionmimetype'] = $e2guardian_file['aclschedule'];
		if ($e2guardian_file['exception_mimetypelist'] == "" && file_exists($e2guardian_dir . '/lists/exceptionmimetypelist.sample')) {
			$config['installedpackages']['e2guardianfileacl']['config'][$count]['exception_mimetypelist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/exceptionmimetypelist.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/exceptionmimetypelist." . $e2guardian_file['name'], ($e2guardian_file['exception_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianfileacl']['config'][$count]['exception_mimetypelist']) : ""), LOCK_EX);

		//bannedextensionlist
		$aclschedule[$e2guardian_file['name']]['bannedextension'] = $e2guardian_file['aclschedule'];
		if ($e2guardian_file['banned_extensionlist'] == "" && file_exists($e2guardian_dir . '/lists/bannedextensionlist.sample')) {
			$config['installedpackages']['e2guardianfileacl']['config'][$count]['banned_extensionlist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/bannedextensionlist.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/bannedextensionlist." . $e2guardian_file['name'], ($e2guardian_file['banned_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianfileacl']['config'][$count]['banned_extensionlist']) : ""), LOCK_EX);

		//bannedmimetypelist
		$aclschedule[$e2guardian_file['name']]['bannedmimetype'] = $e2guardian_file['aclschedule'];
		if ($e2guardian_file['banned_mimetypelist'] == "" && file_exists($e2guardian_dir . '/lists/bannedmimetypelist.sample')) {
			$config['installedpackages']['e2guardianfileacl']['config'][$count]['banned_mimetypelist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/bannedmimetypelist.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/bannedmimetypelist." . $e2guardian_file['name'], ($e2guardian_file['banned_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianfileacl']['config'][$count]['banned_mimetypelist']) : ""), LOCK_EX);
		$count++;
	}

	//header ACL
	//create a default setup if not exists
	if (!is_array($config['installedpackages']['e2guardianheaderacl']['config'])) {
		$config['installedpackages']['e2guardianheaderacl']['config'][0] = array(
			'name' => 'Default',
			'aclschedule' => 'Always active',
			'description' => 'Default header access list setup');
	}
	//loop on array
	$count = 0;
	foreach ($config['installedpackages']['e2guardianheaderacl']['config'] as $e2guardian_header) {
		//chech schedule
		e2g_check_sched($e2guardian_header['aclschedule']);

		//exceptionregexphedaer 
		$aclschedule[$e2guardian_header['name']]['exceptionregexpheader'] = $e2guardian_header['aclschedule'];
		if ($e2guardian_header['exception_regexpheaderlist'] == "" && file_exists($e2guardian_dir . '/lists/exceptionregexpheaderlist.sample')) {
		    $config['installedpackages']['e2guardianheaderacl']['config'][$count]['exception_regexpheaderlist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/exceptionregexpheaderlist.sample'));
		    $load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/exceptionregexpheaderlist." . $e2guardian_header['name'], ($e2guardian_header['exception_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianheaderacl']['config'][$count]['exception_regexpheaderlist']) : ""), LOCK_EX);
		
		//headerregexplist
		$aclschedule[$e2guardian_header['name']]['headerregexp'] = $e2guardian_header['aclschedule'];
		if ($e2guardian_header['header_regexplist'] == "" && file_exists($e2guardian_dir . '/lists/headerregexplist.sample')) {
			$config['installedpackages']['e2guardianheaderacl']['config'][$count]['header_regexplist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/headerregexplist.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/headerregexplist." . $e2guardian_header['name'], ($e2guardian_header['regexp_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianheaderacl']['config'][$count]['header_regexplist']) : ""), LOCK_EX);

		//bannedregexpheaderlist
		$aclschedule[$e2guardian_header['name']]['bannedregexpheader'] = $e2guardian_header['aclschedule'];
		if ($e2guardian_header['banned_regexpheaderlist'] == "" && file_exists($e2guardian_dir . '/lists/bannedregexpheaderlist.sample')) {
			$config['installedpackages']['e2guardianheaderacl']['config'][$count]['banned_regexpheaderlist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/bannedregexpheaderlist.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/bannedregexpheaderlist." . $e2guardian_header['name'], ($e2guardian_header['banned_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianheaderacl']['config'][$count]['banned_regexpheaderlist']) : ""), LOCK_EX);

		$count++;
	}

	//Content ACL
	//create a default setup if not exists
	if (!is_array($config['installedpackages']['e2guardiancontentacl']['config'])) {
		$config['installedpackages']['e2guardiancontentacl']['config'][0] = array(
			'name' => 'Default',
			'aclschedule' => 'Always active',
			'description' => 'Default content setup');
	}
	//loop on array
	$count = 0;
	foreach ($config['installedpackages']['e2guardiancontentacl']['config'] as $e2guardian_content) {
		//chech schedule
		e2g_check_sched($e2guardian_content['aclschedule']);

		//content_regexplist
		$aclschedule[$e2guardian_content['name']]['contentregexp'] = $e2guardian_content['aclschedule'];
		if ($e2guardian_content['content_regexplist'] == "" && file_exists($e2guardian_dir . '/lists/contentregexplist.sample')) {
			$config['installedpackages']['e2guardiancontentacl']['config'][$count]['content_regexplist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/contentregexplist.sample'));
			$load_samples++;
		}
		file_put_contents($e2guardian_dir . "/lists/contentregexplist." . $e2guardian_content['name'], ($e2guardian_content['content_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardiancontentacl']['config'][$count]['content_regexplist']) : ""), LOCK_EX);
		$count++;
	}

	//Antivirus ACL
	//create a default setup if not exists
	if (!is_array($config['installedpackages']['e2guardianantivirusacl']['config'])) {
		$config['installedpackages']['e2guardianantivirusacl']['config'][0] = array();
	}

	$e2guardian_antivirus = $config['installedpackages']['e2guardianantivirusacl']['config'][0];
	//exceptionvirusmimetypelist
	if ($e2guardian_antivirus['mime_list'] == "" && file_exists($e2guardian_dir . '/lists/contentscanners/exceptionvirusmimetypelist.sample')) {
		$config['installedpackages']['e2guardianantivirusacl']['config'][0]['mime_list'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/contentscanners/exceptionvirusmimetypelist.sample'));
		$load_samples++;
	}
	file_put_contents($e2guardian_dir . "/lists/contentscanners/exceptionvirusmimetypelist", ($e2guardian_antivirus['mime_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianantivirusacl']['config'][0]['mime_list']) : ""), LOCK_EX);

	//exceptionvirussitelist
	if ($e2guardian_antivirus['site_list'] == "" && file_exists($e2guardian_dir . '/lists/contentscanners/exceptionvirussitelist.sample')) {
		$config['installedpackages']['e2guardianantivirusacl']['config'][0]['site_list'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/contentscanners/exceptionvirussitelist.sample'));
		$load_samples++;
	}
	file_put_contents($e2guardian_dir . "/lists/contentscanners/exceptionvirussitelist", ($e2guardian_antivirus['site_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianantivirusacl']['config'][0]['site_list']) : ""), LOCK_EX);

	//exceptionvirusurllist
	if ($e2guardian_antivirus['url_list'] == "" && file_exists($e2guardian_dir . '/lists/contentscanners/exceptionvirusurllist.sample')) {
		$config['installedpackages']['e2guardianantivirusacl']['config'][0]['url_list'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/contentscanners/exceptionvirusurllist.sample'));
		$load_samples++;
	}
	file_put_contents($e2guardian_dir . "/lists/contentscanners/exceptionvirusurllist", ($e2guardian_antivirus['url_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianantivirusacl']['config'][0]['url_list']) : ""), LOCK_EX);

	//exceptionvirusextensionlist
	if ($e2guardian_antivirus['extension_list'] == "" && file_exists($e2guardian_dir . '/lists/contentscanners/exceptionvirusextensionlist.sample')) {
		$config['installedpackages']['e2guardianantivirusacl']['config'][0]['extension_list'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/contentscanners/exceptionvirusextensionlist.sample'));
		$load_samples++;
	}
	file_put_contents($e2guardian_dir . "/lists/contentscanners/exceptionvirusextensionlist", ($e2guardian_antivirus['extension_enabled'] ? e2_text_area_decode($config['installedpackages']['e2guardianantivirusacl']['config'][0]['extension_list']):""), LOCK_EX);

	//log report
	$rf = "/usr/local/share/e2guardian/languages/{$reportlanguage}/template.html";
	if(file_exists($rf) && !file_exists("$rf.sample")) {
		copy($rf, "$rf.sample");
	}
	if ($e2guardian_log['report_file'] == "" && file_exists("$rf.sample")) {
		$report_file = file_get_contents("$rf.sample");
		$report_file = preg_replace('/<.*(html|head)>/', '', $report_file);
		$config['installedpackages']['e2guardianlog']['config'][0]['report_file'] = base64_encode($report_file);
		$e2guardian_log['report_file'] = base64_encode($report_file);
		$load_samples++;
	}

	//exception ip list
	//create a default setup if not exists
	if (!is_array($config['installedpackages']['e2guardianips']['config'])) {
	    $config['installedpackages']['e2guardianips']['config'][0] = array("exceptioniplist" => "", "bannediplist" => "");
	}
	if ($config['installedpackages']['e2guardianips']['config'][0]['exceptioniplist'] == "" && file_exists($e2guardian_dir . '/lists/exceptioniplist.sample')) {
		$config['installedpackages']['e2guardianips']['config'][0]['exceptioniplist'] = base64_encode(file_get_contents($e2guardian_dir . '/lists/exceptioniplist.sample'));
		$load_samples++;
	}
	file_put_contents($e2guardian_dir . "/lists/exceptioniplist", e2_text_area_decode($config['installedpackages']['e2guardianips']['config'][0]['exceptioniplist']), LOCK_EX);
	file_put_contents($e2guardian_dir . "/lists/bannediplist", e2_text_area_decode($config['installedpackages']['e2guardianips']['config'][0]['bannediplist']), LOCK_EX);

	if ($load_samples > 0) {
		write_config();
	}
	
	//save schedules used on acls and it's current state
	file_put_contents("/tmp/e2g_scheds.txt",serialize($e2g_sched_in_use),LOCK_EX);

	//Filtergroups
	if (!is_array($config['installedpackages']['e2guardiangroups']['config'])) {
		$config['installedpackages']['e2guardiangroups']['config'][0] = array(
			'name' => 'Default',
			'description' => 'Default e2guardian filtergroup',
			'picsacl' => "Default",
			'phraseacl' => "Default",
			'siteacl' => "Default",
			'extensionacl' => "Default",
			'headeracl' => "Default",
			'contentacl' => "Default",
			'searchacl' => "Default",
			'urlacl' => "Default",
			'group_options' => "scancleancache,infectionbypasserrorsonly",
			'reportinglevel' => '3',
			'group_name_source' => 'name',
			'mode' => "1",
			'report_level' =>"global");
	}
	$groups = array("scancleancache", "hexdecodecontent", "blockdownloads", "enablepics", "deepurlanalysis", "infectionbypasserrorsonly", "disablecontentscan", "sslcheckcert", "sslmitm", "automitm");
	//loop on array
	$count = 1;
	$user_xml = "";
	$filtergroupslist = "";
	foreach ($config['installedpackages']['e2guardiangroups']['config'] as $e2guardian_groups) {
		$e2guardian_group_name = strtolower($e2guardian_groups['name']);
		$dgfg[$count] = $e2guardian_group_name;
		$e2guardian_groups['blockdownloads'] = ($e2guardian_groups['blockdownloads'] ? $e2guardian_groups['blockdownloads'] : "off");
		$e2guardian_groups['weightedphrasemode'] = (preg_match("/\d/", $e2guardian_groups['weightedphrasemode']) ? $e2guardian_groups['weightedphrasemode'] : $e2guardian_config['weightedphrasemode']);
		$e2guardian_groups['naughtynesslimit'] = ($e2guardian_groups['naughtynesslimit'] ? $e2guardian_groups['naughtynesslimit'] : "50");
		$e2guardian_groups['searchtermlimit'] = ($e2guardian_groups['searchtermlimit'] ? $e2guardian_groups['searchtermlimit'] : "30");
		$e2guardian_groups['categorydisplaythreshold'] = ($e2guardian_groups['categorydisplaythreshold'] ? $e2guardian_groups['categorydisplaythreshold'] : "0");
		$e2guardian_groups['embeddedurlweight'] = ($e2guardian_groups['embeddedurlweight'] ? $e2guardian_groups['embeddedurlweight'] : "0");
		$e2guardian_groups['bypass'] = ($e2guardian_groups['bypass'] ? $e2guardian_groups['bypass'] : "0");
		$e2guardian_groups['infectionbypass'] = ($e2guardian_groups['infectionbypass'] ? $e2guardian_groups['infectionbypass'] : "0");
		$e2guardian_groups['maxuploadsize'] = (is_numeric($e2guardian_groups['maxuploadsize']) ? $e2guardian_groups['maxuploadsize'] : "-1");
		$groupreportinglevel = "reportinglevel = " . ($e2guardian_groups['reportinglevel'] == 'global' ? $reportlevel : $e2guardian_groups['reportinglevel']);
        switch (($e2guardian_groups['reportinglevel'] == 'global' ? $reportlevel : $e2guardian_groups['reportinglevel'])) {
			case "1":
			case "2":
				if (is_URL($e2guardian_groups['reportingcgi'])) {
					$groupaccessdeniedaddress = "accessdeniedaddress = '" . $e2guardian_groups['reportingcgi'] . "'";
					e2g_check_logdeniedcgi($e2guardian_log['logdeniedcgi']);
				} else {
					if (is_URL ($e2guardian_log['reportingcgi'])) {
						$groupaccessdeniedaddress = "accessdeniedaddress = '" . $e2guardian_log['reportingcgi'] . "'";
						e2g_check_logdeniedcgi($e2guardian_log['logdeniedcgi']);
					} else {
						log_error('E2guardian - Group ' . $e2guardian_groups['name'] . ' does not have a valid access denied cgi url.');
						file_notice("E2guardian - Group ",$error,"E2guardian - " . $e2guardian_groups['name'] . gettext(" does not have a valid access denied cgi url."), "");
					}
				}
				break;
			case "-1":
			case "0":
				$htmltemplate = "";
$groupaccessdeniedaddress = "";
$groupreportinglevel = "reportinglevel = 0";

break;
			case "3":
				$groupaccessdeniedaddress = "";
				$htmltemplate = "htmltemplate='template.html'";
				break;
			default:
				$groupaccessdeniedaddress = "";
		}
		
        // assign all options selected under group_options
		foreach ($groups as $group) {
			$e2guardian_groups[$group] = (preg_match("/$group/", $e2guardian_groups['group_options']) ? "on" : "off");
		}
		
		//story file options
		switch ($e2guardian_groups['grouptype']) {
		    
		    case "Unfiltered":
		        $group_type  = "#Unfiltered access\n";
		        $group_type .= "function(checkrequest)\n";
		        $group_type .= "if(true) return setexception\n";
		        $group_type .= "function(thttps-checkrequest)\n";
		        $group_type .= "if(true) return setexception\n";
		        break;
		        
		    case "BlockAll":
		        $group_type  = "#Block all sites\n";
		        $group_type .= "function(checkrequest)\n";
		        $group_type .= "if(true,,105) return setblock\n";
		        $group_type .= "function(sslexceptioncheck)\n";
		        $group_type .= "function(localsslcheckrequest)\n";
		        break;

		    case "DenyIp":
		        $group_type  = "#Deny ip sites\n";
		        $group_type .= "function(checkblanketblock)\n";
		        $group_type .= "if(siteisip,,505) return setblock\n";
		        $group_type .= "function(sslcheckblanketblock)\n";
		        $group_type .= "if(siteisip,,507) return setblock\n";
		        break;

		    case "ExceptionSitesOnly":
		        $group_type  = "#ExceptionSitesOnly\n";
		        $group_type .= "function(checkblanketblock)\n";
		        $group_type .= "if(true,,502) return setblock\n";
		        $group_type .= "function(sslcheckblanketblock)\n";
		        $group_type .= "if(true,,506) return setblock\n";
		        break;

		    default:
		        $group_type = "# Normal filtering group type\n";
		}
		
		if ( $e2guardian_groups['onlymitmsslgrey'] == "on" ) {
    	    $sslcheckmitmgreyonly = "function(sslcheckmitm)\n";
    	    $sslcheckmitmgreyonly = "if(true) return sslcheckmitmgreyonly";
		} else {
		    $sslcheckmitmgreyonly = "#Normal ssl filtering if enabled\n";
		}
		
		if ($e2guardian_groups['blockdownloads'] == "off") {
		    $blockdownloads = "";
		} else {
		    $blockdownloads  = "function(checkresponse)\n";
		    $blockdownloads .= "if(viruscheckset) checknoscantypes\n";
		    $blockdownloads .= "if(urlin,exceptionfile) return true\n";
		    $blockdownloads .= "if(mimein, exceptionmime) return true\n";
		    $blockdownloads .= "if(extensionin, exceptionextension) return true\n";
		    $blockdownloads .= "if(true,,750) return setblock";
		}
		
		// temp v5 static lists
		$tlist_dirs = array('contentscanners');
		foreach ($tlist_dirs as $tlist_dir) {
			if ( ! is_dir(E2GUARDIAN_DIR . "/etc/e2guardian/lists/{$tlist_dir}")) {
				mkdir(E2GUARDIAN_DIR . "/etc/e2guardian/lists/{$tlist_dir}",0755);
			}
		}
		$tlist = array ("nocheckcertsiteiplist",
                       	"bannedsiteiplistwithbypass",
                        "contentscanners/exceptionvirussiteiplist",
                        "exceptionfilesiteiplist",
                        "refererexceptionsiteiplist",
                        "embededreferersiteiplist",
                        "refererexceptionsiteiplist",
                        "exceptionsiteiplist",
                        "greysslsiteiplist",
                        "greysiteiplist",
                        "bannedsslsiteiplist",
                        "bannedsiteiplist",
                        "nocheckcertsitelist",
                        "bannedsitelistwithbypass",
                        "contentscanners/exceptionvirussitelist",
                        "refererexceptionsitelist",
                        "embededreferersitelist",
                        "refererexceptionsitelist",
                        "greysslsitelist",
                        "bannedsslsitelist",
                        "bannedurllistwithbypass",
                        "contentscanners/exceptionvirusurllist",
                        "refererexceptionurllist",
                        "embededrefererurllist",
                        "refererexceptionurllist",
                        "bannedsearchoveridelist",
                        "bannedsearchlist",
                        "localbannedsearchlist",
                        "contentscanners/exceptionvirusmimetypelist",
                        "bannedmimeitypelist",
                        "bannedregexpuseragentlist",
                        "searchregexplist",
		                 "localgreysslsitelist",
			"contentscanners/exceptionvirusextensionlist",
                        "urlredirectregexplist");
		foreach ($tlist as $tl) {
			if ( ! file_exists(E2GUARDIAN_DIR . "/etc/e2guardian/lists/{$tl}.g_{$e2guardian_groups['name']}")) {
				if ( file_exists (E2GUARDIAN_DIR . "/etc/e2guardian/lists/{$tl}.sample")) {
					copy (E2GUARDIAN_DIR . "/etc/e2guardian/lists/{$tl}.sample", E2GUARDIAN_DIR . "/etc/e2guardian/lists/{$tl}.g_{$e2guardian_groups['name']}");
				} else {
					file_put_contents(E2GUARDIAN_DIR . "/etc/e2guardian/lists/{$tl}.g_{$e2guardian_groups['name']}","",LOCK_EX);
				}
			}
		}

		//create group list files
		$lists = array(
			"phraseacl" => array("bannedphrase", "weightedphrase", "exceptionphrase"),
			"siteacl" => array("exceptionsite", "bannedsite", "greysite", "exceptionfilesite", "logsite", "sslsiteregexp","bannedsitewithbypass"),
			"urlacl" => array("bannedurl", "greyurl", "exceptionurl", "exceptionregexpurl", "bannedregexpurl", "urlregexp", "exceptionfileurl", "logurl", "logregexpurl"),
			"contentacl" => array("contentregexp"),
			"extensionacl" => array("exceptionextension", "exceptionmimetype", "bannedextension", "bannedmimetype"),
			"headeracl" => array("headerregexp", "bannedregexpheader", "exceptionregexpheader"),
			"searchacl" => array("searchlistbanned", "searchlistoveride", "searchlistregexp")
		);

		foreach ($lists as $list_key => $list_array) {
			// verify groups acls to avoid errors on empty acl group options
			if (!preg_match("/\w+/", $e2guardian_groups[$list_key])) {
				log_error("e2guardian - Config warning, Group {$e2guardian_group_name} {$list_key} cannot be empty! Trying to load sample values");
			}
			foreach ($list_array as $list_value) {
				//read all access lists applied to this group option
				foreach (explode(",", $e2guardian_groups[$list_key]) as $dacl) {
					if (!is_array(${$list_value})) {
						${$list_value} = array();
					}
					
					//print "$dacl {$list_value} {$aclschedule[$dacl][$list_value]} {$e2g_sched_in_use[$aclschedule[$dacl][$list_value]]}\n";
					if (	$aclschedule[$dacl][$list_value] == "" || 
						$aclschedule[$dacl][$list_value] == "Always active" ||
						$e2g_sched_in_use[$aclschedule[$dacl][$list_value]]) {
						$dacl = (preg_match("/\w+/", $dacl) ? $dacl : "sample");
						$ft = E2GUARDIAN_DIR . "/etc/e2guardian/lists/{$list_value}list.{$dacl}";
						if (file_exists($ft)) {
							$file_temp = file_get_contents($ft) . "\n";
						}
					} else {
						$file_temp = "# acl {$dacl} {$list_value} is configured but current time is out of {$aclschedule[$dacl][$list_value]} schedule";
					}
					${$list_value} = array_merge(explode("\n", $file_temp), ${$list_value});
				}
				//remove comments and blank lines
                $save_array = array_unique(preg_grep("/^(\w|\"|\'|\/|\.|\<|\(|\*|\^|\# acl |\#listcategory|\#noconvert|\#ENDLIST)/", ${$list_value}));

				//add a package warning
                array_unshift($save_array, "#Do not edit this file.", "#It's created by e2guardian package and overwritten every config save.");
	
				//save group file and unset array
				//file_put_contents(E2GUARDIAN_DIR . "/etc/e2guardian/lists/{$list_value}list.g_{$e2guardian_groups['name']}", implode("\n", array_unique(${$list_value})) . "\n", LOCK_EX);
				file_put_contents(E2GUARDIAN_DIR . "/etc/e2guardian/lists/{$list_value}list.g_{$e2guardian_groups['name']}", implode("\n",$save_array) . "\n", LOCK_EX);
				unset(${$list_value});
			}
		}

		$e2_dir = E2GUARDIAN_DIR;
		
		//remove localchecks from common.story
		$c_story_file = "/usr/local/etc/e2guardian/common.story";
		if (file_exists($c_story_file)) {
		  $c_story = file($c_story_file);
		  $c_story_count = 0;
		  $nc_story_file = "";
		  foreach($c_story as $c_story_line) {
		      if (preg_match("/^\w.*, local\w+/", $c_story_line)) {
		          $c_story_count++;
		          $nc_story_file .= "#". $c_story_line;
		      } else {
		          $nc_story_file .= $c_story_line;
		      }
		  }
		  if ($c_story_count > 0) {
		      file_put_contents($c_story_file, $nc_story_file, LOCK_EX);
		  }
		}
		include("/usr/local/pkg/e2guardian/templates/e2guardian_story.template");
		file_put_contents($e2guardian_dir . "/g_" . $e2guardian_groups['name'] . ".story", $dg_story, LOCK_EX);
		
		include("/usr/local/pkg/e2guardian/templates/e2guardianfx.conf.template");
		file_put_contents($e2guardian_dir . "/e2guardianf" . $count . ".conf", $dgf, LOCK_EX);

		if ($config['installedpackages']['e2guardianusers']['config'][0][$e2guardian_group_name] != "") {
			$import_users = explode("\n", base64_decode($config['installedpackages']['e2guardianusers']['config'][0][$e2guardian_group_name]));
			asort($import_users);
			$config['installedpackages']['e2guardianusers']['config'][0][$e2guardian_group_name] = base64_encode(implode("\n", $import_users));
			foreach ($import_users as $new_user) {
				if (preg_match("/(\S+)\s+(\S+)/", $new_user, $matches)) {
					$filtergroupslist .= $matches[1] . "=filter" . $count . " #" . $matches[2] . "\n";
				} elseif (preg_match("/(\S+)/", $new_user, $matches)) {
					$filtergroupslist .= $matches[1] . "=filter" . $count . "\n";
				}
			}
		}
		if ($config['installedpackages']['e2guardianips']['config'][0][$e2guardian_group_name] != "") {
			$import_ips = explode("\n", base64_decode($config['installedpackages']['e2guardianips']['config'][0][$e2guardian_group_name]));
			asort($import_ips);
			$config['installedpackages']['e2guardianips']['config'][0][$e2guardian_group_name] = base64_encode(implode("\n", $import_ips));
			foreach ($import_ips as $new_ip) {
				if (preg_match("/(\S+)\s+(.*)/", $new_ip, $matches)) {
					$filtergroupsiplist .= $matches[1] . " = filter" . $count . " #" . $matches[2] . "\n";
				} elseif (preg_match("/(\S+)/", $new_ip, $matches)) {
					$filtergroupsiplist .= $matches[1] . " = filter" . $count . "\n";
				}
			}
		}
		is_countable($import_users) ? $filtergroup_count = count($import_users) : $filtergroup_count=0;
		is_countable($import_ips) ? $filtergroupip_count = count($import_ips) : $filtergroupip_count=0;
// 		$filtergroup_count = count($import_users);
// 		$filtergroupip_count = count($import_ips);
		//Default group catch all unauth groups as well non listed users
		if ($count > 1)
		$user_xml .=<<<EOF
		<field>
			<name>{$e2guardian_groups['description']} ({$filtergroup_count})</name>
			<type>listtopic</type>
		</field>
		<field>
			<fieldname>{$e2guardian_group_name}</fieldname>
			<fielddescr>{$e2guardian_groups['name']}</fielddescr>
			<description><![CDATA[Include users for this group one per line<br>Hint:PFSENSE\marcelloc #Marcello Coutinho]]></description>
			<type>textarea</type>

			<cols>80</cols><rows>12</rows>
			<encoding>base64</encoding>
		</field>
EOF;
		$ips_xml .=<<<EOF
		<field>
			<name>{$e2guardian_groups['description']} ({$filtergroupip_count})</name>
			<type>listtopic</type>
		</field>
		<field>
			<fieldname>{$e2guardian_group_name}</fieldname>
			<fielddescr>{$e2guardian_groups['name']}</fielddescr>
			<description><![CDATA[Include ip addresses and or ip addresses/netmask for this group one per line<br>Hint:192.168.1.0/255.255.255.0<br>192.168.1.5]]></description>
			<type>textarea</type>

			<cols>80</cols><rows>12</rows>
			<encoding>base64</encoding>
		</field>
EOF;

		$count++;
	}
	if ($user_xml == "") {
		$user_xml .=<<<EOF
		<field>
			<name>Users</name>
			<type>listtopic</type>
		</field>
		<field>
			<fielddescr>Users</fielddescr>
			<fieldname>info_checkbox</fieldname>
			<type>info</type>
			<description><![CDATA[E2guardian users are required only when you have more then one group.<br>All unauthenticated users or unlisted users will match first filter group.]]></description>
		</field>
EOF;
	}
	//Create/update filtergroupslist
	file_put_contents($e2guardian_dir . "/lists/filtergroupslist", $filtergroupslist, LOCK_EX);
	//Create/update filtergroupsiplist
	file_put_contents($e2guardian_dir . "/lists/authplugins/ipgroups", $filtergroupsiplist, LOCK_EX);
	//Create/update userlist xml file
	$ips_xml_header = file_get_contents("/usr/local/pkg/e2guardian/templates/e2guardian_ips_header.template");
	$user_xml_header = file_get_contents("/usr/local/pkg/e2guardian/templates/e2guardian_users_header.template");
	$user_xml_footer = file_get_contents("/usr/local/pkg/e2guardian/templates/e2guardian_users_footer.template");
	file_put_contents("/usr/local/pkg/e2guardian/e2guardian_users.xml", $user_xml_header . $user_xml . $user_xml_footer, LOCK_EX);
	file_put_contents("/usr/local/pkg/e2guardian/e2guardian_ips.xml", $ips_xml_header . $ips_xml . $user_xml_footer, LOCK_EX);

	//Create report template
	if (is_dir("/usr/local/share/e2guardian/languages/{$reportlanguage}")) {
		file_put_contents("/usr/local/share/e2guardian/languages/{$reportlanguage}/template.html", e2_text_area_decode($e2guardian_log['report_file']), LOCK_EX);
	}
	//check blacklist download files
	file_put_contents("/root/e2guardian_custom.script", base64_decode($e2guardian_blacklist['custom_script']), LOCK_EX);
	if ($_POST["force_download"]) {
		log_error("Blacklist update process started");
		file_notice("E2guardian",$error,"E2guardian - " . gettext("Blacklist update process started"), "");
		if ($e2guardian_blacklist['enable_custom_script']) {
			mwexec_bg("/root/e2guardian_custom.script");
		} else {
			mwexec_bg("/usr/local/bin/php /usr/local/pkg/e2guardian/e2guardian.php fetch_blacklist");
		}
	}
	
	//Update blacklist categories on Acls
	if ($_POST["force_update"] || $_POST["liston"]) {
		require_once("/usr/local/pkg/e2guardian/e2guardian.php");
		extract_black_list();
	}
	//get clamav user
	$cconf = E2GUARDIAN_DIR . "/etc/clamd.conf";
	$cconf_file = file_get_contents($cconf);
	if (preg_match("/User (\w+)/", $cconf_file, $matches)) {
		mwexec("/usr/sbin/pw user show {$matches[1]} || /usr/sbin/pw user add -n {$matches[1]} -s /usr/sbin/nologin");
		$daemonuser = $matches[1];
		$daemongroup = 'nobody';
	} else {
		$daemonuser = 'nobody';
		$daemongroup = 'nobody';
	}
	$filtergroups = ($count > 1?($count -1):1);
	$filterip = "";
	$filterports = "";
	foreach (explode(",", $e2guardian['interface']) as $i => $iface) {
		$real_ifaces[] = dg_get_real_interface_address($iface);
		if ($real_ifaces[$i][0]) {
			$filterip .= "filterip = " . $real_ifaces[$i][0] . "\n";
		}
		$filterports .= "filterports = " . $filterport . "\n";
	}
	$filterip = ($filterip == "" ? "filterip = " : $filterip);
	$filterports = ($filterports == "" ? "filterports = $filterport" : $filterports);
	$ssl_proxy_port = $e2guardian['ssl_proxy_port'] ? $e2guardian['ssl_proxy_port'] : "8081";
	include("/usr/local/pkg/e2guardian/templates/e2guardian.conf.template");


	//log rotation script
    	log_error("Checking log rotation");
	$lrt_file = "/usr/local/pkg/e2guardian/e2guardian_logrotate.php";
	$cron_cmd = "/usr/local/bin/php -q ${lrt_file}";
	if ($logrotate == "on") {
        log_error("Log rotate on");
		e2g_check_cron($cron_cmd, "create", $cronminute, $cronhour);
	} else {
		file_put_contents($lrt_file, "", LOCK_EX);
		e2g_check_cron($cron_cmd, "remove");
	}
	
	//watchdog script;
	$cron_cmd = "/usr/local/bin/e2g_watchdog.sh";
	if ($e2guardian['watchdog'] == "on") {
		e2g_check_cron("/bin/sh $cron_cmd", "create");
		if ($e2guardian['parentmode'] == 'local') {
			//e2g parent
			$parent_pid = "/var/run/squid/e2gsquid.pid";
			$parent_cmd = "/usr/local/etc/rc.d/e2parent.sh start";
			$watchdog_parent = e2g_watchdog_script($parent_pid, $parent_cmd, "parent");
		}
		//e2g watchdog
		$we2g_pid = "/var/run/e2guardian.pid";
		$we2g_cmd = "/usr/local/etc/rc.d/e2guardian.sh start";
		$watchdog_e2g = e2g_watchdog_script($we2g_pid,$we2g_cmd,"e2guardian");
		//e2g main squid process
		if (is_array($config['installedpackages']['squid'])) {
			$squid_cfg = $config['installedpackages']['squid']['config'][0];
			if ($squid_cfg['enable_squid'] == "on") {
				$msqd_pid = "/var/run/squid/squid.pid";
				$msqd_cmd = "/usr/local/sbin/squid -f /usr/local/etc/squid/squid.conf";
				$watchdog_squid = e2g_watchdog_script($msqd_pid, $msqd_cmd, "squid");
			}
		}
		$watchdog  =  "#!/bin/sh\n";
		$watchdog .= "for a in 00 10 20 30 40 50\ndo\n";
		$watchdog .= "\t{$watchdog_e2g}\n\t{$watchdog_parent}\n\t{$watchdog_squid}\n";
		$watchdog .= "\tsleep 10\ndone\n";
		file_put_contents($cron_cmd, $watchdog, LOCK_EX);
	} else {
		file_put_contents($cron_cmd, "", LOCK_EX);
		e2g_check_cron("/bin/sh $cron_cmd", "remove");
	}

	// Check acl schedules
	$cron_cmd = "/usr/local/bin/php -q /usr/local/pkg/e2guardian/e2guardian_scheds.php";
	if (sizeof($e2g_sched_in_use) > 1) {
		e2g_check_cron($cron_cmd, "create");
	} else {
		e2g_check_cron($cron_cmd, "remove");
	}

	$cron_cmd = E2GUARDIAN_DIR . "/bin/freshclam";
	$cron_field= array('day' => '*' , '02days' => '*/2', 'week' => '*/7');
	if ($e2guardian_config['cron'] && preg_match("/clamd/", $e2guardian_config['content_scanners'])) {
		e2g_check_cron($cron_cmd, "create", "0", "0", $cron_field[$e2guardian_config['cron']]);
	} else {
		e2g_check_cron($cron_cmd, "remove");
	}
	$cron_cmd = "/usr/local/bin/php /usr/local/pkg/e2guardian/e2guardian.php fetch_blacklist";
	if ($e2guardian_blacklist['cron']) {
		e2g_check_cron($cron_cmd, "create", "0", "0", $cron_field[$e2guardian_blacklist['cron']]);
	} else {
		e2g_check_cron($cron_cmd, "remove");
	}
	$cron_cmd = "/usr/local/bin/php /usr/local/pkg/e2guardian/e2guardian_ldap.php";
	if (is_array($config['installedpackages']['e2guardiangroups']['config'])) {
		foreach ($config['installedpackages']['e2guardiangroups']['config'] as $e2guardian_groups) {
			$e2_group_source = ($e2guardian_groups['groupnamesource'] != "" ? $e2guardian_groups['groupnamesource'] : "name");
			if (preg_match('/(\d+)(h|m)/', $e2guardian_groups['freq'], $matches)) {
			    switch ($matches[2]){
			        case 'm':
			            e2g_check_cron("{$cron_cmd} $e2_group_source '{$e2guardian_groups[$e2_group_source]}'", "create", "*/{$matches[1]}", "*");
			            break;
			        case 'h':
			            e2g_check_cron("{$cron_cmd} $e2_group_source '{$e2guardian_groups[$e2_group_source]}'", "create",  "0", "*/{$matches[1]}");
			            break;
			    }
			} else {
				e2g_check_cron("{$cron_cmd} $e2_group_source '{$e2guardian_groups[$e2_group_source]}'", "remove");
			}
		}
	}

	write_config();

	$dirs = array(
		E2GUARDIAN_DIR . '/etc/e2guardian/lists/bannedrooms/',
		'/var/log/e2guardian');

	foreach ($dirs as $dir) {
		if (!is_dir($dir)) {
			mkdir ($dir, 0755, true);
		}
	}

	//update file owner
	mwexec("chown -R $daemonuser:$daemongroup " . E2GUARDIAN_DIR . "/etc/e2guardian");
	mwexec("chown -R $daemonuser:$daemongroup /var/log/e2guardian");

	//create config files
	file_put_contents($e2guardian_dir . "/e2guardian.conf", $dg, LOCK_EX);

	//check virus_scanner options
	$libexec_dir = E2GUARDIAN_DIR . "/libexec/e2guardian/";
	if ($install_process == true) {
		update_output_window("Skipping clamav check during package install.");
	}
	if (preg_match("/clamd/", $e2guardian_config['content_scanners']) && $install_process == false) {
		if (!(file_exists('/var/db/clamav/main.cvd') || file_exists('/var/db/clamav/main.cld'))) {
			file_notice("E2guardian",$error,"E2guardian - " . gettext("No antivirus database found for clamav, running freshclam in background."), "");
			log_error('No antivirus database found for clamav, running freshclam in background. Content-scanner may not work until freshclam finishes.');
			mwexec_bg(E2GUARDIAN_DIR . '/bin/freshclam && /usr/local/etc/rc.d/clamav-clamd');
		}
		//clamdscan.conf e2guardian file
		$cconf = E2GUARDIAN_DIR . "/etc/e2guardian/contentscanners/clamdscan.conf";
		$cconf_file = file_get_contents($cconf);
		if (preg_match('/#clamdudsfile/', $cconf_file)) {
			$cconf_file = preg_replace('/#clamdudsfile/', 'clamdudsfile', $cconf_file);
			file_put_contents($cconf, $cconf_file, LOCK_EX);
		}
		//clamd conf file
		$cconf = E2GUARDIAN_DIR . "/etc/clamd.conf";
		$cconf_file = file_get_contents($cconf);
		if (preg_match("/User (\w+)/", $cconf_file, $matches)) {
			#clamd script file
			$script = '/usr/local/etc/rc.d/clamav-clamd';
			$script_file = file($script);
			$new_clamav_startup = "";
			$cpreg_m[0] = "@NO@";
			$cpreg_m[1] = "@/usr/local@";
			$cpreg_r[0] = "YES";
			$cpreg_r[1] = E2GUARDIAN_DIR;
			foreach ($script_file as $script_line) {
				if (preg_match("/command=/", $script_line)) {
					$new_clamav_startup .= 'if [ ! -d /var/run/clamav ];then /bin/mkdir /var/run/clamav;fi' . "\n";
					$new_clamav_startup .= 'if [ ! -d /var/db/clamav ];then /bin/mkdir /var/db/clamav;fi' . "\n";
					$new_clamav_startup .= 'if [ ! -d /var/log/clamav ];then /bin/mkdir -p /var/log/clamav;fi' . "\n";
					$new_clamav_startup .= "chown -R " . $matches[1] . " /var/run/clamav\n";
					$new_clamav_startup .= "chown -R " . $matches[1] . " /var/db/clamav\n";
					$new_clamav_startup .= "chown -R " . $matches[1] . " /var/log/clamav\n";
					$new_clamav_startup .= $script_line;
				} elseif (!preg_match("/(mkdir|chown|sleep|mailscanner)/", $script_line)) {
					$new_clamav_startup .= preg_replace($cpreg_m, $cpreg_r, $script_line);
				}
			}
			file_put_contents($script, $new_clamav_startup, LOCK_EX);
			chmod ($script, 0755);

			if (file_exists('/var/run/e2guardian.pid') && is_process_running('clamd')) {
				log_error('Stopping clamav-clamd');
				mwexec("$script stop");
			}
			unlink_if_exists("/tmp/.dguardianipc");
			unlink_if_exists("/tmp/.dguardianurlipc");
			if (!is_process_running('clamd')) {
				log_error('Starting clamav-clamd');
				mwexec_bg("$script start");
			}
		}
	}
	//mark config changes
	if (!$_POST["force_update"] && !$_POST["force_download"] && !$_POST["liston"]) {
		mark_subsystem_dirty('e2guardian');
	}
	if ($install_process == true) {
		clear_subsystem_dirty('e2guardian');
	}
}

function e2g_check_cron($cron_cmd, $action, $min = "*", $hour = "*", $mday = "*", $month = "*", $wday = "*"){

	//Remove current job
	install_cron_job ($cron_cmd, false);
	if ($action == "create") {
		//Install a new job
		install_cron_job ($cron_cmd . ' > /dev/null', true, $min, $hour, $mday, $month, $wday , "root");
	}
}

function e2guardian_check_config() {
	global $savemsg, $config;
	
	if (!isset($config['installedpackages']['e2guardian']['config'][0]['enable'])) {
		return 1;
	}

	if (isset($_REQUEST['apply'])) {
		e2guardian_start("no", false, true);
		return;
	}
	if (is_subsystem_dirty('e2guardian')) {
		$savemsg  = gettext("E2guardian configuration has been changed.") . "<br>" . gettext("The changes must be applied for them to take effect.");
        	$savemsg .= "</div><div class='pull-right'><button type='button' onclick=\"location.href='{$_SERVER['REQUEST_URI']}&apply=apply changes'\" class='btn btn-success'>";
		$savemsg .= gettext("Apply Changes") . "</button><a>&nbsp;&nbsp;</a>";
	}
	return 0;
}

function e2guardian_start($via_rpc = "no", $install_process = false, $force_start = false) {
	global $g, $config, $savemsg;

	clear_subsystem_dirty('e2guardian');

	if (!$force_start) {
		if (e2guardian_check_config() <> 0) {
			return;
		}
	}

	if (is_array($config['installedpackages']['e2guardian']['config'])) {
                $e2g_cfg = $config['installedpackages']['e2guardian']['config'][0];
        } else {
                log_error('Cannot find E2guardian daemon configuration');
        }

	//as main squid daemon looks for any squid copy running, lets create an alias for us
	$our_squid = "/usr/local/sbin/e2guid";
	if (!file_exists($our_squid) && $e2g_cfg['parentmode'] == 'local') {
		system("/usr/local/etc/rc.d/e2parent.sh stop");
		symlink("/usr/local/sbin/squid", $our_squid);
	}

	//squid parent start stop script
	$parent_pid = "/var/run/squid/e2gsquid.pid";
	$parent_cmd = "$our_squid -f /usr/local/etc/e2guardian/squidparent.conf";
	$start  = "\nif [ ! -d /var/squid/lib/ssl_db2/certs ];then\n\t";
	$start .= "mkdir -p /var/squid/lib\n\t";
	$start .= "/usr/local/libexec/squid/ssl_crtd -c -s /var/squid/lib/ssl_db2\nfi\n";
	$start .= "if [ -f {$parent_pid} ];then\n\t";
	$start .= "cat {$parent_pid} | xargs ps ||\n\t";
	$start .= "{$parent_cmd}\nelse\n\t";
	$start .= "{$parent_cmd}\nfi\n";
	$stop  = "if [ -f {$parent_pid} ];then\n\t";
	$stop .= "kill `cat {$parent_pid}`\nfi";
	write_rcfile(array("file" => "e2parent.sh", "start" => $start, "stop" => $stop));

	$script = '/usr/local/etc/rc.d/e2guardian.sh';
	$script_parent = '/usr/local/etc/rc.d/e2parent.sh';
	unlink_if_exists('/usr/local/etc/rc.d/e2guardian');
	$squid_parent_pid = "/var/run/squid/e2gsquid.pid";

	if ($e2g_cfg['enable'] == "on") {
		chmod("/usr/local/sbin/e2guardian",0755);
		copy('/usr/local/pkg/e2guardian/templates/e2guardian_rc.template', $script);
		chmod ($script, 0755);
		$max_threads = "sysctl kern.threads.max_threads_per_proc=20480";
		if (is_process_running('e2guardian')) {
			switch ($e2g_cfg['applyaction']){
				case "rc.d":
					log_error("Restarting e2g with rc.d script");
					mwexec("$script stop");
					sleep(2);
                     			mwexec_bg("$script start");
					break;
				case "-r":
					log_error("Sending USR1 to e2g processes");
					mwexec_bg("$max_threads;/usr/local/sbin/e2guardian -g");
					break;
				default:
					log_error("Restarting e2g by sending -Q action to e2g binaries");
					mwexec_bg("$max_threads;/usr/local/sbin/e2guardian -Q");
					break;
			}
		} else {
			//log_error('Starting E2guardian');
			//mwexec("$script start");
		}
		//force local squid daemon stop or start depending on config files
		$parent_status = ( $e2g_cfg['parentmode'] == 'local' ? "stop" : "start");
		mwexec_bg("$script_parent $parent_status");
	} else {
		if (is_process_running('e2guardian')) {
			log_error('E2guardian is disabled, stopping process...');
			mwexec_bg("$script stop");
		}
		mwexec_bg("$script_parent stop");
		if (file_exists($script)) {
			chmod ($script, 444);
		}
	}
	if (!file_exists(E2GUARDIAN_DIR . '/etc/e2guardian/lists/phraselists/pornography/weighted_russian_utf8')) {
		file_put_contents(E2GUARDIAN_DIR . '/etc/e2guardian/lists/phraselists/pornography/weighted_russian_utf8', "", LOCK_EX);
	}

	//check ca certs hashes
	check_ca_hashes();

	//avoid sync during boot process
	if (!isset($boot_process) || $via_rpc == "yes") {
		/* Uses XMLRPC to synchronize the changes to a remote node */
		if (is_array($config['installedpackages']['e2guardiansync']['config'])) {
			$e2_sync = $config['installedpackages']['e2guardiansync']['config'][0];
			$synconchanges = $e2_sync['synconchanges'];
			$synctimeout = $e2_sync['synctimeout'];
			switch ($synconchanges) {
				case "manual":
					if (is_array($e2_sync[row])) {
						$rs = $e2_sync[row];
					} else {
						log_error("[E2guardian] xmlrpc sync is enabled but there is no hosts to push on e2guardian config.");
						return;
					}
					break;
				case "auto":
					if (is_array($config['hasync'])) {
						$system_carp = $config['hasync'];
						$rs[0]['ipaddress'] = $system_carp['synchronizetoip'];
						$rs[0]['username'] = $system_carp['username'];
						$rs[0]['password'] = $system_carp['password'];
						$rs[0]['sync_type'] = "xmlrpc";
						if (!is_ipaddr($system_carp['synchronizetoip'])) {
							log_error("[E2guardian] xmlrpc sync is enabled but there is no system backup hosts to push squid config.");
							return;
						}
					} else {
						log_error("[E2guardian] xmlrpc sync is enabled but there is no system backup hosts to push squid config.");
						return;
					}
					break;
				default:
					return;
				break;
			}
			if (is_array($rs)) {
				log_error("[E2guardian] xmlrpc sync is starting.");
				foreach ($rs as $sh) {
					if ($sh['syncdestinenable']) {
						// Only sync enabled replication targets
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
							e2guardian_do_xmlrpc_sync($sync_to_ip, $port, $protocol, $username, $password, $synctimeout);
						} else {
							log_error("[E2guardian] XMLRPC sync with '{$sync_to_ip}' aborted due to the following error(s): {$error}");
						}
				
					}
				}
				log_error("[E2guardian] xmlrpc sync is ending.");
			}
		}
	}
}

function e2guardian_validate_input($post, &$input_errors) {
	global $config;
	/* Manual update blacklist */
        if ($post['update_av'] == 'Update Blacklist') {
		//parei aqui
                squid_update_clamav();
                return;
        }
	if (is_array($config['installedpackages']['e2guardian'])) {
		$settings = $config['installedpackages']['e2guardian']['config'][0];
	} else {
		$settings = array();
	}


	if ( is_array($post['scan_options']) && in_array('logclienthostnames', $post['scan_options']) && 
	    !in_array('reverseclientiplookups', $post['scan_options'])) {
		$input_errors[] = "Scan option 'Log client hostnames' needs 'Reverse client ip lookups' to be selected as well.";
	}
	if (is_array($post['group_options']) && sizeof ($post['group_options']) > 0) {
		$_POST['lgroupoptions'] = implode (" ", $post['group_options']);
	}
	if ($post['banned_sitelist'] != "") {
		$includes = explode ("\n",$post['banned_sitelist']);
		foreach ($includes as $cline) {
			if (preg_match("/^.Include\<(\S+)\>/", $cline, $clm)){
				if (!file_exists($clm[1])) {
					$input_errors[] = "$clm[1] listed on banned config text area does not exists on file system";
					$input_errors[] = "Comment or remove this line before save config again.";
				}
			}
		}
	}
	if ($post['banned_urllist'] != "") {
		$includes = explode ("\n",$post['banned_urllist']);
		foreach ($includes as $cline) {
			if (preg_match("/^.Include\<(\S+)\>/", $cline, $clm)){
				if (!file_exists($clm[1])) {
					$input_errors[] = "$clm[1] listed on banned config text area does not exists on file system";
					$input_errors[] = "Comment or remove this line before save config again.";
				}
			}
		}
	}
	
	if ($post['reportinglevel'] != "") {
		 $rpa = array ( 'global' => 'Use Global log option',
 				'-1' => 'Stealth mode',
				'0' => 'Just say Access Denied',
				'1' => 'Report why but not what',
				'2' => 'Report fully',
				'3' => 'Use HTML template');
		$_POST['lreportinglevel'] = $rpa[$post['reportinglevel']];
	}
	
	if ($post['report_language'] != "") {
	    $e2glog = $config['installedpackages']['e2guardianlog']['config'][0];
    	if ($_POST['report_file'] == "" || $post['report_language'] != $e2glog['report_language']) {
    	    $rf = "/usr/local/share/e2guardian/languages/{$post['report_language']}/template.html";
    	    if (file_exists($rf) && ! file_exists("{$rf}.sample")) {
    	        copy ($rf, "{$rf}.sample");
    	    }
    		$rf .= ".sample";
    		if( file_exists($rf)) {
    			$report_file = file_get_contents($rf);
    			$_POST['report_file'] = preg_replace('/<.*(html|head)>/', '', $report_file);
    		}
    	}
	}

	if ($post['reportingcgi'] != "") {
		if (! is_URL($post['reportingcgi'])) {
			$input_errors[] = "Reporting cgi/dynamic url is invalid.";
		}
	}

	if ($post['parentmode'] == "local" && $post['dca'] == "") {
		 $input_errors[] = "E2guardian needs a configured CA while using automatic parent proxy mode.";
	}
	if ($post['parentmode'] == "remote") {
		if ($post['proxyip'] == "") {
			$input_errors[] = "E2guardian proxy ip cannot be empty in manual parent mode.";
		}
		 if ($post['proxyport'] == "" || !is_numeric($post['proxyport'])) {
                        $input_errors[] = "E2guardian proxy port cannot be empty or invalid in manual parent mode.";
                }
	}

	// check that the proxy port does not clash with WebGUI
	$port = ($settings['filterports'] ? $settings['filterports'] : 8080);
	$port = $post['filterports'] ? $post['filterports'] : $port;
	$webgui_port = $config['system']['webgui']['port'];
	if (($config['system']['webgui']['port'] == "") && ($config['system']['webgui']['protocol'] == "http")) {
                $webgui_port = 80;
        }
        if (($config['system']['webgui']['port'] == "") && ($config['system']['webgui']['protocol'] == "https")) {
                $webgui_port = 443;
        }
        if (($post['transparent_proxy'] != 'on') && ($port == $webgui_port)) {
                $input_errors[] = "You can not run E2guardian on the same port as the pfSense WebGUI";
        }
        unset($port, $webgui_port);
	if ($post['transparent_proxy'] == 'on') {
                if (empty($post['transparent_active_interface'])) {
                        $input_errors[] = "You must select at least one interface under 'Transparent Proxy Interface(s)' when 'Transparent HTTP Proxy' is enabled.";
		} else {
                        // allow HTTPS/SSL Interception only on interfaces where Squid is actually running to keep configuration sane
                        $a_ifaces = $post['interface'] ?: array();
                        $s_ifaces = $post['transparent_active_interface'];
                        foreach ($s_ifaces as $s_iface) {
                                if (!in_array($s_iface, $a_ifaces)) {
                                        $err_iface = convert_friendly_interface_to_friendly_descr($s_iface);
                                        $input_errors[] = "'Transparent proxy Interface(s)' may only contain interfaces also selected in 'Proxy Interface(s)' above. '{$err_iface}' is not valid.";
                                        unset($err_iface);
                                }
                        }
                        unset($a_ifaces, $s_ifaces, $s_iface);
                }
        }


	foreach ($post as $key => $value) {
		if (preg_match("/^(name|description)$/", $key) && $value == "") {
			$input_errors[] = "{$key} could not be empty.";
		} else if ($key == "name" && $value == "sample") {
			$input_errors[] = "{$value} cannot be used as name.";
		} else if ($key == "name" && preg_match("/\W/", $value)) {
			$input_errors[] = "{$value} cannot be used as name. Use only a-z 0-9 characters";
        } else if ($key == "name" && !isValidXmlElementName($value)){
			$input_errors[] = "{$value} cannot be used as name. It does not comply with the XML tag naming conventions";
		} else if ($key == "group_options") {
			$acls = array("pics", "phrase", "site", "url", "extension", "header", "content", "search");
			foreach ($acls as $gacl) {
				if (!array_key_exists($gacl . "acl", $post)) {
					$input_errors[] = ucfirst($gacl) . " must has at least one acl assigned.";
				}
			}
		} else if (empty($value)) {
			continue;
		} else if ($key == "freq" && (!preg_match("/^\d+(h|m|d)$/", $value) || $value == 0)) {
			$input_errors[] = "A valid number with a time reference is required for the field 'Update Frequency'";
		}
	}
}

function isValidXmlElementName($elementName)
{
    try {
        new DOMElement($elementName);
    } catch (DOMException $e) {
        return false;
    }
    return true;
}

function e2guardian_php_install_command() {
	sync_package_e2guardian("no", true);
	echo "Checking Blacklist...\n";
	require_once("/usr/local/pkg/e2guardian/e2guardian.php");

	if (file_exists("/usr/local/pkg/e2guardian/blacklist.tgz")) {
		extract_black_list();
	} else {
        	fetch_blacklist(false, true);
	}
        echo "Blacklist check done, continuing package config sync.\n";
	clear_subsystem_dirty('e2guardian');
}

function e2guardian_php_deinstall_command() {
	global $config, $g;
	
	echo "Remove modified xml files...\n";
	$e2gxmls = array("users", "ips");
	foreach ($e2gxmls as $e2gxml) {
		unlink_if_exists("/usr/local/pkg/e2guardian/e2guardian_{$e2gxml}.xml");
	}
	echo "Removing package crons...\n";
	$crons = array("/bin/sh /usr/local/bin/e2g_watchdog.sh", //robotman3000 - Leave this until the branch to the v5 gui
			"/usr/local/bin/php -q /usr/local/pkg/e2guardian/e2guardian_scheds.php",
			E2GUARDIAN_DIR . "/bin/freshclam",
			"/usr/local/bin/php /usr/local/pkg/e2guardian/e2guardian.php fetch_blacklist",
			"/usr/local/bin/php /usr/local/pkg/e2guardian/e2guardian_ldap.php",
			"/usr/local/bin/php -q /usr/local/pkg/e2guardian/e2guardian_logrotate.php");
	foreach ($crons as $cron_cmd) {
		e2g_check_cron($cron_cmd, "remove");
	}
	
	echo "Disabling automtic parent squid script...\n";
	$ecmds = array("e2guardian squid parent" => "/usr/local/etc/rc.d/e2parent.sh",
		       "e2guradian" => "/usr/local/etc/rc.d/e2guardian.sh");
	foreach ($ecmds as $einfo => $ecmd) {
		if (file_exists($ecmd)) {
			log_error("stopping $einfo..");
			mwexec("$ecmd stop");
			unlink($ecmd);
		}
	}

	//prevent e2guardian binary from starting from watchdog during package uninstall
	chmod("/usr/local/sbin/e2guardian",0444);

	echo "Removing conf files...\n";
	$rconfs = array('*sample', '*conf', 'lists/*.*', 'authplugins/*sample', 'contentscanners/*sample', 'downloadmanagers/*sample');
	foreach ($rconfs as $rm) {
		system("/bin/rm -f /usr/local/etc/e2guardian/$rm");
	}
    
	clear_subsystem_dirty('e2guardian');
}




function e2guardian_do_xmlrpc_sync($sync_to_ip, $port, $protocol, $username, $password, $synctimeout = 30) {
	global $config, $g;

	if ($username == "" || $password == "" || $sync_to_ip == "" || $port == "" || $protocol == "") {
		log_error("[E2guardian] A required XMLRPC sync parameter (username, password, replication target, port or protocol) is empty ... aborting pkg sync");
		return;
	}

	$xmlrpc_sync_neighbor = $sync_to_ip;
        if ($config['system']['webgui']['protocol'] != "") {
                $synchronizetoip  = $config['system']['webgui']['protocol'];
                $synchronizetoip .= "://";
		if ($protocol == "") {
                	$protocol = $config['system']['webgui']['protocol'];
        	}
        }

        /* if port is empty lets rely on the protocol selection */
        if ($port == "") {
                if ($config['system']['webgui']['protocol'] == "http") {
                        $port = "80";
                } else {
                        $port = "443";
                }
        }

        $synchronizetoip .= $sync_to_ip;

	/* Commands to reload Squid settings on the destination sync host. */
	$execcmd  = "require_once('/usr/local/pkg/e2guardian/e2guardian.inc');\n";
	$execcmd .= "sync_package_e2guardian('yes');\n";
	$execcmd .= "e2guardian_start('yes');";

	 /* xml will hold the sections to sync */
        log_error("Include e2guardian config");
	$elements = array('e2guardian', 'e2guardianantivirusacl', 'e2guardianconfig', 'e2guardianblacklist', 'e2guardianblacklist',
			'e2guardianldap', 'e2guardiancontentacl', 'e2guardianfileacl', 'e2guardiangroups', 'e2guardianheaderacl',
			'e2guardianlimits', 'e2guardianlog', 'e2guardianphraseacl', 'e2guardianpicsacl', 'e2guardiansearchacl',
			'e2guardiansiteacl', 'e2guardianurlacl', 'e2guardianusers', 'e2guardianips');

        $xml = array();
	foreach ($elements as $element) {
		$xml[$element] = $config['installedpackages'][$element];
	}

	// since 2.4 xmlrpc cannot encode NULL objects/arrays..
	foreach($xml as $xmlkey => $xmlvalue) {
		if (gettype($xmlvalue) == "NULL") {
			$xml[$xmlkey] = array();
		}
	}
	$synctimeout = intval($synctimeout);
	$rpc_client = new pfsense_xmlrpc_client();
	$rpc_client->setConnectionData($sync_to_ip, $port, $username, $password, $protocol);
	$resp = $rpc_client->xmlrpc_method('merge_installedpackages_section', $xml, $synctimeout);
	$resp = $rpc_client->xmlrpc_exec_php($execcmd, $synctimeout);

}

?>
