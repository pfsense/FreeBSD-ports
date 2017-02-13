<?php
/*
	patches.inc
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2012 Jim Pingle
	Copyright (C) 2015 ESF, LLC
	All rights reserved.

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
require_once("globals.inc");
require_once("util.inc");
require_once("pfsense-utils.inc");

global $git_root_url, $patch_suffix, $patch_dir, $patch_cmd;
$git_root_url = "https://github.com/pfsense/pfsense/commit/";
$patch_suffix = ".patch";
$patch_dir = "/var/patches";
$patch_cmd = "/usr/bin/patch";

function patch_package_install() {
	patch_add_shellcmd();
}

function patch_package_deinstall() {
	patch_remove_shellcmd();
}

function patch_commit($patch, $action, $test=false, $fulldetail=false) {
	global $patch_dir, $patch_cmd, $patch_suffix;
	$directory = empty($patch['basedir']) ? "/" : $patch['basedir'];
	$filename = '-i ' . $patch_dir . '/' . $patch['uniqid'] . $patch_suffix;
	$check = ($test) ? "--check" : "";
	$force = ($action == "revert") ? "-f" : "-t";
	$direction = ($action == "revert") ? "--reverse" : "--forward";
	$whitespace = isset($patch['ignorewhitespace']) ? "--ignore-whitespace" : "";
	$pathstrip = '-p' . $patch['pathstrip'];
	$full_patch_command = "{$patch_cmd} --directory={$directory} {$force} {$pathstrip} {$filename} {$check} {$direction} {$whitespace}";
	conf_mount_rw();
	patch_write($patch);
	if (!$fulldetail) {
		$output = (mwexec($full_patch_command, true) == 0);
	} else {
		$output = $full_patch_command . "\n\n" . shell_exec($full_patch_command . ' 2>&1');
	}
	patch_erase($patch);
	conf_mount_ro();
	return $output;
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
function patch_fetch(& $patch) {
	global $g;
	$url = patch_fixup_url($patch['location']);
	$temp_filename = tempnam("{$g['tmp_path']}/", "system_patches");
	/*
	 * Backwards compatibility with older 2.1.x pfSense versions
	 * that did not contain download_file() function in pfsense-utils.inc
	 */
	if (!function_exists("download_file")) {
		download_file_with_progress_bar($url, $temp_filename);
	} else {
		download_file($url, $temp_filename);
	}
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
		write_config("Fetched patch {$patch['descr']}");
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
		$filename = $patch_dir . '/' . $patch['uniqid'] . $patch_suffix;
		return (file_put_contents($filename, $text) > 0);
	}
}

function patch_erase($patch) {
	global $patch_dir, $patch_suffix;
	if (!file_exists($patch_dir)) {
		return true;
	}
	$filename = $patch_dir . '/' . $patch['uniqid'] . $patch_suffix;
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

function bootup_apply_patches() {
	global $config;

	if (!isset($config['installedpackages']['patches']['item']) ||
	    !is_array($config['installedpackages']['patches']['item'])) {
		$config['installedpackages']['patches']['item'] = array();
	}

	$a_patches = &$config['installedpackages']['patches']['item'];

	foreach ($a_patches as $patch) {
		/* Skip if it should not be automatically applied;
		   only attempt to apply if it can be applied;
		   and	if it can be reverted it is presumably already applied, so skip it. */
		if (isset($patch['autoapply']) && patch_test_apply($patch) && !patch_test_revert($patch)) {  
			patch_apply($patch);
		}
	}
}

function patch_add_shellcmd() {
	global $config;

	if (!isset($config['system']['earlyshellcmd']) || !is_array($config['system']['earlyshellcmd'])) {
	    $config['system']['earlyshellcmd'] = array();
	}
	$a_earlyshellcmd = &$config['system']['earlyshellcmd'];
	$found = false;
	foreach ($a_earlyshellcmd as $idx => $cmd) {
		if (stristr($cmd, "apply_patches.php")) {
			$found = true;
		}
	}
	if (!$found) {
		// Implicitly creates array if needed
		$a_earlyshellcmd[] = "/usr/local/bin/php-cgi -f /usr/local/bin/apply_patches.php";
		write_config("System Patches package added an early shellcmd: apply patches");
	}
}

function patch_remove_shellcmd() {
	global $config;
	if (!isset($config['system']['earlyshellcmd']) || !is_array($config['system']['earlyshellcmd'])) {
	    $config['system']['earlyshellcmd'] = array();
	}
	$a_earlyshellcmd = &$config['system']['earlyshellcmd'];
	$removed = 0;
	foreach ($a_earlyshellcmd as $idx => $cmd) {
		if (stristr($cmd, "apply_patches.php")) {
			unset($a_earlyshellcmd[$idx]);
			$removed++;
		}
	}
	if ($removed > 0) {
		write_config("System Patches package removed {$removed} existing early shellcmd(s): apply patches");
	}
}

?>
