<?php
/*
 * pfblockerng_threats.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2018 BBcan177@gmail.com
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the \"License\");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an \"AS IS\" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

$title = $host = $domain = $port = '';
if (isset($_REQUEST)) {
	if (isset($_REQUEST['host'])) {
		$title	= 'Source IP';
		$host	= htmlspecialchars($_REQUEST['host']);
	} elseif (isset($_REQUEST['domain'])) {
		$title	= 'Domain';
		$domain	= htmlspecialchars($_REQUEST['domain']);
	} elseif (isset($_REQUEST['port']) && ctype_digit($_REQUEST['port'])) {
		$title	= 'Port';
		$port	= htmlspecialchars($_REQUEST['port']);
	}
}

$pgtitle = array(gettext('Firewall'), gettext('pfBlockerNG'), gettext('Alerts'), gettext("Threat {$title} Lookup"));
$pglinks = array('', '/pfblockerng/pfblockerng_general.php', '/pfblockerng/pfblockerng_alerts.php', '@self');
require('guiconfig.inc');
include('head.inc');
?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h4 class="panel-title"><?=gettext("Threat {$title}:&emsp;" . $host . $domain . $port); ?></h4>
	</div>
	<div>
		<p class="text-center"><br />NOTE:&emsp;The following links are to external services, so their reliability cannot be guaranteed.
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
					<td><span style="color: blue;">Threat Lookups</span><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="http://www.ipvoid.com/scan/<?=$host;?>/">
						<?=gettext("IPVOID");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://dnslytics.com/ip/<?=$host;?>/">
						<?=gettext("DNSlytics");?></a></td>
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
					<td><a target="_blank" href="https://www.fortiguard.com/ip_rep/index.php?data=<?=$host;?>?">
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
					<td><a target="_blank" href="https://isc.sans.edu/ipinfo.html?ip=<?=$host;?>">
						<?=gettext("Internet Storm Center");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://isc.sans.edu/api/ip/<?=$host;?>">
						<?=gettext("Internet Storm Center API summary");?></a></td>
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
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.shodan.io/host/<?=$host;?>">
						<?=gettext("Shodan");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="http://viewdns.info/reverseip/?host=<?=$host;?>&t=1">
						<?=gettext("ViewDNS.info Reverse IP Lookup");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.webiron.com/iplookup/<?=$host;?>">
						<?=gettext("WebIron");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://support.proofpoint.com/rbl-lookup.cgi?ip=<?=$host;?>">
						<?=gettext("Proofpoint Dynamic Reputation - IP Lookup");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="http://www.reputationauthority.org/lookup.php?ip=<?=$host;?>">
						<?=gettext("WatchGuard - Reputation Authority");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.robtex.com/ip-lookup/<?=$host;?>">
						<?=gettext("Robtex: IP Blacklists");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.talosintelligence.com/reputation_center/lookup?search=<?=$host;?>">
						<?=gettext("Talos Threat Intelligence");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://censys.io/ipv4/<?=$host;?>">
						<?=gettext("Censys search engine");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://dnstrails.com/#/list/domain/<?=$host;?>/type/ip/page/1">
						<?=gettext("DNSTrails");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://pulsedive.com/indicator/?ioc=<?=base64_encode($host);?>">
						<?=gettext("PulseDive");?></a></td>
				</tr>
				<br />

				<!-- Mail Server threat source links -->
				<tr>
					<td><span style="color: blue;">Mail Server Lookups</span><i class="fa fa-envelope pull-right"></i></td>
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

				<?php elseif ($_REQUEST['domain']): ?>

				<!-- Domain threat source links -->
				<tr>
					<td>Domain Lookups<i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.talosintelligence.com/reputation_center/lookup?search=<?=$domain;?>">
						<?=gettext("Talos Threat Intelligence");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.alexa.com/siteinfo/<?=$domain;?>">
						<?=gettext("Alexa");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://safeweb.norton.com/report/show?url=<?=$domain;?>">
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
					<td><a target="_blank" href="https://dnslytics.com/domain/<?=$domain;?>">
						<?=gettext("DNSlytics");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.google.com/safebrowsing/diagnostic?site=<?=$domain;?>">
						<?=gettext("Google SafeBrowsing");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.google.com/transparencyreport/safebrowsing/diagnostic/index.html#url=<?=$domain;?>">
						<?=gettext("Google Transparency Report");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://toolbar.netcraft.com/site_report?url=<?=$domain;?>">
						<?=gettext("Netcraft Site Report");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.threatminer.org/domain.php?q=<?=$domain;?>">
						<?=gettext("ThreatMiner");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://hosts-file.net/?s=<?=$domain;?>">
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
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://urlscan.io/">
						<?=gettext("URL Scan");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.virustotal.com/en/domain/<?=$domain;?>/information/">
						<?=gettext("Virus Total");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://otx.alienvault.com/browse/pulses/?q=<?=$domain;?>&sort=-modified">
						<?=gettext("OTX Alienvault");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="http://viewdns.info/reverseip/?host=<?=$domain;?>&t=1">
						<?=gettext("ViewDNS.info Reverse Domain Lookup");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="http://viewdns.info/iphistory/?domain=<?=$domain;?>">
						<?=gettext("ViewDNS.info Domain IP History Lookup");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="http://www.reputationauthority.org/domain_lookup.php?ip=<?=$domain;?>">
						<?=gettext("WatchGuard - Reputation Authority");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.robtex.com/dns-lookup/<?=$domain;?>">
						<?=gettext("Robtex: Summary");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="http://www.isithacked.com/check/<?=$domain;?>">
						<?=gettext("Is It Hacked?");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://pgl.yoyo.org/adservers/details.php?hostname=<?=$domain;?>">
						<?=gettext("Yoyo Domain Lookup");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://censys.io/domain?q=<?=$domain;?>">
						<?=gettext("Censys search engine");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://dnstrails.com/#/domain/domain/<?=$domain;?>">
						<?=gettext("DNSTrails");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.google.ca/search?q=site%3A<?=$domain;?>">
						<?=gettext("Google Site: Search");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://pulsedive.com/indicator/?ioc=<?=base64_encode($domain);?>">
						<?=gettext("PulseDive");?></a></td>
				</tr>

			<?php else: ?>

				<!-- Port threat links -->
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://isc.sans.edu/port.html?port=<?=$port;?>">
						<?=gettext("ISC - Internet Storm Center");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.speedguide.net/port.php?port=<?=$port;?>">
						<?=gettext("Speed Guide - Port database");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://en.wikipedia.org/wiki/List_of_TCP_and_UDP_port_numbers">
						<?=gettext("Wikipedia List of TCP/UDP Ports");?></a></td>
				</tr>

			<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?php include('foot.inc'); ?>
