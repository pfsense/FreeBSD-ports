<?php
/*
 * dhcp_lease_manager.inc
 *
 * Copyright (c) 2026 Branson Matheson
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

/*
 * Leases come from system_get_dhcpleases(), which reads whichever DHCPv4
 * server is configured (ISC dhcpd or Kea), so this package does not depend
 * on the backend. Each lease is enriched with:
 *   vendor / vendor_kind  manufacturer from the MAC (oui|virtual|private|unknown)
 *   type / type_source    device type (rule|manual|unknown)
 *   age                   seconds since the lease started
 *   is_static, staticmap_index, if, if_descr
 *
 * Everything above the "pfSense integration" section is pure PHP so it can be
 * unit tested without a firewall (see tests/ in the development repository).
 */

define('DLM_CONFIG_PATH', 'installedpackages/dhcpleasemanager');

/* ------------------------------------------------------------- helpers */

function dlm_normalize_mac($mac) {
	$hex = strtolower(preg_replace('/[^0-9a-fA-F]/', '', (string)$mac));
	if (strlen($hex) !== 12) {
		return strtolower(trim((string)$mac));
	}
	return implode(':', str_split($hex, 2));
}

/* Lease times from core are "YYYY/MM/DD HH:MM:SS" in UTC; empty for static entries. */
function dlm_to_epoch($value) {
	if ($value === null || $value === '' || $value === 'n/a' || $value === 'never') {
		return null;
	}
	if (is_int($value) || ctype_digit((string)$value)) {
		return (int)$value;
	}
	$ts = strtotime(trim((string)$value) . ' UTC');
	return ($ts === false) ? null : $ts;
}

/* -------------------------------------------------------- manufacturer */

/*
 * MAC prefixes that hypervisors and container runtimes assign to guests.
 * Checked before the OUI database: the database names the vendor
 * ("Microsoft", "Xensource") but not that the device is a guest, and
 * Docker's prefix is locally administered so it would otherwise look like
 * a phone's randomized MAC.
 */
define('DLM_VIRTUAL_PREFIXES', [
	'BC2411' => 'Proxmox VE (virtual)',
	'525400' => 'QEMU/KVM (virtual)',
	'005056' => 'VMware (virtual)',
	'000C29' => 'VMware (virtual)',
	'000569' => 'VMware (virtual)',
	'001C14' => 'VMware (virtual)',
	'00155D' => 'Hyper-V (virtual)',
	'0003FF' => 'Hyper-V (virtual)',
	'00163E' => 'Xen (virtual)',
	'080027' => 'VirtualBox (virtual)',
	'0A0027' => 'VirtualBox (virtual)',
	'001C42' => 'Parallels (virtual)',
	'0242'   => 'Docker (container)',
]);

/* Returns array(vendor, kind) where kind is oui|virtual|private|unknown. */
function dlm_vendor_for_mac($mac, $table) {
	$hex = strtoupper(str_replace(':', '', $mac));
	if (strlen($hex) < 6) {
		return array(gettext('Unknown'), 'unknown');
	}
	foreach (DLM_VIRTUAL_PREFIXES as $prefix => $name) {
		if (strncmp($hex, $prefix, strlen($prefix)) === 0) {
			return array($name, 'virtual');
		}
	}
	if (isset($table[substr($hex, 0, 6)])) {
		return array($table[substr($hex, 0, 6)], 'oui');
	}
	/* Locally administered bit set: phones/laptops with MAC randomization. */
	if (hexdec(substr($hex, 0, 2)) & 0x02) {
		return array(gettext('Private (randomized MAC)'), 'private');
	}
	return array(gettext('Unknown'), 'unknown');
}

/* -------------------------------------------------------- device types */

