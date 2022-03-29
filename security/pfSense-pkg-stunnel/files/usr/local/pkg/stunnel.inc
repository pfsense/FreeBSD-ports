<?php
/*
 * stunnel.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2022 Rubicon Communications, LLC (Netgate)
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

require_once("certs.inc");
require_once("config.inc");
require_once("pfsense-utils.inc");
require_once("util.inc");
require_once('services.inc');
require_once('service-utils.inc');
require_once("xmlrpc_client.inc");

define('STUNNEL_LOCALBASE', '/usr/local');
define('STUNNEL_ETCDIR', STUNNEL_LOCALBASE . "/etc/stunnel");
define('STUNNEL_CHROOTDIR', '/var/tmp/stunnel');

function stunnel_save() {
	global $config;

	init_config_arr(array('installedpackages', 'stunnel', 'config'));
	$stunnelconfig = &$config['installedpackages']['stunnel']['config'];
	$stunnel_conf = STUNNEL_ETCDIR . "/stunnel.conf";

	if (is_array($stunnelconfig) && !empty($stunnelconfig)) {
		stunnel_make_directories();
		stunnel_rcfile();
	} else {
		if (isvalidproc("stunnel")) {
			killbyname("stunnel");
		}
		rmdir_recursive(STUNNEL_ETCDIR);
		rmdir_recursive(STUNNEL_CHROOTDIR);
		unlink_if_exists("/usr/local/etc/rc.d/stunnel.sh");
		return;
	}

	$conf = "cert = " . STUNNEL_ETCDIR . "/stunnel.pem \n";
	$conf .= "chroot = " . STUNNEL_CHROOTDIR . " \n";
	$conf .= "setuid = stunnel \n";
	$conf .= "setgid = stunnel \n";

	// Keep array of in-use certificates so we can clean up after ourselves.
	$in_use_certs = array();
	$in_use_certs[] = 'stunnel.pem';

	foreach ($stunnelconfig as $num => $pkgconfig) {
		$conf .= "\n[" . $pkgconfig['description'] . "]\n";
		if ($pkgconfig['client']) {
			$conf .= "client = yes" . "\n";
		}
		if ($pkgconfig['certificate'] &&
		    $pkgconfig['certificate'] != 'default') {
			$cert = lookup_cert($pkgconfig['certificate']);
			$cert_details = openssl_x509_parse(base64_decode($cert['crt']));
			$replace_cert = true;
			if ($cert != false) {
				$certpath = STUNNEL_ETCDIR .  "/{$pkgconfig['certificate']}.pem";
				if (file_exists($certpath)) {
					$oldcert_details = openssl_x509_parse(file_get_contents($certpath));
					if ($cert_details['hash'] == $oldcert_details['hash']) {
						$replace_cert = false;
					}
				}
				if ($replace_cert) {
					file_put_contents(STUNNEL_ETCDIR .
					    "/{$pkgconfig['certificate']}.pem",
					    trim(base64_decode($cert['prv'])) . "\n" .
					    trim(base64_decode($cert['crt'])) . "\n" .
					    ca_chain($cert));
					chmod(STUNNEL_ETCDIR . "/{$pkgconfig['certificate']}.pem", 0600);
				}
				$conf .= "cert = " . STUNNEL_ETCDIR .  "/{$pkgconfig['certificate']}.pem\n";
				$in_use_certs[] = $pkgconfig['certificate'] . ".pem";
				$stunnelconfig[$num]['certname'] = $cert['descr'];
			}
		} else {
			stunnel_make_default_cert();
		}

		if ($pkgconfig['sourceip']) {
			$conf .= "local = " . $pkgconfig['sourceip'] . "\n";
		}

		$conf .= "accept = " . ($pkgconfig['localip'] ? $pkgconfig['localip'] . ":" : "") . $pkgconfig['localport'] . "\n";
		$conf .= "connect = " . $pkgconfig['redirectip'] . ":" . $pkgconfig['redirectport'] . "\n";
		$conf .= "debug = " . $pkgconfig['loglevel'] . "\n";
		if ($pkgconfig['timeoutclose'] != "") {
			$conf .= "TIMEOUTclose = " . $pkgconfig['timeoutclose'] . "\n";
		}
		if ($pkgconfig['protocol']) {
			$conf .= "protocol = {$pkgconfig['protocol']}\n";
		}
		$conf .= base64_decode($pkgconfig['custom_options']) . "\n";
	}

	if (file_exists($stunnel_conf)) {
		$oldconf = file_get_contents($stunnel_conf);
	} else {
		$oldconf = '';
	}

	if ($conf != $oldconf) {
		file_put_contents($stunnel_conf, $conf);
		write_config('stunnel configuration changed');
		stunnel_sync_config();
	}

	// Clean up certs that are no longer in use.
	foreach (glob(STUNNEL_ETCDIR . "/*.pem") as $file) {
		if (!in_array(basename($file), $in_use_certs)) {
			unlink_if_exists($file);
		}
	}

	restart_service("stunnel");
}

function stunnel_make_directories() {
	safe_mkdir(STUNNEL_ETCDIR);
	safe_mkdir(STUNNEL_CHROOTDIR . "/var/tmp/run/stunnel");
	system("/usr/sbin/chown -R stunnel:stunnel " . STUNNEL_CHROOTDIR);
}

function stunnel_make_default_cert() {
	global $g, $config;

	/*
	 * Generate a self-signed default certificate if one does not already
	 * exist.
	 */
	$stunnel_pem_filename = STUNNEL_ETCDIR . "/stunnel.pem";
	if (file_exists($stunnel_pem_filename)) {
		return;
	}

	$cert = array();
	$cert['refid'] = uniqid();
	$cert['descr'] = sprintf(gettext("stunnel default (%s)"),
	    $cert['refid']);
	$cert_hostname = $config['system']['hostname'] . '-' .  $cert['refid'];

	$dn = array(
	    'organizationName' => "{$g['product_name']} stunnel " .
		"Self-Signed Certificate",
	    'commonName' => $cert_hostname,
	    'subjectAltName' => "DNS:{$cert_hostname}"
	);

	/*
	 * otherwise openssl_ functions throw warnings directly to a
	 * page screwing menu tab
	 */
	$old_err_level = error_reporting(0);
	if (cert_create($cert, null, 2048, 365, $dn, "self-signed", "sha256")) {
		/*
		 * Write the .pem file to the expected default location
		 * for stunnel and set up required permissions.
		 */
		file_put_contents(STUNNEL_ETCDIR . "/stunnel.pem",
		    trim(base64_decode($cert['prv'])) . "\n" .
		    trim(base64_decode($cert['crt'])) . "\n");
		chmod(STUNNEL_ETCDIR . "/stunnel.pem", 0600);
	} else {
		while ($ssl_err = openssl_error_string()) {
			log_error(sprintf(gettext("Error creating stunnel " .
			    "certificate: openssl library returns: %s"),
			    $ssl_err));
		}
	}
	error_reporting($old_err_level);
}

