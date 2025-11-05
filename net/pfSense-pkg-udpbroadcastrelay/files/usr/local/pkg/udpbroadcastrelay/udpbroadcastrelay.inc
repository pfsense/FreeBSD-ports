<?php
/*
 * udpbroadcastrelay.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2023-2025 Rubicon Communications, LLC (Netgate)
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

require_once('pfsense-utils.inc');
require_once('service-utils.inc');
require_once('interfaces.inc');

define('LOG_PREFIX_PKG_UDPBROADCASTRELAY', 'udpbroadcastrelay');

global $udpbr_global;
if (!isset($udpbr_global)) {
	$udpbr_global['name'] = LOG_PREFIX_PKG_UDPBROADCASTRELAY;
	$udpbr_global = [
		'name'        => $udpbr_global['name'],
		'config_path' => "installedpackages/{$udpbr_global['name']}/config/0",
	    'rc_filename' => "{$udpbr_global['name']}.sh",
	    'rc'          => "/usr/local/etc/rc.d/{$udpbr_global['name']}.sh",
	    'daemon'      => "/usr/local/sbin/{$udpbr_global['name']}"
	];
}

/**
 * Writes a message to the dedicated service log.
 * 
 * @param string $message The message to log.
 * @param int $priority The log priority for the message.
 * @param bool $config_revision If true, use the message to create a
 *                              config revision.
 */
function udpbr_log(string $message, int $priority = LOG_INFO, bool $config_revision = false): void {
	logger($priority, $message, LOG_PREFIX_PKG_UDPBROADCASTRELAY, LOG_DAEMON);

	if ($config_revision) {
		write_config("UDP Broadcast Relay: {$message}");
	}
}

/**
 * Stops the service and removes the service script.
 */
function udpbr_deinstall(): void {
	global $udpbr_global;

	if (is_service_running($udpbr_global['name'])) {
		udpbr_log(gettext('Stopping service.'), LOG_NOTICE);
		stop_service($udpbr_global['name']);
	}

	unlink_if_exists($udpbr_global['rc']);
	unlink_if_exists('/tmp/udpbr_tmp.*');
}

/**
 * Get available CARP VIPs.
 * 
 * @return array List of strings keyed by the VIP ID.
 */
function udpbr_get_carpvips(): array {
	$list = [];
	foreach (get_configured_vip_list('all', VIP_CARP) as $vid => $vaddr) {
		$vip = get_configured_vip($vid);
		$list[$vid] = "{$vaddr} (vhid: {$vip['vhid']})";
	}

	return ($list);
}

/**
 * Starts or stops the service depending on the CARP status
 * 
 * @param array $plugin_params List of plugin parameters.
 */
function udpbroadcastrelay_plugin_carp(array $plugin_params = []): void {
	global $udpbr_global;

	$start_service = false;
	$status_reason = null;

	// Track a specific CARP VIP
	$config_carp_vid = config_get_path("{$udpbr_global['config_path']}/carpstatusvid", 'none');
	if ($config_carp_vid == 'none') {
		// Only take action if there's a CARP VIP being tracked
		return;
	}
	if (array_get_path($plugin_params, 'type') == 'carp') {
		/* Event triggered by CARP transition */

		// Only take action when an interface is provided
		if (!isset($plugin_params['interface'])) {
			return;
		}

		// Only take action when the CARP VIP interface matches the CARP event interface
		list($carp_vhid, $carp_interface_port) = explode("@", trim($plugin_params['interface']));
		$carp_interface_name = convert_real_interface_to_friendly_interface_name($carp_interface_port);
		$config_carp_vip = get_configured_vip($config_carp_vid);
		if (($config_carp_vip['vhid'] != $carp_vhid) || ($config_carp_vip['interface'] != $carp_interface_name)) {
			return;
		}

		// Initialize the service based solely on the CARP event
		switch (array_get_path($plugin_params, 'event')) {
			case 'rc.carpmaster':
				$start_service = true;
				$status_reason = 'CARP MASTER';
				break;
			case 'rc.carpbackup':
			default:
				$start_service = false;
				$status_reason = 'CARP BACKUP';
				break;
		}
	} else {
		/* Event triggered by service reload */

		// Initialize the service based solely the CARP VIP status
		switch (strtoupper(get_carp_interface_status($config_carp_vid))) {
			// Stop the service if the VIP is down
			case 'BACKUP':
			case 'INIT':
				$start_service = false;
				$status_reason = 'CARP BACKUP/INIT';
				break;
			// Start the service if the VIP is up
			case 'MASTER':
				$start_service = true;
				$status_reason = 'CARP MASTER';
				break;
			// Start the service if the VIP state cannot be determined
			default:
				$start_service = true;
				break;
		}
	}

	$status_reason = isset($status_reason) ? " ({$status_reason})" : '';
	if ($start_service) {
		udpbr_log((gettext('Starting service.') . $status_reason), LOG_NOTICE);
		start_service($udpbr_global['name']);
	} else {
		udpbr_log((gettext('Stopping service.') . $status_reason), LOG_NOTICE);
		stop_service($udpbr_global['name']);
	}
}

