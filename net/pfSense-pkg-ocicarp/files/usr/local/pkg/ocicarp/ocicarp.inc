<?php
/*
 * ocicarp.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2023, Oracle and/or its affiliates.
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

define('OCICARP_SCRIPT_FILE', '/usr/local/bin/ocicarp.py');
define('OCICARP_CONFIG_LOC', '/usr/local/etc/ocicarp');
define('OCICARP_DEVD_CONFIG_FILE', '/usr/local/etc/devd/ocicarp.conf');
/*
 * If OCI CLI cannot reach the MDS when using --auth instance_principal, it
 * will take a long time to timeout, so kill it after 10 seconds.
 */
define('OCI_CLI', '/bin/timeout 10 /usr/local/bin/oci --auth instance_principal network');
/*
 * The OCI Metadata Service IP address.
 */
define('OCI_MDS_IP', '169.254.169.254');
define('OCI_MDS_VNICS', 'http://' . OCI_MDS_IP . '/opc/v2/vnics');


/**
 * This function attempts to retrieve metadata about the instance's vNICs
 * from the OCI Metadata Service. If it cannot be reached or a non-JSON
 * response received, it will return null.
 * 
 * @return array Associative array of the instance vNIC metadata JSON
 * @return null If no, or non-JSON, result received
 */
