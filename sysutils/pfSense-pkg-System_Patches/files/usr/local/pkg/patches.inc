<?php
/*
 * patches.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2012-2026 Rubicon Communications, LLC (Netgate)
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
require_once("globals.inc");
require_once("util.inc");
require_once("pfsense-utils.inc");
require_once("patches/manifest.inc");

define('LOG_PREFIX_PKG_SYSTEMPATCHES', 'System Patches');

global $git_root_url, $patch_suffix, $patch_dir, $patch_cmd, $recommended_patches;
$git_root_url = 'https://github.com/pfsense/pfsense/commit/';
$patch_suffix = '.patch';
$patch_dir = '/var/patches/';
$rec_patch_dir = '/usr/local/pkg/patches/';
$patch_cmd = '/usr/bin/patch';

function patch_commit($patch, $action, $test=false, $fulldetail=false) {
	global $patch_dir, $rec_patch_dir, $patch_cmd, $patch_suffix;
	/* Check if this is a recommended patch so the test does not need to be repeated each time */
	$is_recommended = array_key_exists('versions', $patch);

	$directory = escapeshellarg(empty($patch['basedir']) ? "/" : $patch['basedir']);

	/* If this is a recommended patch, pull it from the default location */
	$pdir = $is_recommended ? $rec_patch_dir : $patch_dir;
	$filename = '-i ' . escapeshellarg($pdir . basename($patch['uniqid']) . $patch_suffix);

	$check = ($test) ? "--check" : "";
	$force = ($action == "revert") ? "-f" : "-t";
	$direction = ($action == "revert") ? "--reverse" : "--forward";
	$whitespace = isset($patch['ignorewhitespace']) ? "--ignore-whitespace" : "";
	$pathstrip = ' --strip ' . escapeshellarg($patch['pathstrip']);
	$full_patch_command = "{$patch_cmd} --directory={$directory} {$force} {$pathstrip} {$filename} {$check} {$direction} {$whitespace}";

	/* Recommended patches do not need rewritten, they are always present on the filesystem. */
	if (!$is_recommended) {
		patch_write($patch);
	}

	if (!$fulldetail) {
		$output = (mwexec($full_patch_command, true) == 0);
	} else {
		$output = $full_patch_command . "\n\n" . shell_exec($full_patch_command . ' 2>&1');
	}

	if (!$is_recommended) {
		patch_erase($patch);
	}
	return $output;
}

/* Locate and return a recommended patch by ID */
function get_recommended_patch($uniqid) {
	global $recommended_patches;
	foreach ($recommended_patches as $rp) {
		if ($rp['uniqid'] == $uniqid) {
			return $rp;
		}
	}
	return array();
}

/* Attempt to apply a patch */
function patch_apply($patch) {
	return patch_commit($patch, "apply", false);
}

/* Attempt to revert a patch */
function patch_revert($patch) {
	return patch_commit($patch, "revert", false);
}

/* Test if a patch would apply cleanly */
function patch_test_apply($patch, $fulldetail=false) {
	return patch_commit($patch, "apply", true, $fulldetail);
}

/* Test if a patch would revert cleanly */
function patch_test_revert($patch, $fulldetail=false) {
	return patch_commit($patch, "revert", true, $fulldetail);
}

/* Fetch a patch from a URL or github */
function patch_fetch(& $patch, $id = null) {
	global $g;
	$url = patch_fixup_url($patch['location']);
	$temp_filename = tempnam("{$g['tmp_path']}/", "system_patches");
	download_file($url, $temp_filename);
	$text = @file_get_contents($temp_filename);
	@unlink($temp_filename);
	if (empty($text)) {
		return false;
	} else {
		$patch['patch'] = base64_encode($text);
		// Detect pfSense move from / to /src and fix pathstrip
		if (is_github_url($patch['location']) &&
		    $patch['pathstrip'] == 1 &&
		    preg_match('/\n--- a\/src\//', $text)) {
			$patch['pathstrip'] = 2;
		}
		if (isset($id)) {
			config_set_path("installedpackages/patches/item/{$id}", $patch);
		} else {
			config_set_path("installedpackages/patches/item/", $patch);
		}
		write_config(LOG_PREFIX_PKG_SYSTEMPATCHES . ": fetched patch {$patch['descr']}");
		return true;
	}
}

