<?php
/*
 * pfblockerng_safesearch.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2020-2022 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2022 BBcan177@gmail.com
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

require_once('guiconfig.inc');
require_once('globals.inc');
require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');

global $g, $config, $pfb;
pfb_global();

init_config_arr(array('installedpackages', 'pfblockerngsafesearch'));
$pfb['bconfig']	= &$config['installedpackages']['pfblockerngsafesearch'];

$pconfig = array();
$pconfig['safesearch_enable']		= $pfb['bconfig']['safesearch_enable']			?: 'Disable';
$pconfig['safesearch_youtube']		= $pfb['bconfig']['safesearch_youtube']			?: 'Disable';
$pconfig['safesearch_doh']		= $pfb['bconfig']['safesearch_doh']			?: 'Disable';
$pconfig['safesearch_doh_list']		= explode(',', $pfb['bconfig']['safesearch_doh_list'])	?: array();

if (isset($_POST['save'])) {

	if (isset($input_errors)) {
		unset($input_errors);
	}

	if (($_POST['safesearch_doh'] == 'Enable') && empty($_POST['safesearch_doh_list'])) {
		$input_errors[] = "Warning: With DoH/DoT Blocking enabled, you must select at least one List";
	}

	if (!$input_errors) {
		$pfb['bconfig']['safesearch_enable']	= $_POST['safesearch_enable']				?: 'Disable';
		$pfb['bconfig']['safesearch_youtube']	= $_POST['safesearch_youtube']				?: 'Disable';
		$pfb['bconfig']['safesearch_doh']	= $_POST['safesearch_doh']				?: 'Disable';
		$pfb['bconfig']['safesearch_doh_list']	= implode(',', (array)$_POST['safesearch_doh_list'])	?: '';

		$msg = 'Saved SafeSearch configuration';
		write_config("[ pfBlockerNG ] {$msg}");
		$savemsg = "{$msg}. A Force Update|Reload is required to apply changes!";
		header("Location: /pfblockerng/pfblockerng_safesearch.php?savemsg={$savemsg}");
	}
	else {
		print_input_errors($input_errors);
	}
}

$pgtitle = array(gettext('Firewall'), gettext('pfBlockerNG'), gettext('DNSBL'), gettext('DNSBL SafeSearch'));
$pglinks = array('', '/pfblockerng/pfblockerng_general.php', '/pfblockerng/pfblockerng_dnsbl.php', '@self');
include_once('head.inc');

// Define default Alerts Tab href link (Top row)
$get_req = pfb_alerts_default_page();

$tab_array	= array();
$tab_array[]	= array(gettext('General'),		false,	'/pfblockerng/pfblockerng_general.php');
$tab_array[]	= array(gettext('IP'),			false,	'/pfblockerng/pfblockerng_ip.php');
$tab_array[]	= array(gettext('DNSBL'),		true,	'/pfblockerng/pfblockerng_dnsbl.php');
$tab_array[]	= array(gettext('Update'),		false,	'/pfblockerng/pfblockerng_update.php');
$tab_array[]	= array(gettext('Reports'),		false,	"/pfblockerng/pfblockerng_alerts.php{$get_req}");
$tab_array[]	= array(gettext('Feeds'),		false,	'/pfblockerng/pfblockerng_feeds.php');
$tab_array[]	= array(gettext('Logs'),		false,	'/pfblockerng/pfblockerng_log.php');
$tab_array[]	= array(gettext('Sync'),		false,	'/pfblockerng/pfblockerng_sync.php');
display_top_tabs($tab_array, true);

$tab_array	= array();
$tab_array[]	= array(gettext('DNSBL Groups'),	false,	'/pfblockerng/pfblockerng_category.php?type=dnsbl');
$tab_array[]	= array(gettext('DNSBL Category'),	false,	'/pfblockerng/pfblockerng_blacklist.php');
$tab_array[]	= array(gettext('DNSBL SafeSearch'),	true,	'/pfblockerng/pfblockerng_safesearch.php');
display_top_tabs($tab_array, true);

if (isset($_REQUEST['savemsg'])) {
	$savemsg = htmlspecialchars($_REQUEST['savemsg']);
	print_info_box($savemsg);
}

// Create Form
$form = new Form('Save');

$section = new Form_Section('SafeSearch settings');
$section->addInput(new Form_StaticText(
	'Links',
	'<small>'
	. '<a href="/firewall_aliases.php" target="_blank">Firewall Aliases</a>&emsp;'
	. '<a href="/firewall_rules.php" target="_blank">Firewall Rules</a>&emsp;'
	. '<a href="/status_logs_filter.php" target="_blank">Firewall Logs</a></small>'
));

$section->addInput(new Form_StaticText(
	'NOTES:',
	'These settings will force these Search sites to utilize the "Safe Search" algorithms.<br />'
	. 'All enabled Safe Search sites will be wildcard whitelisted to ensure that DNSBL is not blocking these Safe Search Sites.'
));

$section->addInput(new Form_Select(
	'safesearch_enable',
	gettext('SafeSearch Redirection'),
	$pconfig['safesearch_enable'],
	['Disable' => 'Disable', 'Enable' => 'Enable']
))->setHelp("Select to enable SafeSearch Redirection. At the moment it is supported by Google, Yandex, DuckDuckGo, Bing and Pixabay. <br /> 
Only Google, YouTube, and Pixabay support both IPv4/IPv6 SafeSearch redirection. Other search engines support IPv4 SafeSearch only.")
  ->setAttribute('style', 'width: auto');

$section->addInput(new Form_Select(
	'safesearch_youtube',
	gettext('YouTube Restrictions'),
	$pconfig['safesearch_youtube'],
	['Disable' => 'Disable', 'Strict' => 'Strict', 'Moderate' => 'Moderate']
))->setHelp('Select YouTube Restrictions. You can check it by visiting: '
		. '<a target="_blank" href="https://www.youtube.com/check_content_restrictions">Check Youtube Content Restrictions</a>.')
  ->setAttribute('style', 'width: auto');
$form->add($section);

$section = new Form_Section('DNS over HTTPS/TLS Blocking');
$section->addInput(new Form_Select(
	'safesearch_doh',
	gettext('DoH/DoT Blocking'),
	$pconfig['safesearch_doh'],
	['Disable' => 'Disable', 'Enable' => 'Enable']
))->setHelp('Block the feature to use DNS over HTTPS/TLS to resolve DNS queries directly in the browser rather than using the native OS resolver.<br />'
		. 'DNS requests to these domains will return NXDOMAIN')
  ->setAttribute('style', 'width: auto');

$section->addInput(new Form_Select(
	'safesearch_doh_list',
	gettext('DoH/DoT Blocking List'),
	$pconfig['safesearch_doh_list'],
	[
	'use-application-dns.net' => 'Firefox [use-application-dns.net]',
	'cloudflare-dns.com' => 'CloudFlare [cloudflare-dns.com]',
	'security.cloudflare-dns.com' => 'CloudFlare Security [security.cloudflare-dns.com]',
	'family.cloudflare-dns.com' => 'CloudFlare Family [family.cloudflare-dns.com]',
	'one.one.one.one' => 'CloudFlare One [one.one.one.one]',
	'1dot1dot1dot1.cloudflare-dns.com' => 'CloudFlare 1dot [1dot1dot1dot1.cloudflare-dns.com]',
	'dns.google' => 'Google [dns.google]',
	'doh.dns.apple.com' => 'Apple [doh.dns.apple.com]',
	'mask.icloud.com' => 'Apple iCloud Private Relay [mask.icloud.com]',
	'mask-h2.icloud.com' => 'Apple iCloud Private Relay [mask-h2.icloud.com]',
	'doh.opendns.com' => 'OpenDNS [doh.opendns.com]',
	'doh.familyshield.opendns.com' => 'OpenDNS Family [doh.familyshield.opendns.com]',
	'dns.quad9.net' => 'Quad9 [dns.quad9.net]',
	'dns9.quad9.net' => 'Quad9 Malware [dns9.quad9.net]',
	'dns10.quad9.net' => 'Quad9 Unsecured [dns10.quad9.net]',
	'dns11.quad9.net' => 'Quad9 ECS [dns11.quad9.net]',
	'dns.adguard.com' => 'AdGuard [dns.adguard.com]',
	'dns-unfiltered.adguard.com' => 'AdGuard Unfiltered [dns-unfiltered.adguard.com]',
	'dns-family.adguard.com' => 'AdGuard Family [dns-family.adguard.com]',
	'doh.cleanbrowsing.org' => 'CleanBrowsing [doh.cleanbrowsing.org]',
	'security-filter-dns.cleanbrowsing.org' => 'CleanBrowsing Security [security-filter-dns.cleanbrowsing.org]',
	'family-filter-dns.cleanbrowsing.org' => 'CleanBrowsing Family [family-filter-dns.cleanbrowsing.org]',
	'adult-filter-dns.cleanbrowsing.org' => 'CleanBrowsing Adult [adult-filter-dns.cleanbrowsing.org]',
	'dns.nextdns.io' => 'NextDNS DoH/DoT [dns.nextdns.io]',
	'dns.switch.ch' => 'SWITCH [dns.switch.ch]',
	'dns.comss.one' => 'Comss.ru West [dns.comss.one]',
	'dns.east.comss.one' => 'Comss.ru East [dns.east.comss.one]',
	'private.canadianshield.cira.ca' => 'CIRA Private [private.canadianshield.cira.ca]',
	'protected.canadianshield.cira.ca' => 'CIRA Protected [protected.canadianshield.cira.ca]',
	'family.canadianshield.cira.ca' => 'CIRA Family [family.canadianshield.cira.ca]',
	'doh-fi.blahdns.com' => 'BlahDNS Finland [doh-fi.blahdns.com]',
	'doh-jp.blahdns.com' => 'BlahDNS Japan [doh-jp.blahdns.com]',
	'doh-de.blahdns.com' => 'BlahDNS Germany [doh-de.blahdns.com]',
	'fi.doh.dns.snopyta.org' => 'Snopyta [fi.doh.dns.snopyta.org]',
	'dns-doh.dnsforfamily.com' => 'DNS for Family [dns-doh.dnsforfamily.com]',
	'odvr.nic.cz' => 'CZ.NIC ODVR [odvr.nic.cz]',
	'dns.alidns.com' => 'Ali [dns.alidns.com]',
	'dns.cfiec.net' => 'CFIEC [dns.cfiec.net]',
	'asia.dnscepat.id' => 'DNSCEPAT Asia [asia.dnscepat.id]',
	'eropa.dnscepat.id' => 'DNSCEPAT Eropa [eropa.dnscepat.id]',
	'doh.360.cn' => '360 Secure [doh.360.cn]',
	'public.dns.iij.jp' => 'IIJ.JP [public.dns.iij.jp]',
	'doh.pub' => 'DNSPod [doh.pub]',
	'dns.twnic.tw' => 'Quad101 [dns.twnic.tw]',
	'doh.tiarap.org' => 'Privacy-First Singapore [doh.tiarap.org]',
	'doh.tiar.app' => 'Privacy-First Singapore DoH [doh.tiar.app]',
	'dot.tiar.app' => 'Privacy-First Singapore DoT [dot.tiar.app]',
	'jp.tiarap.org' => 'Privacy-First Japan DoH [jp.tiarap.org]',
	'jp.tiar.app' => 'Privacy-First Japan DoT [jp.tiar.app]',
	'dns.oszx.co' => 'OSZX [dns.oszx.co]',
	'dns.pumplex.com' => 'Pumplex [dns.pumplex.com]',
	'doh.applied-privacy.net' => 'Applied Privacy DoH [doh.applied-privacy.net]',
	'dot1.applied-privacy.net' => 'Applied Privacy DoT [dot1.applied-privacy.net]',
	'dns.decloudus.com' => 'DeCloudUs [dns.decloudus.com]',
	'resolver-eu.lelux.fi' => 'Lelux [resolver-eu.lelux.fi]',
	'doh.dns.sb' => 'DNS.SB [doh.dns.sb]',
	'dnsforge.de' => 'DNS Forge [dnsforge.de]',
	'kaitain.restena.lu' => 'Fondation Restena [kaitain.restena.lu]',
	'doh.ffmuc.net' => 'FFMUC [doh.ffmuc.net]',
	'dns.digitale-gesellschaft.ch' => 'Digitale Gesellschaft [dns.digitale-gesellschaft.ch]',
	'doh.libredns.gr' => 'LibreDNS [doh.libredns.gr]',
	'ibksturm.synology.me' => 'ibksturm [ibksturm.synology.me]',
	'getdnsapi.net' => 'DNS Privacy [getdnsapi.net]',
	'dnsovertls.sinodun.com' => 'DNS Privacy TLS [dnsovertls.sinodun.com]',
	'dnsovertls1.sinodun.com' => 'DNS Privacy TLS2 [dnsovertls1.sinodun.com]',
	'unicast.censurfridns.dk' => 'DNS Privacy Unicast [unicast.censurfridns.dk]',
	'anycast.censurfridns.dk' => 'DNS Privacy Anycast [anycast.censurfridns.dk]',
	'dns.cmrg.net' => 'DNS Privacy dkg [dns.cmrg.net]',
	'dns.larsdebruin.net' => 'DNS Privacy larsdebruin [dns.larsdebruin.net]',
	'dns-tls.bitwiseshift.net' => 'DNS Privacy bitwiseshift [dns-tls.bitwiseshift.net]',
	'ns1.dnsprivacy.at' => 'DNS Privacy dnsprivacy [ns1.dnsprivacy.at]',
	'ns2.dnsprivacy.at' => 'DNS Privacy dnsprivacy2 [ns2.dnsprivacy.at]',
	'dns.bitgeek.in' => 'DNS Privacy bitgeek [dns.bitgeek.in]',
	'dns.neutopia.org' => 'DNS Privacy neutopia [dns.neutopia.org]',
	'privacydns.go6lab.si' => 'DNS Privacy Go6Lab [privacydns.go6lab.si]',
	'dot.securedns.eu' => 'DNS Privacy securedns [dot.securedns.eu]',
	'dnsotls.lab.nic.cl' => 'DNS Privacy NIC Chile [dnsotls.lab.nic.cl]',
	'tls-dns-u.odvr.dns-oarc.net' => 'DNS Privacy OARC [tls-dns-u.odvr.dns-oarc.net]',
	'doh.centraleu.pi-dns.com' => 'PI-DNS Central EU DoH [doh.centraleu.pi-dns.com]',
	'dot.centraleu.pi-dns.com' => 'PI-DNS Central EU DoT [dot.centraleu.pi-dns.com]',
	'doh.northeu.pi-dns.com' => 'PI-DNS North EU DoH [doh.northeu.pi-dns.com]',
	'dot.northeu.pi-dns.com' => 'PI-DNS North EU DoT [dot.northeu.pi-dns.com]',
	'doh.westus.pi-dns.com' => 'PI-DNS West USA DoH [doh.westus.pi-dns.com]',
	'dot.westus.pi-dns.com' => 'PI-DNS West USA DoT [dot.westus.pi-dns.com]',
	'doh.eastus.pi-dns.com' => 'PI-DNS East USA DoH [doh.eastus.pi-dns.com]',
	'dot.eastus.pi-dns.com' => 'PI-DNS East USA DoT [dot.eastus.pi-dns.com]',
	'doh.eastau.pi-dns.com' => 'PI-DNS East AU DoH [doh.eastau.pi-dns.com]',
	'dot.eastau.pi-dns.com' => 'PI-DNS East AU DoT [dot.eastau.pi-dns.com]',
	'doh.eastas.pi-dns.com' => 'PI-DNS East AS DoH [doh.eastas.pi-dns.com]',
	'dot.eastas.pi-dns.com' => 'PI-DNS East AS DoT [dot.eastas.pi-dns.com]',
	'doh.pi-dns.com' => 'PI-DNS [doh.pi-dns.com]',
	'dot.seby.io' => 'Seby [dot.seby.io]',
	'doh-2.seby.io' => 'Seby 2 [doh-2.seby.io]',
	'doh.dnslify.com' => 'DNSlify [doh.dnslify.com]'
	],
	TRUE
))->setHelp('Select the DoH/DoT blocking DNS Servers')
  ->setAttribute('style', 'width: auto')
  ->setAttribute('size', 95);

$form->add($section);
print($form);
print_callout('<p><strong>Setting changes are applied via CRON or \'Force Update|Reload\' only!</strong></p>');
?>

<?php include('foot.inc');?>
