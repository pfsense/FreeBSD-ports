<?
/* $Id$ */
/*
	mail_reports.inc
	Part of pfSense
	Copyright (C) 2011 Jim Pingle <jimp@pfsense.org>
	Portions Copyright (C) 2007-2011 Seth Mos <seth.mos@dds.nl>
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
