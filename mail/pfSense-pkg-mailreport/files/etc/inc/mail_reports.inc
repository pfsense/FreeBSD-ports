<?
/*
 * mail_reports.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2011 Rubicon Communications, LLC (Netgate)
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

$logfile_friendly = array(
	"dhcpd"      => "DHCP",
	"filter"     => "Firewall (raw)",
	"gateways"   => "Gateway Events",
	"installer"  => "Installation",
	"ipsec"      => "IPsec VPN",
	"l2tps"      => "L2TP Server (raw)",
	"lighttpd"   => "Web Server (lighttpd)",
	"ntpd"       => "NTP",
	"openvpn"    => "OpenVPN",
	"poes"       => "PPPoE Server (raw)",
	"portalauth" => "Captive Portal Authentication",
	"ppp"        => "PPP",
	"pptps"      => "PPTP Server (raw)",
	"relayd"     => "Load Balancer (relayd)",
	"resolver"   => "DNS Resolver",
	"routing"    => "Routing",
	"system"     => "System",
	"vpn"        => "PPTP/L2TP/PPPoE Server Login Events",
	"wireless"   => "Wireless"
);

function set_mail_report_cron_jobs($a_mailreports) {
	global $config, $g;

	if(!$config['cron']['item'])
		$config['cron']['item'] = array();

	// Search for old report cron jobs and remove them all
	for ($x=0, $numjobs=sizeof($config['cron']['item']); $x < $numjobs; $x++) {
		if(strpos($config['cron']['item'][$x]['command'], "mail_reports_generate.php") !== FALSE) {
			unset($config['cron']['item'][$x]);
		}
	}

	// Add new cron jobs
	foreach($a_mailreports as $id => $report) {
		$cron_item = array();
		$cron_item['minute'] = 0;
		$cron_item['hour'] = isset($report['timeofday']) ? $report['timeofday'] : 0;
		$cron_item['mday'] = isset($report['dayofmonth']) ? $report['dayofmonth'] : "*";
		$cron_item['month'] = isset($report['monthofyear']) ? $report['monthofyear'] : "*";;
		$cron_item['wday'] = isset($report['dayofweek']) ? $report['dayofweek'] : "*";
		$cron_item['who'] = "root";
		$cron_item['command'] = "/usr/local/bin/mail_reports_generate.php {$id} &";
		$config['cron']['item'][] = $cron_item;
	}
}

include('phpmailer/PHPMailerAutoload.php');

function mail_report_send($headertext, $cmdtext, $logtext, $attachments) {
	global $config, $g;

	if (empty($config['notifications']['smtp']['ipaddress']))
		return;

	$mail = new PHPMailer();
	$mail->IsSMTP();
	$mail->Host = $config['notifications']['smtp']['ipaddress'];
	$mail->Port = empty($config['notifications']['smtp']['port']) ? 25 : $config['notifications']['smtp']['port'];

	if (isset($config['notifications']['smtp']['ssl'])) {
		$mail->SMTPSecure =  "ssl";
		$mail->SMTPAutoTLS = true;
	} else {
		$mail->SMTPSecure =  "";
		$mail->SMTPAutoTLS = false;
	}

	if($config['notifications']['smtp']['username'] &&
	   $config['notifications']['smtp']['password']) {
		$mail->SMTPAuth	= true;
		$mail->Username	= $config['notifications']['smtp']['username'];
		$mail->Password	= $config['notifications']['smtp']['password'];
	}

	$mail->ContentType = 'text/html';
	$mail->IsHTML(true);
	$mail->AddReplyTo($config['notifications']['smtp']['fromaddress'], "Firewall Email Report");
	$mail->SetFrom($config['notifications']['smtp']['fromaddress'], "Firewall Email Report");
	$address = $config['notifications']['smtp']['notifyemailaddress'];
	$mail->AddAddress($address, "Report Recipient");
	$mail->Subject = "{$config['system']['hostname']}.{$config['system']['domain']} Email Report: {$headertext}";
	$mail->Body .= "This is a periodic report from your firewall, {$config['system']['hostname']}.{$config['system']['domain']}.<br /><br />Current report: {$headertext}<br />\n<br />\n";
	if (!empty($cmdtext))
		$mail->Body .= $cmdtext;
	if (!empty($logtext))
		$mail->Body .= $logtext;
	if(is_array($attachments)) {
		foreach($attachments as $filename) {
			$shortname = basename($filename);
			$mail->AddEmbeddedImage($filename, $shortname, $shortname);
			$mail->Body .= "<br/><br/>{$shortname}<br/><img src=\"cid:{$shortname}\" />\n";
		}
	}

	if(!$mail->Send()) {
		echo "Mailer Error: " . $mail->ErrorInfo;
	}
}

function mail_report_get_log($logfile, $tail, $grepfor) {
	global $g, $config;
	$logfile = "{$g['varlog_path']}/{$logfile}";
	$logarr = "";
	$grepline = "  ";
	if(is_array($grepfor))
		$grepline = " | /usr/bin/egrep " . escapeshellarg(implode("|", $grepfor));
	if($config['system']['disablesyslogclog']) {
		exec("cat {$logfile}{$grepline} | /usr/bin/tail -n {$tail}", $logarr);
	} else {
		if (is_executable("/usr/local/sbin/clog")) {
			exec("/usr/local/sbin/clog {$logfile}{$grepline}| grep -v \"CLOG\" | grep -v \"\033\" | /usr/bin/tail -n {$tail}", $logarr);
		} elseif (is_executable("/usr/sbin/clog")) {
			exec("/usr/sbin/clog {$logfile}{$grepline}| grep -v \"CLOG\" | grep -v \"\033\" | /usr/bin/tail -n {$tail}", $logarr);
		} else {
			$logarr = array("Cannot locate clog which is required for reading log files.");
		}
	}
	return($logarr);
}

function get_friendly_log_name($logfile) {
	global $logfile_friendly;
	$friendly = str_replace(".log", "", $logfile);
	if (!empty($logfile_friendly[$friendly]))
		$friendly = $logfile_friendly[$friendly];
	return $friendly;
}

?>
