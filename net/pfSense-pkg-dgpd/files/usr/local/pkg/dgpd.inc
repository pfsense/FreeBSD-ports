<?php
/*
 * Copyright 2026 gebra
 * SPDX-License-Identifier: Apache-2.0
 *
 * Dynamic GUA-PD: native pfSense downstream prefix delegation controller.
 * Configuration and service ownership stay with pfSense. This library never
 * patches core files or writes generated daemon configuration. Lease revocation
 * is limited to a confirmed Disable/Delete operation for an owned PD pool.
 */
require_once('/etc/inc/config.inc');
require_once('/etc/inc/interfaces.inc');
require_once('/etc/inc/functions.inc');
require_once('/etc/inc/services.inc');
require_once('/etc/inc/service-utils.inc');
require_once('/etc/inc/notices.inc');

const DGPD_STATE = 'installedpackages/dgpd/state';
const DGPD_SETTINGS = 'installedpackages/dgpd/settings';
const DGPD_ALLOCATOR = '/conf/kea6-scripts/kea-pd-allocator.php';
const DGPD_SIGNAL = '/var/run/dgpd-signal.generation';
const DGPD_ALIAS_ALL = 'DGPD_ALL_PD';
const DGPD_ALIAS_DESCRIPTION = 'Dynamic downstream prefix managed by DynGUA-PD';
const DGPD_ALIAS_FORMER_DESCRIPTION = 'Former DynGUA-PD alias; review referenced firewall rules';

function dgpd_debug_enabled(): bool {
	return config_get_path(DGPD_SETTINGS . '/debug', '') === 'on';
}

function dgpd_syslog_write(int $priority, string $message): void {
	openlog('dgpd', LOG_PID, LOG_DAEMON);
	syslog($priority, $message);
	closelog();
}

function dgpd_cleanup_legacy_log(): void {
	@unlink('/var/log/dgpd-reconcile.log');
}

/* Use the native package syslog registration; never log raw control payloads. */
function dgpd_log(string $level, string $message, array $context = []): void {
	$priorities = [
		'debug' => LOG_DEBUG,
		'info' => LOG_INFO,
		'notice' => LOG_NOTICE,
		'warning' => LOG_WARNING,
		'error' => LOG_ERR,
	];
	if (!isset($priorities[$level]) || ($level === 'debug' && !dgpd_debug_enabled())) {
		return;
	}
	$details = [];
	foreach ($context as $name => $value) {
		if (is_scalar($value) || $value === null) {
			$details[(string)$name] = $value;
		}
	}
	if ($details) {
		$message .= ' ' . json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}
	$signature = $level . "\0" . $message;
	if ($level === 'debug' && ($GLOBALS['dgpd_last_debug_log'] ?? null) === $signature) {
		return;
	}
	if ($level === 'debug') {
		$GLOBALS['dgpd_last_debug_log'] = $signature;
	}
	dgpd_syslog_write($priorities[$level], $message);
}

/* Cached observations are valid only within the current PHP request. */
function dgpd_request_cache_clear(): void {
	unset($GLOBALS['dgpd_allocator_cache'], $GLOBALS['dgpd_sources_cache']);
}

function dgpd_save_logging_setting(): bool {
	$enabled = isset($_POST['dgpd_debug']);
	if ($enabled === dgpd_debug_enabled()) {
		return false;
	}
	config_set_path(DGPD_SETTINGS . '/debug', $enabled ? 'on' : 'off');
	$result = write_config('DGPD: update debug logging setting');
	if ($result === false || $result === -1) {
		throw new RuntimeException(gettext('pfSense could not save the DGPD logging setting'));
	}
	dgpd_log('notice', $enabled ? 'Debug logging enabled' : 'Debug logging disabled');
	return true;
}

/* Return a canonical IPv6 network using the native pfSense implementation. */
function dgpd_network(string $address, int $length): string {
	if (!is_ipaddrv6($address) || $length < 0 || $length > 128) {
		throw new RuntimeException(gettext('Invalid IPv6 prefix'));
	}
	return inet_ntop(inet_pton(gen_subnetv6($address, $length)));
}

/* Check overlapping prefixes without treating textual IPv6 spelling as identity. */
function dgpd_overlap(array $left, array $right): bool {
	$length = min((int)$left['prefix_len'], (int)$right['prefix_len']);
	return dgpd_network($left['prefix'], $length) === dgpd_network($right['prefix'], $length);
}

/* Read only the fields DGPD is allowed to own on one native DHCPv6 interface. */
function dgpd_native_fields(string $interface): array {
	$fields = [];
	foreach (['pdprefix', 'pdprefixlen', 'pddellen'] as $key) {
		$value = config_get_path("dhcpdv6/{$interface}/{$key}", null);
		if ($value !== null && $value !== '') {
			$fields[$key] = (string)$value;
		}
	}
	return $fields;
}

function dgpd_fields(array $assignment): array {
	return ['pdprefix' => $assignment['prefix'],
		'pdprefixlen' => (string)$assignment['prefix_len'],
		'pddellen' => (string)$assignment['delegated_len']];
}

/* Read state separately from the package framework's GUI configuration. */
function dgpd_state(): array {
	$state = config_get_path(DGPD_STATE, []);
	if (!is_array($state) || (isset($state['schema']) && !in_array((string)$state['schema'], ['3', '4', '5'], true))) {
		throw new RuntimeException(gettext('Unsupported DGPD state; manual migration required'));
	}
	if (config_get_path('dgpd/assignments', []) || config_get_path('dgpd/assignment', [])) {
		throw new RuntimeException(gettext('Legacy runtime DGPD assignments detected; review before native migration'));
	}
	$state += ['schema' => '5', 'assignments' => []];
	/* Empty XML elements are read back as empty strings by pfSense. */
	if ($state['assignments'] === '') {
		$state['assignments'] = [];
	}
	if ((string)$state['schema'] === '3') {
		foreach ($state['assignments'] as &$legacy) {
			if (is_array($legacy)) {
				$legacy['status'] = 'active';
			}
		}
		unset($legacy);
	}
	if (in_array((string)$state['schema'], ['3', '4'], true)) {
		$state['schema'] = '5';
	}
	if (!is_array($state['assignments'])) {
		throw new RuntimeException(gettext('Malformed DGPD assignments'));
	}
	foreach ($state['assignments'] as $interface => $assignment) {
		if (!is_string($interface) || !preg_match('/^[a-z][a-z0-9_]*$/D', $interface) ||
		    !is_array($assignment) || ($assignment['interface'] ?? '') !== $interface ||
		    !in_array($assignment['status'] ?? 'active', ['active', 'disabled', 'stale'], true)) {
			throw new RuntimeException(gettext('Malformed DGPD ownership record'));
		}
		$state['assignments'][$interface]['status'] = $assignment['status'] ?? 'active';
	}
	if (isset($state['pending']) && (!is_array($state['pending']) ||
	    !in_array($state['pending']['action'] ?? '', ['apply', 'disable', 'enable', 'delete', 'stale', 'restore'], true) ||
	    !isset($state['assignments'][$state['pending']['interface'] ?? '']))) {
		throw new RuntimeException(gettext('Malformed DGPD pending operation'));
	}
	return $state;
}

function dgpd_alias_name(string $interface): string {
	if (!preg_match('/^[a-z][a-z0-9_]*$/D', $interface)) {
		throw new RuntimeException(gettext('Invalid interface for DGPD alias'));
	}
	return 'DGPD_' . strtoupper($interface) . '_PD';
}

/* Return assignments whose native Kea state has already been verified. */
function dgpd_verified_assignments(array $state): array {
	$verified = [];
	$pending = $state['pending'] ?? null;
	foreach ($state['assignments'] ?? [] as $interface => $assignment) {
		if (($pending['interface'] ?? '') === $interface) {
			$previous = $pending['previous'] ?? null;
			if (is_array($previous) && ($previous['status'] ?? 'active') === 'active') {
				$verified[$interface] = $previous;
			}
			continue;
		}
		if (($assignment['status'] ?? 'active') === 'active') {
			$verified[$interface] = $assignment;
		}
	}
	return $verified;
}

/* Maintain native, locked aliases without creating or changing firewall rules. */
function dgpd_sync_alias_config(array $state): bool {
	$verified = dgpd_verified_assignments($state);
	$desired = [DGPD_ALIAS_ALL => []];
	foreach ($state['assignments'] ?? [] as $interface => $_assignment) {
		$desired[dgpd_alias_name($interface)] = [];
	}
	foreach ($verified as $interface => $assignment) {
		$cidr = $assignment['prefix'] . '/' . $assignment['prefix_len'];
		$desired[dgpd_alias_name($interface)] = [$cidr];
		$desired[DGPD_ALIAS_ALL][] = $cidr;
	}

	$aliases = config_get_path('aliases/alias', []);
	$aliases = is_array($aliases) ? $aliases : [];
	$result = [];
	foreach ($aliases as $alias) {
		$name = (string)($alias['name'] ?? '');
		$owned = str_starts_with((string)($alias['descr'] ?? ''), DGPD_ALIAS_DESCRIPTION) &&
			in_array('automanaged', get_tags_from_alias_item($alias), true);
		if (isset($desired[$name])) {
			$reclaimable = (string)($alias['descr'] ?? '') === DGPD_ALIAS_FORMER_DESCRIPTION &&
				str_starts_with($name, 'DGPD_') && str_ends_with($name, '_PD');
			if (!$owned && !$reclaimable) {
				throw new RuntimeException(sprintf(gettext('Firewall alias %s already exists and is not owned by DGPD'), $name));
			}
			$prefixes = $desired[$name];
			$result[] = ['name' => $name, 'type' => 'network',
				'address' => implode(' ', $prefixes),
				'descr' => DGPD_ALIAS_DESCRIPTION . ($name === DGPD_ALIAS_ALL ? ' (all interfaces)' : ''),
				'detail' => implode('||', array_fill(0, count($prefixes), 'Verified delegated prefix')),
				'tags' => 'automanaged'];
			unset($desired[$name]);
			continue;
		}
		if ($owned && str_starts_with($name, 'DGPD_') && str_ends_with($name, '_PD')) {
			if (is_alias_inuse($name)) {
				$alias['address'] = '';
				$alias['detail'] = '';
				$result[] = $alias;
			}
			continue;
		}
		$result[] = $alias;
	}
	foreach ($desired as $name => $prefixes) {
		$result[] = ['name' => $name, 'type' => 'network',
			'address' => implode(' ', $prefixes),
			'descr' => DGPD_ALIAS_DESCRIPTION . ($name === DGPD_ALIAS_ALL ? ' (all interfaces)' : ''),
			'detail' => implode('||', array_fill(0, count($prefixes), 'Verified delegated prefix')),
			'tags' => 'automanaged'];
	}
	if ($result === $aliases) {
		return false;
	}
	config_set_path('aliases/alias', $result);
	return true;
}

/* write_config() provides the native backup, persistence and audit trail. */
function dgpd_save(array $state, string $reason): void {
	config_set_path(DGPD_STATE, $state);
	$aliases_changed = dgpd_sync_alias_config($state);
	$result = write_config($reason);
	if ($result === false || $result === -1) {
		throw new RuntimeException(gettext('pfSense could not save DGPD configuration'));
	}
	dgpd_log('info', 'State saved', ['reason' => $reason]);
	if ($aliases_changed) {
		alias_make_table();
		filter_configure();
	}
}

/* Discover the socket from native generated configuration; never modify it. */
function dgpd_socket(): string {
	$config = json_decode((string)@file_get_contents('/usr/local/etc/kea/kea-dhcp6.conf'), true);
	$socket = $config['Dhcp6']['control-socket']['socket-name'] ?? '/var/run/kea/kea6-ctrl-socket';
	if (!is_string($socket) || !str_starts_with($socket, '/var/run/') || str_contains($socket, "\0")) {
		throw new RuntimeException(gettext('Unsupported Kea DHCPv6 UNIX control socket path'));
	}
	return $socket;
}

/* Kea configuration stays read-only; lease6-del is restricted by the owned-pool wrapper below. */
function dgpd_command(string $command, ?array $arguments = null): array {
	if (!in_array($command, ['config-get', 'lease6-get-all', 'lease6-del', 'list-commands'], true)) {
		throw new RuntimeException(gettext('Unsupported DGPD Kea command'));
	}
	$socket = dgpd_socket();
	$stream = @stream_socket_client("unix://{$socket}", $error, $message, 5);
	if ($stream === false) {
		throw new RuntimeException(gettext('Enable Kea DHCPv6: The DHCPv6 UNIX control socket is unavailable'));
	}
	try {
		stream_set_timeout($stream, 10);
		$request = ['command' => $command];
		if ($arguments !== null) {
			$request['arguments'] = $arguments;
		}
		$wire = json_encode($request, JSON_THROW_ON_ERROR) . "\n";
		$offset = 0;
		while ($offset < strlen($wire)) {
			$written = fwrite($stream, substr($wire, $offset));
			if (!$written) {
				throw new RuntimeException(gettext('Kea socket write failed'));
			}
			$offset += $written;
		}
		$reply = '';
		while (!feof($stream)) {
			$line = fgets($stream, 65536);
			if ($line === false) {
				break;
			}
			$reply .= $line;
			if (strlen($reply) > 16777216) {
				throw new RuntimeException(gettext('Kea response too large'));
			}
			if (str_contains($line, "\n")) {
				break;
			}
		}
		if (stream_get_meta_data($stream)['timed_out']) {
			throw new RuntimeException(gettext('Kea response timeout'));
		}
	} finally {
		fclose($stream);
	}
	$result = json_decode($reply, true, 512, JSON_THROW_ON_ERROR);
	if (is_array($result) && array_is_list($result)) {
		$result = $result[0] ?? null;
	}
	if (!is_array($result) || !isset($result['result'])) {
		throw new RuntimeException(gettext('Malformed Kea response'));
	}
	return $result;
}

