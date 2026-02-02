<?php
/*
 * sudo.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2013-2025 Rubicon Communications, LLC (Netgate)
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
require_once("util.inc");
require_once("pkg-utils.inc");
require_once("xmlrpc_client.inc");

define('SUDO_BASE','/usr/local');
define('SUDO_LIBEXEC_DIR', '/usr/local/libexec/sudo');
define('SUDO_CONFIG_DIR', SUDO_BASE . '/etc');
define('SUDO_CONF', SUDO_CONFIG_DIR . '/sudo.conf');
define('SUDO_SUDOERS', SUDO_CONFIG_DIR . '/sudoers');
define('LOG_PREFIX_PKG_SUDO', 'sudo');

function sudo_install() {
	global $g;

	/*
	 * If we don't have a config, pre-load some good default sudo entries.
	 */
	if (is_array(
	    config_get_path('installedpackages/sudo/config/0/row'))) {
		return;
	}
	config_set_path('installedpackages/sudo/config/0/row', array(
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
	));
}

function sudo_write_config() {
	$sudoers = "";

	$sudoconf = "Plugin sudoers_policy " . SUDO_LIBEXEC_DIR .
	    "/sudoers.so\n";
	$sudoconf .= "Plugin sudoers_io " . SUDO_LIBEXEC_DIR . "/sudoers.so\n";
	$sudoconf .= "Path noexec " . SUDO_LIBEXEC_DIR . "/sudo_noexec.so\n";
	file_put_contents(SUDO_CONF, $sudoconf);

	if (!is_array(
	    config_get_path('installedpackages/sudo/config/0/row'))) {
		/* No config, wipe sudoers file and bail. */
		unlink(SUDO_SUDOERS);
		logger(LOG_WARNING, localize_text("No sudo configuration found, removing sudoers " .
		    "file to prevent unpredictable results."), LOG_PREFIX_PKG_SUDO);
		return;
	}

	if (config_get_path('installedpackages/sudo/config/0/add_includedir') == 'include_start') {
		$sudoers .= "#includedir /usr/local/etc/sudoers.d\n";
	}

	/* Parse the config and massage it into proper sudo config lines. */
	foreach (config_get_path('installedpackages/sudo/config/0/row', []) as $sudo_commands) {
		// (user|group) ALL=(ALL|user spec) ALL|command list
		list($etype, $ename) = explode(":", $sudo_commands['username']);
		$user = ($etype == "group") ? "%{$ename}" : $ename;
		list($rtype, $rname) = explode(":", $sudo_commands['runas']);
		$runas = ($rtype == "group") ? ":{$rname}" : $rname;
		$nopasswd = ($sudo_commands['nopasswd'] == "ON")
		    ? "NOPASSWD:" : "";
		$commands = (empty($sudo_commands['cmdlist']))
		    ? "ALL" : $sudo_commands['cmdlist'];
		$commands = ($commands == "all") ? "ALL" : $commands;
		$sudoers .= "{$user} ALL=({$runas}) {$nopasswd} {$commands}\n";
	}

	if (config_get_path('installedpackages/sudo/config/0/add_includedir') == 'include_end') {
		$sudoers .= "#includedir /usr/local/etc/sudoers.d\n";
	}

	/* Check validity of the sudoers data created above. */
	$tmpsudoers = tempnam("/tmp", "sudoers");
	file_put_contents($tmpsudoers, $sudoers);
	$result = exec("/usr/local/sbin/visudo -c -f {$tmpsudoers}");

	/*
	 * If the file is OK, move it into place with the correct permissions,
	 * otherwise log an error and trash it.
	 */
	if (stristr($result, "parsed OK")) {
		rename($tmpsudoers, SUDO_SUDOERS);
		chmod(SUDO_SUDOERS, 0440);
	} else {
		logger(LOG_ERR, localize_text("Sudoers file invalid: %s", $result), LOG_PREFIX_PKG_SUDO);
		unlink($tmpsudoers);
	}
}

/*
 * Get a list of users and groups in a format we can use to make proper sudoers
 * entries.  Optionally include "ALL" as a user (for use by the Run As list)
 */
