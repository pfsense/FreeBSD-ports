<?php
/*
 * frr_bfd.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017-2025 Rubicon Communications, LLC (Netgate)
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

/* Get a list of configured BFD peers */
function frr_get_bfd_peers() {
	$bfdpeers = [];
	foreach (config_get_path('installedpackages/frrbfdpeers/config', []) as $bfdpeer) {
		$bfdpeer_name = $bfdpeer['peer'];
		if (!empty($bfdpeer['descr'])) {
			$bfdpeer_name .= " - {$bfdpeer['descr']}";
		}
		$bfdpeers[] = array("name" => "{$bfdpeer_name}", "value" => $bfdpeer['peer']);
	}
	return $bfdpeers;
}

/* Get a list of configured interface addresses */
function frr_get_bfd_source_addresses($includedefault = true) {
	$addresses = array();
	$interfaces = frr_get_interfaces();

	if ($includedefault) {
		$addresses[] = array('name' => 'Default', 'value' => 'default');
	}
	if (is_array($interfaces)) {
		foreach ($interfaces as $interface) {
			if (strlen(frr_get_interfaceinfo($interface['value'])[0]) > 0) {
				list($interface_ip, $interface_subnet, $subnet) = frr_get_interfaceinfo($interface['value']);
				$addresses[] = array("name" => "{$interface['name']} - IPv4 - {$interface_ip}", "value" => $interface_ip);
			}

			if (strlen(frr_get_interfaceinfo($interface['value'], $ipv6 = true)[0]) > 0) {
				list($interface_ip6, $interface_subnet6, $subnet6) = frr_get_interfaceinfo($interface['value'], $ipv6 = true);
				$addresses[] = array("name" => "{$interface['name']} - IPv6 - {$interface_ip6}", "value" => $interface_ip6);
			}
		}
	}

	return $addresses;
}

/* Fetch a list of BFD profiles for use in selection lists */
function frr_get_bfd_profiles() {
	$list = [];
	$list[] = PKG_FRR_LIST_NONE_VALUE;

	$frr_bfd_profiles = config_get_path('installedpackages/frrbfdprofiles/config', []);
	foreach ($frr_bfd_profiles as $profile) {
		$name = empty($profile['descr']) ? $profile['name'] : "{$profile['name']} - {$profile['descr']}";
		$list[] = array("name" => $name, "value" => $profile['name']);
		unset($name);
	}

	return ($list);
}

/* Generate BFD Profiles configuration lines */
function frr_bfd_generate_profiles() {
	/* Populate FRR BFD profiles definitions */
	$frr_bfd_profiles = config_get_path('installedpackages/frrbfdprofiles/config', []);
	if (empty($frr_bfd_profiles)) {
		/* No BFD profiles config, stop. */
		return null;
	}

	$bfdprofileconf = "";
	/* Loop through Profiles and process */
	foreach ($frr_bfd_profiles as $profile) {
		/* Profile Definition */
		$bfdprofileconf .= " profile {$profile['name']}\n";

		/* Profile Options */
		if (frr_validate_intrange($profile['detectmultiplier'], 2, 255)) {
			$bfdprofileconf .= "  detect-multiplier {$profile['detectmultiplier']}\n";
		}
		if (frr_validate_intrange($profile['receiveinterval'], 10, 60000)) {
			$bfdprofileconf .= "  receive-interval {$profile['receiveinterval']}\n";
		}
		if (frr_validate_intrange($profile['transmitinterval'], 10, 60000)) {
			$bfdprofileconf .= "  transmit-interval {$profile['transmitinterval']}\n";
		}
		if (frr_validate_intrange($profile['echointerval'], 10, 60000)) {
			$bfdprofileconf .= "  echo-interval {$profile['echointerval']}\n";
		}
		if (!empty($profile['passive'])) {
			$bfdprofileconf .= "  passive-mode\n";
		}
		if (!empty($profile['shutdown'])) {
			$bfdprofileconf .= "  shutdown\n";
		} else {
			$bfdprofileconf .= "  no shutdown\n";
		}
		if (!empty($profile['echomode'])) {
			$bfdprofileconf .= "  echo-mode\n";
		}
		if (frr_validate_intrange($profile['minimumttl'], 1, 254)) {
			$bfdprofileconf .= "  minimum-ttl {$profile['minimumttl']}\n";
		}
		$bfdprofileconf .= " !\n";
	}

	return $bfdprofileconf;
}

