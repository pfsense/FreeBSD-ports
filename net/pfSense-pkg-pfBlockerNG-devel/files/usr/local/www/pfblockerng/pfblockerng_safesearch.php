<?php
/*
 * pfblockerng_safesearch.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2020-2021 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2021 BBcan177@gmail.com
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
	$pfb['bconfig']['safesearch_enable']	= $_POST['safesearch_enable']				?: 'Disable';
	$pfb['bconfig']['safesearch_youtube']	= $_POST['safesearch_youtube']				?: 'Disable';
	$pfb['bconfig']['safesearch_doh']	= $_POST['safesearch_doh']				?: 'Disable';
	$pfb['bconfig']['safesearch_doh_list']	= implode(',', (array)$_POST['safesearch_doh_list'])	?: '';

	$msg = 'Saved SafeSearch configuration';
	write_config("[ pfBlockerNG ] {$msg}");
	$savemsg = "{$msg}. A Force Update|Reload is required to apply changes!";
	header("Location: /pfblockerng/pfblockerng_safesearch.php?savemsg={$savemsg}");
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
	'use-application-dns.net' => 'Firefox',
	'cloudflare-dns.com' => 'CloudFlare',
	'security.cloudflare-dns.com' => 'CloudFlare Security',
	'family.cloudflare-dns.com' => 'CloudFlare Family',
	'dns.google' => 'Google',
	'doh.dns.apple.com' => 'Apple',
	'doh.opendns.com' => 'OpenDNS',
	'doh.familyshield.opendns.com' => 'OpenDNS Family',
	'dns.quad9.net' => 'Quad9',
	'dns9.quad9.net' => 'Quad9 Malware',
	'dns10.quad9.net' => 'Quad9 Unsecured',
	'dns11.quad9.net' => 'Quad9 ECS',
	'dns.adguard.com' => 'AdGuard',
	'dns-unfiltered.adguard.com' => 'AdGuard Unfiltered',
	'dns-family.adguard.com' => 'AdGuard Family',
	'doh.cleanbrowsing.org' => 'CleanBrowsing',
	'security-filter-dns.cleanbrowsing.org' => 'CleanBrowsing Security',
	'family-filter-dns.cleanbrowsing.org' => 'CleanBrowsing Family',
	'adult-filter-dns.cleanbrowsing.org' => 'CleanBrowsing Adult',
	'dns.switch.ch' => 'SWITCH',
	'dns.comss.one' => 'Comss.ru West',
	'dns.east.comss.one' => 'Comss.ru East',
	'private.canadianshield.cira.ca' => 'CIRA Private',
	'protected.canadianshield.cira.ca' => 'CIRA Protected',
	'family.canadianshield.cira.ca' => 'CIRA Family',
	'doh-fi.blahdns.com' => 'BlahDNS Finland',
	'doh-jp.blahdns.com' => 'BlahDNS Japan',
	'doh-de.blahdns.com' => 'BlahDNS Germany',
	'fi.doh.dns.snopyta.org' => 'Snopyta',
	'dns-doh.dnsforfamily.com' => 'DNS for Family',
	'odvr.nic.cz' => 'CZ.NIC ODVR',
	'dns.alidns.com' => 'Ali',
	'dns.cfiec.net' => 'CFIEC',
	'asia.dnscepat.id' => 'DNSCEPAT Asia',
	'eropa.dnscepat.id' => 'DNSCEPAT Eropa',
	'doh.360.cn' => '360 Secure',
	'public.dns.iij.jp' => 'IIJ.JP',
	'doh.pub' => 'DNSPod',
	'dns.twnic.tw' => 'Quad101',
	'doh.tiarap.org' => 'Privacy-First Singapore',
	'doh.tiar.app' => 'Privacy-First Singapore DoH',
	'dot.tiar.app' => 'Privacy-First Singapore DoT',
	'jp.tiarap.org' => 'Privacy-First Japan DoH',
	'jp.tiar.app' => 'Privacy-First Japan DoT',
	'dns.oszx.co' => 'OSZX',
	'dns.pumplex.com' => 'PumpleX',
	'doh.applied-privacy.net' => 'Applied Privacy DoH',
	'dot1.applied-privacy.net' => 'Applied Privacy DoT',
	'dns.decloudus.com' => 'DeCloudUs',
	'resolver-eu.lelux.fi' => 'Lelux',
	'doh.dns.sb' => 'DNS.SB',
	'dnsforge.de' => 'DNS Forge',
	'kaitain.restena.lu' => 'Fondation Restena',
	'doh.ffmuc.net' => 'FFMUC',
	'dns.digitale-gesellschaft.ch' => 'Digitale Gesellschaft',
	'doh.libredns.gr' => 'LibreDNS',
	'ibksturm.synology.me' => 'ibksturm',
	'getdnsapi.net' => 'DNS Privacy',
	'dnsovertls.sinodun.com' => 'DNS Privacy TLS',
	'dnsovertls1.sinodun.com' => 'DNS Privacy TLS2',
	'unicast.censurfridns.dk' => 'DNS Privacy Unicast',
	'anycast.censurfridns.dk' => 'DNS Privacy Anycast',
	'dns.cmrg.net' => 'DNS Privacy dkg',
	'dns.larsdebruin.net' => 'DNS Privacy larsdebruin',
	'dns-tls.bitwiseshift.net' => 'DNS Privacy bitwiseshift',
	'ns1.dnsprivacy.at' => 'DNS Privacy dnsprivacy',
	'ns2.dnsprivacy.at' => 'DNS Privacy dnsprivacy2',
	'dns.bitgeek.in' => 'DNS Privacy bitgeek',
	'dns.neutopia.org' => 'DNS Privacy neutopia',
	'privacydns.go6lab.si' => 'DNS Privacy Go6Lab',
	'dot.securedns.eu' => 'DNS Privacy securedns',
	'dnsotls.lab.nic.cl' => 'DNS Privacy NIC Chile',
	'tls-dns-u.odvr.dns-oarc.net' => 'DNS Privacy OARC',
	'doh.centraleu.pi-dns.com' => 'PI-DNS Central EU DoH',
	'dot.centraleu.pi-dns.com' => 'PI-DNS Central EU DoT',
	'doh.northeu.pi-dns.com' => 'PI-DNS North EU DoH',
	'dot.northeu.pi-dns.com' => 'PI-DNS North EU DoT',
	'doh.westus.pi-dns.com' => 'PI-DNS West USA DoH',
	'dot.westus.pi-dns.com' => 'PI-DNS West USA DoT',
	'doh.eastus.pi-dns.com' => 'PI-DNS East USA DoH',
	'dot.eastus.pi-dns.com' => 'PI-DNS East USA DoT',
	'doh.eastau.pi-dns.com' => 'PI-DNS East AU DoH',
	'dot.eastau.pi-dns.com' => 'PI-DNS East AU DoT',
	'doh.eastas.pi-dns.com' => 'PI-DNS East AS DoH',
	'dot.eastas.pi-dns.com' => 'PI-DNS East AS DoT',
	'doh.pi-dns.com' => 'PI-DNS',
	'dot.seby.io' => 'Seby',
	'doh-2.seby.io' => 'Seby 2',
	'doh.dnslify.com' => 'DNSlify'
	],
	TRUE
))->setHelp('Select the DoH/DoT blocking DNS Servers')
  ->setAttribute('style', 'width: auto')
  ->setAttribute('size', 88);

$form->add($section);
print($form);
print_callout('<p><strong>Setting changes are applied via CRON or \'Force Update|Reload\' only!</strong></p>');
?>

<?php include('foot.inc');?>
