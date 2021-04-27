<?php
/*
 * wg_globals.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2021 R. Christian McDonald
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the 'License');
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an 'AS IS' BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

// Global defines

require_once('config.inc');
require_once('globals.inc');

global $wgg;

$wgg = array(
	'wg'			=> '/usr/local/bin/wg',
	'wg_quick'		=> '/usr/local/bin/wg-quick',
	'bash'			=> '/usr/local/bin/bash',
	'ifconfig'		=> '/sbin/ifconfig',
	'kldstat'		=> '/sbin/kldstat',
	'pkg'			=> '/usr/sbin/pkg',
	'kmod'			=> 'if_wg',
	'pkg_names'		=> array(
					'pfSense-pkg-WireGuard',
					'bash',
					'wireguard-kmod',
					'wireguard-tools'),
	'conf_path'		=> '/usr/local/etc/wireguard',
	'xml_path'		=> array('installedpackages', 'wireguard'),
	'conf_paths_to_clean'	=> array(
					'/usr/local/etc/wireguard', 
					'/etc/wireguard', 
					'/etc/wg'),
	'xml_paths_to_clean'	=> array(
					array('wireguard'), 
					array('installedpackages', 'wireguard')),
	'if_prefix'		=> 'tun_wg',
	'if_group'		=> 'WireGuard',
	'ifgroupentry'		=> array(
					'ifname' => 'WireGuard', 
					'descr' => 'Dynamic Group for WireGuard (DO NOT EDIT)', 
					'members' => null),
	'default_mtu'		=> 1420,
	'default_port'		=> 51820,
	'max_port'		=> 65535,
	'max_tunnels'		=> 32768
);

function &array_get_value(array &$array, $parents) {

    $ref = &$array;

    foreach ((array) $parents as $parent) {

        if (is_array($ref) && array_key_exists($parent, $ref)) {

            $ref = &$ref[$parent];

        } else {

            return null;

        }

    }

    return $ref;

}

function array_set_value(array &$array, $parents, $value) {

    $ref = &$array;

    foreach ((array) $parents as $parent) {

        if (isset($ref) && !is_array($ref)) {

            $ref = array();

        }

        $ref = &$ref[$parent];

    }

    $ref = $value;

}

function array_unset_value(&$array, $parents) {

    $key = array_shift($parents);

    if (empty($parents)) {

        unset($array[$key]);

    } else {

        array_unset_value($array[$key], $parents);

    }

}

/*
 * This populates the $wgg with the latest information from config.xml
 */
function wg_globals() {
	global $config, $wgg;

	// Reload config.xml to get any recent changes
	$config = parse_config(true);

	// Construct array paths relative to the package root xml_path
	$a_config_path = array_merge($wgg['xml_path'], array('config', 0));
	$a_tunnels_path = array_merge($wgg['xml_path'], array('tunnels', 'item'));
	$a_peers_path = array_merge($wgg['xml_path'], array('peers', 'item'));

	// Need to initialize these config paths as arrays first
	init_config_arr($a_config_path);
	init_config_arr($a_tunnels_path);
	init_config_arr($a_peers_path);

	// By reference assignment for reading and writing back to the configure at xml_path through $wgg
	$wgg['config'] = &array_get_value($config, $a_config_path);
	$wgg['tunnels'] = &array_get_value($config, $a_tunnels_path);
	$wgg['peers'] = &array_get_value($config, $a_peers_path);

}

// Call this often to read the latest configuration information
wg_globals();

?>
