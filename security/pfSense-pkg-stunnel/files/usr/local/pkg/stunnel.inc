<?php
/*
 * stunnel.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2016 Rubicon Communications, LLC (Netgate)
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

define('STUNNEL_LOCALBASE', '/usr/local');
define('STUNNEL_ETCDIR', STUNNEL_LOCALBASE . "/etc/stunnel");
define('STUNNEL_CHROOTDIR', '/var/tmp/stunnel');

function stunnel_save() {
	global $config;
	stunnel_make_directories();
	$fout = fopen(STUNNEL_ETCDIR . "/stunnel.conf", "w");
	fwrite($fout, "cert = " . STUNNEL_ETCDIR . "/stunnel.pem \n");
	fwrite($fout, "chroot = " . STUNNEL_CHROOTDIR . " \n");
	fwrite($fout, "setuid = stunnel \n");
	fwrite($fout, "setgid = stunnel \n");
	init_config_arr(array('installedpackages', 'stunnel', 'config'));

	// Keep array of in-use certificates so we can clean up after ourselves.
	$in_use_certs = array();
	$in_use_certs[] = 'stunnel.pem';

	foreach ($config['installedpackages']['stunnel']['config'] as $pkgconfig) {
		fwrite($fout, "\n[" . $pkgconfig['description'] . "]\n");
		if ($pkgconfig['client']) {
			fwrite($fout, "client = yes" . "\n");
		}
		if ($pkgconfig['certificate'] && $pkgconfig['certificate'] != 'default') {
			$cert = lookup_cert($pkgconfig['certificate']);
			if ($cert != false) {
				file_put_contents(STUNNEL_ETCDIR . "/{$pkgconfig['certificate']}.pem", 
					trim(base64_decode($cert['prv'])) . "\n" .
					trim(base64_decode($cert['crt'])) . "\n" .
					ca_chain($cert));
				fwrite($fout, "cert = " . STUNNEL_ETCDIR . "/{$pkgconfig['certificate']}.pem\n");
				$in_use_certs[] = "{$pkgconfig['certificate']}.pem";
			}
		}
		if ($pkgconfig['sourceip']) {
			fwrite($fout, "local = " . $pkgconfig['sourceip'] . "\n");
		}
		fwrite($fout, "accept = " . ($pkgconfig['localip'] ? $pkgconfig['localip'] . ":" : "")  . $pkgconfig['localport'] . "\n");
		fwrite($fout, "connect = " . $pkgconfig['redirectip'] . ":" . $pkgconfig['redirectport'] . "\n");
		fwrite($fout, "debug = " . $pkgconfig['loglevel'] . "\n");
		if ($pkgconfig['timeoutclose'] != "") {
			fwrite($fout, "TIMEOUTclose = " . $pkgconfig['timeoutclose'] . "\n");
		}
	}
	fclose($fout);

	// Clean up certs that are no longer in use.
	foreach (glob(STUNNEL_ETCDIR . "/*.pem") as $file) {
		if (!in_array(basename($file), $in_use_certs)) {
			unlink($file);
		}
	}

	restart_service("stunnel");
}

function stunnel_make_directories() {
	safe_mkdir(STUNNEL_ETCDIR);
	safe_mkdir(STUNNEL_CHROOTDIR . "/var/tmp/run/stunnel");
	system("/usr/sbin/chown -R stunnel:stunnel " . STUNNEL_CHROOTDIR);
}

function stunnel_install() {
	global $config;
	stunnel_make_directories();

	// Generate a self-signed default certificate if one does not already exist.
	$stunnel_pem_filename = STUNNEL_ETCDIR . "/stunnel.pem";
	if (!file_exists($stunnel_pem_filename)) {
		$cert = array();
		$cert['refid'] = uniqid();
		$cert['descr'] = sprintf(gettext("stunnel default (%s)"), $cert['refid']);

		$dn = array(
			'organizationName' => "{$g['product_name']} stunnel Self-Signed Certificate",
			'commonName' => "{$config['system']['hostname']}-{$cert['refid']}");
		$old_err_level = error_reporting(0); /* otherwise openssl_ functions throw warnings directly to a page screwing menu tab */
		if (!cert_create($cert, null, 2048, 365, $dn, "self-signed", "sha256")) {
			while ($ssl_err = openssl_error_string()) {
				log_error(sprintf(gettext("Error creating stunnel certificate: openssl library returns: %s"), $ssl_err));
			}
			error_reporting($old_err_level);
			return null;
		}
		error_reporting($old_err_level);

		// Write the .pem file to the expected default location for stunnel and set up required permissions.
		file_put_contents(STUNNEL_ETCDIR . "/stunnel.pem",
			trim(base64_decode($cert['prv'])) . "\n" .
			trim(base64_decode($cert['crt'])) . "\n");
		chmod(STUNNEL_ETCDIR . "/stunnel.pem", 0600);
	}

	$_rcfile['file'] = 'stunnel.sh';
	$_rcfile['start'] = STUNNEL_LOCALBASE . "/bin/stunnel " . STUNNEL_ETCDIR . "/stunnel.conf \n\t";
	$_rcfile['stop'] = "/usr/bin/killall stunnel \n\t";
	write_rcfile($_rcfile);
	unlink_if_exists("/usr/local/etc/rc.d/stunnel");
}

function stunnel_deinstall() {
	rmdir_recursive("/var/tmp/stunnel");
	rmdir_recursive(STUNNEL_ETCDIR);
}
?>
