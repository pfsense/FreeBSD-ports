<?php
/*
 * servicewatchdog.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2013-2025 Rubicon Communications, LLC (Netgate)
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

require_once("config.inc");
require_once("services.inc");
require_once("service-utils.inc");
require_once("util.inc");
require_once("notices.inc");

define('LOG_PREFIX_PKG_SERVICEWATCHDOG', 'Service Watchdog');

function servicewatchdog_service_matches($svc1, $svc2) {
	/* If the arrays are equal, it must be the same service. */
	if ($svc1 == $svc2) {
		return true;
	}
	/* If the names are different, they must not be the same. */
	if ($svc1['name'] != $svc2['name']) {
		return false;
	}
	switch ($svc1['name']) {
		case "openvpn":
			if (($svc1['mode'] == $svc2['mode']) && ($svc1['vpnid'] == $svc2['vpnid'])) {
				return true;
			} else {
				return false;
			}
			break;
		case "captiveportal":
			if ($svc1['zone'] == $svc2['zone']) {
				return true;
			} else {
				return false;
			}
			break;
		default:
			/* Other services must be the same if the name matches. */
			return true;
	}
}

function servicewatchdog_is_service_watched($svc) {
	$blacklisted_services = array("cron");

	if (empty($svc['name']) || in_array($svc['name'], $blacklisted_services)) {
		return true;
	}

	foreach (config_get_path('installedpackages/servicewatchdog/item', []) as $a_svc) {
		if (servicewatchdog_service_matches($svc, $a_svc)) {
			return true;
		}
	}
	return false;
}

function servicewatchdog_cron_job($force_remove = false) {
	if (($force_remove == false) && (count(config_get_path('installedpackages/servicewatchdog/item', [])) > 0)) {
		// Add the cron job if it doesn't exist.
		install_cron_job("/usr/local/pkg/servicewatchdog_cron.php", true, "*/1");
	} else {
		// Remove the cron job
		install_cron_job("/usr/local/pkg/servicewatchdog_cron.php", false, "*/1");
	}
}

function servicewatchdog_check_services() {
	foreach (config_get_path('installedpackages/servicewatchdog/item', []) as $svc) {
		// apinger became dpinger in pfSense 2.3
		if ($svc['name'] == 'apinger') {
			$svc['name'] = 'dpinger';
		}
		if (!get_service_status($svc)) {
			$descr = strlen($svc['description']) > 50 ? substr($svc['description'], 0, 50) . "..." : $svc['description'];
			$error_message = localize_text('Detected service %1$s stopped. Restarting %1$s (%2$s)', $svc['name'], $descr);
			logger(LOG_NOTICE, $error_message, LOG_PREFIX_PKG_SERVICEWATCHDOG);
			if (isset($svc['notify'])) {
				notify_all_remote($error_message);
			}
			service_control_start($svc['name'], $svc);
		}
	}
}

function servicewatchdog_build_service_list() {
	$i = 0;
	$list = array();
	$system_services = get_services();
	foreach ($system_services as $svc) {
		if (!servicewatchdog_is_service_watched($svc)) {
			$svc['description'] = empty($svc['description']) ? get_pkg_descr($svc['name']) : $svc['description'];
			$list[$i] = "{$svc['name']}: ";
			$list[$i] .= strlen($svc['description']) > 50 ? substr($svc['description'], 0, 50) . "..." : $svc['description'];
		}
		$i++;
	}
	return $list;
}

?>
