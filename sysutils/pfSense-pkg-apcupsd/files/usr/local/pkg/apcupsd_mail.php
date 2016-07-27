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
require_once("notices.inc");

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

$apcsubject = "apcupsd - " . $apcstatus["$argv[1]"];
$apcmessage = "Status information from apcupsd:\n";

putenv("PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin");
$ph = popen('apcaccess status 2>&1', "r" );
while ($line = fgets($ph)) {
	$apcmessage .= $line;
}
pclose($ph);

send_smtp_message($apcmessage, $apcsubject);

?>
