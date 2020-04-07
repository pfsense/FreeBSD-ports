<?php
/*
 * frr_bfd.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2017-2020 Rubicon Communications, LLC (Netgate)
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

/* Generate BFD Peers configuration lines */
function frr_bfd_generate_peers() {
	global $config, $frr_config_base;
	/* Populate FRR Settings */
	if (is_array($config['installedpackages']['frr']['config'])) {
		$frr_conf = &$config['installedpackages']['frr']['config'][0];
	} else {
		/* If FRR is not configured, stop. */
		return null;
	}
	/* Populate FRR BFD Settings */
	if (is_array($config['installedpackages']['frrbfd']['config'])) {
		$frr_bfd_conf = &$config['installedpackages']['frrbfd']['config'][0];
	} else {
		/* No BFD config, stop. */
		return null;
	}
	$bfdconf = "\n# BFD Config\n";
	$bfdconf .= "\nbfd\n";

	/* Peers */
	foreach ($frr_bfd_conf['row'] as $peer) {
		if (is_ipaddr($peer['peerip'])) {
			$bfdconf .= " peer {$peer['peerip']}\n";
			if (frr_validate_intrange($peer['detectmultiplier'], 2, 255)) {
				$bfdconf .= "  detect-multiplier {$peer['detectmultiplier']}\n";
			}
			if (frr_validate_intrange($peer['receiveinterval'], 10, 60000)) {
				$bfdconf .= "  receive-interval {$peer['receiveinterval']}\n";
			}
			if (frr_validate_intrange($peer['transmitinterval'], 10, 60000)) {
				$bfdconf .= "  transmit-interval {$peer['transmitinterval']}\n";
			}
			if (!empty($peer['shutdown'])) {
				$bfdconf .= "  shutdown\n";
			} else {
				$bfdconf .= "  no shutdown\n";
			}
			$bfdconf .= "\n";
		}
	}

	return $bfdconf;
}

function frr_generate_config_bfd() {
	global $config, $frr_config_base, $frr_auto_config_warning;
	unlink_if_exists("{$frr_config_base}/bfdd.conf");
	/* Make bfdd config */
	if (is_array($config['installedpackages']['frr']['config'])) {
		$frr_conf = &$config['installedpackages']['frr']['config'][0];
	} else {
		/* If FRR is not configured, stop. */
		return null;
	}
	/* Populate FRR BFD Settings */
	if (is_array($config['installedpackages']['frrbfd']['config'])) {
		$frr_bfd_conf = &$config['installedpackages']['frrbfd']['config'][0];
	} else {
		/* No BFD config, stop. */
		return null;
	}

	if (isset($config['installedpackages']['frrglobalraw']['config'][0]['bfdd']) &&
	    !empty($config['installedpackages']['frrglobalraw']['config'][0]['bfdd'])) {
		// if there is a raw config specified in the config.xml use that instead of the assisted config
		$bfddconffile = str_replace("\r","",base64_decode($config['installedpackages']['frrglobalraw']['config'][0]['bfdd']));
	} else {
		/* If there is no raw configuration and no GUI configuration, stop. */
		if (empty($frr_conf['enable']) || empty($frr_bfd_conf['enable'])) {
			/* FRR is disabled or BFD Daemon is not enabled. */
			return;
		}
		$bfddconffile = $frr_auto_config_warning;

		/* Pull the password from the global settings */
		if ($frr_conf['password']) {
			$bfddconffile .= "password {$frr_conf['password']}\n";
		}

		/* BFD Peers */
		$bfddconffile .= frr_bfd_generate_peers();
	}
	file_put_contents("{$frr_config_base}/bfdd.conf", $bfddconffile);
}
