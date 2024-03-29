<?php
/*
 * udpbroadcastrelay.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2023-2024 Rubicon Communications, LLC (Netgate)
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


require_once('config.inc');
require_once('functions.inc');
require_once('util.inc');
require_once('service-utils.inc');
require_once('interfaces_fast.inc');

if (!isset($udpbr_strings)) {
	$udpbr_strings = array(
	    'rc_filename' => 'udpbroadcastrelay.sh',
	    'rc'          => '/usr/local/etc/rc.d/udpbroadcastrelay.sh',
	    'daemon'      => '/usr/local/sbin/udpbroadcastrelay',
	    'services'    => '/usr/local/etc/udpbroadcastrelay/services/',
		'svc_start'   => gettext('Starting service') . ' udpbroadcastrelay',
		'svc_stop'    => gettext('Stopping service') . ' udpbroadcastrelay'
	);
}

/**
 * Validates the given settings.
 * 
 * @param array $settings Settings to validate; can be all settings, or a single instance.
 * @param bool $is_instance If settings is for a single instance, check for duplicate instances
 * @param int $idx (Optional) Configuration array index of the given instance
 * @param array $available_interfaces (Optional) Validate settings based on the given interface array keyed by friendly name
 * 
 * @return array List of strings describing the validation errors
 * @return false No errors found; settings are valid.
 * @return null No settings given to check.
 */
function udpbr_validate_config(array $settings, bool $is_instance, ?int $idx = null, array $available_interfaces = []): array|false|null {
	if (empty($settings)) {
		return null;
	}

	if ($is_instance) {
		$pconfig = array('item' => array());
		$pconfig['item'][] = $settings;
	} else {
		$pconfig = $settings;
	}

	$input_errors = array();
	$input_errors_prefix = gettext('General: ');
	// Validate service 'Track CARP Status' option
	if (!empty($pconfig['carpstatusvid']) && $pconfig['carpstatusvid'] != 'none') {
		$available_carpstatusvid = udpbr_get_carp_list();
		if (!array_key_exists($pconfig['carpstatusvid'], $available_carpstatusvid)) {
			$input_errors[] = sprintf('%1$s%2$s', $input_errors_prefix, gettext('Invalid "Track CARP Status" option.'));
		}
	}

	if (!empty($pconfig['item']) && is_array($pconfig['item'])) {
		// Check for duplicate instances when validating instance settings
		if ($is_instance) {
			$saved_instances = config_get_path('installedpackages/udpbroadcastrelay/config/0/item', []);
		}

		if (empty($available_interfaces)) {
			$available_interfaces = udpbr_get_interfaces_sorted();
		}
		foreach ($pconfig['item'] as $pidx => $pinstance) {
			$input_errors_prefix = "$idx";
			if (!$is_instance) {
				$idx = $pidx;
				$input_errors_prefix = "$pidx";
			} elseif (null === $idx) {
				$input_errors_prefix = 'new';
			}
			$input_errors_prefix = sprintf(gettext('Instance [%s]: '), $input_errors_prefix);

			// Validate instance ID
			if (!empty($pinstance['id'])) {
				if (!($pinstance['id'] >= 1 && $pinstance['id'] <= 63)) {
					$input_errors[] = sprintf('%1$s%2$s', $input_errors_prefix, gettext('Invalid instance ID given.'));
				}
			} else {
				$input_errors[] = sprintf('%1$s%2$s', $input_errors_prefix, gettext('An instance ID number from 1 to 63 is required.'));
			}

			// Validate instance port
			if (!empty($pinstance['port'])) {
				if (!($pinstance['port'] >= 1 && $pinstance['port'] <= 65535)) {
					$input_errors[] = sprintf('%1$s%2$s', $input_errors_prefix, gettext('Invalid port number given.'));
				}
			} else {
				$input_errors[] = sprintf('%1$s%2$s', $input_errors_prefix, gettext('A port number from 1 to 65535 is required.'));
			}

			// Validate instance selected interfaces
			$interfaces = explode(',', $pinstance['interfaces']);
			if (is_array($interfaces) && count($interfaces) >= 2) {
				if (((bool) array_intersect_key(array_flip($interfaces), $available_interfaces)) == false) {
					$input_errors[] = sprintf('%1$s%2$s', $input_errors_prefix, gettext('Invalid interface(s) selected.'));
				}
			} else {
				$input_errors[] = sprintf('%1$s%2$s', $input_errors_prefix, gettext('At least two interfaces must be selected.'));
			}

			// Validate instance spoof option
			if (!empty($pinstance['spoof']) || $pinstance['spoof'] == '0') {
				if (!in_array($pinstance['spoof'], array(0,1,2))) {
					$input_errors[] = sprintf('%1$s%2$s', $input_errors_prefix, gettext('Invalid spoof option selected.'));
				}
			}

			// Validate instance multicast address
			if (!empty($pinstance['multicast'])) {
				if (!is_ipaddr($pinstance['multicast'])) {
					$input_errors[] = sprintf('%1$s%2$s', $input_errors_prefix, gettext('Invalid multicast IP address given.'));
				}
			}

			// Check for duplicates
			if ($is_instance && isset($pinstance['enable']) && $idx !== null) {
				$pinstance_interfaces = explode(',', $pinstance['interfaces']);
				foreach ($saved_instances as $saved_instance_idx => $saved_instance) {
					$input_errors_prefix = sprintf(gettext('Instance [%s]: '), $saved_instance_idx);
					if (isset($saved_instance['enable']) && $idx != $saved_instance_idx) {
						if ($pinstance['id'] == $saved_instance['id']) {
							$input_errors[] = sprintf('%1$s%2$s', $input_errors_prefix, gettext('Duplicate ID found on another instance.'));
						}
						if ($pinstance['id'] != $saved_instance['id'] && $pinstance['port'] == $saved_instance['port'] &&
						    !empty(array_intersect($pinstance_interfaces, explode(',', $saved_instance['interfaces'])))) {
							$input_errors[] = sprintf('%1$s%2$s', $input_errors_prefix, gettext('Duplicate port found on an instance with overlaping interfaces.'));
						}
					}
				}
			}
		}
	}

	return (empty($input_errors) ? false : $input_errors);
}

