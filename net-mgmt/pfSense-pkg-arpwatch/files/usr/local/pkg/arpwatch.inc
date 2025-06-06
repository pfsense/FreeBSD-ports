<?php
/*
 * arpwatch.inc
 *
 * Copyright (c) 2018 Julien Le Goff <julego@gmail.com>
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

define('ARPWATCH_LOCAL_DIR', '/usr/local/arpwatch');
define('ARPWATCH_ETHERCODES_URL', 'http://standards-oui.ieee.org/oui/oui.csv');
define('ARPWATCH_SENDMAIL_PATH', '/usr/sbin/sendmail');
define('ARPWATCH_SENDMAIL_PROXY', '/usr/local/arpwatch/sendmail_proxy.php');

function arpwatch_install_command() {
	// sendmail proxy
	arpwatch_install_sendmail_proxy();

	$arpwatch_config = config_get_path('installedpackages/arpwatch/config/0', []);
	$active_interfaces = $arpwatch_config['active_interfaces'];
	$notifications_recipient = $arpwatch_config['notifications_recipient'];
	$enable_zeropad = ($arpwatch_config['zeropad'] == 'on') ? '-Z' : '-C';
	$disable_carp = ($arpwatch_config['disable_carp'] == 'on') ? '-v' : '';
	$disable_bogons = ($arpwatch_config['disable_bogons'] == 'on') ? '-N' : '';
	$disable_zero = ($arpwatch_config['disable_zero'] == 'on') ? '-z' : '';
	$update_vendors = ($arpwatch_config['update_vendors'] == 'on');

	if ($update_vendors) {
		arpwatch_update_vendors($enable_zeropad);
	}

	$rc = array();
	$rc['file'] = 'arpwatch.sh';

	// Give it a few seconds to stop gracefully, then kill it with fire.
	$rc['start'] = "/usr/bin/killall -q arpwatch ; sleep 3; /usr/bin/killall -q -9 arpwatch\n";

	$active_interfaces_array = explode(',', $active_interfaces);

	if (is_array($active_interfaces_array)) {
		foreach ($active_interfaces_array as $active_interface) {
			$ifname = convert_friendly_interface_to_real_interface_name($active_interface);
			$arp_file = arpwatch_get_arp_file($ifname);

			touch($arp_file);

			$rc['start'] .= '/usr/local/sbin/arpwatch'
			    .' '.$disable_carp
			    .' '.$disable_bogons
			    .' '.$disable_zero
			    .' '.$enable_zeropad
			    .' -f '.escapeshellarg($arp_file)
			    .' -i '.escapeshellarg($ifname);
			if (!empty($notifications_recipient)) {
			    $rc['start'] .= ' -w '.escapeshellarg($notifications_recipient);
			}
			$rc['start'] .= "\n";
		}
	}

	$rc['stop'] = '/usr/bin/killall -q arpwatch';

	if ($arpwatch_config['enable']) {
		write_rcfile($rc);
		if (!platform_booting()) {
			restart_service("arpwatch");
		}
	} else {
		unlink_if_exists("/usr/local/etc/rc.d/arpwatch.sh");
		if (is_process_running("arpwatch")) {
			killbyname("arpwatch");
		}
	}
}

function arpwatch_deinstall_command() {
	if (config_get_path('installedpackages/arpwatch/config/0/clear_database') == 'on') {
		arpwatch_clear_database();
	}
	arpwatch_uninstall_sendmail_proxy();
}

function arpwatch_validate_input($post, &$input_errors) {
	if (!isset($post['enable']) || $post['enable'] != 'on') {
		return;
	}

	foreach ($post['active_interfaces'] as $active_interface) {
		$realif = get_real_interface($active_interface);
		$ip = find_interface_ip($realif);

		if (!is_ipaddrv4($ip)) {
			$ifdescr = convert_friendly_interface_to_friendly_descr($active_interface);

			$input_errors[] = gettext("{$ifdescr} interface has no IPv4 address assigned.");
		}
	}

	if (!empty($post['notifications_recipient']) &&
	    (filter_var($post['notifications_recipient'], FILTER_VALIDATE_EMAIL) === false)) {
		$input_errors[] = gettext("{$post['notifications_recipient']} is not a valid email address.");
	}

	for ($i = 0; $i < 30; $i++) {
		if (!empty($post["mac{$i}"]) && !is_macaddr($post["mac{$i}"])) {
			$input_errors[] = "{$post["mac{$i}"]} is not valid MAC address.";
		}
	}
}

function arpwatch_resync_config_command() {
	arpwatch_install_command();
}

function arpwatch_get_arp_file($ifname) {
	return ARPWATCH_LOCAL_DIR."/arp_{$ifname}.dat";
}

function arpwatch_update_vendors($args) {
	exec('/usr/bin/fetch -qo - '.ARPWATCH_ETHERCODES_URL.'|'
	    .ARPWATCH_LOCAL_DIR.'/massagevendor '.$args.' >'
	    .ARPWATCH_LOCAL_DIR.'/ethercodes.dat');
}

function arpwatch_clear_database() {
	unlink_if_exists(ARPWATCH_LOCAL_DIR."/arp_*.dat");
	unlink_if_exists(ARPWATCH_LOCAL_DIR."/arp_*.dat-");
}

function arpwatch_parse_database() {
	$arpwatch_config = config_get_path('installedpackages/arpwatch/config/0', []);
	$active_interfaces = $arpwatch_config['active_interfaces'];

	$database = array();
	$entries = array();

	foreach (explode(',', $active_interfaces) as $active_interface) {
		$ifname = convert_friendly_interface_to_real_interface_name($active_interface);
		$arp_file = arpwatch_get_arp_file($ifname);

		if (file_exists($arp_file)) {
			$database = file($arp_file);
		}

		foreach ($database as $line) {
			list($mac, $ip, $timestamp, $hostname) = explode("\t", trim($line));

			$pattern = '/(^|:)([a-f0-9])(:|$)/';

			while (preg_match($pattern, $mac)) {
				$mac = preg_replace('/(^|:)([a-f0-9])(:|$)/', '${1}0${2}${3}', $mac);
			}

			$oui = strtolower(substr($mac, 0, 8));
			$matches = array();

			if (file_exists(ARPWATCH_LOCAL_DIR.'/ethercodes.dat') && 
			    preg_match("/^$oui\s+(.*)$/m", file_get_contents(ARPWATCH_LOCAL_DIR.'/ethercodes.dat'), $matches)) {
				$vendor = $matches[1];
			} else {
				$vendor = 'unknown';
			}

			$entry = [
				'ifname' => $ifname,
				'ifdescr' => strtoupper($active_interface),
				'mac' => $mac,
				'vendor' => $vendor,
				'ip' => $ip,
				'hostname' => '',
				'timestamp' => '',
			];

			if ($hostname) {
				$entry['hostname'] = $hostname;
			}

			if ($timestamp) {
				$entry['timestamp'] = strftime('%c', $timestamp);
			}

			$entries[] = $entry;
		}
	}

	usort($entries, function($e1, $e2){
		if ($e1['ifdescr'] == $e2['ifdescr']) {
			return 0;
		}

		return ($e1['ifdescr'] < $e2['ifdescr']) ? -1 : 1;
	});

	return $entries;
}

function arpwatch_get_database_entries() {
	// update arp files
	sigkillbyname("arpwatch", "ALRM");

	// wait a bit
	usleep(0.2 * 10**6);

	return arpwatch_parse_database();
}

function arpwatch_install_sendmail_proxy() {
	$sendmail_proxy =<<< 'EOF'
#!/usr/bin/env php
<?php
require_once("notices.inc");

$fd = fopen('php://stdin','r');
$message = stream_get_contents($fd);
fclose($fd);

if ((false !== $message) && ((false === strpos($message, ': Cron ')) ||
    (config_get_path('installedpackages/arpwatch/config/0/disable_cron') != 'on'))) {
	$subject = array();
	preg_match('/^Subject: (.*)$/m', $message, $subject);
	foreach (config_get_path('installedpackages/arpwatch/config/0/row', []) as $sup) {
		if (!empty($sup['mac']) && strpos($message, strtolower($sup['mac'])) &&
		    (($sup['notification_type'] == 'all') || ($sup['notification_type'] == $subject[1]))) {
			return;
		}
	}
	$message = preg_replace('/^From: .*$/m', '', $message);
	$message = preg_replace('/^To: .*$/m', '', $message);
	$message = preg_replace('/^Subject: .*$/m', '', $message);
	$message = preg_replace("/^(\n){4}/", '', $message);
	$send_subject = config_get_path('system/hostname') . config_get_path('system/domain') . " - Arpwatch Notification : {$subject[1]}";

	send_smtp_message($message, $send_subject);
	if (function_exists('notify_via_telegram')) {
		notify_via_telegram($send_subject . " - " . $message);
	}
	if (function_exists('notify_via_pushover')) {
		notify_via_pushover($send_subject . " - " . $message);
	}
}

?>

EOF;

	file_put_contents(ARPWATCH_SENDMAIL_PROXY, $sendmail_proxy);
	chmod(ARPWATCH_SENDMAIL_PROXY, 0755);
	
	if (!file_exists(ARPWATCH_SENDMAIL_PATH)) {
		@symlink(ARPWATCH_SENDMAIL_PROXY, ARPWATCH_SENDMAIL_PATH);
	}
}

function arpwatch_uninstall_sendmail_proxy() {
	if (file_exists(ARPWATCH_SENDMAIL_PATH) && is_link(ARPWATCH_SENDMAIL_PATH) && readlink(ARPWATCH_SENDMAIL_PATH) == ARPWATCH_SENDMAIL_PROXY) {
		unlink(ARPWATCH_SENDMAIL_PATH);
	}

	@unlink(ARPWATCH_SENDMAIL_PROXY);
}

?>
