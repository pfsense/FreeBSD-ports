<?php
/*
 * shellcmd.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2008 Mark J Crane
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

function shellcmd_install_command() {
	global $config;

	shellcmd_import_config();
	shellcmd_sync_package();

}


function shellcmd_delete_php_command() {
	global $config;

	/* When 'Delete item' is clicked in Shellcmd Settings */
	if ($_GET['act'] == "del") {

		/* System earlyshellcmd commands */
		$a_earlyshellcmd = &$config['system']['earlyshellcmd'];
		if (!is_array($a_earlyshellcmd)) {
			$a_earlyshellcmd = array();
		}
		/* Shellcmd package commands */
		$a_shellcmd_config = &$config['installedpackages']['shellcmdsettings']['config'];
		if (!is_array($a_shellcmd_config)) {
			$a_shellcmd_config = array();
		}

		/* First check for a couple of special cases that we do NOT want deleted */
		$pkg = '';
		/* pfBlockerNG - function to restore archived aliastables on nanobsd (see pfblockerng.inc) */
		$pfbcmd = "/usr/local/pkg/pfblockerng/pfblockerng.sh";
		/* If the entry exists in system config ... */
		if (in_array($pfbcmd, $a_earlyshellcmd)) {
			$cnta = 0;
			/* ... but does not exist in package config ... */
			foreach ($a_shellcmd_config as $item => $value) {
				if (in_array($pfbcmd, $value)) {
					$cnta++;
				}
			}
			/* ... the user has deleted this protected entry. */
			if ($cnta === 0) {
				$pkg .= "[pfBlockerNG]";
				/* Force reimport. */
				shellcmd_forced_restore($pkg);
			}
		}
		/* System Patches auto-apply patch feature (see patches.inc) */
		$spcmd = "/usr/local/bin/php -f /usr/local/bin/apply_patches.php";
		if (in_array($spcmd, $a_earlyshellcmd)) {
			$cntb = 0;
			foreach ($a_shellcmd_config as $item => $value) {
				if (in_array($spcmd, $value)) {
					$cntb++;
				}
			}
			if ($cntb === 0) {
				$pkg .= "[System Patches]";
				shellcmd_forced_restore($pkg);
			}
		}

		/* Otherwise, sync package and system configuration normally */
		shellcmd_sync_package();
	}
}


/* Force restore of protected (early)shellcmds from system config */
function shellcmd_forced_restore($pkg) {
	log_error("[shellcmd] Refused to delete {$pkg} earlyshellcmd. Use {$pkg} to configure this entry.");
	shellcmd_import_config();
	write_config("[shellcmd] Restore of {$pkg} earlyshellcmd forced.");
	/* Send the user back to settings */
	header("Location: pkg.php?xml=shellcmd.xml");
	exit;

}


function shellcmd_sync_package() {
	global $config;
	conf_mount_rw();

	$cmd = '';
	$cmdtype = '';
	$a_shellcmd = array();
	$a_earlyshellcmd = array();
	/*
	 * afterfilterchangeshellcmd is NOT treated as an array, it's a string!
	 * See /etc/inc/xmlparse.inc and /etc/inc/xmlreader.inc
	 */
	$afterfilterchangeshellcmd = '';
	$a_shellcmd_config = &$config['installedpackages']['shellcmdsettings']['config'];
	if (!is_array($a_shellcmd_config)) {
		$a_shellcmd_config = array();
	}
	$i = 0;
	/*
	 * When an item is added to shellcmd package configuration, make sure
	 * we add corresponding entry to $config['system'] as well
	 */
	foreach ($a_shellcmd_config as $item) {
		/* Get the command from package configuration here */
		$cmd = $a_shellcmd_config[$i]['cmd'];
		/* Lets see what type of command we are adding first... */
		$cmdtype = $a_shellcmd_config[$i]['cmdtype'];
		/* shellcmd */
		if ($cmdtype == "shellcmd") {
			$a_shellcmd[] = $cmd;
			$i++;
		/* earlyshellcmd */
		} elseif ($cmdtype == "earlyshellcmd") {
			$a_earlyshellcmd[] = $cmd;
			$i++;
		/* afterfilterchangeshellcmd */
		} elseif ($cmdtype == "afterfilterchangeshellcmd") {
			$afterfilterchangeshellcmd = $cmd;
			$i++;
		/* Either disabled, or possibly someone messing with config.xml manually?! */
		} else {
			$i++;
		}
	}

	/* Write the new system configuration to config.xml from scratch when done */
	unset($config['system']['shellcmd']);
	$config['system']['shellcmd'] = $a_shellcmd;
	unset($config['system']['earlyshellcmd']);
	$config['system']['earlyshellcmd'] = $a_earlyshellcmd;
	unset($config['system']['afterfilterchangeshellcmd']);
	$config['system']['afterfilterchangeshellcmd'] = $afterfilterchangeshellcmd;
	write_config("[shellcmd] Successfully (re)synced shellcmd configuration.");

}


