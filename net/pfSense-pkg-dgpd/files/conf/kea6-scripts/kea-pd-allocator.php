#!/usr/local/bin/php
<?php
/*
 * Copyright 2026 gebra
 * SPDX-License-Identifier: Apache-2.0
 *
 * Kea PD Allocator v0.3.5, 2026-10-09. Read-only pfSense allocator.
 * Usage: /usr/local/bin/php kea-pd-allocator.php [--target=opt3]
 * No changes to config.xml, Kea, leases, or pfSense services.
 */
declare(strict_types=1);

function fail(string $message): never {
	fwrite(STDERR, "ERROR: {$message}\n");
	exit(1);
}

function net(string $address, int $length): string {
	$packed = inet_pton($address);
	if ($packed === false || strlen($packed) !== 16 || $length < 0 || $length > 128) {
		fail("Invalid IPv6 CIDR: {$address}/{$length}");
	}
	for ($i = 0; $i < 16; $i++) {
		$remaining = $length - ($i * 8);
		$mask = $remaining >= 8 ? 255 : ($remaining <= 0 ? 0 : (255 << (8 - $remaining)) & 255);
		$packed[$i] = chr(ord($packed[$i]) & $mask);
	}
	return $packed;
}

function cidr(string $value): array {
	if (!preg_match('/^([^\/]+)\/(\d{1,3})$/', $value, $matches)) {
		fail("Invalid CIDR: {$value}");
	}
	$length = (int)$matches[2];
	return [net($matches[1], $length), $length];
}

function overlap(string $left, int $left_length, string $right, int $right_length): bool {
	$length = min($left_length, $right_length);
	return net(inet_ntop($left), $length) === net(inet_ntop($right), $length);
}

function child(string $base, int $parent_length, int $length, int $index): string {
	for ($bit = $parent_length; $bit < $length; $bit++) {
		$value = ($index >> ($length - $bit - 1)) & 1;
		$byte = intdiv($bit, 8);
		$base[$byte] = chr(ord($base[$byte]) | ($value << (7 - ($bit % 8))));
	}
	return $base;
}

function option(array $arguments, string $name, ?string $default = null): ?string {
	foreach ($arguments as $argument) {
		if (str_starts_with($argument, "--{$name}=")) {
			return substr($argument, strlen($name) + 3);
		}
	}
	return $default;
}

function guas(string $device): array {
	if (!preg_match('/^[A-Za-z0-9_.:-]+$/', $device)) {
		fail("Invalid interface name: {$device}");
	}
	$lines = [];
	exec('/sbin/ifconfig ' . escapeshellarg($device) . ' 2>/dev/null', $lines, $status);
	if ($status !== 0) {
		fail("ifconfig failed for {$device}");
	}
	$addresses = [];
	foreach ($lines as $line) {
		if (!preg_match('/\binet6\s+([0-9a-fA-F:]+)(?:%\S+)?\s+prefixlen\s+(\d+)/', $line, $matches)) {
			continue;
		}
		$packed = inet_pton($matches[1]);
		if ($packed === false || (ord($packed[0]) & 0xe0) !== 0x20) {
			continue;
		}
		$addresses[] = [
			'net' => net($matches[1], (int)$matches[2]),
			'len' => (int)$matches[2],
			'addr' => $matches[1],
		];
	}
	return $addresses;
}

function kea_config(string $socket): array {
	if (!is_socket_path($socket)) {
		fail("Kea DHCPv6 UNIX control socket not found: {$socket}");
	}
	$request = escapeshellarg('{"command":"config-get","service":["dhcp6"]}');
	$command = "/usr/bin/printf '%s\\n' {$request} | /usr/local/bin/socat -T 5 - UNIX-CONNECT:" .
		escapeshellarg($socket) . ' 2>/dev/null';
	$lines = [];
	exec($command, $lines, $status);
	if ($status !== 0) {
		fail('Kea config-get failed');
	}
	$response = json_decode(implode("\n", $lines), true);
	if (!is_array($response)) {
		fail('Invalid Kea JSON response');
	}
	if (array_is_list($response)) {
		$response = $response[0] ?? [];
	}
	if (($response['result'] ?? null) !== 0 || !isset($response['arguments']['Dhcp6'])) {
		fail('Kea config-get returned no successful Dhcp6 configuration');
	}
	return $response['arguments']['Dhcp6'];
}

