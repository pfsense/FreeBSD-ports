<?php
/*
	servicewatchdog.inc
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2013 Jim Pingle
	Copyright (C) 2015 ESF, LLC
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
require_once("config.inc");
require_once("services.inc");
require_once("service-utils.inc");
require_once("util.inc");
require_once("notices.inc");

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
	global $config;
	if (!is_array($config['installedpackages']['servicewatchdog']['item'])) {
		$config['installedpackages']['servicewatchdog']['item'] = array();
	}
	$a_pwservices = &$config['installedpackages']['servicewatchdog']['item'];
	$blacklisted_services = array("cron");

	if (empty($svc['name']) || in_array($svc['name'], $blacklisted_services)) {
		return true;
	}

	foreach ($a_pwservices as $a_svc) {
		if (servicewatchdog_service_matches($svc, $a_svc)) {
			return true;
		}
	}
	return false;
}

function servicewatchdog_cron_job($force_remove = false) {
	global $config;
	if (!is_array($config['installedpackages']['servicewatchdog']['item'])) {
		$config['installedpackages']['servicewatchdog']['item'] = array();
	}
	$a_pwservices = &$config['installedpackages']['servicewatchdog']['item'];

	if (($force_remove == false) && (count($a_pwservices) > 0)) {
		// Add the cron job if it doesn't exist.
		install_cron_job("/usr/local/pkg/servicewatchdog_cron.php", true, "*/1");
	} else {
		// Remove the cron job
		install_cron_job("/usr/local/pkg/servicewatchdog_cron.php", false, "*/1");
	}
}

function servicewatchdog_check_services() {
	global $config;
	if (!is_array($config['installedpackages']['servicewatchdog']['item'])) {
		$config['installedpackages']['servicewatchdog']['item'] = array();
	}
	$a_pwservices = &$config['installedpackages']['servicewatchdog']['item'];

	foreach ($a_pwservices as $idx => $svc) {
		// apinger became dpinger in pfSense 2.3
		if ($svc['name'] == 'apinger') {
			$a_pwservices[$idx]['name'] = $svc['name'] = 'dpinger';
			write_config(gettext("Service Watchdog updated service apinger to dpinger"));
		}
		if (!get_service_status($svc)) {
			$descr = strlen($svc['description']) > 50 ? substr($svc['description'], 0, 50) . "..." : $svc['description'];
			$error_message = "Service Watchdog detected service {$svc['name']} stopped. Restarting {$svc['name']} ({$descr})";
			log_error($error_message);
			if (isset($svc['notify'])) {
				notify_via_smtp($error_message);
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