function ocicarp_get_oci_metadata(): array|null {
	$conn = curl_init(OCI_MDS_VNICS);
	curl_setopt($conn, CURLOPT_HTTPHEADER, array('Authorization: Bearer Oracle'));
	curl_setopt($conn, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($conn, CURLOPT_FRESH_CONNECT,  true);
	curl_setopt($conn, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($conn, CURLOPT_TIMEOUT, 3);
	$curl_result = trim(curl_exec($conn));
	curl_close($conn);
	$json_result = json_decode($curl_result, true);

  return $json_result;
}


/**
 * A function to return an associative array of OCI vNIC details. vNIC MAC
 * address as key and OCID as value. If no vNICs are found it will return an
 * empty array, however, if it cannot access the Metadata Service, (e.g.
 * this might happen if not running on OCI), null is returned.
 * 
 * @return array Associative array (empty if no matches) where the key is the interface MAC and value is the vNIC OCID
 * @return null If no metadata could be retrieved
 */
function ocicarp_get_oci_vnic_list_details(): array|null {
	$ocimetadata = ocicarp_get_oci_metadata();
	if (!$ocimetadata) {
		return null;
	}

	$ociifs = array();
	foreach ($ocimetadata as $ocivnic => $vnicdetails) {
		if ((!$vnicdetails['macAddr']) || (!$vnicdetails['vnicId'])) {
			log_error(gettext("Missing OCI metadata for vNIC") . " {$ocivnic}");
			continue;
		}
		$ociifs[strtolower($vnicdetails['macAddr'])] = $vnicdetails['vnicId'];
	}

	return $ociifs;
}


/**
 * A function to return a list of VIP IDs where they can be matched to OCI
 * vNICs (by MAC address). If no matches, returns an empty array. If no
 * metadata available, returns null.
 * 
 * @return array Associative array (empty if no matches) where key is CARP VIP ID (vid) and value is the IP address
 * @return null If no OCI metadata could be accessed
 */
function ocicarp_get_oci_suitable_vips(): array|null {
	$ocivnics = ocicarp_get_oci_vnic_list_details();
	$carplist = get_configured_vip_list('all', VIP_CARP);
	$suitablevips = array();

	if (is_null($ocivnics)) {
		return null;
	}

	// If either of these are empty, nothing to do here; return an empty array.
	if (!empty($ocivnics) && (!empty($carplist))) {

		// Get a list of all the VIPs we can match to interfaces.
		$matchedvips = array();
		foreach ($carplist as $vid => $vaddr) {
			$vip = get_configured_vip($vid);
			if (!array_key_exists('carp_mode', $vip) || ($vip['carp_mode'] !== 'ucast')) {
				// Only unicast CARP VIPs are supported.
				continue;
			}
			$realif = get_real_interface($vip['interface']);
			$ifaddrs = get_interface_addresses($realif);
			if (!$ocivnics[strtolower($ifaddrs['macaddr'])]) {
				continue;
			}
			$matchedvips[$vid] = $vaddr;
		}

		if (!empty($matchedvips)) {
			/*
			 * Get VIPs we can match to vNICs and OCIDs. This ensures that IPs
			 * actually exist in OCI in addition to on the pfSense instance.
			 */
			$vipdetails = ocicarp_get_all_vip_matching_ocids(array_keys($matchedvips), $ocivnics);
			foreach ($vipdetails as $vid => $vipdetail) {
				$aliases = array();
				$match = '';
				foreach ($vipdetail['ips'] as $ipdetail) {
					// As we will have all IPs (including aliases), look for our VIP IP.
					if ($ipdetail['ip'] !== $matchedvips[$vid]) {
						$aliases[] = $ipdetail['ip'];
						continue;
					}
					$match = $matchedvips[$vid];
				}
				if ($match != '') {
					if (!empty($aliases)) {
						$suitablevips[$vid] = $matchedvips[$vid] . " (" . implode(', ', $aliases) . ")";
					} else {
						$suitablevips[$vid] = $matchedvips[$vid];
					}
				}
			}
		}
	}

	return $suitablevips;
}


/**
 * A function to find the OCI OCID for an vNIC's subnet.
 *
 * @param string $vnicocid OCID of the vNIC that should have the VIP
 *
 * @return string OCID of the subnet
 * @return null Address was not found
 */
function ocicarp_get_subnet_ocid(string $vnicocid): string|null {
	$output = array();
	$ret_val = 0;
	// Gets only one row.
	exec(OCI_CLI . " vnic get --vnic-id " . escapeshellarg($vnicocid) . " --query 'data.\"subnet-id\"' 2>&1", $output, $ret_val);
	if (($ret_val == 0) && (!empty($output))) {
		$subnetocid = trim($output[0], '"'); // Strip double quotes.
		return $subnetocid;
	} else {
		log_error(implode(', ', $output));
		return null;
	}
}


/**
 * A function to map an array of IPs from a subnet to their corresponding
 * OCIDs. Works for both v4 and v6 addresses.
 * 
 * @param array $ips Array of IP addresses of the same class - v4 or v6
 * @param string $subnetocid OCID of the subnet in which to look for IPs.
 *
 * @return array Associative multi-dimensional array of CARP VIPs with vNIC
 * OCIDs, IP addresses and  IP address OCIDs.
 */
function ocicarp_get_subnet_ip_ocids(array $ips, string $subnetocid): array|null {
	$mapping = array();
	if (!empty($ips)) {
		$output = array();
		$ret_val = 0;
		// All IPs will be of the same class, so just check the first one.
		$ipcmd = 'private-ip';
		if (is_v6($ips[0])) {
			$ipcmd = 'ipv6';
		}
		// Gets 0 or more as JSON.
		exec(OCI_CLI . " {$ipcmd} list --subnet-id " . escapeshellarg($subnetocid) . " --query 'data[?\"ip-address\"==`" . implode('` || "ip-address"==`', $ips) . "`].[\"ip-address\", id]' 2>&1", $output, $ret_val);

		/*
		 * If the JMESPath query returns nothing (i.e. no matches) we get an error
		 * message and not JSON. In that case, return null and log the message.
		 */
		$json_result = json_decode(implode(PHP_EOL, $output), true);
		if (($ret_val == 0) && (!empty($output)) && (!empty($json_result))) {
			foreach($json_result as $map) {
				$mapping[] = array('ip' => $map[0], 'ipocid' => $map[1]);
			}
		} else {
			log_error(implode(', ', $output));
			return null;
		}
	}
	return $mapping;
}


/**
 * A function to construct an associative multi-dimensional array of CARP
 * VIPs with vNIC OCIDs, IP addresses and IP address OCIDs.
 * 
 * @param array $carpvips OCI CARP VIP IDs
 * @param array $ocivnics Associative array of MAC and vNIC OCID
 *
 * @return array Associative multi-dimensional array of CARP VIPs with vNIC
 * OCIDs, IP addresses and IP address OCIDs.
 */
function ocicarp_get_all_vip_matching_ocids($carpvips, $ocivnics):array {
	$vnicsubnet = array();
	$mapping = array();
	foreach ($carpvips as $vid) {
		$vip = get_configured_vip($vid);
		$realif = get_real_interface($vip['interface']);
		$ifaddrs = get_interface_addresses($realif);

		if (!$ocivnics[strtolower($ifaddrs['macaddr'])]) {
			continue;
		}
		$vnicocid = $ocivnics[strtolower($ifaddrs['macaddr'])];
		/*
		 * As looking up the subnet is expensive and multiple VIPs could be on the
		 * vNIC, cache it to avoid extra look ups.
		 */
		if (!array_key_exists($vnicocid, $vnicsubnet)) {
			$vnicsubnet[$vnicocid] = ocicarp_get_subnet_ocid($vnicocid);
		}

		$carpvip = "_vip{$vip['uniqid']}";
		$allips = array($vip['subnet']);

		$ipaliases = trim(get_interface_vip_ips($carpvip), ' ');
		if ($ipaliases !== "") {
			$allips = array_merge($allips, explode(' ', $ipaliases));
		}

		$ipocids = ocicarp_get_subnet_ip_ocids($allips, $vnicsubnet[$vnicocid]);
		if (!empty($ipocids)) {
			$mapping[$carpvip]['vnicocid'] = $vnicocid;
			$mapping[$carpvip]['ips'] = $ipocids;
		} else {
			log_error(gettext("Oracle Cloud Infrastructure CARP unable to map any IP OCIDs for") . " {$carpvip} - " . explode(', ', $allips));
		}
	}
	return $mapping;
}


/**
 * A function to write the JSON configuration files for each CARP VIP that
 * will be used by invocation from devd.
 * 
 * @param array $ocicarp_config OCI CARP package configuration
 * @param array $ocivnics array of MAC and vNIC OCID
 */
function ocicarp_write_vip_files($ocicarp_config, $ocivnics):void {
	if (!empty($ocicarp_config['carpvips'])) {

		$carpvips = explode(',', $ocicarp_config['carpvips']);
		$vipdetails = ocicarp_get_all_vip_matching_ocids($carpvips, $ocivnics);

		if (empty($vipdetails)) {
			log_error(gettext("Oracle Cloud Infrastructure CARP could not map VIPs to OCIDs") . " - {$ocicarp_config['carpvips']}");
			return;
		}
		foreach ($vipdetails as $vip => $vipdetail) {
			// Build file body.
			$txt = "{" . PHP_EOL;
			$txt .= "\t\"{$vipdetail['vnicocid']}\": {". PHP_EOL;

			$ipv4s = "\t\t\"ipv4\": [";
			$ipv6s = "\t\t\"ipv6\": [";
			foreach ($vipdetail['ips'] as $ip) {
				if (is_v6($ip['ip'])) {
					$ipv6s .= PHP_EOL . "\t\t\t\"{$ip['ipocid']}\",";
				} else {
					$ipv4s .= PHP_EOL . "\t\t\t\"{$ip['ipocid']}\",";
				}
			}
			$ipv4s = rtrim($ipv4s, ',') . PHP_EOL;
			$ipv6s = rtrim($ipv6s, ',') . PHP_EOL;

			$ipv4s .= "\t\t]," . PHP_EOL;
			$ipv6s .= "\t\t]" . PHP_EOL;
			$txt .= $ipv4s . $ipv6s;
			$txt .= "\t}" . PHP_EOL;
			$txt .= "}" . PHP_EOL;

			$filename = OCICARP_CONFIG_LOC . "/{$vip}.json";
			if (!file_put_contents($filename, $txt)) {
				log_error(gettext("Oracle Cloud Infrastructure CARP ERROR: Could not write to file") . " {$filename}");
				exit;
			}
		}
	}
}


/**
 * A function to write the devd configuration file with the matching rules to
 * call the ocicarp.py script to invoke the OCI APIs.
 * 
 * @param array $ocicarp_config OCI CARP package configuration
 * @param array $ocivnics array of MAC and vNIC OCID
 */
function ocicarp_write_devd_config($ocicarp_config, $ocivnics): void {
	$txt = "# This file is generated by the pfSense Oracle Cloud Infrastructure CARP package." . PHP_EOL;
	$txt .= "# Do not edit this file, it will be overwritten automatically." . PHP_EOL;

	if (!empty($ocicarp_config['carpvips'])) {
		foreach (explode(',', $ocicarp_config['carpvips']) as $vid) {
			$vip = get_configured_vip($vid);
			$realif = get_real_interface($vip['interface']);
			$ifaddrs = get_interface_addresses($realif);

			if (!$ocivnics[strtolower($ifaddrs['macaddr'])]) {
				continue;
			}
			$vnicocid = $ocivnics[strtolower($ifaddrs['macaddr'])];
			$txt .= "notify 100 {" . PHP_EOL;
			$txt .= "\tmatch \"system\" \"CARP\";" . PHP_EOL;
			$txt .= "\tmatch \"type\" \"MASTER\";" . PHP_EOL;
			$txt .= "\tmatch \"subsystem\" \"{$vip['vhid']}@{$realif}\";" . PHP_EOL;
			$txt .= "\taction \"" . OCICARP_SCRIPT_FILE . " --json " . escapeshellarg(OCICARP_CONFIG_LOC . "/_vip{$vip['uniqid']}.json") . " &\";" . PHP_EOL;
			$txt .= "};" . PHP_EOL;
		}
	}

	if (!file_put_contents(OCICARP_DEVD_CONFIG_FILE, $txt)) {
		log_error(gettext("Oracle Cloud Infrastructure CARP ERROR: Could not write to file") . " '" . OCICARP_DEVD_CONFIG_FILE . "'");
		exit;
	}
}


/**
 * A function to resync the configuration and config files and restart devd.
 */
function ocicarp_sync_config():void {
	$ocicarp_config = config_get_path('installedpackages/ocicarp/config/0', []);
	if (isset($ocicarp_config['enable'])) {
		$enable = $ocicarp_config['enable'];
	} else {
		$enable = 'no';
	}

	$ocivnics = ocicarp_get_oci_vnic_list_details();

	// Always write VIP config files.
	ocicarp_write_vip_files($ocicarp_config, $ocivnics);
	if ($enable !== 'yes') {
		// Remove the devd config file.
		unlink_if_exists(OCICARP_DEVD_CONFIG_FILE);
	} else {
		// Create the devd config file.
		ocicarp_write_devd_config($ocicarp_config, $ocivnics);
	}

	// service devd restart exit code is 0 if running, convert to PHP "TRUE".
	if (0==mwexec('service devd restart')) {
		log_error(gettext("Oracle Cloud Infrastructure CARP sync restarting devd"));
	} else {
		log_error(gettext("Oracle Cloud Infrastructure CARP failed to restart! Check configuration in") . " '" . OCICARP_DEVD_CONFIG_FILE . "'");
	}
}


/**
 * A function to handle deinstallation tasks. On deinstall, the devd config
 * file is removed and devd is restarted to reload the changed configuration.
 * If "keep_config" is cleared in the configuration, all package configuration
 * is removed, otherwise it is preserved.
 */
function ocicarp_deinstall_command():void {
	$ocicarp_config = config_get_path('installedpackages/ocicarp/config/0', []);
	if (isset($ocicarp_config['keep_conf'])) {
		$keep_conf = $ocicarp_config['keep_conf'];
	} else {
		$keep_conf = 'no';
	}

	// Remove the devd config file.
	log_error(gettext("Oracle Cloud Infrastructure CARP deinstall removed config files"));
	unlink_if_exists(OCICARP_DEVD_CONFIG_FILE);

	if (!empty($ocicarp_config['carpvips'])) {
		// Remove the vip config file(s)
		foreach (explode(',', $ocicarp_config['carpvips']) as $vip) {
			unlink_if_exists(OCICARP_CONFIG_LOC . "/{$vip}.json");
		}
	}

	/*
	 * Restart devd to reread config.
	 * Using mwexec as restart_service() does not work during deinstall.
	 */
	log_error(gettext("Oracle Cloud Infrastructure CARP deinstall restarting service devd"));
	mwexec('service devd restart');

	if ($keep_conf == 'no') {
		// Remove configuration.
		config_del_path('installedpackages/ocicarp/config/0');
		write_config('Oracle Cloud Infrastructure CARP removed settings');
	}
}


/**
 * A function to build a list of VHID@IF and VID for matching in the
 * ocicarp_plugin_carp() function.
 * 
 * @param string $ocicarp_vids The comma separated list of CARP VIP IDs (vids) from the configuration
 * 
 * @return array Associative array (empty if no matches) where key is CARP subsystem ids (i.e. VHID@interface) and value is the matched VIP ID.
 */
function ocicarp_get_carp_vhidif_list($ocicarp_vids): array {
	$list = array();
	if (!empty($ocicarp_vids)) {
		foreach (explode(',', $ocicarp_vids) as $vid) {
			$vip = get_configured_vip($vid);
			$realif = get_real_interface($vip['interface']);
			$list["{$vip['vhid']}@{$realif}"] = $vid;
		}
	}
	return $list;
}


/**
 * A function called by rc.carpmaster/rc.carpbackup via plugin_carp.
 * 
 * @param array $pluginparams List of parameters passed from CARP scripts.
 */
function ocicarp_plugin_carp($pluginparams):void {
	// Start or stop the service as needed based on the CARP transition.
	if ($pluginparams['event'] == "rc.carpbackup") {
		log_error(gettext("Oracle Cloud Infrastructure CARP nothing to do") . " (rc.carpbackup)");
	} elseif ($pluginparams['event'] == "rc.carpmaster") {
		// If there is no ocicarp configuration, then nothing to do.
		$ocicarp_config = config_get_path('installedpackages/ocicarp/config/0', []);
		if ((empty($ocicarp_config)) || (!isset($ocicarp_config['enable']))
		  || empty($ocicarp_config['carpvips'])) {
			log_error(gettext("Oracle Cloud Infrastructure CARP nothing to do (no config)") . " (rc.carpmaster)");
		} else {
			// Locate the VIP and match it against the configured check address.
			$vhidifs = ocicarp_get_carp_vhidif_list($ocicarp_config['carpvips']);
			if (array_key_exists(trim($pluginparams['interface']), $vhidifs)) {
				$vid = $vhidifs[trim($pluginparams['interface'])];
				log_error(gettext("Oracle Cloud Infrastructure CARP running for") . " {$vid} (rc.carpmaster)");
				mwexec(OCICARP_SCRIPT_FILE . " --json " . escapeshellarg(OCICARP_CONFIG_LOC . "/{$vid}.json") . " &");
			} else {
				log_error(gettext("Oracle Cloud Infrastructure CARP no action (no VIP config) for") . " {$pluginparams['interface']} (rc.carpmaster)");
			}
		}
	} else {
		log_error(gettext("Oracle Cloud Infrastructure CARP no action for event") . " '{$pluginparams['event']}' (rc.carpmaster)");
	}
}