/**
 * Generates the shell command needed to run an instance. Does not
 * validate the given configuration.
 * 
 * @param array $config Instance config with interface ports.
 * 
 * @return string Full shell command.
 */
function udpbr_get_command(array $config): string {
	global $udpbr_global;

	$command = "{$udpbr_global['daemon']} --id {$config['id']} --port {$config['port']}";

	foreach ($config['ports'] as $interface) {
		$command .= " --dev {$interface}";
	}
	if (isset($config['multicast'])) {
		$command .= " --multicast {$config['multicast']}";
	}
	if ($config['spoof'] == '1') {
		$command .= ' -s 1.1.1.1';
	} elseif ($config['spoof'] == '2') {
		$command .= ' -s 1.1.1.2';
	}

	$command .= ' -f';

	return escapeshellcmd($command);
}

/**
 * Generates the service script and starts/stops the service as needed.
 */
function udpbr_resync(): void {
	global $udpbr_global;
	$config = udpbr_get_config();

	// Don't take any action if the service is disabled
	if (empty($config) || !isset($config['enable'])) {
		return;
	}

	udpbr_log(gettext('Reloading service.'), LOG_INFO);

	// Stop the service by default
	if (is_service_running($udpbr_global['name'])) {
		udpbr_log(gettext('Stopping service.'), LOG_NOTICE);
		stop_service($udpbr_global['name']);
		unlink_if_exists($udpbr_global['rc']);
	}

	// Bail if the system is booting - package services will be started after
	if (is_platform_booting()) {
		udpbr_log(gettext('Ignoring service start - the system is still booting.'), LOG_INFO);
		return;
	}

	$input_errors = udpbr_parse_config($config);

	// Bail if there's a configuration issue
	if (!empty($input_errors)) {
		udpbr_log(gettext('Failed to start service due to a configuration error.'), LOG_ERR);
		return;
	}

	// Don't take further action if no instance would be started
	if (empty($config['item'])) {
		udpbr_log(gettext('Ignoring service start - no configured instances to start.'), LOG_INFO);
		return;
	}

	// Convenience function to use in heredocs
	$func_gettext = function (string $message): string {return gettext($message);};

	// Genereate the instance commands
	$run_commands = [];
	$instance_id = 0;
	$interfaces = udpbr_get_interfaces();
	foreach ($config['item'] as $instance) {
		if (!isset($instance['enable'])) {
			continue;
		}

		// Generate the instance ID
		$instance_id++;
		$instance['id'] = $instance_id;

		// Use the interface's real name instead of its friendly name
		$instance['ports'] = [];
		foreach (explode(',', $instance['interfaces']) as $interface) {
			$instance['ports'][] = $interfaces[$interface]['port'];
		}

		$run_command = udpbr_get_command($instance);
		$run_commands[] = <<<SSCRIPT
				: > "\${UDPBRTEMP}"
				{$run_command} 2>\$UDPBRTEMP >/dev/null
				ERRORMSG=\$(cat \${UDPBRTEMP})
				if [ "\$ERRORMSG" != '' ]; then
					/usr/bin/logger -p daemon.err -t {$udpbr_global['name']} "ERROR [{$udpbr_global['name']}] {$func_gettext('Failed to start instance.')}: \${ERRORMSG}"
				fi
		
		SSCRIPT;
	}

	// Don't take further action if no instance would be started
	if (empty($run_commands)) {
		udpbr_log(gettext('Ignoring service start - no valid instances to start.'), LOG_INFO);
		return;
	}

	// Write the rc script
	$start = <<<BSSCRIPT
	ERRORMSG=''
	    UDPBRTEMP=$(mktemp /tmp/udpbr_tmp.XXXXXX) || ERRORMSG="{$func_gettext('Failed to create temporary file.')}"
	    if [ "\$ERRORMSG" = '' ]; then

	BSSCRIPT;
	$start .= implode(PHP_EOL, $run_commands);
	$start .= <<<ESSCRIPT
	    rm -f "\${UDPBRTEMP}"
	    else
	        /usr/bin/logger -p daemon.err -t {$udpbr_global['name']} "ERROR [{$udpbr_global['name']}] {$func_gettext('Failed to start service.')} (\${ERRORMSG})"
	    fi
	ESSCRIPT;

	$stop = "/usr/bin/killall -q {$udpbr_global['name']}; rm -f /tmp/udpbr_tmp.*";
	write_rcfile([
		'file' => $udpbr_global['rc_filename'],
		'start' => $start,
		'stop' => $stop
	]);

	if (array_get_path($config, 'carpstatusvid', 'none') != 'none') {
		// Handle CARP status when tracking a CARP VIP
		udpbroadcastrelay_plugin_carp();
	} else {
		// Start the service
		udpbr_log(gettext('Starting service.'), LOG_NOTICE);
		start_service($udpbr_global['name']);
	}
}