/* Write a patch file out to $patch_dir */
function patch_write($patch) {
	global $patch_dir, $patch_suffix;
	if (!file_exists($patch_dir)) {
		safe_mkdir($patch_dir);
	}
	if (empty($patch['patch'])) {
		return false;
	} else {
		$text = base64_decode($patch['patch']);
		$filename = $patch_dir . $patch['uniqid'] . $patch_suffix;
		return (file_put_contents($filename, $text) > 0);
	}
}

function patch_erase($patch) {
	global $patch_dir, $patch_suffix;
	if (!file_exists($patch_dir)) {
		return true;
	}
	$filename = $patch_dir . $patch['uniqid'] . $patch_suffix;
	return @unlink($filename);
}

/* Detect a github URL or commit ID and fix it up */
function patch_fixup_url($url) {
	global $git_root_url, $patch_suffix;
	// If it's a commit id then prepend git url, and add .patch
	if (is_commit_id($url)) {
		$url = $git_root_url . $url . $patch_suffix;
	} elseif (is_URL($url)) {
		$urlbits = explode("/", $url);
		if (substr($urlbits[2], -10) == "github.com") {
			// If it's a github url and does not already end in .patch, add it
			if (substr($url, -strlen($patch_suffix)) != $patch_suffix) {
				// Make sure it's really a URL to a commit id before adding .patch
				if (is_commit_id(array_pop($urlbits))) {
					$url .= $patch_suffix;
				}
			}
		}
	}
	return $url;
}

function is_commit_id($str) {
	return preg_match("/^[0-9a-f]{5,40}$/", $str);
}

function is_github_url($url) {
	$urlbits = explode("/", $url);
	return (substr($urlbits[2], -10) == "github.com");
}

function is_patch_for_this_system(array $rpatch) {
	static $platform = system_identify_specific_platform();
	if (!isset($thisversion)) {
		$thisversion_temp = explode('-', g_get('product_version'), 2);
		if (!count($thisversion_temp)) {
			return false;
		}
		static $thisversion = $thisversion_temp[0];
	}

	// Check for applicable version and model.
	if ((!in_array($thisversion, $rpatch['versions'])) ||
	    (array_key_exists('models', $rpatch) &&
	     !in_array($platform['name'], $rpatch['models']))) {
		return false;
	}

	return true;
}

/**
 * Filter a list of recommended patch IDs for entries which apply to this
 * system. Patch IDs are also run through htmlspecialchars_decode().
 * @param mixed $unique_ids A list of patch ID strings.
 * @return string[] Validated list.
 */
function validate_recommended_patches(mixed $unique_ids): array {
	$valid_unique_ids = [];
	if (empty($unique_ids) || !is_array($unique_ids) || !array_is_list($unique_ids)) {
		return [];
	}

	$unique_ids = array_unique($unique_ids);
	foreach ($unique_ids as &$id) {
		if (!is_string($id)) {
			continue;
		}
		$id = htmlspecialchars_decode($id);
		$rpatch = get_recommended_patch($id);
		if (empty($rpatch)) {
			// Skip recommended patches that don't exist.
			continue;
		}
		if (!is_patch_for_this_system($rpatch)) {
			// Skip patches that aren't relevant to this system.
			continue;
		}
		$valid_unique_ids[] = &$id;
	}
	return $valid_unique_ids;
}

/**
 * Updates the list of excluded recommended patches and removes any that
 * no longer apply to this system.
 * @param array|null $unique_ids (Optional) A list of patch ID strings.
 * @return bool True if a configuration change was made. False otherwise.
 */
