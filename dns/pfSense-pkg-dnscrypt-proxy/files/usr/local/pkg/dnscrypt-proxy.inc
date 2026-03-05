<?php
/*
 * dnscrypt-proxy.inc
 *
 * for pfSense
 * Copyright (c) 2026 nopoz (https://github.com/nopoz)
 * SPDX-License-Identifier: ISC
 *
 * Permission to use, copy, modify, and/or distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

require_once("config.inc");
require_once("functions.inc");
require_once("util.inc");
require_once("service-utils.inc");

define('DNSCRYPT_PROXY_BASE', '/usr/local/etc/dnscrypt-proxy');
define('DNSCRYPT_PROXY_BINARY', '/usr/local/bin/dnscrypt-proxy');
define('DNSCRYPT_PROXY_RCFILE', '/usr/local/etc/rc.d/dnscrypt-proxy.sh');
define('DNSCRYPT_PROXY_CONFIG', DNSCRYPT_PROXY_BASE . '/dnscrypt-proxy.toml');
define('DNSCRYPT_PROXY_LOGDIR', '/var/log/dnscrypt-proxy');
define('DNSCRYPT_PROXY_BUNDLED_DIR', '/usr/local/bin/dnscrypt-proxy-bin');
define('DNSCRYPT_PROXY_VERSION', '2.1.15');

/**
 * Get package configuration
 */
function dnscrypt_proxy_get_config() {
	return config_get_path('installedpackages/dnscryptproxy/config/0', []);
}

/**
 * Get the package version from pkg info
 */
function dnscrypt_proxy_get_pkg_version() {
	$output = shell_exec("pkg query '%v' pfSense-pkg-dnscrypt-proxy 2>/dev/null");
	if ($output) {
		return trim($output);
	}
	return 'unknown';
}

/**
 * Get the installed dnscrypt-proxy binary version
 * Tries to get from binary, falls back to bundled version constant
 */
function dnscrypt_proxy_get_version() {
	$binary = DNSCRYPT_PROXY_BINARY;
	if (file_exists($binary) && is_executable($binary)) {
		$output = shell_exec("{$binary} --version 2>/dev/null");
		if ($output) {
			// Output is like "dnscrypt-proxy 2.1.5"
			$output = trim($output);
			if (preg_match('/(\d+\.\d+\.\d+)/', $output, $matches)) {
				return $matches[1];
			}
		}
	}
	// Fall back to bundled version
	return DNSCRYPT_PROXY_VERSION;
}

/**
 * Check if dnscrypt-proxy service is running
 */
function dnscrypt_proxy_is_running() {
	$output = shell_exec("pgrep -x dnscrypt-proxy 2>/dev/null");
	return !empty(trim($output));
}

/**
 * Check if service is enabled
 */
function dnscrypt_proxy_enabled() {
	$pkg_config = dnscrypt_proxy_get_config();
	return isset($pkg_config['enable']) && $pkg_config['enable'] == 'on';
}

/**
 * Convert interface names to listen addresses
 * Returns array of IP:port strings for TOML config
 */
function dnscrypt_proxy_get_listen_addresses($interfaces, $port) {
	$addresses = array();
	$port = !empty($port) ? $port : '5300';

	if (empty($interfaces)) {
		// Default to localhost
		$addresses[] = "127.0.0.1:{$port}";
		return $addresses;
	}

	// Handle comma-separated list from multi-select
	$iface_list = explode(',', $interfaces);

	foreach ($iface_list as $iface) {
		$iface = trim($iface);
		if (empty($iface)) {
			continue;
		}

		if ($iface == 'lo0' || $iface == 'localhost') {
			// Loopback interface
			$addresses[] = "127.0.0.1:{$port}";
		} elseif ($iface == 'all') {
			// All interfaces
			$addresses[] = "0.0.0.0:{$port}";
			$addresses[] = "[::]:{$port}";
		} else {
			// Get IP address for the interface
			$ipv4 = get_interface_ip($iface);
			if (is_ipaddrv4($ipv4)) {
				$addresses[] = "{$ipv4}:{$port}";
			}
			$ipv6 = get_interface_ipv6($iface);
			if (is_ipaddrv6($ipv6)) {
				$addresses[] = "[{$ipv6}]:{$port}";
			}
		}
	}

	// Fallback to localhost if no valid addresses found
	if (empty($addresses)) {
		$addresses[] = "127.0.0.1:{$port}";
	}

	return array_unique($addresses);
}

/**
 * Get default configuration values
 */
function dnscrypt_proxy_get_defaults() {
	return array(
		'enable' => '',
		'listen_interfaces' => 'lo0',
		'listen_port' => '5300',
		'max_clients' => '250',
		'bootstrap_resolvers' => '9.9.9.11:53, 8.8.8.8:53',
		'ignore_system_dns' => 'on',
		'timeout' => '5000',
		'ipv4_servers' => 'on',
		'ipv6_servers' => '',
		'dnscrypt_servers' => 'on',
		'doh_servers' => 'on',
		'require_dnssec' => '',
		'require_nolog' => 'on',
		'require_nofilter' => 'on',
		'server_selection_mode' => 'auto',
		'cache' => 'on',
		'cache_size' => '4096',
		'cache_min_ttl' => '2400',
		'cache_max_ttl' => '86400',
		'block_ipv6' => '',
		'block_unqualified' => 'on',
		'block_undelegated' => 'on',
		'log_level' => '2',
		'use_syslog' => 'on',
		'log_file' => '',
		'query_log' => '',
		'query_log_file' => '/var/log/dnscrypt-proxy/query.log',
		'force_tcp' => '',
		'http3' => '',
		'keepalive' => '30',
		'lb_strategy' => 'p2',
		'netprobe_timeout' => '60',
		'netprobe_address' => '9.9.9.9:53',
		'offline_mode' => '',
		'odoh_servers' => '',
		'dnscrypt_ephemeral_keys' => '',
		'tls_disable_session_tickets' => '',
		'cert_refresh_delay' => '240',
		'cache_neg_min_ttl' => '60',
		'cache_neg_max_ttl' => '600',
		'blocked_query_response' => 'hinfo',
		'reject_ttl' => '10',
		'log_files_max_size' => '10',
		'log_files_max_age' => '7',
		'log_files_max_backups' => '1',
		'anon_dns_enable' => '',
		'anon_dns_routes' => '',
		'anon_dns_skip_incompatible' => 'on',
		'anon_dns_direct_cert_fallback' => '',
		'custom_toml' => ''
	);
}

/**
 * Reset configuration to defaults
 */
function dnscrypt_proxy_reset_config() {
	$defaults = dnscrypt_proxy_get_defaults();
	config_set_path('installedpackages/dnscryptproxy/config/0', $defaults);
	write_config("[dnscrypt-proxy] Configuration reset to defaults");
	log_error("[dnscrypt-proxy] Configuration has been reset to defaults");
}

/**
 * List of all config fields across all tabs
 */
function dnscrypt_proxy_get_all_fields() {
	return array(
		// General Settings tab
		'enable', 'listen_interfaces', 'listen_port', 'max_clients',
		'bootstrap_resolvers', 'ignore_system_dns', 'timeout',
		// Server Selection tab
		'ipv4_servers', 'ipv6_servers', 'dnscrypt_servers', 'doh_servers',
		'require_dnssec', 'require_nolog', 'require_nofilter',
		'server_selection_mode', 'servers_cloudflare', 'servers_quad9',
		'servers_google', 'servers_adguard', 'servers_nextdns',
		'servers_mullvad', 'servers_cisco', 'servers_cleanbrowsing',
		'additional_servers', 'custom_servers',
		'anon_dns_enable', 'anon_dns_routes',
		'anon_dns_skip_incompatible', 'anon_dns_direct_cert_fallback',
		// Cache & Filtering tab
		'cache', 'cache_size', 'cache_min_ttl', 'cache_max_ttl',
		'block_ipv6', 'block_unqualified', 'block_undelegated',
		// Logging tab
		'log_level', 'use_syslog', 'log_file', 'query_log', 'query_log_file',
		// Lists tab
		'blocked_names', 'allowed_names', 'forwarding_rules', 'cloaking_rules',
		// Advanced tab
		'force_tcp', 'http3', 'keepalive', 'lb_strategy',
		'netprobe_timeout', 'netprobe_address', 'offline_mode',
		'odoh_servers', 'dnscrypt_ephemeral_keys', 'tls_disable_session_tickets',
		'disabled_server_names', 'cert_refresh_delay',
		'cache_neg_min_ttl', 'cache_neg_max_ttl',
		'blocked_query_response', 'reject_ttl',
		'log_files_max_size', 'log_files_max_age', 'log_files_max_backups',
		'custom_toml'
	);
}

/**
 * Get checkbox fields organized by tab
 * This is needed to properly handle unchecked checkboxes (which aren't sent in POST)
 */
