#!/usr/local/bin/php
<?php
/*
 * e2guardian_scheds.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017 Marcello Coutinho
 * Copyright (c) 2020 Rubicon Communications, LLC (Netgate)
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
require_once("xmlrpc_client.inc");
require_once("/usr/local/pkg/e2guardian/e2guardian.inc");
require_once("service-utils.inc");

log_error("e2guardian - rotating logs.");

// TODO: Make all of this less hardcoded and hacky
service_control_stop("e2guardian", array());

log_error("e2guardian - stoping");

init_config_arr(array('installedpackages', 'e2guardianlog', 'config'));
$e2guardian_log = $config['installedpackages']['e2guardianlog']['config'];

$logfilecount = ($e2guardian_log['logcount']
    ? $e2guardian_log['logcount'] : "30");
$log="/var/log/e2guardian/access.log";

// logrotate script file distrubuted with e2guardian translated to php
unlink_if_exists("{$log}.{$logfilecount}");

$n = $logfilecount - 1;
while ($n > 0){
	$m = $n + 1;
	if (file_exists("{$log}.{$n}")) {
		rename("{$log}.{$n}", "{$log}.{$m}");
	}
	$n--;
}
if (file_exists($log)){
	rename($log, "{$log}.1");
}

log_error("e2guardian - starting");
service_control_start("e2guardian", array());

log_error("e2guardian - log rotation complete.");
?>
