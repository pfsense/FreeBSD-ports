<?php
/*
 * pfblockerng_safesearch.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2020 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2020 BBcan177@gmail.com
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
$pconfig['safesearch_enable']		= $pfb['bconfig']['safesearch_enable']		?: 'Disable';
$pconfig['safesearch_youtube']		= $pfb['bconfig']['safesearch_youtube']		?: 'Disable';
$pconfig['safesearch_firefoxdoh']	= $pfb['bconfig']['safesearch_firefoxdoh']	?: 'Disable';

if (isset($_POST['save'])) {
	$pfb['bconfig']['safesearch_enable']	= $_POST['safesearch_enable']		?: 'Disable';
	$pfb['bconfig']['safesearch_youtube']	= $_POST['safesearch_youtube']		?: 'Disable';
	$pfb['bconfig']['safesearch_firefoxdoh']= $_POST['safesearch_firefoxdoh']	?: 'Disable';

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
))->setHelp('Select to enable SafeSearch Redirection. At the moment it is supported by Google, Yandex, DuckDuckGo, Bing and Pixabay.')
  ->setAttribute('style', 'width: auto');

$section->addInput(new Form_Select(
	'safesearch_youtube',
	gettext('YouTube Restrictions'),
	$pconfig['safesearch_youtube'],
	['Disable' => 'Disable', 'Strict' => 'Strict', 'Moderate' => 'Moderate']
))->setHelp('Select YouTube Restrictions. You can check it by visiting: '
		. '<a target="_blank" href="https://www.youtube.com/check_content_restrictions">Check Youtube Content Restrictions</a>.')
  ->setAttribute('style', 'width: auto');

$section->addInput(new Form_Select(
	'safesearch_firefoxdoh',
	gettext('Firefox DoH blocking'),
	$pconfig['safesearch_firefoxdoh'],
	['Disable' => 'Disable', 'Enable' => 'Enable']
))->setHelp('Block Firefox feature to use DNS over HTTPS to resolve DNS queries directly in the browser rather than using the native OS resolver.')
  ->setAttribute('style', 'width: auto');

$form->add($section);
print($form);
print_callout('<p><strong>Setting changes are applied via CRON or \'Force Update|Reload\' only!</strong></p>');
?>

<?php include('foot.inc');?>