function dnscrypt_proxy_get_tab_checkboxes() {
	return array(
		'general' => array('enable', 'ignore_system_dns'),
		'servers' => array('ipv4_servers', 'ipv6_servers', 'dnscrypt_servers', 'doh_servers',
		                   'require_dnssec', 'require_nolog', 'require_nofilter',
		                   'anon_dns_enable', 'anon_dns_skip_incompatible', 'anon_dns_direct_cert_fallback'),
		'cache' => array('cache', 'block_ipv6', 'block_unqualified', 'block_undelegated'),
		'logging' => array('use_syslog', 'query_log'),
		'advanced' => array('force_tcp', 'http3', 'offline_mode', 'odoh_servers',
		                    'dnscrypt_ephemeral_keys', 'tls_disable_session_tickets')
	);
}

/**
 * Get multi-select fields organized by tab
 * Multi-selects with no selection are not sent in POST (like unchecked checkboxes)
 */
function dnscrypt_proxy_get_tab_multiselects() {
	return array(
		'general' => array('listen_interfaces'),
		'servers' => array('servers_cloudflare', 'servers_quad9', 'servers_google',
		                   'servers_adguard', 'servers_nextdns', 'servers_mullvad',
		                   'servers_cisco', 'servers_cleanbrowsing'),
		'cache' => array(),
		'logging' => array(),
		'advanced' => array()
	);
}

/**
 * Detect which tab submitted the form based on a unique field from each tab
 */
function dnscrypt_proxy_detect_current_tab() {
	global $_POST;

	// Each tab has at least one unique non-checkbox field we can detect
	// We check for fields that would always be present (even if empty) when that tab submits
	$tab_markers = array(
		'general' => 'listen_port',
		'servers' => 'server_selection_mode',
		'cache' => 'cache_size',
		'logging' => 'log_level',
		'lists' => 'blocked_names',
		'advanced' => 'lb_strategy'
	);

	foreach ($tab_markers as $tab => $field) {
		// array_key_exists checks if the key exists, even if value is empty/null
		if (array_key_exists($field, $_POST)) {
			return $tab;
		}
	}

	return null;
}

/**
 * Merge saved config with existing config to preserve settings across tabs
 * Called after form save via custom_add_php_command_late
 */
function dnscrypt_proxy_save_config_late() {
	// Get what was just saved (may be incomplete - only fields from current tab)
	$saved = dnscrypt_proxy_get_config();

	// Store the values that were just saved (from current tab)
	// We need to detect which fields came from the form vs preserved
	// The saved config now has the new values, we need to merge with what
	// was in $_POST (which includes our merged values from validation)
	global $_POST;

	$all_fields = dnscrypt_proxy_get_all_fields();
	$merged = $saved;

	// For each field, if it's in $_POST but not in saved config, add it
	foreach ($all_fields as $field) {
		if (isset($_POST[$field]) && !isset($merged[$field])) {
			$merged[$field] = $_POST[$field];
		}
	}

	// Only re-save if we added fields
	if ($merged !== $saved) {
		config_set_path('installedpackages/dnscryptproxy/config/0', $merged);
		write_config("[dnscrypt-proxy] Merged config from multi-tab save");
	}
}

/**
 * Merge POST data with existing config to preserve settings across tabs
 * This populates $_POST with existing values so they're available for save_config_late
 *
 * IMPORTANT: Handles checkboxes and multi-selects specially. In HTML, unchecked
 * checkboxes and multi-selects with no selection are NOT sent in POST data.
 * We need to distinguish between:
 * 1. A field that was cleared on the current tab (should become empty)
 * 2. A field from another tab that wasn't in the form (should preserve existing value)
 */
function dnscrypt_proxy_merge_config() {
	global $_POST;

	// Get existing config
	$existing = dnscrypt_proxy_get_config();

	// Detect which tab submitted the form
	$current_tab = dnscrypt_proxy_detect_current_tab();

	// Get checkbox fields organized by tab
	$tab_checkboxes = dnscrypt_proxy_get_tab_checkboxes();

	// Get multi-select fields organized by tab
	$tab_multiselects = dnscrypt_proxy_get_tab_multiselects();

	// Get checkboxes and multi-selects for the current tab (if detected)
	$current_tab_checkboxes = array();
	$current_tab_multiselects = array();
	if ($current_tab !== null) {
		if (isset($tab_checkboxes[$current_tab])) {
			$current_tab_checkboxes = $tab_checkboxes[$current_tab];
		}
		if (isset($tab_multiselects[$current_tab])) {
			$current_tab_multiselects = $tab_multiselects[$current_tab];
		}
	}

	// Merge: POST values take precedence, but preserve existing values for fields not in POST
	foreach (dnscrypt_proxy_get_all_fields() as $field) {
		if (!isset($_POST[$field])) {
			// Field is not in POST
			if (in_array($field, $current_tab_checkboxes) || in_array($field, $current_tab_multiselects)) {
				// This is a checkbox/multi-select from the current tab that was cleared
				// Explicitly set to empty string to clear the value
				$_POST[$field] = '';
			} elseif (isset($existing[$field])) {
				// This is a field from another tab, preserve existing value
				$_POST[$field] = $existing[$field];
			}
		}
	}
}

/**
 * Validate a TOML configuration file using dnscrypt-proxy -check
 * Returns array of error strings (empty = valid)
 */
function dnscrypt_proxy_check_config($toml_content) {
	$errors = array();
	$tmp_file = '/tmp/dnscrypt-proxy-check.toml';

	file_put_contents($tmp_file, $toml_content);

	$binary = DNSCRYPT_PROXY_BINARY;
	if (!file_exists($binary) || !is_executable($binary)) {
		@unlink($tmp_file);
		return $errors; // Can't validate without binary, allow save
	}

	$output = '';
	$retval = 0;
	exec("{$binary} -check -config {$tmp_file} 2>&1", $output, $retval);

	@unlink($tmp_file);

	if ($retval !== 0) {
		// Extract ERROR/FATAL lines from output
		foreach ($output as $line) {
			if (preg_match('/\[(ERROR|FATAL)\]\s*(.+)/i', $line, $matches)) {
				$errors[] = "Config check: " . trim($matches[2]);
			}
		}
		// If no specific error lines found, include full output
		if (empty($errors)) {
			$errors[] = "Config check failed: " . implode(' ', $output);
		}
	}

	return $errors;
}

/**
 * Sanitize a value for use inside TOML single-quoted (literal) strings.
 * Strips characters that could break or escape out of the quoting context.
 */
function dnscrypt_proxy_toml_safe($value) {
	return str_replace(array("'", "\n", "\r", "\t"), '', $value);
}

/**
 * Remove top-level key definitions from a TOML string.
 * Used to strip generated keys that are being overridden by custom TOML.
 */
function dnscrypt_proxy_strip_toml_keys($toml, $keys) {
	$pattern = '/^(' . implode('|', array_map('preg_quote', $keys)) . ')\s*=.*$/m';
	return preg_replace($pattern, '', $toml);
}

/**
 * Validate user input
 */