/**
 * Generates the shell command string needed to run the given instance configuraiton.
 * 
 * @param array $instance Configuration for a single instance
 * 
 * @return string Full shell command
 */
function udpbr_get_command(array $instance): string {
	global $udpbr_strings;
	$interfaces = explode(',', $instance['interfaces']);

	$command = sprintf('%1$s --id %2$s --port %3$s', $udpbr_strings['daemon'], $instance['id'], $instance['port']);
	foreach ($interfaces as $value) {
		$command .= sprintf(' --dev %s', $value);
	}
	if (!empty($instance['multicast'])) {
		$command .= sprintf(' --multicast %s', $instance['multicast']);
	}
	if ($instance['spoof'] == '1') {
		$command .= ' -s 1.1.1.1';
	} elseif ($instance['spoof'] == '2') {
		$command .= ' -s 1.1.1.2';
	}
	$command .= ' -f';

	return escapeshellcmd($command);
}

/**
 * Initializes the configuration path; creates the service script, and starts/stops the service as needed.
 */
function udpbr_resync(): void {
	global $udpbr_strings;
	$hd_get = function (string $hd_get_data): string {return gettext($hd_get_data);};
	$cpath = 'installedpackages/udpbroadcastrelay/config/0';

	if (is_service_running('udpbroadcastrelay')) {
		syslog(LOG_NOTICE, $udpbr_strings['svc_stop']);
		stop_service('udpbroadcastrelay');
	}

	$run_commands = array();
	$instances = udpbr_get_settings(true);
	if (!empty($instances)) {
		$available_interfaces = udpbr_get_interfaces_sorted();
		$available_interfaces_ports = array_flip(convert_real_interface_to_friendly_interface_name_fast());
		foreach ($instances as $instance_idx => $instance) {
			if (empty($instance)) {
				continue;
			}

			$input_errors = udpbr_validate_config($instance, true, $instance_idx, $available_interfaces);
			if (is_array($input_errors)) {
				syslog(LOG_ERR, gettext('ERROR validating UDP Broadcast Relay settings: ') . implode('; ', $input_errors));
				continue;
			}

			if (!isset($instance['enable'])) {
				continue;
			}

			// Use the interface's real name instead of its friendly name
			$instance_interfaces = array();
			foreach (explode(',', $instance['interfaces']) as $i) {
				if (array_key_exists($i, $available_interfaces_ports)) {
					$instance_interfaces[] = $available_interfaces_ports[$i];
				} else {
					continue 2;
				}
			}
			$instance['interfaces'] = implode(',', $instance_interfaces);

			$run_command = udpbr_get_command($instance);
			if (!empty($run_command)) {
				$run_script = <<<SSCRIPT
				        : > "\${UDPBRTEMP}"
				        {$run_command} 2>\$UDPBRTEMP >/dev/null
				        ERRORMSG=\$(cat \${UDPBRTEMP})
				        if [ "\$ERRORMSG" != '' ]; then
				            /usr/bin/logger -t udpbroadcastrelay "{$hd_get('Failed to start instance')}: \${ERRORMSG}"
				        fi
				
				SSCRIPT;

				$run_commands[] = $run_script;
			}
		}
	}
	if (empty($run_commands) || !config_path_enabled($cpath)) {
		unlink_if_exists($udpbr_strings['rc']);
		return;
	}

	/* Write the rc script */
	$start = <<<BSSCRIPT
	ERRORMSG=''
	    UDPBRTEMP=$(mktemp /tmp/udpbr_tmp.XXXXXX) || ERRORMSG="{$hd_get('Could not create temporary file')}"
	    if [ "\$ERRORMSG" = '' ]; then

	BSSCRIPT;
	$start .= implode(PHP_EOL, $run_commands);
	$start .= <<<ESSCRIPT
	    rm -f "\${UDPBRTEMP}"
	    else
	        /usr/bin/logger -t udpbroadcastrelay "{$hd_get('Failed to start service')}: \${ERRORMSG}"
	    fi
	ESSCRIPT;

	$stop = '/usr/bin/killall -q udpbroadcastrelay; rm -f /tmp/udpbr_tmp.*';
	write_rcfile(array(
		'file' => $udpbr_strings['rc_filename'],
		'start' => $start,
		'stop' => $stop
		)
	);

	if (platform_booting()) {
		return;
	}

	if (config_path_enabled($cpath)) {
		/* Start/Restart UDP Broadcast Relay, if a CARP VIP is set, check its status and act appropriately. */
		$carpstatusvid = config_get_path($cpath . '/carpstatusvid');
		if (!empty($carpstatusvid) || $carpstatusvid != 'none') {
			$status = get_carp_interface_status($carpstatusvid);
			switch (strtoupper($status)) {
				// Stop the service if the VIP is in BACKUP or INIT state.
				case 'BACKUP':
				case 'INIT':
					syslog(LOG_NOTICE, $udpbr_strings['svc_stop'] . ' (CARP BACKUP/INIT)');
					stop_service('udpbroadcastrelay');
					break;
				// Start the service if the VIP is MASTER state.
				case 'MASTER':
				// Assume it's up if the status can't be determined.
				default:
					syslog(LOG_NOTICE, $udpbr_strings['svc_start']);
					start_service('udpbroadcastrelay');
					break;
			}
		} else {
			syslog(LOG_NOTICE, $udpbr_strings['svc_start']);
			start_service('udpbroadcastrelay');
		}
	}
}