/* type id => array(label, Font Awesome 6 icon) */
define('DLM_TYPES', [
	'tv'           => ['TV', 'fa-tv'],
	'streaming'    => ['Apple TV / streaming', 'fa-circle-play'],
	'audio'        => ['Speaker / audio', 'fa-volume-high'],
	'light-switch' => ['Light switch / dimmer', 'fa-toggle-on'],
	'smart-plug'   => ['Smart plug', 'fa-plug'],
	'light'        => ['Light / bulb', 'fa-lightbulb'],
	'camera'       => ['Camera', 'fa-video'],
	'doorbell'     => ['Doorbell', 'fa-bell'],
	'thermostat'   => ['Thermostat', 'fa-temperature-half'],
	'energy'       => ['Energy monitor', 'fa-bolt'],
	'iot'          => ['IoT / sensor', 'fa-microchip'],
	'phone'        => ['Phone', 'fa-mobile-screen-button'],
	'tablet'       => ['Tablet', 'fa-tablet-screen-button'],
	'watch'        => ['Watch', 'fa-clock'],
	'computer'     => ['Computer', 'fa-laptop'],
	'server'       => ['Server / NAS', 'fa-server'],
	'vm'           => ['Virtual machine', 'fa-cubes'],
	'container'    => ['Container', 'fa-box'],
	'sbc'          => ['Raspberry Pi / SBC', 'fa-memory'],
	'printer'      => ['Printer', 'fa-print'],
	'3d-printer'   => ['3D printer', 'fa-cube'],
	'access-point' => ['Access point', 'fa-wifi'],
	'network'      => ['Network gear', 'fa-network-wired'],
	'game'         => ['Game console', 'fa-gamepad'],
	'unknown'      => ['Unknown', 'fa-circle-question'],
]);

/*
 * Ordered classification rules; the first rule whose conditions ALL match wins.
 * Conditions are case-insensitive regexes on lease fields ('hostname',
 * 'vendor', 'vendor_kind', 'descr'). Hostname rules come before vendor-only
 * rules because a name chosen by the owner ("kitchen-tv") says more than the
 * manufacturer of the device's network chip.
 */
define('DLM_TYPE_RULES', [
	/* --- hypervisor / container MACs: definitive, so they come first --- */
	['container',    ['vendor_kind' => '/^virtual$/', 'vendor' => '/\(container\)$/']],
	['vm',           ['vendor_kind' => '/^virtual$/']],

	/* --- hostname-driven (most specific first) --- */
	['3d-printer',   ['hostname' => '/(^|[^a-z])(ender|prusa|octopi|octoprint|bambu|voron|klipper|mainsail)/']],
	['printer',      ['hostname' => '/printer|laserjet|officejet|deskjet|^prn[-\d]/']],
	['doorbell',     ['hostname' => '/doorbell|^ring-/']],
	['camera',       ['hostname' => '/(^|[^a-z])(cam|camera|ipcam|nvr)([^a-z]|$)|webcam/']],
	['streaming',    ['hostname' => '/apple-?tv|roku|fire-?tv|firestick|chromecast|nvidia-?shield|google-?tv|onn-?4k/']],
	['game',         ['hostname' => '/xbox|playstation|ps[45]|nintendo|switch-?oled|steam-?deck/']],
	['tablet',       ['hostname' => '/ipad|tablet|kindle|galaxy-?tab|fire-?hd/']],
	['phone',        ['hostname' => '/iphone|android|galaxy|pixel-?\d|(^|-)s2\d(-|$)|phone/']],
	['watch',        ['hostname' => '/watch/']],
	['computer',     ['hostname' => '/macbook|mbp|imac|mac-?mini|mac-?studio|^mac\d*$|^mac-|desktop|laptop|notebook|workstation|windows|thinkpad|surface|(^|-)pc(-|$)|comp$/']],
	['access-point', ['hostname' => '/^ap-|(^|-)ap(\d|-|$)|(^|-)wap|unifi-?ap|(^|-)mr\d+/']],
	['network',      ['hostname' => '/^(sw|switch)-|router|gateway|firewall|(^|-)(mx|ms)\d+/',
	                  'vendor' => '/netgear|tp-link|cisco|meraki|ubiquiti|aruba|mikrotik|juniper|d-link|zyxel/']],
	['network',      ['hostname' => '/router|gateway|firewall|openwrt|(^|-)(mx|ms)\d+/']],
	['audio',        ['hostname' => '/sonos|homepod|speaker|soundbar|castaudio|studiolive|echo-?dot|alexa|(^|-)audio/']],
	['tv',           ['hostname' => '/(^|[^a-z])tv([^a-z]|$)|tv$|bravia|television/']],
	['smart-plug',   ['hostname' => '/plug|outlet|mss(1|6)\d\d|kasa|wemo/']],
	['light-switch', ['hostname' => '/switch|dimmer|(^|-)sw(-|$)|^sw-|mss5\d\d/']],
	['light',        ['hostname' => '/bulb|lamp|light|wled|(^|-)hue|lifx|nanoleaf|govee/']],
	['thermostat',   ['hostname' => '/thermostat|ecobee|(^|-)nest/']],
	['energy',       ['hostname' => '/envoy|enphase|(^|-)sense-|powerwall|solar|emporia/']],
	['server',       ['hostname' => '/(^|-)pve|proxmox|esxi|(^|-)nas(-|\d|$)|synology|qnap|truenas|unraid|server|buildhost|docker|k8s|homeassistant/']],
	['sbc',          ['hostname' => '/(^|-)pi(\d|-|$)|rpi\d?|raspberry/']],
	['iot',          ['hostname' => '/^esp|esp32|esp8266|tasmota|shelly|sensor|weather|zigbee|zwave|homeiq/']],
	/* Apple devices named after a room are almost always an Apple TV or HomePod. */
	['streaming',    ['vendor' => '/^apple/',
	                  'hostname' => '/kitchen|family|living|bed|room|den$|office|basement|garage|great|loft|patio|theater|theatre/']],

	/* --- manufacturer-only fallbacks --- */
	['printer',      ['vendor' => '/brother|epson|canon|lexmark|xerox|kyocera/']],
	['camera',       ['vendor' => '/amcrest|reolink|hikvision|dahua|wyze|axis comm|eufy|altobeam/']],
	['light',        ['vendor' => '/philips lighting|signify|lifx|nanoleaf|govee/']],
	['thermostat',   ['vendor' => '/ecobee|nest labs/']],
	['energy',       ['vendor' => '/enphase/']],
	['tv',           ['vendor' => '/hisense|vizio|^tcl|lg electronics|sony visual/']],
	['streaming',    ['vendor' => '/roku/']],
	['audio',        ['vendor' => '/sonos|presonus|bose|denon|yamaha/']],
	['sbc',          ['vendor' => '/raspberry pi/']],
	['iot',          ['vendor' => '/espressif|tuya|shelly/']],
	['smart-plug',   ['vendor' => '/meross|belkin/']],
	['access-point', ['vendor' => '/ubiquiti|meraki/']],
	['network',      ['vendor' => '/netgear|tp-link|cisco|mikrotik|aruba|juniper|zyxel/']],
	['server',       ['vendor' => '/synology|qnap|supermicro/']],
	['computer',     ['vendor' => '/intel corporate|micro-star|asustek|elitegroup|dell|lenovo|hewlett|gigabyte|framework/']],
	/* Not "microsoft": that OUI also covers Surface, Hyper-V and USB NICs. */
	['game',         ['vendor' => '/nintendo|sony interactive/']],
]);