function dnscrypt_proxy_validate($post, &$input_errors) {
	global $_POST;

	// Detect current tab BEFORE merge (merge populates $_POST with all
	// saved fields, which would confuse tab detection)
	$current_tab = dnscrypt_proxy_detect_current_tab();

	// First merge config to preserve settings from other tabs
	dnscrypt_proxy_merge_config();

	// Validate listen port
	if (!empty($post['listen_port'])) {
		if (!is_port($post['listen_port'])) {
			$input_errors[] = "Listen Port must be a valid port number (1-65535).";
		}
	}

	// Validate max clients
	if (!empty($post['max_clients'])) {
		if (!is_numericint($post['max_clients']) || $post['max_clients'] < 1) {
			$input_errors[] = "Max Clients must be a positive integer.";
		}
	}

	// Validate timeout
	if (!empty($post['timeout'])) {
		if (!is_numericint($post['timeout']) || $post['timeout'] < 100) {
			$input_errors[] = "Timeout must be at least 100 milliseconds.";
		}
	}

	// Validate cache settings
	if (!empty($post['cache_size'])) {
		if (!is_numericint($post['cache_size']) || $post['cache_size'] < 0) {
			$input_errors[] = "Cache Size must be a non-negative integer.";
		}
	}

	if (!empty($post['cache_min_ttl'])) {
		if (!is_numericint($post['cache_min_ttl']) || $post['cache_min_ttl'] < 0) {
			$input_errors[] = "Cache Min TTL must be a non-negative integer.";
		}
	}

	if (!empty($post['cache_max_ttl'])) {
		if (!is_numericint($post['cache_max_ttl']) || $post['cache_max_ttl'] < 0) {
			$input_errors[] = "Cache Max TTL must be a non-negative integer.";
		}
	}

	// Validate bootstrap resolvers format
	if (!empty($post['bootstrap_resolvers'])) {
		$resolvers = explode(',', $post['bootstrap_resolvers']);
		foreach ($resolvers as $resolver) {
			$resolver = trim($resolver);
			if (empty($resolver)) {
				continue;
			}
			// Check format: IP:port or just IP
			if (strpos($resolver, ':') !== false) {
				$parts = explode(':', $resolver);
				if (!is_ipaddr($parts[0])) {
					$input_errors[] = "Invalid bootstrap resolver IP address: {$resolver}";
				}
				if (isset($parts[1]) && !is_port($parts[1])) {
					$input_errors[] = "Invalid bootstrap resolver port: {$resolver}";
				}
			} else {
				if (!is_ipaddr($resolver)) {
					$input_errors[] = "Invalid bootstrap resolver: {$resolver}";
				}
			}
		}
	}

	// Validate advanced settings
	if (!empty($post['keepalive'])) {
		if (!is_numericint($post['keepalive']) || $post['keepalive'] < 1) {
			$input_errors[] = "HTTP Keepalive must be a positive integer.";
		}
	}

	if (isset($post['netprobe_timeout']) && $post['netprobe_timeout'] !== '') {
		if (!is_numericint($post['netprobe_timeout']) && $post['netprobe_timeout'] != '-1') {
			$input_errors[] = "Network Probe Timeout must be an integer (use -1 to disable).";
		}
	}

	if (!empty($post['netprobe_address'])) {
		// Validate IP:port format
		if (strpos($post['netprobe_address'], ':') !== false) {
			$parts = explode(':', $post['netprobe_address']);
			if (!is_ipaddr($parts[0])) {
				$input_errors[] = "Network Probe Address must have a valid IP address.";
			}
			if (isset($parts[1]) && !is_port($parts[1])) {
				$input_errors[] = "Network Probe Address must have a valid port.";
			}
		} else {
			if (!is_ipaddr($post['netprobe_address'])) {
				$input_errors[] = "Network Probe Address must be a valid IP address.";
			}
		}
	}

	if (!empty($post['cert_refresh_delay'])) {
		if (!is_numericint($post['cert_refresh_delay']) || $post['cert_refresh_delay'] < 1) {
			$input_errors[] = "Certificate Refresh Delay must be a positive integer.";
		}
	}

	if (!empty($post['cache_neg_min_ttl'])) {
		if (!is_numericint($post['cache_neg_min_ttl']) || $post['cache_neg_min_ttl'] < 0) {
			$input_errors[] = "Negative Cache Min TTL must be a non-negative integer.";
		}
	}

	if (!empty($post['cache_neg_max_ttl'])) {
		if (!is_numericint($post['cache_neg_max_ttl']) || $post['cache_neg_max_ttl'] < 0) {
			$input_errors[] = "Negative Cache Max TTL must be a non-negative integer.";
		}
	}

	if (!empty($post['reject_ttl'])) {
		if (!is_numericint($post['reject_ttl']) || $post['reject_ttl'] < 0) {
			$input_errors[] = "Reject TTL must be a non-negative integer.";
		}
	}

	if (!empty($post['log_files_max_size'])) {
		if (!is_numericint($post['log_files_max_size']) || $post['log_files_max_size'] < 1) {
			$input_errors[] = "Max Log File Size must be a positive integer.";
		}
	}

	if (!empty($post['log_files_max_age'])) {
		if (!is_numericint($post['log_files_max_age']) || $post['log_files_max_age'] < 1) {
			$input_errors[] = "Max Log Age must be a positive integer.";
		}
	}

	if (!empty($post['log_files_max_backups'])) {
		if (!is_numericint($post['log_files_max_backups']) || $post['log_files_max_backups'] < 0) {
			$input_errors[] = "Max Log Backups must be a non-negative integer.";
		}
	}

	// Validate file paths (must be absolute, safe characters, no traversal)
	foreach (array('log_file', 'query_log_file') as $path_field) {
		if (!empty($post[$path_field])) {
			if (!preg_match('#^/[a-zA-Z0-9/._-]+$#', $post[$path_field]) || strpos($post[$path_field], '..') !== false) {
				$input_errors[] = "Invalid file path for {$path_field}. Must be an absolute path with safe characters and no '..' traversal.";
			}
		}
	}

	// Validate server name lists (comma-separated alphanumeric with ._-)
	foreach (array('additional_servers', 'disabled_server_names') as $server_field) {
		if (!empty($post[$server_field])) {
			$names = array_map('trim', explode(',', $post[$server_field]));
			foreach ($names as $name) {
				if (!empty($name) && !preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
					$input_errors[] = "Invalid server name '{$name}' in {$server_field}. Only alphanumeric characters, dots, hyphens, and underscores are allowed.";
				}
			}
		}
	}

	// Validate enum selects
	if (!empty($post['lb_strategy']) && !in_array($post['lb_strategy'], array('p2', 'ph', 'fastest', 'random'))) {
		$input_errors[] = "Invalid load balancing strategy.";
	}
	if (!empty($post['blocked_query_response']) && !in_array($post['blocked_query_response'], array('hinfo', 'refused', 'empty'))) {
		$input_errors[] = "Invalid blocked query response type.";
	}
	if (!empty($post['log_level']) && !in_array($post['log_level'], array('0', '1', '2', '3', '4', '5', '6'))) {
		$input_errors[] = "Invalid log level. Must be 0-6.";
	}

	// Validate custom TOML options (only when advanced tab is submitting)
	if ($current_tab == 'advanced' && !empty($post['custom_toml'])) {
		$custom_raw = $post['custom_toml'];
		// Basic syntax check: each non-empty, non-comment line must contain '='
		$custom_lines = explode("\n", $custom_raw);
		foreach ($custom_lines as $line_num => $line) {
			$line = trim($line);
			if (empty($line) || strpos($line, '#') === 0) {
				continue;
			}
			// Allow TOML section headers like [section]
			if (preg_match('/^\[.+\]$/', $line)) {
				continue;
			}
			if (strpos($line, '=') === false) {
				$input_errors[] = "Custom TOML: Invalid syntax on line " . ($line_num + 1) . ": '{$line}'. Each option must contain '='.";
			}
		}

		// If basic syntax is OK, do a full config check with dnscrypt-proxy -check
		if (empty($input_errors)) {
			// Read the current live config file (if it exists) and merge custom options
			if (file_exists(DNSCRYPT_PROXY_CONFIG)) {
				$current_toml = file_get_contents(DNSCRYPT_PROXY_CONFIG);

				// Strip previous custom blocks from the live config since
				// we're about to re-add them from the new custom input
				$current_toml = preg_replace('/^# Custom options\n.*?(?=\n# DNSCrypt Proxy configuration|\z)/ms', '', $current_toml);
				$current_toml = preg_replace('/\n# Custom sections\n.*/s', '', $current_toml);

				// Split custom TOML into top-level keys and section blocks
				$val_top = '';
				$val_sections = '';
				$override_keys = array();
				$in_section = false;
				foreach ($custom_lines as $cline) {
					if (preg_match('/^\s*\[/', $cline)) {
						$in_section = true;
					}
					if ($in_section) {
						$val_sections .= $cline . "\n";
					} else {
						$val_top .= $cline . "\n";
						$trimmed = trim($cline);
						if (!empty($trimmed) && $trimmed[0] !== '#' && preg_match('/^([a-zA-Z0-9_]+)\s*=/', $trimmed, $km)) {
							$override_keys[] = $km[1];
						}
					}
				}

				if (!empty($override_keys)) {
					$current_toml = dnscrypt_proxy_strip_toml_keys($current_toml, $override_keys);
				}

				// Top-level overrides prepended, section blocks appended
				$test_toml = '';
				if (!empty(trim($val_top))) {
					$test_toml .= "# Custom options\n" . $val_top . "\n";
				}
				$test_toml .= $current_toml;
				if (!empty(trim($val_sections))) {
					$test_toml .= "\n# Custom sections\n" . $val_sections;
				}

				$check_errors = dnscrypt_proxy_check_config($test_toml);
				foreach ($check_errors as $err) {
					$input_errors[] = "Custom TOML: {$err}";
				}
			}
		}
	}

	// Validate anonymized DNS settings (only when servers tab is submitting,
	// since textarea base64 encoding happens after validation in pkg_edit.php
	// and values from other tabs come pre-encoded from config)
	if ($current_tab == 'servers' && isset($post['anon_dns_enable']) && $post['anon_dns_enable'] == 'on') {
		// POST textarea value is raw text (not yet base64-encoded)
		$routes_raw = !empty($post['anon_dns_routes']) ? $post['anon_dns_routes'] : '';
		if (empty(trim($routes_raw))) {
			$input_errors[] = "Anonymized DNS: At least one relay route is required. Example: * = anon-cs-austria, anon-cs-tokyo";
		} else {
			$lines = explode("\n", $routes_raw);
			foreach ($lines as $line_num => $line) {
				$line = trim($line);
				if (empty($line) || strpos($line, '#') === 0) {
					continue;
				}
				if (!preg_match('/^([a-zA-Z0-9_*.-]+)\s*=\s*(.+)$/', $line, $matches)) {
					$input_errors[] = "Anonymized DNS: Invalid route format on line " . ($line_num + 1) . ". Expected: server_name = relay1, relay2";
				} else {
					$server = trim($matches[1]);
					if (!preg_match('/^[a-zA-Z0-9._*-]+$/', $server)) {
						$input_errors[] = "Anonymized DNS: Invalid server name '{$server}' on line " . ($line_num + 1) . ".";
					}
					$relays = array_map('trim', explode(',', $matches[2]));
					foreach ($relays as $relay) {
						if (!empty($relay) && !preg_match('/^[a-zA-Z0-9._*-]+$/', $relay)) {
							$input_errors[] = "Anonymized DNS: Invalid relay name '{$relay}' on line " . ($line_num + 1) . ".";
						}
					}
				}
			}
		}
	}
}

