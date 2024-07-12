<?php
/*
 * wg_install.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2021 R. Christian McDonald (https://github.com/rcmcdonald91)
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

// pfSense includes
require_once('config.inc');
require_once('services.inc');
require_once('service-utils.inc');
require_once('unbound.inc');
require_once('util.inc');

// WireGuard includes
require_once('wireguard/includes/wg_api.inc');
require_once('wireguard/includes/wg_globals.inc');
require_once('wireguard/includes/wg_service.inc');

/*
 * This is the main install hook
 */
function wg_install() {
	global $g;

	$g['wireguard_installing'] = true;

	update_status("done.\n  Installing WireGuard early shell commands...");

	// Installs the WireGuard earlyshellcmds
	wg_earlyshellcmd_install();

	update_status("done.\n  Creating WireGuard interface group...");

	// Create the 'WireGuard' interface group
	wg_ifgroup_install();

	update_status("done.\n  Creating WireGuard Unbound access list...");

	// Create the WireGuard Unbound ACL
	wg_unbound_acl_install();

	update_status("done.\n  Installing WireGuard service...");

	// Create the WireGuard service
	wg_service_install();

	update_status("done.\n  Applying WireGuard default settings as necessary...");

	wg_defaults_install();

	update_status("done.\n");
}

/*
 * This is the main deinstall hook
 */
function wg_deinstall() {
	global $config, $wgg;

	wg_globals();

	update_status("  Removing WireGuard early shell commands...");

	// Removes the WireGuard earlyshellcmds
	wg_earlyshellcmd_deinstall();

	update_status("done.\n  Removing WireGuard interface group...");

	// Removes the 'WireGuard' interface group
	wg_ifgroup_deinstall();

	update_status("done.\n  Removing WireGuard temporary files...");

	// Removes WireGuard temporary files
	wg_delete_temp_files();

	// Only delete WireGuard configuration if keep_conf is explicitly 'no'
	if (isset($wgg['config']['keep_conf']) && $wgg['config']['keep_conf'] == 'no') {
		update_status("done.\n  Removing WireGuard configuration settings...");

		// Removes WireGuard configuration
		wg_remove_config_settings();
	} else {
		update_status("done.\n  Keeping WireGuard configuration settings...");
	}

	update_status("done.\n  Removing WireGuard Unbound access list...");

	// Remove Unbound ACL
	wg_unbound_acl_deinstall();

	update_status("done.\n  Destroying WireGuard tunnels...");

	// Teardown any WireGuard tunnel interfaces
	wg_destroy_tunnels();

	update_status("done.\n  Stopping and removing the WireGuard service...");

	// Stop and remove the WireGuard service
	wg_service_deinstall(true);

	update_status("done.\n");
}

function wg_delete_temp_files() {
	global $wgg;

	// Removes WireGuard temporary configuration files
	wg_delete_config_files();

	// This nukes any lingering .apply files
	foreach ($wgg['applylist'] as $path) {
		unlink_if_exists($path);
	}

	// Package is uninstalling, so clear lingering dirty status too...
	foreach ($wgg['subsystems'] as $subsystem) {
		clear_subsystem_dirty($subsystem);
	}
}

/*
 * This just ensures that there are defaults in the XML.
 * The package should function correctly without these being set at all.
 * This is just to make sure the XML is reflecting the running behavior.
 */
function wg_defaults_install() {
	global $wgg;

	// Read latest settings
	wg_globals();

	$wgg['config']['enable']			= wg_is_service_enabled() ? 'on' : 'off';

	$wgg['config']['keep_conf']			= isset($wgg['config']['keep_conf']) ? $wgg['config']['keep_conf'] : 'yes';

	$wgg['config']['resolve_interval']		= isset($wgg['config']['resolve_interval']) ? $wgg['config']['resolve_interval'] : $wgg['default_resolve_interval'];
	
	$wgg['config']['resolve_interval_track']	= isset($wgg['config']['resolve_interval_track']) ? $wgg['config']['resolve_interval_track'] : 'no';
	
	$wgg['config']['interface_group']		= isset($wgg['config']['interface_group']) ? $wgg['config']['interface_group'] : 'all';
	
	$wgg['config']['hide_secrets'] 			= isset($wgg['config']['hide_secrets']) ? $wgg['config']['hide_secrets'] : 'yes';

	$wgg['config']['hide_peers'] 			= isset($wgg['config']['hide_peers']) ? $wgg['config']['hide_peers'] : 'yes';
	
	wg_write_config('Applied package default settings as necessary.', false);
}

