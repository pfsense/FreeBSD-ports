<?php
/*
 * pfblockerng_dnsbl.php
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

require_once('guiconfig.inc');
require_once('globals.inc');
require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');

global $config, $pfb;
pfb_global();
$disable_move = FALSE;

$pfb['dconfig'] = &$config['installedpackages']['pfblockerngdnsblsettings']['config'][0];
if (!is_array($pfb['dconfig'])) {
	$pfb['dconfig'] = array();
}
$pconfig = array();
$pconfig['pfb_dnsbl']		= $pfb['dconfig']['pfb_dnsbl']				?: '';
$pconfig['pfb_tld']		= $pfb['dconfig']['pfb_tld']				?: '';
$pconfig['pfb_dnsvip']		= $pfb['dconfig']['pfb_dnsvip']				?: '10.10.10.1';
$pconfig['pfb_dnsvip_type']	= $pfb['dconfig']['pfb_dnsvip_type']			?: 'ipalias';
$pconfig['pfb_dnsvip_pass']	= $pfb['dconfig']['pfb_dnsvip_pass']			?: '';
$pconfig['pfb_dnsport']		= $pfb['dconfig']['pfb_dnsport']			?: '8081';
$pconfig['pfb_dnsport_ssl']	= $pfb['dconfig']['pfb_dnsport_ssl']			?: '8443';
$pconfig['dnsbl_interface']	= $pfb['dconfig']['dnsbl_interface']			?: 'lan';
$pconfig['pfb_dnsbl_rule']	= $pfb['dconfig']['pfb_dnsbl_rule']			?: '';
$pconfig['dnsbl_allow_int']	= explode(',', $pfb['dconfig']['dnsbl_allow_int'])	?: array();
$pconfig['dnsbl_webpage']	= $pfb['dconfig']['dnsbl_webpage']			?: 'dnsbl_default.php';
$pconfig['pfb_dnsbl_sync']	= $pfb['dconfig']['pfb_dnsbl_sync']			?: '';
$pconfig['action']		= $pfb['dconfig']['action']				?: 'Disabled';
$pconfig['aliaslog']		= $pfb['dconfig']['aliaslog']				?: 'enabled';

$pconfig['autoaddrnot_in']	= $pfb['dconfig']['autoaddrnot_in']			?: '';
$pconfig['autoports_in']	= $pfb['dconfig']['autoports_in']			?: '';
$pconfig['aliasports_in']	= $pfb['dconfig']['aliasports_in']			?: '';
$pconfig['autoaddr_in']		= $pfb['dconfig']['autoaddr_in']			?: '';
$pconfig['autonot_in']		= $pfb['dconfig']['autonot_in']				?: '';
$pconfig['aliasaddr_in']	= $pfb['dconfig']['aliasaddr_in']			?: '';
$pconfig['autoproto_in']	= $pfb['dconfig']['autoproto_in']			?: '';
$pconfig['agateway_in']		= $pfb['dconfig']['agateway_in']			?: 'default';

$pconfig['autoaddrnot_out']	= $pfb['dconfig']['autoaddrnot_out']			?: '';
$pconfig['autoports_out']	= $pfb['dconfig']['autoports_out']			?: '';
$pconfig['aliasports_out']	= $pfb['dconfig']['aliasports_out']			?: '';
$pconfig['autoaddr_out']	= $pfb['dconfig']['autoaddr_out']			?: '';
$pconfig['autonot_out']		= $pfb['dconfig']['autonot_out']			?: '';
$pconfig['aliasaddr_out']	= $pfb['dconfig']['aliasaddr_out']			?: '';
$pconfig['autoproto_out']	= $pfb['dconfig']['autoproto_out']			?: '';
$pconfig['agateway_out']	= $pfb['dconfig']['agateway_out']			?: 'default';

$pconfig['suppression']		= base64_decode($pfb['dconfig']['suppression'])		?: '';

$pconfig['alexa_enable']	= $pfb['dconfig']['alexa_enable']			?: '';
$pconfig['alexa_type']		= $pfb['dconfig']['alexa_type']				?: 'Alexa';
$pconfig['alexa_count']		= $pfb['dconfig']['alexa_count']			?: '1000';
$pconfig['alexa_inclusion']	= explode(',', $pfb['dconfig']['alexa_inclusion'])	?: array('com','net','org','ca','co','io');

$pconfig['tldexclusion']	= base64_decode($pfb['dconfig']['tldexclusion'])	?: '';
$pconfig['tldblacklist']	= base64_decode($pfb['dconfig']['tldblacklist'])	?: '';
$pconfig['tldwhitelist']	= base64_decode($pfb['dconfig']['tldwhitelist'])	?: '';

// Validate input fields and save
if ($_POST) {

	if (isset($_POST['save'])) {

		unset($input_errors);

		// Check if DNSBL Webpage has been changed.
		$dnsbl_webpage = FALSE;
		if ($_POST['dnsbl_webpage'] != $pfb['dconfig']['dnsbl_webpage']) {
			$dnsbl_webpage = TRUE;
		}

		// Reset TOP1M Database/Whitelist on user changes
		if ($pfb['dconfig']['alexa_type'] != $_POST['alexa_type']) {
			unlink_if_exists("{$pfb['dbdir']}/top-1m.csv");
			unlink_if_exists("{$pfb['dbdir']}/pfbalexawhitelist.txt");
		}

		// Reset TOP1M Whitelist on user changes
		if ($pfb['dconfig']['alexa_count'] != $_POST['alexa_count'] ||
		    $pfb['dconfig']['alexa_inclusion'] != $_POST['alexa_inclusion']) {
			unlink_if_exists("{$pfb['dbdir']}/pfbalexawhitelist.txt");
		}

		$pfb['dconfig']['pfb_dnsbl']		= $_POST['pfb_dnsbl']					?: '';
		$pfb['dconfig']['pfb_tld']		= $_POST['pfb_tld']					?: '';
		$pfb['dconfig']['pfb_dnsvip']		= $_POST['pfb_dnsvip']					?: '10.10.10.1';
		$pfb['dconfig']['pfb_dnsvip_type']	= $_POST['pfb_dnsvip_type']				?: 'ipalias';
		$pfb['dconfig']['pfb_dnsvip_pass']	= $_POST['pfb_dnsvip_pass']				?: '';
		$pfb['dconfig']['pfb_dnsport']		= $_POST['pfb_dnsport']					?: '8081';
		$pfb['dconfig']['pfb_dnsport_ssl']	= $_POST['pfb_dnsport_ssl']				?: '8443';
		$pfb['dconfig']['dnsbl_interface']	= $_POST['dnsbl_interface']				?: 'lan';
		$pfb['dconfig']['pfb_dnsbl_rule']	= $_POST['pfb_dnsbl_rule']				?: '';
		$pfb['dconfig']['dnsbl_allow_int']	= implode(',', (array)$_POST['dnsbl_allow_int'])	?: '';
		$pfb['dconfig']['dnsbl_webpage']	= $_POST['dnsbl_webpage']				?: 'dnsbl_default.php';
		$pfb['dconfig']['pfb_dnsbl_sync']	= $_POST['pfb_dnsbl_sync']				?: '';
		$pfb['dconfig']['action']		= $_POST['action']					?: 'Disabled';
		$pfb['dconfig']['aliaslog']		= $_POST['aliaslog']					?: 'enabled';

		$pfb['dconfig']['autoaddrnot_in']	= $_POST['autoaddrnot_in']				?: '';
		$pfb['dconfig']['autoports_in']		= $_POST['autoports_in']				?: '';
		$pfb['dconfig']['aliasports_in']	= htmlspecialchars($_POST['aliasports_in'])		?: '';
		$pfb['dconfig']['autoaddr_in']		= $_POST['autoaddr_in']					?: '';
		$pfb['dconfig']['autonot_in']		= $_POST['autonot_in']					?: '';
		$pfb['dconfig']['aliasaddr_in']		= htmlspecialchars($_POST['aliasaddr_in'])		?: '';
		$pfb['dconfig']['autoproto_in']		= $_POST['autoproto_in']				?: '';
		$pfb['dconfig']['agateway_in']		= $_POST['agateway_in']					?: 'default';

		$pfb['dconfig']['autoaddrnot_out']	= $_POST['autoaddrnot_out']				?: '';
		$pfb['dconfig']['autoports_out']	= $_POST['autoports_out']				?: '';
		$pfb['dconfig']['aliasports_out']	= htmlspecialchars($_POST['aliasports_out'])		?: '';
		$pfb['dconfig']['autoaddr_out']		= $_POST['autoaddr_out']				?: '';
		$pfb['dconfig']['autonot_out']		= $_POST['autonot_out']					?: '';
		$pfb['dconfig']['aliasaddr_out']	= htmlspecialchars($_POST['aliasaddr_out'])		?: '';
		$pfb['dconfig']['autoproto_out']	= $_POST['autoproto_out']				?: '';
		$pfb['dconfig']['agateway_out']		= $_POST['agateway_out']				?: 'default';

		$pfb['dconfig']['suppression']		= base64_encode($_POST['suppression'])			?: '';

		$pfb['dconfig']['alexa_enable']		= $_POST['alexa_enable']				?: '';
		$pfb['dconfig']['alexa_type']		= $_POST['alexa_type']					?: 'Alexa';
		$pfb['dconfig']['alexa_count']		= $_POST['alexa_count']					?: '1000';
		$pfb['dconfig']['alexa_inclusion']	= implode(',', (array)$_POST['alexa_inclusion'])	?: 'com,net,org,ca,co,io';

		$pfb['dconfig']['tldexclusion']		= base64_encode($_POST['tldexclusion'])			?: '';
		$pfb['dconfig']['tldblacklist']		= base64_encode($_POST['tldblacklist'])			?: '';
		$pfb['dconfig']['tldwhitelist']		= base64_encode($_POST['tldwhitelist'])			?: '';

		// Validate DNSBL VIP address
		if (!is_ipaddrv4($_POST['pfb_dnsvip'])) {
			$input_errors[] = 'DNSBL Virtual IP: A valid IPv4 address must be specified.';
		}
		else {
			$ip_validate = where_is_ipaddr_configured($_POST['pfb_dnsvip'], '' , true, true, '');
			if (count($ip_validate)) {
				$input_errors[] = 'DNSBL Virtual IP: Address must be in an isolated Range that is not used in your Network.';
			}
		}

		// Validate Adv. firewall rule 'Protocol' setting
		if (!empty($_POST['autoports_in']) || !empty($_POST['autoaddr_in'])) {
			if (empty($_POST['autoproto_in'])) {
				$input_errors[] = "Settings: Protocol setting cannot be set to 'Default' with Advanced Inbound firewall rule settings.";
			}
		}
		if (!empty($_POST['autoports_out']) || !empty($_POST['autoaddr_out'])) {
			if (empty($_POST['autoproto_out'])) {
				$input_errors[] = "Settings: Protocol setting cannot be set to 'Default' with Advanced Outbound firewall rule settings.";
			}
		}

		if (!$input_errors) {

			// Replace DNSBL active blocked webpage with user selection
			if ($dnsbl_webpage || !file_exists('/usr/local/www/pfblockerng/www/dnsbl_active.php')) {
				@copy("/usr/local/www/pfblockerng/www/{$pfb['dconfig']['dnsbl_webpage']}", '/usr/local/www/pfblockerng/www/dnsbl_active.php');
			}

			write_config('[pfBlockerNG] save DNSBL settings');
			header('Location: /pfblockerng/pfblockerng_dnsbl.php');
		}
		else {
			// Restore $_POST data on input errors
			$pconfig = $_POST;
		}
	}
}

$pgtitle = array(gettext('Firewall'), gettext('pfBlockerNG'), gettext('DNSBL'));
$pglinks = array('', '/pfblockerng/pfblockerng_dnsbl.php', '@self');
include_once('head.inc');

// Define default Alerts Tab href link (Top row)
$get_req = pfb_alerts_default_page();

$tab_array	= array();
$tab_array[]	= array(gettext('General'),	false,	'/pfblockerng/pfblockerng_general.php');
$tab_array[]	= array(gettext('IP'),		false,	'/pfblockerng/pfblockerng_ip.php');
$tab_array[]	= array(gettext('DNSBL'),	true,	'/pfblockerng/pfblockerng_dnsbl.php');
$tab_array[]	= array(gettext('Update'),	false,	'/pfblockerng/pfblockerng_update.php');
$tab_array[]	= array(gettext('Reports'),	false,	"/pfblockerng/pfblockerng_alerts.php{$get_req}");
$tab_array[]	= array(gettext('Feeds'),	false,	'/pfblockerng/pfblockerng_feeds.php');
$tab_array[]	= array(gettext('Logs'),	false,	'/pfblockerng/pfblockerng_log.php');
$tab_array[]	= array(gettext('Sync'),	false,	'/pfblockerng/pfblockerng_sync.php');
display_top_tabs($tab_array, true);

$tab_array	= array();
$tab_array[]	= array(gettext('DNSBL Feeds'),		false,		'/pfblockerng/pfblockerng_category.php?type=dnsbl');
$tab_array[]	= array(gettext('DNSBL EasyList'),	false,		'/pfblockerng/pfblockerng_category.php?type=easylist');
$tab_array[]	= array(gettext('DNSBL Category'),	false,		'/pfblockerng/pfblockerng_blacklist.php');
display_top_tabs($tab_array, true);

if (isset($input_errors)) {
	print_input_errors($input_errors);
}

$form = new Form('Save DNSBL settings');

$section = new Form_Section('DNSBL Configuration');
$section->addInput(new Form_StaticText(
	'Links',
	'<small>'
	. '<a href="/firewall_aliases.php" target="_blank">Firewall Aliases</a>&emsp;'
	. '<a href="/firewall_rules.php" target="_blank">Firewall Rules</a>&emsp;'
	. '<a href="/status_logs_filter.php" target="_blank">Firewall Logs</a></small>'
));

$dnsbl_text = '<div class="infoblock">
			<span class="text-danger">Note: </span>
			DNSBL requires the DNS Resolver (Unbound) to be used as the DNS service.<br />
			When a DNS request is made for a Domain that is listed in DNSBL, the request is redirected to the Virtual IP address<br />
			where an instance of Lighttpd Web Server will collect the packet statistics and push a \'1x1\' GIF image to the Browser.<br /><br />

			If browsing is slow, check for Firewall LAN Rules/Limiters that might be blocking access to the DNSBL VIP.<br /><br />

			<span class="text-danger">Note: </span>
			DNSBL will block and <u>partially</u> log Alerts for HTTPS requests.
			To debug issues with \'False Positives\', the following tools below can be used:<br />
			<ol>
				<li>Browser Dev mode (F12) and goto \'Console\' to review any error messages.</li>
				<li>Execute the following command from pfSense Shell (Changing the interface \'re1\' to the pfSense Lan Interface):<br />
					&emsp;<strong>tcpdump -nnvli re1 port 53 | grep -B1 \'A 10.10.10.1\'</strong></li>
				<li>Packet capture software such as Wireshark.</li>
			</ol>
		</div>';

$section->addInput(new Form_Checkbox(
	'pfb_dnsbl',
	gettext('DNSBL'),
	'Enable',
	$pconfig['pfb_dnsbl'] === 'on' ? true:false,
	'on'
))->setHelp('This will enable DNS Block List for Malicious and/or unwanted Adverts Domains<br />'
		. 'To Utilize, <strong>Unbound DNS Resolver</strong> must be enabled.<br />'
		. 'Also ensure that pfBlockerNG is enabled.'
		. "{$dnsbl_text}"
);

$dnsbl_text = 'This is an <strong>Advanced process</strong> to determine if all Sub-Domains should be blocked for each listed Domain.<br />
		<span class="text-danger">Click infoblock before enabling this feature!</span>&emsp;
		<div class="infoblock">

		<strong>This Feature is not recommended for Low-Perfomance/Low-Memory installations!</strong><br />
		<strong>Definition: TLD</strong> -
		&emsp;represents the last segment of a domain name. IE: example.com (TLD = com), example.uk.com (TLD = uk.com)<br /><br />

		The \'Unbound Resolver Reloads\' can take several seconds or more to complete and may temporarily interrupt
		DNS Resolution until the Resolver has been fully Reloaded with the updated Domain changes.
		Consider updating the DNSBL Feeds <strong>\'Once per Day\'</strong>, if network issues arise.<br /><br />

		When enabled and after all downloads for DNSBL Feeds have completed; TLD will process the Domains.<br />
		TLD uses a predetermined list of TLDs, to determine if the listed Domain should be configured to block all Sub-Domains.<br />
		The predetermined TLD list can be found in &emsp;<u>/usr/local/pkg/pfblockerng/dnsbl_tld</u><br /><br />

		To exclude a TLD/Domain from the TLD process, add the TLD/Domain to the <strong>TLD Exclusion</strong>.
		The specific Domain will be Blocked, but all other Sub-Domains will only be blocked if they are listed elsewhere.
		Whitelisting a Domain in the <strong>Custom Domain Whitelist</strong> can also be used to bypass TLD, however,
		the listed Domain will not be Blocked.<br /><br />

		<strong>TLD Blacklist</strong>, can be used to block whole TLDs. &emsp;IE: <strong>xyz</strong><br />
		<strong>TLD Whitelist</strong> is <strong><u>only</u></strong> used in conjunction with <strong>TLD Blacklist</strong> and
		is used to allow access to a Domain that is being blocked by a TLD Blacklist.<br /><br />

		When Enabling/Disabling TLD, a <strong>Force Reload - DNSBL</strong> is required.<br /><br />

		Once the TLD Domain limit below is exceeded, the balance of the Domains will be listed as-is.
		IE: Blocking only the listed Domain (Not Sub-Domains)<br /><strong>TLD Domain Limit Restrictions:</strong><br />
		<ul>
			<li>< 1.0GB RAM - Max 100k Domains</li>
			<li>< 1.5GB RAM - Max 150k Domains</li>
			<li>< 2.0GB RAM - Max 200k Domains</li>
			<li>< 2.5GB RAM - Max 250k Domains</li>
			<li>< 3.0GB RAM - Max 400k Domains</li>
			<li>< 4.0GB RAM - Max 600k Domains</li>
			<li>< 5.0GB RAM - Max 1.0M Domains</li>
			<li>< 6.0GB RAM - Max 1.5M Domains</li>
			<li>< 7.0GB RAM - Max 2.5M Domains</li>
			<li>> 7.0GB RAM - > 2.5M Domains</li>
		</ul>
	</div>';

$section->addInput(new Form_Checkbox(
	'pfb_tld',
	gettext('TLD'),
	'Enable',
	$pconfig['pfb_tld'] === 'on' ? true:false,
	'on'
))->setHelp($dnsbl_text);

$section->addInput(new Form_Input(
	'pfb_dnsvip',
	gettext('Virtual IP Address'),
	'text',
	$pconfig['pfb_dnsvip'],
	[ 'placeholder' => 'Enter DNSBL VIP address' ]
))->setHelp('Example ( 10.10.10.1 )<br />'
		. 'Enter a &emsp;<strong>single IPv4 VIP address</strong> &emsp;that is RFC1918 Compliant.<br /><br />'
		. 'This address should be in an Isolated Range that is not used in your Network.<br />'
		. 'Rejected DNS Requests will be forwarded to this VIP (Virtual IP)<br />'
		. 'RFC1918 Compliant - (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16)<br />'
		. 'Changes to the DNSBL VIP will require a Force Reload - DNSBL to take effect.'
);

$group = new Form_Group('VIP Address Type');
$group->add(new Form_Select(
	'pfb_dnsvip_type',
	gettext('DNSBL VIP Type'),
	$pconfig['pfb_dnsvip_type'],
	[ 'ipalias' => 'IP Alias', 'carp' => 'Carp' ]
))->setHelp('Select the DNSBL VIP type.<br />Default: IP Alias &emsp;<span class="badge">Carp mode (Beta)</span>');

$group->add(new Form_Input(
	'pfb_dnsvip_pass',
	'VIP Carp Password',
	'password',
	$pconfig['pfb_dnsvip_pass'],
	[ 'placeholder' => 'Enter Carp password' ]
));
$section->add($group);

$section->addInput(new Form_Input(
	'pfb_dnsport',
	gettext('Listening Port'),
	'number',
	$pconfig['pfb_dnsport'],
	[ 'min' => 1, 'max' => 65535, 'placeholder' => 'Enter DNSBL Listening Port' ]
))->setHelp('Example ( 8081 )<br />Enter a &emsp;<strong>single PORT</strong> &emsp;that is in the range of 1 - 65535<br />'
		. 'This Port must not be in use by any other process.'
);

$section->addInput(new Form_Input(
	'pfb_dnsport_ssl',
	gettext('SSL Listening Port'),
	'number',
	$pconfig['pfb_dnsport_ssl'],
	[ 'min' => 1, 'max' => 65535, 'placeholder' => 'Enter DNSBL VIP address' ]
))->setHelp('Example ( 8443 )<br />Enter a &emsp;<strong>single PORT</strong> &emsp;that is in the range of 1 - 65535<br />'
		. 'This Port must not be in use by any other process.'
);

$interface_list	= pfb_build_if_list(FALSE, FALSE);
$int_size	= count($interface_list) ?: '1';

$section->addInput(new Form_Select(
	'dnsbl_interface',
	gettext('Listening Interface'),
	$pconfig['dnsbl_interface'],
	$interface_list
))->setHelp('Select the interface you want the DNSBL Web Server to Listen on.<br />'
	. 'Default: <strong>LAN</strong> - Selected Interface should be a Local Interface only.');

$group = new Form_Group('Permit Firewall Rules');
$group->add(new Form_Checkbox(
	'pfb_dnsbl_rule',
	NULL,
	gettext('Enable'),
	$pconfig['pfb_dnsbl_rule'] === 'on' ? true:false,
	'on'
))->setWidth(7)
  ->setHelp('This will create \'Floating\' Firewall permit rules to allow traffic from the Selected Interface(s) to access<br />'
		. 'the DNSBL VIP on the DNSBL Listening interface. (ICMP and Webserver ports only). This is only required for networks with multiple LAN Segments.');

$int_size = count($interface_list) ?: '1';
$group->add(new Form_Select(
	'dnsbl_allow_int',
	NULL,
	$pconfig['dnsbl_allow_int'],
	$interface_list,
	TRUE
))->setAttribute('style', 'width: auto')
  ->setAttribute('size', $int_size);
$section->add($group);

$lista = array();
$indexdir = '/usr/local/www/pfblockerng/www/';
if (is_dir("{$indexdir}")) {
	$list = glob("{$indexdir}/dnsbl_*.php");
	if (!empty($list)) {
		foreach ($list as $line) {
			if (strpos($line, 'dnsbl_active.php') === FALSE) {
				$file = pathinfo($line, PATHINFO_BASENAME);
				$l = array($file => $file);
				$lista = array_merge($lista, $l);
			}
		}
	}
}
$list_size = count($lista) ?: '1';

$section->addInput(new Form_Select(
	'dnsbl_webpage',
	'Blocked Webpage',
	$pconfig['dnsbl_webpage'],
	$lista
))->sethelp('Default: <strong>dnsbl_default.php</strong><br />Select the DNSBL Blocked Webpage.<br />'
	. 'Custom block web pages can be added to: <strong>/usr/local/www/pfblockerng/www/</strong> folder.')
  ->setAttribute('style', 'width: auto')
  ->setAttribute('size', $list_size);

$section->addInput(new Form_Checkbox(
	'pfb_dnsbl_sync',
	gettext('Resolver Live Sync'),
	'Enable',
	$pconfig['pfb_dnsbl_sync'] === 'on' ? true:false,
	'on'
))->setHelp('<span class="badge" title="This feature is in BETA">BETA</span>&emsp;'
	. 'When enabled, updates to the DNS Resolver DNSBL database will be performed Live without reloading the Resolver.'
	. '<br />&emsp;&emsp;&emsp;&emsp;<span class="text-danger">Note: </span>A Force Reload will run a full Reload of Unbound');

$form->add($section);

$section = new Form_Section('DNSBL IP Firewall Rule Settings', 'DNSBL_IP_Firewall', COLLAPSIBLE|SEC_CLOSED);
$section->addInput(new Form_StaticText(
	NULL,
	'Configure settings for Firewall Rules when any DNSBL Feed contain IP Addresses.<br />'
	. '<span class="text-danger">Note: </span>To utilize this feature, you must define the Inbound/Outbound Interfaces in the IP Tab.'
));

$list_action_text = 'Default: <strong>Disabled</strong>
			<div class="infoblock">
				Select the <strong>Action</strong> for Firewall Rules when any DNSBL Feed contain IP addresses.<br /><br />
				<strong><u>\'Disabled\' Rule:</u></strong> Disables selection and does nothing to selected Alias.<br /><br />

				<strong><u>\'Deny\' Rules:</u></strong><br />
				\'Deny\' rules create high priority \'block\' or \'reject\' rules on the stated interfaces.
				 They don\'t change the \'pass\' rules on other interfaces. Typical uses of \'Deny\' rules are:<br />

				<ul>
				<li><strong>Deny Both</strong> - blocks all traffic in both directions, if the source or destination IP is in the block list</li>
				<li><strong>Deny Inbound/Deny Outbound</strong> - blocks all traffic in one direction <u>unless</u> it is part of a session started by
				traffic sent in the other direction. Does not affect traffic in the other direction.</li>
				<li>One way \'Deny\' rules can be used to selectively block <u>unsolicited</u> incoming (new session) packets in one direction, while
				still allowing <u>deliberate</u> outgoing sessions to be created in the other direction.</li>
				</ul>

				<strong><u>\'Alias\' Rule:</u></strong><br />
				<strong>\'Alias\'</strong> rules create an <a href="/firewall_aliases.php">alias</a> for the list (and do nothing else).
				This enables a pfBlockerNG list to be used by name, in any firewall rule or pfSense function, as desired.
			</div>';

$section->addInput(new Form_Select(
	'action',
	gettext('List Action'),
	$pconfig['action'],
	[ 'Disabled' => 'Disabled', 'Deny_Inbound' => 'Deny Inbound', 'Deny_Outbound' => 'Deny Outbound', 'Deny_Both' => 'Deny Both', 'Alias_Deny' => 'Alias Deny' ]
))->setHelp($list_action_text);

$section->addInput(new Form_Select(
	'aliaslog',
	gettext('Enable Logging'),
	$pconfig['aliaslog'],
	[ 'enabled' => 'Enable', 'disabled' => 'Disable' ]
))->sethelp('Default: <strong>Enable</strong><br />Select - Logging to Status: System Logs: FIREWALL ( Log )<br />'
		. 'This can be overriden by the \'Global Logging\' Option in the General Tab.'
);

$form->add($section);

// Print Advanced Firewall Rule Settings (Inbound and Outbound) section
foreach (array( 'In' => 'Source', 'Out' => 'Destination') as $adv_mode => $adv_type) {

	$advmode = strtolower($adv_mode);

	// Collect all pfSense 'Port' Aliases
	$portslist = $networkslist = '';
	if (!empty($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $alias) {
			if ($alias['type'] == 'port') {
				$portslist .= "{$alias['name']},";
			} elseif ($alias['type'] == 'network') {
				$networkslist .= "{$alias['name']},";
			}
		}
	}
	$ports_list	= trim($portslist, ',');
	$networks_list	= trim($networkslist, ',');

	$section = new Form_Section("Advanced {$adv_mode}bound Firewall Rule Settings", "adv{$advmode}boundsettings", COLLAPSIBLE|SEC_CLOSED);
	$section->addInput(new Form_StaticText(
		NULL,
		"<span class=\"text-danger\">Note:</span>&nbsp; In general, Auto-Rules are created as follows:<br />
			<dl class=\"dl-horizontal\">
				<dt>{$adv_mode}bound</dt><dd>'any' port, 'any' protocol, 'any' destination and 'any' gateway</dd>
			</dl>
			Configuring the Adv. {$adv_mode}bound Rule settings, will allow for more customization of the {$adv_mode}bound Auto-Rules."));

	$section->addInput(new Form_Checkbox(
		'autoaddrnot_' . $advmode,
		"Invert {$adv_type}",
		NULL,
		$pconfig['autoaddrnot_' . $advmode] === 'on' ? true:false,
		'on'
	))->setHelp("Option to invert the sense of the match. ie - Not (!) {$adv_type} Address(es)");

	$group = new Form_Group("Custom DST Port");
	$group->add(new Form_Checkbox(
		'autoports_' . $advmode,
		'Custom DST Port',
		NULL,
		$pconfig['autoports_' . $advmode] === 'on' ? true:false,
		'on'
	))->setHelp('Enable')
	  ->setWidth(2);

	$group->add(new Form_Input(
		'aliasports_' . $advmode,
		'Custom Port',
		'text',
		$pconfig["aliasports_{$advmode}"]
	))->setHelp("<a target=\"_blank\" href=\"/firewall_aliases.php?tab=port\">Click Here to add/edit Aliases</a>
			Do not manually enter port numbers.<br />Do not use 'pfB_' in the Port Alias name."
	)->setWidth(8);
	$section->add($group);

	if ($adv_type == 'Source') {
		$custom_location = 'Destination';
	} else {
		$custom_location = 'Source';
	}

	$group = new Form_Group("Custom {$custom_location}");
	$group->add(new Form_Checkbox(
		'autoaddr_' . $advmode,
		"Custom {$custom_location}",
		NULL,
		$pconfig["autoaddr_{$advmode}"] === 'on' ? true:false,
		'on'
	))->setHelp('Enable')->setWidth(1);

	$group->add(new Form_Checkbox(
		'autonot_' . $advmode,
		NULL,
		NULL,
		$pconfig["autonot_{$advmode}"] === 'on' ? true:false,
		'on'
	))->setHelp('Invert')->setWidth(1);

	$group->add(new Form_Input(
		'aliasaddr_' . $advmode,
		"Custom {$custom_location}",
		'text',
		$pconfig['aliasaddr_' . $advmode]
	))->sethelp('<a target="_blank" href="/firewall_aliases.php?tab=ip">Click Here to add/edit Aliases</a>'
		. 'Do not manually enter Addresses(es).<br />Do not use \'pfB_\' in the \'IP Network Type\' Alias name.<br />'
		. "Select 'invert' to invert the sense of the match. ie - Not (!) {$custom_location} Address(es)"
	)->setWidth(8);
	$section->add($group);

	$group = new Form_Group('Custom Protocol');
	$group->add(new Form_Select(
		'autoproto_' . $advmode,
		NULL,
		$pconfig['autoproto_' . $advmode],
		['' => 'any', 'tcp' => 'TCP', 'udp' => 'UDP', 'tcp/udp' => 'TCP/UDP']
	))->setHelp("<strong>Default: any</strong><br />Select the Protocol used for {$adv_mode}bound Firewall Rule(s).<br />
		<span class=\"text-danger\">Note:</span>&nbsp;Do not use 'any' with Adv. {$adv_mode}bound Rules as it will bypass these settings!");
	$section->add($group);

	$group = new Form_Group('Custom Gateway');
	$group->add(new Form_Select(
		'agateway_' . $advmode,
		NULL,
		$pconfig['agateway_' . $advmode],
		pfb_get_gateways()
	))->setHelp("Select alternate Gateway or keep 'default' setting.");

	$section->add($group);
	$form->add($section);
}

// Print Custom List TextArea section
$section = new Form_Section('DNSBL Whitelist', 'DNSBL_Whitelist_customlist', COLLAPSIBLE|SEC_CLOSED);

// Create page anchor for DNSBL Whitelist
$section->addInput(new Form_StaticText(
	NULL,
	'<div id="Whitelist"></div>'));

$suppression_text = 'No Regex Entries Allowed!&emsp;
			<div class="infoblock">
				Enter one &emsp; <strong>Domain Name</strong>&emsp; per line<br />
				Prefix Domain with a "." to Whitelist all Sub-Domains. &emsp;IE: (.example.com)<br />
				You may use "<strong>#</strong>" after any Domain name to add comments. &emsp;IE: (example.com # Whitelist example.com)<br />
				This List is stored as \'Base64\' format in the config.xml file.<br /><br />

				<span class="text-danger">Note: </span>These entries are only Whitelisted when Feeds are downloaded or on a
				<span class="text-danger">\'Force Reload\'.</span><br />

				Use the Alerts Tab \'+\' Whitelist Icon to immediately remove a Domain (and any associated CNAMES) from Unbound DNSBL.<br />
				Note: When manually adding a Domain to the Whitelist, check for any associated CNAMES<br />
				&emsp; ie: \'drill @8.8.8.8 example.com\'
			</div>';

$section->addInput(new Form_Textarea(
	'suppression',
	NULL,
	$pconfig['suppression']
))->removeClass('form-control')
  ->addClass('row-fluid col-sm-12')
  ->setAttribute('columns', '90')
  ->setAttribute('rows', '15')
  ->setAttribute('wrap', 'off')
  ->setAttribute('style', 'background:#fafafa; width: 100%')
  ->setHelp($suppression_text);

$form->add($section);

$section = new Form_Section('TOP1M Whitelist', 'TOP1M_Whitelist', COLLAPSIBLE|SEC_CLOSED);
$top1m_text = 'The TOP1M feed can be used to whitelist the most popular Domain names to avoid false positives.<br />
		Note: The domains listed in the TOP1M *may* be malicious in nature, there consider limiting this feature.<br /><br />
		Whitelist(s) available:<br />

		<ul>
			<li><a target="_blank" href="https://aws.amazon.com/alexa-top-sites/">Alexa TOP1M</a></li>
			<li><a target="_blank" href="https://s3-us-west-1.amazonaws.com/umbrella-static/index.html">Cisco Umbrella TOP1M</a></li>
		</ul>
		To use this feature, select the number of \'Top Domains\' to whitelist. You can also \'include\' which TLDs to whitelist.

		<div class="infoblock">
			<span class="text-danger">Recommendation: </span>
			<ul>TOP1M also contains the \'Top\' AD Servers, so its recommended to configure the first DNSBL Alias with AD Server<br />
				(ie. yoyo, Adaway...) based feeds. TOP1M whitelisting can be disabled for this first defined Alias.<br /><br />
				Generally, TOP1M should be used for feeds that post full URLs like PhishTank, OpenPhish or MalwarePatrol.<br /><br />
				To bypass a TOP1M Domain, add the Domain to the first defined Alias \'Custom Block list\' with TOP1M disabled in this alias.<br />
				When enabled, this list will be automatically updated once per month along with the MaxMind Database.
			</ul>
		</div>';

$section->addInput(new Form_Checkbox(
	'alexa_enable',
	gettext('TOP1M'),
	'Enable',
	$pconfig['alexa_enable'] === 'on' ? true:false,
	'on'
))->setHelp($top1m_text);

$section->addInput(new Form_Select(
	'alexa_type',
	gettext('Type'),
	$pconfig['alexa_type'],
	[ 'alexa' => 'Alexa TOP1M', 'cisco' => 'Cisco Umbrella TOP1M' ]
))->setHelp('To change the TOP1M type, select type and Save, followed by a \'Force Reload - DNSBL\'');

$section->addInput(new Form_Select(
	'alexa_count',
	gettext('Domain count'),
	$pconfig['alexa_count'],
	[	'500' => 'Top 500', '1000' => 'Top 1k', '2000' => 'Top 2k', '5000' => 'Top 5k', '10000' => 'Top 10k',
		'25000' => 'Top 25k', '50000' => 'Top 50k', '75000' => 'Top 75k', '100000' => 'Top 100k', '250000' => 'Top 250k',
		'500000' => 'Top 500k', '750000' => 'Top 750k', '1000000' => 'Top 1M'
	]
))->sethelp('<strong>Default: Top 1k</strong><br />Select the <strong>number</strong> of TOP1M \'Top Domain global ranking\' to whitelist.');

$section->addInput(new Form_Select(
	'alexa_inclusion',
	gettext('TLD Inclusion'),
	$pconfig['alexa_inclusion'],
	[	'ae' => 'AE',
		'aero' => 'AERO',
		'ag' => 'AG',
		'al' => 'AL',
		'am' => 'AM',
		'ar' => 'AR',
		'ae' => 'AE',
		'aero' => 'AERO',
		'ag' => 'AG',
		'al' => 'AL',
		'am' => 'AM',
		'ar' => 'AR',
		'asia' => 'ASIA',
		'at' => 'AT',
		'au' => 'AU (16)',
		'az' => 'AZ',
		'ba' => 'BA',
		'bd' => 'BD',
		'be' => 'BE',
		'bg' => 'BG',
		'biz' => 'BIZ',
		'bo' => 'BO',
		'br' => 'BR (7)',
		'by' => 'BY',
		'bz' => 'BZ',
		'ca' => 'CA (21)',
		'cat' => 'CAT',
		'cc' => 'CC',
		'cf' => 'CF',
		'ch' => 'CH',
		'cl' => 'CL',
		'club' => 'CLUB',
		'cn' => 'CN (14)',
		'co' => 'CO (22)',
		'com' => 'COM (1)',
		'coop' => 'COOP',
		'cr' => 'CR',
		'cu' => 'CU',
		'cy' => 'CY',
		'cz' => 'CZ (23)',
		'de' => 'DE (5)',
		'dev' => 'DEV',
		'dk' => 'DK',
		'do' => 'DO',
		'dz' => 'DZ',
		'ec' => 'EC',
		'edu' => 'EDU',
		'ee' => 'EE',
		'eg' => 'EG',
		'es' => 'ES (18)',
		'eu' => 'EU (25)',
		'fi' => 'FI',
		'fm' => 'FM',
		'fr' => 'FR (12)',
		'ga' => 'GA',
		'ge' => 'GE',
		'gov' => 'GOV',
		'gr' => 'GR (20)',
		'gt' => 'GT',
		'guru' => 'GURU',
		'hk' => 'HK',
		'hr' => 'HR',
		'hu' => 'HU',
		'id' => 'ID',
		'ie' => 'IE',
		'il' => 'IL',
		'im' => 'IM',
		'in' => 'IN (9)',
		'info' => 'INFO (15)',
		'int' => 'INT',
		'io' => 'IO',
		'ir' => 'IR (13)',
		'is' => 'IS',
		'it' => 'IT (11)',
		'jo' => 'JO',
		'jobs' => 'JOBS',
		'jp' => 'JP (6)',
		'ke' => 'KE',
		'kg' => 'KG',
		'kr' => 'KR (19)',
		'kw' => 'KW',
		'kz' => 'KZ',
		'la' => 'LA',
		'li' => 'LI',
		'link' => 'LINK',
		'lk' => 'LK',
		'lt' => 'LT',
		'lu' => 'LU',
		'lv' => 'LV',
		'ly' => 'LY',
		'ma' => 'MA',
		'md' => 'MD',
		'me' => 'ME',
		'mk' => 'MK',
		'ml' => 'ML',
		'mn' => 'MN',
		'mobi' => 'MOBI',
		'mx' => 'MX',
		'my' => 'MY',
		'name' => 'NAME',
		'net' => 'NET (2)',
		'ng' => 'NG',
		'ninja' => 'NINJA',
		'nl' => 'NL (17)',
		'no' => 'NO',
		'np' => 'NP',
		'nu' => 'NU',
		'nz' => 'NZ',
		'om' => 'OM',
		'org' => 'ORG (4)',
		'pa' => 'PA',
		'pe' => 'PE',
		'ph' => 'PH',
		'pk' => 'PK',
		'pl' => 'PL (10)',
		'pro' => 'PRO',
		'pt' => 'PT',
		'pw' => 'PW',
		'py' => 'PY',
		'qa' => 'QA',
		'ro' => 'RO',
		'rs' => 'RS',
		'ru' => 'RU (3)',
		'sa' => 'SA',
		'se' => 'SE',
		'sg' => 'SG',
		'si' => 'SI',
		'sk' => 'SK',
		'so' => 'SO',
		'space' => 'SPACE',
		'su' => 'SU',
		'th' => 'TH',
		'tk' => 'TK',
		'tn' => 'TN',
		'to' => 'TO',
		'today' => 'TODAY',
		'top' => 'TOP',
		'tr' => 'TR',
		'travel' => 'TRAVEL',
		'tv' => 'TV',
		'tw' => 'TW (24)',
		'tz' => 'TZ',
		'ua' => 'UA',
		'uk' => 'UK (8)',
		'us' => 'US',
		'uy' => 'UY',
		'uz' => 'UZ',
		'vc' => 'VC',
		've' => 'VE',
		'vn' => 'VN',
		'website' => 'WEBSITE',
		'ws' => 'WS',
		'xn--p1ai' => 'XN--P1AI',
		'xxx' => 'XXX',
		'xyz' => 'XYZ',
		'za' => 'ZA'
	],
	TRUE
))->setHelp('Select the TLDs for Whitelist. (Only showing the Top 150 TLDs)<br />'
		. '<strong>Default: COM, NET, ORG, CA, CO, IO</strong><br /><br />'
		. 'Detailed listing : <a target=_blank href="http://www.iana.org/domains/root/db">Root Zone Top-Level Domains.</a>'
)->setAttribute('size', '20')
 ->setWidth(3);

$form->add($section);

$section = new Form_Section('TLD Exclusion List', 'TLD_Exclusion', COLLAPSIBLE|SEC_CLOSED);
$tld_exclusion_text = 'Enter TLD(s) and/or Domain(s) to be excluded from the TLD function. These excluded TLDs/domains/sub-domains will be listed as-is.&emsp;
			<div class="infoblock">
				Enter one &emsp; <strong>Domain Name or TLD</strong>&emsp; per line<br />
				No Regex Entries and no leading/trailing \'dot\' allowed!<br />
				You may use "<strong>#</strong>" after any Domain/TLD to add comments. &emsp;<br />
				IE: (example.com # Exclude example.com)<br />
				IE: (co.uk # Exclude CO.UK)<br />
				This List is stored as \'Base64\' format in the config.xml file.<br /><br />
			</div>';

$section->addInput(new Form_Textarea(
	'tldexclusion',
	'TLD Exclusion List',
	$pconfig['tldexclusion']
))->removeClass('form-control')
  ->addClass('row-fluid col-sm-12')
  ->setAttribute('columns', '90')
  ->setAttribute('rows', '15')
  ->setAttribute('wrap', 'off')
  ->setAttribute('style', 'background:#fafafa; width: 100%')
  ->setHelp($tld_exclusion_text);

$form->add($section);

$section = new Form_Section('TLD Blacklist/Whitelist', 'TLD_BW_list', COLLAPSIBLE|SEC_CLOSED);

$section->addInput(new Form_StaticText(
	'Note:',
	'The TLD Blacklist is used to block a whole TLD (IE: pw).<br />'
	. 'The TLD Whitelist is used to allow access to the specific domain/sub-domains that is blocked by a TLD Blacklist; while blocking all others.<br />'
	. 'TLD Blacklist/Whitelist: A <strong>static</strong> zone entry is used in the DNS Resolver for this feature, therefore no Alerts will be generated.'
));

$tld_blacklist_text = 'Enter TLD(s) to be blacklisted.&emsp;
			<div class="infoblock">
				Enter one &emsp; <strong>TLD</strong>&emsp; per line. ie: xyz<br />
				No Regex Entries and no leading/trailing \'dot\' allowed!<br />
				You may use "<strong>#</strong>" after any TLD to add comments. example (xyz # Blacklist XYZ TLD)<br />
				This List is stored as \'Base64\' format in the config.xml file.<br /><br />
			</div>';

$section->addInput(new Form_Textarea(
	'tldblacklist',
	'TLD Blacklist',
	$pconfig['tldblacklist']
))->removeClass('form-control')
  ->addClass('row-fluid col-sm-12')
  ->setAttribute('columns', '90')
  ->setAttribute('rows', '15')
  ->setAttribute('wrap', 'off')
  ->setAttribute('style', 'background:#fafafa; width: 100%')
  ->setHelp($tld_blacklist_text);

$tld_whitelist_text = 'Enter <strong>each specific</strong> Domain and/or Sub-Domains to be Whitelisted.
			(Used in conjunction with <strong>TLD Blacklist only</strong>)&emsp;
			<div class="infoblock">
				Enter one &emsp;<strong>Domain</strong>&emsp;per line<br />Examples:<br />
				<ul>
					<li>example.com</li>
					<li>example.com|x.x.x.x&emsp;&emsp;(Replace x.x.x.x with associated Domain/Sub-Domain IP Address.</li>
				</ul>
				The First option above will collect the IP Address on each Cron run,
				while the second option will define a Static IP Address.<br /><br />

				You must Whitelist every Domain or Sub-Domain individually.<br />
				No Regex Entries and no leading/trailing \'dot\' allowed!<br />
				You may use "<strong>#</strong>" after any Domain/Sub-Domain to add comments. IE: (example.com|x.x.x.x # TLD Whitelist)<br />
				This List is stored as \'Base64\' format in the config.xml file.<br /><br />
			</div>';

$section->addInput(new Form_Textarea(
	'tldwhitelist',
	'TLD Whitelist',
	$pconfig['tldwhitelist']
))->removeClass('form-control')
  ->addClass('row-fluid col-sm-12')
  ->setAttribute('columns', '90')
  ->setAttribute('rows', '15')
  ->setAttribute('wrap', 'off')
  ->setAttribute('style', 'background:#fafafa; width: 100%')
  ->setHelp($tld_whitelist_text);

$form->add($section);
print ($form);
print_callout('<strong>Setting changes are applied via CRON or \'Force Update|Reload\' only!</strong>');

?>
<script type="text/javascript">
//<![CDATA[

var pagetype = 'advanced';
var disable_move = "<?=$disable_move?>";

// Auto-Complete for Adv. In/Out Address Select boxes
var plist = "<?=$ports_list?>";
var portsarray = plist.split(',');
var nlist = "<?=$networks_list?>";
var networksarray = nlist.split(',');

// Disable GeoIP/ASN Autocomplete as not required for the DNSBL page
var geoiparray = 'disabled';

function enable_carp() {
	if ($('#pfb_dnsvip_type').val() == 'ipalias') {
		disableInput('pfb_dnsvip_pass', true);
	} else {
		disableInput('pfb_dnsvip_pass', false);
	}
}

events.push(function(){

	$('#pfb_dnsvip_type').click(function() {
		enable_carp();
	});
	enable_carp();
});

//]]>
</script>
<script src="pfBlockerNG.js" type="text/javascript"></script>
<?php include('foot.inc');?>