/* Returns array(type, source) where source is rule|unknown. */
function dlm_classify($lease) {
	foreach (DLM_TYPE_RULES as $rule) {
		list($type, $conds) = $rule;
		$hit = true;
		foreach ($conds as $field => $re) {
			$val = strtolower((string)($lease[$field] ?? ''));
			if ($val === '' || !preg_match($re . 'i', $val)) {
				$hit = false;
				break;
			}
		}
		if ($hit) {
			return array($type, 'rule');
		}
	}
	return array('unknown', 'unknown');
}

/* Core reports translated state strings; map them back to stable ids. */
function dlm_canonical_state($raw, $is_static = false) {
	if ($is_static) {
		return 'static';
	}
	$raw = strtolower(trim((string)$raw));
	foreach (array('active', 'expired', 'static', 'reserved', 'free', 'backup', 'declined', 'released', 'abandoned') as $s) {
		if ($raw === $s || $raw === strtolower(gettext($s))) {
			return ($s === 'abandoned') ? 'declined' : (($s === 'released') ? 'expired' : $s);
		}
	}
	return ($raw === '') ? 'unknown' : $raw;
}

/* Core reports "active/online" or "idle/offline" (possibly translated). */
function dlm_is_online($raw) {
	$raw = strtolower(trim((string)$raw));
	return (bool)preg_match('/(^|\W)(online|' . preg_quote(strtolower(gettext('online')), '/') . ')$/u', $raw);
}

/* ------------------------------------------------ pfSense integration */