function is_socket_path(string $path): bool {
	return str_starts_with($path, '/var/run/') && file_exists($path);
}

function add_block(array &$blocks, string $value, string $reason): void {
	[$network, $length] = cidr($value);
	$blocks[] = ['net' => $network, 'len' => $length, 'reason' => $reason];
}

$arguments = array_slice($argv, 1);
if (in_array('--help', $arguments, true) || in_array('-h', $arguments, true)) {
	echo "Usage: /usr/local/bin/php kea-pd-allocator.php [--upstream=IPv6/LEN] " .
		"[--target=opt3] [--config=/conf/config.xml] " .
		"[--socket=/var/run/kea/kea6-ctrl-socket] [--json|--inspect-json]\n";
	exit(0);
}
$upstream_option = option($arguments, 'upstream');
$target = option($arguments, 'target');
$config_path = option($arguments, 'config', '/conf/config.xml');
if (option($arguments, 'delegated-len') !== null || option($arguments, 'del-len') !== null) {
	fail('Delegation length is automatic in v0.3.5; omit --delegated-len/--del-len');
}
if (!is_readable($config_path)) {
	fail("Cannot read {$config_path}");
}
libxml_use_internal_errors(true);
$xml = simplexml_load_file($config_path, 'SimpleXMLElement', LIBXML_NONET);
if ($xml === false || !isset($xml->interfaces)) {
	fail('Cannot parse pfSense interfaces');
}
$interfaces = [];
foreach ($xml->interfaces->children() as $logical => $node) {
	$logical = (string)$logical;
	$device = trim((string)$node->if);
	if ($device === '') {
		continue;
	}
	$interfaces[$logical] = [
		'dev' => $device,
		'ipv6' => strtolower(trim((string)$node->ipaddrv6)),
		'trackwan' => trim((string)$node->{'track6-interface'}),
		'pdlen' => trim((string)$node->{'dhcp6-ia-pd-len'}),
		'guas' => guas($device),
	];
}
$wan_candidates = [];
foreach ($interfaces as $logical => $entry) {
	if ($entry['ipv6'] !== 'dhcp6' || $entry['pdlen'] === '' || !ctype_digit($entry['pdlen'])) {
		continue;
	}
	$sla_bits = (int)$entry['pdlen'];
	if ($sla_bits < 0 || $sla_bits > 64) {
		fail("Invalid DHCPv6 IA_PD SLA bits on {$logical}: {$sla_bits}");
	}
	$wan_candidates[$logical] = 64 - $sla_bits;
}
if (!$wan_candidates) {
	fail('No DHCPv6 WAN with configured IA_PD length');
}
$wan_data = [];
foreach ($wan_candidates as $wan => $upstream_length) {
	$tracks = [];
	$derived = [];
	foreach ($interfaces as $logical => $entry) {
		if ($entry['ipv6'] !== 'track6' || $entry['trackwan'] !== $wan) {
			continue;
		}
		if (!$entry['guas']) {
			fail("Track6 {$logical} ({$entry['dev']}) has no GUA");
		}
		$tracks[$logical] = $entry;
		foreach ($entry['guas'] as $gua) {
			if ($gua['len'] !== 64) {
				fail("Track6 {$logical} has unexpected GUA prefix length /{$gua['len']}");
			}
			$derived[inet_ntop(net($gua['addr'], $upstream_length))] = true;
		}
	}
	if (!$tracks) {
		continue;
	}
	if (count($derived) !== 1) {
		fail("Track6 addresses disagree on reconstructed upstream prefix for {$wan}: " .
			implode(', ', array_keys($derived)));
	}
	$prefix = array_key_first($derived) . '/' . $upstream_length;
	if ($upstream_option !== null) {
		[$provided_base, $provided_length] = cidr($upstream_option);
		if (count($wan_candidates) !== 1) {
			fail('--upstream cannot be used with multiple DHCPv6 WANs');
		}
		if ($provided_length !== $upstream_length || inet_ntop($provided_base) !== array_key_first($derived)) {
			fail("Explicit upstream '{$upstream_option}' disagrees with Track6 reconstruction '{$prefix}'");
		}
	}
	[$base, $upstream_length] = cidr($prefix);
	$wan_data[$wan] = [
		'base' => $base,
		'len' => $upstream_length,
		'tracks' => $tracks,
		'prefix' => $prefix,
	];
}
if (!$wan_data) {
	fail('No active Track6 interfaces linked to DHCPv6-PD WAN');
}
$occupied = [];
$blocks = [];
foreach ($interfaces as $logical => $entry) {
	foreach ($entry['guas'] as $gua) {
		$occupied[] = ['logical' => $logical, 'dev' => $entry['dev']] + $gua;
		$blocks[] = [
			'net' => $gua['net'],
			'len' => $gua['len'],
			'reason' => "Interface {$logical} ({$entry['dev']})",
		];
	}
}
$kea = kea_config(option($arguments, 'socket', '/var/run/kea/kea6-ctrl-socket'));
$subnets = $kea['subnet6'] ?? [];
if (!is_array($subnets)) {
	fail('Unexpected Kea subnet6 format');
}
$existing = [];
$kea_by_device = [];
foreach ($subnets as $subnet) {
	if (!is_array($subnet) || !isset($subnet['subnet'], $subnet['id'])) {
		fail('Kea subnet without subnet/id');
	}
	cidr($subnet['subnet']);
	if (isset($subnet['interface']) && is_string($subnet['interface'])) {
		$kea_by_device[$subnet['interface']][] = $subnet;
	}
	foreach ($subnet['pd-pools'] ?? [] as $pool) {
		if (!isset($pool['prefix'], $pool['prefix-len'], $pool['delegated-len'])) {
			fail('Malformed Kea PD pool');
		}
		$pool_cidr = $pool['prefix'] . '/' . $pool['prefix-len'];
		add_block($blocks, $pool_cidr, "Kea PD pool subnet-id {$subnet['id']}");
		$existing[] = $pool_cidr;
	}
	foreach ($subnet['pools'] ?? [] as $pool) {
		$pool_value = $pool['pool'] ?? null;
		if (!is_string($pool_value)) {
			fail('Malformed Kea address pool');
		}
		if (str_contains($pool_value, '-')) {
			fail("Kea address range '{$pool_value}' requires additional overlap analysis");
		}
		add_block($blocks, $pool_value, "Kea address pool subnet-id {$subnet['id']}");
	}
	foreach ($subnet['reservations'] ?? [] as $reservation) {
		if (!empty($reservation['prefixes'])) {
			fail('Kea host PD reservations detected; cannot safely allocate');
		}
	}
}
if (!empty($kea['reservations-global'])) {
	fail('Global Kea reservations require further validation');
}
if (!empty($kea['shared-networks'])) {
	fail('Kea shared networks not supported in v0.3.5');
}
$lease_database = $kea['lease-database'] ?? [];
if (($lease_database['type'] ?? null) !== 'memfile' || !is_string($lease_database['name'] ?? null)) {
	fail('Expected Kea memfile lease database');
}
$lease_file = $lease_database['name'];
if (!is_readable($lease_file)) {
	fail("Cannot read Kea leases: {$lease_file}");
}
$handle = fopen($lease_file, 'rb');
if ($handle === false) {
	fail('Cannot open lease file');
}
$header = fgetcsv($handle, 0, ',', '"', '\\');
if (!is_array($header)) {
	fail('Empty Kea lease file');
}
$columns = array_flip(array_map('trim', $header));
foreach (['address', 'prefix_len', 'lease_type'] as $column) {
	if (!isset($columns[$column])) {
		fail("Kea lease CSV lacks column {$column}");
	}
}
$pd_leases = 0;
while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
	if (count($row) === 1 && trim($row[0]) === '') {
		continue;
	}
	if (count($row) !== count($header)) {
		fail('Malformed Kea lease CSV row; refusing allocation');
	}
	$type = trim($row[$columns['lease_type']]);
	if ($type !== '2' && strtoupper($type) !== 'PD') {
		continue;
	}
	$address = trim($row[$columns['address']]);
	$length = trim($row[$columns['prefix_len']]);
	if (!ctype_digit($length)) {
		fail('Malformed PD lease prefix length');
	}
	add_block($blocks, "{$address}/{$length}", 'Historical/current Kea PD lease');
	$pd_leases++;
}
fclose($handle);
$targets = [];
foreach ($interfaces as $logical => $entry) {
	if ($target !== null && $logical !== $target) {
		continue;
	}
	$device = $entry['dev'];
	$matches = $kea_by_device[$device] ?? [];
	if (!$matches) {
		if ($target !== null) {
			fail("No Kea DHCPv6 subnet for target '{$target}' ({$device})");
		}
		continue;
	}
	if (count($matches) !== 1) {
		fail("Expected exactly one Kea subnet for {$logical} ({$device}), got " . count($matches));
	}
	$track_wan = $entry['trackwan'];
	if ($entry['ipv6'] === 'track6' && isset($wan_data[$track_wan])) {
		$wan = $track_wan;
	} elseif (count($wan_data) === 1) {
		$wan = array_key_first($wan_data);
	} else {
		fail("Cannot unambiguously associate Kea interface {$logical} with a DHCPv6-PD WAN");
	}
	$targets[$logical] = ['dev' => $device, 'subnet' => $matches[0], 'wan' => $wan];
}
if ($target !== null && !isset($targets[$target])) {
	fail("Target '{$target}' not found or not eligible");
}
if (!$targets) {
	fail('No Kea DHCPv6 target interfaces found');
}

