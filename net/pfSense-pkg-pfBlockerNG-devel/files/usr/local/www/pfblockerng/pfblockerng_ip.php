<?php
/*
 * pfblockerng_ip.php
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

require_once('guiconfig.inc');
require_once('globals.inc');
require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');

global $config, $pfb;
pfb_global();

init_config_arr(array('installedpackages', 'pfblockerngipsettings', 'config', 0));
$pfb['iconfig'] = &$config['installedpackages']['pfblockerngipsettings']['config'][0];

$pconfig = array();
$pconfig['enable_dup']		= $pfb['iconfig']['enable_dup']				?: '';
$pconfig['enable_agg']		= $pfb['iconfig']['enable_agg']				?: '';

// Default to 'on' for new installation only
$pconfig['suppression']		= isset($pfb['iconfig']['suppression'])			? $pfb['iconfig']['suppression'] : 'on';

$pconfig['enable_log']		= $pfb['iconfig']['enable_log']				?: '';
$pconfig['ip_placeholder']	= $pfb['iconfig']['ip_placeholder']			?: '127.1.7.7';
$pconfig['maxmind_locale']	= $pfb['iconfig']['maxmind_locale']			?: 'en';
$pconfig['asn_reporting']	= $pfb['iconfig']['asn_reporting']			?: 'disabled';
$pconfig['database_cc']		= $pfb['iconfig']['database_cc']			?: '';
$pconfig['maxmind_key']		= $pfb['iconfig']['maxmind_key']			?: '';
$pconfig['inbound_interface']	= explode(',', $pfb['iconfig']['inbound_interface'])	?: array();
$pconfig['inbound_deny_action']	= $pfb['iconfig']['inbound_deny_action']		?: 'block';
$pconfig['outbound_interface']	= explode(',', $pfb['iconfig']['outbound_interface'])	?: array();
$pconfig['outbound_deny_action']= $pfb['iconfig']['outbound_deny_action']		?: 'reject';
$pconfig['enable_float']	= $pfb['iconfig']['enable_float']			?: '';
$pconfig['pass_order']		= $pfb['iconfig']['pass_order']				?: 'order_0';
$pconfig['autorule_suffix']	= $pfb['iconfig']['autorule_suffix']			?: 'autorule';
$pconfig['killstates']		= $pfb['iconfig']['killstates']				?: '';
$pconfig['v4suppression']	= base64_decode($pfb['iconfig']['v4suppression'])	?: '';

// Validate input fields and save
if ($_POST) {

	if (isset($_POST['save'])) {

		if (isset($input_errors)) {
			unset($input_errors);
		}
		if (isset($savemsg)) {
			unset($savemsg);
		}

		// Validate Placeholder IP address
		if (!is_ipaddrv4($_POST['ip_placeholder'])) {
			$input_errors[] = 'Placeholder IP: A valid IPv4 address must be specified.';
		}
		else {
			$ip_validate = where_is_ipaddr_configured($_POST['ip_placeholder'], '' , true, true, '');
			if (count($ip_validate)) {
				$input_errors[] = 'Placeholder IP: Address must be in an isolated Range that is not used in your Network.';
			}
		}

		$maxmind	= $pconfig['maxmind_locale'];
		$p_maxmind	= in_array($_POST['maxmind_locale'], array('en', 'fr', 'de', 'pt-BR', 'ja', 'zh-CN', 'es')) ? $_POST['maxmind_locale'] : 'en';

		// Apply MaxMind locale changes if required
		if ($maxmind != $p_maxmind) {

			exec('/bin/ps -wx', $result_cron);
			if (!preg_grep("/pfblockerng[.]php\s+?(uc|gc|ugc)/", $result_cron)) {

				// Execute MaxMind update and generate pfSense Notice message on completion
				mwexec_bg("/usr/local/bin/php /usr/local/www/pfblockerng/pfblockerng.php ugc {$maxmind} {$p_maxmind} >> {$pfb['extraslog']} 2>&1");

				$savemsg = "The MaxMind language locale is being changed from [ {$maxmind} to {$p_maxmind} ]. "
					. "A pfSense Notice message will be submitted on completion.";
			} else {
				$input_errors[] = 'MaxMind GeoIP conversion already in process!';
				$input_errors[] = 'Cannot change Language Locale at this time!';
			}
		}

		if (!empty($_POST['maxmind_key']) && !ctype_alnum($_POST['maxmind_key'])) {
			$input_errors[] = 'MaxMind License key Invalid';
		}

		$v4suppression = explode("\r\n", $_POST['v4suppression']);
		if (!empty($v4suppression)) {
			foreach ($v4suppression as $line) {

				if (substr($line, 0, 1) == '#' || empty($line)) {
					continue;
				}

				$host = array_map('trim', preg_split('/(?=#)/', $line));
				$mask = strstr($host[0], '/', FALSE);

				if ($mask != '/32' && $mask != '/24') {
					$input_errors[] = "IPv4 Suppression: Invalid mask [ {$host[0]} ]. Mask must be defined as /32 or /24 only.";
				}

				if (!is_subnetv4($host[0])) {
					$input_errors[] = "IPv4 Suppression: Invalid IPv4 subnet address defined [ {$host[0]} ]";
				}
			}
		}

		$pfb['iconfig']['enable_dup']		= $_POST['enable_dup']					?: '';
		$pfb['iconfig']['enable_agg']		= $_POST['enable_agg']					?: '';
		$pfb['iconfig']['suppression']		= $_POST['suppression']					?: '';
		$pfb['iconfig']['enable_log']		= $_POST['enable_log']					?: '';
		$pfb['iconfig']['ip_placeholder']	= $_POST['ip_placeholder']				?: '127.1.7.7';
		$pfb['iconfig']['maxmind_locale']	= $_POST['maxmind_locale']				?: '';
		$pfb['iconfig']['database_cc']		= $_POST['database_cc']					?: '';
		$pfb['iconfig']['maxmind_key']		= pfb_filter($_POST['maxmind_key'], 1)			?: '';
		$pfb['iconfig']['asn_reporting']	= $_POST['asn_reporting']				?: 'disabled';
		$pfb['iconfig']['inbound_interface']	= implode(',', (array)$_POST['inbound_interface'])	?: '';
		$pfb['iconfig']['inbound_deny_action']	= $_POST['inbound_deny_action']				?: '';
		$pfb['iconfig']['outbound_interface']	= implode(',', (array)$_POST['outbound_interface'])	?: '';
		$pfb['iconfig']['outbound_deny_action']	= $_POST['outbound_deny_action']			?: '';
		$pfb['iconfig']['enable_float']		= $_POST['enable_float']				?: '';
		$pfb['iconfig']['pass_order']		= $_POST['pass_order']					?: '';
		$pfb['iconfig']['autorule_suffix']	= $_POST['autorule_suffix']				?: '';
		$pfb['iconfig']['killstates']		= $_POST['killstates']					?: '';
		$pfb['iconfig']['v4suppression']	= base64_encode($_POST['v4suppression'])		?: '';

		if (!$input_errors) {
			write_config('[pfBlockerNG] save IP settings');

			if (!empty($savemsg)) {
				header("Location: /pfblockerng/pfblockerng_ip.php?savemsg={$savemsg}");
			} else {
				header('Location: /pfblockerng/pfblockerng_ip.php');
			}
		}
		else {
			$pconfig = $_POST;
		}
	}
}

$pgtitle = array(gettext('Firewall'), gettext('pfBlockerNG'), gettext('IP'));
$pglinks = array('', '/pfblockerng/pfblockerng_ip.php', '@self');
include_once('head.inc');

// Define default Alerts Tab href link (Top row)
$get_req = pfb_alerts_default_page();

$tab_array	= array();
$tab_array[]	= array(gettext('General'),	false,	'/pfblockerng/pfblockerng_general.php');
$tab_array[]	= array(gettext('IP'),		true,	'/pfblockerng/pfblockerng_ip.php');
$tab_array[]	= array(gettext('DNSBL'),	false,	'/pfblockerng/pfblockerng_dnsbl.php');
$tab_array[]	= array(gettext('Update'),	false,	'/pfblockerng/pfblockerng_update.php');
$tab_array[]	= array(gettext('Reports'),	false,	"/pfblockerng/pfblockerng_alerts.php{$get_req}");
$tab_array[]	= array(gettext('Feeds'),	false,	'/pfblockerng/pfblockerng_feeds.php');
$tab_array[]	= array(gettext('Logs'),	false,	'/pfblockerng/pfblockerng_log.php');
$tab_array[]	= array(gettext('Sync'),	false,	'/pfblockerng/pfblockerng_sync.php');
display_top_tabs($tab_array, true);

$tab_array	= array();
$tab_array[]	= array(gettext('IPv4'),	false,	'/pfblockerng/pfblockerng_category.php?type=ipv4');
$tab_array[]	= array(gettext('IPv6'),	false,	'/pfblockerng/pfblockerng_category.php?type=ipv6');
$tab_array[]	= array(gettext('GeoIP'),	false,	'/pfblockerng/pfblockerng_category.php?type=geoip');
$tab_array[]	= array(gettext('Reputation'),	false,	'/pfblockerng/pfblockerng_reputation.php');
display_top_tabs($tab_array, true);

if (isset($input_errors)) {
	print_input_errors($input_errors);
}

if (!$input_errors && isset($_REQUEST['savemsg'])) {
	$savemsg = htmlspecialchars($_REQUEST['savemsg']);
	print_info_box($savemsg);
}

$form = new Form('Save IP settings');

$section = new Form_Section('IP Configuration');
$section->addInput(new Form_StaticText(
	'Links',
	'<small>'
	. '<a href="/firewall_aliases.php" target="_blank">Firewall Aliases</a>&emsp;'
	. '<a href="/firewall_rules.php" target="_blank">Firewall Rules</a>&emsp;'
	. '<a href="/status_logs_filter.php" target="_blank">Firewall Logs</a></small>'
));

$section->addInput(new Form_Checkbox(
	'enable_dup',
	'De-Duplication',
	'Enable',
	$pconfig['enable_dup'] === 'on' ? true:false,
	'on'
))->setHelp('Only used for IPv4 Deny Lists');

$section->addInput(new Form_Checkbox(
	'enable_agg',
	'CIDR Aggregation',
	'Enable',
	$pconfig['enable_agg'] === 'on' ? true:false,
	'on'
))->setHelp('Optimise CIDRs - merge contiguous CIDRs into larger CIDR blocks.');

$section->addInput(new Form_Checkbox(
	'suppression',
	'Suppression',
	'Enable',
	$pconfig['suppression'] === 'on' ? true:false,
	'on'
))->setHelp('Default enabled. This will prevent Selected IPs (and RFC1918/Loopback addresses) from being blocked. Only for IPv4 lists (/32 and /24).'
	. '<div class="infoblock">'
	. 'GeoIP blocklist cannot be suppressed.<br /><br />'
	. 'Alerts can be suppressed using the \'+\' icon in the Alerts tab and IPs are added to the IPv4 suppression custom list.<br />'
	. 'For GeoIP/Blocked IPs in a CIDR other than /32 or /24, will need a \'Whitelist alias\' w/ a List Action: \'Permit Outbound\' Firewall rule.<br />'
	. 'Only \'Deny\' type Aliases can be suppressed!'
	. '</div>'
);

$section->addInput(new Form_Checkbox(
	'enable_log',
	'Force Global IP Logging',
	'Enable',
	$pconfig['enable_log'] === 'on' ? true:false,
	'on'
))->setHelp('The global logging option is only used to force logging for all IP Aliases, and not to disable the logging of all IP Aliases.<br />'
		. 'This overrides any logging settings in the GeoIP/IPv4/v6 tabs.'
);

$section->addInput(new Form_Input(
	'ip_placeholder',
	gettext('Placeholder IP Address'),
	'text',
	$pconfig['ip_placeholder'],
	[ 'placeholder' => '127.1.7.7' ]
))->setHelp('Enter a single IPv4 placeholder address<br />'
	. 'For IPv6 \'::\' will be prefixed to the placeholder IP.<br />'
	. 'This address should be in an Isolated Range that is not used in your Network.<br />'
	. 'This IP address will be used as a placeholder IP to avoid empty Feeds/Aliases.'
);

$section->addInput(new Form_Select(
	'asn_reporting',
	'ASN Reporting',
	$pconfig['asn_reporting'],
	[	'disabled'	=> 'Disabled',
		'week'		=> 'Enabled - ASN entries cached for 1 week',
		'24hour'	=> 'Enabled - ASN entries cached for 24 hours',
		'12hour'	=> 'Enabled - ASN entries cached for 12 hours',
		'4hour'		=> 'Enabled - ASN entries cached for 4 hours',
		'1hour'		=> 'Enabled - ASN entries cached for 1 hour' ]
))->setHelp('Query for the ASN (BGPview.io API) for each block/reject/permit/match IP entry. ASN values are cached as per the defined selection.')
  ->setAttribute('style', 'width: auto');

$form->add($section);
$section = new Form_Section('MaxMind GeoIP configuration');

$section->addInput(new Form_Input(
	'maxmind_key',
	gettext('MaxMind License Key'),
	'text',
	$pconfig['maxmind_key'],
	['placeholder' => 'Enter the MaxMind License Key']
))->setHelp('To utilize the MaxMind GeoIP functionality, you must first register for a free MaxMind user account. Visit the following '
	. '<a href="https://www.maxmind.com/en/geolite2/signup" target="_blank">Link to Register</a> for a free MaxMind user account. '
	. '<strong>Utilize the GeoIP Update version 3.1.1 or newer registration option.</strong>')
  ->setAttribute('autocomplete', 'off');

$section->addInput(new Form_Select(
	'maxmind_locale',
	'MaxMind Localized Language',
	$pconfig['maxmind_locale'],
	[	'en' => 'English', 'fr' => 'French', 'pt-BR' => 'Brazilian Portuguese', 'de' => 'German',
		'ja' => 'Japanese', 'zh-CN' => 'Simplified Chinese', 'es' => 'Spanish' ]
))->setHelp('Select the localized name data from the Language options available.<br />'
		. 'Changes to the Locale will be executed in the background, and will take a few minutes to complete.<br />'
		. 'Upon completion, a pfSense Notice will be generated.')
  ->setAttribute('style', 'width: auto');

$section->addInput(new Form_Checkbox(
	'database_cc',
	'MaxMind CSV Updates',
	'Check to disable MaxMind CSV updates',
	$pconfig['database_cc'] === 'on' ? true:false,
	'on'
))->setHelp('This will disable the MaxMind monthly CSV GeoIP database cron update. This does not affect the MaxMind binary cron update that is used for other GeoIP funcionality in the package.');

// Create page anchor for IP Suppression List
$section->addInput(new Form_StaticText(
	NULL,
	'<div id="Suppression"></div>'));

$form->add($section);

// Print Custom List TextArea section
$section = new Form_Section('IPv4 Suppression', 'IPv4_Suppression_customlist', COLLAPSIBLE|SEC_CLOSED);
$suppression_text = '<strong><u>This suppression list is for [ /32 or /24 ] IPv4 addresses only!</u></strong><br /><br />

			When \'Suppression\' is enabled, all RFC1918 and loopback addresses are also filtered on feed download|Update|Reload.<br /><br />

			Enter one &emsp; <strong>IPv4 address</strong>&emsp; per line<br />
			You may use "<strong>#</strong>" after any address to add comments. &emsp;IE: (x.x.x.x/32 # example.com)<br /><br />

			To utilize this <strong>Suppression List</strong>, enable <strong>Suppression</strong> and click on the "+"
			icon(s) in the Alerts tab to add the IPv4 addresses automatically to this Suppression list and immeditely
			remove the IPv4 address from the Deny aliastable.<br /><br />

			Note: When manually adding an IPv4 address <strong>[ /32 or /24 only! ]</strong> to this Suppression List,
			you must run a <strong>"Force Reload - IP"</strong> for the changes to take effect.';

$section->addInput(new Form_Textarea(
	'v4suppression',
	'',
	$pconfig['v4suppression']
))->removeClass('form-control')
  ->addClass('row-fluid col-sm-12')
  ->setAttribute('columns', '90')
  ->setAttribute('rows', '15')
  ->setAttribute('wrap', 'off')
  ->setAttribute('style', 'background:#fafafa; width: 100%')
  ->setHelp($suppression_text);

$form->add($section);

$section = new Form_Section('IP Interface/Rules Configuration');

$interface_list	= pfb_build_if_list(TRUE, FALSE);
$int_size	= count($interface_list) ?: '1';

$group = new Form_Group('Inbound Firewall Rules');
$group->add(new Form_Select(
	'inbound_interface',
	'Interface(s)',
	$pconfig['inbound_interface'],
	$interface_list,
	TRUE
))->setHelp('Select the Inbound interface(s) you want to apply auto rules to:')
  ->setAttribute('size', $int_size);

$group->add(new Form_Select(
	'inbound_deny_action',
	'Rule Action',
	$pconfig['inbound_deny_action'],
	[ 'block' => 'Block', 'reject' => 'Reject' ]
))->setHelp('Default: <strong>Block</strong><br />Select \'Rule action\' for Inbound rules:')
  ->setAttribute('style', 'width: auto');
$section->add($group);

$group = new Form_Group('Outbound Firewall Rules');
$group->add(new Form_Select(
	'outbound_interface',
	'Interface(s)',
	$pconfig['outbound_interface'],
	$interface_list,
	TRUE
))->setHelp('Select the Outbound interface(s) you want to apply auto rules to:')
  ->setAttribute('size', $int_size);

$group->add(new Form_Select(
	'outbound_deny_action',
	'Rule Action',
	$pconfig['outbound_deny_action'],
	[ 'reject' => 'Reject', 'block' => 'Block' ]
))->setHelp('Default: <strong>Reject</strong><br />Select \'Rule action\' for Outbound rules:')
  ->setAttribute('style', 'width: auto');
$section->add($group);

$section->addInput(new Form_Checkbox(
	'enable_float',
	'Floating Rules',
	'Enable',
	$pconfig['enable_float'] === 'on' ? true:false,
	'on'
))->setHelp('<strong>Enabled:</strong> Auto-rules will be generated in the \'Floating Rules\' tab.<br />'
		. '<strong>Disabled:</strong> Auto-rules will be generated in the selected Inbound/Outbound interfaces.'
);

$section->addInput(new Form_Select(
	'pass_order',
	'Firewall \'Auto\' Rule Order',
	$pconfig['pass_order'],
	[	'order_0' => '| pfB_Pass/Match/Block/Reject | All other Rules | (Default format)',
		'order_1' => '| pfSense Pass/Match | pfB_Pass/Match | pfB_Block/Reject | pfSense Block/Reject |',
		'order_2' => '| pfB_Pass/Match | pfSense Pass/Match | pfB_Block/Reject | pfSense Block/Reject |',
		'order_3' => '| pfB_Pass/Match | pfB_Block/Reject | pfSense Pass/Match | pfSense Block/Reject |',
		'order_4' => '| pfB_Pass/Match | pfB_Block/Reject | pfSense Block/Reject | pfSense Pass/Match |'
	]
))->setHelp('Default Order:<strong> | pfB_Block/Reject | All other Rules | (original format)</strong><br />'
		. '<span class="text-danger"><strong>Note: \'Auto type\' Firewall Rules will be \'ordered\' by this selection.</strong></span>'
		. '<div class="infoblock">'
		. 'Refer to the blue infoblock \'List Action\' icon in the IPv4 tab for details on how to use \'Alias type\'<br />'
		. '(ie: \'Alias Deny\') instead of \'Auto generated rules\', if required for your network design.<br /><br />'
		. 'Select the \'<strong>Order</strong>\' of the Rules<br /><br />'
		. '&emsp;Selecting \'original format\', sets pfBlockerNG rules at the top of the Firewall TAB.<br />'
		. '&emsp;Selecting any other \'Order\' will re-order <strong>all the rules to the format indicated!</strong></div>')
  ->setAttribute('style', 'width: auto');

$section->addInput(new Form_Select(
	'autorule_suffix',
	'Firewall \'Auto\' Rule Suffix',
	$pconfig['autorule_suffix'],
	[ 'autorule' => 'auto rule', 'standard' => 'Null (no suffix)', 'ar' => 'AR' ]
))->setHelp('Default: <strong>auto rule</strong><br />Select \'Auto Rule\' description suffix for auto defined rules. pfBlockerNG must be disabled to modify suffix.')
  ->setAttribute('style', 'width: auto');

$section->addInput(new Form_Checkbox(
	'killstates',
	'Kill States',
	'Enable',
	$pconfig['killstates'] === 'on' ? true:false,
	'on'
))->setHelp('When \'Enabled\', after a cron event or any \'Force\' commands, any blocked IPs found in the Firewall states will be cleared.');

$form->add($section);

print ($form);
print_callout('<strong>Setting changes are applied via CRON or \'Force Update|Reload\' only!</strong>');

?>
<script type="text/javascript">
//<![CDATA[

var pagetype = null;

//]]>
</script>
<script src="pfBlockerNG.js" type="text/javascript"></script>
<?php include('foot.inc');?>