/**
 * Install the package
 */
function dnscrypt_proxy_install() {
	// Create directories
	safe_mkdir(DNSCRYPT_PROXY_BASE);
	safe_mkdir(DNSCRYPT_PROXY_LOGDIR);
	safe_mkdir('/var/cache/dnscrypt-proxy');

	// Download and install binary
	dnscrypt_proxy_install_binary();

	// Generate initial configuration
	dnscrypt_proxy_sync();

	log_error("[dnscrypt-proxy] Package installed successfully");
}

/**
 * Install the bundled dnscrypt-proxy binary for the current architecture
 */
function dnscrypt_proxy_install_binary() {
	// Detect architecture
	$arch = php_uname('m');
	if ($arch == 'amd64' || $arch == 'x86_64') {
		$arch_suffix = 'amd64';
	} elseif ($arch == 'arm64' || $arch == 'aarch64') {
		$arch_suffix = 'arm64';
	} else {
		log_error("[dnscrypt-proxy] Unsupported architecture: {$arch}");
		return false;
	}

	$bundled_binary = DNSCRYPT_PROXY_BUNDLED_DIR . '/dnscrypt-proxy-' . $arch_suffix;

	if (!file_exists($bundled_binary)) {
		log_error("[dnscrypt-proxy] Bundled binary not found: {$bundled_binary}");
		return false;
	}

	// Copy bundled binary to installation location
	if (!copy($bundled_binary, DNSCRYPT_PROXY_BINARY)) {
		log_error("[dnscrypt-proxy] Failed to copy binary to " . DNSCRYPT_PROXY_BINARY);
		return false;
	}

	chmod(DNSCRYPT_PROXY_BINARY, 0755);
	chown(DNSCRYPT_PROXY_BINARY, 'root');
	chgrp(DNSCRYPT_PROXY_BINARY, 'wheel');

	// Store version in config
	config_set_path('installedpackages/dnscryptproxy/binary_version', DNSCRYPT_PROXY_VERSION);
	write_config("[dnscrypt-proxy] Installed version " . DNSCRYPT_PROXY_VERSION);

	log_error("[dnscrypt-proxy] Binary installed: " . DNSCRYPT_PROXY_VERSION . " ({$arch_suffix})");

	return true;
}

/**
 * Write the RC startup script using pfSense's write_rcfile()
 */
function dnscrypt_proxy_write_rcfile() {
	$rc = array();
	$rc['file'] = 'dnscrypt-proxy.sh';
	$rc['start'] = <<<'EOF'
if [ -f /usr/local/etc/dnscrypt-proxy/dnscrypt-proxy.toml ] && [ -x /usr/local/bin/dnscrypt-proxy ]; then
	/usr/bin/killall -q dnscrypt-proxy
	/usr/sbin/daemon -p /var/run/dnscrypt_proxy.pid -f /usr/local/bin/dnscrypt-proxy -config /usr/local/etc/dnscrypt-proxy/dnscrypt-proxy.toml
	/usr/bin/logger -p daemon.info -t dnscrypt-proxy "Service started"
fi
EOF;
	$rc['stop'] = <<<'EOF'
/usr/bin/killall -q dnscrypt-proxy
if [ -f /var/run/dnscrypt_proxy.pid ]; then
	rm -f /var/run/dnscrypt_proxy.pid
fi
/usr/bin/logger -p daemon.info -t dnscrypt-proxy "Service stopped"
EOF;

	write_rcfile($rc);
}

/**
 * Uninstall the package
 */
function dnscrypt_proxy_deinstall() {
	// Stop the service
	if (is_process_running("dnscrypt-proxy")) {
		mwexec("/usr/bin/killall -q dnscrypt-proxy");
	}

	// Remove files
	unlink_if_exists(DNSCRYPT_PROXY_BINARY);
	unlink_if_exists(DNSCRYPT_PROXY_RCFILE);
	unlink_if_exists(DNSCRYPT_PROXY_CONFIG);
	unlink_if_exists(DNSCRYPT_PROXY_BASE . '/blocked-names.txt');
	unlink_if_exists(DNSCRYPT_PROXY_BASE . '/allowed-names.txt');
	unlink_if_exists(DNSCRYPT_PROXY_BASE . '/forwarding-rules.txt');
	unlink_if_exists(DNSCRYPT_PROXY_BASE . '/cloaking-rules.txt');
	unlink_if_exists('/var/run/dnscrypt_proxy.pid');

	// Remove directories if empty
	@rmdir(DNSCRYPT_PROXY_BASE);
	@rmdir(DNSCRYPT_PROXY_LOGDIR);
	@rmdir('/var/cache/dnscrypt-proxy');

	log_error("[dnscrypt-proxy] Package uninstalled");
}

/**
 * Import a TOML configuration and write parsed values into config.xml.
 *
 * Parses top-level key=value pairs and known sections from the TOML content,
 * maps them to config.xml fields, and triggers a sync to regenerate the TOML
 * and restart the service.
 *
 * Returns an array of error strings (empty = success).
 */