/* Machine-readable output uses the same discovery and occupancy checks as proposals. */
$report = [
	'schema' => 1,
	'allocator' => '0.3.5',
	'upstreams' => [],
	'targets' => [],
	'blocks' => [],
];
foreach ($wan_data as $wan => $data) {
	$report['upstreams'][$wan] = [
		'prefix' => inet_ntop($data['base']),
		'prefix_len' => $data['len'],
		'tracks' => array_keys($data['tracks']),
	];
}
foreach ($targets as $logical => $data) {
	$report['targets'][$logical] = [
		'wan' => $data['wan'],
		'physical' => $data['dev'],
		'subnet_id' => $data['subnet']['id'],
	];
}
foreach ($blocks as $block) {
	$report['blocks'][] = [
		'prefix' => inet_ntop($block['net']),
		'prefix_len' => $block['len'],
		'reason' => $block['reason'],
	];
}
if (in_array('--inspect-json', $arguments, true)) {
	echo json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
	exit(0);
}

/* Return free candidates at one length within an upstream prefix. */
function free_candidates(string $base, int $upstream_length, int $delegated_length, array $blocks): array {
	if ($delegated_length < $upstream_length || $delegated_length > 64 ||
	    $delegated_length - $upstream_length > 16) {
		return [];
	}
	$free = [];
	$count = 1 << ($delegated_length - $upstream_length);
	for ($i = 0; $i < $count; $i++) {
		$candidate = child($base, $upstream_length, $delegated_length, $i);
		$conflict = false;
		foreach ($blocks as $block) {
			if (overlap($candidate, $delegated_length, $block['net'], $block['len'])) {
				$conflict = true;
				break;
			}
		}
		if (!$conflict) {
			$free[] = $candidate;
		}
	}
	return $free;
}
$observed_blocks = $blocks;
$target_groups = [];
foreach ($targets as $logical => $target_data) {
	$target_groups[$target_data['wan']][] = $logical;
}
$plans_by_wan = [];
$shortage = false;
foreach ($target_groups as $wan => $names) {
	$wan_entry = $wan_data[$wan];
	$chosen_length = null;
	$available = [];
	for ($length = $wan_entry['len']; $length <= 64; $length++) {
		$candidates = free_candidates($wan_entry['base'], $wan_entry['len'], $length, $blocks);
		if (count($candidates) >= count($names)) {
			$chosen_length = $length;
			$available = $candidates;
			break;
		}
	}
	if ($chosen_length === null) {
		$shortage = true;
		$plans_by_wan[$wan] = ['length' => null, 'free' => [], 'targets' => $names];
		continue;
	}
	$plans_by_wan[$wan] = ['length' => $chosen_length, 'free' => $available, 'targets' => $names];
	/* Reserve complete proposals immediately, including across other WAN groups. */
	foreach ($names as $index => $logical) {
		$blocks[] = [
			'net' => $available[$index],
			'len' => $chosen_length,
			'reason' => "Planned allocation {$logical}",
		];
	}
}
if (in_array('--json', $arguments, true)) {
	$report['proposals'] = [];
	$report['candidates'] = [];
	foreach ($targets as $logical => $data) {
		$wan_entry = $wan_data[$data['wan']];
		for ($length = $wan_entry['len']; $length <= 64; $length++) {
			$free = free_candidates($wan_entry['base'], $wan_entry['len'], $length, $observed_blocks);
			if (!$free) {
				continue;
			}
			$report['candidates'][$logical][$length] = [
				'count' => count($free),
				'prefixes' => array_map(
					fn($network) => inet_ntop($network) . '/' . $length,
					array_slice($free, 0, 256)
				),
			];
		}
	}
	foreach ($plans_by_wan as $plan) {
		if ($plan['length'] === null) {
			continue;
		}
		foreach ($plan['targets'] as $index => $logical) {
			$report['proposals'][$logical] = [
				'subnet-id' => $targets[$logical]['subnet']['id'],
				'pd-pool' => [
					'prefix' => inet_ntop($plan['free'][$index]),
					'prefix-len' => $plan['length'],
					'delegated-len' => $plan['length'],
				],
			];
		}
	}
	echo json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
	exit($shortage ? 2 : 0);
}
printf("Kea PD Allocator v0.3.5 [READ-ONLY]\nMode: %s | delegation length: AUTOMATIC\n",
	$target === null ? 'all Kea targets' : 'single target');
