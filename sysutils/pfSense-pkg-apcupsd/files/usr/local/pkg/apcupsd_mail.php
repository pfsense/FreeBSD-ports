<?php
/*
 * apcupsd_mail.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013-2016 Danilo G. Baio <dbaio@bsd.com.br>
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
require_once("pkg-utils.inc");
require_once("globals.inc");
require_once("notices.inc");

$apcstatus['killpower'] = gettext("UPS now committed to shut down");
$apcstatus['commfailure'] = gettext("Communications with UPS lost");
$apcstatus['commok'] = gettext("Communications with UPS restored");
$apcstatus['onbattery'] = gettext("Power failure. Running on UPS batteries");
$apcstatus['offbattery'] = gettext("Power has returned...");
$apcstatus['failing'] = gettext("UPS battery power exhausted. Doing shutdown");
$apcstatus['timeout'] = gettext("UPS battery runtime limit exceeded. Doing shutdown");
$apcstatus['loadlimit'] = gettext("UPS battery discharge limit reached. Doing shutdown");
$apcstatus['runlimit'] = gettext("UPS battery runtime percent reached. Doing shutdown");
$apcstatus['doreboot'] = gettext("Beginning Reboot Sequence");
$apcstatus['doshutdown'] = gettext("Beginning Shutdown Sequence");
$apcstatus['annoyme'] = gettext("Power problems please logoff");
$apcstatus['emergency'] = gettext("Emergency Shutdown. Possible UPS battery failure");
$apcstatus['changeme'] = gettext("Emergency! UPS batteries have failed. Change them NOW");
$apcstatus['remotedown'] = gettext("Remote Shutdown. Beginning Shutdown Sequence");

if (empty($argv[1]) || empty($apcstatus["$argv[1]"])) {
	return;
}

$apcsubject = "apcupsd - " . $apcstatus["$argv[1]"];
$apcmessage = gettext("Status information from apcupsd") . ":\n";

putenv("PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin");
$ph = popen('apcaccess status 2>&1', "r" );
while ($line = fgets($ph)) {
	$apcmessage .= $line;
}
pclose($ph);

send_smtp_message($apcmessage, $apcsubject);

?>
