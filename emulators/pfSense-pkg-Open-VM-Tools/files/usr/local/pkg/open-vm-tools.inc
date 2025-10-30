<?php
/*
 * open-vm-tools.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008-2025 Rubicon Communications, LLC (Netgate)
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
require_once("service-utils.inc");
require_once("util.inc");

define('LOG_PREFIX_PKG_OPENVMTOOLS', 'open-vm-tools');

function open_vm_tools_deinstall() {
	$tgt = "/usr/local/etc/rc.d";

	foreach (array("guestd", "kmod") as $service) {
		@unlink("{$tgt}/vmware-{$service}.sh");
	}
}

function open_vm_tools_install() {
	global $g;

	$openvmtools_path = "/usr/local";
	$vmware_tools_conf = <<<EOF
[powerops]
poweron-script=
resume-script=
suspend-script=
poweroff-script=

EOF;

	// Write out conf files.
	$fd = fopen("$openvmtools_path/share/vmware-tools/tools.conf", "w");
	if (!$fd) {
		logger(LOG_ERR, localize_text("Could not open %s for writing", "{$openvmtools_path}/share/vmware-tools/tools.conf"), LOG_PREFIX_PKG_OPENVMTOOLS);
		die("Could not open $openvmtools_path/share/vmware-tools/tools.conf for writing");
	}
	fwrite($fd, $vmware_tools_conf);
	fclose($fd);

	/*
	 * Workaround for systems upgraded when install/uninstall process used
	 * to remove open-vm-tools-nox11 startup scripts. Carry a copy of them
	 * and re-install to avoid system to miss them
	 */
	$src = "/usr/local/share/{$g['product_name']}-pkg-Open-VM-Tools";
	$tgt = "/usr/local/etc/rc.d";
	foreach (array("guestd", "kmod") as $service) {
		if (!file_exists("{$tgt}/vmware-{$service}")) {
			@copy("{$src}/vmware-{$service}", "{$tgt}/vmware-{$service}");
			@chmod("{$tgt}/vmware-{$service}", 0755);
		}

		@unlink("{$tgt}/vmware-{$service}.sh");
		@symlink("{$tgt}/vmware-{$service}", "{$tgt}/vmware-{$service}.sh");
		start_service("vmware-{$service}");
	}
}

?>
