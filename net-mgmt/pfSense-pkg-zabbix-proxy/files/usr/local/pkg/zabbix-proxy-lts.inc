<?php
/* $Id$ */
/* ========================================================================== */
/*
    zabbix-proxy-lts.inc
    part of the Zabbix package for pfSense
    Copyright (C) 2013 Danilo G. Baio
    Copyright (C) 2013 Marcello Coutinho

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
/* ========================================================================== */
require_once("util.inc");
require_once("functions.inc");
require_once("pkg-utils.inc");
require_once("globals.inc");

$pf_version=substr(trim(file_get_contents("/etc/version")),0,3);
if ($pf_version == "2.1" || $pf_version == "2.2") {
	define('ZABBIX_PROXY_BASE', '/usr/pbi/zabbix22-proxy-' . php_uname("m"));
} else {
	define('ZABBIX_PROXY_BASE', '/usr/local');
}

function php_install_zabbix_proxy_lts(){
	sync_package_zabbix_proxy_lts();	
}

function php_deinstall_zabbix_proxy_lts(){
   global $config, $g;

   conf_mount_rw();

   exec("/usr/bin/killall zabbix_proxy");
   unlink_if_exists(ZABBIX_PROXY_BASE . "/etc/rc.d/zabbix_proxy_lts.sh");
   unlink_if_exists(ZABBIX_PROXY_BASE . "/etc/zabbix22/zabbix_proxy_lts.conf");
   unlink_if_exists("/var/log/zabbix-proxy-lts/zabbix_proxy_lts.log");
   unlink_if_exists("/var/run/zabbix-proxy-lts/zabbix_proxy_lts.pid");

	if (is_dir("/var/log/zabbix-proxy-lts")) {
		exec("/bin/rm -r /var/log/zabbix-proxy-lts/");
	}
	if (is_dir("/var/run/zabbix-proxy-lts")) {
		exec("/bin/rm -r /var/run/zabbix-proxy-lts/");
	}
   if (is_dir("/var/db/zabbix-proxy-lts")) {
		exec("/bin/rm -r /var/db/zabbix-proxy-lts/");
	}

   conf_mount_ro();
}

function validate_input_zabbix_proxy_lts($post, &$input_errors){
	if  (isset($post['proxyenabled'])) {
		if (!preg_match("/\w+/", $post['server'])) {
			$input_errors[]='Server field is required.';
		}

		if (!is_numericint($post['serverport'])) {
			$input_errors[]='Server Port is not numeric.'.$ServerPort;
		}

		if (!preg_match("/\w+/", $post['hostname'])) {
			$input_errors[]='Hostname field is required.';
		}

		if (!is_numericint($post['configfrequency'])) {
			$input_errors[]='Config Frequency is not numeric.';
		}
	}
}