/**
 * Stops the service and removes the service script.
 */
function udpbr_deinstall(): void {
	global $udpbr_strings;
	if (is_service_running('udpbroadcastrelay')) {
		syslog(LOG_NOTICE, $udpbr_strings['svc_stop']);
		stop_service('udpbroadcastrelay');
	}

	unlink_if_exists($udpbr_strings['rc']);
}

/**
 * Get the available CARP VIPs on the system.
 * 
 * @return array List of strings keyed by the VIP ID
 */
function udpbr_get_carp_list(): array {
	$list = array();
	$list['none'] = 'none';
	$carplist = get_configured_vip_list('all', VIP_CARP);
	foreach ($carplist as $vid => $vaddr) {
		$vip = get_configured_vip($vid);
		$list[$vid] = "{$vaddr} (vhid: {$vip['vhid']})";
	}
	return ($list);
}

/**
 * Starts or stops the service based on the CARP transition
 * 
 * @param array $pluginparams List of plugin parameters
 */
function udpbroadcastrelay_plugin_carp(array $pluginparams): void {
	global $udpbr_strings;
	$carpstatusvid = config_get_path('installedpackages/udpbroadcastrelay/config/0/carpstatusvid');

	/* If there is no "Track CARP Status" IP address selected, then stop */
	if (empty($carpstatusvid) || $carpstatusvid == 'none') {
		return;
	}
	/* Locate the VIP and match it against the configured "Track CARP Status" IP address */
	list($vhid, $iface) = explode("@", trim($pluginparams['interface']));
	$friendly = convert_real_interface_to_friendly_interface_name($iface);
	$vip = get_configured_vip($carpstatusvid);
	if (($vip['vhid'] != $vhid) || ($vip['interface'] != $friendly)) {
		/* This is not the VIP we're looking for, move along. */
		return;
	}
	/* Start or stop the service as needed based on the CARP transition. */
	if ($pluginparams['event'] == 'rc.carpmaster') {
		syslog(LOG_NOTICE, $udpbr_strings['svc_start']);
		start_service('udpbroadcastrelay');
	} elseif ($pluginparams['event'] == 'rc.carpbackup') {
		syslog(LOG_NOTICE, $udpbr_strings['svc_stop'] . ' (CARP BACKUP/INIT)');
		stop_service('udpbroadcastrelay');
	}
}

/**
 * Generates a list of interfaces and their descriptions with port names, sorted by their friendly names.
 * For example: 'lan' => 'LAN (igb1)' or simply 'lan' => 'LAN'
 * 
 * @param bool $description_only (Optional) When true, excludes the port name from the interface description
 * 
 * @return array List of interfaces and their description, keyed by the interface's friendly name.
 */
