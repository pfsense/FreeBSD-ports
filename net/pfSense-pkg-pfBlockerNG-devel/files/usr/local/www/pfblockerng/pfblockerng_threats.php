<?php
/*
 * pfblockerng_threats.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2022 BBcan177@gmail.com
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

require('guiconfig.inc');
include('head.inc');

$title = $host = $domain = $port = '';
if (isset($_REQUEST)) {
	if (isset($_REQUEST['host']) && is_ipaddr($_REQUEST['host'])) {
		$title	= 'Source IP';
		$host	= $_REQUEST['host'];
	} elseif (isset($_REQUEST['domain']) && is_domain($_REQUEST['domain'])) {
		$title	= 'Domain';
		$domain = $_REQUEST['domain'];
	} elseif (isset($_REQUEST['port']) && is_port($_REQUEST['port'])) {
		$title	= 'Port';
		$port	= $_REQUEST['port'];
	}
}

$pgtitle = array(gettext('Firewall'), gettext('pfBlockerNG'), gettext('Alerts'), gettext("Threat {$title} Lookup"));
$pglinks = array('', '/pfblockerng/pfblockerng_general.php', '/pfblockerng/pfblockerng_alerts.php', '@self');
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
					<td><a target="_blank" href="http://www.ip-tracker.org/locator/ip-lookup.php?ip=<?=$host;?>">
						<?=gettext("IP Tracker");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.fortiguard.com/webfilter?q=<?=$host;?>&version=8">
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
					<td><a target="_blank" href="https://www.trustedsource.org/en/feedback/url">
						<?=gettext("Trusted Score (McAfee)");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://opentip.kaspersky.com/<?=$host;?>/">
						<?=gettext("Kaspersky Threat Intelligence");?></a></td>
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
					<td><a target="_blank" href="https://www.shodan.io/search?query=<?=$host;?>">
						<?=gettext("Shodan");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="http://viewdns.info/reverseip/?host=<?=$host;?>&t=1">
						<?=gettext("ViewDNS.info Reverse IP Lookup");?></a></td>
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
					<td><a target="_blank" href="https://securitytrails.com/list/ip/<?=$host;?>?page=1">
						<?=gettext("SecurityTrails");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://pulsedive.com/indicator/?ioc=<?=base64_encode($host);?>">
						<?=gettext("PulseDive");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.abuseipdb.com/check/<?=$host;?>">
						<?=gettext("AbuseIPDB");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://bgp.he.net/ip/<?=$host;?>">
						<?=gettext("Hurricane Electric BGP Toolkit");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://myip.ms/info/whois/<?=$host;?>">
						<?=gettext("MYIP.MS");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://viz.greynoise.io/query/?gnql=ip%3A<?=$host;?>">
						<?=gettext("Grey Noise");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://api.mnemonic.no/pdns/v3/<?=$host;?>">
						<?=gettext("mnemonic passiveDNS API");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://api.stopforumspam.org/api?ip=<?=$host;?>">
						<?=gettext("Stop Forum Spam");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://onyphe.io/search/?query=<?=$host;?>">
						<?=gettext("ONYPHE");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="http://rbluri.interserver.net/ip.php?ip=<?=$host;?>">
						<?=gettext("InterServer.net");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://spyse.com/target/ip/<?=$host;?>">
						<?=gettext("SpySe.com");?></a></td>
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
					<td><a target="_blank" href="https://opentip.kaspersky.com/<?=$domain;?>/">
						<?=gettext("Kaspersky Threat Intelligence");?></a></td>
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
                                        <td><a target="_blank" href="https://transparencyreport.google.com/safe-browsing/search?url=<?=$domain;?>">
                                                <?=gettext("Google SafeBrowsing");?></a></td>
                                </tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://yandex.com/safety/?url=<?=$domain;?>">
						<?=gettext("Yandex Safe Browsing");?></a></td>
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
					<td><a target="_blank" href="https://www.trustedsource.org/en/feedback/url">
						<?=gettext("Trusted Score (McAfee)");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.threatcrowd.org/domain.php?domain=<?=$domain;?>">
						<?=gettext("Threat Crowd");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://api.mnemonic.no/pdns/v3/<?=$domain;?>">
						<?=gettext("mnemonic passiveDNS API");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://urlscan.io/domain/<?=$domain;?>">
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
					<td><a target="_blank" href="https://securitytrails.com/domain/<?=$domain;?>/dns">
						<?=gettext("SecurityTrails");?></a></td>
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
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.abuseipdb.com/check/<?=$domain;?>">
						<?=gettext("AbuseIPDB");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.fortiguard.com/webfilter?q=<?=$domain;?>&version=8">
						<?=gettext("FortiGuard");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.shodan.io/search?query=<?=$domain;?>">
						<?=gettext("Shodan");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://viz.greynoise.io/query/?gnql=<?=$domain;?>">
						<?=gettext("Grey Noise");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://onyphe.io/search/?query=<?=$domain;?>">
						<?=gettext("ONYPHE");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="http://rbluri.interserver.net/domain.php?domain=<?=$domain;?>">
						<?=gettext("InterServer.net");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://spyse.com/target/domain/<?=$domain;?>">
						<?=gettext("SpySe.com");?></a></td>
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