/**
 * Generates a list of interfaces and their descriptions.
 * 
 * Example: ['lan' => ['port'=>'igb1', 'descr'=>'LAN']]
 * 
 * @param bool $valid_only (Optional) If false, do not verify that an
 *                         interface is available.
 * @param bool $append_port (Optional) If false, do not append the port to
 *                          the interface's description.
 * 
 * @return array List of interfaces keyed by their friendly name.
 */
function udpbr_get_interfaces(bool $valid_only = true, bool $append_port = true): array {
	$interfaces = [];
	$interface_ports = array_flip(get_interface_arr());
	foreach (config_get_path('interfaces', []) as $interface_name => $interface) {
		if ($valid_only && (!isset($interface['enable']) ||
		    !isset($interface['if']) || !isset($interface_ports[$interface['if']]))) {
			/* Skip invalid interfaces. Valid interfaces must be assigned,
			   enabled, and currently exist on the system */
			continue;
		}

		$interfaces[$interface_name] = [
			'port' => $interface['if'],
			'descr' => $interface['descr']
		];
		if ($append_port) {
			$interfaces[$interface_name]['descr'] .= " ({$interface['if']})";
		}
	}

	return $interfaces;
}

/**
 * Validates all configuration. The given configuration is then normalized
 * with expected values if no errors are detected.
 * 
 * @param array &$config Full config to parse.
 * 
 * @return array|null null on a valid config, otherwise a list of errors.
 */
