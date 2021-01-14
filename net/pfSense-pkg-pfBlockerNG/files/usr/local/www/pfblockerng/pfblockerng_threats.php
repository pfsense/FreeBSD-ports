<?php
/*
 * pfblockerng_threats.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2017 BBcan177@gmail.com
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

$pgtitle = array(gettext('Firewall'), gettext('pfBlockerNG'), gettext('Alerts'), gettext('Threat Source Lookup'));
$pglinks = array('', '/pfblockerng/pfblockerng_general.php', '/pfblockerng/pfblockerng_alerts.php', '@self');
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
					<td><font color="blue">Threat Lookups</font><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="http://www.ipvoid.com/scan/<?=$host;?>/">
						<?=gettext("IPVOID");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.tcpiputils.com/browse/ip-address/<?=$host;?>/">
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
					<td><a target="_blank" href="https://www.alexa.com/siteinfo/<?=$domain;?>">
						<?=gettext("Alexa");?></a></td>
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
					<td><a target="_blank" href="https://www.tcpiputils.com/browse/domain/<?=$domain;?>">
						<?=gettext("TCPUtils");?></a></td>
				</tr>
				<tr>
					<td><i class="fa fa-globe pull-right"></i></td>
					<td><a target="_blank" href="https://www.google.com/safebrowsing/diagnostic?site=<?=$domain;?>">
						<?=gettext("Google SafeBrowsing");?></a></td>
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
			<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<?php include('foot.inc'); ?>