function stunnel_rcfile() {
	$_rcfile['file'] = 'stunnel.sh';
	$_rcfile['start'] = STUNNEL_LOCALBASE . "/bin/stunnel " .
	    STUNNEL_ETCDIR . "/stunnel.conf \n\t";
	$_rcfile['stop'] = "/usr/bin/killall stunnel \n\t";
	if (!file_exists("/usr/local/etc/rc.d/stunnel.sh")) {
		write_rcfile($_rcfile);
	}
	unlink_if_exists("/usr/local/etc/rc.d/stunnel");
}

function stunnel_deinstall() {
	rmdir_recursive(STUNNEL_CHROOTDIR);
	rmdir_recursive(STUNNEL_ETCDIR);
	unlink_if_exists("/usr/local/etc/rc.d/stunnel.sh");
}

function stunnel_sync_config() {
	global $config;
	init_config_arr(array('hasync'));
	$system_carp = $config['hasync'];
	if (!is_array($system_carp) ||
	    !$system_carp['synchronizetoip'] ||
	    !$system_carp['username'] ||
	    !$system_carp['password']) {
		return -1;
	}

	if ($config['system']['webgui']['protocol'] == "http") {
		$varsyncprotocol = "http";
		$varsyncport = $config['system']['webgui']['port'] ?: '80';
	} else {
		$varsyncprotocol = "https";
		$varsyncport = $config['system']['webgui']['port'] ?: '443';
	}
	stunnel_do_xmlrpc_sync($system_carp['synchronizetoip'], $varsyncport,
	    $varsyncprotocol, $system_carp['username'],
	    $system_carp['password'], 100);
}

/* Do the actual XMLRPC sync */
function stunnel_do_xmlrpc_sync($sync_to_ip, $port, $protocol, $username,
    $password, $synctimeout) {
	global $config;

	$xml = array();
	$xml['stunnel'] = $config['installedpackages']['stunnel'];

	$remote_stunnel_config_apply = <<<EOD
if (file_exists('/usr/local/pkg/stunnel.inc')) {
	require_once('/usr/local/pkg/stunnel.inc');
	stunnel_save();
}
EOD;

	$synctimeout = intval($synctimeout);
	$rpc_client = new pfsense_xmlrpc_client();
	$rpc_client->setConnectionData($sync_to_ip, $port, $username, $password,
	    $protocol);
	$resp = $rpc_client->xmlrpc_method('merge_installedpackages_section',
	    $xml, $synctimeout);
	$resp = $rpc_client->xmlrpc_exec_php($remote_stunnel_config_apply,
	    $synctimeout);
}

function stunnel_plugin_certificates($pluginparams) {
	global $config;
	$result = array();
	init_config_arr(array('installedpackages', 'stunnel', 'config'));
	$stunnelconfig = $config['installedpackages']['stunnel']['config'];

	if (($pluginparams['type'] == 'certificates') && ($pluginparams['event'] == 'used_certificates') &&
	    is_array($stunnelconfig)) {	
		$result['pkgname'] = "STunnel";
		$result['certificatelist'] = array();
		// return a array of used certificates.
		foreach ($stunnelconfig as $stunnel) {
			if ($stunnel['certificate'] != 'default') {
				$item = array();
				$cert = $stunnel['certificate'];
				$item['usedby'] = $stunnel['description'];
				$result['certificatelist'][$cert][] = $item;
			}
		}
	}
	return $result;
}



?>