function dgpd_live(): array {
	$result = dgpd_command('config-get');
	if ($result['result'] !== 0 || !is_array($result['arguments']['Dhcp6'] ?? null)) {
		throw new RuntimeException(gettext('Kea config-get failed'));
	}
	return $result['arguments']['Dhcp6'];
}

/* Inspect prerequisites at GUI/worker initialization and again before actions. */
function dgpd_prerequisites(): array {
	$checks = [];
	foreach (['config_get_path', 'config_set_path', 'config_del_path', 'config_path_enabled', 'write_config',
	    'config_read_file', 'lock', 'unlock', 'gen_subnetv6', 'is_ipaddrv6', 'get_real_interface',
	    'get_interface_addresses', 'get_interface_ipv6', 'route_get_default',
	    'get_tags_from_alias_item', 'is_alias_inuse', 'alias_make_table', 'filter_configure',
	    'services_dhcpd_configure', 'mark_subsystem_dirty', 'is_subsystem_dirty', 'clear_subsystem_dirty',
	    'dhcp_is_backend', 'is_dhcpv6_server_enabled',
	    'is_process_running', 'mwexec_bg'] as $function) {
		if (!function_exists($function)) {
			$checks[] = ['name' => "pfSense API {$function}", 'ready' => false,
				'required' => true, 'message' => gettext('Required native pfSense API unavailable')];
		}
	}
	if ($checks) {
		return $checks;
	}
	$requirements = [
		gettext('Allocator') => is_readable(DGPD_ALLOCATOR),
		gettext('socat') => is_executable('/usr/local/bin/socat'),
		gettext('Kea DHCPv6') => dhcp_is_backend('kea') && is_dhcpv6_server_enabled() &&
			is_executable('/usr/local/sbin/kea-dhcp6') && is_process_running('kea-dhcp6'),
		gettext('DHCPv6 WAN client') => is_executable('/usr/local/sbin/dhcp6c') && is_process_running('dhcp6c'),
	];
	foreach ($requirements as $name => $ready) {
		$checks[] = ['name' => $name, 'ready' => $ready, 'required' => true,
			'message' => $ready ? gettext('Available') :
				sprintf(gettext('Please install or activate %s'), $name)];
	}
	$socket_ready = false;
	$leases_ready = false;
	$routing_ready = false;
	$socket = dgpd_socket();
	if (@filetype($socket) === 'socket') {
		try {
			$live = dgpd_live();
			$socket_ready = true;
			foreach ($live['hooks-libraries'] ?? [] as $hook) {
				if (($hook['parameters']['name'] ?? '') === '/usr/local/share/kea/scripts/kea_run6' &&
				    is_executable('/usr/local/bin/kea2fib6') &&
				    is_executable('/conf/kea6_scripts.d/kea2fib6.sh')) {
					$routing_ready = true;
				}
			}
		} catch (Throwable $error) {
			$socket_ready = false;
		}
		if ($socket_ready) {
			try {
				$commands = dgpd_command('list-commands');
				$leases_ready = $commands['result'] === 0 &&
					is_array($commands['arguments'] ?? null) &&
					in_array('lease6-get-all', $commands['arguments'], true) &&
					in_array('lease6-del', $commands['arguments'], true);
			} catch (Throwable $error) {
				$leases_ready = false;
			}
		}
	}
	$checks[] = ['name' => gettext('Kea DHCPv6 UNIX control socket'), 'ready' => $socket_ready,
		'required' => true, 'message' => $socket_ready ? gettext('Available') :
		gettext('Enable Kea DHCPv6: The DHCPv6 UNIX control socket is unavailable')];
	$checks[] = ['name' => gettext('Kea lease commands'), 'ready' => $leases_ready,
		'required' => true, 'message' => $leases_ready ? gettext('Available') :
		gettext('Enable Kea lease commands (libdhcp_lease_cmds)')];
	$checks[] = ['name' => gettext('Native PD routing (pfSense-system)'), 'ready' => $routing_ready,
		'required' => true, 'message' => $routing_ready ? gettext('Available') :
		gettext('Enable native Kea IPv6 routing (kea2fib6, pfSense-system)')];
	$ready = is_executable('/usr/local/sbin/radvd') && is_process_running('radvd');
	$checks[] = ['name' => gettext('Router Advertisements (radvd)'), 'ready' => $ready,
		'required' => false, 'message' => $ready ? gettext('Available for prefix comparison') :
		gettext('Enable Router Advertisements (radvd) to compare prefixes')];
	return $checks;
}

function dgpd_retry_pause(): void {
	usleep(500000);
}

/* Require a stable Kea control plane after the native reload returns. */
function dgpd_wait_prerequisites(bool $after_reload = false): array {
	$attempts = $after_reload ? 10 : 1;
	$consecutive = 0;
	$checks = [];
	for ($attempt = 0; $attempt < $attempts; $attempt++) {
		$checks = dgpd_prerequisites();
		$ready = true;
		foreach ($checks as $check) {
			if ($check['required'] && !$check['ready']) {
				$ready = false;
				break;
			}
		}
		$consecutive = $ready ? $consecutive + 1 : 0;
		if ($ready && (!$after_reload || $consecutive >= 2)) {
			return $checks;
		}
		if ($attempt + 1 < $attempts) {
			dgpd_retry_pause();
		}
	}
	return $checks;
}

function dgpd_require_ready(bool $after_reload = false): void {
	$errors = [];
	foreach (dgpd_wait_prerequisites($after_reload) as $check) {
		if ($check['required'] && !$check['ready']) {
			$errors[] = $check['message'];
		}
	}
	if ($errors) {
		throw new RuntimeException(implode('; ', $errors));
	}
	dgpd_validate_custom_json();
}

/* Missing DGPD markers are valid: native PD integration no longer needs them. */
function dgpd_validate_custom_json(): void {
	$paths = ['kea6/custom_kea_config'];
	foreach (array_keys(config_get_path('dhcpdv6', [])) as $interface) {
		$paths[] = "dhcpdv6/{$interface}/custom_kea_config";
	}
	foreach ($paths as $path) {
		$raw = config_get_path($path, '');
		if ($raw === '') {
			continue;
		}
		$decoded = is_string($raw) ? base64_decode($raw, true) : false;
		try {
			$json = $decoded === false ? null : json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
			if (!is_array($json) || !str_starts_with(ltrim($decoded), '{')) {
				throw new RuntimeException(gettext('Expected a JSON object'));
			}
		} catch (Throwable $error) {
			throw new RuntimeException(sprintf(
				gettext('Custom JSON invalid at %s. DGPD actions are blocked.'), $path));
		}
	}
}

/* Resolve an explicitly selected, enabled native DHCPv6 target. */
function dgpd_target(string $interface, array $live): array {
	if (!preg_match('/^[a-z][a-z0-9_]*$/D', $interface) ||
	    !config_path_enabled("interfaces/{$interface}") ||
	    !config_path_enabled("dhcpdv6/{$interface}")) {
		throw new RuntimeException(gettext('Enable DHCPv6 on the selected interface'));
	}
	if (config_get_path("interfaces/{$interface}/ipaddrv6", '') !== 'track6' ||
	    config_get_path("interfaces/{$interface}/track6-interface", '') === '') {
		throw new RuntimeException(gettext('Enable Track Interface on the selected interface'));
	}
	$physical = get_real_interface($interface, 'inet6');
	$addresses = get_interface_addresses($physical) ?? [];
	if (($addresses['status'] ?? '') !== 'up') {
		throw new RuntimeException(sprintf(gettext('Enable interface %s'), $interface));
	}
	$matches = array_values(array_filter($live['subnet6'] ?? [],
		fn($subnet) => ($subnet['interface'] ?? '') === $physical));
	if (count($matches) !== 1) {
		throw new RuntimeException(gettext('Expected exactly one live Kea subnet for selected interface'));
	}
	return ['physical' => $physical, 'subnet_id' => (int)$matches[0]['id']];
}

function dgpd_interface_failure(string $interface, array $live): ?string {
	if (config_get_path("interfaces/{$interface}/ipaddrv6", '') !== 'track6' ||
	    config_get_path("interfaces/{$interface}/track6-interface", '') === '') {
		return gettext('FAIL: Track Interface is not enabled');
	}
	if (!config_path_enabled("dhcpdv6/{$interface}")) {
		return gettext('FAIL: Kea DHCPv6 is not enabled');
	}
	try {
		dgpd_target($interface, $live);
	} catch (Throwable $error) {
		return 'FAIL: ' . $error->getMessage();
	}
	return null;
}

/* Preserve the tested allocator algorithm and consume its structured output. */
function dgpd_allocator(string $interface, bool $proposal = false, bool $refresh = false): array {
	$key = $interface . ':' . ($proposal ? 'proposal' : 'inspection');
	if (!$refresh && isset($GLOBALS['dgpd_allocator_cache'][$key])) {
		return $GLOBALS['dgpd_allocator_cache'][$key];
	}
	$output = [];
	$command = '/usr/local/bin/php ' . escapeshellarg(DGPD_ALLOCATOR) .
		' ' . escapeshellarg('--target=' . $interface) .
		' ' . escapeshellarg('--socket=' . dgpd_socket()) .
		($proposal ? ' --json' : ' --inspect-json');
	exec($command . ' 2>&1', $output, $status);
	$report = dgpd_allocator_report(implode("\n", $output), $status, $interface);
	dgpd_log('debug', 'Allocator report accepted',
		['interface' => $interface, 'mode' => $proposal ? 'proposal' : 'inspection',
		'status' => $status, 'blocks' => count($report['blocks'] ?? [])]);
	$GLOBALS['dgpd_allocator_cache'][$key] = $report;
	return $report;
}

/* A valid inspection remains useful when the allocator reports no free proposal. */
function dgpd_allocator_report(string $output, int $status, string $interface): array {
	try {
		$report = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
	} catch (Throwable $error) {
		throw new RuntimeException(sprintf(gettext('Allocator unavailable: %s'),
			implode(' ', array_slice(explode("\n", $output), -4))));
	}
	if (($report['schema'] ?? null) !== 1 || !is_array($report['upstreams'] ?? null) ||
	    !isset($report['targets'][$interface]['wan']) || !is_array($report['blocks'] ?? null)) {
		throw new RuntimeException($status !== 0 ?
			sprintf(gettext('Allocator unavailable: %s'), $output) : gettext('Invalid allocator report'));
	}
	return $report;
}

/* Keep every DGPD reservation, including disabled ones, out of displayed free space. */
function dgpd_reserve_report(array $report, array $state, string $interface): array {
	$reservations = array_values($state['assignments'] ?? []);
	foreach (array_keys($report['candidates'][$interface] ?? []) as $length) {
		$prefixes = $report['candidates'][$interface][$length]['prefixes'] ?? [];
		$prefixes = array_values(array_filter($prefixes, function ($cidr) use ($reservations) {
			[$prefix, $length] = explode('/', $cidr, 2);
			$candidate = ['prefix' => $prefix, 'prefix_len' => (int)$length];
			foreach ($reservations as $reserved) {
				if (dgpd_overlap($candidate, $reserved)) {
					return false;
				}
			}
			return true;
		}));
		$report['candidates'][$interface][$length]['prefixes'] = $prefixes;
		$report['candidates'][$interface][$length]['count'] = count($prefixes);
	}
	$proposal = $report['proposals'][$interface]['pd-pool'] ?? null;
	if (is_array($proposal)) {
		$candidate = ['prefix' => $proposal['prefix'], 'prefix_len' => (int)$proposal['prefix-len']];
		foreach ($reservations as $reserved) {
			if (dgpd_overlap($candidate, $reserved)) {
				unset($report['proposals'][$interface]['pd-pool']);
				break;
			}
		}
	}
	return $report;
}