function dnscrypt_proxy_import_toml($toml_content) {
	$errors = array();

	// Validate with dnscrypt-proxy -check first
	$check_errors = dnscrypt_proxy_check_config($toml_content);
	if (!empty($check_errors)) {
		return $check_errors;
	}

	// Parse the TOML content line by line
	$lines = explode("\n", $toml_content);
	$current_section = '';
	$top_level = array();
	$sections = array();

	foreach ($lines as $line) {
		$line = trim($line);

		// Skip empty lines and comments
		if (empty($line) || $line[0] === '#') {
			continue;
		}

		// Section header (simple, e.g. [query_log])
		if (preg_match('/^\[([a-zA-Z_][a-zA-Z0-9_]*)\]$/', $line, $m)) {
			$current_section = $m[1];
			if (!isset($sections[$current_section])) {
				$sections[$current_section] = array();
			}
			continue;
		}

		// Skip nested/complex section headers like [sources.'public-resolvers'] or [static.'name']
		if (preg_match('/^\[.+\]$/', $line)) {
			$current_section = '__nested__';
			continue;
		}

		// Key = value
		if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(.+)$/', $line, $m)) {
			$key = $m[1];
			$raw_value = trim($m[2]);

			if ($current_section === '') {
				$top_level[$key] = $raw_value;
			} elseif ($current_section !== '__nested__') {
				$sections[$current_section][$key] = $raw_value;
			}
		}
	}

	// Helper to parse a TOML value
	$parse_value = function($raw) {
		if ($raw === 'true') return true;
		if ($raw === 'false') return false;
		if (preg_match("/^'(.*)'$/s", $raw, $m)) return $m[1];
		if (preg_match('/^"(.*)"$/s', $raw, $m)) return $m[1];
		if (preg_match('/^-?\d+$/', $raw)) return $raw;
		return $raw;
	};

	// Helper to parse a TOML array into a PHP array of strings.
	// Handles both single-line and multi-line arrays.
	$parse_array = function($raw) use (&$lines) {
		$items = array();
		// Single-line: ['val1', 'val2']
		if (preg_match('/^\[(.+)\]$/s', $raw, $m)) {
			preg_match_all("/'([^']*)'|\"([^\"]*)\"/", $m[1], $matches);
			foreach ($matches[1] as $i => $v) {
				$items[] = $v !== '' ? $v : $matches[2][$i];
			}
			return $items;
		}
		// Multi-line: raw starts with '[' but no closing ']' on same line.
		// The caller already captured everything after '=' on the key's line.
		// We need to find the continuation in the original lines array.
		return $items;
	};

	// Second pass: reassemble multi-line arrays before field processing.
	// Join continuation lines for array values (lines after key = [ until ])
	$reassembled = array();
	foreach ($top_level as $key => $raw) {
		$reassembled[$key] = $raw;
	}
	// Re-scan original lines to detect multi-line arrays at top level
	$current_section_rescan = '';
	$ml_key = null;
	$ml_value = '';
	foreach ($lines as $line) {
		$line = trim($line);
		if (empty($line) || $line[0] === '#') {
			continue;
		}
		// When collecting a multi-line array, append lines until we find ']'
		if ($ml_key !== null) {
			$ml_value .= ' ' . $line;
			if (strpos($line, ']') !== false) {
				$reassembled[$ml_key] = $ml_value;
				$ml_key = null;
			}
			continue;
		}
		// Section header
		if (preg_match('/^\[.+\]$/', $line)) {
			if (preg_match('/^\[([a-zA-Z_][a-zA-Z0-9_]*)\]$/', $line)) {
				$current_section_rescan = $line;
			} else {
				$current_section_rescan = '__nested__';
			}
			continue;
		}
		// Skip lines inside sections
		if ($current_section_rescan !== '') {
			continue;
		}
		// Top-level key=value: check for multi-line array start
		if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(.+)$/', $line, $m)) {
			$raw = trim($m[2]);
			if (preg_match('/^\[/', $raw) && !preg_match('/\]/', $raw)) {
				$ml_key = $m[1];
				$ml_value = $raw;
			}
		}
	}
	if ($ml_key !== null) {
		$reassembled[$ml_key] = $ml_value;
	}
	$top_level = $reassembled;

	// Start with current config to preserve fields we don't import
	$config = dnscrypt_proxy_get_config();
	if (empty($config)) {
		$config = dnscrypt_proxy_get_defaults();
	}

	// Preserve enable state
	$saved_enable = isset($config['enable']) ? $config['enable'] : '';

	// Boolean fields: TOML true → 'on', false → ''
	$bool_fields = array(
		'ipv4_servers', 'ipv6_servers', 'dnscrypt_servers', 'doh_servers',
		'odoh_servers', 'require_dnssec', 'require_nolog', 'require_nofilter',
		'ignore_system_dns', 'force_tcp', 'http3', 'offline_mode',
		'cache', 'block_ipv6', 'block_unqualified', 'block_undelegated',
		'use_syslog', 'dnscrypt_ephemeral_keys', 'tls_disable_session_tickets'
	);

	// Numeric/string fields that map directly
	$direct_fields = array(
		'max_clients', 'timeout', 'keepalive', 'netprobe_timeout',
		'cert_refresh_delay', 'reject_ttl', 'log_level',
		'log_files_max_size', 'log_files_max_age', 'log_files_max_backups',
		'cache_size', 'cache_min_ttl', 'cache_max_ttl',
		'cache_neg_min_ttl', 'cache_neg_max_ttl'
	);

	// String fields that need quote stripping
	$string_fields = array(
		'lb_strategy', 'blocked_query_response', 'netprobe_address', 'log_file'
	);

	// Track which keys we've handled
	$handled_keys = array_merge($bool_fields, $direct_fields, $string_fields,
		array('listen_addresses', 'server_names', 'disabled_server_names',
		      'bootstrap_resolvers'));

	// Process boolean fields
	foreach ($bool_fields as $field) {
		if (isset($top_level[$field])) {
			$val = $parse_value($top_level[$field]);
			$config[$field] = ($val === true) ? 'on' : '';
		}
	}

	// Process direct numeric/string fields
	foreach ($direct_fields as $field) {
		if (isset($top_level[$field])) {
			$config[$field] = $parse_value($top_level[$field]);
		}
	}

	// Process string fields
	foreach ($string_fields as $field) {
		if (isset($top_level[$field])) {
			$config[$field] = $parse_value($top_level[$field]);
		}
	}

	// listen_addresses → extract port from first entry
	if (isset($top_level['listen_addresses'])) {
		$addrs = $parse_array($top_level['listen_addresses']);
		if (!empty($addrs)) {
			$first = $addrs[0];
			if (preg_match('/]:(\d+)$/', $first, $m)) {
				$config['listen_port'] = $m[1];
			} elseif (preg_match('/:(\d+)$/', $first, $m)) {
				$config['listen_port'] = $m[1];
			}
		}
	}

	// bootstrap_resolvers → join with ', '
	if (isset($top_level['bootstrap_resolvers'])) {
		$resolvers = $parse_array($top_level['bootstrap_resolvers']);
		if (!empty($resolvers)) {
			$config['bootstrap_resolvers'] = implode(', ', $resolvers);
		}
	}

	// server_names → set manual mode, put all into additional_servers
	// Absent server_names → auto mode
	if (isset($top_level['server_names'])) {
		$names = $parse_array($top_level['server_names']);
		if (!empty($names)) {
			$config['server_selection_mode'] = 'manual';
			$provider_fields = array(
				'servers_cloudflare', 'servers_quad9', 'servers_google',
				'servers_adguard', 'servers_nextdns', 'servers_mullvad',
				'servers_cisco', 'servers_cleanbrowsing'
			);
			foreach ($provider_fields as $pf) {
				$config[$pf] = '';
			}
			$config['additional_servers'] = implode(', ', $names);
		}
	} else {
		$config['server_selection_mode'] = 'auto';
	}

	// disabled_server_names → join with comma
	if (isset($top_level['disabled_server_names'])) {
		$disabled = $parse_array($top_level['disabled_server_names']);
		$config['disabled_server_names'] = implode(',', $disabled);
	}

	// [query_log] section
	if (isset($sections['query_log'])) {
		$config['query_log'] = 'on';
		if (isset($sections['query_log']['file'])) {
			$config['query_log_file'] = $parse_value($sections['query_log']['file']);
		}
	} else {
		$config['query_log'] = '';
	}

	// [anonymized_dns] section
	if (isset($sections['anonymized_dns'])) {
		$config['anon_dns_enable'] = 'on';
		if (isset($sections['anonymized_dns']['skip_incompatible'])) {
			$val = $parse_value($sections['anonymized_dns']['skip_incompatible']);
			$config['anon_dns_skip_incompatible'] = ($val === true) ? 'on' : '';
		}
		if (isset($sections['anonymized_dns']['direct_cert_fallback'])) {
			$val = $parse_value($sections['anonymized_dns']['direct_cert_fallback']);
			$config['anon_dns_direct_cert_fallback'] = ($val === true) ? 'on' : '';
		}
	} else {
		$config['anon_dns_enable'] = '';
	}

	// Collect unrecognized top-level keys for custom_toml.
	// Keys are already validated by dnscrypt_proxy_check_config() above,
	// so they're syntactically valid TOML that dnscrypt-proxy accepts.
	$unrecognized = array();
	foreach ($top_level as $key => $raw) {
		if (!in_array($key, $handled_keys)) {
			// Only allow keys with safe names (alphanumeric + underscore)
			if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
				$unrecognized[] = "{$key} = {$raw}";
			}
		}
	}
	if (!empty($unrecognized)) {
		$config['custom_toml'] = base64_encode(implode("\n", $unrecognized));
	} else {
		$config['custom_toml'] = '';
	}

	// Validate imported values the same way the GUI does.
	// File paths: must be absolute, safe characters, no traversal.
	foreach (array('log_file', 'query_log_file') as $path_field) {
		if (!empty($config[$path_field])) {
			if (!preg_match('#^/[a-zA-Z0-9/._-]+$#', $config[$path_field]) || strpos($config[$path_field], '..') !== false) {
				$errors[] = "Invalid file path for {$path_field}: " . htmlspecialchars($config[$path_field]);
			}
		}
	}

	// Enum fields
	if (!empty($config['lb_strategy']) && !in_array($config['lb_strategy'], array('p2', 'ph', 'fastest', 'random'))) {
		$errors[] = "Invalid load balancing strategy: " . htmlspecialchars($config['lb_strategy']);
	}
	if (!empty($config['blocked_query_response']) && !in_array($config['blocked_query_response'], array('hinfo', 'refused', 'empty'))) {
		$errors[] = "Invalid blocked query response: " . htmlspecialchars($config['blocked_query_response']);
	}
	if (isset($config['log_level']) && !in_array((string)$config['log_level'], array('0', '1', '2', '3', '4', '5', '6'))) {
		$errors[] = "Invalid log level: " . htmlspecialchars($config['log_level']);
	}

	// Numeric fields: must be integers
	$numeric_fields = array('max_clients', 'timeout', 'keepalive', 'cert_refresh_delay',
		'reject_ttl', 'cache_size', 'cache_min_ttl', 'cache_max_ttl',
		'cache_neg_min_ttl', 'cache_neg_max_ttl', 'log_files_max_size',
		'log_files_max_age', 'log_files_max_backups', 'listen_port');
	foreach ($numeric_fields as $nf) {
		if (isset($config[$nf]) && $config[$nf] !== '' && !preg_match('/^-?\d+$/', (string)$config[$nf])) {
			$errors[] = "Invalid numeric value for {$nf}: " . htmlspecialchars($config[$nf]);
		}
	}

	// Server name lists: only safe characters
	foreach (array('additional_servers', 'disabled_server_names') as $server_field) {
		if (!empty($config[$server_field])) {
			$names = array_map('trim', explode(',', $config[$server_field]));
			foreach ($names as $name) {
				if (!empty($name) && !preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
					$errors[] = "Invalid server name '{$name}' in {$server_field}.";
				}
			}
		}
	}

	// Netprobe address: IP or IP:port
	if (!empty($config['netprobe_address'])) {
		$npa = $config['netprobe_address'];
		if (!preg_match('/^[0-9a-fA-F.:]+$/', $npa)) {
			$errors[] = "Invalid network probe address: " . htmlspecialchars($npa);
		}
	}

	// If validation failed, don't write to config.xml
	if (!empty($errors)) {
		return $errors;
	}

	// Restore enable state
	$config['enable'] = $saved_enable;

	// Write to config.xml
	config_set_path('installedpackages/dnscryptproxy/config/0', $config);
	write_config("[dnscrypt-proxy] Configuration imported from TOML");

	// Regenerate TOML and restart service
	dnscrypt_proxy_sync();

	return $errors;
}

