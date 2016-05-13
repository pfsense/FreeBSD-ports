<?php
/*
	apcupsd_mail.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2014-2016 Danilo G. Baio <dbaio@bsd.com.br>
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
require_once("pkg-utils.inc");
require_once("globals.inc");
require_once("phpmailer/PHPMailerAutoload.php");

global $config, $g;

$apcstatus[killpower] = "UPS now committed to shut down";
$apcstatus[commfailure] = "Communications with UPS lost";
$apcstatus[commok] = "Communications with UPS restored";
$apcstatus[onbattery] = "Power failure. Running on UPS batteries";
$apcstatus[offbattery] = "Power has returned...";
$apcstatus[failing] = "UPS battery power exhausted. Doing shutdown";
$apcstatus[timeout] = "UPS battery runtime limit exceeded. Doing shutdown";
$apcstatus[loadlimit] = "UPS battery discharge limit reached. Doing shutdown";
$apcstatus[runlimit] = "UPS battery runtime percent reached. Doing shutdown";
$apcstatus[doreboot] = "Beginning Reboot Sequence";
$apcstatus[doshutdown] = "Beginning Shutdown Sequence";
$apcstatus[annoyme] = "Power problems please logoff";
$apcstatus[emergency] = "Emergency Shutdown. Possible UPS battery failure";
$apcstatus[changeme] = "Emergency! UPS batteries have failed. Change them NOW";
$apcstatus[remotedown] = "Remote Shutdown. Beginning Shutdown Sequence";

if (empty($argv[1]) || empty($apcstatus["$argv[1]"])) {
	return;
}

$apcsubject = $apcstatus["$argv[1]"];

if (empty($config['notifications']['smtp']['ipaddress'])) {
	return;
}

$mail = new PHPMailer();
$mail->IsSMTP();
$mail->Host = $config['notifications']['smtp']['ipaddress'];

if ((isset($config['notifications']['smtp']['ssl']) && $config['notifications']['smtp']['ssl'] != "unchecked") || $config['notifications']['smtp']['ssl'] == "checked") {
	$mail->SMTPSecure = "ssl";
}

if ((isset($config['notifications']['smtp']['tls']) && $config['notifications']['smtp']['tls'] != "unchecked") || $config['notifications']['smtp']['tls'] == "checked") {
	$mail->SMTPSecure = "tls";
}

$mail->Port = empty($config['notifications']['smtp']['port']) ? 25 : $config['notifications']['smtp']['port'];

if ($config['notifications']['smtp']['username'] && $config['notifications']['smtp']['password']) {
	$mail->SMTPAuth	= true;
	$mail->Username	= $config['notifications']['smtp']['username'];
	$mail->Password	= $config['notifications']['smtp']['password'];
}

$mail->ContentType = 'text/html';
$mail->IsHTML(true);
$mail->AddReplyTo($config['notifications']['smtp']['fromaddress'], "Apcupsd");
$mail->SetFrom($config['notifications']['smtp']['fromaddress'], "Apcupsd");
$address = $config['notifications']['smtp']['notifyemailaddress'];
$mail->AddAddress($address, "Apcupsd Recipient");
$mail->Subject = "{$config['system']['hostname']}.{$config['system']['domain']} - {$apcsubject}";

putenv("PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin");
$mail->Body = "<pre>";
$ph = popen('apcaccess status 2>&1', "r" );
while ($line = fgets($ph)) $mail->Body .= htmlspecialchars($line);
pclose($ph);
$mail->Body .= "</pre>";

if (!$mail->Send()) {
	echo "Mailer Error: " . $mail->ErrorInfo;
}

?>