/* Read advertised prefixes, not a claimed DHCPv6 IA_PD binding. */
function dgpd_ra_prefixes(string $configuration, string $physical): array {
	$prefixes = [];
	$tokens = preg_split('/\s+/', trim(preg_replace('/#[^\n]*/', '',
		str_replace(['{', '}', ';'], [' { ', ' } ', ' ; '], $configuration))));
	$depth = 0;
	$selected = false;
	foreach ($tokens as $index => $token) {
		if ($depth === 0 && $token === 'interface') {
			$selected = ($tokens[$index + 1] ?? '') === $physical;
		}
		if ($token === '{') {
			$depth++;
		} elseif ($token === '}') {
			$depth--;
		} elseif ($selected && $depth === 1 && $token === 'prefix') {
			$cidr = explode('/', $tokens[$index + 1] ?? '');
			if (count($cidr) !== 2 || !ctype_digit($cidr[1])) {
				throw new RuntimeException(gettext('Unsupported Router Advertisement prefix'));
			}
			$network = dgpd_network($cidr[0], (int)$cidr[1]);
			$packed = inet_pton($network);
			/* radvd also advertises ULA/link-local networks; they are not IA_PD evidence. */
			if ($packed !== false && (ord($packed[0]) & 0xe0) === 0x20) {
				$prefixes[] = ['prefix' => $network, 'prefix_len' => (int)$cidr[1]];
			}
		}
	}
	return $prefixes;
}

/* A secondary source may corroborate the allocator, but cannot overrule it. */
function dgpd_compare_ra(array $upstream, array $advertised): string {
	foreach ($advertised as $prefix) {
		if ($prefix['prefix_len'] < $upstream['prefix_len'] ||
		    dgpd_network($prefix['prefix'], $upstream['prefix_len']) !== $upstream['prefix']) {
			throw new RuntimeException(gettext('Prefix sources disagree: allocator / Router Advertisements; changes blocked'));
		}
	}
	return $advertised ? 'MATCH (derived RA evidence)' : 'UNAVAILABLE';
}

function dgpd_sources(string $interface, array $report): array {
	$wan = $report['targets'][$interface]['wan'];
	$upstream = $report['upstreams'][$wan] ?? null;
	if (!is_array($upstream)) {
		throw new RuntimeException(gettext('Allocator upstream missing'));
	}
	$upstream['prefix'] = dgpd_network($upstream['prefix'], (int)$upstream['prefix_len']);
	$upstream['prefix_len'] = (int)$upstream['prefix_len'];
	$key = hash('sha256', json_encode([$interface, $wan, $upstream,
		$report['targets'][$interface]['physical'] ?? null], JSON_THROW_ON_ERROR));
	if (isset($GLOBALS['dgpd_sources_cache'][$key])) {
		return $GLOBALS['dgpd_sources_cache'][$key];
	}
	$advertised = [];
	if (is_process_running('radvd') && is_readable('/var/etc/radvd.conf')) {
		$advertised = dgpd_ra_prefixes(file_get_contents('/var/etc/radvd.conf'),
			$report['targets'][$interface]['physical']);
	}
	$comparison = dgpd_compare_ra($upstream, $advertised);
	dgpd_log('debug', 'Prefix sources evaluated',
		['interface' => $interface, 'wan' => $wan,
		'upstream' => $upstream['prefix'] . '/' . $upstream['prefix_len'],
		'ra_prefixes' => count($advertised), 'comparison' => $comparison]);
	$sources = ['wan' => $wan, 'upstream' => $upstream, 'comparison' => $comparison];
	$GLOBALS['dgpd_sources_cache'][$key] = $sources;
	return $sources;
}

/* Validate manually entered assignments against current allocator evidence. */
function dgpd_validate_assignment(array $assignment, array $report): array {
	$interface = (string)($assignment['interface'] ?? '');
	foreach (['prefix_len', 'delegated_len'] as $key) {
		if (!ctype_digit((string)($assignment[$key] ?? ''))) {
			throw new RuntimeException(gettext('Invalid prefix lengths'));
		}
	}
	$length = (int)$assignment['prefix_len'];
	$delegated = (int)$assignment['delegated_len'];
	if ($length < 1 || $length > 64 || $delegated < $length || $delegated > 64) {
		throw new RuntimeException(gettext('Invalid pool/delegation length'));
	}
	$prefix = dgpd_network((string)($assignment['prefix'] ?? ''), $length);
	$entered = inet_ntop(inet_pton((string)$assignment['prefix']));
	$sources = dgpd_sources($interface, $report);
	$upstream = $sources['upstream'];
	if ($length < $upstream['prefix_len'] || dgpd_network($prefix, $upstream['prefix_len']) !== $upstream['prefix']) {
		throw new RuntimeException(gettext('Pool is outside the current allocator upstream prefix'));
	}
	return ['interface' => $interface, 'prefix' => $prefix, 'prefix_len' => $length,
		'delegated_len' => $delegated, 'wan' => $sources['wan'],
		'upstream' => $upstream['prefix'] . '/' . $upstream['prefix_len'],
		'normalized_from' => $entered !== $prefix ? $entered : null];
}

/* Refuse to adopt or overwrite independently configured native PD fields. */
function dgpd_assert_ownership(string $interface, ?array $owned): void {
	$current = dgpd_native_fields($interface);
	if ($owned === null ? (bool)$current : $current !== dgpd_fields($owned)) {
		throw new RuntimeException(gettext('Native PD configuration is not owned by DGPD or was changed externally'));
	}
}

function dgpd_check_blocks(array $assignment, array $report, ?array $owned, int $subnet): void {
	$owned_matches = 0;
	foreach ($report['blocks'] as $block) {
		if ($owned !== null && ($block['reason'] ?? '') === "Kea PD pool subnet-id {$subnet}" &&
		    (int)$block['prefix_len'] === (int)$owned['prefix_len'] &&
		    dgpd_network($block['prefix'], (int)$block['prefix_len']) === $owned['prefix']) {
			$owned_matches++;
			if ($owned_matches > 1) {
				throw new RuntimeException(gettext('Duplicate old PD pool; ownership is ambiguous'));
			}
			continue;
		}
		if (dgpd_overlap($assignment, $block)) {
			throw new RuntimeException(sprintf(gettext('Pool overlaps %s'), $block['reason']));
		}
	}
}

function dgpd_ownership_notice_id(string $interface): string {
	return 'dgpd-ownership-' . $interface;
}

function dgpd_close_ownership_notice(string $interface): void {
	close_notice(dgpd_ownership_notice_id($interface));
}

/* Remove ownership alerts that no longer have a saved or pending DGPD owner. */
function dgpd_close_orphan_ownership_notices(array $state): void {
	$managed = array_fill_keys(array_keys($state['assignments'] ?? []), true);
	if (is_string($state['pending']['interface'] ?? null)) {
		$managed[$state['pending']['interface']] = true;
	}
	foreach (get_notices() ?: [] as $notice) {
		$id = (string)($notice['id'] ?? '');
		if (!str_starts_with($id, 'dgpd-ownership-')) {
			continue;
		}
		$interface = substr($id, strlen('dgpd-ownership-'));
		if (!isset($managed[$interface])) {
			close_notice($id);
		}
	}
}

/* Publish one native pfSense alert per interface and message, without reload spam. */
function dgpd_publish_ownership_notice(string $interface, string $message): void {
	$id = dgpd_ownership_notice_id($interface);
	foreach (get_notices() ?: [] as $notice) {
		if (($notice['id'] ?? '') === $id && html_entity_decode((string)($notice['notice'] ?? ''), ENT_QUOTES) === $message) {
			return;
		}
	}
	close_notice($id);
	file_notice($id, $message, 'Dynamic GUA-PD', '/pkg_edit.php?xml=dgpd.xml&interface=' . rawurlencode($interface), 2, true);
	logger(LOG_ERR, $message);
}

/* Validate native fields as an adoptable assignment without changing either owner. */
function dgpd_native_candidate(string $interface, array $owned, array $state,
	array $report, array $live): array {
	$native = dgpd_native_fields($interface);
	if (!$native) {
		throw new RuntimeException(gettext('Native PD assignment was deleted'));
	}
	if (array_keys($native) !== ['pdprefix', 'pdprefixlen', 'pddellen']) {
		throw new RuntimeException(gettext('Native PD assignment is incomplete'));
	}
	$candidate = dgpd_validate_assignment(['interface' => $interface,
		'prefix' => $native['pdprefix'], 'prefix_len' => $native['pdprefixlen'],
		'delegated_len' => $native['pddellen']], $report);
	if (($candidate['normalized_from'] ?? null) !== null || dgpd_fields($candidate) !== $native) {
		throw new RuntimeException(gettext('Native PD assignment is not canonically aligned'));
	}
	unset($candidate['normalized_from']);
	$target = dgpd_target($interface, $live);
	/* Ignore exactly one copy of the candidate's own native Kea pool. */
	dgpd_check_blocks($candidate, $report, $candidate, $target['subnet_id']);
	foreach ($state['assignments'] as $other => $assignment) {
		if ($other !== $interface && dgpd_overlap($candidate, $assignment)) {
			throw new RuntimeException(gettext('Native PD assignment overlaps another DGPD assignment'));
		}
	}
	if (!dgpd_pool_present($live, $interface, $candidate)) {
		throw new RuntimeException(gettext('Native Kea runtime does not contain the changed PD assignment'));
	}
	$candidate['status'] = 'active';
	return $candidate;
}

/* Derive ownership state. Valid native replacements may be adopted by explicit workers/actions. */
function dgpd_ownership_observation(string $interface, array $owned, array $state,
	array $report, array $live): array {
	$native = dgpd_native_fields($interface);
	$pending = ($state['pending']['interface'] ?? '') === $interface ? $state['pending'] : null;
	if (is_array($pending)) {
		$expected = in_array($pending['action'] ?? '', ['delete', 'disable', 'stale'], true) ? [] :
			dgpd_fields($owned);
	} else {
		$expected = ($owned['status'] ?? 'active') === 'active' ? dgpd_fields($owned) : [];
	}
	if ($native === $expected) {
		return ['status' => 'matched', 'candidate' => null, 'message' => ''];
	}
	try {
		$candidate = dgpd_native_candidate($interface, $owned, $state, $report, $live);
		return ['status' => 'adoptable', 'candidate' => $candidate,
			'message' => sprintf(gettext('%s: conflict-free native PD change detected'), $interface)];
	} catch (Throwable $error) {
		return ['status' => 'conflict', 'candidate' => null,
			'message' => sprintf(gettext('%s: native PD configuration changed externally: %s'),
				$interface, $error->getMessage())];
	}
}

function dgpd_accept_native(string $interface, bool $automatic = false): string {
	$lock = lock('dgpd', LOCK_EX | LOCK_NB);
	if (!$lock) {
		throw new RuntimeException(gettext('DGPD is busy; retry the action'));
	}
	try {
		config_read_file(false, false);
		$state = dgpd_state();
		dgpd_log('debug', 'Reconciliation started',
			['preflight' => $preflight, 'after_reload' => $after_reload,
			'assignments' => count($state['assignments']),
			'pending' => $state['pending']['action'] ?? 'none']);
		if (isset($state['pending'])) {
			throw new RuntimeException(gettext('A native apply is pending'));
		}
		$owned = $state['assignments'][$interface] ?? null;
		if (!is_array($owned)) {
			throw new RuntimeException(gettext('No DGPD assignment to update'));
		}
		$live = dgpd_live();
		$report = dgpd_reserve_report(dgpd_allocator($interface, true), $state, $interface);
		$observation = dgpd_ownership_observation($interface, $owned, $state, $report, $live);
		if ($observation['status'] !== 'adoptable') {
			throw new RuntimeException($observation['message'] ?: gettext('No valid native change to accept'));
		}
		$old = $owned['prefix'] . '/' . $owned['prefix_len'];
		$state['assignments'][$interface] = $observation['candidate'];
		$state['notice'] = ['interface' => $interface, 'old' => $old,
			'new' => $observation['candidate']['prefix'] . '/' . $observation['candidate']['prefix_len']];
		dgpd_save($state, $automatic ? "DGPD: automatically accept conflict-free native PD change on {$interface}" :
			"DGPD: accept native PD change on {$interface}");
		dgpd_close_ownership_notice($interface);
		return gettext('Conflict-free native PD change accepted; DGPD ownership updated.');
	} finally {
		unlock($lock);
	}
}

function dgpd_release_ownership(string $interface): string {
	$lock = lock('dgpd', LOCK_EX | LOCK_NB);
	if (!$lock) {
		throw new RuntimeException(gettext('DGPD is busy; retry the action'));
	}
	try {
		config_read_file(false, false);
		$state = dgpd_state();
		if (isset($state['pending'])) {
			throw new RuntimeException(gettext('A native apply is pending'));
		}
		if (!isset($state['assignments'][$interface])) {
			return gettext('Already unmanaged');
		}
		unset($state['assignments'][$interface]);
		if (($state['notice']['interface'] ?? '') === $interface) {
			unset($state['notice']);
		}
		dgpd_save($state, "DGPD: release ownership of {$interface}; native configuration retained");
		dgpd_close_ownership_notice($interface);
		return gettext('DGPD ownership released; native DHCPv6 configuration was not changed.');
	} finally {
		unlock($lock);
	}
}