/* Map of mac => array(if, index, ip, descr) for every configured static mapping. */
function dlm_static_maps() {
	$out = array();
	foreach (config_get_path('dhcpd', []) as $if => $ifcfg) {
		if (!is_array($ifcfg)) {
			continue;
		}
		foreach (array_get_path($ifcfg, 'staticmap', []) as $idx => $sm) {
			if (empty($sm['mac'])) {
				continue;
			}
			$out[dlm_normalize_mac($sm['mac'])] = array(
				'if' => $if,
				'index' => $idx,
				'ip' => $sm['ipaddr'] ?? '',
				'descr' => $sm['descr'] ?? '',
			);
		}
	}
	return $out;
}

/* Interface (e.g. "lan", "opt1") whose subnet contains $ip; dynamic leases from core carry none. */
function dlm_interface_for_ip($ip) {
	static $nets = null;
	if ($nets === null) {
		$nets = array();
		foreach (array_keys(config_get_path('dhcpd', [])) as $if) {
			$ifip = get_interface_ip($if);
			$bits = get_interface_subnet($if);
			if (is_ipaddrv4($ifip) && is_numeric($bits)) {
				$nets[$if] = gen_subnet($ifip, $bits) . "/{$bits}";
			}
		}
	}
	foreach ($nets as $if => $cidr) {
		if (ip_in_subnet($ip, $cidr)) {
			return $if;
		}
	}
	return '';
}

/* Per-MAC manual type overrides, stored in config.xml as base64-encoded JSON. */
function dlm_type_overrides() {
	$raw = config_get_path(DLM_CONFIG_PATH . '/typemap', '');
	$map = $raw ? json_decode(base64_decode($raw), true) : null;
	return is_array($map) ? $map : array();
}

/* Set ($type) or reset ($type === '') the type of one or more MACs. Returns an error string or null. */
function dlm_set_type_override($macs, $type) {
	if ($type !== '' && !array_key_exists($type, DLM_TYPES)) {
		return gettext('Invalid device type.');
	}
	$map = dlm_type_overrides();
	$n = 0;
	foreach ((array)$macs as $mac) {
		$mac = dlm_normalize_mac($mac);
		if (!is_macaddr($mac)) {
			return gettext('Invalid MAC address.');
		}
		if ($type === '') {
			unset($map[$mac]);
		} else {
			$map[$mac] = $type;
		}
		$n++;
	}
	if ($n === 0) {
		return gettext('No devices selected.');
	}
	ksort($map);
	config_set_path(DLM_CONFIG_PATH . '/typemap', base64_encode(json_encode($map)));
	if (!write_config(sprintf(gettext('DHCP Lease Manager: device type %1$s for %2$d device(s)'),
	    ($type === '') ? 'reset' : "set to {$type}", $n))) {
		return gettext('The configuration could not be saved.');
	}
	return null;
}

/*
 * Remove static mappings. $items is a list of array(if, id, mac); an entry is
 * only removed if the mapping at that index still has that MAC, so a stale
 * page can never delete the wrong mapping. Mirrors the "del" action of
 * services_dhcp.php: once the configuration is saved, the static ARP entry and
 * the Kea lease are cleared and the DHCP (and DNS host) subsystems are marked
 * dirty, so the change takes effect when the user clicks Apply Changes.
 * Returns array(removed_count, errors[]).
 */
