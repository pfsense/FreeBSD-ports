<?php
/*
 * backup.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2008 Mark J Crane
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

function backup_sync_package() {
	global $config;
	if ($config['installedpackages']['backup']['config'] != "") {
		foreach ($config['installedpackages']['backup']['config'] as $rowhelper) {
			if ($rowhelper['enabled'] != "false") {
				//$tmp_php = base64_decode($rowhelper['php']);
				if (strlen($tmp_php) > 0) {
					$tmp .= "// name: " . $rowhelper['name'] . " \n";
					$tmp .= "// description: " . $rowhelper['description'] . " \n\n";
					$tmp .= base64_decode($rowhelper['php']);
					$tmp .= "\n";
				}
			}
		}
	}
}

function backup_install_command() {
	// Create the backup directory
	safe_mkdir("/root/backup/");
	backup_sync_package();
}

?>