/* Return only IA_PD leases contained by the selected DGPD-owned pool. */
function dgpd_assignment_leases(int $subnet, array $assignment): array {
	$result = dgpd_command('lease6-get-all', ['subnets' => [$subnet]]);
	if (!in_array($result['result'], [0, 3], true)) {
		throw new RuntimeException(gettext('Enable Kea lease commands: Safe lease verification failed'));
	}
	$leases = $result['arguments']['leases'] ?? [];
	if (!is_array($leases)) {
		throw new RuntimeException(gettext('Malformed lease response; change blocked'));
	}
	$matches = [];
	foreach ($leases as $lease) {
		if (!array_key_exists('type', $lease)) {
			throw new RuntimeException(gettext('Malformed lease response; change blocked'));
		}
		if (!in_array($lease['type'], [2, '2', 'IA_PD'], true)) {
			continue;
		}
		if (!is_string($lease['ip-address'] ?? null) || !is_ipaddrv6($lease['ip-address']) ||
		    !ctype_digit((string)($lease['prefix-len'] ?? ''))) {
			throw new RuntimeException(gettext('Malformed IA_PD lease response; change blocked'));
		}
		$lease_length = (int)$lease['prefix-len'];
		if ($lease_length < (int)$assignment['prefix_len'] || $lease_length > 128) {
			continue;
		}
		if (dgpd_network($lease['ip-address'], (int)$assignment['prefix_len']) === $assignment['prefix']) {
			$matches[] = $lease;
		}
	}
	return $matches;
}

function dgpd_guard_assignment_leases(int $subnet, array $assignment): void {
	if (dgpd_assignment_leases($subnet, $assignment)) {
		throw new RuntimeException(gettext('An active IA_PD lease exists in this DGPD assignment; explicit revocation confirmation is required'));
	}
}

/* Kea emits lease6_delete, so pfSense's native kea2fib6 hook removes the route. */
function dgpd_revoke_assignment_leases(int $subnet, array $assignment): int {
	$leases = dgpd_assignment_leases($subnet, $assignment);
	foreach ($leases as $lease) {
		$result = dgpd_command('lease6-del', ['ip-address' => $lease['ip-address'],
			'type' => 'IA_PD', 'subnet-id' => $subnet]);
		if (!in_array($result['result'], [0, 3], true)) {
			throw new RuntimeException(sprintf(gettext('Kea could not revoke IA_PD lease %s/%d'),
				$lease['ip-address'], (int)$lease['prefix-len']));
		}
	}
	if (dgpd_assignment_leases($subnet, $assignment)) {
		throw new RuntimeException(gettext('Kea IA_PD lease revocation could not be verified; change blocked'));
	}
	if ($leases) {
		logger(LOG_NOTICE, sprintf('DGPD: revoked %d IA_PD lease(s) from %s/%d before Disable/Delete',
			count($leases), $assignment['prefix'], (int)$assignment['prefix_len']));
	}
	return count($leases);
}

/* Persist intent and native fields together, then use pfSense's dirty/apply flow. */
function dgpd_transition(string $interface, string $action, ?array $requested, bool $take_ownership,
    bool $confirm_revoke = false): string {
	if (!in_array($action, ['apply', 'disable', 'enable', 'delete', 'stale'], true)) {
		throw new RuntimeException(gettext('Unsupported DGPD action'));
	}
	$lock = lock('dgpd', LOCK_EX | LOCK_NB);
	if (!$lock) {
		throw new RuntimeException(gettext('DGPD is busy; retry the action'));
	}
	$native = null;
	try {
		$native = lock('dhcpdconfigure', LOCK_EX | LOCK_NB);
		if (!$native) {
			throw new RuntimeException(gettext('Native DHCP configuration is busy; retry the action'));
		}
		config_read_file(false, false);
		dgpd_require_ready();
		$state = dgpd_state();
		if (isset($state['pending'])) {
			throw new RuntimeException(gettext('A native apply is pending; use Recover before another change'));
		}
		$owned = $state['assignments'][$interface] ?? null;
		if ($owned === null && $action !== 'apply') {
			return 'Already unmanaged';
		}
		if ($owned === null && $action === 'apply' && !$take_ownership) {
			throw new RuntimeException(gettext('Allow Interface Management for the selected interface (checkbox)'));
		}
		$status = $owned['status'] ?? 'active';
		if (($action === 'disable' && $status !== 'active') || ($action === 'enable' && $status !== 'disabled')) {
			throw new RuntimeException(gettext('Requested transition does not match the assignment status'));
		}
		if ($owned !== null) {
			$current_native = dgpd_native_fields($interface);
			$expected_native = $status === 'active' ? dgpd_fields($owned) : [];
			if ($current_native !== $expected_native) {
				throw new RuntimeException(gettext('Native PD configuration is not owned by DGPD or was changed externally'));
			}
		} elseif (dgpd_native_fields($interface)) {
			throw new RuntimeException(gettext('Native PD configuration is not owned by DGPD or was changed externally'));
		}
		$live = dgpd_live();
		$target = dgpd_target($interface, $live);
		$report = dgpd_allocator($interface);
		dgpd_sources($interface, $report);
		if ($action === 'enable') {
			$requested = $owned;
		}
		if ($action === 'apply' || $action === 'enable') {
			$requested = dgpd_validate_assignment($requested, $report);
			if ($action === 'apply' && $owned !== null && $status === 'active' && dgpd_fields($owned) === dgpd_fields($requested)) {
				return 'UNCHANGED: native PD assignment already saved';
			}
			dgpd_check_blocks($requested, $report, $owned, $target['subnet_id']);
			foreach ($state['assignments'] as $other => $assignment) {
				if ($other !== $interface && dgpd_overlap($requested, $assignment)) {
					throw new RuntimeException(gettext('Pool overlaps another DGPD assignment'));
				}
			}
		}
		$revoked = 0;
		if (in_array($action, ['disable', 'delete'], true) && $status === 'active') {
			$leases = dgpd_assignment_leases($target['subnet_id'], $owned);
			if ($leases && !$confirm_revoke) {
				throw new RuntimeException(gettext('This action will interrupt the downstream network and revoke its active IA_PD lease. Confirm the warning to continue.'));
			}
			if ($leases) {
				$revoked = dgpd_revoke_assignment_leases($target['subnet_id'], $owned);
			}
		} elseif ($owned !== null && ($status === 'active' || $action === 'delete') && $action !== 'stale') {
			dgpd_guard_assignment_leases($target['subnet_id'], $owned);
		}
		/* Re-read evidence immediately before the persistent mutation. */
		if ($action === 'apply' || $action === 'enable') {
			$fresh = dgpd_allocator($interface, false, true);
			dgpd_validate_assignment($requested, $fresh);
			dgpd_check_blocks($requested, $fresh, $owned, $target['subnet_id']);
		}
		$normalized = $requested['normalized_from'] ?? null;
		if ($requested !== null) {
			unset($requested['normalized_from']);
		}
		$fields = in_array($action, ['apply', 'enable'], true) ? dgpd_fields($requested) : [];
		foreach (['pdprefix', 'pdprefixlen', 'pddellen'] as $key) {
			if (isset($fields[$key])) {
				config_set_path("dhcpdv6/{$interface}/{$key}", $fields[$key]);
			} else {
				config_del_path("dhcpdv6/{$interface}/{$key}");
			}
		}
		$state['pending'] = ['interface' => $interface, 'action' => $action];
		if ($owned !== null) {
			$state['pending']['previous'] = $owned;
		}
		if ($action === 'apply') {
			$requested['status'] = 'active';
			$state['assignments'][$interface] = $requested;
		}
		$state['schema'] = '4';
		/* Keep ownership until the native apply and runtime verification complete. */
		dgpd_save($state, "DGPD: queue native PD change on {$interface}");
		mark_subsystem_dirty('dhcpd6');
	} finally {
		unlock($native);
		unlock($lock);
	}
	$result = $action === 'delete' ? gettext('Prefix deleted. Save Settings to finalize operation') :
		'SAVED: activation and verification pending';
	if (($revoked ?? 0) > 0) {
		$result = sprintf(gettext('Revoked %d active IA_PD lease(s). '), $revoked) . $result;
	}
	if ($normalized !== null) {
		$result = sprintf(gettext('INFO: PD Pool Prefix normalized from %s to %s/%d. '),
			$normalized, $requested['prefix'], $requested['prefix_len']) . $result;
	}
	return $result;
}

function dgpd_commit(string $interface, ?array $requested, bool $take_ownership): string {
	return dgpd_transition($interface, $requested === null ? 'delete' : 'apply', $requested, $take_ownership);
}

/* Restore saved ownership after an external native edit; never trust stale fields. */
function dgpd_restore_assignment(string $interface): string {
	$lock = lock('dgpd', LOCK_EX | LOCK_NB);
	if (!$lock) {
		throw new RuntimeException(gettext('DGPD is busy; retry the action'));
	}
	$native_lock = null;
	try {
		$native_lock = lock('dhcpdconfigure', LOCK_EX | LOCK_NB);
		if (!$native_lock) {
			throw new RuntimeException(gettext('Native DHCP configuration is busy; retry the action'));
		}
		config_read_file(false, false);
		dgpd_require_ready();
		$state = dgpd_state();
		if (isset($state['pending'])) {
			throw new RuntimeException(gettext('A native apply is pending'));
		}
		$owned = $state['assignments'][$interface] ?? null;
		if (!is_array($owned)) {
			throw new RuntimeException(gettext('No saved DGPD assignment to restore'));
		}
		$live = dgpd_live();
		$target = dgpd_target($interface, $live);
		dgpd_guard_assignment_leases($target['subnet_id'], $owned);
		$report = dgpd_allocator($interface);
		$validated = dgpd_validate_assignment($owned, $report);
		dgpd_check_blocks($validated, $report, $owned, $target['subnet_id']);
		foreach ($state['assignments'] as $other => $assignment) {
			if ($other !== $interface && dgpd_overlap($validated, $assignment)) {
				throw new RuntimeException(gettext('Saved assignment overlaps another DGPD assignment'));
			}
		}
		foreach (dgpd_fields($validated) as $key => $value) {
			config_set_path("dhcpdv6/{$interface}/{$key}", $value);
		}
		$state['pending'] = ['interface' => $interface, 'action' => 'restore', 'previous' => $owned];
		dgpd_save($state, "DGPD: queue restoration of saved assignment on {$interface}");
		mark_subsystem_dirty('dhcpd6');
	} finally {
		if ($native_lock) {
			unlock($native_lock);
		}
		unlock($lock);
	}
	return gettext('SAVED: restoration activation and verification pending');
}

/* Re-stage the complete saved intent after an interrupted or overwritten native edit. */
function dgpd_recover_pending(): string {
	$lock = lock('dgpd', LOCK_EX | LOCK_NB);
	if (!$lock) {
		throw new RuntimeException(gettext('DGPD is busy; retry the action'));
	}
	$native_lock = null;
	try {
		$native_lock = lock('dhcpdconfigure', LOCK_EX | LOCK_NB);
		if (!$native_lock) {
			throw new RuntimeException(gettext('Native DHCP configuration is busy; retry the action'));
		}
		config_read_file(false, false);
		dgpd_require_ready();
		$state = dgpd_state();
		$pending = $state['pending'] ?? null;
		if (!is_array($pending) || !is_string($pending['interface'] ?? null)) {
			throw new RuntimeException(gettext('No pending DGPD operation to recover'));
		}
		$interface = $pending['interface'];
		$action = (string)($pending['action'] ?? '');
		$owned = $state['assignments'][$interface] ?? null;
		$restoring = in_array($action, ['apply', 'enable', 'restore'], true);
		if ($restoring && !is_array($owned)) {
			throw new RuntimeException(gettext('Pending DGPD assignment is missing'));
		}
		$fields = [];
		if ($restoring) {
			$live = dgpd_live();
			$target = dgpd_target($interface, $live);
			$report = dgpd_allocator($interface);
			dgpd_sources($interface, $report);
			$validated = dgpd_validate_assignment($owned, $report);
			dgpd_check_blocks($validated, $report, $owned, $target['subnet_id']);
			foreach ($state['assignments'] as $other => $assignment) {
				if ($other !== $interface && dgpd_overlap($validated, $assignment)) {
					throw new RuntimeException(gettext('Saved assignment overlaps another DGPD assignment'));
				}
			}
			$fields = dgpd_fields($validated);
		}
		foreach (['pdprefix', 'pdprefixlen', 'pddellen'] as $key) {
			if (isset($fields[$key])) {
				config_set_path("dhcpdv6/{$interface}/{$key}", $fields[$key]);
			} else {
				config_del_path("dhcpdv6/{$interface}/{$key}");
			}
		}
		dgpd_save($state, "DGPD: re-stage pending {$action} on {$interface}");
		mark_subsystem_dirty('dhcpd6');
	} finally {
		if ($native_lock) {
			unlock($native_lock);
		}
		unlock($lock);
	}
	return gettext('Save Settings restored; pending intent and ownership are retained.');
}

