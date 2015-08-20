<?php
/*
	sudo.inc
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2013 Jim Pingle (jpingle@gmail.com)
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
require_once("config.inc");

global $pfs_version;
$pfs_version = substr(trim(file_get_contents("/etc/version")), 0, 3);
switch ($pfs_version) {
	case "2.1":
		/* Hackish way to detect if someone manually did pkg_add rather than use pbi. */
		if (is_dir('/usr/pbi/sudo-' . php_uname("m"))) {
			define('SUDO_BASE', '/usr/pbi/sudo-' . php_uname("m"));
			define('SUDO_LIBEXEC_DIR', '/usr/local/libexec/');
		} else {
			define('SUDO_BASE', '/usr/local');
			define('SUDO_LIBEXEC_DIR', '/usr/local/libexec/sudo');
		}
		break;
	case "2.2":
		define('SUDO_BASE','/usr/local');
		/* Hackish way to detect if someone manually did pkg_add rather than use pbi. */
		if (is_dir('/usr/pbi/sudo-' . php_uname("m"))) {
			define('SUDO_LIBEXEC_DIR', '/usr/pbi/sudo-' . php_uname("m") . '/local/libexec/sudo');
		} else {
			define('SUDO_LIBEXEC_DIR', '/usr/local/libexec/sudo');
		}
		break;
	default:
		define('SUDO_BASE','/usr/local');
		define('SUDO_LIBEXEC_DIR', '/usr/local/libexec/sudo');
}

define('SUDO_CONFIG_DIR', SUDO_BASE . '/etc');
define('SUDO_CONF', SUDO_CONFIG_DIR . '/sudo.conf');
define('SUDO_SUDOERS', SUDO_CONFIG_DIR . '/sudoers');

function sudo_install() {
	global $g, $config;
	/* If we don't have a config, pre-load some good default sudo entries. */
	if (!is_array($config['installedpackages']['sudo']['config'][0]['row'])) {
		$config['installedpackages']['sudo']['config'][0]['row'] = array(
			0 => array(
				"username" => "user:root",
				"runas" => "user:root",
				"cmdlist" => "ALL"
			),
			1 => array(
				"username" => "user:admin",
				"runas" => "user:root",
				"cmdlist" => "ALL"
			),
			2 => array(
				"username" => "group:admins",
				"runas" => "user:root",
				"cmdlist" => "ALL"
			)
		);
	}
}

function sudo_write_config() {
	global $config, $pfs_version;
	$sudoers = "";
	if ($pfs_version == "2.2") {
		mwexec_bg("/sbin/ldconfig -m /usr/pbi/sudo-" . php_uname("m") . "/local/lib/");
	}
	conf_mount_rw();

	$sudoconf = "Plugin sudoers_policy " . SUDO_LIBEXEC_DIR . "/sudoers.so\n";
	$sudoconf .= "Plugin sudoers_io " . SUDO_LIBEXEC_DIR . "/sudoers.so\n";
	$sudoconf .= "Path noexec " . SUDO_LIBEXEC_DIR . "/sudo_noexec.so\n";
	file_put_contents(SUDO_CONF, $sudoconf);

	if (!is_array($config['installedpackages']['sudo']['config'][0]['row'])) {
		/* No config, wipe sudoers file and bail. */
		unlink(SUDO_SUDOERS);
		log_error("No sudo configuration found, removing sudoers file to prevent unpredictable results.");
		return;
	}
	$sudocfg = &$config['installedpackages']['sudo']['config'][0]['row'];
	/* Parse the config and massage it into proper sudo config lines. */
	foreach ($sudocfg as $sudo_commands) {
		// (user|group) ALL=(ALL|user spec) ALL|command list
		list($etype, $ename) = explode(":", $sudo_commands['username']);
		$user = ($etype == "group") ? "%{$ename}" : $ename;
		list($rtype, $rname) = explode(":", $sudo_commands['runas']);
		$runas = ($rtype == "group") ? ":{$rname}" : $rname;
		$nopasswd = ($sudo_commands['nopasswd'] == "ON") ? "NOPASSWD:" : "";
		$commands = (empty($sudo_commands['cmdlist'])) ? "ALL" : $sudo_commands['cmdlist'];
		$commands = ($commands == "all") ? "ALL" : $commands;
		$sudoers .= "{$user} ALL=({$runas}) {$nopasswd} {$commands}\n";
	}

	/* Check validity of the sudoers data created above. */
	$tmpsudoers = tempnam("/tmp", "sudoers");
	file_put_contents($tmpsudoers, $sudoers);
	$result = exec("/usr/local/sbin/visudo -c -f {$tmpsudoers}");

	/* If the file is OK, move it into place with the correct permissions, otherwise log an error and trash it. */
	if (stristr($result, "parsed OK")) {
		rename($tmpsudoers, SUDO_SUDOERS);
		chmod(SUDO_SUDOERS, 0440);
	} else {
		log_error("Sudoers file invalid: {$result}");
		unlink($tmpsudoers);
	}
	conf_mount_ro();
}

/*
Get a list of users and groups in a format we can use to make proper sudoers entries.
Optionally include "ALL" as a user (for use by the Run As list)
 */
function sudo_get_users($list_all_user = false) {
	global $config;
	if (!is_array($config['system']['user'])) {
		$config['system']['user'] = array();
	}
	$a_user = &$config['system']['user'];
	if (!is_array($config['system']['group'])) {
		$config['system']['group'] = array();
	}
	$a_group = &$config['system']['group'];
	$users = array();

	/* Make an entry for root, even though admin is essentially the same as root, they are distinct. */
	$tmpuser = array();
	$tmpuser["name"] = "user:root";
	$tmpuser["descr"] = "User: root";
	$users[] = $tmpuser;

	/* Add the all user if we want it */
	if ($list_all_user) {
		$tmpuser = array();
		$tmpuser["name"] = "user:ALL";
		$tmpuser["descr"] = "User: ALL Users";
		$users[] = $tmpuser;
	}

	foreach ($a_user as $user) {
		$tmpuser = array();
		$tmpuser["name"] = "user:{$user['name']}";
		$tmpuser["descr"] = "User: {$user['name']}";
		$users[] = $tmpuser;
	}

	/* Add the wheel group here. We may need other manual groups later (e.g. operator) */
	$tmpuser = array();
	$tmpuser["name"] = "group:wheel";
	$tmpuser["descr"] = "Group: wheel";
	$users[] = $tmpuser;

	foreach ($a_group as $group) {
		/* The "all" group is internal and doesn't make sense to use here. */
		if ($group['name'] == "all") {
			continue;
		}
		$tmpgroup = array();
		$tmpgroup["name"] = "group:{$group['name']}";
		$tmpgroup["descr"] = "Group: {$group['name']}";
		$users[] = $tmpgroup;
	}

	return $users;
}

/*
Make sure commands passed in are valid executables to help ensure a valid sudoers file and expected behavior.
This also forces the user to give full paths to executables, which they should be doing anyhow.
 */
function sudo_validate_commands(&$input_errors) {
	$idx = 0;
	while (isset($_POST["cmdlist{$idx}"])) {
		$commands = $_POST["cmdlist" . $idx++];
		if (strtoupper($commands) == "ALL") {
			continue;
		}
		$commands = explode(",", $commands);
		foreach ($commands as $command) {
			list($cmd, $params) = explode(" ", trim($command), 2);
			if (!is_executable($cmd)) {
				$input_errors[] = htmlspecialchars($cmd) . " is not an executable command.";
			}
		}
	}
}
?>
