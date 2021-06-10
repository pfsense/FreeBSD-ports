<?php
/*
 * wg_install.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021 R. Christian McDonald (https://github.com/theonemcdonald)
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
require_once('wireguard/wg_api.inc');
require_once('wireguard/wg_globals.inc');
require_once('wireguard/wg_service.inc');

/*
 * This is the main install hook
 */
function wg_install() {
	global $g;

	$g['wireguard_installing'] = true;

	update_status("\n  Upgrading any existing WireGuard XML configuration...");

	// Things to potentially upgrade, these will be removed in future versions.
	wg_upgrade_addresses();

	wg_upgrade_allowedips();

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

	update_status("done.\n");

}

function wg_upgrade_allowedips() {
	global $wgg;

	// Read latest settings
	wg_globals();

	// Save a copy for manipulation
	$peers = $wgg['peers'];

	foreach ($peers as $peer_idx => $peer) {

		if (is_string($peer['allowedips']) && !empty($peer['allowedips'])) {

			unset($wgg['peers'][$peer_idx]['allowedips']);

			$a_allowedips = explode(',', $peer['allowedips']);

			$tmp_addrs = array();

			foreach($a_allowedips as $a_index => $ip) {

				$tmp_addrs['item'][$a_index]['addr'] 		= $ip;

				$tmp_addrs['item'][$a_index]['descr'] 		= null;

				$tmp_addrs['item'][$a_index]['peeraddr'] 	= null;

			}

			$wgg['peers'][$peer_idx]['allowedips'] = $tmp_addrs;

		}

	}

	// Write the changes out to disk
	write_config("[{$wgg['pkg_name']}] Upgraded peer allowed ips XML structure.");

	// Read latest settings
	wg_globals();

	// Save a copy for manipulation
	$peers = $wgg['peers'];

	foreach ($peers as $peer_idx => $peer) {

		if (is_array($peer['allowedips']['item']) && !is_array($peer['allowedips']['row'])) {

			unset($wgg['peers'][$peer_idx]['allowedips']);

			$tmp_addrs = array();

			foreach ($peer['allowedips']['item'] as $a_index => $item) {

				list($addr, $addr_mask) 			= explode('/', $item['addr']);

				$tmp_addrs['row'][$a_index]['address'] 		= $addr;

				$tmp_addrs['row'][$a_index]['mask'] 		= $addr_mask;

				$tmp_addrs['row'][$a_index]['description'] 	= $item['descr'];

			}

			$wgg['peers'][$peer_idx]['allowedips'] = $tmp_addrs;

		}

	}
	
	// Write the changes out to disk
	write_config("[{$wgg['pkg_name']}] Upgraded peer allowed ips XML structure.");

	// Read latest settings
	wg_globals();

	// Save a copy for manipulation
	$peers = $wgg['peers'];

	foreach ($peers as $peer_idx => $peer) {

		if (is_array($peer['allowedips']['row'])) {

			$tmp_addrs = array();

			foreach ($peer['allowedips']['row'] as $a_index => $item) {

				$tmp_addrs['row'][$a_index]['address'] 	= $item['address'];

				$tmp_addrs['row'][$a_index]['mask'] 	= $item['mask'];

				$tmp_addrs['row'][$a_index]['descr'] 	= (!isset($item['descr'])) ? $item['description'] : $item['descr'];

				unset($tmp_addrs['row'][$a_index]['description']);

			}

			$wgg['peers'][$peer_idx]['allowedips'] = $tmp_addrs;

		}

	}

	// Write the changes out to disk
	write_config("[{$wgg['pkg_name']}] Upgraded peer allowed ips XML structure.");

}

