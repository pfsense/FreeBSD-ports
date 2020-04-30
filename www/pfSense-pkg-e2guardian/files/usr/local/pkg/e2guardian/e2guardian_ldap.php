#!/usr/local/bin/php -f
<?php

/*
 * e2guardian_ldap.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Marcello Coutinho
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

require_once("/etc/inc/util.inc");
require_once("/etc/inc/functions.inc");
require_once("/etc/inc/pkg-utils.inc");
require_once("/etc/inc/globals.inc");

function explode_dn ($dn, $with_attributes=0) {
	$result = ldap_explode_dn($dn, $with_attributes);
	if (is_array($result)) {
		foreach ($result as $key => $value) {
			$result[$key] = $value;
		}
	}
	return $result;
}

function get_ldap_members($group, $user, $password) {
	global $ldap_host;
	global $ldap_dn;
	$LDAPFieldsToFind = array("member");
	print "{$ldap_host} {$ldap_dn}\n";
	$ldap = ldap_connect($ldap_host) or die("Could not connect to LDAP");

	// OPTIONS TO AD
	ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
	ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);

	ldap_bind($ldap, $user, $password) or die("Could not bind to LDAP");

	//check if group is just a name or an ldap string
	$group_cn = (preg_match("/cn=/i", $group)? $group : "cn={$group}");

	$results = ldap_search($ldap, $ldap_dn, $group_cn, $LDAPFieldsToFind);

	$member_list = ldap_get_entries($ldap, $results);
	$group_member_details = array();
	if (is_array($member_list[0])) {
		foreach ($member_list[0] as $list) {
			if (!is_array($list)) {
				continue;
			}
			foreach ($list as $member) {
				$member_dn = explode_dn($member);
				$member_cn = str_replace("CN=", "", $member_dn[0]);
				$member_search = ldap_search($ldap, $ldap_dn, "(CN=" . $member_cn . ")");
				$member_details = ldap_get_entries($ldap, $member_search);
				$group_member_details[] = array(
					$member_details[0]['samaccountname'][0],
					$member_details[0]['displayname'][0],
					$member_details[0]['useraccountcontrol'][0]);
			}
		}
	}
	ldap_close($ldap);
	array_shift($group_member_details);
	ldap_unbind($ldap);
	return $group_member_details;
}

$id = 0;
$apply_config = 0;

init_config_arr(array('installedpackages', 'e2guardianusers', 'config'));
init_config_arr(array('installedpackages', 'e2guardiangroups', 'config'));
init_config_arr(array('installedpackages', 'e2guardianldap', 'config'));

$e2gusers &= $config['installedpackages']['e2guardianusers']['config'];

foreach ($config['installedpackages']['e2guardiangroups']['config'] as $group) {
	//ignore default group
	if ($id == 0) {
		$id++;
		continue;
	}
	$ldap_group_source=(preg_match("/description/", $argv[1])
	    ? "description" : "name");
	if ($argv[2] != $group[$ldap_group_source]) {
		continue;
	}
	$members = "";
	$ldap_servers = explode (',', $group['ldap']);
	echo  "Group : {$group['name']}({$group['description']})\n";
	foreach ($config['installedpackages']['e2guardianldap']['config'] as
	    $server) {
		if (!in_array($server['dc'], $ldap_servers)) {
			continue;
		}
		$ldap_dn = $server['dn'];
		$ldap_host = $server['dc'];
		$mask = ( empty($server['mask']) ? "USER" : $server['mask'] );
		$ldap_username = $server['username'];
		if (preg_match("/cn/", $server['username'])) {
			$ldap_username .= "," . $server['dn'];
		}
		$result = get_ldap_members($group[$ldap_group_source],
		    $ldap_username, $server['password']);
		if ($group['useraccountcontrol'] !="") {
			$valid_account_codes = explode(",",
			    $group['useraccountcontrol']);
		}
		foreach ($result as $mvalue) {
			if (!preg_match ("/\w+/", $mvalue[0])) {
				continue;
			}
			$name= preg_replace("/&([a-z])[a-z]+;/i", "$1",
			    htmlentities($mvalue[1]));
			$pattern[0] = "/USER/";
			$pattern[1] = "/,/";
			$pattern[2] = "/NAME/";
			$replace[0] = $mvalue[0];
			$replace[1] = "\n";
			$replace[2] = "$name";

			if (is_array($valid_account_codes)) {
				if (in_array($mvalue[2], $valid_account_codes,
				    true)) {
					$members .= preg_replace($pattern,
					    $replace, $mask) . "\n";
				}
			} else {
				$members .= preg_replace($pattern, $replace,
				    $mask) . "\n";
			}
		}
	}
	if (empty($members)) {
		if (!is_null($e2gusers[strtolower($group['name'])])) {
			$e2gusers[strtolower($group['name'])] = NULL;
			$apply_config++;
		}
	} else {
		$import_users = explode("\n", $members);
		asort($import_users);
		$members = base64_encode(implode("\n", $import_users));
		if ($e2gusers[strtolower($group['name'])] != $members) {
			$e2gusers['config'][strtolower($group['name'])] =
			    $members;
			$apply_config++;
		}
	}
}

if ($apply_config > 0) {
	print "User list from LDAP is different from current group, applying new configuration...";
	write_config();
	include("/usr/local/pkg/e2guardian/e2guardian.inc");
	sync_package_e2guardian();
	e2guardian_start();
	print "done\n";
} else {
	print "User list from LDAP is already the same as current group, no changes made\n";
}

?>
