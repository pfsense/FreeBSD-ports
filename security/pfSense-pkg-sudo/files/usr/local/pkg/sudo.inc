<?php
/*
 * sudo.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2013-2024 Rubicon Communications, LLC (Netgate)
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

function sudo_install() {
	global $g, $config;

	/*
	 * If we don't have a config, pre-load some good default sudo entries.
	 */
	if (is_array(
	    $config['installedpackages']['sudo']['config'][0]['row'])) {
		return;
	}
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

function sudo_write_config() {
	global $config;
	$sudoers = "";

	$sudoconf = "Plugin sudoers_policy " . SUDO_LIBEXEC_DIR .
	    "/sudoers.so\n";
	$sudoconf .= "Plugin sudoers_io " . SUDO_LIBEXEC_DIR . "/sudoers.so\n";
	$sudoconf .= "Path noexec " . SUDO_LIBEXEC_DIR . "/sudo_noexec.so\n";
	file_put_contents(SUDO_CONF, $sudoconf);

	if (!is_array(
	    $config['installedpackages']['sudo']['config'][0]['row'])) {
		/* No config, wipe sudoers file and bail. */
		unlink(SUDO_SUDOERS);
		log_error("No sudo configuration found, removing sudoers " .
		    "file to prevent unpredictable results.");
		return;
	}
	$sudocfg = &$config['installedpackages']['sudo']['config'][0];

	if ($sudocfg['add_includedir'] == 'include_start') {
		$sudoers .= "#includedir /usr/local/etc/sudoers.d\n";
	}

	/* Parse the config and massage it into proper sudo config lines. */
	foreach ($sudocfg['row'] as $sudo_commands) {
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

	if ($sudocfg['add_includedir'] == 'include_end') {
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
		/* sync the config over XMLRPC */
		sudo_sync_config();
	} else {
		log_error("Sudoers file invalid: {$result}");
		unlink($tmpsudoers);
	}
}

/*
 * Get a list of users and groups in a format we can use to make proper sudoers
 * entries.  Optionally include "ALL" as a user (for use by the Run As list)
 */
function sudo_get_users($list_all_user = false) {
	global $config;
	if (!is_array($config['system']['user'])) {
		$config['system']['user'] = array();
	}
	$a_user = &$config['system']['user'];

	/* Make an entry for root, even though admin is essentially the same
	 * as root, they are distinct.
	 */
	$a_user[]['name'] = 'root';

	/* Include users created by other packages */
	$stdout = '';
	$stderr = '';

	pkg_exec('query %U', $stdout, $stderr);
	foreach (explode(PHP_EOL, $stdout) as $user) {
		if ($user == '') {
			continue;
		}
		$a_user[]['name'] = $user;
	}

	if (!is_array($config['system']['group'])) {
		$config['system']['group'] = array();
	}
	$a_group = &$config['system']['group'];
	
	/* Include groups created by other packages */
	pkg_exec('query %G', $stdout, $stderr);
	foreach (explode(PHP_EOL, $stdout) as $group) {
		if ($group == '') {
			continue;
		}
		$a_group[]['name'] = $group;
	}

	$users = array();

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

	/*
	 * Add the wheel group here. We may need other manual groups later
	 * (e.g. operator)
	 */
	$tmpuser = array();
	$tmpuser["name"] = "group:wheel";
	$tmpuser["descr"] = "Group: wheel";
	$users[] = $tmpuser;

	foreach ($a_group as $group) {
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

function sudo_sync_config() {
	global $config;
	init_config_arr(array('hasync'));
	$system_carp = $config['hasync'];
	if (!is_array($system_carp) ||
	    !$system_carp['synchronizetoip'] ||
	    !$system_carp['username'] ||
	    !$system_carp['password']) {
		return -1;
	}
	if ($config['system']['webgui']['protocol'] == "http") {
		$varsyncprotocol = "http";
		$varsyncport = $config['system']['webgui']['port'] ?: '80';
	} else {
		$varsyncprotocol = "https";
		$varsyncport = $config['system']['webgui']['port'] ?: '443';
	}
	sudo_do_xmlrpc_sync($system_carp['synchronizetoip'], $varsyncport,
	    $varsyncprotocol, $system_carp['username'],
	    $system_carp['password'], 100);
}

/* Do the actual XMLRPC sync */
function sudo_do_xmlrpc_sync($sync_to_ip, $port, $protocol, $username,
    $password, $synctimeout) {
	global $config;

	/*
	 * Send over the <sudo> portion of config.xml
	 * $xml will hold the section to sync.
	 */
	$xml = array();
	$xml['sudo'] = $config['installedpackages']['sudo'];

	$remote_sudo_config_apply = <<<EOD
require_once('/usr/local/pkg/sudo.inc');
sudo_write_config();
EOD;

	$synctimeout = intval($synctimeout);
	$rpc_client = new pfsense_xmlrpc_client();
	$rpc_client->setConnectionData($sync_to_ip, $port, $username, $password,
	    $protocol);
	$resp = $rpc_client->xmlrpc_method('merge_installedpackages_section',
	    $xml, $synctimeout);
	$resp = $rpc_client->xmlrpc_exec_php($remote_sudo_config_apply,
	    $synctimeout);
}

?>
