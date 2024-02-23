<?php
/*
 * pfblockerng_safesearch.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2020-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2023 BBcan177@gmail.com
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

// Select field options
$options_safesearch_enable	= ['Disable' => 'Disable', 'Enable' => 'Enable'];
$options_safesearch_youtube	= ['Disable' => 'Disable', 'Strict' => 'Strict', 'Moderate' => 'Moderate'];
$options_safesearch_doh		= ['Disable' => 'Disable', 'Enable' => 'Enable'];
$options_safesearch_doh_list	= [
					'use-application-dns.net' => 'Firefox [use-application-dns.net]',
					'cloudflare-dns.com' => 'CloudFlare DoH/DoT [cloudflare-dns.com]',
					'security.cloudflare-dns.com' => 'CloudFlare Security DoH/DoT [security.cloudflare-dns.com]',
					'family.cloudflare-dns.com' => 'CloudFlare Family DoH/DoT [family.cloudflare-dns.com]',
					'one.one.one.one' => 'CloudFlare One [one.one.one.one]',
					'1dot1dot1dot1.cloudflare-dns.com' => 'CloudFlare 1dot DoT [1dot1dot1dot1.cloudflare-dns.com]',
					'dns.google' => 'Google DoH/DoT [dns.google]',
					'doh.dns.apple.com' => 'Apple [doh.dns.apple.com]',
					'mask.icloud.com' => 'Apple iCloud Private Relay [mask.icloud.com]',
					'mask-h2.icloud.com' => 'Apple iCloud Private Relay [mask-h2.icloud.com]',
					'mask-api.icloud.com' => 'Apple iCloud Private Relay [mask-api.icloud.com]',
					'mask-t.apple-dns.net' => 'Apple iCloud Private Relay [mask-t.apple-dns.net]',
					'mask.apple-dns.net' => 'Apple iCloud Private Relay [mask.apple-dns.net]',
					'mask-api.fe.apple-dns.net' => 'Apple iCloud Private Relay [mask-api.fe.apple-dns.net]',
					'doh.opendns.com' => 'OpenDNS DoH [doh.opendns.com]',
					'doh.familyshield.opendns.com' => 'OpenDNS Family DoH [doh.familyshield.opendns.com]',
					'dns.quad9.net' => 'Quad9 DoH/DoT [dns.quad9.net]',
					'dns10.quad9.net' => 'Quad9 Unsecured DoH/DoT [dns10.quad9.net]',
					'dns11.quad9.net' => 'Quad9 ECS DoH/DoT [dns11.quad9.net]',
					'dns.adguard-dns.com' => 'AdGuard DoH/DoT/DoQ [dns.adguard-dns.com]',
					'unfiltered.adguard-dns.com' => 'AdGuard Unfiltered DoH/DoT/DoQ [unfiltered.adguard-dns.com]',
					'family.adguard-dns.com' => 'AdGuard Family DoH/DoT/DoQ [family.adguard-dns.com]',
					'doh.cleanbrowsing.org' => 'CleanBrowsing [doh.cleanbrowsing.org]',
					'security-filter-dns.cleanbrowsing.org' => 'CleanBrowsing Security [security-filter-dns.cleanbrowsing.org]',
					'family-filter-dns.cleanbrowsing.org' => 'CleanBrowsing Family [family-filter-dns.cleanbrowsing.org]',
					'adult-filter-dns.cleanbrowsing.org' => 'CleanBrowsing Adult [adult-filter-dns.cleanbrowsing.org]',
					'dns.nextdns.io' => 'NextDNS DoH/DoT/DoQ [dns.nextdns.io]',
					'dns.switch.ch' => 'SWITCH DoH/DoT [dns.switch.ch]',
					'dns.futuredns.me' => 'FutureDNS DoH/DoT/DoQ [dns.futuredns.me]',
					'dns.comss.one' => 'Comss.ru West DoH/DoT [dns.comss.one]',
					'dns.east.comss.one' => 'Comss.ru East [dns.east.comss.one]',
					'private.canadianshield.cira.ca' => 'CIRA Private [private.canadianshield.cira.ca]',
					'protected.canadianshield.cira.ca' => 'CIRA Protected [protected.canadianshield.cira.ca]',
					'family.canadianshield.cira.ca' => 'CIRA Family [family.canadianshield.cira.ca]',
					'doh-fi.blahdns.com' => 'BlahDNS DoH Finland [doh-fi.blahdns.com]',
					'doh-jp.blahdns.com' => 'BlahDNS DoH Japan [doh-jp.blahdns.com]',
					'doh-de.blahdns.com' => 'BlahDNS DoH Germany [doh-de.blahdns.com]',
					'dot-fi.blahdns.com' => 'BlahDNS DoT Finland [dot-fi.blahdns.com]',
					'dot-jp.blahdns.com' => 'BlahDNS DoT Japan [dot-fi.blahdns.com]',
					'dot-de.blahdns.com' => 'BlahDNS DoT Germany [dot-de.blahdns.com]',
					'fi.doh.dns.snopyta.org' => 'Snopyta [fi.doh.dns.snopyta.org]',
					'dns-doh.dnsforfamily.com' => 'DNS for Family DoH [dns-doh.dnsforfamily.com]',
					'dns-dot.dnsforfamily.com' => 'DNS for Family DoT [dns-dot.dnsforfamily.com]',
					'odvr.nic.cz' => 'CZ.NIC ODVR [odvr.nic.cz]',
					'dns.alidns.com' => 'Ali [dns.alidns.com]',
					'dns.cfiec.net' => 'CFIEC [dns.cfiec.net]',
					'asia.dnscepat.id' => 'DNSCEPAT Asia [asia.dnscepat.id]',
					'eropa.dnscepat.id' => 'DNSCEPAT Eropa [eropa.dnscepat.id]',
					'doh.360.cn' => '360 Secure [doh.360.cn]',
					'public.dns.iij.jp' => 'IIJ.JP [public.dns.iij.jp]',
					'dns.pub' => 'DNSPod [dns.pub]',
					'doh.pub' => 'DNSPod DoH [doh.pub]',
					'dot.pub' => 'DNSPod DoT [dot.pub]',
					'dns.twnic.tw' => 'Quad101 [dns.twnic.tw]',
					'doh.tiarap.org' => 'Privacy-First Singapore [doh.tiarap.org]',
					'doh.tiar.app' => 'Privacy-First Singapore DoH/DoQ [doh.tiar.app]',
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
					'doh.ffmuc.net' => 'FFMUC DoH [doh.ffmuc.net]',
					'dot.ffmuc.net' => 'FFMUC DoT [dot.ffmuc.net]',
					'dns.digitale-gesellschaft.ch' => 'Digitale Gesellschaft [dns.digitale-gesellschaft.ch]',
					'doh.libredns.gr' => 'LibreDNS DoH [doh.libredns.gr]',
					'dot.libredns.gr' => 'LibreDNS DoT [dot.libredns.gr]',
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
					'doh.dnslify.com' => 'DNSlify [doh.dnslify.com]',
					'yandex.dns' => 'Yandex [yandex.dns]',
					'blitz.ahadns.com' => 'AhaDNS Blitz DoH [blitz.ahadns.com]',
					'doh.nl.ahadns.net' => 'AhaDNS Blitz Netherlands DoH [doh.nl.ahadns.net]',
					'dot.nl.ahadns.net' => 'AhaDNS Blitz Netherlands DoT [dot.nl.ahadns.net]',
					'doh.in.ahadns.net' => 'AhaDNS Blitz India DoH [doh.in.ahadns.net]',
					'dot.in.ahadns.net' => 'AhaDNS Blitz India DoT [dot.in.ahadns.net]',
					'doh.la.ahadns.net' => 'AhaDNS Blitz Las Angeles DoH [doh.la.ahadns.net]',
					'dot.la.ahadns.net' => 'AhaDNS Blitz Las Angeles DoT [dot.la.ahadns.net]',
					'doh.ny.ahadns.net' => 'AhaDNS Blitz New York DoH [doh.ny.ahadns.net]',
					'dot.ny.ahadns.net' => 'AhaDNS Blitz New York DoT [dot.ny.ahadns.net]',
					'doh.pl.ahadns.net' => 'AhaDNS Blitz Poland DoH [doh.pl.ahadns.net]',
					'dot.pl.ahadns.net' => 'AhaDNS Blitz Poland DoT [dot.pl.ahadns.net]',
					'doh.it.ahadns.net' => 'AhaDNS Blitz Italy DoH [doh.it.ahadns.net]',
					'dot.it.ahadns.net' => 'AhaDNS Blitz Italy DoT [dot.it.ahadns.net]',
					'doh.es.ahadns.net' => 'AhaDNS Blitz Spain DoH [doh.es.ahadns.net]',
					'dot.es.ahadns.net' => 'AhaDNS Blitz Spain DoT [dot.es.ahadns.net]',
					'doh.no.ahadns.net' => 'AhaDNS Blitz Norway DoH [doh.no.ahadns.net]',
					'dot.no.ahadns.net' => 'AhaDNS Blitz Norway DoT [dot.no.ahadns.net]',
					'doh.chi.ahadns.net' => 'AhaDNS Blitz Chicago DoH [doh.chi.ahadns.net]',
					'dot.chi.ahadns.net' => 'AhaDNS Blitz Chicago DoT [dot.chi.ahadns.net]',
					'doh.au.ahadns.net' => 'AhaDNS Blitz Australia DoH [doh.au.ahadns.net]',
					'dot.au.ahadns.net' => 'AhaDNS Blitz Australia DoT [dot.au.ahadns.net]',
					'basic.rethinkdns.com' => 'RethinkDNS DoH [basic.rethinkdns.com]',
					'max.rethinkdns.com' => 'RethinkDNS DoT [max.rethinkdns.com]',
					'freedns.controld.com' => 'ControlD DoH [freedns.controld.com]',
					'p0.freedns.controld.com' => 'ControlD DoT [p0.freedns.controld.com]',
					'p1.freedns.controld.com' => 'ControlD DoT [p1.freedns.controld.com]',
					'p2.freedns.controld.com' => 'ControlD DoT [p2.freedns.controld.com]',
					'p3.freedns.controld.com' => 'ControlD DoT [p3.freedns.controld.com]',
					'doh.mullvad.net' => 'Mullvad [doh.mullvad.net]',
					'adblock.doh.mullvad.net' => 'Mullvad [adblock.doh.mullvad.net]',
					'dns.arapurayil.com' => 'Arapurayil [dns.arapurayil.com]',
					'dandelionsprout.asuscomm.com' => 'Dandelion Sprout DoH/DoT/DoQ [dandelionsprout.asuscomm.com]',
					'zero.dns0.eu' => 'European public DNS DoH/DoT/DoQ [zero.dns0.eu]'
					];

if (isset($_POST['save'])) {

	if (isset($input_errors)) {
		unset($input_errors);
	}

	if (($_POST['safesearch_doh'] == 'Enable') && empty($_POST['safesearch_doh_list'])) {
		$input_errors[] = 'Warning: With DoH/DoT Blocking enabled, you must select at least one List';
	}

	// Validate Select field (array) options
	$select_options = array(	'safesearch_enable'	=> '',
					'safesearch_youtube'	=> '',
					'safesearch_doh'	=> ''
					);

	foreach ($select_options as $s_option => $s_default) {
		if (is_array($_POST[$s_option])) {
			foreach ($_POST[$s_option] as $post_option) {
				if (!array_key_exists($post_option, ${"options_$s_option"})) {
					$_POST[$s_option] = $s_default;
					break;
			}
			}
		}
		elseif (!array_key_exists($_POST[$s_option], ${"options_$s_option"})) {
			$_POST[$s_option] = $s_default;
		}
	}

	// Validate SafeSearch selections
	if (is_array($_POST['safesearch_doh_list'])) {
		foreach ($_POST['safesearch_doh_list'] as $validate) {
			if (!array_key_exists($validate, $options_safesearch_doh_list)) {
				$_POST['safesearch_doh_list'] = '';
				break;
			}
		}
	}
	elseif (!array_key_exists($_POST['safesearch_doh_list'], $options_safesearch_doh_list)) {
		$_POST['safesearch_doh_list'] = '';
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
}

$pgtitle = array(gettext('Firewall'), gettext('pfBlockerNG'), gettext('DNSBL'), gettext('DNSBL SafeSearch'));
$pglinks = array('', '/pfblockerng/pfblockerng_general.php', '/pfblockerng/pfblockerng_dnsbl.php', '@self');
include_once('head.inc');

if ($input_errors) {
	print_input_errors($input_errors);
}

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
	$options_safesearch_enable
))->setHelp("Select to enable SafeSearch Redirection. At the moment it is supported by Google, Yandex, DuckDuckGo, Bing and Pixabay. <br /> 
Only Google, YouTube, and Pixabay support both IPv4/IPv6 SafeSearch redirection. Other search engines support IPv4 SafeSearch only.")
  ->setAttribute('style', 'width: auto');

$section->addInput(new Form_Select(
	'safesearch_youtube',
	gettext('YouTube Restrictions'),
	$pconfig['safesearch_youtube'],
	$options_safesearch_youtube
))->setHelp('Select YouTube Restrictions. You can check it by visiting: '
		. '<a target="_blank" href="https://www.youtube.com/check_content_restrictions">Check Youtube Content Restrictions</a>.')
  ->setAttribute('style', 'width: auto');
$form->add($section);

$section = new Form_Section('DNS over HTTPS/TLS/QUIC Blocking');
$section->addInput(new Form_Select(
	'safesearch_doh',
	gettext('DoH/DoT/DoQ Blocking'),
	$pconfig['safesearch_doh'],
	$options_safesearch_doh
))->setHelp('Block the feature to use DNS over HTTPS/TLS/QUIC to resolve DNS queries directly in the browser rather than using the native OS resolver.<br />'
		. 'DNS requests to these domains will return NXDOMAIN')
  ->setAttribute('style', 'width: auto');

$section->addInput(new Form_Select(
	'safesearch_doh_list',
	gettext('DoH/DoT/DoQ Blocking List'),
	$pconfig['safesearch_doh_list'],
	$options_safesearch_doh_list,
	TRUE
))->setHelp('Select the DoH/DoT/DoQ blocking DNS Servers')
  ->setAttribute('style', 'width: auto')
  ->setAttribute('size', 140);

$form->add($section);
print($form);
print_callout('<p><strong>Setting changes are applied via CRON or \'Force Update|Reload\' only!</strong></p>');
?>

<?php include('foot.inc');?>