foreach ($wan_data as $wan => $data) {
	printf("WAN %s: %s (Track6-derived, NOT IA_PD-confirmed) | Track6 references: %d\n",
		$wan, $data['prefix'], count($data['tracks']));
}
echo "Active occupied GUA networks:\n";
foreach ($occupied as $occupied_network) {
	$relevant = false;
	foreach ($wan_data as $wan_entry) {
		if (overlap($wan_entry['base'], $wan_entry['len'], $occupied_network['net'], $occupied_network['len'])) {
			$relevant = true;
			break;
		}
	}
	if ($relevant) {
		printf("  %-7s %-14s %s/%d\n", $occupied_network['logical'], $occupied_network['dev'],
			inet_ntop($occupied_network['net']), $occupied_network['len']);
	}
}
printf("Kea configured PD pools: %d | PD lease rows (conservatively reserved): %d\n",
	count($existing), $pd_leases);
$planned = [];
foreach ($plans_by_wan as $wan => $plan) {
	printf("\nWAN %s: %d Kea target(s) | %s\n", $wan, count($plan['targets']),
		$plan['length'] === null ? 'INSUFFICIENT FREE PREFIX SPACE' :
		'automatic delegated-len /' . $plan['length']);
	if ($plan['length'] === null) {
		foreach ($plan['targets'] as $logical) {
			printf("  UNAVAILABLE: %s (%s), subnet-id %s\n", $logical,
				$targets[$logical]['dev'], (string)$targets[$logical]['subnet']['id']);
		}
		continue;
	}
	printf("  Free /%d candidates before assignments: %d\n", $plan['length'], count($plan['free']));
	foreach ($plan['targets'] as $index => $logical) {
		$target_data = $targets[$logical];
		$prefix = inet_ntop($plan['free'][$index]);
		$pool = [
			'prefix' => $prefix,
			'prefix-len' => $plan['length'],
			'delegated-len' => $plan['length'],
		];
		printf("  %-7s %-14s subnet-id %-4s PROPOSED: %s/%d -> delegated /%d\n",
			$logical, $target_data['dev'], (string)$target_data['subnet']['id'], $prefix,
			$plan['length'], $plan['length']);
		$planned[$logical] = ['subnet-id' => $target_data['subnet']['id'], 'pd-pool' => $pool];
	}
}
echo "\nPROPOSED ALLOCATIONS (NOT APPLIED):\n";
echo json_encode($planned, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "NOTE: IA_PD length is configured/requested, NOT independently verified. Read-only. No config-set or lease mutation.\n";
if ($shortage) {
	fwrite(STDERR, "WARNING: Not all Kea targets could be allocated a PD pool (maximum delegated length /64).\n");
	exit(2);
}