function wg_service_rcfile() {
	global $wgg;

	$start = $stop = array();

	$start[] 		= "{$wgg['wg_daemon']} start";

	$stop[] 		= "{$wgg['wg_daemon']} stop";

	$rcfile['file'] 	= $wgg['service_name'];

	$rcfile['start'] 	= implode("\n\t", $start);

	$rcfile['stop'] 	= implode("\n\t", $stop);

	write_rcfile($rcfile);
}

function wg_service_install() {
	global $wgg;

	wg_service_rcfile();

	wg_service_deinstall();

	link($wgg['php'], $wgg['php_wg']);
}

function wg_service_deinstall($force_stop = false) {
	global $wgg;

	if ($force_stop) {
		wg_service_fpm_stop();
	}

	unlink_if_exists($wgg['php_wg']);
}

function wg_destroy_tunnels(&$cmds = null) {
	global $wgg;

	// Assume this will be successful...
	$res = true;

	// Tear down interfaces
	foreach (wg_get_running_ifs() as $tunnel) {
		$res &= wg_ifconfig_if_destroy($tunnel, $cmds);
	}

	// Now attempt to unload the module if the above was successful
	if ($res) {
		$res &= wg_kld_loadunload(false, $cmds);
	}

	return $res;
}

function wg_listenport_alias_install() {
	global $config, $wgg;

	wg_globals();

	// TODO ?
}

/*
 * This function creates earlyshellcmd entries in the config
 */
function wg_earlyshellcmd_install() {
	global $config, $wgg;

	wg_globals();

	wg_earlyshellcmd_deinstall();

	init_config_arr(array('system', 'earlyshellcmd'));

	init_config_arr(array('installedpackages', 'shellcmdsettings', 'config'));

	$a_earlyshellcmd = &$config['system']['earlyshellcmd'];

	$a_shellcmdsettings = &$config['installedpackages']['shellcmdsettings']['config'];

	$shellcmd_pkg_cmds = array_map(fn($x) => $x['cmd'], $a_shellcmdsettings);

	foreach ($wgg['shellcmdentries'] as $shellcmd) {
		// Need to escape slashes from the path
		$escaped_cmd = preg_quote($shellcmd['cmd'], '/');

		// Install the earlyshellcmd
		if (!preg_grep("/{$escaped_cmd}/", $a_earlyshellcmd)) {
			// Put our entry at the top of the list
			array_unshift($a_earlyshellcmd, $shellcmd['cmd']);
		}

		// Update the shellcmd package too
		if (!preg_grep("/{$escaped_cmd}/", $shellcmd_pkg_cmds)) {
			// Put our entry at the top of the list
			array_unshift($a_shellcmdsettings, $shellcmd);
		}
	}

	wg_write_config('Installed earlyshellcmd(s).', false);
}

/*
 * This function removes earlyshellcmd entries in the config
 */
function wg_earlyshellcmd_deinstall() {
	global $config, $wgg;

	wg_globals();

	init_config_arr(array('system', 'earlyshellcmd'));
	
	init_config_arr(array('installedpackages', 'shellcmdsettings', 'config'));

	$a_earlyshellcmd = &$config['system']['earlyshellcmd'];

	$a_shellcmdsettings = &$config['installedpackages']['shellcmdsettings']['config'];

	$shellcmd_pkg_cmds = array_map(fn($x) => $x['cmd'], $a_shellcmdsettings);

	foreach ($wgg['shellcmdentries'] as $shellcmd) {
		// Need to escape slashes from the path
		$escaped_cmd = preg_quote($shellcmd['cmd'], '/');

		// Deinstall the earlyshellcmd
		if ($cmds = preg_grep("/{$escaped_cmd}/", $a_earlyshellcmd)) {
			foreach ($cmds as $cmd_idx => $cmd) {
				unset($a_earlyshellcmd[$cmd_idx]);
			}
		}

		// Update the shellcmd package too
		if ($cmds = preg_grep("/{$escaped_cmd}/", $shellcmd_pkg_cmds)) {
			foreach ($cmds as $cmd_idx => $cmd) {
				unset($a_shellcmdsettings[$cmd_idx]);
			}
		}
	}

	wg_write_config('De-installed earlyshellcmd(s).', false);
}