/**
 * Synchronize configuration and restart service
 */
function dnscrypt_proxy_sync() {
	// Generate TOML configuration
	dnscrypt_proxy_generate_toml();

	// Generate list files
	dnscrypt_proxy_generate_lists();

	// Validate generated config before restarting service
	if (file_exists(DNSCRYPT_PROXY_CONFIG)) {
		$check_errors = dnscrypt_proxy_check_config(file_get_contents(DNSCRYPT_PROXY_CONFIG));
		if (!empty($check_errors)) {
			log_error("[dnscrypt-proxy] Config validation failed, not restarting service: " . implode('; ', $check_errors));
			return;
		}
	}

	// Manage service state
	if (dnscrypt_proxy_enabled()) {
		// Write RC file and start/restart service
		dnscrypt_proxy_write_rcfile();
		if (!platform_booting()) {
			// Use RC file directly rather than restart_service()
			// because during initial install, the service is not
			// yet registered in config.xml (install_package_xml
			// registers it after custom_php_install_command runs).
			dnscrypt_proxy_stop();
			dnscrypt_proxy_start();
		}
	} else {
		// Stop service and remove RC file
		if (is_process_running("dnscrypt-proxy")) {
			dnscrypt_proxy_stop();
		}
		unlink_if_exists(DNSCRYPT_PROXY_RCFILE);
	}
}

/**
 * Collect selected server names from all provider fields
 */
function dnscrypt_proxy_get_selected_servers($pkg_config) {
	$servers = array();

	// Provider selection fields
	$provider_fields = array(
		'servers_cloudflare',
		'servers_quad9',
		'servers_google',
		'servers_adguard',
		'servers_nextdns',
		'servers_mullvad',
		'servers_cisco',
		'servers_cleanbrowsing'
	);

	foreach ($provider_fields as $field) {
		if (!empty($pkg_config[$field])) {
			// Handle both array and comma-separated string formats
			if (is_array($pkg_config[$field])) {
				$servers = array_merge($servers, $pkg_config[$field]);
			} else {
				$field_servers = array_map('trim', explode(',', $pkg_config[$field]));
				$servers = array_merge($servers, $field_servers);
			}
		}
	}

	// Add additional servers from text field
	if (!empty($pkg_config['additional_servers'])) {
		$additional = array_map('trim', explode(',', $pkg_config['additional_servers']));
		$servers = array_merge($servers, $additional);
	}

	// Add custom static server names (these will be defined in [static] section)
	if (!empty($pkg_config['custom_servers'])) {
		$custom_content = base64_decode($pkg_config['custom_servers']);
		$lines = explode("\n", $custom_content);
		foreach ($lines as $line) {
			$line = trim($line);
			if (empty($line) || strpos($line, '#') === 0) {
				continue;
			}
			// Parse "name = stamp" format
			if (preg_match('/^([a-zA-Z0-9_-]+)\s*=/', $line, $matches)) {
				$servers[] = $matches[1];
			}
		}
	}

	// Remove empty entries and duplicates
	$servers = array_filter($servers);
	$servers = array_unique($servers);

	return $servers;
}

/**
 * Generate static server entries for custom resolvers
 */
function dnscrypt_proxy_generate_static_servers($pkg_config) {
	$static_section = '';

	if (empty($pkg_config['custom_servers'])) {
		return $static_section;
	}

	$custom_content = base64_decode($pkg_config['custom_servers']);
	$lines = explode("\n", $custom_content);
	$has_servers = false;

	foreach ($lines as $line) {
		$line = trim($line);
		if (empty($line) || strpos($line, '#') === 0) {
			continue;
		}

		// Parse "name = stamp" format
		if (preg_match('/^([a-zA-Z0-9_-]+)\s*=\s*(sdns:\/\/\S+)/', $line, $matches)) {
			$name = $matches[1];
			$stamp = $matches[2];

			if (!$has_servers) {
				$static_section .= "\n# Custom static servers\n";
				$has_servers = true;
			}

			$static_section .= "\n[static.'{$name}']\n";
			$static_section .= "stamp = '{$stamp}'\n";
		}
	}

	return $static_section;
}

/**
 * Generate the [anonymized_dns] TOML section
 */
function dnscrypt_proxy_generate_anon_dns($pkg_config) {
	if (empty($pkg_config['anon_dns_enable']) || $pkg_config['anon_dns_enable'] != 'on') {
		return '';
	}

	$section = "\n[anonymized_dns]\n";
	$routes = array();

	// Parse routes from textarea (base64-encoded in config)
	if (!empty($pkg_config['anon_dns_routes'])) {
		$content = base64_decode($pkg_config['anon_dns_routes']);
		$lines = explode("\n", $content);
		foreach ($lines as $line) {
			$line = trim($line);
			if (empty($line) || strpos($line, '#') === 0) {
				continue;
			}
			// Parse: server_name = relay1, relay2
			if (preg_match('/^([a-zA-Z0-9_*.-]+)\s*=\s*(.+)$/', $line, $matches)) {
				$server = trim($matches[1]);
				$relays = array_map('trim', explode(',', $matches[2]));
				$relays = array_filter($relays);
				$relay_str = "'" . implode("', '", $relays) . "'";
				$routes[] = "{ server_name='{$server}', via=[{$relay_str}] }";
			}
		}
	}

	if (!empty($routes)) {
		$section .= "routes = [\n";
		$section .= "    " . implode(",\n    ", $routes) . "\n";
		$section .= "]\n";
	}

	// Options
	$skip = (isset($pkg_config['anon_dns_skip_incompatible']) && $pkg_config['anon_dns_skip_incompatible'] == 'on') ? 'true' : 'false';
	if (!isset($pkg_config['anon_dns_skip_incompatible'])) {
		$skip = 'true';
	}
	$section .= "skip_incompatible = {$skip}\n";

	if (isset($pkg_config['anon_dns_direct_cert_fallback']) && $pkg_config['anon_dns_direct_cert_fallback'] == 'on') {
		$section .= "direct_cert_fallback = true\n";
	}

	return $section;
}

/**
 * Generate the TOML configuration file
 */