/* Compare the owned native fields and runtime pool, ignoring unrelated pools. */
function dgpd_pool_present(array $live, string $interface, array $assignment): bool {
	$target = dgpd_target($interface, $live);
	foreach ($live['subnet6'] as $subnet) {
		if ((int)$subnet['id'] !== $target['subnet_id']) {
			continue;
		}
		foreach ($subnet['pd-pools'] ?? [] as $pool) {
			if (dgpd_network($pool['prefix'], (int)$pool['prefix-len']) === $assignment['prefix'] &&
			    (int)$pool['prefix-len'] === (int)$assignment['prefix_len'] &&
			    (int)$pool['delegated-len'] === (int)$assignment['delegated_len']) {
				return true;
			}
		}
	}
	return false;
}

/* Native Kea startup is asynchronous; retry readiness at most ten times. */
function dgpd_wait_live(bool $after_reload): array {
	for ($attempt = 0; $attempt < ($after_reload ? 10 : 1); $attempt++) {
		try {
			return dgpd_live();
		} catch (Throwable $error) {
			if (!$after_reload || $attempt === 9) {
				throw $error;
			}
			usleep(500000);
		}
	}
	throw new RuntimeException(gettext('Kea is unavailable after native apply'));
}

/* The worker verifies only after the native pfSense DHCPv6 save routine completes. */
function dgpd_reconcile(bool $preflight = false, bool $after_reload = false): string {
	$lock = lock('dgpd', LOCK_EX | LOCK_NB);
	if (!$lock) {
		throw new RuntimeException(gettext('DGPD is busy; pending operation retained'));
	}
	try {
		config_read_file(false, false);
		$state = dgpd_state();
		if (!$state['assignments'] && !isset($state['pending'])) {
			return 'UNASSIGNED';
		}
		dgpd_require_ready($after_reload);
		$pending = $state['pending'] ?? null;
		if ($pending !== null && is_subsystem_dirty('dhcpd6') && !$preflight) {
			return 'PENDING: activation required';
		}
		foreach ($state['assignments'] as $interface => $assignment) {
			$enabling = ($pending['interface'] ?? '') === $interface &&
				in_array($pending['action'] ?? '', ['enable', 'restore'], true);
			$inactive = in_array($assignment['status'] ?? 'active', ['disabled', 'stale'], true) && !$enabling;
			$removing = ($pending['interface'] ?? '') === $interface &&
				in_array($pending['action'] ?? '', ['disable', 'delete', 'stale'], true);
			if ($inactive || $removing) {
				if (dgpd_native_fields($interface)) {
					throw new RuntimeException(gettext('Inactive assignment conflict; native PD fields were changed'));
				}
				if ($removing && ($pending['action'] ?? '') !== 'stale') {
					$target = dgpd_target($interface, dgpd_live());
					dgpd_guard_assignment_leases($target['subnet_id'], $assignment);
				}
				continue;
			}
			dgpd_assert_ownership($interface, $assignment);
			dgpd_validate_assignment($assignment, dgpd_allocator($interface));
		}
		if (isset($pending['previous']) && in_array($pending['action'], ['apply', 'enable', 'restore'], true)) {
			$target = dgpd_target($pending['interface'], dgpd_live());
			dgpd_guard_assignment_leases($target['subnet_id'], $pending['previous']);
		}
		if ($preflight) {
			return 'READY';
		}
		$live = dgpd_wait_live($after_reload);
		foreach ($state['assignments'] as $interface => $assignment) {
			$enabling = ($pending['interface'] ?? '') === $interface &&
				in_array($pending['action'] ?? '', ['enable', 'restore'], true);
			$should_be_absent = (in_array($assignment['status'] ?? 'active', ['disabled', 'stale'], true) && !$enabling) ||
				(($pending['interface'] ?? '') === $interface && in_array($pending['action'] ?? '', ['disable', 'delete', 'stale'], true));
			if (dgpd_pool_present($live, $interface, $assignment) === $should_be_absent) {
				throw new RuntimeException(gettext('Native Kea runtime verification failed; pending state retained'));
			}
		}
		if ($pending !== null) {
			/* Reload fresh config so unrelated edits are not overwritten by stale state. */
			config_read_file(false, false);
			$current = dgpd_state();
			if ($current !== $state) {
				throw new RuntimeException(gettext('DGPD state changed during native apply; review required'));
			}
			foreach ($state['assignments'] as $interface => $assignment) {
				$enabling = $pending['interface'] === $interface &&
					in_array($pending['action'], ['enable', 'restore'], true);
				$inactive = in_array($assignment['status'] ?? 'active', ['disabled', 'stale'], true) && !$enabling;
				$removing = $pending['interface'] === $interface &&
					in_array($pending['action'], ['disable', 'delete', 'stale'], true);
				if ($inactive || $removing) {
					if (dgpd_native_fields($interface)) {
						throw new RuntimeException(gettext('Native PD configuration changed during removal'));
					}
					continue;
				} else {
					dgpd_assert_ownership($interface, $assignment);
					dgpd_validate_assignment($assignment, dgpd_allocator($interface));
				}
			}
			if ($pending['action'] === 'delete') {
				unset($state['assignments'][$pending['interface']]);
			} elseif ($pending['action'] === 'disable') {
				$state['assignments'][$pending['interface']]['status'] = 'disabled';
			} elseif ($pending['action'] === 'stale') {
				$state['assignments'][$pending['interface']]['status'] = 'stale';
			} elseif (in_array($pending['action'], ['apply', 'enable', 'restore'], true)) {
				$state['assignments'][$pending['interface']]['status'] = 'active';
			}
			if (isset($pending['previous']) && in_array($pending['action'], ['apply', 'enable', 'stale'], true) &&
			    ($pending['action'] === 'stale' ||
			    dgpd_fields($pending['previous']) !== dgpd_fields($state['assignments'][$pending['interface']]))) {
				$state['notice'] = ['interface' => $pending['interface'],
					'old' => $pending['previous']['prefix'] . '/' . $pending['previous']['prefix_len'],
					'new' => $pending['action'] === 'stale' ? '' :
						$state['assignments'][$pending['interface']]['prefix'] . '/' . $state['assignments'][$pending['interface']]['prefix_len']];
			}
			unset($state['pending']);
			dgpd_save($state, 'DGPD: verified native IPv6 PD apply');
		}
		return 'ACTIVE: native assignments verified';
	} finally {
		unlock($lock);
	}
}

function dgpd_sync_native_ownership(): string {
	$state = dgpd_state();
	if (!$state['assignments']) {
		return 'MATCHED';
	}
	$live = dgpd_live();
	foreach ($state['assignments'] as $interface => $owned) {
		$report = dgpd_reserve_report(dgpd_allocator($interface, true), $state, $interface);
		$observation = dgpd_ownership_observation($interface, $owned, $state, $report, $live);
		if ($observation['status'] === 'matched') {
			dgpd_close_ownership_notice($interface);
			continue;
		}
		if ($observation['status'] === 'adoptable') {
			return 'NATIVE-ACCEPTED: ' . dgpd_accept_native($interface, true);
		}
		dgpd_publish_ownership_notice($interface, $observation['message'] .
			gettext('; resolve in Services > Dynamic GUA-PD'));
		return 'CONFLICT: ' . $observation['message'];
	}
	return 'MATCHED';
}

/* Reconcile native ownership first, then an upstream change, or safely mark stale. */
function dgpd_renumber(): string {
	$ownership = dgpd_sync_native_ownership();
	if ($ownership !== 'MATCHED') {
		return $ownership;
	}
	$state = dgpd_state();
	if (isset($state['pending'])) {
		return 'PENDING';
	}
	foreach ($state['assignments'] as $interface => $assignment) {
		if (($assignment['status'] ?? 'active') !== 'active') {
			continue;
		}
		$report = dgpd_reserve_report(dgpd_allocator($interface, true), $state, $interface);
		$sources = dgpd_sources($interface, $report);
		$current = $sources['upstream']['prefix'] . '/' . $sources['upstream']['prefix_len'];
		if ($current === ($assignment['upstream'] ?? '')) {
			continue;
		}
		try {
			$candidates = $report['candidates'][$interface][(string)$assignment['prefix_len']]['prefixes'] ?? [];
			if (!$candidates) {
				throw new RuntimeException(gettext('No collision-free replacement pool'));
			}
			[$prefix] = explode('/', $candidates[0], 2);
			$requested = ['interface' => $interface, 'prefix' => $prefix,
				'prefix_len' => (string)$assignment['prefix_len'],
				'delegated_len' => (string)$assignment['prefix_len']];
			dgpd_transition($interface, 'apply', $requested, true);
			return 'RENUMBERED: ' . dgpd_native_apply();
		} catch (Throwable $error) {
			/* An obsolete pool must not remain active after an unresolvable upstream change. */
			dgpd_transition($interface, 'stale', null, true);
			return 'STALE: ' . dgpd_native_apply() . '; ' . $error->getMessage();
		}
	}
	return dgpd_reconcile();
}

function dgpd_native_apply(): string {
	dgpd_reconcile(true);
	if (!function_exists('dhcp6_apply_changes')) {
		require_once('/usr/local/pfSense/include/www/services_dhcp.inc');
	}
	if (!function_exists('dhcp6_apply_changes')) {
		throw new RuntimeException(gettext('The native pfSense DHCPv6 save routine is unavailable'));
	}
	if (dhcp6_apply_changes() !== 0) {
		throw new RuntimeException(gettext('The native pfSense DHCPv6 save routine failed; pending state retained'));
	}
	dgpd_request_cache_clear();
	return dgpd_reconcile(false, true);
}

/* A single generation file doubles as the signal lock; no separate JSON state. */
function dgpd_request_reconcile(): void {
	$signal = fopen(DGPD_SIGNAL, 'c+');
	if ($signal === false) {
		throw new RuntimeException(gettext('Cannot open DGPD worker signal; pending state retained'));
	}
	try {
		if (!flock($signal, LOCK_EX)) {
			throw new RuntimeException(gettext('Cannot lock DGPD worker signal'));
		}
		$generation = trim(stream_get_contents($signal));
		if ($generation !== '' && !ctype_digit($generation)) {
			throw new RuntimeException(gettext('Invalid DGPD signal generation'));
		}
		$next = (int)$generation >= PHP_INT_MAX ? 1 : (int)$generation + 1;
		rewind($signal);
		if (!ftruncate($signal, 0) || fwrite($signal, $next . "\n") === false || !fflush($signal)) {
			throw new RuntimeException(gettext('Cannot save DGPD worker signal'));
		}
		/* A child inherits open descriptors: release the signal before spawning. */
		flock($signal, LOCK_UN);
		mwexec_bg('/usr/local/bin/php /usr/local/libexec/dgpd_worker.php');
	} finally {
		fclose($signal);
	}
}

/* Installation is observational: unavailable prerequisites never start services. */
function dgpd_package_initialize(): void {
	dgpd_cleanup_legacy_log();
	$raw = config_get_path(DGPD_STATE, []);
	$state = dgpd_state();
	$migrate = is_array($raw) && in_array((string)($raw['schema'] ?? '5'), ['3', '4'], true);
	if ($migrate || dgpd_sync_alias_config($state)) {
		config_set_path(DGPD_STATE, $state);
		$result = write_config($migrate ? 'DGPD: migrate state and initialize managed aliases' :
			'DGPD: initialize managed firewall aliases');
		if ($result === false || $result === -1) {
			throw new RuntimeException(gettext('pfSense could not initialize DGPD configuration'));
		}
		alias_make_table();
		filter_configure();
	}
	foreach (dgpd_prerequisites() as $check) {
		if (!$check['ready']) {
			dgpd_log('warning', 'Initialization prerequisite unavailable',
				['prerequisite' => $check['name'], 'message' => $check['message']]);
		}
	}
	dgpd_log('notice', 'Package initialized');
}

function dgpd_package_deinstall(): void {
	$aliases = config_get_path('aliases/alias', []);
	$aliases = is_array($aliases) ? $aliases : [];
	$result = [];
	$changed = false;
	foreach ($aliases as $alias) {
		$name = (string)($alias['name'] ?? '');
		$owned = str_starts_with((string)($alias['descr'] ?? ''), DGPD_ALIAS_DESCRIPTION) &&
			in_array('automanaged', get_tags_from_alias_item($alias), true);
		if (!$owned || !str_starts_with($name, 'DGPD_') || !str_ends_with($name, '_PD')) {
			$result[] = $alias;
			continue;
		}
		$changed = true;
		if (is_alias_inuse($name)) {
			unset($alias['tags']);
			$alias['descr'] = DGPD_ALIAS_FORMER_DESCRIPTION;
			$result[] = $alias;
		}
	}
	if (!$changed) {
		return;
	}
	config_set_path('aliases/alias', $result);
	write_config('DGPD: release managed firewall aliases during package removal');
	alias_make_table();
	filter_configure();
}

function dgpd_package_resync_signal(): void {
	try {
		$state = dgpd_state();
		if ($state['assignments'] || isset($state['pending'])) {
			dgpd_request_reconcile();
		}
	} catch (Throwable $error) {
		dgpd_log('error', 'Package resynchronization failed', ['message' => $error->getMessage()]);
	}
}

