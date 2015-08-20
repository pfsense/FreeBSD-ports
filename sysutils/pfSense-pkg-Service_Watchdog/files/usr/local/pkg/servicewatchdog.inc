<?php
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

function servicewatchdog_cron_job($force_remove) {
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

	foreach ($a_pwservices as $svc) {
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

?>
