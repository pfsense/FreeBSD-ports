<?php
/*
	pfBlockerNG_threats.php

	pfBlockerNG
	Copyright (c) 2015 BBcan177@gmail.com
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

$pgtitle = array(gettext('pfBlockerNG'), gettext('Threat Source Lookup'));
require('guiconfig.inc');

if (isset($_REQUEST['host'])) {
	$host = htmlspecialchars($_REQUEST['host']);
}

if (isset($_REQUEST['domain'])) {
	$domain = htmlspecialchars($_REQUEST['domain']);
}

include('head.inc');
include('fbegin.inc');
?>

<tr>
	<td>
	<div id="mainarea">
	<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
	<table width="100%" border="0" cellpadding="6" cellspacing="0" summary="tabcont">
		<tbody>
			<tr>
				<td colspan="2" class="listtopic"><?php echo gettext("Threat"); ?></td>
			</tr>
			<tr>
				<td colspan="2" class="listr"><font size="3"><?php echo " {$host}{$domain}"; ?></font></td>
			</tr>
			<tr>
				<td colspan="2" class="vncell"></td>
			</tr>
			<tr>
				<td colspan="2" class="listtopic"><?php echo gettext("Threat Lookups"); ?></td>
			</tr>
			<tr>
			<td width="14%" valign="top" class="vncell"></td>
			<td width="86%" class="listr">
				<?=gettext("NOTE:");?><br /><br />
				<?=gettext("The following links are to external services, so their reliability cannot be guaranteed.");?><br />
				<?=gettext("It is also recommended to open these links in a different Browser.");?><br /><br />

				<?php if (isset($_REQUEST['host'])) { ?>

				<a target="_blank" href="http://www.ipvoid.com/scan/<?php echo $host; ?>/">
					<?=gettext("IPVOID");?></a><br />
				<a target="_blank" href="http://www.tcpiputils.com/browse/ip-address/<?php echo $host; ?>/">
					<?=gettext("TCPUtils");?></a><br />
				<a target="_blank" href="https://www.herdprotect.com/ip-address-<?php echo $host; ?>.aspx">
					<?=gettext("Herd Protect");?></a><br />
				<a target="_blank" href="https://www.senderbase.org/lookup/ip/?search_string=<?php echo $host; ?>">
					<?=gettext("SenderBase");?></a><br />
				<a target="_blank" href="http://www.ip-tracker.org/locator/ip-lookup.php?ip=<?php echo $host; ?>">
					<?=gettext("IP Tracker");?></a><br />
				<a target="_blank" href="https://www.fortiguard.com/ip_rep/index.php?data=/<?php echo $host; ?>?">
					<?=gettext("FortiGuard");?></a><br />
				<a target="_blank" href="https://www.projecthoneypot.org/ip_<?php echo $host; ?>">
					<?=gettext("Project HoneyPot");?></a><br />
				<a target="_blank" href="https://www.virustotal.com/en/ip-address/<?php echo $host; ?>/information">
					<?=gettext("VirusTotal Info");?></a><br />
				<a target="_blank" href="https://www.mcafee.com/threat-intelligence/ip/default.aspx?ip=<?php echo $host; ?>">
					<?=gettext("McAfee Threat Center");?></a><br />
				<a target="_blank" href="https://sitecheck.sucuri.net/results/<?php echo $host; ?>">
					<?=gettext("Securi SiteCheck");?></a><br />
				<a target="_blank" href="https://www.dshield.org/ipinfo.html?IP=<?php echo $host; ?>">
					<?=gettext("DShield Threat Lookup");?></a><br />
				<a target="_blank" href="https://isc.sans.edu/ipinfo.html?ip=<?php echo $host; ?>">
					<?=gettext("Internet Storm Center");?></a><br />
				<a target="_blank" href="https://www.mywot.com/en/scorecard/<?php echo $host; ?>">
					<?=gettext("Web of Trust (WOT) Scorecard");?></a><br />
				<a target="_blank" href="https://quttera.com/sitescan/<?php echo $host; ?>">
					<?=gettext("Quattera");?></a><br />
				<a target="_blank" href="https://www.iblocklist.com/search.php?string=<?php echo $host; ?>">
					<?=gettext("I-Block List");?></a><br />
				<p/>

				<?=gettext("Mail Server DNSRBL Lookups");?><br /><br />
				<a target="_blank" href="https://senderscore.org/lookup.php?lookup=<?php echo $host; ?>&ipLookup=Go">
					<?=gettext("SenderScore");?></a><br />
				<a target="_blank" href="https://www.spamhaus.org/query/bl?ip=<?php echo $host; ?>">
					<?=gettext("Spamhaus Blocklist");?></a><br />
				<a target="_blank" href="https://www.spamcop.net/w3m?action=checkblock&ip=<?php echo $host; ?>">
					<?=gettext("SPAMcop Blocklist");?></a><br />
				<a target="_blank" href="http://multirbl.valli.org/lookup/<?php echo $host; ?>.html">
					<?=gettext("multirbl RBL Lookup");?></a><br />
				<a target="_blank" href="https://mxtoolbox.com/SuperTool.aspx?action=blacklist%3a<?php echo $host; ?>&run=toolpage">
					<?=gettext("MXToolbox");?></a><br />

				<?php } else { ?>

				<a target="_blank" href="http://www.alexa.com/siteinfo/<?php echo $domain; ?>">
					<?=gettext("Alexa");?></a><br />
				<a target="_blank" href="https://www.c-sirt.org/en/incidents-on-domain/<?php echo $domain; ?>">
					<?=gettext("C-SIRT");?></a><br />
				<a target="_blank" href="https://www.herdprotect.com/domain-<?php echo $domain; ?>.aspx">
					<?=gettext("HerdProtect");?></a><br />
				<a target="_blank" href="https://sitecheck.sucuri.net/results/<?php echo $domain; ?>">
					<?=gettext("Sucuri");?></a><br />

				<?php } ?>

			</td>
			</tr>
		</tbody>
	</table>
	</div>
	</td>
</tr>

<?php include('fend.inc'); ?>
</body>
</html>