/* Generate BFD Peers configuration lines */
function frr_bfd_generate_peers() {
	/* Populate FRR BFD Peer definitions */
	$frr_bfd_peers = config_get_path('installedpackages/frrbfdpeers/config', []);
	if (empty($frr_bfd_peers)) {
		/* No BFD peer config, stop. */
		return null;
	}

	$bfdpeerconf = "";
	/* Loop through Peers and process */
	foreach ($frr_bfd_peers as $peer) {
		if (is_ipaddr($peer['peer'])) {
			/* Peer Definition */
			$bfdpeerdefconf = " peer {$peer['peer']}";
			if (!empty($peer['multihop'])) {
				$bfdpeerdefconf .= " multihop";
			}
			if (!empty($peer['local_address']) && ($peer['interface'] <> 'default')) {
				if (!find_ip_interface($peer['local_address'])) {
					frr_package_log("BFDd: Source Address not found, skipping peer configuration.");
					continue;
				}
				$bfdpeerdefconf .= " local-address {$peer['local_address']}";
			}
			if (!empty($peer['interface']) && ($peer['interface'] <> 'default')) {
				$realif = get_real_interface($peer['interface']);
				if (!$realif) {
					frr_package_log("BFDd: Source Interface not found, skipping peer configuration.");
					continue;
				}
				$bfdpeerdefconf .= " interface {$realif}";
			}
			$bfdpeerconf .= $bfdpeerdefconf . "\n";

			/* Peer Options */
			if ((empty($peer['profile'])) || ($peer['profile'] == 'none')) {
				if (frr_validate_intrange($peer['detectmultiplier'], 2, 255)) {
					$bfdpeerconf .= "  detect-multiplier {$peer['detectmultiplier']}\n";
				}
				if (frr_validate_intrange($peer['receiveinterval'], 10, 60000)) {
					$bfdpeerconf .= "  receive-interval {$peer['receiveinterval']}\n";
				}
				if (frr_validate_intrange($peer['transmitinterval'], 10, 60000)) {
					$bfdpeerconf .= "  transmit-interval {$peer['transmitinterval']}\n";
				}
				if (frr_validate_intrange($peer['echointerval'], 10, 60000)) {
					$bfdpeerconf .= "  echo-interval {$peer['echointerval']}\n";
				}
				if (!empty($peer['shutdown'])) {
					$bfdpeerconf .= "  shutdown\n";
				} else {
					$bfdpeerconf .= "  no shutdown\n";
				}
				if (!empty($peer['echomode'])) {
					$bfdpeerconf .= "  echo-mode\n";
				}
			} else {
				$bfdpeerconf .= "  profile {$peer['profile']}\n";
			}
			$bfdpeerconf .= " !\n";
		}
	}

	return $bfdpeerconf;
}

/* Generate BFD configuration lines */
function frr_generate_config_bfd() {
	global $frr_config_base, $frr_integrated_config;

	/* Make bfdd config */
	$frr_conf = config_get_path('installedpackages/frr/config/0', []);
	if (empty($frr_conf)) {
		/* If FRR is not configured, stop. */
		frr_package_log("BFDd: No FRR global config data found.");
		return null;
	}
	/* Populate FRR BFD Settings */

	$frr_bfd_conf = config_get_path('installedpackages/frrbfd/config/0', []);

	if (!empty(config_get_path('installedpackages/frrglobalraw/config/0/bfdd'))) {
		// if there is a raw config specified in the config.xml use that instead of the assisted config
		$frr_integrated_config['bfdd'] = str_replace("\r","",base64_decode(config_get_path('installedpackages/frrglobalraw/config/0/bfdd')));
	} else {
		/* If there is no raw configuration and no GUI configuration, stop. */
		if (empty($frr_bfd_conf)) {
			frr_package_log("BFDd: No config data found.");
			return;
		} elseif (empty($frr_bfd_conf['enable'])) {
			/* BFD Daemon is not enabled. */
			frr_package_log("BFDd: BFD disabled.");
			return;
		} elseif (empty($frr_conf['enable'])) {
			/* FRR is disabled or BFD Daemon is not enabled. */
			frr_package_log("BFDd: FRR master disabled.");
			return;
		}

		$frr_integrated_config['bfdd'] = "";
		$frr_integrated_config['bfdd'] .= "bfd\n";

		/* BFD Profiles */
		$frr_integrated_config['bfdd'] .= frr_bfd_generate_profiles();

		/* BFD Peers */
		$frr_integrated_config['bfdd'] .= frr_bfd_generate_peers();
	}
}