function wg_upgrade_addresses() {
	global $wgg;

	// Read latest settings
	wg_globals();

	// Save a copy for manipulation
	$tunnels = $wgg['tunnels'];

	foreach ($tunnels as $tun_idx => $tunnel) {

		if (is_string($tunnel['addresses']) && !empty($tunnel['addresses'])) {

			unset($wgg['tunnels'][$tun_idx]['addresses']);

			$a_addresses = explode(',', $tunnel['addresses']);

			$tmp_addrs = array();

			foreach($a_addresses as $a_index=> $address) {

				$tmp_addrs['item'][$a_index]['addr'] 	= $address;

				$tmp_addrs['item'][$a_index]['descr'] 	= null;

			}

			$wgg['tunnels'][$tun_idx]['addresses'] = $tmp_addrs;

		}
	

	}

	// Write the changes out to disk
	write_config("[{$wgg['pkg_name']}] Upgraded tunnel addresses XML structure.");

	// Read latest settings
	wg_globals();

	// Save a copy for manipulation
	$tunnels = $wgg['tunnels'];

	foreach ($tunnels as $t_index => $tunnel) {

		if (is_array($tunnel['addresses']['item']) && !is_array($tunnel['addresses']['row'])) {

			unset($wgg['tunnels'][$t_index]['addresses']);

			$tmp_addrs = array();

			foreach ($tunnel['addresses']['item'] as $a_index => $item) {

				list($addr, $addr_mask) 			= explode('/', $item['addr']);

				$tmp_addrs['row'][$a_index]['address'] 		= $addr;

				$tmp_addrs['row'][$a_index]['mask'] 		= $addr_mask;

				$tmp_addrs['row'][$a_index]['description'] 	= $item['descr'];

			}

			$wgg['tunnels'][$t_index]['addresses'] = $tmp_addrs;

		}

	}

	// Write the changes out to disk
	write_config("[{$wgg['pkg_name']}] Upgraded tunnel addresses XML structure.");

	// Read latest settings
	wg_globals();

	// Save a copy for manipulation
	$tunnels = $wgg['tunnels'];

	foreach ($tunnels as $tun_idx => $tunnel) {

		if (is_array($tunnel['addresses']['row'])) {

			$tmp_addrs = array();

			foreach ($tunnel['addresses']['row'] as $a_index => $item) {

				$tmp_addrs['row'][$a_index]['address'] 	= $item['address'];

				$tmp_addrs['row'][$a_index]['mask'] 	= $item['mask'];

				$tmp_addrs['row'][$a_index]['descr'] 	= (!isset($item['descr'])) ? $item['description'] : $item['descr'];

				unset($tmp_addrs['row'][$a_index]['description']);

			}

			$wgg['tunnels'][$tun_idx]['addresses'] = $tmp_addrs;

		}

	}

	// Write the changes out to disk
	write_config("[{$wgg['pkg_name']}] Upgraded tunnel addresses XML structure.");

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

function wg_service_rcfile() {
	global $wgg;

	$start = $stop = array();

	$start[] = "{$wgg['wg_daemon']} start";

	$stop[] = "{$wgg['wg_daemon']} stop";

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

function wg_destroy_tunnels() {
	global $wgg;

	$ret_code = 0;

	// We only care about the ones in the running state
	$tunnels_to_destroy = wg_get_real_ifs();

	// Tear down interfaces
	foreach ($tunnels_to_destroy as $tunnel) {

		$ret_code |= wg_interface_destroy($tunnel);

	}

	// Now unload the kernel module if it is still loaded
	if ($ret_code == 0 && is_module_loaded($wgg['kmod'])) {

		$kmod = $wgg['kmod'];

		wg_escapeshellarg($kmod);

		$ret_code |= mwexec("{$wgg['kldunload']} {$kmod}");

	}

	return $ret_code;

}

/*
 * This function creates earlyshellcmd entries in the config
 */
function wg_earlyshellcmd_install() {
	global $config, $wgg;

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

	write_config("[{$wgg['pkg_name']}] earlyshellcmd(s) installed.");

}

/*
 * This function removes earlyshellcmd entries in the config
 */
function wg_earlyshellcmd_deinstall() {
	global $config, $wgg;

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

	write_config("[{$wgg['pkg_name']}] earlyshellcmd(s) de-installed.");

}

/*
 * This function creates the WireGuard interface group
 */
function wg_ifgroup_install() {
	global $config, $wgg;

	wg_ifgroup_deinstall();

	init_config_arr(array('ifgroups', 'ifgroupentry'));

	$a_ifgroups = &$config['ifgroups']['ifgroupentry'];

	$ifgroup_names = array_map(fn($x) => $x['ifname'], $a_ifgroups);

	if (!preg_grep("/{$wgg['ifgroupentry']['ifname']}/", $ifgroup_names)) {

		// Put our entry at the top of the list
		array_unshift($a_ifgroups, $wgg['ifgroupentry']);

	}

	write_config("[{$wgg['pkg_name']}] Interface group ({$wgg['ifgroupentry']['ifname']}) installed.");

}

/*
 * This function removes the WireGuard interface group
 */
function wg_ifgroup_deinstall() {
	global $config, $wgg;

	init_config_arr(array('ifgroups', 'ifgroupentry'));

	$a_ifgroups = &$config['ifgroups']['ifgroupentry'];

	$ifgroup_names = array_map(fn($x) => $x['ifname'], $a_ifgroups);

	if ($groups = preg_grep("/{$wgg['ifgroupentry']['ifname']}/", $ifgroup_names)) {

		foreach ($groups as $group_idx => $group) {

			unset($a_ifgroups[$group_idx]);

		}

	}

	write_config("[{$wgg['pkg_name']}] Interface group ({$wgg['ifgroupentry']['ifname']}) de-installed.");

}

/*
 * This function removes the WireGuard Unbound access list
 */
function wg_unbound_acl_deinstall() {
	global $config, $wgg;

	init_config_arr(array('unbound', 'acls'));

	$a_unbound_acls = &$config['unbound']['acls'];

	$a_unbound_acls_names = array_map(fn($x) => $x['aclname'], $a_unbound_acls);

	if ($acls = preg_grep("/{$wgg['unboundaclentry']['aclname']}/", $a_unbound_acls_names)) {

		foreach ($acls as $acl_idx => $acl) {

			unset($a_unbound_acls[$acl_idx]);

		}

	}

	write_config("[{$wgg['pkg_name']}] Unbound ACL group ({$wgg['unboundaclentry']['aclname']}) de-installed.");

}

/*
 * This function creates the WireGuard Unbound access list
 */
function wg_unbound_acl_install() {
	global $config, $wgg;

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

				$tmp_acls[$network_idx]['description']	= "{$network['tun']} ({$network['descr']})";

				$wgg['unboundaclentry']['row'] = $tmp_acls;

			}

			// Put our entry at the top of the list
			array_unshift($a_unbound_acls, $wgg['unboundaclentry']);

		}

	}

	write_config("[{$wgg['pkg_name']}] Unbound ACL group ({$wgg['unboundaclentry']['aclname']}) installed.");

}


?>