function udpbr_parse_config(?array &$config): null|array {
	$input_errors = [];

	// Check for invalid arguments
	if (empty($config)) {
		$input_errors[] = 'Invalid configuration.';
		return $input_errors;
	}

	// Validate service 'Track CARP Status' option
	if (isset($config['carpstatusvid']) && ($config['carpstatusvid'] != 'none') &&
	    !array_key_exists($config['carpstatusvid'], udpbr_get_carpvips())) {
		$input_errors[] = 'Invalid "Track CARP Status" option.';
	}

	// Parse all instances without modifying the original configuration
	$temp_config_instances = (is_array($config['item'])) ? $config['item'] : [];
	$enabled_instance_count = 0;
	foreach ($temp_config_instances as $id => &$item) {
		if (is_array(udpbr_parse_instance_config($item, $id))) {
			$input_errors[] = "Invalid configuration for instance {$id}";
		}
		if (isset($item['enable'])) {
			$enabled_instance_count++;
		}
	}

	// Validate instance count
	if ($enabled_instance_count > 63) {
		$input_errors[] = "Instance limit reached; a maximum of 63 enabled instances are supported.";
	}

	// Build the configuration with validated values
	if (empty($input_errors)) {
		$temp_config = [];

		// Optional settings
		if (isset($config['enable'])) {
			$temp_config['enable'] = true;
		}
		if (isset($config['carpstatusvid']) && ($config['carpstatusvid'] != 'none')) {
			$temp_config['carpstatusvid'] = htmlspecialchars($config['carpstatusvid']);
		}
		if (!empty($temp_config_instances)) {
			$temp_config['item'] = $temp_config_instances;
		}

		$config = $temp_config;
		return null;
	}

	return $input_errors;
}

/**
 * Validates the configuration for an instance. The given configuration is
 * then normalized with expected values if no errors are detected.
 * 
 * @param array &$config Instance config to parse.
 * @param int $id (Optional) Config index of the given instance.
 * 
 * @return array|null null on a valid config, otherwise a list of errors.
 */
function udpbr_parse_instance_config(?array &$config, ?int $id = null): null|array {
	$input_errors = [];
	$temp_config_interfaces = null;
	
	// Check for invalid arguments
	if (empty($config)) {
		$input_errors[] = 'Invalid configuration.';
		return $input_errors;
	}

	// Validate interfaces
	if (isset($config['interfaces']) && is_string($config['interfaces'])) {
		$temp_config_interfaces = explode(',', $config['interfaces']);
		if (count($temp_config_interfaces) < 2) {
			unset($temp_config_interfaces);
			$input_errors[] = 'At least two interfaces must be selected.';
		} elseif (!empty(array_diff_key($temp_config_interfaces, array_keys(udpbr_get_interfaces())))) {
			$input_errors[] = 'Invalid interface(s) selected.';
		}
	} else {
		$input_errors[] = 'At least two interfaces must be selected.';
	}

	// Validate port
	if (isset($config['port']) && is_numericint($config['port'])) {
		if (!($config['port'] >= 1 && $config['port'] <= 65535)) {
			$input_errors[] = 'Invalid port number given.';
		}
	} else {
		$input_errors[] = 'A port number from 1 to 65535 is required.';
	}

	// Validate spoof option
	if (isset($config['spoof']) && is_numericint($config['spoof'])) {
		if (!in_array($config['spoof'], [0,1,2])) {
			$input_errors[] = 'Invalid spoof option selected.';
		}
	}

	// Validate multicast address
	if (!empty($config['multicast']) && !is_ipaddr($config['multicast'])) {
		$input_errors[] = 'Invalid multicast IP address given.';
	}

	// Check for duplicates
	if (isset($config['enable']) && isset($temp_config_interfaces)) {
		foreach (udpbr_get_instance_config() as $instance_id => $instance) {
			if (($id !== $instance_id) && isset($instance['enable']) && is_string($instance['interfaces'])) {
				if (($config['port'] == $instance['port']) && !empty(array_intersect($temp_config_interfaces, explode(',', $instance['interfaces'])))) {
					$input_errors[] = 'Duplicate port found on an instance with overlaping interfaces.';
				}
			}
		}
	}

	// Build the configuration with validated values
	if (empty($input_errors)) {
		$temp_config = [];

		// Optional settings
		if (isset($config['enable'])) {
			$temp_config['enable'] = true;
		}
		if (!empty($config['description']) || ($config['description'] === 0)) {
			$temp_config['description'] = htmlspecialchars($config['description']);
		}
		if (!empty($config['multicast'])) {
			$temp_config['multicast'] = htmlspecialchars($config['multicast']);
		}
		
		// Required settings
		$temp_config['interfaces'] = htmlspecialchars($config['interfaces']);
		$temp_config['spoof'] = intval($config['spoof']);
		$temp_config['port'] = intval($config['port']);

		$config = $temp_config;
		return null;
	}

	return $input_errors;
}