/*
 * This function creates the WireGuard interface group
 */
function wg_ifgroup_install() {
	global $config, $wgg;

	wg_globals();

	wg_ifgroup_deinstall();

	if (isset($wgg['config']['interface_group'])
	    && ($wgg['config']['interface_group'] == 'none')) {
		// No point installing the interface group...
		return;
	}

	init_config_arr(array('ifgroups', 'ifgroupentry'));

	$a_ifgroups = &$config['ifgroups']['ifgroupentry'];

	$ifgroup_names = array_map(fn($x) => $x['ifname'], $a_ifgroups);

	if (!preg_grep("/{$wgg['ifgroupentry']['ifname']}/", $ifgroup_names)) {
		// Put our entry at the top of the list
		array_unshift($a_ifgroups, $wgg['ifgroupentry']);
	}

	wg_write_config("Installed interface group ({$wgg['ifgroupentry']['ifname']}).", false);
}

/*
 * This function removes the WireGuard interface group
 */
function wg_ifgroup_deinstall() {
	global $config, $wgg;

	wg_globals();

	init_config_arr(array('ifgroups', 'ifgroupentry'));

	$a_ifgroups = &$config['ifgroups']['ifgroupentry'];

	$ifgroup_names = array_map(fn($x) => $x['ifname'], $a_ifgroups);

	if ($groups = preg_grep("/{$wgg['ifgroupentry']['ifname']}/", $ifgroup_names)) {
		foreach ($groups as $group_idx => $group) {
			unset($a_ifgroups[$group_idx]);
		}
	}

	wg_write_config("De-installed interface group ({$wgg['ifgroupentry']['ifname']}).", false);
}

/*
 * This function removes the WireGuard Unbound access list
 */
function wg_unbound_acl_deinstall() {
	global $config, $wgg;

	wg_globals();

	init_config_arr(array('unbound', 'acls'));

	$a_unbound_acls = &$config['unbound']['acls'];

	$a_unbound_acls_names = array_map(fn($x) => $x['aclname'], $a_unbound_acls);

	if ($acls = preg_grep("/{$wgg['unboundaclentry']['aclname']}/", $a_unbound_acls_names)) {
		foreach ($acls as $acl_idx => $acl) {
			unset($a_unbound_acls[$acl_idx]);
		}
	}

	wg_write_config("De-installed Unbound ACL group ({$wgg['unboundaclentry']['aclname']}).", false);
}

/*
 * This function creates the WireGuard Unbound access list
 */
function wg_unbound_acl_install() {
	global $config, $wgg;

	// Format tunnel description if one is configured
	$tunDescr = fn($tunnel) => !empty($tunnel['descr']) ? " ({$tunnel['descr']})" : null;

	wg_globals();

	wg_unbound_acl_deinstall();

	$tun_networks = wg_get_tunnel_networks();

	init_config_arr(array('unbound', 'acls'));

	$a_unbound_acls = &$config['unbound']['acls'];

	$a_unbound_acls_names = array_map(fn($x) => $x['aclname'], $a_unbound_acls);

	// Only need to create the ACL if we actually have tunnel networks to add
	if (!empty($tun_networks) && is_array($tun_networks)) {
		if (!preg_grep("/{$wgg['unboundaclentry']['aclname']}/", $a_unbound_acls_names)) {
			$wgg['unboundaclentry']['aclid'] = unbound_get_next_id();

			$tmp_acls = array();

			foreach ($tun_networks as $network_idx => $network) {
				$tmp_acls[$network_idx]['acl_network'] 	= $network['network'];

				$tmp_acls[$network_idx]['mask']		= $network['mask'];

				$tmp_acls[$network_idx]['description']	= "{$network['tun']}{$tunDescr($network['descr'])}";

				$wgg['unboundaclentry']['row'] = $tmp_acls;
			}

			// Put our entry at the top of the list
			array_unshift($a_unbound_acls, $wgg['unboundaclentry']);
		}
	}

	wg_write_config("Installed Unbound ACL group ({$wgg['unboundaclentry']['aclname']}).", false);
}

?>