function dnscrypt_proxy_generate_toml() {
	$pkg_config = dnscrypt_proxy_get_config();

	// Get values with defaults - General settings
	$listen_port = !empty($pkg_config['listen_port']) ? $pkg_config['listen_port'] : '5300';
	$listen_interfaces = !empty($pkg_config['listen_interfaces']) ? $pkg_config['listen_interfaces'] : 'lo0';
	$listen_addresses = dnscrypt_proxy_get_listen_addresses($listen_interfaces, $listen_port);
	$listen_addresses_str = "'" . implode("', '", $listen_addresses) . "'";

	$max_clients = !empty($pkg_config['max_clients']) ? $pkg_config['max_clients'] : '250';
	$timeout = !empty($pkg_config['timeout']) ? $pkg_config['timeout'] : '5000';
	$log_level = isset($pkg_config['log_level']) ? intval($pkg_config['log_level']) : 2;

	// Cache settings
	$cache_size = !empty($pkg_config['cache_size']) ? $pkg_config['cache_size'] : '4096';
	$cache_min_ttl = !empty($pkg_config['cache_min_ttl']) ? $pkg_config['cache_min_ttl'] : '2400';
	$cache_max_ttl = !empty($pkg_config['cache_max_ttl']) ? $pkg_config['cache_max_ttl'] : '86400';
	$cache_neg_min_ttl = !empty($pkg_config['cache_neg_min_ttl']) ? $pkg_config['cache_neg_min_ttl'] : '60';
	$cache_neg_max_ttl = !empty($pkg_config['cache_neg_max_ttl']) ? $pkg_config['cache_neg_max_ttl'] : '600';

	// Advanced settings with defaults
	$keepalive = !empty($pkg_config['keepalive']) ? $pkg_config['keepalive'] : '30';
	$lb_strategy = !empty($pkg_config['lb_strategy']) ? dnscrypt_proxy_toml_safe($pkg_config['lb_strategy']) : 'p2';
	$netprobe_timeout = isset($pkg_config['netprobe_timeout']) ? $pkg_config['netprobe_timeout'] : '60';
	$netprobe_address = !empty($pkg_config['netprobe_address']) ? $pkg_config['netprobe_address'] : '9.9.9.9:53';
	$cert_refresh_delay = !empty($pkg_config['cert_refresh_delay']) ? $pkg_config['cert_refresh_delay'] : '240';
	$blocked_query_response = !empty($pkg_config['blocked_query_response']) ? dnscrypt_proxy_toml_safe($pkg_config['blocked_query_response']) : 'hinfo';
	$reject_ttl = !empty($pkg_config['reject_ttl']) ? $pkg_config['reject_ttl'] : '10';
	$log_files_max_size = !empty($pkg_config['log_files_max_size']) ? $pkg_config['log_files_max_size'] : '10';
	$log_files_max_age = !empty($pkg_config['log_files_max_age']) ? $pkg_config['log_files_max_age'] : '7';
	$log_files_max_backups = !empty($pkg_config['log_files_max_backups']) ? $pkg_config['log_files_max_backups'] : '1';

	// Determine server selection mode and build server_names
	$server_names = '';
	$selection_mode = !empty($pkg_config['server_selection_mode']) ? $pkg_config['server_selection_mode'] : 'auto';

	if ($selection_mode == 'manual') {
		// Manual mode: use selected servers
		$selected_servers = dnscrypt_proxy_get_selected_servers($pkg_config);
		if (!empty($selected_servers)) {
			$server_names = "server_names = ['" . implode("', '", array_map('dnscrypt_proxy_toml_safe', $selected_servers)) . "']";
		}
	}
	// In auto mode, don't set server_names - dnscrypt-proxy will use any matching server

	// Disabled server names
	if (!empty($pkg_config['disabled_server_names'])) {
		$disabled = array_map('trim', explode(',', $pkg_config['disabled_server_names']));
		$disabled = array_filter($disabled);
		if (!empty($disabled)) {
			$server_names .= "\ndisabled_server_names = ['" . implode("', '", array_map('dnscrypt_proxy_toml_safe', $disabled)) . "']";
		}
	}

	// Add trailing newline only if we have server selection lines
	if (!empty($server_names)) {
		$server_names .= "\n";
	}

	// Parse bootstrap resolvers
	$bootstrap = '9.9.9.11:53, 8.8.8.8:53';
	if (!empty($pkg_config['bootstrap_resolvers'])) {
		$bootstrap = $pkg_config['bootstrap_resolvers'];
	}
	$bootstrap_list = array_map(function($r) {
		return "'" . dnscrypt_proxy_toml_safe(trim($r)) . "'";
	}, explode(',', $bootstrap));
	$bootstrap_str = implode(', ', array_filter($bootstrap_list));

	// Boolean settings - Server selection
	$ipv4_servers = isset($pkg_config['ipv4_servers']) && $pkg_config['ipv4_servers'] == 'on' ? 'true' : 'false';
	$ipv6_servers = isset($pkg_config['ipv6_servers']) && $pkg_config['ipv6_servers'] == 'on' ? 'true' : 'false';
	$dnscrypt_servers = isset($pkg_config['dnscrypt_servers']) && $pkg_config['dnscrypt_servers'] == 'on' ? 'true' : 'false';
	$doh_servers = isset($pkg_config['doh_servers']) && $pkg_config['doh_servers'] == 'on' ? 'true' : 'false';
	$odoh_servers = isset($pkg_config['odoh_servers']) && $pkg_config['odoh_servers'] == 'on' ? 'true' : 'false';
	$require_dnssec = isset($pkg_config['require_dnssec']) && $pkg_config['require_dnssec'] == 'on' ? 'true' : 'false';
	$require_nolog = isset($pkg_config['require_nolog']) && $pkg_config['require_nolog'] == 'on' ? 'true' : 'false';
	$require_nofilter = isset($pkg_config['require_nofilter']) && $pkg_config['require_nofilter'] == 'on' ? 'true' : 'false';

	// Boolean settings - Network/connection
	$ignore_system_dns = isset($pkg_config['ignore_system_dns']) && $pkg_config['ignore_system_dns'] == 'on' ? 'true' : 'false';
	$force_tcp = isset($pkg_config['force_tcp']) && $pkg_config['force_tcp'] == 'on' ? 'true' : 'false';
	$http3 = isset($pkg_config['http3']) && $pkg_config['http3'] == 'on' ? 'true' : 'false';
	$offline_mode = isset($pkg_config['offline_mode']) && $pkg_config['offline_mode'] == 'on' ? 'true' : 'false';

	// Boolean settings - Cache/filtering
	$cache_enabled = isset($pkg_config['cache']) && $pkg_config['cache'] == 'on' ? 'true' : 'false';
	$block_ipv6 = isset($pkg_config['block_ipv6']) && $pkg_config['block_ipv6'] == 'on' ? 'true' : 'false';
	$block_unqualified = isset($pkg_config['block_unqualified']) && $pkg_config['block_unqualified'] == 'on' ? 'true' : 'false';
	$block_undelegated = isset($pkg_config['block_undelegated']) && $pkg_config['block_undelegated'] == 'on' ? 'true' : 'false';

	// Boolean settings - Logging
	$use_syslog = isset($pkg_config['use_syslog']) && $pkg_config['use_syslog'] == 'on' ? 'true' : 'false';

	// Boolean settings - Security/privacy
	$dnscrypt_ephemeral_keys = isset($pkg_config['dnscrypt_ephemeral_keys']) && $pkg_config['dnscrypt_ephemeral_keys'] == 'on' ? 'true' : 'false';
	$tls_disable_session_tickets = isset($pkg_config['tls_disable_session_tickets']) && $pkg_config['tls_disable_session_tickets'] == 'on' ? 'true' : 'false';

	// Handle defaults when not set
	if (!isset($pkg_config['ipv4_servers'])) $ipv4_servers = 'true';
	if (!isset($pkg_config['dnscrypt_servers'])) $dnscrypt_servers = 'true';
	if (!isset($pkg_config['doh_servers'])) $doh_servers = 'true';
	if (!isset($pkg_config['require_nolog'])) $require_nolog = 'true';
	if (!isset($pkg_config['require_nofilter'])) $require_nofilter = 'true';
	if (!isset($pkg_config['ignore_system_dns'])) $ignore_system_dns = 'true';
	if (!isset($pkg_config['cache'])) $cache_enabled = 'true';
	if (!isset($pkg_config['block_unqualified'])) $block_unqualified = 'true';
	if (!isset($pkg_config['block_undelegated'])) $block_undelegated = 'true';
	if (!isset($pkg_config['use_syslog'])) $use_syslog = 'true';

	// Log file
	$log_file_line = '';
	if (!empty($pkg_config['log_file'])) {
		$log_file_line = "log_file = '" . dnscrypt_proxy_toml_safe($pkg_config['log_file']) . "'\n";
	}

	// Query log section
	$query_log_section = '';
	if (isset($pkg_config['query_log']) && $pkg_config['query_log'] == 'on') {
		$query_log_file = !empty($pkg_config['query_log_file']) ? dnscrypt_proxy_toml_safe($pkg_config['query_log_file']) : '/var/log/dnscrypt-proxy/query.log';
		$query_log_section = <<<TOML

[query_log]
file = '{$query_log_file}'
format = 'tsv'
TOML;
	}

	// Blocked names section
	$blocked_names_section = '';
	if (!empty($pkg_config['blocked_names'])) {
		$blocked_names_section = <<<TOML

[blocked_names]
blocked_names_file = '/usr/local/etc/dnscrypt-proxy/blocked-names.txt'
TOML;
	}

	// Allowed names section
	$allowed_names_section = '';
	if (!empty($pkg_config['allowed_names'])) {
		$allowed_names_section = <<<TOML

[allowed_names]
allowed_names_file = '/usr/local/etc/dnscrypt-proxy/allowed-names.txt'
TOML;
	}

	// Forwarding rules section
	$forwarding_section = '';
	if (!empty($pkg_config['forwarding_rules'])) {
		$forwarding_section = <<<TOML

[forwarding_rules]
forwarding_rules_file = '/usr/local/etc/dnscrypt-proxy/forwarding-rules.txt'
TOML;
	}

	// Cloaking rules section
	$cloaking_section = '';
	if (!empty($pkg_config['cloaking_rules'])) {
		$cloaking_section = <<<TOML

[cloaking_rules]
cloaking_rules_file = '/usr/local/etc/dnscrypt-proxy/cloaking-rules.txt'
TOML;
	}

	// Build TOML configuration
	// Split custom TOML into top-level key overrides (prepended) and
	// section blocks (appended) so that [section] headers don't capture
	// the generated top-level keys that follow them.
	$custom_toml_top = '';
	$custom_toml_sections = '';
	$custom_override_keys = array();
	if (!empty($pkg_config['custom_toml'])) {
		$custom_content = base64_decode($pkg_config['custom_toml']);
		$custom_content = trim($custom_content);
		if (!empty($custom_content)) {
			$in_section = false;
			foreach (explode("\n", $custom_content) as $cline) {
				if (preg_match('/^\s*\[/', $cline)) {
					$in_section = true;
				}
				if ($in_section) {
					$custom_toml_sections .= $cline . "\n";
				} else {
					$custom_toml_top .= $cline . "\n";
					// Collect top-level keys so we can strip duplicates from generated config
					$trimmed = trim($cline);
					if (!empty($trimmed) && $trimmed[0] !== '#' && preg_match('/^([a-zA-Z0-9_]+)\s*=/', $trimmed, $km)) {
						$custom_override_keys[] = $km[1];
					}
				}
			}
		}
	}

	$toml = <<<TOML
# DNSCrypt Proxy configuration
# Generated by pfSense package - do not edit manually

listen_addresses = [{$listen_addresses_str}]
max_clients = {$max_clients}
{$server_names}
# Server selection
ipv4_servers = {$ipv4_servers}
ipv6_servers = {$ipv6_servers}
dnscrypt_servers = {$dnscrypt_servers}
doh_servers = {$doh_servers}
odoh_servers = {$odoh_servers}

require_dnssec = {$require_dnssec}
require_nolog = {$require_nolog}
require_nofilter = {$require_nofilter}

# Connection settings
force_tcp = {$force_tcp}
http3 = {$http3}
timeout = {$timeout}
keepalive = {$keepalive}
lb_strategy = '{$lb_strategy}'

# Startup and network
ignore_system_dns = {$ignore_system_dns}
bootstrap_resolvers = [{$bootstrap_str}]
netprobe_timeout = {$netprobe_timeout}
netprobe_address = '{$netprobe_address}'
offline_mode = {$offline_mode}

# Security and privacy
dnscrypt_ephemeral_keys = {$dnscrypt_ephemeral_keys}
tls_disable_session_tickets = {$tls_disable_session_tickets}

# Certificate handling
cert_refresh_delay = {$cert_refresh_delay}

# Query filtering
block_ipv6 = {$block_ipv6}
block_unqualified = {$block_unqualified}
block_undelegated = {$block_undelegated}
blocked_query_response = '{$blocked_query_response}'
reject_ttl = {$reject_ttl}

# Logging
log_level = {$log_level}
use_syslog = {$use_syslog}
{$log_file_line}log_files_max_size = {$log_files_max_size}
log_files_max_age = {$log_files_max_age}
log_files_max_backups = {$log_files_max_backups}

# Cache
cache = {$cache_enabled}
cache_size = {$cache_size}
cache_min_ttl = {$cache_min_ttl}
cache_max_ttl = {$cache_max_ttl}
cache_neg_min_ttl = {$cache_neg_min_ttl}
cache_neg_max_ttl = {$cache_neg_max_ttl}

# Server sources
[sources]
  [sources.'public-resolvers']
  urls = ['https://raw.githubusercontent.com/DNSCrypt/dnscrypt-resolvers/master/v3/public-resolvers.md', 'https://download.dnscrypt.info/resolvers-list/v3/public-resolvers.md']
  cache_file = '/var/cache/dnscrypt-proxy/public-resolvers.md'
  minisign_key = 'RWQf6LRCGA9i53mlYecO4IzT51TGPpvWucNSCh1CBM0QTaLn73Y7GFO3'
  refresh_delay = 72
  prefix = ''
TOML;

	// Add relay source when anonymized DNS is enabled
	if (isset($pkg_config['anon_dns_enable']) && $pkg_config['anon_dns_enable'] == 'on') {
		$toml .= <<<TOML

  [sources.'relays']
  urls = ['https://raw.githubusercontent.com/DNSCrypt/dnscrypt-resolvers/master/v3/relays.md', 'https://download.dnscrypt.info/resolvers-list/v3/relays.md']
  cache_file = '/var/cache/dnscrypt-proxy/relays.md'
  minisign_key = 'RWQf6LRCGA9i53mlYecO4IzT51TGPpvWucNSCh1CBM0QTaLn73Y7GFO3'
  refresh_delay = 72
  prefix = ''
TOML;
	}

	// Append optional sections
	$toml .= $query_log_section;
	$toml .= $blocked_names_section;
	$toml .= $allowed_names_section;
	$toml .= $forwarding_section;
	$toml .= $cloaking_section;

	// Add anonymized DNS section
	$toml .= dnscrypt_proxy_generate_anon_dns($pkg_config);

	// Add custom static servers
	$static_servers = dnscrypt_proxy_generate_static_servers($pkg_config);
	$toml .= $static_servers;

	// Create cache directory
	safe_mkdir('/var/cache/dnscrypt-proxy');

	// Strip generated keys that are overridden by custom TOML top-level keys
	if (!empty($custom_override_keys)) {
		$toml = dnscrypt_proxy_strip_toml_keys($toml, $custom_override_keys);
	}
	// Prepend top-level custom options (generated keys win by last-write)
	if (!empty(trim($custom_toml_top))) {
		$toml = "# Custom options\n" . $custom_toml_top . "\n" . $toml;
	}
	// Append custom sections (e.g. [monitoring_ui], [broken_implementations])
	if (!empty(trim($custom_toml_sections))) {
		$toml .= "\n# Custom sections\n" . $custom_toml_sections;
	}

	// Write configuration file
	$toml = rtrim($toml) . "\n";
	file_put_contents(DNSCRYPT_PROXY_CONFIG, $toml);
	chmod(DNSCRYPT_PROXY_CONFIG, 0644);
}