/* Render categorized, escaped feedback using native Bootstrap alert classes. */
function dgpd_alert(string $message, string $level): void {
	$staged = $message === 'SAVED: activation and verification pending';
	$deletion_staged = $message === gettext('Prefix deleted. Save Settings to finalize operation');
	if ($staged || $deletion_staged) {
		$level = 'info';
	}
	$levels = [
		'danger' => gettext('Error'),
		'warning' => gettext('Warning'),
		'success' => gettext('Success'),
		'info' => gettext('Information'),
	];
	if (!isset($levels[$level])) {
		$level = 'info';
	}
	foreach ($GLOBALS['dgpd_alerts'] ?? [] as $alert) {
		if (($alert['message'] ?? null) === $message && ($alert['level'] ?? null) === $level) {
			return;
		}
	}
	$GLOBALS['dgpd_alerts'][] = ['message' => $message, 'level' => $level];
	$label = htmlspecialchars($levels[$level], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	if ($staged || $deletion_staged) {
		$complete = htmlspecialchars($deletion_staged ? gettext('Assignment deletion staged.') :
			gettext('Settings staged.'),
			ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		$next = htmlspecialchars($deletion_staged ? gettext('Select Save Settings to finalize deletion.') :
			gettext('Select Save Settings to finalize operation.'),
			ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		echo "<div class=\"alert alert-info\"><strong>{$label}</strong><br />{$complete}<br />" .
			"<strong class=\"text-danger\">{$next}</strong></div>";
		return;
	}
	$message = htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	$transient_class = $level === 'success' ? ' dgpd-success-transient' : '';
	echo "<div class=\"alert alert-{$level}{$transient_class}\"><strong>{$label}</strong><br />{$message}</div>";
}

function dgpd_notice(string $message, bool $error = false): void {
	if ($error) {
		dgpd_alert($message, 'danger');
	}
}

/* Follow native POST/Redirect/GET before pkg_edit.php reaches its donotsave exit. */
function dgpd_gui_prepare(): void {
	$GLOBALS['dgpd_alerts'] = [];
	ob_start();
	try {
		dgpd_gui_before_form();
		$feedback = ob_get_contents();
	} finally {
		ob_end_clean();
	}
	$GLOBALS['dgpd_gui_feedback'] = $feedback;
	if ($_POST) {
		$interface = $_POST['dgpd_interface'] ?? '';
		if (is_array($interface)) {
			$interface = reset($interface);
		}
		$url = '/pkg_edit.php?xml=dgpd.xml&interface=' . rawurlencode((string)$interface);
		$alerts = $GLOBALS['dgpd_alerts'];
		if ($alerts) {
			$priorities = ['danger' => 4, 'warning' => 3, 'success' => 2, 'info' => 1];
			usort($alerts, static function ($left, $right) use ($priorities) {
				return $priorities[$right['level']] <=> $priorities[$left['level']];
			});
			$url .= '&dgpd_result=' . rawurlencode($alerts[0]['message']) .
				'&dgpd_level=' . rawurlencode($alerts[0]['level']);
		}
		pfSenseHeader($url);
		exit;
	}
}

/* Called after the native page header; all dynamic text has already been escaped. */
function dgpd_gui_feedback(): void {
	echo $GLOBALS['dgpd_gui_feedback'] ?? '';
	if (isset($_GET['dgpd_result']) && is_string($_GET['dgpd_result'])) {
		$level = is_string($_GET['dgpd_level'] ?? null) ? $_GET['dgpd_level'] : 'info';
		dgpd_alert($_GET['dgpd_result'], $level);
	}
	try {
		$state = dgpd_state();
		$pending_action = $state['pending']['action'] ?? '';
		if (!isset($_GET['dgpd_result']) && $pending_action === 'delete') {
			dgpd_alert(gettext('Prefix deleted. Save Settings to finalize operation'), 'info');
		} elseif (!isset($_GET['dgpd_result']) &&
		    in_array($pending_action, ['apply', 'disable', 'enable', 'restore'], true)) {
			dgpd_alert('SAVED: activation and verification pending', 'info');
		}
		$notice = $state['notice'] ?? null;
		if (is_array($notice)) {
			$old = htmlspecialchars((string)($notice['old'] ?? ''), ENT_QUOTES, 'UTF-8');
			$new = htmlspecialchars((string)($notice['new'] ?? ''), ENT_QUOTES, 'UTF-8');
			echo '<div class="alert alert-warning"><strong>' . gettext('Downstream Prefix Change') .
				'</strong><br />' . gettext('The delegated prefix changed. Downstream routers may require DHCPv6-PD renewal or manual reconfiguration.') .
				"<br /><code>{$old}</code> &rarr; <code>" . ($new !== '' ? $new : gettext('STALE / disabled')) . '</code></div>';
		}
	} catch (Throwable $error) { /* State diagnostics are rendered below. */ }
}

function dgpd_gui_before_form(): void {
	$interface = '';
	$after_native_apply = false;
	try {
		if (isset($_POST['apply'])) {
			$result = dgpd_native_apply();
			$after_native_apply = true;
			dgpd_gui_result($result);
		}
		$action = '';
		foreach (['propose' => 'Propose', 'apply' => 'Apply Assignment',
		    'disable' => 'Disable', 'enable' => 'Enable', 'delete' => 'Delete',
		    'native_apply' => 'Save Settings', 'recover' => 'Recover',
		    'acknowledge' => 'Acknowledge', 'restore_assignment' => 'Restore DGPD Assignment',
		    'accept_native' => 'Accept Native Change',
		    'release_ownership' => 'Release Ownership'] as $button => $label) {
			if (isset($_POST['dgpd_action_' . $button])) {
				if ($action !== '') {
					throw new RuntimeException(gettext('Multiple DGPD actions submitted'));
				}
				$action = $label;
			}
		}
		$interface = $_POST['dgpd_interface'] ?? $_GET['interface'] ?? '';
		if (is_array($interface)) {
			$interface = reset($interface);
		}
		$interface = (string)$interface;
		if ($action === '' && !$_POST) {
			$state = dgpd_state();
			if (isset($state['pending']) && !is_subsystem_dirty('dhcpd6')) {
				dgpd_gui_result(dgpd_reconcile());
			} elseif (!isset($state['pending'])) {
				$ownership = dgpd_sync_native_ownership();
				if (str_starts_with($ownership, 'NATIVE-ACCEPTED:')) {
					dgpd_gui_result($ownership);
				}
			}
		} elseif ($action === 'Save Settings') {
			$state = dgpd_state();
			$logging_changed = dgpd_save_logging_setting();
			if (isset($state['pending'])) {
				$result = dgpd_native_apply();
				$after_native_apply = true;
				dgpd_gui_result($result);
			} elseif ($logging_changed) {
				dgpd_gui_result(dgpd_debug_enabled() ?
					gettext('Debug logging enabled.') : gettext('Debug logging disabled.'));
			} else {
				dgpd_gui_result(gettext('Settings are unchanged.'));
			}
		} elseif ($action === 'Acknowledge') {
			$state = dgpd_state();
			if (isset($state['notice'])) {
				unset($state['notice']);
				dgpd_save($state, 'DGPD: acknowledge downstream prefix change');
			}
			dgpd_gui_result(gettext('Downstream-impact notice acknowledged.'));
		} elseif ($action === 'Recover') {
			dgpd_gui_result(dgpd_recover_pending());
		} elseif ($action === 'Restore DGPD Assignment') {
			dgpd_gui_result(dgpd_restore_assignment($interface));
		} elseif ($action === 'Accept Native Change') {
			dgpd_gui_result(dgpd_accept_native($interface));
		} elseif ($action === 'Release Ownership') {
			dgpd_gui_result(dgpd_release_ownership($interface));
		} elseif (in_array($action, ['Apply Assignment', 'Disable', 'Enable', 'Delete'], true)) {
			$state = dgpd_state();
			$requested = $action === 'Apply Assignment' ? [
				'interface' => $interface, 'prefix' => trim((string)($_POST['dgpd_prefix'] ?? '')),
				'prefix_len' => $_POST['dgpd_prefix_len'] ?? '',
				'delegated_len' => $_POST['dgpd_prefix_len'] ?? ''] : null;
			$owned = $state['assignments'][$interface] ?? null;
			$changes_downstream = false;
			if ($action === 'Apply Assignment' && is_array($owned) &&
			    ctype_digit((string)$requested['prefix_len']) && is_ipaddrv6($requested['prefix'])) {
				$length = (int)$requested['prefix_len'];
				if ($length >= 1 && $length <= 64) {
					$changes_downstream = dgpd_network($requested['prefix'], $length) !== $owned['prefix'] ||
						$length !== (int)$owned['prefix_len'] || $length !== (int)$owned['delegated_len'];
				}
			}
			if ($changes_downstream && !isset($_POST['dgpd_downstream_confirm'])) {
				throw new RuntimeException(gettext('Confirm that downstream routers may require DHCPv6-PD renewal or manual reconfiguration'));
			}
			$operation = ['Apply Assignment' => 'apply', 'Disable' => 'disable',
				'Enable' => 'enable', 'Delete' => 'delete'][$action];
			$result = dgpd_transition($interface, $operation, $requested, isset($_POST['dgpd_manage']),
				isset($_POST['dgpd_revoke_confirm']));
			dgpd_gui_result($operation === 'delete' ?
				gettext('Prefix deleted. Save Settings to finalize operation') : $result);
		} elseif ($action === 'Propose') {
			dgpd_require_ready();
			dgpd_target($interface, dgpd_live());
			$report = dgpd_reserve_report(dgpd_allocator($interface, true), dgpd_state(), $interface);
			dgpd_sources($interface, $report);
			$pool = $report['proposals'][$interface]['pd-pool'] ?? null;
			if (!is_array($pool)) {
				throw new RuntimeException(gettext('No free allocator proposal'));
			}
			dgpd_gui_result(sprintf(
				gettext('Proposal (not saved): %s/%d -> /%d. Enter these values to apply.'),
				$pool['prefix'], $pool['prefix-len'], $pool['delegated-len']));
		}
	} catch (Throwable $error) {
		dgpd_notice($error->getMessage(), true);
	}
	foreach (dgpd_wait_prerequisites($after_native_apply) as $check) {
		if (!$check['ready']) {
			dgpd_notice($check['name'] . ': ' . $check['message'], true);
		}
	}
	try {
		$state = dgpd_state();
		dgpd_close_orphan_ownership_notices($state);
		dgpd_validate_custom_json();
		$live = $after_native_apply ? dgpd_wait_live(true) : dgpd_live();
		$models = [];
		foreach (config_get_path('dhcpdv6', []) as $name => $settings) {
			if (!is_string($name) || !preg_match('/^[a-z][a-z0-9_]*$/D', $name) ||
			    !config_path_enabled("interfaces/{$name}")) {
				continue;
			}
			$failure = dgpd_interface_failure($name, $live);
			if ($failure !== null) {
				continue;
			}
			try {
				$report = dgpd_reserve_report(dgpd_allocator($name, true), $state, $name);
				$sources = dgpd_sources($name, $report);
				$pool = $report['proposals'][$name]['pd-pool'] ?? null;
				$owned = $state['assignments'][$name] ?? null;
				$observation = ['status' => 'matched', 'candidate' => null, 'message' => ''];
				if ($owned !== null) {
					$observation = dgpd_ownership_observation($name, $owned, $state, $report, $live);
					if ($observation['status'] !== 'matched') {
						dgpd_publish_ownership_notice($name, $observation['message'] .
							gettext('; resolve in Services > Dynamic GUA-PD'));
						if ($interface === '' || $interface === $name) {
							dgpd_notice($observation['message'], true);
						}
					} else {
						dgpd_close_ownership_notice($name);
						dgpd_validate_assignment($owned, $report);
						$present = dgpd_pool_present($live, $name, $owned);
						if (!isset($state['pending']) && ($present !== (($owned['status'] ?? 'active') === 'active'))) {
							throw new RuntimeException(gettext('Native Kea runtime verification failed'));
						}
					}
				}
				$lease_count = 0;
				if (is_array($owned) && ($owned['status'] ?? 'active') === 'active') {
					$target = dgpd_target($name, $live);
					$lease_count = count(dgpd_assignment_leases($target['subnet_id'], $owned));
				}
				$models[$name] = ['upstream' => $sources['upstream'],
					'proposal' => $pool, 'candidates' => $report['candidates'][$name] ?? [],
					'blocks' => $report['blocks'] ?? [],
					'current' => $owned, 'pending' => ($state['pending']['interface'] ?? '') === $name,
					'conflict' => $observation['status'] === 'conflict',
					'adoptable' => $observation['status'] === 'adoptable', 'lease_count' => $lease_count];
			} catch (Throwable $error) {
				if ($interface === '' || $interface === $name) {
					dgpd_notice($name . ': ' . $error->getMessage(), true);
				}
				if (($interface === '' || $interface === $name) && is_process_running('radvd') && is_readable('/var/etc/radvd.conf')) {
					foreach (dgpd_ra_prefixes(file_get_contents('/var/etc/radvd.conf'),
					    get_real_interface($name, 'inet6')) as $prefix) {
						dgpd_notice(sprintf(gettext('RA fallback: %s/%d advertised; upstream unconfirmed, changes blocked.'),
						    $prefix['prefix'], $prefix['prefix_len']), true);
					}
				}
			}
		}
		if (!$models) {
			dgpd_notice(gettext('No tracked interface found. Enable Track Interface and Kea DHCPv6 on the desired interface.'), true);
		}
		dgpd_gui_fields($models, $interface, $action === 'Propose');
	} catch (Throwable $error) {
		dgpd_notice($error->getMessage(), true);
		dgpd_notice(gettext('No tracked interface found. Enable Track Interface and Kea DHCPv6 on the desired interface.'), true);
		dgpd_gui_fields([], $interface, false);
	}
}

/* Operation confirmations belong to their own panel, never to prerequisite alerts. */
function dgpd_gui_result(string $message): void {
	if (str_starts_with($message, 'INFO: PD Pool Prefix normalized')) {
		dgpd_alert(substr($message, strlen('INFO: ')), 'success');
		return;
	}
	$level = 'success';
	if ($message === 'SAVED: activation and verification pending') {
		$level = 'info';
	} elseif (preg_match('/^(PENDING|STALE|CONFLICT):/', $message) || str_contains(strtolower($message), 'pending')) {
		$level = 'warning';
	} elseif ($message === gettext('Prefix deleted. Save Settings to finalize operation')) {
		$level = 'info';
	} elseif (str_starts_with($message, 'Proposal (not saved):') ||
	    str_starts_with($message, 'UNCHANGED:') ||
	    str_starts_with($message, 'Save Settings restored;')) {
		$level = 'info';
	}
	dgpd_alert($message, $level);
}

/* Localize XML-backed fields because pkg_edit.php does not call gettext() for them. */
function dgpd_gui_localize_fields(array $fields): array {
	$labels = [
		'dgpd_action_acknowledge' => gettext('Acknowledge Notice'),
		'dgpd_proposals' => gettext('Available Prefixes'),
		'dgpd_current' => gettext('Current Assignment'),
		'dgpd_interface' => gettext('Interface for Delegation'),
		'dgpd_upstream' => gettext('Upstream Pool'),
		'dgpd_prefix' => gettext('PD Pool Prefix'),
		'dgpd_prefix_len' => gettext('Pool LEN'),
		'dgpd_downstream_confirm' => gettext('Downstream Impact'),
		'dgpd_manage' => gettext('Allow Interface Management'),
		'dgpd_action_propose' => gettext('Propose'),
		'dgpd_action_apply' => gettext('Apply Assignment'),
		'dgpd_action_native_apply' => gettext('Save Settings'),
		'dgpd_action_disable' => gettext('Disable'),
		'dgpd_action_enable' => gettext('Enable'),
		'dgpd_action_delete' => gettext('Delete'),
		'dgpd_action_recover' => gettext('Recover'),
		'dgpd_action_restore_assignment' => gettext('Restore Assignment'),
		'dgpd_action_accept_native' => gettext('Accept Native Change'),
		'dgpd_action_release_ownership' => gettext('Release Ownership'),
		'dgpd_debug' => gettext('Enable Debug Logging'),
		'dgpd_usage' => gettext('Usage'),
	];
	$descriptions = [
		'dgpd_action_acknowledge' => gettext(
			'Confirm that the persistent downstream impact notice was reviewed.'),
		'dgpd_proposals' => gettext('Loading allocator proposals.'),
		'dgpd_current' => gettext('No interface selected.'),
		'dgpd_interface' => gettext('Select an eligible Track6 interface with active Kea DHCPv6.'),
		'dgpd_upstream' => gettext('Allocator upstream prefix and pool length.'),
		'dgpd_prefix' => gettext('IPv6 address of the Kea delegation pool, without a CIDR length.'),
		'dgpd_prefix_len' => gettext('Prefix length of the complete Kea PD pool. Kea delegates this complete prefix to one downstream router.'),
		'dgpd_downstream_confirm' => gettext('I understand that changing an existing delegated prefix may require DHCPv6-PD renewal or manual reconfiguration on downstream routers.'),
		'dgpd_manage' => gettext('Explicitly authorize DGPD to manage this interface. Existing native PD assignments remain protected.'),
		'dgpd_action_propose' => gettext(
			'Copy a free, unreserved candidate into the editor without saving it.'),
		'dgpd_action_apply' => gettext('Validate and stage the displayed assignment.'),
		'dgpd_action_disable' => gettext(
			'Remove the native pool and retain the DGPD reservation.'),
		'dgpd_action_enable' => gettext('Revalidate and restore a disabled reservation.'),
		'dgpd_action_delete' => gettext(
			'Remove the native pool and release its DGPD reservation.'),
		'dgpd_action_recover' => gettext(
			'Restore Save Settings for an interrupted pending operation.'),
		'dgpd_action_restore_assignment' => gettext(
			'Replace an external native change with the saved DGPD assignment.'),
		'dgpd_action_accept_native' => gettext(
			'Adopt a validated, upstream-contained, collision-free native assignment.'),
		'dgpd_action_release_ownership' => gettext(
			'Stop managing the assignment without changing native DHCPv6 settings.'),
		'dgpd_debug' => gettext(
			'Record detailed allocator, prefix-source, reconciliation, Kea validation, and retry decisions. ' .
			'Disable this option during normal operation.'),
	];
	foreach ($fields as &$field) {
		$name = $field['fieldname'] ?? '';
		if (isset($labels[$name])) {
			$field['fielddescr'] = $labels[$name];
		}
		if (isset($descriptions[$name])) {
			$field['description'] = $descriptions[$name];
		}
		if ($name === 'dgpd_action_native_apply') {
			unset($field['description']);
		}
		if ($name === 'dgpd_usage') {
			$field['description'] = dgpd_gui_usage();
		}
	}
	unset($field);
	return $fields;
}

function dgpd_gui_usage(): string {
	$items = [
		gettext('Propose') => gettext(
			'Copy the displayed allocator proposal into PD Pool Prefix and Pool LEN, replacing current edits. ' .
			'This does not save or reserve a prefix and does not change services. ' .
			'Apply Assignment checks availability again.'),
		gettext('Apply Assignment') => gettext(
			'Validate and stage the assignment. Use Save Settings to commit it.'),
		gettext('Allow Interface Management') => gettext(
			'Confirm ownership once when DGPD starts managing an interface. The confirmation is reset when ' .
			'another interface is selected.'),
		gettext('Downstream Impact') => gettext(
			'Confirm the warning when an existing delegated prefix is changed. The confirmation remains hidden ' .
			'when the downstream prefix is unchanged.'),
		gettext('Disable') => gettext(
			'Disable the native PD pool while retaining the DGPD reservation. An active owned IA_PD lease ' .
			'is revoked only after a downstream-interruption warning is confirmed.'),
		gettext('Enable') => gettext('Revalidate and restore a disabled reservation.'),
		gettext('Delete') => gettext(
			'Remove the native PD pool and release the DGPD reservation. Warning: Active IA_PD leases in ' .
			'this DGPD-owned pool will be revoked. This interrupts downstream prefix delegation and may ' .
			'require renewal or manual reconfiguration.'),
		gettext('Recover') => gettext(
			'Recover a saved operation after resolving a failure. This does not reset assignments or ' .
			'remove leases.'),
		gettext('Acknowledge Notice') => gettext(
			'Acknowledge the persistent downstream-impact notice after checking affected routers.'),
		gettext('Restore Assignment') => gettext(
			'Revalidate and restore the saved DGPD assignment.'),
		gettext('Accept Native Change') => gettext(
			'Accept a fully validated, upstream-contained and collision-free native Kea PD change as the ' .
			'new DGPD assignment.'),
		gettext('Release Ownership') => gettext(
			'Release the DGPD reservation while leaving the current native DHCPv6 configuration unchanged.'),
		gettext('Save Settings') => gettext('Commit changes of settings.'),
		gettext('Enable Debug Logging') => gettext(
			'Record detailed diagnostics for evaluation and error reports.'),
	];
	$html = '<div id="dgpd-usage" class="infoblock"><ul>';
	foreach ($items as $label => $description) {
		$html .= '<li><strong>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</strong><ul><li>' .
			htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</li></ul></li>';
	}
	return $html . '</ul></div>';
}

/* Populate native XML defaults without storing presentation data in config.xml. */
function dgpd_gui_fields(array $models, string $selected, bool $propose): void {
	global $pkg;
	$pkg['fields']['field'] = dgpd_gui_localize_fields($pkg['fields']['field'] ?? []);
	/* Ignore obsolete transient form rows in memory; preserve persistent state. */
	config_del_path('installedpackages/dgpd/config');
	if (!isset(dgpd_state()['notice'])) {
		$pkg['fields']['field'] = array_values(array_filter($pkg['fields']['field'] ?? [],
			fn($field) => ($field['fieldname'] ?? '') !== 'dgpd_action_acknowledge'));
	}
	if (!isset($models[$selected])) {
		$selected = '';
		foreach ($models as $name => $candidate) {
			if (($candidate['current'] ?? null) !== null) {
				$selected = $name;
				break;
			}
		}
		if ($selected === '') {
			$selected = (string)(array_key_first($models) ?? '');
		}
	}
	$GLOBALS['dgpd_gui_models'] = $models;
	$model = $models[$selected] ?? null;
	$pool = $model['proposal'] ?? null;
	$current = $model['current'] ?? null;
	$prefix = $current !== null && !$propose ? $current['prefix'] : ($pool['prefix'] ?? '');
	$prefix_length = $current !== null && !$propose ? $current['prefix_len'] : ($pool['prefix-len'] ?? '');
	foreach ($pkg['fields']['field'] ?? [] as $index => $field) {
		$name = $field['fieldname'] ?? '';
		if ($name === 'dgpd_interface') {
			$field['default_value'] = $selected;
			$field['options'] = ['option' => []];
			foreach (array_keys($models) as $interface) {
				$field['options']['option'][] = ['name' => strtoupper($interface), 'value' => $interface];
			}
		}
		if ($name === 'dgpd_prefix') {
			$field['default_value'] = $prefix;
		}
		if ($name === 'dgpd_prefix_len') {
			$field['default_value'] = $prefix_length;
			$field['options'] = ['option' => []];
			for ($len = (int)($model['upstream']['prefix_len'] ?? 64); $len <= 64; $len++) {
				$field['options']['option'][] = ['name' => '/' . $len, 'value' => (string)$len];
			}
		}
		if ($name === 'dgpd_upstream') {
			$field['description'] = '<span id="dgpd-upstream-prefix">' .
				htmlspecialchars($model['upstream']['prefix'] ?? '', ENT_QUOTES, 'UTF-8') .
				'</span> &nbsp; <strong>' . gettext('Pool') . '</strong> <span id="dgpd-upstream-length">/' .
				(int)($model['upstream']['prefix_len'] ?? 0) . '</span>';
		}
		if ($name === 'dgpd_current') {
			$field['description'] = dgpd_gui_current($selected, $model);
		}
		if ($name === 'dgpd_proposals') {
			$field['description'] = '<div class="panel panel-default"><div class="panel-body" id="dgpd-proposals">' .
				dgpd_gui_proposals($model) . '</div></div>';
		}
		if ($name === 'dgpd_debug') {
			$field['default_value'] = dgpd_debug_enabled() ? 'on' : '';
		}
		$pkg['fields']['field'][$index] = $field;
	}
}

function dgpd_gui_current(string $interface, ?array $model): string {
	$current = $model['current'] ?? null;
	if ($current === null) {
		return '<span id="dgpd-current"><span class="label label-default">' .
			htmlspecialchars(gettext('UNMANAGED'), ENT_QUOTES, 'UTF-8') . '</span></span>';
	}
	$status = strtoupper(($model['conflict'] ?? false) || ($model['adoptable'] ?? false) ? 'conflict' :
		($model['pending'] ? 'pending' : ($current['status'] ?? 'active')));
	$status_classes = [
		'ACTIVE' => 'label-success',
		'PENDING' => 'label-info',
		'DISABLED' => 'label-warning',
		'WAITING' => 'label-warning',
		'STALE' => 'label-danger',
		'CONFLICT' => 'label-danger',
	];
	$status_class = $status_classes[$status] ?? 'label-danger';
	$status_labels = [
		'ACTIVE' => gettext('ACTIVE'),
		'PENDING' => gettext('PENDING'),
		'DISABLED' => gettext('DISABLED'),
		'WAITING' => gettext('WAITING'),
		'STALE' => gettext('STALE'),
		'CONFLICT' => gettext('CONFLICT'),
	];
	$status_label = $status_labels[$status] ?? $status;
	$cidr = $current['prefix'] . '/' . $current['prefix_len'] . ' -> /' . $current['delegated_len'];
	$link = '/services_dhcpv6.php?if=' . rawurlencode($interface);
	return '<span id="dgpd-current"><span class="label ' . $status_class . '">' .
		htmlspecialchars($status_label, ENT_QUOTES, 'UTF-8') . '</span> &nbsp; <code>' .
		htmlspecialchars($cidr, ENT_QUOTES, 'UTF-8') . '</code> &nbsp; <a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' .
		htmlspecialchars(gettext('Open native DHCPv6 settings'), ENT_QUOTES, 'UTF-8') . '</a></span>';
}

function dgpd_gui_proposals(?array $model): string {
	if ($model === null) {
		return htmlspecialchars(gettext('No eligible interface or proposal available.'), ENT_QUOTES, 'UTF-8');
	}
	$pool = $model['proposal'];
	$html = '<p>' . htmlspecialchars(gettext('Proposal (not reserved)'), ENT_QUOTES, 'UTF-8') . ': <code>' .
		($pool ? htmlspecialchars($pool['prefix'] . '/' . $pool['prefix-len'] . ' -> /' . $pool['delegated-len'], ENT_QUOTES, 'UTF-8') : gettext('Unavailable')) . '</code></p>';
	if (!$pool) {
		$occupied = [];
		$upstream = $model['upstream'];
		foreach ($model['blocks'] ?? [] as $block) {
			if (!isset($block['prefix'], $block['prefix_len'], $block['reason']) ||
			    !dgpd_overlap($upstream, $block)) {
				continue;
			}
			$key = dgpd_network($block['prefix'], (int)$block['prefix_len']) . '/' . (int)$block['prefix_len'];
			$occupied[$key][] = (string)$block['reason'];
		}
		$html .= '<p><strong>' . htmlspecialchars(gettext('No collision-free proposal.'), ENT_QUOTES, 'UTF-8') .
			'</strong> ' . htmlspecialchars(gettext('The current upstream is occupied by these native networks:'), ENT_QUOTES, 'UTF-8') . '</p><ul>';
		foreach (array_slice($occupied, 0, 12, true) as $cidr => $reasons) {
			$html .= '<li><code>' . htmlspecialchars($cidr, ENT_QUOTES, 'UTF-8') . '</code> — ' .
				htmlspecialchars(implode('; ', array_unique($reasons)), ENT_QUOTES, 'UTF-8') . '</li>';
		}
		$html .= '</ul>';
	}
	if ($model['pending']) {
		$html .= '<p>' . gettext('Native apply pending. Use Recover after resolving a failure.') . '</p>';
	}
	foreach ($model['candidates'] as $length => $entry) {
		$html .= '<details><summary>/' . (int)$length . ' (' . (int)$entry['count'] . ')</summary><p>';
		foreach ($entry['prefixes'] as $cidr) {
			$html .= '<code>' . htmlspecialchars($cidr, ENT_QUOTES, 'UTF-8') . '</code><br />';
		}
		if ($entry['count'] > count($entry['prefixes'])) {
			$html .= htmlspecialchars(gettext('Showing the first 256 candidates.'), ENT_QUOTES, 'UTF-8');
		}
		$html .= '</p></details>';
	}
	return $html;
}

/* Native framework after-form hook: update display and defaults on interface changes. */
function dgpd_gui_after_form(): void {
	$models = $GLOBALS['dgpd_gui_models'] ?? [];
	foreach ($models as $interface => &$model) {
		$model['html'] = dgpd_gui_proposals($model);
		$model['current_html'] = dgpd_gui_current($interface, $model);
	}
	unset($model);
	$json = json_encode($models, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
	$group_labels = json_encode([
		'workflow' => gettext('Assignment Workflow'),
		'state' => gettext('Assignment State'),
		'conflict' => gettext('Conflict Resolution'),
		'commit' => gettext('Commit Changes'),
	], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
	$ui_labels = json_encode([
		'saving' => gettext('Saving staged settings'),
		'warning' => gettext('Warning'),
		'unusable_prefix' => gettext('Prefix not aligned or usable. Please check your settings.'),
		'interrupt_downstream' => gettext(
			'This action will interrupt the downstream network and revoke its active IA_PD lease. Are you sure?'),
	], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
	echo '<script type="text/javascript">const dgpdModels = ' . $json .
		'; const dgpdGroupLabels = ' . $group_labels .
		'; const dgpdUiLabels = ' . $ui_labels . ';';
echo <<<'DGPD_JS'
(function () {
	const form = document.getElementById('dgpd_interface');
	if (!form) {
		return;
	}
	const formElement = form.closest('form');
	function showTransientWarning(message) {
		const warning = document.createElement('div');
		warning.className = 'alert alert-warning dgpd-ui-transient';
		warning.setAttribute('role', 'alert');
		const label = document.createElement('strong');
		label.textContent = dgpdUiLabels.warning;
		warning.append(label, document.createElement('br'), document.createTextNode(message));
		formElement.prepend(warning);
		window.setTimeout(function () { warning.remove(); }, 3000);
	}
	const savingAlert = document.createElement('div');
	savingAlert.className = 'alert alert-info dgpd-saving-alert';
	savingAlert.setAttribute('role', 'status');
	savingAlert.setAttribute('aria-live', 'polite');
	savingAlert.hidden = true;
	const savingText = document.createElement('strong');
	savingText.textContent = dgpdUiLabels.saving;
	const savingDots = document.createElement('span');
	savingDots.className = 'dgpd-saving-dots';
	savingDots.setAttribute('aria-hidden', 'true');
	savingDots.textContent = '...';
	savingAlert.append(savingText, savingDots);
	formElement.prepend(savingAlert);
	const savingStyle = document.createElement('style');
	savingStyle.textContent = `
		.dgpd-saving-dots {
			display: inline-block;
			overflow: hidden;
			vertical-align: bottom;
			width: 0;
			animation: dgpd-saving-dots 1.2s steps(4, end) infinite alternate;
		}
		.dgpd-submit-locked button[type="submit"] {
			pointer-events: none;
		}
		@keyframes dgpd-saving-dots {
			to { width: 1.5em; }
		}
		@media (prefers-reduced-motion: reduce) {
			.dgpd-saving-dots { animation: none; width: 1.5em; }
		}`;
	document.head.appendChild(savingStyle);
	let saving = false;
	formElement.addEventListener('submit', function (event) {
		if (saving) {
			event.preventDefault();
			event.stopImmediatePropagation();
			return;
		}
		const submitter = event.submitter || document.activeElement;
		if (!submitter || submitter.id !== 'dgpd_action_native_apply') {
			return;
		}
		saving = true;
		savingAlert.hidden = false;
		formElement.classList.add('dgpd-submit-locked');
		formElement.querySelectorAll('button[type="submit"]').forEach(function (button) {
			button.setAttribute('aria-disabled', 'true');
		});
		savingAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	});
	function configureActionRow(firstButtonId, labelText) {
		const firstButton = document.getElementById(firstButtonId);
		const row = firstButton ? firstButton.closest('.form-group') : null;
		if (!row) {
			return;
		}
		const label = row.querySelector('label.control-label span');
		if (label) {
			label.textContent = labelText;
		}
		row.querySelectorAll('button[id^="dgpd_action_"]').forEach(function (button) {
			const column = button.parentElement;
			if (!column) {
				return;
			}
			Array.from(column.classList).forEach(function (className) {
				if (className.indexOf('col-sm-') === 0) {
					column.classList.remove(className);
				}
			});
			column.classList.add('col-sm-2');
			column.style.paddingLeft = '20px';
			column.style.paddingRight = '20px';
		});
	}
	configureActionRow('dgpd_action_propose', dgpdGroupLabels.workflow);
	configureActionRow('dgpd_action_enable', dgpdGroupLabels.state);
	configureActionRow('dgpd_action_recover', dgpdGroupLabels.conflict);
	configureActionRow('dgpd_action_native_apply', dgpdGroupLabels.commit);
	const refresh = document.getElementById('submit');
	if (refresh) {
		const refreshRow = refresh.closest('.form-group');
		if (refreshRow) {
			refreshRow.remove();
		} else {
			refresh.remove();
		}
	}
	const downstreamConfirm = document.getElementById('dgpd_downstream_confirm');
	const downstreamRow = downstreamConfirm ? downstreamConfirm.closest('.form-group') : null;
	const revokeConfirm = document.createElement('input');
	revokeConfirm.type = 'hidden';
	revokeConfirm.name = 'dgpd_revoke_confirm';
	revokeConfirm.value = '';
	form.closest('form').appendChild(revokeConfirm);
	function updateDownstreamImpact() {
		const model = dgpdModels[form.value];
		const current = model && model.current;
		const prefix = document.getElementById('dgpd_prefix');
		const poolLength = document.getElementById('dgpd_prefix_len');
		const changed = Boolean(current && prefix && poolLength &&
			(prefix.value.trim().toLowerCase() !== String(current.prefix).toLowerCase() ||
			 String(poolLength.value) !== String(current.prefix_len) ||
			 String(poolLength.value) !== String(current.delegated_len)));
		if (downstreamRow) {
			downstreamRow.style.display = changed ? '' : 'none';
		}
		if (!changed && downstreamConfirm) {
			downstreamConfirm.checked = false;
		}
	}
	function update(proposal) {
		const model = dgpdModels[form.value];
		const prefix = document.getElementById('dgpd_prefix');
		const poolLength = document.getElementById('dgpd_prefix_len');
		const apply = document.getElementById('dgpd_action_apply');
		const nativeApply = document.getElementById('dgpd_action_native_apply');
		const disable = document.getElementById('dgpd_action_disable');
		const enable = document.getElementById('dgpd_action_enable');
		const remove = document.getElementById('dgpd_action_delete');
		const restore = document.getElementById('dgpd_action_restore_assignment');
		const acceptNative = document.getElementById('dgpd_action_accept_native');
		const releaseOwnership = document.getElementById('dgpd_action_release_ownership');
		document.getElementById('dgpd-proposals').innerHTML = model ? model.html : '';
		document.getElementById('dgpd-upstream-prefix').textContent = model ? model.upstream.prefix : '';
		document.getElementById('dgpd-upstream-length').textContent = model ? '/' + model.upstream.prefix_len : '';
		document.getElementById('dgpd-current').innerHTML = model ? model.current_html : 'UNMANAGED';
		poolLength.replaceChildren();
		if (model) {
			for (let n = Number(model.upstream.prefix_len); n <= 64; n++) {
				poolLength.add(new Option('/' + n, n));
			}
		}
		const current = model && !proposal ? model.current : null;
		const pool = model ? model.proposal : null;
		prefix.value = current ? current.prefix : (pool ? pool.prefix : '');
		poolLength.value = current ? current.prefix_len : (pool ? pool['prefix-len'] : '');
		const ownershipConflict = model && (model.conflict || model.adoptable);
		apply.disabled = !model || model.pending || ownershipConflict || !prefix.value;
		nativeApply.disabled = false;
		const status = model && model.current ? (model.current.status || 'active') : '';
		disable.disabled = !model || model.pending || ownershipConflict || status !== 'active';
		enable.disabled = !model || model.pending || ownershipConflict || status !== 'disabled';
		remove.disabled = !model || model.pending || ownershipConflict || !status;
		const hasActiveLease = Boolean(model && Number(model.lease_count) > 0);
		const warningTitle = 'interrupt downstream service and revoke the active IA_PD lease';
		if (hasActiveLease) {
			remove.title = warningTitle;
		} else {
			remove.removeAttribute('title');
		}
		revokeConfirm.value = '';
		restore.disabled = !ownershipConflict || model.pending;
		acceptNative.disabled = !model || !model.adoptable || model.pending;
		releaseOwnership.disabled = !ownershipConflict || model.pending;
		prefix.dispatchEvent(new Event('input'));
		updateDownstreamImpact();
	}
	form.addEventListener('change', function () {
		document.getElementById('dgpd_manage').checked = false;
		update(false);
	});
	document.getElementById('dgpd_action_propose').addEventListener('click', function (event) {
		event.preventDefault();
		const model = dgpdModels[form.value];
		if (!model || !model.proposal) {
			showTransientWarning(dgpdUiLabels.unusable_prefix);
			return;
		}
		update(true);
	});
	['dgpd_action_disable', 'dgpd_action_delete'].forEach(function (id) {
		document.getElementById(id).addEventListener('click', function (event) {
			const model = dgpdModels[form.value];
			if (!model || Number(model.lease_count) < 1) {
				return;
			}
			if (id === 'dgpd_action_disable' && !window.confirm(dgpdUiLabels.interrupt_downstream)) {
				event.preventDefault();
				event.stopPropagation();
				return;
			}
			revokeConfirm.value = 'on';
		});
	});
	document.getElementById('dgpd_prefix').addEventListener('input', function () {
		const model = dgpdModels[form.value];
		const poolLength = document.getElementById('dgpd_prefix_len');
		if (!model) {
			return;
		}
		document.getElementById('dgpd_action_apply').disabled = model.pending || model.conflict || model.adoptable ||
			!this.value || Number(poolLength.value) > 64;
		updateDownstreamImpact();
	});
	document.getElementById('dgpd_prefix_len').addEventListener('change', function () {
		document.getElementById('dgpd_prefix').dispatchEvent(new Event('input'));
	});
	update(false);
	document.getElementById('dgpd_prefix').dispatchEvent(new Event('input'));
	window.setTimeout(function () {
		document.querySelectorAll('.dgpd-success-transient').forEach(function (item) { item.remove(); });
	}, 3000);
})();
</script>
DGPD_JS;
}
