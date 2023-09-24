<?php
/*
 * ipfs.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2009-2023 Rubicon Communications, LLC (Netgate)
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
require_once("util.inc");
require_once("service-utils.inc");

const IPFS_RCFILE = "/usr/local/etc/rc.d/ipfs-go.sh";
const IPFS_UPSTREAM_RCFILE = "/usr/local/etc/rc.d/ipfs-go";

function ipfs_write_config($ipfs_config) {
	$listen_addr = get_interface_ip($ipfs_config["listen_iface"]);
	$listen_port = $ipfs_config["listen_port"];

	$gateway4 = "/ip4/$listen_addr/tcp/$listen_port";

	// other config options will need escapeshellarg() to be safe - the gateway is constructed
	// from already-validated IP/port values.
	exec("su - ipfs-go -c '/usr/local/bin/ipfs-go config Addresses.Gateway $gateway4'");
}

function ipfs_write_rcfile() {

	$rc = array();
	$rc['file']  = 'ipfs-go.sh';
	$rc['stop']  = sprintf("%s onestop", IPFS_UPSTREAM_RCFILE);
	$rc['start'] = sprintf("%s onestart", IPFS_UPSTREAM_RCFILE);
	write_rcfile($rc);
}

function ipfs_sync_config() {
	global $config;
	if (is_service_running('ipfs')) {
		log_error("Stopping service ipfs");
		stop_service('ipfs');
	}

	$ipfs_config = &$config['installedpackages']['ipfs']['config'][0];
	if ($ipfs_config['enable'] !== 'on') {
		unlink_if_exists(IPFS_RCFILE);
		return;
	}

	/* Write the config and rc files */
	ipfs_write_rcfile();
	ipfs_write_config($ipfs_config);

	if (platform_booting()) {
		return;
	}
	log_error("Starting service ipfs");
	start_service('ipfs');
}

function ipfs_install_command() {
	global $config;

	// If instaing for the first time, set up the default config
	$ipfs_config = &$config['installedpackages']['ipfs']['config'][0];
	if (!is_array($ipfs_config)) {
		$ipfs_config = [
			"listen_port" => "8080",
			"listen_iface" => "lan"
		];

		exec('su - ipfs-go -c "/usr/local/bin/ipfs-go init --profile=server"');
	}

	ipfs_sync_config();
}

function ipfs_deinstall_command() {
	if (is_service_running('ipfs')) {
		log_error("Stopping service ipfs");
		stop_service('ipfs');
	}
}

function ipfs_validate_form($post, &$input_errors) {
	if (empty($post['listen_iface'])) {
		$input_errors[] = gettext('You must select an interface to listen to');
	}

	if (!is_port($post['listen_port'])) {
		$input_errors[] = gettext('You must enter a valid port number in the \'Listen Port\' field');
	}
}

?>