/**
 * Generate list files from configuration
 */
function dnscrypt_proxy_generate_lists() {
	$pkg_config = dnscrypt_proxy_get_config();

	// Blocked names
	$blocked_file = DNSCRYPT_PROXY_BASE . '/blocked-names.txt';
	if (!empty($pkg_config['blocked_names'])) {
		$content = base64_decode($pkg_config['blocked_names']);
		file_put_contents($blocked_file, $content);
	} else {
		@unlink($blocked_file);
	}

	// Allowed names
	$allowed_file = DNSCRYPT_PROXY_BASE . '/allowed-names.txt';
	if (!empty($pkg_config['allowed_names'])) {
		$content = base64_decode($pkg_config['allowed_names']);
		file_put_contents($allowed_file, $content);
	} else {
		@unlink($allowed_file);
	}

	// Forwarding rules
	$forwarding_file = DNSCRYPT_PROXY_BASE . '/forwarding-rules.txt';
	if (!empty($pkg_config['forwarding_rules'])) {
		$content = base64_decode($pkg_config['forwarding_rules']);
		file_put_contents($forwarding_file, $content);
	} else {
		@unlink($forwarding_file);
	}

	// Cloaking rules
	$cloaking_file = DNSCRYPT_PROXY_BASE . '/cloaking-rules.txt';
	if (!empty($pkg_config['cloaking_rules'])) {
		$content = base64_decode($pkg_config['cloaking_rules']);
		file_put_contents($cloaking_file, $content);
	} else {
		@unlink($cloaking_file);
	}
}

/**
 * Start the service
 */
function dnscrypt_proxy_start() {
	if (file_exists(DNSCRYPT_PROXY_RCFILE)) {
		mwexec(DNSCRYPT_PROXY_RCFILE . ' start');
	}
}

/**
 * Stop the service
 */
function dnscrypt_proxy_stop() {
	mwexec("/usr/bin/killall -q dnscrypt-proxy");
	unlink_if_exists('/var/run/dnscrypt_proxy.pid');
}

/**
 * Restart the service
 */
function dnscrypt_proxy_restart() {
	dnscrypt_proxy_stop();
	dnscrypt_proxy_start();
}

/**
 * Get service status
 */
function dnscrypt_proxy_status() {
	$pid_file = '/var/run/dnscrypt_proxy.pid';
	if (file_exists($pid_file)) {
		$pid = trim(file_get_contents($pid_file));
		if (!empty($pid) && posix_kill($pid, 0)) {
			return true;
		}
	}
	return false;
}

?>
