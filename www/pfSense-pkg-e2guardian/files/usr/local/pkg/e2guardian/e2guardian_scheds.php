#!/usr/local/bin/php
<?php
/*
 * e2guardian_scheds.php
 *
 * part of Unofficial packages for pfSense(R) softwate
 * Copyright (c) 2017 Marcello Coutinho
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
if ($pfs_version == "2.3" ) {
        require_once("xmlrpc.inc");
}
require_once("xmlrpc_client.inc");
require_once("/usr/local/pkg/e2guardian/e2guardian.inc");
require_once("service-utils.inc");

$file = "/tmp/e2g_scheds.txt";
$e2g_sched_in_use = array();
if (file_exists($file)) {
	$last_scheds = unserialize(file_get_contents($file));
	foreach ( $last_scheds as $sched => $last_status) {
		$current_status = e2g_check_sched($sched);
	}
}


if ($last_scheds !== $e2g_sched_in_use) {
	log_error("e2guardian - change on schedules, reapplying config.");
	print "changes on schedule\n";
	//update acl files
	sync_package_e2guardian("yes");
	clear_subsystem_dirty('e2guardian');
	$max_threads = "sysctl kern.threads.max_threads_per_proc=20480";		
	//reload e2guardian
	system("$max_threads;/usr/local/sbin/e2guardian -g");
        //service_control_restart('e2guardian');
} else {
	print "No changes on schedule\n";
}

//save new schedules states
file_put_contents($file, serialize($e2g_sched_in_use), LOCK_EX);

?>