function dlm_delete_static_maps($items) {
	$removed = array();
	$errors = array();
	foreach ((array)$items as $it) {
		$if = (string)($it['if'] ?? '');
		$id = $it['id'] ?? null;
		$mac = dlm_normalize_mac($it['mac'] ?? '');
		if (!preg_match('/^[a-z0-9_]+$/i', $if) || !is_numericint((string)$id) || !is_macaddr($mac)) {
			$errors[] = gettext('Invalid request.');
			continue;
		}
		$map = config_get_path("dhcpd/{$if}/staticmap/{$id}");
		if (!is_array($map) || (dlm_normalize_mac($map['mac'] ?? '') !== $mac)) {
			$errors[] = sprintf(gettext('The static mapping for %s changed since the page was loaded. Reload and try again.'), $mac);
			continue;
		}
		/* Unsetting leaves the other indices untouched, so the batch order does not matter. */
		config_del_path("dhcpd/{$if}/staticmap/{$id}");
		$removed[] = array('if' => $if, 'map' => $map);
	}
	if (empty($removed)) {
		return array(0, $errors);
	}

	if (!write_config(sprintf(gettext('DHCP Lease Manager: removed %d static mapping(s)'), count($removed)))) {
		return array(0, array(gettext('The configuration could not be saved.')));
	}

	$kea = dhcp_is_backend('kea');
	if ($kea) {
		require_once('system.inc');
	}
	foreach ($removed as $r) {
		$if = $r['if'];
		$map = $r['map'];
		if (isset($map['arp_table_static_entry']) && is_ipaddrv4($map['ipaddr'] ?? '')) {
			mwexec('/usr/sbin/arp -d ' . escapeshellarg($map['ipaddr']));
		}
		if (config_path_enabled("dhcpd/{$if}")) {
			mark_subsystem_dirty('dhcpd');
			if (config_path_enabled('dnsmasq') && config_get_path('dnsmasq/regdhcpstatic', false)) {
				mark_subsystem_dirty('hosts');
			}
		}
		if ($kea) {
			if (is_ipaddrv4($map['ipaddr'] ?? '')) {
				system_del_kea4lease($map['ipaddr']);
			} elseif (!empty($map['mac'])) {
				system_del_kea4lease($map['mac'], get_keasubnetid_from_interface($if));
			} elseif (!empty($map['cid'])) {
				system_del_kea4lease($map['cid'], get_keasubnetid_from_interface($if));
			}
		}
	}
	return array(count($removed), $errors);
}

/* All DHCPv4 leases and static mappings, enriched for display. */
function dlm_get_leases() {
	$res = system_get_dhcpleases();
	$oui = load_mac_manufacturer_table();
	$statics = dlm_static_maps();
	$overrides = dlm_type_overrides();
	$now = time();
	$out = array();

	foreach (array_get_path($res, 'lease', []) as $l) {
		if (!is_array($l) || empty($l['ip'])) {
			continue;
		}
		$mac = dlm_normalize_mac($l['mac'] ?? '');
		$core_static = (($l['type'] ?? '') === 'static');
		$sm = $statics[$mac] ?? null;
		$lease = array(
			'ip' => $l['ip'],
			'mac' => $mac,
			'cid' => $l['cid'] ?? '',
			'hostname' => rtrim((string)($l['hostname'] ?? ''), '.'),
			'descr' => (string)($l['descr'] ?? ($sm['descr'] ?? '')),
			'if' => (string)($l['if'] ?? ''),
			'starts' => dlm_to_epoch($l['starts'] ?? null),
			'ends' => dlm_to_epoch($l['ends'] ?? null),
			'state' => dlm_canonical_state($l['act'] ?? '', $core_static),
			'online' => dlm_is_online($l['online'] ?? ''),
			'is_static' => $core_static || ($sm !== null),
			'staticmap_index' => $l['staticmap_array_index'] ?? null,
		);
		if ($lease['if'] === '') {
			$lease['if'] = $sm['if'] ?? dlm_interface_for_ip($lease['ip']);
		}
		/* A dynamic lease held by a device that also has a static mapping. */
		if ($lease['staticmap_index'] === null && $sm !== null && $sm['if'] === $lease['if']) {
			$lease['staticmap_index'] = $sm['index'];
		}

		list($lease['vendor'], $lease['vendor_kind']) = dlm_vendor_for_mac($mac, $oui);
		if (isset($overrides[$mac], DLM_TYPES[$overrides[$mac]])) {
			$lease['type'] = $overrides[$mac];
			$lease['type_source'] = 'manual';
		} else {
			list($lease['type'], $lease['type_source']) = dlm_classify($lease);
		}
		$lease['age'] = ($lease['starts'] !== null) ? max(0, $now - $lease['starts']) : null;
		$lease['if_descr'] = ($lease['if'] !== '') ?
		    convert_friendly_interface_to_friendly_descr($lease['if']) : '';
		$out[] = $lease;
	}

	$types = array();
	foreach (DLM_TYPES as $id => $t) {
		$types[$id] = array('label' => gettext($t[0]), 'icon' => $t[1]);
	}
	return array(
		'backend' => dhcp_is_backend('kea') ? 'kea' : 'isc',
		'generated' => $now,
		'types' => $types,
		'leases' => $out,
	);
}
