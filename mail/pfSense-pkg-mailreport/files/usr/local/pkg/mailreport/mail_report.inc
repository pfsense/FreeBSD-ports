<?
/*
 * mail_report.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2007-2011 Seth Mos <seth.mos@dds.nl>
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

require_once("globals.inc");
require_once("config.inc");
require_once("filter.inc");
require_once("syslog.inc");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once('mailreport/phpmailer/Exception.php');
require_once('mailreport/phpmailer/PHPMailer.php');
require_once('mailreport/phpmailer/SMTP.php');

$logfile_friendly = array(
	"auth"            => gettext("Authentication (General)"),
	"bsdinstall_log"  => gettext("Installation"),
	"dhcpd"           => gettext("DHCP"),
	"filter"          => gettext("Firewall (raw)"),
	"gateways"        => gettext("Gateway Events"),
	"ipsec"           => gettext("IPsec VPN"),
	"l2tps"           => gettext("L2TP Server (raw)"),
	"lighttpd"        => gettext("Web Server (lighttpd)"),
	"nginx"           => gettext("Web Server (nginx)"),
	"ntpd"            => gettext("NTP"),
	"openvpn"         => gettext("OpenVPN"),
	"poes"            => gettext("PPPoE Server (raw)"),
	"portalauth"      => gettext("Captive Portal Authentication"),
	"ppp"             => gettext("PPP"),
	"pptps"           => gettext("PPTP Server (raw)"),
	"resolver"        => gettext("DNS Resolver"),
	"routing"         => gettext("Routing"),
	"system"          => gettext("System"),
	"userlog"         => gettext("OS Account Changes"),
	"vpn"             => gettext("PPPoE/L2TP Server Login Events"),
	"wireless"        => gettext("Wireless")
);

function set_mail_report_cron_jobs($a_mailreports) {
	global $config, $g;

	init_config_arr(array('cron', 'item'));

	// Search for old report cron jobs and remove them all
	for ($x=0, $numjobs=sizeof($config['cron']['item']); $x < $numjobs; $x++) {
		if (strpos($config['cron']['item'][$x]['command'], "mail_reports_generate.php") !== FALSE) {
			unset($config['cron']['item'][$x]);
		}
	}

	// Add new cron jobs
	foreach ($a_mailreports as $id => $report) {
		$cron_item = array();
		$cron_item['minute'] = isset($report['minuteofday']) ? $report['minuteofday'] : 0;
		$cron_item['hour'] = isset($report['timeofday']) ? $report['timeofday'] : 0;
		$cron_item['mday'] = isset($report['dayofmonth']) ? $report['dayofmonth'] : "*";
		$cron_item['month'] = isset($report['monthofyear']) ? $report['monthofyear'] : "*";
		$cron_item['wday'] = isset($report['dayofweek']) ? $report['dayofweek'] : "*";
		$cron_item['who'] = "root";
		$cron_item['command'] = "/usr/local/bin/mail_reports_generate.php {$id} &";
		$config['cron']['item'][] = $cron_item;
	}
}

function mail_report_send($headertext, $cmdtext, $logtext, $attachments) {
	global $config, $g;

	if (empty($config['notifications']['smtp']['ipaddress']) ||
	    empty($config['notifications']['smtp']['notifyemailaddress'])) {
		return;
	}

	$mail = new PHPMailer();
	$mail->IsSMTP();
	$mail->Host = $config['notifications']['smtp']['ipaddress'];
	$mail->Port = empty($config['notifications']['smtp']['port']) ? 25 : $config['notifications']['smtp']['port'];
	$mail->SMTPSecure = isset($config['notifications']['smtp']['ssl']) ? "ssl" : "";

	if ($config['notifications']['smtp']['sslvalidate'] == "disabled") {
		$mail->SMTPOptions = array(
			'ssl' => array(
			'verify_peer'       => false,
			'verify_peer_name'  => false,
			'allow_self_signed' => true,
			),
		);
	} else {
		$mail->SMTPOptions = array(
			'ssl' => array(
			'verify_peer'       => true,
			'verify_peer_name'  => true,
			),
		);
	}

	if ($config['notifications']['smtp']['username'] &&
	    $config['notifications']['smtp']['password']) {
		$mail->SMTPAuth = true;
		$mail->Username = $config['notifications']['smtp']['username'];
		$mail->Password = $config['notifications']['smtp']['password'];
	}

	$mail->ContentType = 'text/html';
	$mail->IsHTML(true);
	$mail->AddReplyTo($config['notifications']['smtp']['fromaddress'], gettext("pfSense Email Report"));
	$mail->SetFrom($config['notifications']['smtp']['fromaddress'], gettext("pfSense Email Report"));
	$addresses = explode(",", $config['notifications']['smtp']['notifyemailaddress']);
	foreach ($addresses as $address) {
		$recipient = explode("@", $address);
		$recipient = str_replace(array("_", "."), " ", $recipient[0]);
		$mail->AddAddress($address, ucwords(strtolower($recipient)));
	}
	$mail->Subject = "{$config['system']['hostname']}.{$config['system']['domain']} " . gettext("Email Report") . ": {$headertext}";
	$mail->Body .= sprintf(gettext("This is a periodic report from pfSense at %s."), $config['system']['hostname'] . '.' . $config['system']['domain']);
	$mail->Body .= "<br />\n<br />\n" . gettext("Current report") . ": {$headertext}<br />\n<br />\n";
	if (!empty($cmdtext)) {
		$mail->Body .= $cmdtext;
	}
	if (!empty($logtext)) {
		$mail->Body .= $logtext;
	}
	if (is_array($attachments)) {
		foreach($attachments as $filename) {
			$shortname = basename($filename);
			$mail->AddEmbeddedImage($filename, $shortname, $shortname);
			$mail->Body .= "<br/><br/>{$shortname}<br/><img src=\"cid:{$shortname}\" />\n";
		}
	}

	if (!$mail->Send()) {
		echo gettext("Mailer Error") . ": " . $mail->ErrorInfo;
	}
}

function mail_report_get_log($logfile, $tail = 100, $grepfor = array()) {
	global $g, $config;
	$logfile = "{$g['varlog_path']}/{$logfile}";
	$logarr = "";
	$grepline = "  ";

	if (is_array($grepfor) && !empty($grepfor)) {
		$grepline = " | /usr/bin/egrep " . escapeshellarg(implode("|", $grepfor));
	}

	exec(system_log_get_cat() . ' ' . sort_related_log_files($logfile, true, true) . "{$grepline} | /usr/bin/tail -n " . escapeshellarg($tail), $logarr);

	if (empty($logarr)) {
		$logarr = array(gettext("Log file is empty."));
	}

	return($logarr);
}

function get_friendly_log_name($logfile) {
	global $logfile_friendly;
	$friendly = str_replace(".log", "", $logfile);
	if (!empty($logfile_friendly[$friendly])) {
		$friendly = $logfile_friendly[$friendly];
	}
	return $friendly;
}