/**
 * Get the full package configuration.
 * 
 * @return array|null Package configuration if it exist.
 */
function udpbr_get_config(): array|null {
	global $udpbr_global;

	return config_get_path($udpbr_global['config_path']);
}

/**
 * Get a specific instance configuration. If the instance ID is omitted,
 * get the configuration for all instances.
 * 
 * @param int $id (Optional) Config index for a specific instance.
 * 
 * @return array|null Instance(s) configuration if any exist.
 */
function udpbr_get_instance_config(int $id = null): array|null {
	global $udpbr_global;

	// config for specific instance
	if (isset($id)) {
		return config_get_path("{$udpbr_global['config_path']}/item/{$id}");
	}

	// config for all instances
	return config_get_path("{$udpbr_global['config_path']}/item");
}

/**
 * Writes all configuration; includes general and instance settings.
 * 
 * @param array $config All settings.
 */
function udpbr_set_config(array $config): void {
	global $udpbr_global;

	// Save general settings
	if (isset($config['enable'])) {
		// Must not be empty for compatibility with is_service_enabled()
		config_set_path("{$udpbr_global['config_path']}/enable", 'yes');
	} else {
		config_del_path("{$udpbr_global['config_path']}/enable");
	}
	if (isset($config['carpstatusvid'])) {
		config_set_path("{$udpbr_global['config_path']}/carpstatusvid", $config['carpstatusvid']);
	} else {
		config_del_path("{$udpbr_global['config_path']}/carpstatusvid");
	}

	// Save instance settings
	if (is_array($config['item']) && (count($config['item']) > 0)) {
		// reindex the instances
		$config['item'] = array_values($config['item']);
		config_set_path("{$udpbr_global['config_path']}/item", $config['item']);
	} else {
		config_del_path("{$udpbr_global['config_path']}/item");
	}

	udpbr_log(gettext('Saved settings.'), LOG_INFO, true);
}

/**
 * Writes the configuration for an instance.
 * 
 * Does not validate data.
 * 
 * @param array $config Instance configuration.
 * @param int $id (Optional) Configuration item to replace.
 */
function udpbr_set_instance_config(array $config, int $id = null): void {
	global $udpbr_global;

	if (isset($id) && config_get_path("{$udpbr_global['config_path']}/item/{$id}")) {
		config_set_path("{$udpbr_global['config_path']}/item/{$id}", $config);
		udpbr_log(gettext('Saved instance settings.'), LOG_INFO, true);
	} else {
		config_set_path("{$udpbr_global['config_path']}/item/", $config);
		udpbr_log(gettext('Added new instance.'), LOG_INFO, true);
	}
}

/**
 * Delete one or more instances.
 * 
 * @param array $instance_ids A list of instance config IDs.
 * 
 * @return bool true if an instance was deleted, false otherwise.
 */
function udpbr_del_instance_config(array $instance_ids): bool {
	global $udpbr_global;

	$deleted_count = 0;
	foreach ($instance_ids as $id) {
		if (is_numericint($id) && (config_del_path("{$udpbr_global['config_path']}/item/" . intval($id)) !== null)) {
			$deleted_count++;
		}
	}

	if ($deleted_count > 0) {
		udpbr_log(gettext("Deleted {$deleted_count} instance(s)."), LOG_INFO, true);
		return true;
	}

	return false;
}