function udpbr_get_interfaces_sorted(bool $description_only = false): array {
	// Get all interfaces and their descriptions
	$i_ports = get_interface_arr();
	$i_names = convert_real_interface_to_friendly_interface_name_fast();
	$i_descriptions = get_configured_interface_with_descr(true);

	/* Group interfaces and their descriptions in a consistent order */
	foreach ($i_names as $i => $n) {
		if (array_search($i, $i_ports, true) === false) {
			// Ignore interfaces that are not found on the system
			continue;
		}
		$i_list_assigned[$n] = $i;
	}
	$i_list_assigned_append = $i_list_assigned;
	foreach ($i_ports as $i) {
		if (in_array($i, array('pfsync0', 'pflog0', 'enc0', 'lo0'))) {
			// Ignore special interfaces
			continue;
		}

		$append = false;
		$assigned = false;
		$description = sprintf(gettext('unassigned (%s)'), $i);

		if (preg_match('/(ovpn[sc]\d+/', $i)) {
			$append = true;
		}

		// Set the interface description
		if (!$assigned && array_key_exists($i, $i_names)) {
			if (array_key_exists($i_names[$i], $i_descriptions)) {
				$assigned = true;
				if ($description_only) {
					$description = sprintf('%1$s', $i_descriptions[$i_names[$i]]);
				} else {
					$description = sprintf('%1$s (%2$s)', $i_descriptions[$i_names[$i]], $i);
				}
			}
		}

		// Save the interface to the respective group
		if ($assigned && (get_interface_ip($i_names[$i]) || get_interface_ipv6($i_names[$i]))) {
			if ($append) {
				$i_list_assigned_append[$i_names[$i]] = $description;
				unset($i_list_assigned[$i_names[$i]]);
			} else {
				$i_list_assigned[$i_names[$i]] = $description;
				unset($i_list_assigned_append[$i_names[$i]]);
			}
		} else {
			unset($i_list_assigned[$i_names[$i]]);
			unset($i_list_assigned_append[$i_names[$i]]);
		}
	}

	/* return ordered interface list */
	return array_merge($i_list_assigned, $i_list_assigned_append);
}

/**
 * Fetches the configuration for the service and or instance(s).
 * 
 * @param bool $instancesonly (Optional) When true, returns a list of all instances configuration
 * @param int $idx (Optional) Return the instance stored at the given configuration array index
 * 
 * @return array Empty when no instances are configured
 * @return array All configuration
 * @return array List of all instances configuration
 * @return array Instance configuration in the given configuration array index
 */
function udpbr_get_settings(bool $instancesonly = false, ?int $idx = null): array {
	$cpath = 'installedpackages/udpbroadcastrelay/config/0';

	// Return all settings config
	if (!$instancesonly) {
		return config_get_path($cpath, []);
	}

	// Return all instances config
	if (null === $idx) {
		return config_get_path($cpath . '/item', []);
	}

	// Return the instance config for the given idx
	return config_get_path($cpath . "/item/{$idx}", []);
}

/**
 * Writes the configuration for the given instance array, and optionally removes it.
 * 
 * @param array $instance Configuration for a single instance. Set as null when removing.
 * @param bool $is_new_entry Signifies to save the given instance as a new entry
 * @param int $idx (Optional) Configuration array index of instance to update or remove
 */
function udpbr_set_instance(?array $instance, bool $is_new_entry, ?int $idx = null): void {
	// Instances config path
	$cpath = 'installedpackages/udpbroadcastrelay/config/0/item';

	if (!empty($instance) && $is_new_entry) {
		// Save a new instance config
		$pkg_instances_config = config_get_path($cpath, []);
		$pkg_instances_config[] = $instance;
		config_set_path($cpath, $pkg_instances_config);
	} elseif (null !== $idx) {
		$cpath = sprintf('%1$s/%2$s', $cpath, $idx);
		if (!empty($instance)) {
			// Update existing instance config
			if (!empty(config_get_path($cpath))) {
				config_set_path($cpath, $instance);
			}
		} else {
			// Remove existing instance config
			if (!empty(config_get_path($cpath))) {
				config_del_path($cpath);
			}
		}
	}
}

/**
 * Writes the configuration for service's general settings. Does not include instance configuration.
 * 
 * @param array $settings List of general settings
 */
function udpbr_set_settings(array $settings): void {
	$cpath = 'installedpackages/udpbroadcastrelay/config/0';

	// Save the general settings
	if (isset($settings['enable'])) {
		// Must not be empty for compatibility with is_service_enabled()
		config_set_path($cpath . '/enable', 'yes');
	} else {
		config_del_path($cpath . '/enable');
	}
	if (isset($settings['carpstatusvid'])) {
		config_set_path($cpath . '/carpstatusvid', $settings['carpstatusvid']);
	}
}

?>