function refresh_excluded_recommended_patches(array|null $unique_ids = null): bool {
	if (is_null($unique_ids)) {
		$unique_ids = array_column(config_get_path('installedpackages/patches/excluded/item', []), 'text');
	}
	$excluded_patches = validate_recommended_patches($unique_ids);

	$need_save = false;
	if (empty($unique_ids)) {
		// Nothing to exclude.
		$need_save = !is_null(config_del_path('installedpackages/patches/excluded'));
	} else {
		// Create the items array for the config.
		$excluded_patches_items = [];
		foreach ($excluded_patches as $unique_id) {
			$excluded_patches_items[] = [
				'text' => $unique_id
			];
		}
		$need_save = !is_null(config_set_path('installedpackages/patches/excluded/item', $excluded_patches_items));
	}
	if ($need_save) {
		write_config(LOG_PREFIX_PKG_SYSTEMPATCHES . ": " . gettext("refreshed excluded patches."));
	}

	return $need_save;
}

function autoapply_recommended_patches() {
	global $recommended_patches;

	if (!config_path_enabled('installedpackages/patches', 'autoapply_recommended')) {
		return;
	}

	$excluded_recommended_patches = validate_recommended_patches(array_column(config_get_path('installedpackages/patches/excluded/item', []), 'text'));
	foreach ($recommended_patches as $rpatch) {
		if (in_array($rpatch['uniqid'], $excluded_recommended_patches)) {
			// Skip excluded patches.
			continue;
		}
		if (!is_patch_for_this_system($rpatch)) {
			// Skip patches that aren't relevant to this system.
			continue;
		}

		// Apply when possible and not already applied.
		if (patch_test_apply($rpatch) && !patch_test_revert($rpatch)) {
			$descr = patch_descr($rpatch);
			$savemsg = patch_apply($rpatch) ? gettext("Recommended patch autoapplied successfully") : gettext("Recommended patch could NOT be autoapplied");
			patchlog($savemsg . $descr);
		}
	}
}

function bootup_apply_patches() {
	autoapply_recommended_patches();

	foreach (config_get_path('installedpackages/patches/item', []) as $id => $patch) {
		/* Skip if it should not be automatically applied;
		   only attempt to apply if it can be applied;
		   and	if it can be reverted it is presumably already applied, so skip it. */
		if (isset($patch['autoapply']) && patch_test_apply($patch) && !patch_test_revert($patch)) {
			$descr = patch_descr($patch);
			$savemsg = patch_apply($patch) ? gettext("Patch autoapplied successfully") : gettext("Patch could NOT be autoapplied");
			patchlog($savemsg . $descr);
		}
	}
}

function patch_add_shellcmd() {
	$found = false;
	foreach (config_get_path('system/earlyshellcmd', []) as $idx => $cmd) {
		if (stristr($cmd, "apply_patches.php")) {
			$found = true;
		}
	}
	if (!$found) {
		// Implicitly creates array if needed
		config_set_path('system/earlyshellcmd/', "/usr/local/bin/php-cgi -f /usr/local/bin/apply_patches.php");
		write_config(LOG_PREFIX_PKG_SYSTEMPATCHES . ": added an early shellcmd: apply patches");
	}
}

function patch_remove_shellcmd() {
	$removed = 0;
	foreach (config_get_path('system/earlyshellcmd', []) as $idx => $cmd) {
		if (stristr($cmd, "apply_patches.php")) {
			config_del_path("system/earlyshellcmd/{$idx}");
			$removed++;
		}
	}
	if ($removed > 0) {
		write_config(LOG_PREFIX_PKG_SYSTEMPATCHES . ": removed {$removed} existing early shellcmd(s): apply patches");
	}
}

function patchlog($msg) {
	logger(LOG_WARNING, localize_text($msg), LOG_PREFIX_PKG_SYSTEMPATCHES);
}

function patch_descr($patch) {
	if (!isset($patch) || empty($patch)) {
		return "";
	}
	$descr = " (" . gettext("ID") . ": {$patch['uniqid']}";
	if (isset($patch['descr'])) {
		$descr .= ", " . gettext("DESCR") . ": {$patch['descr']}";
	}
	$descr .= ")";
	return $descr;
}

function patch_package_install() {
	patch_add_shellcmd();
	autoapply_recommended_patches();
}

function patch_package_deinstall() {
	patch_remove_shellcmd();
}

?>