function sync_package_zabbix_proxy_lts(){
	global $config, $g;

	conf_mount_rw();

	#check zabbix proxy config
	if (is_array($config['installedpackages']['zabbixproxylts'])){
		$zbproxy_config = $config['installedpackages']['zabbixproxylts']['config'][0];
		if ($zbproxy_config['proxyenabled']=="on"){
			$Mode=(is_numericint($zbproxy_config['proxymode'])?$zbproxy_config['proxymode'] : 0);
			$AdvancedParams=base64_decode($zbproxy_config['advancedparams']);
		
			$zbproxy_conf_file = <<< EOF
Server={$zbproxy_config['server']}
ServerPort={$zbproxy_config['serverport']}
Hostname={$zbproxy_config['hostname']}
PidFile=/var/run/zabbix-proxy-lts/zabbix_proxy_lts.pid
DBName=/var/db/zabbix-proxy-lts/proxy.db
LogFile=/var/log/zabbix-proxy-lts/zabbix_proxy_lts.log
ConfigFrequency={$zbproxy_config['configfrequency']}
FpingLocation=/usr/local/sbin/fping
#there's currently no fping6 (IPv6) dependency in the package, but if there was, the binary would likely also be in /usr/local/sbin
Fping6Location=/usr/local/sbin/fping6
ProxyMode={$Mode}
{$AdvancedParams}

EOF;
			file_put_contents(ZABBIX_PROXY_BASE . "/etc/zabbix22/zabbix_proxy.conf", strtr($zbproxy_conf_file, array("\r" => "")));
		}
	}

	$want_sysctls = array(
		'kern.ipc.shmall' => '2097152',
		'kern.ipc.shmmax' => '2147483648',
		'kern.ipc.semmsl' => '250'
	);
	$sysctls = array();
	#check sysctl file values
	$sc_file="";
	if (file_exists("/etc/sysctl.conf")) {
		$sc = file("/etc/sysctl.conf");
		foreach ($sc as $line) {
			list($sysk, $sysv) = explode("=", $line, 2);
			if (preg_match("/\w/",$line) && !array_key_exists($sysk, $want_sysctls))
				$sc_file.=$line;
			}
	}
	foreach ($want_sysctls as $ws=> $wv) {
		$sc_file .= "{$ws}={$wv}\n";
		exec("/sbin/sysctl {$ws}={$wv}");
	}
	file_put_contents("/etc/sysctl.conf", $sc_file);

	#check bootloader values
	$lt_file="";
	$want_tunables = array(
		'kern.ipc.semopm' => '100',
		'kern.ipc.semmni' => '128',
		'kern.ipc.semmns' => '32000',
		'kern.ipc.shmmni' => '4096'
	);
	$tunables = array();
	if (file_exists("/boot/loader.conf")) {
		$lt = file("/boot/loader.conf");
		foreach ($lt as $line) {
			list($tunable, $val) = explode("=", $line, 2);
			if (preg_match("/\w/",$line) && !array_key_exists($tunable, $want_tunables))
				$lt_file.=$line;
		}
	}
	foreach ($want_tunables as $wt => $wv) {
		$lt_file.= "{$wt}={$wv}\n";
	}
	file_put_contents("/boot/loader.conf", $lt_file);

	/*check startup script files*/
	/* create a few directories and ensure the sample files are in place */
	if (!is_dir(ZABBIX_PROXY_BASE . "/etc/zabbix22"))
		exec("/bin/mkdir -p " . ZABBIX_PROXY_BASE . "/etc/zabbix22");

	$dir_checks  = <<< EOF

	if [ ! -d /var/log/zabbix-proxy-lts ]; then
		/bin/mkdir -p /var/log/zabbix-proxy-lts
		/usr/sbin/chmod 755 /var/log/zabbix-proxy-lts
	fi
	/usr/sbin/chown -R zabbix:zabbix /var/log/zabbix-proxy-lts

	if [ ! -d /var/run/zabbix-proxy-lts ]; then
		/bin/mkdir -p /var/run/zabbix-proxy-lts
		/usr/sbin/chmod 755 /var/run/zabbix-proxy-lts
	fi
	/usr/sbin/chown -R zabbix:zabbix /var/run/zabbix-proxy-lts

	if [ ! -d /var/db/zabbix-proxy-lts ]; then
		/bin/mkdir -p /var/db/zabbix-proxy-lts
		/usr/sbin/chmod 755 /var/db/zabbix-proxy-lts
	fi
	/usr/sbin/chown -R zabbix:zabbix /var/db/zabbix-proxy-lts

EOF;

	$pid_check  = <<< EOF

	/bin/pgrep -anx zabbix_proxy 2>/dev/null
	if [ "\$?" -eq "0" ]; then
		/usr/bin/killall -9 zabbix_proxy
		/bin/rm -f /var/run/zabbix-proxy-lts/zabbix_proxy_lts.pid
	fi

EOF;
 
	$zproxy_rcfile="/usr/local/etc/rc.d/zabbix_proxy_lts.sh";
	if (is_array($zbproxy_config) && $zbproxy_config['proxyenabled']=="on"){
		$zproxy_start  = strtr($dir_checks, array("\r" => ""));
		$zproxy_start .= "\techo \"Starting Zabbix Proxy LTS\"...\n";
		$zproxy_start .= "\t" . ZABBIX_PROXY_BASE . "/sbin/zabbix_proxy\n";
	
		$zproxy_stop  = "echo \"Stopping Zabbix Proxy LTS\"\n";
		$zproxy_stop .= "\t/usr/bin/killall zabbix_proxy\n";
		$zproxy_stop .= "\t/bin/sleep 5\n";
		$zproxy_stop .= strtr($pid_check, array("\r" => ""));

		/* write out rc.d start/stop file */
		write_rcfile(array(
			"file" => "zabbix_proxy_lts.sh",
			"start" => $zproxy_start,
			"stop" => $zproxy_stop
			)
		);
		mwexec("{$zproxy_rcfile} restart");
	}else{
		if (file_exists($zproxy_rcfile)){
		mwexec("{$zproxy_rcfile} stop");
		unlink($zproxy_rcfile);
		}
	}
	
	conf_mount_ro();
}

?>