function sudo_get_users($list_all_user = false) {
	/* Collect users and groups from config */
	$users_config = config_get_path('system/user', []);
	$groups_config = config_get_path('system/group', []);

	/* Collect users and groups from packages */
	$package_users = [];
	$stdout = $stderr = '';
	pkg_exec('query %U', $stdout, $stderr);
	foreach (explode(PHP_EOL, $stdout) as $user) {
		if ($user == '') {
			continue;
		}
		$package_users[$user] = $user;
	}
	$package_groups = [];
	$stdout = $stderr = '';
	pkg_exec('query %G', $stdout, $stderr);
	foreach (explode(PHP_EOL, $stdout) as $group) {
		if ($group == '') {
			continue;
		}
		$package_groups[$group] = $group;
	}

	/* Create a map from the list for easier searching */
	$config_users = array_column($users_config, 'name', 'name');
	$config_groups = array_column($groups_config, 'name', 'name');

	/* Make an entry for root, even though admin is essentially the same
	 * as root, they are distinct.
	 */
	if (!isset($config_users['root'])) {
		$users_config[] = ['name' => 'root'];
	}

	/* Include users created by other packages */
	foreach ($package_users as $package_user) {
		if (isset($config_users[$package_user])) {
			continue;
		}
		$users_config[] = ['name' => $package_user];
	}

	/* Include groups created by other packages */
	foreach ($package_groups as $package_group) {
		if (isset($config_groups[$package_group])) {
			continue;
		}
		$groups_config[] = ['name' => $package_group];
	}

	$users = array();

	/* Add the all user if we want it */
	if ($list_all_user) {
		$tmpuser = array();
		$tmpuser["name"] = "user:ALL";
		$tmpuser["descr"] = "User: ALL Users";
		$users[] = $tmpuser;
	}

	foreach ($users_config as $user) {
		$tmpuser = array();
		$tmpuser["name"] = "user:{$user['name']}";
		$tmpuser["descr"] = "User: {$user['name']}";
		$users[] = $tmpuser;
	}

	/*
	 * Add the wheel group here. We may need other manual groups later
	 * (e.g. operator)
	 */
	$tmpuser = array();
	$tmpuser["name"] = "group:wheel";
	$tmpuser["descr"] = "Group: wheel";
	$users[] = $tmpuser;

	foreach ($groups_config as $group) {
		/*
		 * The "all" group is internal and doesn't make sense to use
		 * here.
		 */
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
 * Make sure commands passed in are valid executables to help ensure a valid
 * sudoers file and expected behavior.  This also forces the user to give full
 * paths to executables, which they should be doing anyhow.
 */
function sudo_validate_commands(&$input_errors) {
	$idx = 0;
	while (isset($_POST["cmdlist{$idx}"])) {
		$commands = $_POST["cmdlist" . $idx++];
		if (strtoupper($commands) == "ALL" || empty($commands)) {
			continue;
		}
		$commands = explode(",", $commands);
		foreach ($commands as $command) {
			list($cmd, $params) = explode(" ", trim($command), 2);
			if (!is_executable($cmd)) {
				$input_errors[] = htmlspecialchars($cmd) .
				    " is not an executable command.";
			}
		}
	}
}

function sudo_plugin_xmlrpc_send() {
	return [
		'installedpackages/sudo'
	];
}

function sudo_plugin_xmlrpc_recv($new_sections) {
	$section_paths = [
		'installedpackages/sudo'
	];

	$ret = [
		'xmlrpc_recv_result' => false
	];
	foreach ($section_paths as $path) {
		$old_section = config_get_path($path, []);
		$new_section = array_get_path($new_sections, $path, []);

		// Don't save empty configuration sections.
		if (empty($old_section) && empty($new_section)) {
			continue;
		}
		// Ignore unchanged configuration sections.
		if ($old_section === $new_section) {
			continue;
		}

		$ret[$path] = array_merge($old_section, $new_section);
		$ret['xmlrpc_recv_result'] = true;
	}

	return $ret;
}

function sudo_plugin_xmlrpc_recv_done($xmlrpc_recv_result) {
	if (array_get_path($xmlrpc_recv_result, 'sudo/xmlrpc_recv_result') !== true) {
		return;
	}
	sudo_write_config();
}

?>