function shellcmd_import_config() {
	global $config;

	$shellcmd_config = &$config['installedpackages']['shellcmdsettings']['config'];
	if (!is_array($shellcmd_config)) {
		$shellcmd_config = array();
	}

	$i = 0;

	/* First, preserve any disabled items */
	$a_shellcmd_config = &$shellcmd_config;
	foreach ($a_shellcmd_config as $item => $value) {
		$cmd = $value['cmd'];
		$cmdtype = $value['cmdtype'];
		$description = $value['description'];
		if ($cmdtype == "disabled") {
			$shellcmd_config[$i]['cmd'] = $cmd;
			$shellcmd_config[$i]['cmdtype'] = "disabled";
			$shellcmd_config[$i]['description'] = $description ?: "Imported disabled item ({$i})";
			$i++;
		}
	}

	/*
	 * Import earlyshellcmd entries which were either created by previous package versions,
	 * or manually, or added by some other package(s) (if there are any in config.xml)
	 * Two currently known special cases are handled here - System Patches and pfBlockerNG
	 */
	if (is_array($config['system']['earlyshellcmd'])) {
		$earlyshellcmds = &$config['system']['earlyshellcmd'];
		$pfbcmd = "/usr/local/pkg/pfblockerng/pfblockerng.sh";
		$spcmd = "/usr/local/bin/php -f /usr/local/bin/apply_patches.php";
		foreach ($earlyshellcmds as $earlyshellcmd) {
			/* pfBlockerNG - function to restore archived aliastables on nanobsd (see pfblockerng.inc) */
			if (stristr($earlyshellcmd, "{$pfbcmd}")) {
				$shellcmd_config[$i]['cmd'] = $earlyshellcmd;
				$shellcmd_config[$i]['cmdtype'] = "earlyshellcmd";
				$shellcmd_config[$i]['description'] = "pfBlockerNG default earlyshellcmd. DO NOT EDIT/DELETE!";
				$i++;
			/* System Patches auto-apply patch feature (see patches.inc) */
			} elseif (stristr($earlyshellcmd, "{$spcmd}")) {
				$shellcmd_config[$i]['cmd'] = $earlyshellcmd;
				$shellcmd_config[$i]['cmdtype'] = "earlyshellcmd";
				$shellcmd_config[$i]['description'] = "System Patches default earlyshellcmd. DO NOT EDIT/DELETE!";
				$i++;
			/* Other manually added earlyshellcmd entries */
			} else {
				$shellcmd_config[$i]['cmd'] = $earlyshellcmd;
				$shellcmd_config[$i]['cmdtype'] = "earlyshellcmd";
				$shellcmd_config[$i]['description'] = $shellcmd_config[$i]['description'] ?: "Imported earlyshellcmd ({$i})";
				$i++;
			}

		}
	}
	/* Import shellcmd entries which were created manually (if there are any in config.xml) */
	if (is_array($config['system']['shellcmd'])) {
		$shellcmds = &$config['system']['shellcmd'];
		foreach ($shellcmds as $shellcmd) {
			$shellcmd_config[$i]['cmd'] = $shellcmd;
			$shellcmd_config[$i]['cmdtype'] = "shellcmd";
			$shellcmd_config[$i]['description'] = $shellcmd_config[$i]['description'] ?: "Imported shellcmd ({$i})";
			$i++;
		}
	}

	/*
	 * Import afterfilterchangeshellcmd entry which was created manually (if there is any in config.xml)
	 * afterfilterchangeshellcmd is NOT treated as an array, it's a string!
	 * See /etc/inc/xmlparse.inc and /etc/inc/xmlreader.inc
	 */
	if ($config['system']['afterfilterchangeshellcmd'] != '') {
		$shellcmd_config[$i]['cmd'] = $config['system']['afterfilterchangeshellcmd'];
		$shellcmd_config[$i]['cmdtype'] = "afterfilterchangeshellcmd";
		$shellcmd_config[$i]['description'] = $shellcmd_config[$i]['description'] ?: "Imported afterfilterchangeshellcmd";
		$i++;
	}

	/* Write the new config.xml when import is finished */
	write_config("[shellcmd] Successfully imported package configuration from config.xml.");

}

function shellcmd_validate_input($post, &$input_errors) {
	global $config;
	$a_shellcmd = &$config['system']['shellcmd'];
	if (!is_array($a_shellcmd)) {
		$a_shellcmd = array();
	}
	$a_earlyshellcmd = &$config['system']['earlyshellcmd'];
	if (!is_array($a_earlyshellcmd)) {
		$a_earlyshellcmd = array();
	}
	$a_shellcmd_config = &$config['installedpackages']['shellcmdsettings']['config'];
	if (!is_array($a_shellcmd_config)) {
		$a_shellcmd_config = array();
	}
	/* afterfilterchangeshellcmd is NOT an array */
	$afterfilterchangeshellcmd = $config['system']['afterfilterchangeshellcmd'];

	/* Make sure we don't add the same command twice as it's just pointless */
	if (($post['cmd']) != '') {
		$id = $post['id'];
		if ($post['cmdtype'] == "shellcmd") {
			if (in_array($post['cmd'], $a_shellcmd)) {
				/* Allow changing description */
				if ((($post['cmd']) == $a_shellcmd_config[$id]['cmd']) && (($post['cmdtype']) == $a_shellcmd_config[$id]['cmdtype'])) {
					return;
				} else {
					$input_errors[] = "{$post['cmd']} already exists as shellcmd.";
				}
			}
		}
		if ($post['cmdtype'] == "earlyshellcmd") {
			if (in_array($post['cmd'], $a_earlyshellcmd)) {
				/* Allow changing description */
				if ((($post['cmd']) == $a_shellcmd_config[$id]['cmd']) && (($post['cmdtype']) == $a_shellcmd_config[$id]['cmdtype'])) {
					return;
				} else {
					$input_errors[] = "{$post['cmd']} already exists as earlyshellcmd.";
				}
			}
		}
		/* Only ONE item of this type may be configured */
		if ($post['cmdtype'] == "afterfilterchangeshellcmd") {
			// Not yet configured, OK
			if ($afterfilterchangeshellcmd == '') {
				return;
			// Allow changing description
			} elseif ((($post['cmd']) == $a_shellcmd_config[$id]['cmd']) && (($post['cmdtype']) == $a_shellcmd_config[$id]['cmdtype'])) {
				return;
			} else {
				$input_errors[] = "Only one afterfilterchangeshellcmd may be configured. Delete the existing entry and try again.";
			}
		}
	}

}

?>
