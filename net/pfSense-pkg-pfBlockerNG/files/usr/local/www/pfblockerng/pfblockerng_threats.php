<?php
/*
	pfBlockerNG_threats.php

	pfBlockerNG
	Copyright (c) 2015-2016 BBcan177@gmail.com
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
?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h4 class="panel-title"><?=gettext("Threat:&emsp;" . $host . $domain); ?></h4>
	</div>
	<div>
		<p class="text-center"><br />NOTE:&emsp;The following links are to external services, so their reliability cannot be guaranteed
			It is also recommended to open these links in a different Browser</p>
	</div>
	<div>
		<table class="table table-striped table-hover table-compact">
			<thead>
				<tr>
					<th width="20%"><!-- Icon field --></th>
					<th><!-- Threat Source Link --></th>
				</tr>
			</thead>
			<tbody>
				<?php if (isset($_REQUEST['host'])): ?>
				<!-- IP threat source links -->
				<tr>
					<td><font color="blue">Threat Lookups</font><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="http://www.ipvoid.com/scan/<?=$host;?>/">
						<?=gettext("IPVOID");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="http://www.tcpiputils.com/browse/ip-address/<?=$host;?>/">
						<?=gettext("TCPUtils");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.herdprotect.com/ip-address-<?=$host;?>.aspx">
						<?=gettext("Herd Protect");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.senderbase.org/lookup/ip/?search_string=<?=$host;?>">
					<?=gettext("SenderBase");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>	
					<td><a target="_blank" href="http://www.ip-tracker.org/locator/ip-lookup.php?ip=<?=$host;?>">
						<?=gettext("IP Tracker");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.fortiguard.com/ip_rep/index.php?data=/<?=$host;?>?">
						<?=gettext("FortiGuard");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.projecthoneypot.org/ip_<?=$host;?>">
						<?=gettext("Project HoneyPot");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.virustotal.com/en/ip-address/<?=$host;?>/information">
						<?=gettext("VirusTotal Info");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.mcafee.com/threat-intelligence/ip/default.aspx?ip=<?=$host;?>">
						<?=gettext("McAfee Threat Center");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://sitecheck.sucuri.net/results/<?=$host;?>">
						<?=gettext("Securi SiteCheck");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.dshield.org/ipinfo.html?ip=<?=$host;?>">
						<?=gettext("DShield Threat Lookup");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://isc.sans.edu/ipinfo.html?ip=<?=$host;?>">
						<?=gettext("Internet Storm Center");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.mywot.com/en/scorecard/<?=$host;?>">
						<?=gettext("Web of Trust (WOT) Scorecard");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://quttera.com/sitescan/<?=$host;?>">
						<?=gettext("Quattera");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.iblocklist.com/search.php?string=<?=$host;?>">
						<?=gettext("I-Block List");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.threatminer.org/host.php?q=<?=$host;?>">
						<?=gettext("ThreatMiner");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.threatcrowd.org/ip.php?ip=<?=$host;?>">
						<?=gettext("Threat Crowd");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://ransomwaretracker.abuse.ch/ip/<?=$host;?>">
						<?=gettext("Ransomware Tracker");?></a></td>
				</tr>

				<!-- Mail Server threat source links -->
				<tr>
					<td><font color="blue">Mail Server Lookups</font><i class="fa fa-envelope pull-right"></i></td>
					<td><a target="_blank" href="https://senderscore.org/lookup.php?lookup=<?=$host;?>&ipLookup=Go">
						<?=gettext("SenderScore");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-envelope pull-right"></i></td>
					<td><a target="_blank" href="https://www.spamhaus.org/query/bl?ip=<?=$host;?>">
						<?=gettext("Spamhaus Blocklist");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-envelope pull-right"></i></td>
					<td><a target="_blank" href="https://www.spamcop.net/w3m?action=checkblock&ip=<?=$host;?>">
						<?=gettext("SPAMcop Blocklist");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-envelope pull-right"></i></td>
					<td><a target="_blank" href="http://multirbl.valli.org/lookup/<?=$host;?>.html">
						<?=gettext("multirbl RBL Lookup");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-envelope pull-right"></i></td>
					<td><a target="_blank" href="https://mxtoolbox.com/SuperTool.aspx?action=blacklist%3a<?=$host;?>&run=toolpage">
						<?=gettext("MXToolbox");?></a></td>
				</tr>

				<?php else: ?>

				<!-- Domain threat source links -->
				<tr>
					<td>Domain Lookups<i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="http://www.alexa.com/siteinfo/<?=$domain;?>">
						<?=gettext("Alexa");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.c-sirt.org/en/incidents-on-domain/<?=$domain;?>">
						<?=gettext("C-SIRT");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://safeweb.norton.com/report/show_mobile?name=<?=$domain;?>">
						<?=gettext("Norton Safe Web");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.herdprotect.com/domain-<?=$domain;?>.aspx">
						<?=gettext("HerdProtect");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://sitecheck.sucuri.net/results/<?=$domain;?>">
						<?=gettext("Sucuri");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="http://www.tcpiputils.com/browse/domain/<?=$domain;?>">
						<?=gettext("TCPUtils");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.google.com/safebrowsing/diagnostic?site=<?=$domain;?>">
						<?=gettext("Google SafeBrowsing");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="http://toolbar.netcraft.com/site_report?url=<?=$domain;?>">
						<?=gettext("Netcraft Site Report");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.threatminer.org/domain.php?q=<?=$domain;?>">
						<?=gettext("ThreatMiner");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="http://hosts-file.net/?s=<?=$domain;?>">
						<?=gettext("hpHosts");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.mcafee.com/threat-intelligence/domain/default.aspx?domain=<?=$domain;?>">
						<?=gettext("Intel Security (McAfee)");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.threatcrowd.org/domain.php?domain=<?=$domain;?>">
						<?=gettext("Threat Crowd");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://ransomwaretracker.abuse.ch/host/<?=$domain;?>/">
						<?=gettext("Ransomware Tracker");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://passivedns.mnemonic.no/search/?query=<?=$domain;?>&method=exact">
						<?=gettext("mnemonic passiveDNS");?></a></td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?php include('foot.inc'); ?>
