<?php
/*
 * pfblockerng_dnsbl.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2024 BBcan177@gmail.com
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

global $pfb;
pfb_global();
$disable_move = FALSE;

$pfb['dconfig'] = config_get_path('installedpackages/pfblockerngdnsblsettings/config/0', []);

// Collect local domain TLD for Python TLD Allow array
if (strpos(config_get_path('system/domain'), '.') !== FALSE) {
	$local_tld = ltrim(strstr(config_get_path('system/domain'), '.', FALSE), '.');
} else {
	$local_tld = config_get_path('system/domain');
}
$default_tlds = array('arpa',$local_tld,'com','net','org','edu','ca','co','io');

$pconfig = array();
$pconfig['pfb_dnsbl']		= $pfb['dconfig']['pfb_dnsbl']				?: '';
$pconfig['pfb_tld']		= $pfb['dconfig']['pfb_tld']				?: '';
$pconfig['pfb_control']		= $pfb['dconfig']['pfb_control']			?: '';
$pconfig['pfb_dnsvip4'] = $pfb['dconfig']['pfb_dnsvip4'] ?: 'none';
$pconfig['pfb_dnsvip6'] = $pfb['dconfig']['pfb_dnsvip6'] ?: 'none';
$pconfig['pfb_dnsport']		= $pfb['dconfig']['pfb_dnsport']			?: '8081';
$pconfig['pfb_dnsport_ssl']	= $pfb['dconfig']['pfb_dnsport_ssl']			?: '8443';
$pconfig['dnsbl_interface']	= $pfb['dconfig']['dnsbl_interface']			?: 'lo0';
$pconfig['pfb_dnsbl_rule']	= $pfb['dconfig']['pfb_dnsbl_rule']			?: '';
$pconfig['dnsbl_allow_int']	= explode(',', $pfb['dconfig']['dnsbl_allow_int'])	?: array();
$pconfig['global_log']		= $pfb['dconfig']['global_log']				?: '';
$pconfig['dnsbl_webpage']	= $pfb['dconfig']['dnsbl_webpage']			?: 'dnsbl_default.php';
$pconfig['pfb_cache']		= isset($pfb['dconfig']['pfb_cache'])			? $pfb['dconfig']['pfb_cache'] : 'on';
$pconfig['pfb_dnsbl_sync']	= $pfb['dconfig']['pfb_dnsbl_sync']			?: '';

$pconfig['dnsbl_mode']		= $pfb['dconfig']['dnsbl_mode']				?: '';
if (isset($pfb['dconfig']['pfb_python'])) {
	if ($pfb['dconfig']['pfb_python'] == 'on') {
		$pconfig['dnsbl_mode'] = 'dnsbl_python';
	} else {
		$pconfig['dnsbl_mode'] = 'dnsbl_unbound';
	}
}

$pconfig['pfb_py_reply']	= isset($pfb['dconfig']['pfb_py_reply'])		? $pfb['dconfig']['pfb_py_reply'] : 'on';
$pconfig['pfb_py_block']	= isset($pfb['dconfig']['pfb_py_block'])		? $pfb['dconfig']['pfb_py_block'] : 'on';
$pconfig['pfb_hsts']		= isset($pfb['dconfig']['pfb_hsts'])			? $pfb['dconfig']['pfb_hsts'] : 'on';
$pconfig['pfb_idn']		= $pfb['dconfig']['pfb_idn']				?: '';
$pconfig['pfb_regex']		= $pfb['dconfig']['pfb_regex']				?: '';
$pconfig['pfb_cname']		= $pfb['dconfig']['pfb_cname']				?: '';
$pconfig['pfb_noaaaa']		= $pfb['dconfig']['pfb_noaaaa']				?: '';
$pconfig['pfb_gp']		= $pfb['dconfig']['pfb_gp']				?: '';
$pconfig['pfb_pytld']		= $pfb['dconfig']['pfb_pytld']				?: '';
$pconfig['pfb_pytld_sort']	= $pfb['dconfig']['pfb_pytld_sort']			?: '';
$pconfig['pfb_pytlds_gtld']	= explode(',', $pfb['dconfig']['pfb_pytlds_gtld'])	?: $default_tlds;
$pconfig['pfb_pytlds_cctld']	= explode(',', $pfb['dconfig']['pfb_pytlds_cctld'])	?: array();
$pconfig['pfb_pytlds_itld']	= explode(',', $pfb['dconfig']['pfb_pytlds_itld'])	?: array();
$pconfig['pfb_pytlds_bgtld']	= explode(',', $pfb['dconfig']['pfb_pytlds_bgtld'])	?: array();
$pconfig['pfb_py_nolog']	= $pfb['dconfig']['pfb_py_nolog']			?: '';
$pconfig['pfb_regex_list']	= base64_decode($pfb['dconfig']['pfb_regex_list'])	?: '';
$pconfig['pfb_noaaaa_list']	= base64_decode($pfb['dconfig']['pfb_noaaaa_list'])	?: '';
$pconfig['pfb_gp_bypass_list']	= base64_decode($pfb['dconfig']['pfb_gp_bypass_list'])	?: '';
$pconfig['action']		= $pfb['dconfig']['action']				?: 'Disabled';
$pconfig['aliaslog']		= $pfb['dconfig']['aliaslog']				?: 'enabled';

$pconfig['autoaddrnot_in']	= $pfb['dconfig']['autoaddrnot_in']			?: '';
$pconfig['autoports_in']	= $pfb['dconfig']['autoports_in']			?: '';
$pconfig['aliasports_in']	= $pfb['dconfig']['aliasports_in']			?: '';
$pconfig['autoaddr_in']		= $pfb['dconfig']['autoaddr_in']			?: '';
$pconfig['autonot_in']		= $pfb['dconfig']['autonot_in']				?: '';
$pconfig['aliasaddr_in']	= $pfb['dconfig']['aliasaddr_in']			?: '';
$pconfig['autoproto_in']	= $pfb['dconfig']['autoproto_in']			?: 'any';
$pconfig['agateway_in']		= $pfb['dconfig']['agateway_in']			?: 'default';

$pconfig['autoaddrnot_out']	= $pfb['dconfig']['autoaddrnot_out']			?: '';
$pconfig['autoports_out']	= $pfb['dconfig']['autoports_out']			?: '';
$pconfig['aliasports_out']	= $pfb['dconfig']['aliasports_out']			?: '';
$pconfig['autoaddr_out']	= $pfb['dconfig']['autoaddr_out']			?: '';
$pconfig['autonot_out']		= $pfb['dconfig']['autonot_out']			?: '';
$pconfig['aliasaddr_out']	= $pfb['dconfig']['aliasaddr_out']			?: '';
$pconfig['autoproto_out']	= $pfb['dconfig']['autoproto_out']			?: 'any';
$pconfig['agateway_out']	= $pfb['dconfig']['agateway_out']			?: 'default';

$pconfig['suppression']		= base64_decode($pfb['dconfig']['suppression'])		?: '';

$pconfig['alexa_enable']	= $pfb['dconfig']['alexa_enable']			?: '';
$pconfig['alexa_type']		= $pfb['dconfig']['alexa_type']				?: 'tranco';
$pconfig['alexa_count']		= $pfb['dconfig']['alexa_count']			?: '1000';
$pconfig['alexa_inclusion']	= explode(',', $pfb['dconfig']['alexa_inclusion'])	?: array('com','net','org','ca','co','io');

$pconfig['tldexclusion']	= base64_decode($pfb['dconfig']['tldexclusion'])	?: '';
$pconfig['tldblacklist']	= base64_decode($pfb['dconfig']['tldblacklist'])	?: '';
$pconfig['tldwhitelist']	= base64_decode($pfb['dconfig']['tldwhitelist'])	?: '';

// Select field options

$options_dnsbl_mode		= [ 'dnsbl_unbound' => 'Unbound mode', 'dnsbl_python' => 'Unbound python mode' ];
$options_dnsbl_interface	= pfb_build_if_list(FALSE, FALSE);
$options_dnsbl_interface_all	= array_merge(array('lo0' => 'Localhost'), $options_dnsbl_interface);
$options_dnsbl_interface_cnt	= count($options_dnsbl_interface) ?: '1';

$options_dnsbl_allow_int	= $options_dnsbl_interface;

if ($pfb['dnsbl_py_blacklist']) {
	$options_global_log_txt = 'Default: <strong>No Global mode</strong><br />'
				. 'Enabling this option will overide the individual DNSBL Group "Logging/Blocking" settings!<br /><br />'
				. '&#8226 <strong>Null Block (logging)</strong>, Utilize \'0.0.0.0\' with logging.<br />'
				. '&#8226 <strong>DNSBL WebServer/VIP</strong>, Domains are sinkholed to the DNSBL VIP and logged via the DNSBL WebServer.<br />'
				. '&#8226 <strong>Null Block (no logging)</strong>, Utilize \'0.0.0.0\' with no logging.<br />'
				. 'Blocked domains will be reported to the Alert/Python Block Table.<br /><br />'
				. 'A \'Force Reload - DNSBL\' is required for changes to take effect';

	$options_global_log	= [	''		=> 'No Global mode',
					'disabled_log'	=> 'Null Block (logging)',
					'enabled'	=> 'DNSBL WebServer/VIP',
					'disabled'	=> 'Null Block (no logging)'];
} else {
	$options_global_log_txt = 'Default: <strong>No Global mode</strong><br />'
					. '&#8226 When \'Enabled\', Domains are sinkholed to the DNSBL VIP and logged via the DNSBL WebServer.<br />'
					. '&#8226 When \'Disabled\', <strong>\'0.0.0.0\'</strong> will be used instead of the DNSBL VIP.<br />'
					. 'A \'Force Reload - DNSBL\' is required for changes to take effect';

	$options_global_log	= [	''		=> 'No Global mode',
					'enabled'	=> 'DNSBL WebServer/VIP',
					'disabled'	=> 'Null Block (no logging)'];
}

$options_dnsbl_webpage = array();
$indexdir = '/usr/local/www/pfblockerng/www';
if (is_dir("{$indexdir}")) {
	$list = glob("{$indexdir}/*.{php,html}", GLOB_BRACE);
	if (!empty($list)) {
		foreach ($list as $line) {
			if (strpos($line, 'index.php') !== FALSE || strpos($line, 'dnsbl_active.php') !== FALSE) {
				continue;
			} else {
				$file = basename($line);
				if (@filesize("/usr/local/www/pfblockerng/www/{$file}") > 0) {
					$options_dnsbl_webpage = array_merge($options_dnsbl_webpage, array($file => $file));
				}
			}
		}
	}
}
$options_dnsbl_webpage_cnt = count($options_dnsbl_webpage) ?: '1';

$options_alexa_type		= [ 'tranco' => 'Tranco TOP1M', 'cisco' => 'Cisco Umbrella TOP1M', 'alexa' => 'Alexa TOP1M' ];

$options_alexa_count		= [	'500' => 'Top 500', '1000' => 'Top 1k', '2000' => 'Top 2k', '5000' => 'Top 5k', '10000' => 'Top 10k',
					'25000' => 'Top 25k', '50000' => 'Top 50k', '75000' => 'Top 75k', '100000' => 'Top 100k', '250000' => 'Top 250k',
					'500000' => 'Top 500k', '750000' => 'Top 750k', '1000000' => 'Top 1M' ];

$options_alexa_inclusion	= [	'ae' => 'AE',
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
				];

$options_action			= [ 'Disabled' => 'Disabled', 'Deny_Inbound' => 'Deny Inbound', 'Deny_Outbound' => 'Deny Outbound', 'Deny_Both' => 'Deny Both', 'Alias_Deny' => 'Alias Deny' ];

$options_aliaslog		= [ 'enabled' => 'Enable', 'disabled' => 'Disable' ];

// Collect all pfSense 'Port' Aliases
$ports_list = $networks_list = '';
foreach (config_get_path('aliases/alias', []) as $alias) {
	if ($alias['type'] == 'port') {
		$ports_list .= "{$alias['name']},";
	} elseif ($alias['type'] == 'network') {
		$networks_list .= "{$alias['name']},";
	}
}
$ports_list			= trim($ports_list, ',');
$networks_list			= trim($networks_list, ',');
$options_aliasports_in		= $options_aliasports_out	= explode(',', $ports_list);
$options_aliasaddr_in		= $options_aliasaddr_out	= explode(',', $networks_list);

$options_autoproto_in		= $options_autoproto_out	= get_ipprotocols();
$options_agateway_in		= $options_agateway_out		= pfb_get_gateways();

// Validate input fields and save
if ($_POST) {
	if (isset($_POST['save'])) {

		if (isset($input_errors)) {
			unset($input_errors);
		}

		// Validate Select field options
		$select_options = array(	'dnsbl_mode'		=> 'dnsbl_unbound',
						'dnsbl_interface'	=> 'lo0',
						'global_log'		=> '',
						'dnsbl_webpage'		=> 'dnsbl_default.php',
						'alexa_type'		=> 'tranco',
						'alexa_count'		=> '1000',
						'action'		=> 'Disabled',
						'aliaslog'		=> 'enabled',
						'aliasports_in'		=> '',
						'aliasports_out'	=> '',
						'aliasaddr_in'		=> '',
						'aliasaddr_out'		=> '',
						'autoproto_in'		=> 'any',
						'autoproto_out'		=> 'any',
						'agateway_in'		=> 'default',
						'agateway_out'		=> 'default'
						);

		foreach ($select_options as $s_option => $s_default) {
			if (is_array($_POST[$s_option])) {
				$_POST[$s_option] = $s_default;
			}
			elseif (!array_key_exists($_POST[$s_option], ${"options_$s_option"})) {
				$_POST[$s_option] = $s_default;
			}
		}

		// Validate Select field (array) options
		$select_options = array(	'dnsbl_allow_int'	=> '',
						'alexa_inclusion'	=> $default_tlds
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

		// Validate DNSBL webserver block page
		$dnsbl_webpage		= FALSE;
		$dnsbl_webpage_file	= pfb_filter(basename($_POST['dnsbl_webpage']), PFB_FILTER_WORD_DOT, 'dnsbl', 'dnsbl_default.php');
		if (file_exists("/usr/local/www/pfblockerng/www/{$dnsbl_webpage_file}") &&
		    @filesize("/usr/local/www/pfblockerng/www/{$dnsbl_webpage_file}") > 0 &&
		    pfb_filter(array("/usr/local/www/pfblockerng/www/{$dnsbl_webpage_file}", 'text/html'), PFB_FILTER_FILE_MIME_COMPARE, 'dnsbl')) {

			// Check if DNSBL Webpage has been changed.
			if ($dnsbl_webpage_file != $pfb['dconfig']['dnsbl_webpage']) {
				$dnsbl_webpage = TRUE;
			}
			$pfb['dconfig']['dnsbl_webpage'] = $dnsbl_webpage_file;
			config_set_path('installedpackages/pfblockerngdnsblsettings/config/0/dnsbl_webpage', $pfb['dconfig']['dnsbl_webpage']);
		}
		else {
			$input_errors[] = 'DNSBL Web Server page is invalid!';
		}

		foreach (array('aliasports_in', 'aliasaddr_in', 'aliasports_out', 'aliasaddr_out') as $value) {
			if (!empty($_POST[$value]) && !is_validaliasname($_POST[$value])) {
				$input_errors[] = 'Settings: Advanced In/Outbound Aliasname error - ' . invalidaliasnamemsg($_POST[$value]);
			}
		}

		if (!is_port($_POST['pfb_dnsport'])) {
			$input_errors[] = 'DNSBL Port is invalid!';
		}
		if (!is_port($_POST['pfb_dnsport_ssl'])) {
			$input_errors[] = 'DNSBL SSL Port is invalid!';
		}

		// Non-ascii characters are not allowed for DNSBL Regex
		if (!mb_detect_encoding($_POST['pfb_regex_list'], 'ASCII', TRUE)) {
			$input_errors[] = 'DNSBL Regex list contains non-ascii characters';
		}

		// Validate customlists
		foreach (array(	'pfb_regex_list'	=> 'regex',
				'pfb_noaaaa_list'	=> 'domain',
				'pfb_gp_bypass_list'	=> 'ip',
				'suppression'		=> 'domain',
				'tldexclusion'		=> 'hostname',
				'tldblacklist'		=> 'tld',
				'tldwhitelist'		=> 'tldwhite' ) as $custom_type => $custom_format) {

				if (!empty($_POST[$custom_type])) {
					$customlist = explode("\r\n", $_POST[$custom_type]);
					if (!empty($customlist)) {
						foreach ($customlist as $line) {

							if (substr($line, 0, 1) == '#' || empty($line)) {
								continue;
							}
							$value = array_map('trim', preg_split('/(?=#)/', $line));

							switch ($custom_format) {
								case 'regex':
									// TODO (See non-ascii validation above)
									break;
								case 'hostname':
									$value[0] = trim($value[0], '.');
									if (empty(pfb_filter($value[0], PFB_FILTER_HOSTNAME, 'dnsbl'))) {
										$input_errors[] = "Customlist {$custom_type}: Invalid  Hostname entry: [ " . htmlspecialchars($line) . " ]";
									}
									break;
								case 'domain':
									$value[0] = trim($value[0], '.');
									if (empty(pfb_filter($value[0], PFB_FILTER_DOMAIN, 'dnsbl'))) {
										$input_errors[] = "Customlist {$custom_type}: Invalid Domain name entry: [ " . htmlspecialchars($line) . " ]";
									}
									break;
								case 'ip':
									if (empty(pfb_filter($value[0], PFB_FILTER_IP, 'dnsbl'))) {
										$input_errors[] = "Customlist {$custom_type}: Invalid IP entry: [ " . htmlspecialchars($line) . " ]";
									}
									break;
								case 'tld':
									if (empty(pfb_filter($value[0], PFB_FILTER_TLD, 'dnsbl'))) {
										$input_errors[] = "Customlist {$custom_type}: Invalid TLD entry: [ " . htmlspecialchars($line) . " ]";
									}
									break;
								case 'tldwhite':
									if (strpos($value[0], '|') !== FALSE) {
										list($value[0], $host) = array_map('trim', explode('|', $value[0]));
										if (empty(pfb_filter($host, PFB_FILTER_IP, 'dnsbl'))) {
											$input_errors[] = "Customlist {$custom_type}: Invalid TLD IP entry: [ " . htmlspecialchars($line) . " ]"; 
										}
									}
									if (empty(pfb_filter($value[0], PFB_FILTER_TLD, 'dnsbl'))) {
										$input_errors[] = "Customlist {$custom_type}: Invalid TLD entry: [ " . htmlspecialchars($line) . " ]";
									}
									break;
								default:
									break;
							}
						}
					}
				}
		}

		// Validate DNSBL VIP address
		if ($_POST['pfb_dnsvip4'] == 'none') {
			$_POST['pfb_dnsvip4'] = '';
		}
		if ($_POST['pfb_dnsvip6'] == 'none') {
			$_POST['pfb_dnsvip6'] = '';
		}
		list($vips_valid, $error) = pfb_validate_vips($_POST['dnsbl_interface'], $_POST['pfb_dnsvip4'], $_POST['pfb_dnsvip6']);
		if (!$vips_valid) {
			$input_errors[] = "DNSBL: {$error}";
		}

		// Validate Adv. firewall rule 'Protocol' setting
		if (!empty($_POST['autoports_in']) || !empty($_POST['autoaddr_in'])) {
			if (empty($_POST['autoproto_in']) || $_POST['autoproto_in'] == 'any') {
				$input_errors[] = "Settings: Protocol setting cannot be set to 'Any' with Advanced Inbound firewall rule settings.";
			}
		}
		if (!empty($_POST['autoports_out']) || !empty($_POST['autoaddr_out'])) {
			if (empty($_POST['autoproto_out']) || $_POST['autoproto_out'] == 'any') {
				$input_errors[] = "Settings: Protocol setting cannot be set to 'Any' with Advanced Outbound firewall rule settings.";
			}
		}

		if (!$input_errors) {

			$pfb['dconfig']['pfb_dnsbl']		= pfb_filter($_POST['pfb_dnsbl'], PFB_FILTER_ON_OFF, 'dnsbl')		?: '';
			$pfb['dconfig']['pfb_tld']		= pfb_filter($_POST['pfb_tld'], PFB_FILTER_ON_OFF, 'dnsbl')		?: '';
			$pfb['dconfig']['pfb_control']		= pfb_filter($_POST['pfb_control'], PFB_FILTER_ON_OFF, 'dnsbl')		?: '';
			$pfb['dconfig']['pfb_dnsvip6'] = $_POST['pfb_dnsvip6'] ?: '';

			$pfb['dconfig']['pfb_dnsport']		= $_POST['pfb_dnsport']							?: '8081';
			$pfb['dconfig']['pfb_dnsport_ssl']	= $_POST['pfb_dnsport_ssl']						?: '8443';
			$pfb['dconfig']['pfb_dnsbl_rule']	= pfb_filter($_POST['pfb_dnsbl_rule'], PFB_FILTER_ON_OFF, 'dnsbl')	?: '';
			$pfb['dconfig']['dnsbl_allow_int']	= implode(',', (array)$_POST['dnsbl_allow_int'])			?: '';
			$pfb['dconfig']['global_log']		= $_POST['global_log']							?: '';
			$pfb['dconfig']['pfb_cache']		= pfb_filter($_POST['pfb_cache'], PFB_FILTER_ON_OFF, 'dnsbl')		?: '';
			$pfb['dconfig']['pfb_dnsbl_sync']	= pfb_filter($_POST['pfb_dnsbl_sync'], PFB_FILTER_ON_OFF, 'dnsbl')	?: '';

			$pfb['dconfig']['pfb_py_reply']		= pfb_filter($_POST['pfb_py_reply'], PFB_FILTER_ON_OFF, 'dnsbl')	?: '';
			$pfb['dconfig']['pfb_hsts']		= pfb_filter($_POST['pfb_hsts'], PFB_FILTER_ON_OFF, 'dnsbl')		?: '';
			$pfb['dconfig']['pfb_idn']		= pfb_filter($_POST['pfb_idn'], PFB_FILTER_ON_OFF, 'dnsbl')		?: '';
			$pfb['dconfig']['pfb_regex']		= pfb_filter($_POST['pfb_regex'], PFB_FILTER_ON_OFF, 'dnsbl')		?: '';
			$pfb['dconfig']['pfb_cname']		= pfb_filter($_POST['pfb_cname'], PFB_FILTER_ON_OFF, 'dnsbl')		?: '';
			$pfb['dconfig']['pfb_noaaaa']		= pfb_filter($_POST['pfb_noaaaa'], PFB_FILTER_ON_OFF, 'dnsbl')		?: '';
			$pfb['dconfig']['pfb_gp']		= pfb_filter($_POST['pfb_gp'], PFB_FILTER_ON_OFF, 'dnsbl')		?: '';

			$pfb['dconfig']['pfb_pytld']		= pfb_filter($_POST['pfb_pytld'], PFB_FILTER_ON_OFF, 'dnsbl')		?: '';
			$pfb['dconfig']['pfb_pytld_sort']	= pfb_filter($_POST['pfb_pytld_sort'], PFB_FILTER_ON_OFF, 'dnsbl')	?: '';
			$pfb['dconfig']['pfb_py_nolog']		= pfb_filter($_POST['pfb_py_nolog'], PFB_FILTER_ON_OFF, 'dnsbl')	?: '';

			// Python TLD Allow (Add default TLD Allows + ARPA + pfSense TLD
			if (!empty($_POST['pfb_pytlds_gtld'])) {
				$pfb['dconfig']['pfb_pytlds_gtld']	= "arpa,{$local_tld}," . implode(',', (array)$_POST['pfb_pytlds_gtld']); 
			} else {
				$pfb['dconfig']['pfb_pytlds_gtld']	= implode(',', $default_tlds);
			}

			// Python TLD Allow
			$pfb['dconfig']['pfb_pytlds_cctld']	= implode(',', (array)$_POST['pfb_pytlds_cctld']);
			$pfb['dconfig']['pfb_pytlds_itld']	= implode(',', (array)$_POST['pfb_pytlds_itld']);
			$pfb['dconfig']['pfb_pytlds_bgtld']	= implode(',', (array)$_POST['pfb_pytlds_bgtld']);

			$pfb['dconfig']['action']		= $_POST['action']							?: 'Disabled';
			$pfb['dconfig']['aliaslog']		= $_POST['aliaslog']							?: 'enabled';

			$pfb['dconfig']['autoaddrnot_in']	= pfb_filter($_POST['autoaddrnot_in'], PFB_FILTER_ON_OFF, 'dnsbl')	?: '';
			$pfb['dconfig']['autoports_in']		= pfb_filter($_POST['autoports_in'], PFB_FILTER_ON_OFF, 'dnsbl')	?: '';
			$pfb['dconfig']['aliasports_in']	= $_POST['aliasports_in']						?: '';
			$pfb['dconfig']['autoaddr_in']		= pfb_filter($_POST['autoaddr_in'], PFB_FILTER_ON_OFF, 'dnsbl')		?: '';
			$pfb['dconfig']['autonot_in']		= pfb_filter($_POST['autonot_in'], PFB_FILTER_ON_OFF, 'dnsbl')		?: '';
			$pfb['dconfig']['aliasaddr_in']		= $_POST['aliasaddr_in']						?: '';
			$pfb['dconfig']['autoproto_in']		= $_POST['autoproto_in']						?: 'any';
			$pfb['dconfig']['agateway_in']		= $_POST['agateway_in']							?: 'default';

			$pfb['dconfig']['autoaddrnot_out']	= pfb_filter($_POST['autoaddrnot_out'], PFB_FILTER_ON_OFF, 'dnsbl')	?: '';
			$pfb['dconfig']['autoports_out']	= pfb_filter($_POST['autoports_out'], PFB_FILTER_ON_OFF, 'dnsbl')	?: '';
			$pfb['dconfig']['aliasports_out']	= $_POST['aliasports_out']						?: '';
			$pfb['dconfig']['autoaddr_out']		= pfb_filter($_POST['autoaddr_out'], PFB_FILTER_ON_OFF, 'dnsbl')	?: '';
			$pfb['dconfig']['autonot_out']		= pfb_filter($_POST['autonot_out'], PFB_FILTER_ON_OFF, 'dnsbl')		?: '';
			$pfb['dconfig']['aliasaddr_out']	= $_POST['aliasaddr_out']						?: '';
			$pfb['dconfig']['autoproto_out']	= $_POST['autoproto_out']						?: 'any';
			$pfb['dconfig']['agateway_out']		= $_POST['agateway_out']						?: 'default';

			$pfb['dconfig']['alexa_enable']		= pfb_filter($_POST['alexa_enable'], PFB_FILTER_ON_OFF, 'dnsbl')	?: '';

			$pfb['dconfig']['pfb_regex_list']	= base64_encode($_POST['pfb_regex_list'])				?: '';
			$pfb['dconfig']['pfb_noaaaa_list']	= base64_encode($_POST['pfb_noaaaa_list'])				?: '';
			$pfb['dconfig']['pfb_gp_bypass_list']	= base64_encode($_POST['pfb_gp_bypass_list'])				?: '';
			$pfb['dconfig']['suppression']		= base64_encode($_POST['suppression'])					?: '';
			$pfb['dconfig']['tldexclusion']		= base64_encode($_POST['tldexclusion'])					?: '';
			$pfb['dconfig']['tldblacklist']		= base64_encode($_POST['tldblacklist'])					?: '';
			$pfb['dconfig']['tldwhitelist']		= base64_encode($_POST['tldwhitelist'])					?: '';

			// Reset TOP1M Database/Whitelist on user changes
			if ($pfb['dconfig']['alexa_type'] != $_POST['alexa_type']) {
				unlink_if_exists("{$pfb['dbdir']}/top-1m.csv");
				unlink_if_exists("{$pfb['dbdir']}/pfbalexawhitelist.txt");
			}
			$pfb['dconfig']['alexa_type']		= $_POST['alexa_type']							?: 'tranco';

			// Reset TOP1M Whitelist on user changes
			if ($pfb['dconfig']['alexa_count'] != $_POST['alexa_count'] ||
				explode(',', $pfb['dconfig']['alexa_inclusion']) != $_POST['alexa_inclusion']) {
				unlink_if_exists("{$pfb['dbdir']}/pfbalexawhitelist.txt");
			}
			$pfb['dconfig']['alexa_count']		= $_POST['alexa_count']							?: '1000';

			// Remove DNSBL blocking files, when user changes blocking mode
			if (($pfb['dconfig']['pfb_py_block'] != $_POST['pfb_py_block']) ||
			    ($pfb['dconfig']['dnsbl_mode'] !== $_POST['dnsbl_mode'] && $_POST['pfb_py_block'] == 'on')) {

				unlink_if_exists("{$pfb['dnsbl_file']}.conf");
				unlink_if_exists($pfb['unbound_py_data']);
				unlink_if_exists($pfb['unbound_py_zone']);
				unlink_if_exists($pfb['unbound_py_wh']);
				unlink_if_exists("{$pfb['dbdir']}/pfbalexawhitelist.txt");
				$savemsg = "A Force Reload DNSBL must be run to complete the blocking mode changes! The previous DNSBL database has been deleted!";
			}
			$pfb['dconfig']['pfb_py_block']		= pfb_filter($_POST['pfb_py_block'], PFB_FILTER_ON_OFF, 'dnsbl')	?: '';
			$pfb['dconfig']['dnsbl_mode']		= $_POST['dnsbl_mode']							?: 'dnsbl_unbound';

			$pfb['dconfig']['pfb_dnsvip4']		= $_POST['pfb_dnsvip4']							?: '';
			$pfb['dconfig']['dnsbl_interface']	= $_POST['dnsbl_interface']						?: 'lo0';

			// Replace DNSBL active blocked webpage with user selection
			if ($dnsbl_webpage || !file_exists('/usr/local/www/pfblockerng/www/dnsbl_active.php')) {
				@copy("/usr/local/www/pfblockerng/www/{$pfb['dconfig']['dnsbl_webpage']}", '/usr/local/www/pfblockerng/www/dnsbl_active.php');
			}

			config_set_path('installedpackages/pfblockerngdnsblsettings/config/0', $pfb['dconfig']);
			write_config('[pfBlockerNG] save DNSBL settings');
			if ($savemsg) {
				header("Location: /pfblockerng/pfblockerng_dnsbl.php?savemsg={$savemsg}");
			} else {
				header('Location: /pfblockerng/pfblockerng_dnsbl.php');
			}
			exit;
		}
		else {
			$pconfig = $_POST;	// Restore $_POST data on input errors
		}
	}
}

$pgtitle = array(gettext('Firewall'), gettext('pfBlockerNG'), gettext('DNSBL'));
$pglinks = array('', '/pfblockerng/pfblockerng_dnsbl.php', '@self');
include_once('head.inc');

if ($input_errors) {
	print_input_errors($input_errors);
}

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
$tab_array[]	= array(gettext('DNSBL Groups'),	false,		'/pfblockerng/pfblockerng_category.php?type=dnsbl');
$tab_array[]	= array(gettext('DNSBL Category'),	false,		'/pfblockerng/pfblockerng_blacklist.php');
$tab_array[]	= array(gettext('DNSBL SafeSearch'),	false,		'/pfblockerng/pfblockerng_safesearch.php');
display_top_tabs($tab_array, true);

if (isset($_REQUEST['savemsg'])) {
	$savemsg = htmlspecialchars($_REQUEST['savemsg']);
	print_info_box($savemsg);
}

$form = new Form('Save DNSBL settings');

$section = new Form_Section('DNSBL');
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
			where an instance of Lighttpd Web Server will collect the packet statistics and push a \'1x1\' GIF image to the Browser.<br />
			If the blocked domain is a root domain, a customizable Blocked Webpage will be displayed to the user.<br /><br />

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
	'Enable DNSBL',
	$pconfig['pfb_dnsbl'] === 'on' ? true:false,
	'on'
))->setHelp('This will enable DNS Block List for Malicious and/or unwanted Adverts Domains<br />'
		. 'To Utilize, <strong>Unbound DNS Resolver</strong> must be enabled. Also ensure that pfBlockerNG is enabled.'
		. "{$dnsbl_text}"
);

$dnsbl_text = 'This is an <strong>Advanced process</strong> to determine if all Sub-Domains should be wildcard blocked for each listed Domain.<br />
		<span class="text-danger">Click infoblock</span> before enabling this feature!&emsp;
		<div id="dnsbl_unbound_tld_info" class="infoblock">

		<span class="dnsbl_unbound_tld"><strong>This Feature is not recommended for Low-Perfomance/Low-Memory installations!</strong><br /></span>
		<strong>Definition: TLD</strong> -
		&emsp;represents the last segment of a domain name. IE: example.com (TLD = com), example.uk.com (TLD = uk.com)<br /><br />

		<span class="dnsbl_unbound_tld">The \'Unbound Resolver Reloads\' can take several seconds or more to complete and may temporarily interrupt
		DNS Resolution until the Resolver has been fully Reloaded with the updated Domain changes.
		Consider updating the DNSBL Feeds <strong>\'Once per Day\'</strong>, if network issues arise.<br /><br /></span>

		When enabled and after all downloads for DNSBL Feeds have completed; TLD will process the Domains.<br />
		TLD uses a predetermined list of TLDs, to determine if the listed Domains should be wildcard blocked (Block all sub-Domains).<br />
		The predetermined TLD list can be found in &emsp;<u>/usr/local/pkg/pfblockerng/dnsbl_tld</u><br /><br />

		To exclude a TLD/Domain from the TLD process, add the TLD/Domain to the <strong>TLD Exclusion</strong> custom list:<br />
		&#8226&emsp;This only excludes the domain from the TLD process, it doesn\'t whitelist the domain.<br />
		&#8226&emsp;Only the specific Sub-Domains/Domains listed in the DNSBL Feeds will be blocked.<br />
		&#8226&emsp;A Force Reload - DNSBL, is required after manually adding to the TLD Exclusion<br /><br />
		<strong>Note:</strong>
		&emsp;Whitelisting a "sub-Domain" for a TLD Blocked "Domain" in the <strong>Custom Domain Whitelist</strong>
		will not whitelist a TLD Wildcard Blocked domain!<br />
		&emsp;&emsp;&emsp;&emsp;Either add the domain to the TLD Exclusion, or wildcard Whitelist the whole domain.<br /><br />

		<strong>TLD Blacklist</strong>, can be used to block whole TLDs. &emsp;IE: <strong>xyz</strong><br />
		<span class="dnsbl_unbound_tld"><strong>TLD Whitelist</strong> is <strong><u>only</u></strong> used in conjunction with
		<strong> TLD Blacklist</strong> and is used to allow access to a Domain that is being blocked by a TLD Blacklist.<br /><br /></span>

		When Enabling/Disabling this option, a <strong>Force Reload - DNSBL</strong> is required.<br /><br />

		<span class="dnsbl_unbound_tld">Once the TLD Domain limit below is exceeded, the balance of the Domains will be listed as-is.
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
		</ul></span>
	</div>';

$section->addInput(new Form_Select(
	'dnsbl_mode',
	gettext('DNSBL Mode'),
	$pconfig['dnsbl_mode'],
	$options_dnsbl_mode
))->setHelp('Select the DNSBL mode.&emsp;'
		. '<div class="infoblock">'
		. '<strong>Unbound Mode</strong>:<br />'
		. '&emsp;&emsp;&emsp;&emsp;This mode will utilize Unbound local-zone/local-data entries for DNSBL (requires more memory).<br />'
		. '<strong>Unbound Python Mode</strong>:<br />'
		. '&emsp;&emsp;&emsp;&emsp;This mode is only available for pfSense version 2.4.5 and above.<br />'
		. '&emsp;&emsp;&emsp;&emsp;This mode will utilize the python integration of Unbound for DNSBL.<br />'
		. '&emsp;&emsp;&emsp;&emsp;This mode will allow logging of DNS Replies, and more advanced DNSBL Blocking features.<br />'
		. '&emsp;&emsp;&emsp;&emsp;This mode requires substantially less memory </div>'
);

$section->addInput(new Form_Checkbox(
	'pfb_tld',
	gettext('Wildcard Blocking (TLD)'),
	'Enable',
	$pconfig['pfb_tld'] === 'on' ? true:false,
	'on'
))->setHelp($dnsbl_text);

$section->addInput(new Form_Checkbox(
	'pfb_control',
	gettext('Python Control') . '(py)',
	'Enable',
	$pconfig['pfb_control'] === 'on' ? true:false,
	'on'
))->setHelp('Enabling this option will allow sending python_control commands (via DNS TXT) to the Python integration.'
	. '<div class="infoblock" style="width: 90%;">'
	. 'The python_control feature is limited to DNS TXT records sent from pfSense localhost (127.0.0.1) only!<br />'
	. 'This is a temporary intervention, and will be reset on a restart of the Resolver<br />'
	. 'These commands can be incorporated in CRON/Scheduler tasks or run manually as required<br />'
	. 'All events are logged to the Reports Tab (Gear icon)<br /><br />'
	. '<strong>Command Syntax:</strong><br />'
	. '<table class="table table-bordered table-striped table-hover table-compact">'
	. '	<thead>'
	. '		<tr>'
	. '			<th style="max-width: 10%">Description</th>'
	. '			<th style="max-width: 85%">Command</th>'
	. '			<th style="max-width: 5%">Notes</th>'
	. '		</tr>'
	. '	</thead>'
	. '	<tbody>'
	. '		<tr><td>Enable DNSBL</td><td>drill TXT python_control.enable</td><td></td></tr>'
	. '		<tr><td>Disable DNSBL</td><td>drill TXT python_control.disable</td><td></td></tr>'
	. '		<tr><td>Disable DNSBL for duration</td><td>drill TXT python_control.disable.seconds</td><td>Seconds: 1-3600</td></tr>'
	. '		<tr><td>Add a Global bypass IP</td><td>drill TXT python_control.addbypass.1-2-3-4</td><td>Use "-" in place of "."</td></tr>'
	. '		<tr><td>Add a Global bypass IP for duration</td><td>drill TXT python_control.addbypass.1-2-3-4.seconds</td><td>Seconds: 1-3600</td></tr>'
	. '		<tr><td>Remove a Global bypass IP</td><td>;drill TXT python_control.removebypass.1-2-3-4</td><td>Use "-" in place of "."</td></tr>'
	. '	</tbody>'
	. '</table>'
	. '</div>');

$section->addInput(new Form_Checkbox(
	'pfb_py_reply',
	gettext('DNS Reply Logging') . '(py)',
	'Enable',
	$pconfig['pfb_py_reply'] === 'on' ? true:false,
	'on'
))->setHelp('Enable the logging of all DNS Replies that were not blocked via DNSBL.');

$section->addInput(new Form_Checkbox(
	'pfb_py_block',
	gettext('DNSBL Blocking') . '(py)',
	'Enable',
	$pconfig['pfb_py_block'] === 'on' ? true:false,
	'on'
))->setHelp('Enable the DNSBL python blocking mode.<div class="infoblock">'
	. '<strong>DNSBL python blocking order</strong>:<br /><br />'
	. '1) DNSBL python blocking mode option (Block any domains listed in the Feeds via DNSBL/TLD/DNSBL_TLD)<br />'
	. '2) TLD Allow option (Only allow these TLDs to the next validation steps)<br />'
	. '3) IDN Blocking option (Block any IDN domain or IDNs in punycode (ascii) format)<br />'
	. '4) Regex Blocking option (User defined regular expression rules)<br /><br />'
	. 'Blocked events (#2-4) will be <strong title="Utilizes 0.0.0.0 instead of the DNSBL VIP">Null Blocked</strong> and reported in the python log</div>'
);

$section->addInput(new Form_Checkbox(
	'pfb_dnsbl_sync',
	gettext('Resolver Live Sync'),
	'Enable',
	$pconfig['pfb_dnsbl_sync'] === 'on' ? true:false,
	'on'
))->setHelp('When enabled, updates to the DNS Resolver DNSBL database will be performed Live without reloading the Resolver.<br />'
	. 'This will allow for more frequent DNSBL Updates (ie: Hourly) without losing DNS Resolution.<br />'
	. 'This option is not required when DNSBL python blocking mode is enabled.<br />'
	. '<span class="text-danger">Note:</span> A Force Reload will run a full Reload of Unbound');

$section->addInput(new Form_Checkbox(
	'pfb_hsts',
	gettext('HSTS mode') . '(py)',
	'Enable',
	$pconfig['pfb_hsts'] === 'on' ? true:false,
	'on'
))->setHelp('Enable the DNSBL python <strong title="Utilizes 0.0.0.0 instead of the DNSBL VIP">Null Block mode</strong> for HSTS domains.<br />'
	. 'Blocked domains that are in the <a target=_"blank" href="https://hstspreload.org/">HSTS preload</a> browser'
	. ' <a target=_"blank" href="https://raw.githubusercontent.com/chromium/chromium/master/net/http/transport_security_state_static.json">list</a>'
	. ' will use the Null Block Mode which *may* prevent Browser Certificate Errors.<br />'
	. '<span class="text-danger">Note:</span> This option will not block HSTS domains, unless those Domains are added via the Feeds/Customlists.'
);


$tld_list = array();

$tld_list['gTLD'] = array(
'com' => 'COM* [149,657,691]',
'net' => 'NET* [15,033,024]',
'org' => 'ORG* [11,435,695]',
'edu' => 'EDU* [7,470]',
'info' => 'INFO [6,598,400]',
'top' => 'TOP [4,054,352]',
'xyz' => 'XYZ [2,574,052]',
'biz' => 'BIZ [2,526,989]',
'loan' => 'LOAN [2,247,507]',
'club' => 'CLUB [1,696,900]',
'online' => 'ONLINE [1,263,447]',
'site' => 'SITE [1,056,019]',
'vip' => 'VIP [789,039]',
'ltd' => 'LTD [728,146]',
'shop' => 'SHOP [611,909]',
'win' => 'WIN [585,396]',
'mobi' => '(s) MOBI [456,722]',
'men' => 'MEN [419,738]',
'icu' => 'ICU [400,885]',
'app' => '(s) APP [357,664]',
'website' => 'WEBSITE [338,254]',
'pro' => 'PRO [337,195]',
'space' => 'SPACE [331,935]',
'stream' => 'STREAM [319,026]',
'live' => 'LIVE [315,146]',
'wang' => 'WANG [314,891]',
'ooo' => 'OOO [313,861]',
'asia' => '(s) ASIA [293,486]',
'store' => 'STORE [266,600]',
'review' => 'REVIEW [261,201]',
'tech' => 'TECH [257,404]',
'life' => 'LIFE [228,176]',
'fun' => 'FUN [220,554]',
'blog' => 'BLOG [203,890]',
'cloud' => 'CLOUD [190,577]',
'trade' => 'TRADE [183,643]',
'xin' => 'XIN (HiChina) [181,417]',
'world' => 'WORLD [146,086]',
'host' => 'HOST [140,599]',
'name' => 'NAME [138,702]',
'download' => 'DOWNLOAD [126,393]',
'party' => 'PARTY [122,932]',
'today' => 'TODAY [115,480]',
'link' => 'LINK [114,350]',
'cat' => '(s) CAT [114,058]',
'rocks' => 'ROCKS [112,659]',
'ren' => 'REN [105,562]',
'ink' => 'INK [96,853]',
'science' => 'SCIENCE [95,819]',
'xxx' => '(s) XXX [93,139]',
'design' => 'DESIGN [91,689]',
'solutions' => 'SOLUTIONS [91,210]',
'email' => 'EMAIL [89,381]',
'tel' => '(s) TEL [85,247]',
'racing' => 'RACING [81,760]',
'london' => '(eu) LONDON [81,647]',
'services' => 'SERVICES [79,374]',
'one' => 'ONE [77,774]',
'group' => 'GROUP [74,396]',
'company' => 'COMPANY [74,050]',
'nyc' => '(na) NYC [72,512]',
'agency' => 'AGENCY [69,267]',
'news' => 'NEWS [68,258]',
'guru' => 'GURU [67,798]',
'accountant' => 'ACCOUNTANT [64,327]',
'business' => 'BUSINESS [63,099]',
'faith' => 'FAITH [62,665]',
'webcam' => 'WEBCAM [60,654]',
'network' => 'NETWORK [55,634]',
'berlin' => '(eu) BERLIN [55,555]',
'photography' => 'PHOTOGRAPHY [55,260]',
'click' => 'CLICK [52,054]',
'global' => 'GLOBAL [50,301]',
'realtor' => 'REALTOR [48,823]',
'media' => 'MEDIA [48,757]',
'studio' => 'STUDIO [48,541]',
'press' => 'PRESS [48,070]',
'jobs' => '(s) JOBS [46,762]',
'art' => 'ART [44,811]',
'center' => 'CENTER [43,477]',
'technology' => 'TECHNOLOGY [40,419]',
'digital' => 'DIGITAL [38,904]',
'expert' => 'EXPERT [37,333]',
'ninja' => 'NINJA [37,251]',
'tips' => 'TIPS [34,474]',
'bayern' => '(eu) BAYERN [33,954]',
'sale' => 'SALE [32,675]',
'city' => 'CITY [32,350]',
'amsterdam' => '(eu) AMSTERDAM [31,843]',
'red' => 'RED [31,790]',
'love' => 'LOVE (Merchant Law Group) [31,654]',
'systems' => 'SYSTEMS [31,371]',
'academy' => 'ACADEMY [31,073]',
'wedding' => 'WEDDING [30,890]',
'koeln' => '(eu) KOELN [29,465]',
'market' => 'MARKET [29,354]',
'international' => 'INTERNATIONAL [27,475]',
'aero' => '(s) AERO [27,425]',
'zone' => 'ZONE [27,017]',
'consulting' => 'CONSULTING [26,981]',
'social' => 'SOCIAL [26,253]',
'cricket' => 'CRICKET [25,881]',
'events' => 'EVENTS [25,666]',
'team' => 'TEAM [25,420]',
'church' => 'CHURCH [25,243]',
'support' => 'SUPPORT [25,071]',
'education' => 'EDUCATION [24,697]',
'hamburg' => '(eu) HAMBURG [24,531]',
'pub' => 'PUB [24,366]',
'photos' => 'PHOTOS [22,978]',
'care' => 'CARE [22,594]',
'marketing' => 'MARKETING [22,396]',
'boston' => '(na) BOSTON [22,334]',
'kim' => 'KIM [22,086]',
'paris' => '(eu) PARIS [21,767]',
'vegas' => '(na) VEGAS [21,698]',
'moscow' => '(eu) MOSCOW [21,694]',
'coffee' => 'COFFEE [21,222]',
'house' => 'HOUSE [21,012]',
'nrw' => '(eu) NRW [20,656]',
'kiwi' => '(oc) KIWI [20,358]',
'video' => 'VIDEO [20,297]',
'gmbh' => '(de) GMBH [20,290]',
'africa' => '(af) AFRICA [20,276]',
'reviews' => 'REVIEWS [20,165]',
'training' => 'TRAINING [20,014]',
'bet' => 'BET [19,996]',
'family' => 'FAMILY [19,735]',
'wiki' => 'WIKI [19,627]',
'travel' => '(s) TRAVEL [19,614]',
'realestate' => 'REALESTATE [19,596]',
'directory' => 'DIRECTORY [19,453]',
'photo' => 'PHOTO [19,409]',
'gallery' => 'GALLERY [19,383]',
'cool' => 'COOL [19,308]',
'guide' => 'GUIDE [18,987]',
'works' => 'WORKS [18,557]',
'swiss' => '(eu) SWISS [18,196]',
'bike' => 'BIKE [17,924]',
'games' => 'GAMES [17,777]',
'immo' => '(it) IMMO [17,774]',
'bio' => 'BIO [17,515]',
'uno' => '(es) UNO [16,973]',
'cash' => 'CASH [16,888]',
'cafe' => 'CAFE [16,739]',
'land' => 'LAND [16,645]',
'farm' => 'FARM [16,559]',
'fit' => 'FIT [16,439]',
'miami' => '(na) MIAMI [15,928]',
'software' => 'SOFTWARE [15,711]',
'properties' => 'PROPERTIES [15,658]',
'wien' => '(eu) WIEN [15,656]',
'run' => 'RUN [15,586]',
'capital' => 'CAPITAL [15,518]',
'coach' => 'COACH [15,442]',
'fund' => 'FUND [15,201]',
'community' => 'COMMUNITY [15,068]',
'frl' => '(eu) FRL [15,055]',
'wine' => 'WINE [14,904]',
'band' => 'BAND [14,487]',
'fashion' => 'FASHION [14,172]',
'help' => 'HELP [14,080]',
'fyi' => 'FYI [14,056]',
'blue' => 'BLUE [13,911]',
'school' => 'SCHOOL [13,857]',
'foundation' => 'FOUNDATION [13,843]',
'wales' => '(eu) WALES [13,787]',
'legal' => 'LEGAL [13,769]',
'wtf' => 'WTF [13,587]',
'exchange' => 'EXCHANGE [13,501]',
'beer' => 'BEER [13,335]',
'istanbul' => '(eu) ISTANBUL [13,218]',
'chat' => 'CHAT [13,115]',
'tools' => 'TOOLS [13,040]',
'plus' => 'PLUS [13,013]',
'rentals' => 'RENTALS [12,927]',
'ventures' => 'VENTURES [12,900]',
'clothing' => 'CLOTHING [12,802]',
'lawyer' => 'LAWYER [12,713]',
'direct' => 'DIRECT [12,605]',
'money' => 'MONEY [12,465]',
'dog' => 'DOG [12,424]',
'style' => 'STYLE [12,376]',
'institute' => 'INSTITUTE [12,369]',
'scot' => '(eu) SCOT [12,283]',
'energy' => 'ENERGY [12,167]',
'management' => 'MANAGEMENT [11,786]',
'law' => 'LAW [11,784]',
'pet' => 'PET [11,633]',
'fitness' => 'FITNESS [11,606]',
'tours' => 'TOURS [11,381]',
'buzz' => 'BUZZ [10,992]',
'porn' => 'PORN [10,811]',
'ist' => '(eu) IST [10,759]',
'cologne' => '(eu) COLOGNE [10,704]',
'gold' => 'GOLD [10,599]',
'earth' => 'EARTH [10,541]',
'estate' => 'ESTATE [10,498]',
'show' => 'SHOW [10,376]',
'melbourne' => '(oc) MELBOURNE [10,358]',
'jetzt' => '(de) JETZT [10,089]',
'ruhr' => '(eu) RUHR [10,062]',
'sydney' => '(oc) SYDNEY [10,000]',
'golf' => 'GOLF [9,830]',
'yoga' => 'YOGA [9,821]',
'boutique' => 'BOUTIQUE [9,813]',
'watch' => 'WATCH [9,736]',
'pictures' => 'PICTURES [9,602]',
'healthcare' => 'HEALTHCARE [9,465]',
'deals' => 'DEALS [9,393]',
'eus' => '(eu) EUS [9,392]',
'restaurant' => 'RESTAURANT [9,280]',
'health' => 'HEALTH [9,263]',
'quebec' => '(na) QUEBEC [9,254]',
'finance' => 'FINANCE [9,007]',
'moe' => 'MOE [8,876]',
'adult' => 'ADULT [8,870]',
'codes' => 'CODES [8,611]',
'sport' => 'SPORT (Global Assoc. of Sports Fed.) [8,582]',
'express' => 'EXPRESS [8,460]',
'bzh' => '(af) BZH [8,365]',
'page' => 'PAGE [8,279]',
'clinic' => 'CLINIC [8,218]',
'sex' => 'SEX [8,186]',
'dental' => 'DENTAL [8,175]',
'pink' => 'PINK [8,168]',
'coop' => '(s) COOP [8,115]',
'partners' => 'PARTNERS [8,110]',
'careers' => 'CAREERS [8,040]',
'sucks' => 'SUCKS [8,000]',
'attorney' => 'ATTORNEY [7,988]',
'reisen' => '(de) REISEN [7,958]',
'dance' => 'DANCE [7,932]',
'lol' => 'LOL [7,810]',
'productions' => 'PRODUCTIONS [7,773]',
'brussels' => '(eu) BRUSSELS [7,694]',
'kaufen' => '(de) KAUFEN [7,679]',
'cymru' => '(eu) CYMRU [7,668]',
'graphics' => 'GRAPHICS [7,567]',
'shopping' => 'SHOPPING [7,490]',
'construction' => 'CONSTRUCTION [7,489]',
'vision' => 'VISION [7,488]',
'repair' => 'REPAIR [7,454]',
'university' => 'UNIVERSITY [7,441]',
'sexy' => 'SEXY [7,399]',
'nagoya' => '(asia) NAGOYA [7,380]',
'solar' => 'SOLAR [7,330]',
'pizza' => 'PIZZA [7,315]',
'promo' => 'PROMO [7,307]',
'kitchen' => 'KITCHEN [7,218]',
'immobilien' => '(de) IMMOBILIEN [7,189]',
'domains' => 'DOMAINS [7,138]',
'cam' => 'CAM [7,108]',
'pics' => 'PICS [7,074]',
'enterprises' => 'ENTERPRISES [7,065]',
'report' => 'REPORT [7,063]',
'tax' => 'TAX [6,941]',
'vet' => 'VET [6,923]',
'taxi' => 'TAXI [6,807]',
'engineering' => 'ENGINEERING [6,710]',
'camp' => 'CAMP [6,600]',
'lighting' => 'LIGHTING [6,526]',
'place' => 'PLACE [6,521]',
'vlaanderen' => '(eu) VLAANDEREN [6,498]',
'tirol' => '(eu) TIROL [6,478]',
'cards' => 'CARDS [6,456]',
'delivery' => 'DELIVERY [6,415]',
'casa' => '(it) CASA [6,413]',
'audio' => 'AUDIO [6,358]',
'parts' => 'PARTS [6,264]',
'gift' => 'GIFT [6,258]',
'haus' => '(de) HAUS [6,205]',
'bar' => 'BAR [6,169]',
'holdings' => 'HOLDINGS [6,075]',
'yokohama' => '(asia) YOKOHAMA [6,044]',
'limited' => 'LIMITED [5,995]',
'equipment' => 'EQUIPMENT [5,970]',
'barcelona' => '(eu) BARCELONA [5,919]',
'vin' => 'VIN [5,870]',
'gov' => 'GOV [5,838]',
'holiday' => 'HOLIDAY [5,680]',
'menu' => 'MENU [5,664]',
'credit' => 'CREDIT [5,655]',
'football' => 'FOOTBALL [5,642]',
'ski' => 'SKI [5,636]',
'computer' => 'COMPUTER [5,605]',
'srl' => 'SRL (InterNetx) [5,595]',
'financial' => 'FINANCIAL [5,506]',
'supply' => 'SUPPLY [5,414]',
'doctor' => 'DOCTOR [5,413]',
'shoes' => 'SHOES [5,387]',
'green' => 'GREEN [5,360]',
'toys' => 'TOYS [5,186]',
'insure' => 'INSURE [5,184]',
'casino' => 'CASINO [5,150]',
'investments' => 'INVESTMENTS [5,119]',
'gal' => '(eu) GAL [5,050]',
'gratis' => '(es) GRATIS [5,001]',
'capetown' => '(af) CAPETOWN [4,990]',
'rent' => 'RENT [4,933]',
'recipes' => 'RECIPES [4,885]',
'fish' => 'FISH [4,825]',
'camera' => 'CAMERA [4,778]',
'llc' => 'LLC [4,710]',
'onl' => 'ONL [4,658]',
'best' => 'BEST [4,638]',
'black' => 'BLACK [4,581]',
'discount' => 'DISCOUNT [4,577]',
'schule' => '(de) SCHULE [4,536]',
'vacations' => 'VACATIONS [4,493]',
'auction' => 'AUCTION [4,442]',
'industries' => 'INDUSTRIES [4,393]',
'mortgage' => 'MORTGAGE [4,314]',
'hosting' => 'HOSTING [4,301]',
'irish' => '(eu) IRISH [4,252]',
'gifts' => 'GIFTS [4,236]',
'film' => 'FILM [4,233]',
'fail' => 'FAIL [4,208]',
'saarland' => '(eu) SAARLAND [4,206]',
'builders' => 'BUILDERS [4,200]',
'contractors' => 'CONTRACTORS [4,155]',
'build' => 'BUILD [4,154]',
'apartments' => 'APARTMENTS [4,082]',
'town' => 'TOWN [4,066]',
'property' => 'PROPERTY [4,023]',
'college' => 'COLLEGE [3,987]',
'okinawa' => '(asia) OKINAWA [3,965]',
'catering' => 'CATERING [3,924]',
'organic' => 'ORGANIC [3,921]',
'taipei' => '(asia) TAIPEI [3,892]',
'singles' => 'SINGLES [3,886]',
'glass' => 'GLASS [3,866]',
'associates' => 'ASSOCIATES [3,848]',
'dentist' => 'DENTIST [3,817]',
'cheap' => 'CHEAP [3,804]',
'jewelry' => 'JEWELRY [3,792]',
'mba' => 'MBA [3,758]',
'poker' => 'POKER [3,746]',
'moda' => '(it) MODA [3,736]',
'bargains' => 'BARGAINS [3,692]',
'rip' => 'RIP [3,677]',
'exposed' => 'EXPOSED [3,611]',
'gent' => '(eu) GENT [3,579]',
'lat' => '(s) LAT [3,536]',
'supplies' => 'SUPPLIES [3,456]',
'dating' => 'DATING [3,433]',
'joburg' => '(af) JOBURG [3,407]',
'cab' => 'CAB [3,396]',
'soccer' => 'SOCCER [3,370]',
'ngo' => 'NGO [3,295]',
'ong' => 'ONG [3,284]',
'engineer' => 'ENGINEER [3,193]',
'vote' => 'VOTE [3,166]',
'luxe' => '(fr) LUXE [3,135]',
'voyage' => 'VOYAGE [3,109]',
'movie' => 'MOVIE [3,108]',
'plumbing' => 'PLUMBING [3,103]',
'accountants' => 'ACCOUNTANTS [2,993]',
'claims' => 'CLAIMS [2,986]',
'tube' => 'TUBE [2,965]',
'futbol' => '(es) FUTBOL [2,942]',
'bank' => 'BANK [2,878]',
'ceo' => 'CEO [2,843]',
'surf' => 'SURF [2,839]',
'tienda' => '(es) TIENDA [2,818]',
'degree' => 'DEGREE [2,778]',
'diamonds' => 'DIAMONDS [2,774]',
'coupons' => 'COUPONS [2,738]',
'reise' => '(de) REISE [2,699]',
'feedback' => 'FEEDBACK [2,652]',
'cleaning' => 'CLEANING [2,592]',
'radio' => 'RADIO [2,587]',
'furniture' => 'FURNITURE [2,568]',
'mom' => 'MOM [2,561]',
'realty' => 'REALTY [2,545]',
'lgbt' => 'LGBT [2,530]',
'archi' => 'ARCHI [2,504]',
'horse' => 'HORSE [2,484]',
'florist' => 'FLORIST [2,471]',
'flights' => 'FLIGHTS [2,458]',
'durban' => '(af) DURBAN [2,425]',
'how' => 'HOW [2,425]',
'limo' => 'LIMO [2,409]',
'cruises' => 'CRUISES [2,386]',
'salon' => 'SALON (Outer Orchard LLC) [2,379]',
'actor' => 'ACTOR [2,352]',
'eco' => 'ECO [2,337]',
'army' => 'ARMY [2,269]',
'fan' => 'FAN [2,256]',
'garden' => 'GARDEN [2,252]',
'tattoo' => 'TATTOO [2,227]',
'condos' => 'CONDOS [2,168]',
'game' => 'GAME [2,122]',
'bot' => 'BOT [2,121]',
'alsace' => '(eu) ALSACE [2,117]',
'courses' => 'COURSES [2,113]',
'diet' => 'DIET [2,108]',
'rest' => 'REST [2,104]',
'villas' => 'VILLAS [2,070]',
'versicherung' => '(de) VERSICHERUNG [2,038]',
'surgery' => 'SURGERY [2,028]',
'lease' => 'LEASE [1,966]',
'viajes' => '(es) VIAJES [1,947]',
'gives' => 'GIVES [1,845]',
'homes' => 'HOMES [1,845]',
'rehab' => 'REHAB [1,821]',
'fans' => 'FANS [1,812]',
'tennis' => 'TENNIS [1,797]',
'cooking' => 'COOKING [1,761]',
'mil' => 'MIL [1,661]',
'fishing' => 'FISHING [1,637]',
'hospital' => 'HOSPITAL [1,616]',
'vodka' => 'VODKA [1,596]',
'christmas' => 'CHRISTMAS [1,580]',
'soy' => '(es) SOY [1,516]',
'hockey' => 'HOCKEY [1,479]',
'bible' => 'BIBLE [1,473]',
'study' => 'STUDY [1,457]',
'bingo' => 'BINGO [1,447]',
'flowers' => 'FLOWERS [1,427]',
'theater' => 'THEATER [1,374]',
'corsica' => '(eu) CORSICA [1,353]',
'career' => 'CAREER [1,333]',
'charity' => 'CHARITY [1,321]',
'gripe' => 'GRIPE [1,317]',
'blackfriday' => 'BLACKFRIDAY [1,289]',
'physio' => 'PHYSIO [1,233]',
'tires' => 'TIRES [1,227]',
'gop' => 'GOP [1,217]',
'democrat' => 'DEMOCRAT [1,135]',
'tickets' => 'TICKETS [1,124]',
'trading' => 'TRADING [1,118]',
'rio' => '(s) RIO [1,114]',
'guitars' => 'GUITARS [1,113]',
'maison' => '(fr) MAISON [1,084]',
'observer' => 'OBSERVER [1,054]',
'republican' => 'REPUBLICAN [1,015]',
'sarl' => 'SARL [997]',
'creditcard' => 'CREDITCARD [970]',
'hiphop' => 'HIPHOP [932]',
'voting' => 'VOTING [891]',
'luxury' => 'LUXURY [891]',
'tatar' => '(asia) TATAR [885]',
'markets' => 'MARKETS [879]',
'navy' => 'NAVY [857]',
'juegos' => '(es) JUEGOS [831]',
'kyoto' => '(asia) KYOTO [818]',
'memorial' => 'MEMORIAL [777]',
'ltda' => '(es) LTDA [736]',
'hoteles' => '(es) HOTELES [682]',
'osaka' => '(asia) OSAKA [676]',
'airforce' => 'AIRFORCE [663]',
'baby' => 'BABY [657]',
'abudhabi' => '(asia) SABUDHABI [633]',
'museum' => '(s) MUSEUM [585]',
'ryukyu' => '(asia) RYUKYU [556]',
'storage' => 'STORAGE [555]',
'pharmacy' => 'PHARMACY [537]',
'krd' => '(asia) KRD [518]',
'rodeo' => 'RODEO [496]',
'qpon' => 'QPON [481]',
'boats' => 'BOATS [467]',
'auto' => 'AUTO [436]',
'car' => 'CAR [375]',
'dev' => 'DEV [375]',
'voto' => '(it) VOTO [347]',
'hiv' => 'HIV [339]',
'cars' => 'CARS [336]',
'abogado' => '(es) ABOGADO [334]',
'security' => 'SECURITY [279]',
'broadway' => 'BROADWAY [268]',
'moi' => '(fr) MOI [253]',
'insurance' => 'INSURANCE [249]',
'rugby' => 'RUGBY [239]',
'makeup' => 'MAKEUP [229]',
'yachts' => 'YACHTS [210]',
'int' => '(s) INT [204]',
'reit' => 'REIT [126]',
'theatre' => 'THEATRE [113]',
'whoswho' => 'WHOSWHO [106]',
'rich' => 'RICH [91]',
'cancerresearch' => 'CANCERRESEARCH [85]',
'protection' => 'PROTECTION [84]',
'motorcycles' => 'MOTORCYCLES [75]',
'lotto' => 'LOTTO [72]',
'post' => '(s) POST [71]',
'autos' => 'AUTOS [71]',
'trust' => 'TRUST [53]',
'new' => 'NEW [45]',
'kpn' => 'KPN (KPN) [43]',
'nowruz' => 'NOWRUZ (Asia Green IT) [42]',
'aws' => 'AWS [40]',
'foo' => 'FOO [31]',
'stockholm' => '(eu) STOCKHOLM [28]',
'wed' => 'WED [20]',
'inc' => 'INC (Uniregistry) [14]',
'catholic' => 'CATHOLIC [13]',
'ice' => 'ICE [10]',
'skin' => 'SKIN [8]',
'active' => 'ACTIVE [8]',
'arte' => '(fr) ARTE [8]',
'meet' => 'MEET [7]',
'spreadbetting' => 'SPREADBETTING [7]',
'ismaili' => 'ISMAILI (Fondation Aga Khan) [6]',
'forum' => 'FORUM [6]',
'dubai' => '(asia) DUBAI [5]',
'cfd' => 'CFD [5]',
'pay' => 'PAY [5]',
'madrid' => '(eu) MADRID [4]',
'med' => 'MED [4]',
'now' => 'NOW [3]',
'duck' => 'DUCK [3]',
'analytics' => 'ANALYTICS [3]',
'here' => 'HERE [3]',
'book' => 'BOOK [3]',
'boo' => 'BOO [2]',
'ads' => 'ADS [2]',
'ubank' => 'UBANK (Bank Of Australia) [2]',
'you' => 'YOU [2]',
'hot' => 'HOT [2]',
'barefoot' => 'BAREFOOT [2]',
'frontdoor' => 'FRONTDOOR [2]',
'docs' => 'DOCS [2]',
'free' => 'FREE [2]',
'travelersinsurance' => 'TRAVELERSINSURANCE [2]',
'box' => 'BOX [2]',
'fly' => 'FLY [2]',
'esq' => 'ESQ [2]',
'vuelos' => '(es) VUELOS [2]',
'channel' => 'CHANNEL [2]',
'eat' => 'EAT [2]',
'dad' => 'DAD [2]',
'day' => 'DAY [2]',
'imamat' => 'IMAMAT (Fondation Aga Khan) [2]',
'fast' => 'FAST [2]',
'ing' => 'ING [2]',
'meme' => 'MEME [2]',
'prof' => 'PROF [2]',
'rsvp' => '(fr) RSVP [2]',
'mov' => 'MOV [2]',
'passagens' => 'PASSAGENS [2]',
'kosher' => 'KOSHER (Kosher Marketing) [1]',
'bestbuy' => 'BESTBUY [1]',
'author' => 'AUTHOR [1]',
'xihuan' => '(de) XIHUAN [1]',
'pars' => 'PARS (Asia Green) [1]',
'wow' => 'WOW [1]',
'safe' => 'SAFE [1]',
'origins' => 'ORIGINS [1]',
'baseball' => 'BASEBALL [1]',
'winners' => 'WINNERS [1]',
'bcn' => '(eu) BCN [1]',
'beauty' => 'BEAUTY [1]',
'blockbuster' => 'BLOCKBUSTER [1]',
'phone' => 'PHONE [1]',
'open' => 'OPEN [1]',
'weibo' => '(cn) WEIBO [1]',
'off' => 'OFF [1]',
'save' => 'SAVE [1]',
'scholarships' => 'SCHOLARSHIPS [1]',
'weather' => 'WEATHER [1]',
'watches' => 'WATCHES [1]',
'homesense' => 'HOMESENSE [1]',
'wanggou' => '(cn) WANGGOU [1]',
'search' => 'SEARCH [1]',
'audible' => 'AUDIBLE [1]',
'pid' => 'PID [1]',
'secure' => 'SECURE [1]',
'tkmaxx' => 'TKMAXX (TJX Companies) [1]',
'bom' => 'BOM [1]',
'toronto' => 'TORONTO [1]',
'hotels' => 'HOTELS [1]',
'shia' => 'SHIA (Asia Green) [1]',
'phd' => 'PHD (Charleston Road Registry) [1]',
'loft' => 'LOFT (Anco Inc.) [1]',
'prime' => 'PRIME [1]',
'viva' => 'VIVA (Saudi Tele - CentralNic) [1]',
'vana' => 'VANA (Lifetyle Domain Holdings) [1]',
'pin' => 'PIN [1]',
'tjmaxx' => 'TJMAXX (TJX Companies) [1]',
'arab' => '(asia) ARAB [1]',
'tiaa' => 'TIAA (Teachers Insurance & Assoc) [1]',
'srt' => 'SRT (FCA US LLC) [1]',
'samsclub' => 'SAMSCLUB (Walmart) [1]',
'ril' => 'RIL (Reliance Industries) [1]',
'rightathome' => 'RIGHTATHOME (Johnson & Johnson) [1]',
'zuerich' => '(eu) ZUERICH [1]',
'room' => 'ROOM [1]',
'zero' => 'ZERO [1]',
'yun' => 'YUN (QIHOO-Security) [1]',
'joy' => 'JOY [1]',
'anquan' => 'ANQUAN (QIHOO-Security) [1]',
'budapest' => '(eu) BUDAPEST [1]',
'buy' => 'BUY [1]',
'talk' => 'TALK [1]',
'living' => 'LIVING [1]',
'dot' => 'DOT [1]',
'drive' => 'DRIVE [1]',
'map' => 'MAP [1]',
'sling' => 'SLING [1]',
'smile' => 'SMILE [1]',
'epost' => '(de) EPOST [1]',
'locker' => 'LOCKER [1]',
'read' => 'READ [1]',
'final' => 'FINAL [1]',
'fire' => 'FIRE [1]',
'song' => 'SONG [1]',
'silk' => 'SILK [1]',
'like' => 'LIKE [1]',
'food' => 'FOOD [1]',
'foodnetwork' => 'FOODNETWORK [1]',
'lifeinsurance' => 'LIFEINSURANCE [1]',
'spot' => 'SPOT [1]',
'latino' => 'LATINO [1]',
'got' => 'GOT (Amazon) [1]',
'grocery' => 'GROCERY [1]',
'hair' => 'HAIR [1]',
'hangout' => 'HANGOUT [1]',
'helsinki' => '(eu) HELSINKI [1]',
'doha' => '(asia) DOHA [1]',
'diy' => 'DIY [1]',
'call' => 'CALL [1]',
'contact' => 'CONTACT [1]',
'select' => 'SELECT [1]',
'jot' => 'JOT (Amazon) [1]',
'mobily' => 'MOBILY [1]',
'mobile' => 'MOBILE [1]',
'mls' => 'MLS (Canada Real Estate Assoc.) [1]',
'cipriani' => 'CIPRIANI [1]',
'circle' => 'CIRCLE [1]',
'cityeats' => 'CITYEATS [1]',
'clinique' => '(fr) CLINIQUE [1]',
'compare' => 'COMPARE [1]',
'case' => 'CASE [1]',
'deal' => 'DEAL [1]',
'coupon' => 'COUPON [1]',
'cruise' => 'CRUISE [1]',
'mint' => 'MINT [1]',
'data' => 'DATA [1]',
'shouji' => '(cn) SHOUJI [1]',
'uconnect' => 'UCONNECT [1]',
'tushu' => '(cn) TUSHU [1]',
'tunes' => 'TUNES [1]',
'dds' => 'DDS (Dr. Dental Surgery) [1]',
'homegoods' => 'HOMEGOODS [1]',
'showtime' => 'SHOWTIME [1]',
'boots' => 'BOOTS [Unknown]',
'country' => 'COUNTRY [Unknown]',
'work' => '( ! ) WORK [637,305]',
'gdn' => '( ! ) GDN [356,076]',
'bid' => '( ! ) BID [320,198]',
'date' => '( ! ) DATE [155,569]',
'tokyo' => '( ! ) (asia) TOKYO [140,188]',
'loans' => '( ! ) LOANS [4,828]'
);

$tld_list['ccTLD'] = array(
'de' => 'DE* (Germany) [15,083,400]',
'uk' => 'UK* (United Kingdom) [11,160,534]',
'co' => 'CO* (Colombia) [2,508,814]',
'io' => 'IO* (British Indian Ocean Territory) [500,566]',
'sk' => 'SK* (Slovakia) [405,693]',
'ch' => 'CH* (Switzerland) [188,608]',
'lt' => 'LT* (Lithuania) [177,780]',
'to' => 'TO* (Tonga) [20,011]',
'ms' => 'MS* (Montserrat) [9,674]',
'cn' => 'CN (China) [14,150,248]',
'ru' => 'RU (Russia) [5,470,742]',
'nl' => 'NL (Netherlands) [5,351,802]',
'tk' => 'TK (Tokelau) [4,212,624]',
'eu' => 'EU (European Union) [3,902,598]',
'br' => 'BR (Brazil) [3,795,680]',
'fr' => 'FR (France) [3,434,038]',
'au' => 'AU (Australia) [3,042,212]',
'it' => 'IT (Italy) [2,983,600]',
'ca' => 'CA (Canada) [2,832,331]',
'pl' => 'PL (Poland) [2,633,768]',
'us' => 'US (United States) [2,408,864]',
'tw' => 'TW (Taiwan) [2,334,182]',
'in' => 'IN (India) [2,172,108]',
'es' => 'ES (Spain) [2,004,044]',
'se' => 'SE (Sweden) [1,811,235]',
'be' => 'BE (Belgium) [1,561,224]',
'jp' => 'JP (Japan) [1,470,648]',
'dk' => 'DK (Denmark) [1,352,931]',
'at' => 'AT (Austria) [1,304,138]',
'cz' => 'CZ (Czechia) [1,282,735]',
'za' => 'ZA (South Africa) [1,214,396]',
'kr' => 'KR (South Korea) [1,065,746]',
'cc' => 'CC (Cocos Islands) [1,055,955]',
'me' => 'ME (Montenegro) [1,009,376]',
'ir' => 'IR (Iran) [931,857]',
'mx' => 'MX (Mexico) [877,362]',
'hu' => 'HU (Hungary) [754,710]',
'no' => 'NO (Norway) [731,580]',
'nz' => 'NZ (New Zealand) [722,124]',
'ro' => 'RO (Romania) [652,713]',
'ua' => 'UA (Ukraine) [600,008]',
'pw' => 'PW (Palau) [551,240]',
'tv' => 'TV (Tuvalu) [499,095]',
'cl' => 'CL (Chile) [472,126]',
'fi' => 'FI (Finland) [459,959]',
'nu' => 'NU (Niue) [458,978]',
'vn' => 'VN (Vietnam) [453,169]',
'ar' => 'AR (Argentina) [444,819]',
'gr' => 'GR (Greece) [407,259]',
'tr' => 'TR (Turkey) [377,632]',
'pt' => 'PT (Portugal) [297,891]',
'id' => 'ID (Indonesia) [284,584]',
'hk' => 'HK (Hong Kong) [277,407]',
'ie' => 'IE (Ireland) [261,631]',
'il' => 'IL (Israel) [247,990]',
'my' => 'MY (Malaysia) [211,207]',
'sg' => 'SG (Singapore) [188,546]',
'ae' => 'AE (United Arab Emirates) [185,235]',
'ws' => 'WS (Samoa) [168,465]',
'kz' => 'KZ (Kazakhstan) [149,056]',
'by' => 'BY (Belarus) [130,955]',
'si' => 'SI (Slovenia) [122,905]',
'ee' => 'EE (Estonia) [120,573]',
'su' => 'SU [119,464]',
'lv' => 'LV (Latvia) [116,839]',
'ng' => 'NG (Nigeria) [112,463]',
'rs' => 'RS (Serbia) [107,435]',
'pe' => 'PE (Peru) [101,466]',
'hr' => 'HR (Croatia) [100,940]',
've' => 'VE (Venezuela) [93,627]',
'ph' => 'PH (Philippines) [93,543]',
'lu' => 'LU (Luxembourg) [86,301]',
'ke' => 'KE (Kenya) [82,010]',
'th' => 'TH (Thailand) [72,067]',
'uy' => 'UY (Uruguay) [71,589]',
'ma' => 'MA (Morocco) [69,951]',
'uz' => 'UZ (Uzbekistan) [67,209]',
'np' => 'NP (Nepal) [63,877]',
'is' => 'IS (Iceland) [63,663]',
'bg' => 'BG (Bulgaria) [62,004]',
'sa' => 'SA (Saudi Arabia) [52,624]',
'ai' => 'AI (Anguilla) [47,626]',
'hm' => 'HM (Heard Island and McDonald Islands) [45,517]',
'ec' => 'EC (Ecuador) [42,785]',
'la' => 'LA (Laos) [42,573]',
'bd' => 'BD (Bangladesh) [42,453]',
'tn' => 'TN (Tunisia) [42,393]',
'li' => 'LI (Liechtenstein) [41,601]',
'zw' => 'ZW (Zimbabwe) [35,076]',
'am' => 'AM (Armenia) [34,906]',
'ge' => 'GE (Georgia) [33,826]',
'bz' => 'BZ (Belize) [32,336]',
'im' => 'IM (Isle of Man) [31,849]',
'cm' => 'CM (Cameroon) [31,181]',
'lk' => 'LK (Sri Lanka) [31,086]',
'mk' => 'MK (Macedonia) [27,976]',
're' => 'RE (Reunion) [27,141]',
'az' => 'AZ (Azerbaijan) [26,274]',
'do' => 'DO (Dominican Republic) [25,917]',
'vc' => 'VC (Saint Vincent and the Grenadines) [23,983]',
'md' => 'MD (Moldova) [23,888]',
'pk' => 'PK (Pakistan) [23,847]',
'ba' => 'BA (Bosnia and Herzegovina) [22,334]',
'al' => 'AL (Albania) [21,863]',
'py' => 'PY (Paraguay) [20,828]',
'ag' => 'AG (Antigua and Barbuda) [18,924]',
'qa' => 'QA (Qatar) [18,678]',
'ac' => 'AC (Ascention Island) [18,537]',
'mt' => 'MT (Malta) [17,792]',
'fm' => 'FM (Micronesia) [17,697]',
'gg' => 'GG (Guernsey) [17,601]',
'mn' => 'MN (Mongolia) [17,557]',
'gt' => 'GT (Guatemala) [17,457]',
'cr' => 'CR (Costa Rica) [17,261]',
'tz' => 'TZ (Tanzania) [16,651]',
'ly' => 'LY (Libya) [15,240]',
'cy' => 'CY (Cyprus) [15,147]',
'as' => 'AS (American Samoa) [14,643]',
'st' => 'ST (Sao Tome and Principe) [13,923]',
'cx' => 'CX (Christmas Island) [13,881]',
'gs' => 'GS (South Georgia and the South Sandwich Islands) [13,377]',
'kg' => 'KG (Kyrgyzstan) [12,389]',
'bo' => 'BO (Bolivia) [12,043]',
'so' => 'SO (Somalia) [11,544]',
'sh' => 'SH (Saint Helena) [11,542]',
'sy' => 'SY (Syria) [9,960]',
'tm' => 'TM (Turkmenistan) [9,657]',
'mu' => 'MU (Mauritius) [9,538]',
'ci' => 'CI (Ivory Coast) [8,820]',
'sv' => 'SV (El Salvador) [8,775]',
'eg' => 'EG (Egypt) [8,447]',
'tc' => 'TC (Turks and Caicos Islands) [8,376]',
'hn' => 'HN (Honduras) [8,205]',
'ug' => 'UG (Uganda) [8,189]',
'dz' => 'DZ (Algeria) [8,058]',
'ps' => 'PS (Palestinian Territory) [7,595]',
'dj' => 'DJ (Djibouti) [7,473]',
'jm' => 'JM (Jamaica) [7,145]',
'bw' => 'BW (Botswana) [7,013]',
'ky' => 'KY (Cayman Islands) [6,837]',
'pm' => 'PM (Saint Pierre and Miquelon) [6,703]',
'tj' => 'TJ (Tajikistan) [6,019]',
'mz' => 'MZ (Mozambique) [5,910]',
'gl' => 'GL (Greenland) [5,781]',
'nc' => 'NC (New Caledonia) [5,752]',
'sc' => 'SC (Seychelles) [5,677]',
'af' => 'AF (Afghanistan) [5,676]',
'je' => 'JE (Jersey) [5,619]',
'cd' => 'CD (Democratic Republic of the Congo) [5,600]',
'sn' => 'SN (Senegal) [5,203]',
'ao' => 'AO (Angola) [5,167]',
'vg' => 'VG (British Virgin Islands) [5,068]',
'pr' => 'PR (Puerto Rico) [5,040]',
'zm' => 'ZM (Zambia) [5,033]',
'mm' => 'MM (Myanmar) [4,600]',
'na' => 'NA (Namibia) [4,466]',
'jo' => 'JO (Jordan) [4,458]',
'tt' => 'TT (Trinidad and Tobago) [4,447]',
'lb' => 'LB (Lebanon) [4,419]',
'fo' => 'FO (Faroe Islands) [4,415]',
'mv' => 'MV (Maldives) [4,192]',
'kw' => 'KW (Kuwait) [4,105]',
'mg' => 'MG (Madagascar) [4,075]',
'sd' => 'SD (Sudan) [3,980]',
'rw' => 'RW (Rwanda) [3,948]',
'gh' => 'GH (Ghana) [3,927]',
'lc' => 'LC (Saint Lucia) [3,912]',
'mc' => 'MC (Monaco) [3,627]',
'kh' => 'KH (Cambodia) [3,612]',
'om' => 'OM (Oman) [3,594]',
'yt' => 'YT (Mayotte) [3,416]',
'sx' => 'SX (Sint Maarten) [3,400]',
'ax' => 'AX (Aland Islands) [3,226]',
'gy' => 'GY (Guyana) [3,186]',
'sr' => 'SR (Suriname) [3,172]',
'tf' => 'TF (French Southern Territories) [3,084]',
'bi' => 'BI (Burundi) [3,072]',
'fj' => 'FJ (Fiji) [3,024]',
'bm' => 'BM (Bermuda) [3,008]',
'ht' => 'HT (Haiti) [2,876]',
'mo' => 'MO (Macao) [2,862]',
'gd' => 'GD (Grenada) [2,825]',
'et' => 'ET (Ethiopia) [2,802]',
'dm' => 'DM (Dominica) [2,593]',
'bs' => 'BS (Bahamas) [2,584]',
'pa' => 'PA (Panama) [2,473]',
'gi' => 'GI (Gibraltar) [2,298]',
'sm' => 'SM (San Marino) [2,273]',
'bt' => 'BT (Bhutan) [2,217]',
'bh' => 'BH (Bahrain) [2,214]',
'gp' => 'GP (Guadeloupe) [2,206]',
'pg' => 'PG (Papua New Guinea) [2,082]',
'mw' => 'MW (Malawi) [2,017]',
'cv' => 'CV (Cape Verde) [1,994]',
'wf' => 'WF (Wallis and Futuna) [1,961]',
'tg' => 'TG (Togo) [1,937]',
'ad' => 'AD (Andorra) [1,908]',
'sl' => 'SL (Sierra Leone) [1,772]',
'pf' => 'PF (French Polynesia) [1,746]',
'tl' => 'TL (East Timor) [1,739]',
'vu' => 'VU (Vanuatu) [1,660]',
'iq' => 'IQ (Iraq) [1,658]',
'sz' => 'SZ (Swaziland) [1,614]',
'ls' => 'LS (Lesotho) [1,605]',
'gm' => 'GM (Gambia) [1,518]',
'bb' => 'BB (Barbados) [1,500]',
'cu' => 'CU (Cuba) [1,427]',
'bf' => 'BF (Burkina Faso) [1,424]',
'sb' => 'SB (Solomon Islands) [1,404]',
'cg' => 'CG (Republic of the Congo) [1,389]',
'bn' => 'BN (Brunei) [1,346]',
'ck' => 'CK (Cook Islands) [1,325]',
'bj' => 'BJ (Benin) [1,143]',
'nf' => 'NF (Norfolk Island) [1,113]',
'ye' => 'YE (Yemen) [1,066]',
'vi' => 'VI (U.S. Virgin Islands) [1,017]',
'cw' => 'CW (Curacao) [849]',
'mr' => 'MR (Mauritania) [824]',
'kn' => 'KN (Saint Kitts and Nevis) [788]',
'mp' => 'MP (Northern Mariana Islands) [743]',
'pn' => 'PN (Pitcairn) [673]',
'aw' => 'AW (Aruba) [670]',
'ne' => 'NE (Niger) [613]',
'mq' => 'MQ (Martinique) [589]',
'ki' => 'KI (Kiribati) [511]',
'gf' => 'GF (French Guiana) [498]',
'td' => 'TD (Chad) [370]',
'lr' => 'LR (Liberia) [338]',
'nr' => 'NR (Nauru) [309]',
'gn' => 'GN (Guinea) [290]',
'gw' => 'GW (Guinea-Bissau) [210]',
'er' => 'ER (Eritrea) [113]',
'km' => 'KM (Comoros) [109]',
'aq' => 'AQ (Antarctica) [84]',
'ni' => 'NI (Nicaragua) [64]',
'gu' => 'GU (Guam) [45]',
'va' => 'VA (Vatican) [36]',
'kp' => 'KP (North Korea) [35]',
'mh' => 'MH (Marshall Islands) [10]',
'gb' => 'GB (United Kingdom) [1]',
'ss' => 'SS (South Sudan) [Unknown]',
'mf' => 'MF (Saint Martin) [Unknown]',
'bv' => 'BV (Bouvet Island) [Unknown]',
'eh' => 'EH (Western Sahara) [Unknown]',
'bl' => 'BL (Saint Barthelemy) [Unknown]',
'um' => 'UM (United States Minor Outlying Islands) [Unknown]',
'sj' => 'SJ (Svalbard and Jan Mayen) [Unknown]',
'fk' => 'FK (Falkland Islands) [Unknown]',
'an' => 'AN (Netherlands Antilles)  [Unknown]',
'bq' => 'BQ (Bonaire, Saint Eustatius and Saba ) [Unknown]',
'ga' => '( ! ) GA (Gabon) [2,361,641]',
'cf' => '( ! ) CF (Central African Republic) [1,822,686]',
'gq' => '( ! ) GQ (Equatorial Guinea) [1,774,205]',
'ml' => '( ! ) ML (Mali) [1,729,138]'
);

$tld_list['iTLD'] = array(
'xn--p1ai' => '(cc) XN--P1AI - рф [820,042]',
'xn--ses554g' => '(cn) XN--SES554G - 网址 [217,564]',
'xn--fiqs8s' => '(cc) XN--FIQS8S - 中国 [213,153]',
'xn--fiqz9s' => '(cc) XN--FIQZ9S - 中國 [211,520]',
'xn--55qx5d' => '(cn) XN--55QX5D - 公司 [50,003]',
'xn--3ds443g' => '(cn) XN--3DS443G - 在线 [49,765]',
'xn--p1acf' => '(cc) XN--P1ACF - рус [48,033]',
'xn--j6w193g' => '(cc) XN--J6W193G - 香港 [42,175]',
'xn--kput3i' => '(cc) XN--KPUT3I - 手机 [36,262]',
'xn--6qq986b3xl' => '(cn) XN--6QQ986B3XL - 我爱你 [34,592]',
'xn--io0a7i' => '(cn) XN--IO0A7I - 网络 [32,673]',
'xn--czr694b' => '(cn) XN--CZR694B - 商标 [25,144]',
'xn--3e0b707e' => '(cc) XN--3E0B707E - 한국 [19,233]',
'xn--80adxhks' => '(cc) XN--80ADXHKS - москва [17,346]',
'xn--czru2d' => '(cn) XN--CZRU2D - 商城 [16,581]',
'xn--fiq228c5hs' => '(cn) XN--FIQ228C5HS - 中文网 [13,065]',
'xn--90ais' => '(cc) XN--90AIS - бел [10,706]',
'xn--j1amh' => '(cc) XN--J1AMH - укр [8,305]',
'xn--mk1bu44c' => 'XN--MK1BU44C - 닷컴 [6,580]',
'xn--kpry57d' => '(cc) XN--KPRY57D - 台灣 [6,170]',
'xn--tckwe' => 'XN--TCKWE - コム [5,995]',
'xn--hxt814e' => '(cc) XN--HXT814E - 网店 [4,877]',
'desi' => '(hin) DESI [4,484]',
'xn--3bst00m' => '(cn) XN--3BST00M - 集团 [4,369]',
'xn--9dbq2a' => 'XN--9DBQ2A - קום [3,597]',
'xn--80asehdb' => '(cr) XN--80ASEHDB - онлайн [3,576]',
'xn--6frz82g' => '(Brand) XN--6FRZ82G - 移动 [3,088]',
'xn--vuq861b' => '(cc) XN--VUQ861B - 信息 [2,671]',
'xn--mgbaam7a8h' => '(cc) XN--MGBAAM7A8H - امارات [1,701]',
'xn--t60b56a' => 'XN--T60B56A - 닷넷 [1,605]',
'xn--rhqv96g' => '(cn) XN--RHQV96G - 世界 [1,554]',
'xn--vhquv' => '(cc) XN--VHQUV - 企业 [1,541]',
'xn--g2xx48c' => '(cc) XN--G2XX48C - 购物 [1,471]',
'xn--80aswg' => '(cr) XN--80ASWG - сайт [1,287]',
'xn--c1avg' => '(cr) XN--C1AVG - орг [1,285]',
'xn--d1acj3b' => '(cr) XN--D1ACJ3B - дети [1,219]',
'xn--q9jyb4c' => 'XN--Q9JYB4C - みんな [1,192]',
'xn--90ae' => '(cc) XN--90AE - бг [1,097]',
'shiksha' => '(hin) SHIKSHA [1,097]',
'xn--ygbi2ammx' => '(cc) XN--YGBI2AMMX - فلسطين [1,084]',
'xn--ngbc5azd' => '(ar) XN--NGBC5AZD - شبكة [817]',
'xn--80ao21a' => '(cc) XN--80AO21A - қаз [812]',
'xn--90a3ac' => '(cc) XN--90A3AC - срб [802]',
'xn--h2brj9c' => '(cc) XN--H2BRJ9C - भारत [759]',
'xn--czrs0t' => '(cc) XN--CZRS0T - 商店 [643]',
'xn--pgbs0dh' => '(cc) XN--PGBS0DH - تونس [632]',
'xn--5tzm5g' => '(cn) XN--5TZM5G - 网站 [522]',
'xn--o3cw4h' => '(cc) XN--O3CW4H - ไทย [502]',
'xn--e1a4c' => '(cc) XN--E1A4C - ею [446]',
'xn--node' => '(cc) XN--NODE - გე [425]',
'xn--4gbrim' => '(ar) XN--4GBRIM - موقع [397]',
'xn--wgbh1c' => '(cc) XN--WGBH1C - مصر [316]',
'xn--nqv7f' => '(cn) XN--NQV7F - 机构 [260]',
'xn--l1acc' => '(cc) XN--L1ACC - мон [231]',
'xn--mgbayh7gpa' => '(cc) XN--MGBAYH7GPA - الاردن [230]',
'xn--fiq64b' => '(Brand) XN--FIQ64B - 中信 [200]',
'xn--fzc2c9e2c' => '(cc) XN--FZC2C9E2C - ලංකා [191]',
'xn--yfro4i67o' => '(cc) XN--YFRO4I67O - 新加坡 [184]',
'xn--unup4y' => '(cc) XN--UNUP4Y - 游戏 [176]',
'xn--kprw13d' => '(cc) XN--KPRW13D - 台湾 [174]',
'xn--xhq521b' => '(cc) XN--XHQ521B - 广东 [146]',
'xn--cck2b3b' => 'XN--CCK2B3B - ストア [143]',
'xn--fjq720a' => '(cc) XN--FJQ720A - 娱乐 [121]',
'xn--ogbpf8fl' => '(cc) XN--OGBPF8FL - سورية [120]',
'xn--wgbl6a' => '(cc) XN--WGBL6A - قطر [110]',
'xn--y9a3aq' => '(cc) XN--Y9A3AQ - հայ [109]',
'xn--i1b6b1a6a2e' => 'XN--I1B6B1A6A2E - संगठन [105]',
'xn--1ck2e1b' => 'XN--1CK2E1B - セール [105]',
'xn--1qqw23a' => '(cc) XN--1QQW23A - 佛山 [98]',
'xn--xkc2al3hye2a' => '(cc) XN--XKC2AL3HYE2A - இலங்கை [93]',
'xn--d1alf' => '(cc) XN--D1ALF - мкд [92]',
'xn--zfr164b' => '(cc) XN--ZFR164B - 政务 [91]',
'xn--fct429k' => '(cc) XN--FCT429K - 家電 [68]',
'xn--gckr3f0f' => 'XN--GCKR3F0F - クラウド [67]',
'xn--jvr189m' => '(cc) XN--JVR189M - 食品 [52]',
'xn--45q11c' => '(cn) XN--45Q11C - 八卦 [46]',
'xn--otu796d' => '(cc) XN--OTU796D - 招聘 [39]',
'xn--bck1b9a5dre4c' => 'XN--BCK1B9A5DRE4C - ファッション [38]',
'xn--imr513n' => '(cc) XN--IMR513N - 餐厅 [36]',
'xn--mgbca7dzdo' => '(cc) XN--MGBCA7DZDO - ابوظبي [34]',
'xn--rovu88b' => '(cc) XN--ROVU88B - 書籍 [34]',
'xn--nyqy26a' => '(cc) XN--NYQY26A - 健康 [24]',
'xn--xkc2dl3a5ee0h' => '(cc) XN--XKC2DL3A5EE0H - இந்தியா [24]',
'xn--55qw42g' => '(cn) XN--55QW42G - 公益 [23]',
'xn--lgbbat1ad8j' => '(cc) XN--LGBBAT1AD8J - الجزائر [13]',
'xn--mgb9awbf' => '(cc) XN--MGB9AWBF - عمان [13]',
'xn--kcrx77d1x4a' => '(Brand) XN--KCRX77D1X4A - 飞利浦 [9]',
'xn--clchc0ea0b2g2a9gcd' => '(cc) XN--CLCHC0EA0B2G2A9GCD - சிங்கப்பூர் [9]',
'xn--mgbpl2fh' => '(cc) XN--MGBPL2FH - سودان [4]',
'xn--fpcrj9c3d' => '(cc) XN--FPCRJ9C3D - భారత్ [3]',
'xn--mgberp4a5d4ar' => '(cc) XN--MGBERP4A5D4AR - السعودية [2]',
'xn--mgbc0a9azcg' => '(cc) XN--MGBC0A9AZCG - المغرب [2]',
'xn--5su34j936bgsg' => '(Brand) XN--5SU34J936BGSG - 香格里拉 [2]',
'xn--s9brj9c' => '(cc) XN--S9BRJ9C - ਭਾਰਤ [2]',
'xn--8y0a063a' => '(Brand) XN--8Y0A063A - 联通 [2]',
'xn--mgba7c0bbn0a' => '(cc) XN--MGBA7C0BBN0A - العليان [2]',
'xn--mgba3a4f16a' => '(cc) XN--MGBA3A4F16A - ایران [2]',
'xn--mgba3a3ejt' => '(Brand) XN--MGBA3A3EJT - ارامكو [2]',
'xn--80aqecdr1a' => '(cr) XN--80AQECDR1A - католик [1]',
'xn--nqv7fs00ema' => '(cc) XN--NQV7FS00EMA - 组织机构 [1]',
'xn--11b4c3d' => 'XN--11B4C3D - कॉम [1]',
'xn--ngbrx' => '(cc) XN--NGBRX - عرب [1]',
'xn--ngbe9e0a' => '(ar) XN--NGBE9E0A - بيتك [1]',
'xn--w4rs40l' => '(Brand) XN--W4RS40L - 嘉里 [1]',
'xn--tiq49xqyj' => '(cc) XN--TIQ49XQYJ - 天主教 [1]',
'xn--w4r85el8fhu5dnra' => '(Brand) XN--W4R85EL8FHU5DNRA - 嘉里大酒店 [1]',
'xn--30rr7y' => '(cn) XN--30RR7Y - 慈善 [1]',
'xn--3oq18vl8pn36a' => '(Brand) XN--3OQ18VL8PN36A - 大众汽车 [1]',
'xn--pbt977c' => '(cc) XN--PBT977C - 珠宝 [1]',
'xn--pssy2u' => '(cc) XN--PSSY2U - 大拿 [1]',
'xn--qcka1pmc' => '(Brand) XN--QCKA1PMC - グーグル [1]',
'xn--vermgensberatung-pwb' => '(Brand) XN--VERMGENSBERATUNGPWB - vermögensberatung [1]',
'xn--vermgensberater-ctb' => '(Brand) XN--VERMGENSBERATUNGCTB - vermögensberater [1]',
'xn--42c2d9a' => 'XN--42C2D9A - คอม [1]',
'xn--3pxu8k' => '(cc) XN--3PXU8K - 点看 [1]',
'xn--mxtq1m' => '(cc) XN--MXTQ1M - 政府 [1]',
'xn--9et52u' => '(cc) XN--9ET52U - 时尚 [1]',
'xn--flw351e' => '(Brand) XN--FLW351E - 谷歌 [1]',
'xn--9krt00a' => '(cn) XN--9KRT00A - 微博 [1]',
'xn--b4w605ferd' => '(Brand) XN--B4W605FERD - 淡马锡 [1]',
'xn--fzys8d69uvgm' => '(Brand) XN--FZYS8D69UVGM - 電訊盈科 [1]',
'xn--fhbei' => '(ar) XN--FHBEI - كوم [1]',
'xn--gk3at1e' => '(cc) XN--GK3AT1E - 通販 [1]',
'xn--estv75g' => '(Brand) XN--ESTV75G - 工行 [1]',
'xn--efvy88h' => '(cc) XN--EFVY88H - 新闻 [1]',
'xn--j1aef' => '(cr) XN--J1AEF - ком [1]',
'xn--eckvdtc9d' => 'XN--ECKVDTC9D - ポイント [1]',
'xn--jlq61u9w7b' => '(Brand) XN--JLQ61U9W7B - 诺基亚 [1]',
'xn--cg4bki' => '(Brand) XN--CG4BKI - 삼성 [1]',
'xn--kpu716f' => '(cc) XN--KPU716F - 手表 [1]',
'xn--c2br7g' => 'XN--C2BR7G - नेट [1]',
'xn--mgbaakc7dvf' => '(Brand) XN--MGBAAKC7DVF - اتصالات [1]',
'xn--mgbt3dhd' => '(cc) XN--MGBT3DHD - همراه [1]',
'xn--mgbb9fbpob' => '(ar) XN--MGBB9FBPOB - موبايلي [1]',
'xn--mgbi4ecexp' => '(ar) XN--MGBI4ECEXP - كاثوليك [1]',
'xn--mgbbh1a71e' => '(cc) XN--MGBBH1A71E - بھارت [1]',
'xn--mix082f' => '(cc) XN--MIX082F - 澳门 [Unknown]',
'xn--mgbx4cd0ab' => '(cc) XN--MGBX4CD0AB - مليسيا [Unknown]',
'xn--gecrj9c' => '(cc) XN--GECRJ9C - ભારત [Unknown]',
'xn--nnx388a' => '(cc) XN--NNX388A - 臺灣 [Unknown]',
'xn--h2breg3eve' => '(cc) XN--H2BREG3EVE - भारतम् [Unknown]',
'xn--h2brj9c8c' => '(cc) XN--H2BRJ9C8C - भारोत [Unknown]',
'xn--mgbtx2b' => '(cc) XN--MGBTX2B - عراق [Unknown]',
'xn--mgbtf8fl' => '(cc) XN--MGBTF8FL - سوريا [Unknown]',
'xn--mgbqly7cvafr' => '(cc) XN--MGBQLY7CVAFR - السعوديه [Unknown]',
'xn--mgbqly7c0a67fbc' => '(cc) XN--MGBQLY7C0A67FBC - السعودیۃ [Unknown]',
'xn--2scrj9c' => '(cc) XN--2SCRJ9C - ಭಾರತ [Unknown]',
'xn--mgberp4a5d4a87g' => '(cc) XN--MGBERP4A5D4A87G - السعودیة [Unknown]',
'xn--mgbgu82a' => '(cc) XN--MGBGU82A - ڀارت [Unknown]',
'xn--mgbbh1a' => '(cc) XN--MGBBH1A - بارت [Unknown]',
'xn--3hcrj9c' => '(cc) XN--3HCRJ9C - ଭାରତ [Unknown]',
'xn--mgb2ddes' => '(cc) XN--MGB2DDES - اليمن [Unknown]',
'xn--mgba3a4fra' => '(cc) XN--MGBA3A4FRA - ايران [Unknown]',
'xn--54b7fta0cc' => '(cc) XN--54B7FTA0CC - বাংলা [Unknown]',
'xn--mgbab2bd' => '(ar) XN--MGBAB2BD - بازار [Unknown]',
'xn--mix891f' => '(cc) XN--MIX891F - 澳門 [Unknown]',
'xn--mgbai9a5eva00b' => '(cc) XN--MGBAI9A5EVA00B - پاكستان [Unknown]',
'xn--45br5cyl' => '(cc) XN--45BR5CYL - ভাৰত [Unknown]',
'xn--qxam' => '(cc) XN--QXAM - ελ [Unknown]',
'xn--mgbai9azgqp6j' => '(cc) XN--MGBAI9AZGQP6J - پاکستان [Unknown]',
'xn--45brj9c' => '(cc) XN--45BRJ9C - ভারত [Unknown]',
'xn--rvc1e0am3e' => '(cc) XN--RVC1E0AM3E - ഭാരതം [Unknown]'
);

$tld_list['bgTLD'] = array(
'ovh' => 'OVH [66,591]',
'forsale' => 'FORSALE [7,807]',
'kred' => 'KRED [6,749]',
'dvag' => 'DVAG [2,519]',
'mma' => 'MMA [1,684]',
'audi' => 'AUDI [1,409]',
'allfinanz' => 'ALLFINANZ [702]',
'broker' => 'BROKER [664]',
'seat' => 'SEAT [659]',
'mini' => 'MINI [653]',
'neustar' => 'NEUSTAR [633]',
'creditunion' => 'CREDITUNION [619]',
'gmx' => 'GMX [473]',
'crs' => 'CRS [397]',
'aco' => 'ACO [293]',
'bnpparibas' => 'BNPPARIBAS [253]',
'basketball' => 'BASKETBALL [230]',
'lamborghini' => 'LAMBORGHINI [203]',
'forex' => 'FOREX (IG Holdings) [198]',
'leclerc' => 'LECLERC (A.C.D. Assoc.) [166]',
'abbott' => 'ABBOTT [162]',
'citic' => 'CITIC [159]',
'bradesco' => 'BRADESCO [147]',
'esurance' => 'ESURANCE [147]',
'nra' => 'NRA [144]',
'weber' => 'WEBER [142]',
'man' => 'MAN [130]',
'bmw' => 'BMW [122]',
'weir' => 'WEIR [120]',
'barclays' => 'BARCLAYS [118]',
'jnj' => 'JNJ (Johnson & Johnson) [107]',
'discover' => 'DISCOVER [101]',
'stada' => 'STADA [86]',
'iselect' => 'ISELECT [86]',
'linde' => 'LINDE [85]',
'goog' => 'GOOG [81]',
'pru' => 'PRU [78]',
'erni' => 'ERNI [76]',
'bloomberg' => 'BLOOMBERG [75]',
'fox' => 'FOX [72]',
'prudential' => 'PRUDENTIAL [71]',
'schwarz' => 'SCHWARZ [67]',
'globo' => 'GLOBO [65]',
'afl' => 'AFL [65]',
'fage' => 'FAGE [61]',
'total' => 'TOTAL [57]',
'edeka' => 'EDEKA [54]',
'monash' => 'MONASH [53]',
'saxo' => 'SAXO [51]',
'cba' => 'CBA [50]',
'microsoft' => 'MICROSOFT [49]',
'pictet' => 'PICTET [48]',
'firmdale' => 'FIRMDALE [48]',
'uol' => 'UOL [45]',
'mango' => 'MANGO [43]',
'auspost' => 'AUSPOST [43]',
'aig' => 'AIG [42]',
'fresenius' => 'FRESENIUS [40]',
'canon' => 'CANON [38]',
'toray' => 'TORAY [38]',
'sener' => 'SENER [36]',
'google' => 'GOOGLE [35]',
'vivo' => 'VIVO [35]',
'yandex' => 'YANDEX [34]',
'sky' => 'SKY [33]',
'xbox' => 'XBOX [33]',
'aquarelle' => 'AQUARELLE [33]',
'mlb' => 'MLB [33]',
'scb' => 'SCB [33]',
'deloitte' => 'DELOITTE [31]',
'cern' => 'CERN [30]',
'liaison' => 'LIAISON [30]',
'shriram' => 'SHRIRAM [28]',
'axa' => 'AXA [28]',
'lidl' => 'LIDL [26]',
'abb' => 'ABB [25]',
'bentley' => 'BENTLEY [25]',
'ikano' => 'IKANO [25]',
'smart' => 'SMART [24]',
'ipiranga' => 'IPIRANGA [24]',
'barclaycard' => 'BARCLAYCARD [24]',
'windows' => 'WINDOWS [24]',
'komatsu' => 'KOMATSU [24]',
'ifm' => 'IFM [23]',
'hyatt' => 'HYATT [23]',
'cisco' => 'CISCO [23]',
'bing' => 'BING [22]',
'latrobe' => 'LATROBE [21]',
'allstate' => 'ALLSTATE [21]',
'philips' => 'PHILIPS [21]',
'hsbc' => 'HSBC [20]',
'sbi' => 'SBI [20]',
'sap' => 'SAP [20]',
'sandvik' => 'SANDVIK [19]',
'hotmail' => 'HOTMAIL [19]',
'csc' => 'CSC [18]',
'williamhill' => 'WILLIAMHILL [18]',
'otsuka' => 'OTSUKA [18]',
'shell' => 'SHELL [18]',
'sncf' => 'SNCF [18]',
'hisamitsu' => 'HISAMITSU [17]',
'amex' => 'AMEX [17]',
'woodside' => 'WOODSIDE [17]',
'praxi' => 'PRAXI [17]',
'sharp' => 'SHARP [16]',
'kfh' => 'KFH [15]',
'marriott' => 'MARRIOTT [15]',
'jprs' => 'JPRS (Japan Registry) [15]',
'teva' => 'TEVA [14]',
'statefarm' => 'STATEFARM [14]',
'schmidt' => 'SCHMIDT [14]',
'walter' => 'WALTER [14]',
'azure' => 'AZURE [13]',
'bbva' => 'BBVA [13]',
'dell' => 'DELL [13]',
'locus' => 'LOCUS [12]',
'hitachi' => 'HITACHI [12]',
'nico' => 'NICO [12]',
'ricoh' => 'RICOH [12]',
'suzuki' => 'SUZUKI [12]',
'jcb' => 'JCB [12]',
'chase' => 'CHASE [11]',
'dhl' => 'DHL [11]',
'sew' => 'SEW [11]',
'emerck' => 'EMERCK [10]',
'abc' => 'ABC [10]',
'lundbeck' => 'LUNDBECK [10]',
'orange' => 'ORANGE [9]',
'bostik' => 'BOSTIK [9]',
'amfam' => 'AMFAM [9]',
'seven' => 'SEVEN [9]',
'gmo' => 'GMO [9]',
'jll' => 'JLL (Jones Lang LaSalle Inc.) [9]',
'ntt' => 'NTT [9]',
'stc' => 'STC [9]',
'dabur' => 'DABUR [9]',
'nec' => 'NEC [9]',
'americanexpress' => 'AMERICANEXPRESS [8]',
'pioneer' => 'PIONEER [8]',
'gle' => 'GLE [8]',
'swatch' => 'SWATCH [8]',
'fujitsu' => 'FUJITSU [8]',
'lancaster' => 'LANCASTER [8]',
'gea' => 'GEA [8]',
'extraspace' => 'EXTRASPACE [8]',
'redstone' => 'REDSTONE [8]',
'maif' => 'MAIF [8]',
'vistaprint' => 'VISTAPRINT [8]',
'toyota' => 'TOYOTA [8]',
'bridgestone' => 'BRIDGESTONE [8]',
'brother' => 'BROTHER [8]',
'jpmorgan' => 'JPMORGAN [8]',
'rexroth' => 'REXROTH [8]',
'vanguard' => 'VANGUARD [8]',
'chintai' => 'CHINTAI [8]',
'dnp' => 'DNP [7]',
'clubmed' => 'CLUBMED [7]',
'sanofi' => 'SANOFI [7]',
'sandvikcoromant' => 'SANDVIKCOROMANT [7]',
'itv' => 'ITV [7]',
'aetna' => 'AETNA [7]',
'sony' => 'SONY [7]',
'intel' => 'INTEL [7]',
'sfr' => 'SFR [7]',
'kpmg' => 'KPMG [7]',
'monster' => 'MONSTER (Monster Worldwide Inc.) [6]',
'sohu' => 'SOHU [6]',
'gucci' => 'GUCCI [6]',
'sca' => 'SCA [6]',
'lipsy' => 'LIPSY [6]',
'skype' => 'SKYPE [6]',
'temasek' => 'TEMASEK [6]',
'wme' => 'WME [6]',
'bugatti' => 'BUGATTI [6]',
'anz' => 'ANZ [6]',
'cfa' => 'CFA [6]',
'honda' => 'HONDA [5]',
'schaeffler' => 'SCHAEFFLER [5]',
'netbank' => 'NETBANK (Bank Of Australia) [5]',
'cuisinella' => 'CUISINELLA [5]',
'softbank' => 'SOFTBANK [5]',
'landrover' => 'LANDROVER [5]',
'mattel' => 'MATTEL [5]',
'next' => 'NEXT (Next PLC) [5]',
'kia' => 'KIA [5]',
'apple' => 'APPLE [5]',
'mtn' => 'MTN [5]',
'onyourside' => 'ONYOURSIDE (Nationwide Mutual Insurance) [5]',
'frogans' => 'FROGANS [5]',
'tatamotors' => 'TATAMOTORS [5]',
'nissan' => 'NISSAN [4]',
'jaguar' => 'JAGUAR [4]',
'commbank' => 'COMMBANK [4]',
'rmit' => 'RMIT [4]',
'citi' => 'CITI [4]',
'northwesternmutual' => 'NORTHWESTERNMUTUAL [4]',
'chanel' => 'CHANEL [4]',
'genting' => 'GENTING [4]',
'java' => 'JAVA [4]',
'bauhaus' => 'BAUHAUS [4]',
'jio' => 'JIO (Affinity Names) [4]',
'volkswagen' => 'VOLKSWAGEN [4]',
'nokia' => 'NOKIA [4]',
'blanco' => 'BLANCO [4]',
'grainger' => 'GRAINGER [4]',
'hermes' => 'HERMES [4]',
'lexus' => 'LEXUS [4]',
'airbus' => 'AIRBUS [4]',
'ferrero' => 'FERRERO [4]',
'zara' => 'ZARA [4]',
'fairwinds' => 'FAIRWINDS [4]',
'gmail' => 'GMAIL [4]',
'everbank' => 'EVERBANK [4]',
'eurovision' => 'EUROVISION [4]',
'ford' => 'FORD [4]',
'lanxess' => 'LANXESS [4]',
'bbc' => 'BBC [3]',
'able' => 'ABLE [3]',
'kinder' => 'KINDER [3]',
'accenture' => 'ACCENTURE [3]',
'panasonic' => 'PANASONIC [3]',
'amica' => 'AMICA [3]',
'lilly' => 'LILLY [3]',
'youtube' => 'YOUTUBE [3]',
'kerrylogistics' => 'KERRYLOGISTICS [3]',
'playstation' => 'PLAYSTATION [3]',
'lupin' => 'LUPIN [3]',
'xerox' => 'XEROX [3]',
'pfizer' => 'PFIZER [3]',
'aarp' => 'AARP [3]',
'ses' => 'SES [3]',
'lixil' => 'LIXIL [3]',
'mit' => 'MIT [3]',
'goo' => 'GOO (NTT Resonant) [3]',
'target' => 'TARGET [3]',
'dupont' => 'DUPONT [3]',
'natura' => 'NATURA [3]',
'bond' => 'BOND [3]',
'nadex' => 'NADEX [3]',
'sbs' => 'SBS [3]',
'guardian' => 'GUARDIAN [3]',
'ieee' => 'IEEE [3]',
'travelers' => 'TRAVELERS [3]',
'obi' => 'OBI [3]',
'nab' => 'NAB (National Australia Bank) [3]',
'mutual' => 'MUTUAL [3]',
'mtr' => 'MTR [3]',
'jmp' => 'JMP (Matrix IP LLC) [3]',
'omega' => 'OMEGA [3]',
'oracle' => 'ORACLE [3]',
'mitsubishi' => 'MITSUBISHI [2]',
'pwc' => 'PWC [2]',
'pohl' => 'POHL [2]',
'quest' => 'QUEST [2]',
'redumbrella' => 'REDUMBRELLA (Affilias) [2]',
'nextdirect' => 'NEXTDIRECT (Next PLC) [2]',
'ping' => 'PING [2]',
'rwe' => 'RWE [2]',
'nfl' => 'NFL [2]',
'nhk' => 'NHK [2]',
'rocher' => 'ROCHER [2]',
'raid' => 'RAID (Johnson & Johnson) [2]',
'nikon' => 'NIKON [2]',
'office' => 'OFFICE [2]',
'olayan' => 'OLAYAN (Crescent Holding GmbH) [2]',
'olayangroup' => 'OLAYANGROUP (Crescent Holding GmbH) [2]',
'sas' => 'SAS (Research IP LLC) [2]',
'aaa' => 'AAA [2]',
'lotte' => 'LOTTE [2]',
'ubs' => 'UBS [2]',
'firestone' => 'FIRESTONE [2]',
'fidelity' => 'FIDELITY [2]',
'lincoln' => 'LINCOLN [2]',
'tmall' => 'TMALL (Alibaba) [2]',
'toshiba' => 'TOSHIBA [2]',
'datsun' => 'DATSUN [2]',
'travelchannel' => 'TRAVELCHANNEL [2]',
'crown' => 'CROWN [2]',
'cookingchannel' => 'COOKINGCHANNEL [2]',
'trv' => 'TRV (Travelers TLD) [2]',
'chrome' => 'CHROME [2]',
'ceb' => 'CEB (Corp. Exec. Board Co) [2]',
'flir' => 'FLIR [2]',
'cbs' => 'CBS [2]',
'unicom' => 'UNICOM [2]',
'cbn' => 'CBN [2]',
'bosch' => 'BOSCH [2]',
'booking' => 'BOOKING [2]',
'bananarepublic' => 'BANANAREPUBLIC [2]',
'warman' => 'WARMAN (Weir Group IP) [2]',
'aramco' => 'ARAMCO [2]',
'yahoo' => 'YAHOO [2]',
'yodobashi' => 'YODOBASHI [2]',
'zappos' => 'ZAPPOS [2]',
'zip' => 'ZIP [2]',
'symantec' => 'SYMANTEC [2]',
'prod' => 'PROD [2]',
'ibm' => 'IBM [2]',
'kddi' => 'KDDI [2]',
'itau' => 'ITAU [2]',
'infiniti' => 'INFINITI [2]',
'gallo' => 'GALLO [2]',
'hyundai' => 'HYUNDAI [2]',
'honeywell' => 'HONEYWELL [2]',
'jcp' => 'JCP [2]',
'hdfcbank' => 'HDFCBANK [2]',
'spiegel' => 'SPIEGEL [2]',
'goldpoint' => 'GOLDPOINT [2]',
'godaddy' => 'GODADDY [2]',
'juniper' => 'JUNIPER [2]',
'glade' => 'GLADE (Johnson & Johnson) [2]',
'gbiz' => 'GBIZ [2]',
'weatherchannel' => 'WEATHERCHANNEL [1]',
'xfinity' => 'XFINITY [1]',
'sina' => 'SINA [1]',
'scjohnson' => 'SCJOHNSON [1]',
'scor' => 'SCOR [1]',
'seek' => 'SEEK [1]',
'yamaxun' => 'YAMAXUN [1]',
'shangrila' => 'SHANGRILA [1]',
'virgin' => 'VIRGIN [1]',
'shaw' => 'SHAW [1]',
'visa' => 'VISA [1]',
'qvc' => 'QVC [1]',
'vista' => 'VISTA [1]',
'volvo' => 'VOLVO [1]',
'wtc' => 'WTC [1]',
'wolterskluwer' => 'WOLTERSKLUWER [1]',
'vig' => 'VIG [1]',
'walmart' => 'WALMART [1]',
'viking' => 'VIKING [1]',
'swiftcover' => 'SWIFTCOVER [1]',
'reliance' => 'RELIANCE [1]',
'thd' => 'THD (Homer TLC Inc.) [1]',
'stcgroup' => 'STCGROUP [1]',
'tab' => 'TAB (Tabcorp Holdings) [1]',
'samsung' => 'SAMSUNG [1]',
'taobao' => 'TAOBAO [1]',
'sakura' => 'SAKURA [1]',
'tci' => 'TCI (Asia Green) [1]',
'tdk' => 'TDK [1]',
'safety' => 'SAFETY [1]',
'telefonica' => 'TELEFONICA [1]',
'tiffany' => 'TIFFANY [1]',
'verisign' => 'VERISIGN [1]',
'statebank' => 'STATEBANK [1]',
'rogers' => 'ROGERS [1]',
'starhub' => 'STARHUB [1]',
'star' => 'STAR [1]',
'staples' => 'STAPLES [1]',
'tui' => 'TUI [1]',
'tvs' => 'TVS [1]',
'richardli' => 'RICHARDLI (Pacific Century Asset Mgmt) [1]',
'ups' => 'UPS [1]',
'tjx' => 'TJX [1]',
'lacaixa' => 'LACAIXA [1]',
'progressive' => 'PROGRESSIVE [1]',
'dish' => 'DISH [1]',
'capitalone' => 'CAPITALONE [1]',
'caravan' => 'CARAVAN [1]',
'cartier' => 'CARTIER [1]',
'caseih' => 'CASEIH (Fiat)  [1]',
'cbre' => 'CBRE [1]',
'chrysler' => 'CHRYSLER [1]',
'citadel' => 'CITADEL [1]',
'comcast' => 'COMCAST [1]',
'comsec' => 'COMSEC (Verisign) [1]',
'cyou' => 'CYOU (Afilias) [1]',
'dclk' => 'DCLK (Google) [1]',
'dealer' => 'DEALER [1]',
'delta' => 'DELTA [1]',
'dodge' => 'DODGE [1]',
'cal' => 'CAL [1]',
'dtv' => 'DTV (Dish TV) [1]',
'dunlop' => 'DUNLOP [1]',
'duns' => 'DUNS (Dun and Bradstreet) [1]',
'dvr' => 'DVR (Hughes Satellite Co.) [1]',
'ericsson' => 'ERICSSON [1]',
'etisalat' => 'ETISALAT [1]',
'farmers' => 'FARMERS [1]',
'fedex' => 'FEDEX [1]',
'ferrari' => 'FERRARI [1]',
'fiat' => 'FIAT [1]',
'fido' => 'FIDO (Rogers) [1]',
'flickr' => 'FLICKR [1]',
'frontier' => 'FRONTIER [1]',
'calvinklein' => 'CALVINKLEIN [1]',
'bofa' => 'BOFA (NMS Services) [1]',
'fujixerox' => 'FUJIXEROX [1]',
'alstom' => 'ALSTOM [1]',
'abarth' => 'ABARTH [1]',
'abbvie' => 'ABBVIE [1]',
'adac' => 'ADAC (Allgemeiner De. Auto-Club) [1]',
'aeg' => 'AEG [1]',
'afamilycompany' => 'AFAMILYCOMPANY (Johnson & Johnson) [1]',
'agakhan' => 'AGAKHAN [1]',
'aigo' => 'AIGO [1]',
'airtel' => 'AIRTEL [1]',
'akdn' => 'AKDN [1]',
'alfaromeo' => 'ALFAROMEO [1]',
'alibaba' => 'ALIBABA [1]',
'alipay' => 'ALIPAY [1]',
'ally' => 'ALLY [1]',
'americanfamily' => 'AMERICANFAMILY (AMFam) [1]',
'boehringer' => 'BOEHRINGER [1]',
'android' => 'ANDROID [1]',
'aol' => 'AOL [1]',
'asda' => 'ASDA (Walmart) [1]',
'athleta' => 'ATHLETA (The Gap) [1]',
'avianca' => 'AVIANCA (Aerovias del Continente Americano) [1]',
'baidu' => 'BAIDU [1]',
'banamex' => 'BANAMEX (Citigroup) [1]',
'bbt' => 'BBT [1]',
'bcg' => 'BCG [1]',
'beats' => 'BEATS (Beats Elecronics) [1]',
'bharti' => 'BHARTI [1]',
'bms' => 'BMS [1]',
'bnl' => 'BNL [1]',
'pramerica' => 'PRAMERICA (Prudential Financial) [1]',
'ftr' => 'FTR (Frontier Communications Co.) [1]',
'gallup' => 'GALLUP [1]',
'nba' => 'NBA [1]',
'lplfinancial' => 'LPLFINANCIAL [1]',
'macys' => 'MACYS [1]',
'marshalls' => 'MARSHALLS (TJX Co. Inc.) [1]',
'maserati' => 'MASERATI [1]',
'mckinsey' => 'MCKINSEY [1]',
'merckmsd' => 'MERCKMSD (MSD Registry) [1]',
'metlife' => 'METLIFE [1]',
'mopar' => 'MOPAR (Chrysler Group) [1]',
'mormon' => 'MORMON [1]',
'moto' => 'MOTO [1]',
'movistar' => 'MOVISTAR [1]',
'msd' => 'MSD [1]',
'nationwide' => 'NATIONWIDE [1]',
'netflix' => 'NETFLIX [1]',
'lifestyle' => 'LIFESTYLE [1]',
'newholland' => 'NEWHOLLAND [1]',
'nexus' => 'NEXUS [1]',
'nike' => 'NIKE [1]',
'nissay' => 'NISSAY (Nippon Life Ins. Co.) [1]',
'norton' => 'NORTON [1]',
'nowtv' => 'NOWTV (Starbucks) [1]',
'oldnavy' => 'OLDNAVY (The GAP) [1]',
'ollo' => 'OLLO (Dish DBS Co.) [1]',
'ott' => 'OTT (Dish Network) [1]',
'pccw' => 'PCCW [1]',
'piaget' => 'PIAGET [1]',
'play' => 'PLAY [1]',
'pnc' => 'PNC (PNC Domain Co.) [1]',
'politie' => 'POLITIE [1]',
'lpl' => 'LPL [1]',
'lego' => 'LEGO [1]',
'gap' => 'GAP [1]',
'lefrak' => 'LEFRAK (LeFrak Org.) [1]',
'george' => 'GEORGE (Walmart) [1]',
'ggee' => 'GGEE (GMO Registry) [1]',
'giving' => 'GIVING [1]',
'goodyear' => 'GOODYEAR [1]',
'guge' => 'GUGE (Google) [1]',
'hbo' => 'HBO [1]',
'hdfc' => 'HDFC [1]',
'hgtv' => 'HGTV (Lifestyle Brands) [1]',
'hkt' => 'HKT [1]',
'homedepot' => 'HOMEDEPOT (Homer TLC) [1]',
'hughes' => 'HUGHES [1]',
'icbc' => 'ICBC (Bank of China) [1]',
'imdb' => 'IMDB [1]',
'intuit' => 'INTUIT [1]',
'iveco' => 'IVECO [1]',
'lds' => 'LDS [1]',
'lasalle' => 'LASALLE [1]',
'lancome' => 'LANCOME [1]',
'lancia' => 'LANCIA [1]',
'lamer' => 'LAMER (Estee Lauder) [1]',
'ladbrokes' => 'LADBROKES [1]',
'kuokgroup' => 'KUOKGROUP [1]',
'kindle' => 'KINDLE [1]',
'kerryproperties' => 'KERRYPROPERTIES [1]',
'kerryhotels' => 'KERRYHOTELS [1]',
'jeep' => 'JEEP [1]',
'zippo' => 'ZIPPO [1]',
'telecity' => 'TELECITY [Unknown]',
'epson' => 'EPSON [Unknown]',
'chloe' => 'CHLOE [Unknown]',
'statoil' => 'STATOIL [Unknown]'
);

$tld_info = array();
$tld_info['gTLD']	= 'List of Generic Top-Level-Domains (gTLD)';
$tld_info['ccTLD']	= 'List of Country code Top-Level-Domains (ccTLD)';
$tld_info['iTLD']	= 'List of Internationalized (IDN) Top-Level-Domains (iTLD)';
$tld_info['bgTLD']	= 'List of Branded Generic Top-Level-Domains (bgTLD)'; 

$section->addInput(new Form_Checkbox(
	'pfb_pytld',
	gettext('TLD Allow') . '(py)',
	'Enable',
	$pconfig['pfb_pytld'] === 'on' ? true:false,
	'on'
))->setHelp('Enable the Python TLD Allow feature (1,546 TLDs available). This will block all TLDs that are not specifically selected.'
		. '<div id="dnsbl_python_tld_allow_text">'
		. '<strong>By default</strong> \'ARPA\' and the pfSense TLD \'' . strtoupper($local_tld) . '\' are allowed.<br />'
		. 'If no TLDs are selected, the following are added by default [ COM, NET, ORG, EDU, CA, CO, IO ]<br /><br />'
		. 'Detailed TLD listings : <a target=_blank href="http://www.iana.org/domains/root/db">Root Zone Top-Level Domains.</a><br />'
		. 'Changes to this option will require a Force Update to take effect.<br /><br />'
		. '<strong>Legend</strong>:<br />'
		. '(*) TLD is used by atleast one DNSBL Feed in the Feeds Tab. Confirm the TLDs used by the selected Feeds.<br />'
		. '(!) TLD is listed by <a target=_blank href="https://www.spamhaus.org/statistics/tlds/">Spamhaus (Most Abused TLDs)</a><br /></div>'
		);

$section->addInput(new Form_Checkbox(
	'pfb_pytld_sort',
	'',
	'Enable',
	$pconfig['pfb_pytld_sort'] === 'on' ? true:false,
	'on'
))->setHelp('Enable to sort TLDs alphabetically');

$group = new Form_Group('TLD Group 1 (py)');
foreach (array('gTLD', 'ccTLD', 'iTLD', 'bgTLD') as $key => $tld_type) {
	if ($key == 2) {
		$group = new Form_Group('TLD Group 2 (py)');
	}
	$count = count($tld_list[$tld_type]);

	if ($pconfig['pfb_pytld_sort'] == 'on') {
		ksort($tld_list[$tld_type]);
	}

	$group->add(new Form_Select(
		'pfb_pytlds_' . strtolower($tld_type),
		'',
		$pconfig['pfb_pytlds_' . strtolower($tld_type)],
		$tld_list[$tld_type],
		TRUE
	))->setHelp("{$tld_info[$tld_type]}<br />Total TLD Count: [{$count}]")
	  ->setAttribute('size', '20')
	  ->addClass('pfb_python')
	  ->setWidth(4);

	if ($key == 1 || $key == 3) { 
		$section->add($group);
	}
}

$section->addInput(new Form_Checkbox(
	'pfb_idn',
	gettext('IDN Blocking') . '(py)',
	'Enable',
	$pconfig['pfb_idn'] === 'on' ? true:false,
	'on'
))->setHelp('Enable the Python IDN blocking feature (not Regex based). This will block all IDN\'s and domains that include \'xn--\'.');

$section->addInput(new Form_Checkbox(
	'pfb_regex',
	gettext('Regex Blocking') . '(py)',
	'Enable',
	$pconfig['pfb_regex'] === 'on' ? true:false,
	'on'
))->setHelp('Enable the Python Regex blocking feature. Regex list below: [Python Regex List]');

$section->addInput(new Form_Checkbox(
	'pfb_cname',
	gettext('CNAME Validation') . '(py)',
	'Enable',
	$pconfig['pfb_cname'] === 'on' ? true:false,
	'on'
))->setHelp('Enable the Python CNAME Validation feature. All CNAMES will be evaluated against DNSBL database and blocked.<br />'
		. 'Events are logged with a "_CNAME" suffix in the DNSBL Log.');

$section->addInput(new Form_Checkbox(
	'pfb_noaaaa',
	gettext('no AAAA') . '(py)',
	'Enable',
	$pconfig['pfb_noaaaa'] === 'on' ? true:false,
	'on'
))->setHelp('Enable the Python no-AAAA feature. This will block all (IPv6) AAAA DNS requests for the defined domains. no AAAA List below.');

$section->addInput(new Form_Checkbox(
	'pfb_gp',
	gettext('Python Group Policy') . '(py)',
	'Enable',
	$pconfig['pfb_gp'] === 'on' ? true:false,
	'on'
))->setHelp('Enable the Python Group Policy functionality to allow certain Local LAN IPs to bypass DNSBL');

$form->add($section);

$section = new Form_Section('Python Group Policy', 'Python_Group_Policy', COLLAPSIBLE|SEC_CLOSED);
$section->addInput(new Form_StaticText(
	NULL,
	'This is a preliminary DNSBL Group Policy configuration that will bypass DNSBL for the defined LAN IPs. (No Subnets allowed)'));

$section->addInput(new Form_Textarea(
	'pfb_gp_bypass_list',
	'Bypass IPs',
	$pconfig['pfb_gp_bypass_list']
))->removeClass('form-control')
  ->addClass('row-fluid col-sm-12')
  ->setAttribute('columns', '90')
  ->setAttribute('rows', '15')
  ->setAttribute('wrap', 'off')
  ->setAttribute('style', 'background:#fafafa; width: 100%')
  ->setHelp('Enter the Local LAN IPs (one per line) that will bypass Python DNSBL Blocking.<br />'
		. 'Changes to this option will require a Force Update to take effect.');

$form->add($section);

$regex_text = 'List of Python Regex\'s to block via DNSBL<br /><br />
		Enter a single regex per line.<br /><br />
		You may use "<strong>#</strong>" after each line for a Regex Description. IE:&emsp;regex (Regular Expression) # Regex Description<br /><br />
		Ensure a space is entered before the # character. Keep the Regex description less than 15 characters as it will be used in<br />
		the Alerts Tab. If no Description is entered a default Regex line number will be utilized.<br />
		This List is stored as \'Base64\' format in the config.xml file.<br /><br />
		Changes to this option will require a Force Update to take effect.';

$section = new Form_Section('Python Regex List', 'Python_regex_list', COLLAPSIBLE|SEC_CLOSED);
$section->addInput(new Form_Textarea(
	'pfb_regex_list',
	'Python Regex List',
	$pconfig['pfb_regex_list']
))->removeClass('form-control')
  ->addClass('row-fluid col-sm-12')
  ->setAttribute('columns', '90')
  ->setAttribute('rows', '15')
  ->setAttribute('wrap', 'off')
  ->setAttribute('style', 'background:#fafafa; width: 100%')
  ->setHelp($regex_text);

$form->add($section);

$noaaaa_text = 'List of no AAAA domains to block the (IPv6) AAAA DNS Resolution.<br /><br />
		Enter a single domain per line.<br />
		Prefix domain with a "." to apply wildcard no AAAA to all Sub-Domains. &emsp;IE: (.example.com)<br /><br />
		Any domain added to the no AAAA list, will never be filtered by any DNSBL python blocking.<br /><br />
		This List is stored as \'Base64\' format in the config.xml file.<br /><br />
		Changes to this option will require a Force Update to take effect.';

$section = new Form_Section('Python no AAAA  List', 'Python_noaaaa_list', COLLAPSIBLE|SEC_CLOSED);
$section->addInput(new Form_Textarea(
	'pfb_noaaaa_list',
	'Python no AAAA List',
	$pconfig['pfb_noaaaa_list']
))->removeClass('form-control')
  ->addClass('row-fluid col-sm-12')
  ->setAttribute('columns', '90')
  ->setAttribute('rows', '15')
  ->setAttribute('wrap', 'off')
  ->setAttribute('style', 'background:#fafafa; width: 100%')
  ->setHelp($noaaaa_text);

$form->add($section);

$section = new Form_Section('DNSBL Webserver Configuration');
$section->addInput(new Form_Select(
	'dnsbl_interface',
	gettext('Web Server Interface'),
	$pconfig['dnsbl_interface'],
	$options_dnsbl_interface_all
))->setHelp('Select the interface which DNSBL Web Server will Listen on.<br />'
	. 'Default: <strong>Localhost (ports 80/443)</strong> - Selected Interface should be a Local Interface only.');

$group = new Form_Group('DNSBL Virtual IP');
$vips = pfb_get_vips();
$group->add(new Form_Select(
	'pfb_dnsvip4',
	gettext('IPv4 VIP'),
	$pconfig['pfb_dnsvip4'],
	pfb_get_vip_options(AF_INET)
))->setWidth(4)->setHelp('IPv4 Virtual IP');
$group->add(new Form_Select(
	'pfb_dnsvip6',
	gettext('IPv6 VIP'),
	(!empty($pconfig['pfb_dnsvip6']) ? $pconfig['pfb_dnsvip6'] : 'none'),
	pfb_get_vip_options(AF_INET6)
))->setWidth(4)->setHelp('IPv6 Virtual IP (optional)');;
$group->setHelp('Select the DNSBL VIP address.%1$s'
		. 'Rejected DNS requests will be forwarded to this VIP.%1$s'
		. 'VIPs %2$smust be configured first%3$s at %4$sFirewall > Virtual IPs%5$s.',
	'<br />', '<strong>', '</strong>', '<a target="_blank" href="/firewall_virtual_ip.php">', '</a>'
);
$section->add($group);

$section->addInput(new Form_Input(
	'pfb_dnsport',
	gettext('Port'),
	'number',
	$pconfig['pfb_dnsport'],
	[ 'min' => 1, 'max' => 65535, 'placeholder' => 'Enter DNSBL Listening Port' ]
))->setHelp('Example ( 8081 )<br />Enter a &emsp;<strong>single PORT</strong> &emsp;that is in the range of 1 - 65535<br />'
		. 'This Port must not be in use by any other process.'
);

$section->addInput(new Form_Input(
	'pfb_dnsport_ssl',
	gettext('SSL Port'),
	'number',
	$pconfig['pfb_dnsport_ssl'],
	[ 'min' => 1, 'max' => 65535, 'placeholder' => 'Enter DNSBL VIP address' ]
))->setHelp('Example ( 8443 )<br />Enter a &emsp;<strong>single PORT</strong> &emsp;that is in the range of 1 - 65535<br />'
		. 'This Port must not be in use by any other process.'
);

// Add option to disable DNSBL logging in python and utilize the DNSBL Webserver (excluding nullblocking events)
$section->addInput(new Form_Checkbox(
	'pfb_py_nolog',
	gettext('DNSBL Event Logging') . '(py)',
	'Enable',
	$pconfig['pfb_py_nolog'] === 'on' ? true:false,
	'on'
))->setHelp('Disable event logging in Unbound python mode and utilize the DNSBL Webserver. Typically used when an upstream LAN DNS server is utilized.<br />'
	. 'Null blocked events will still be logged via python.');

$form->add($section);

$section = new Form_Section('DNSBL Configuration');

$group = new Form_Group('Permit Firewall Rules');
$group->add(new Form_Checkbox(
	'pfb_dnsbl_rule',
	NULL,
	gettext('Enable'),
	$pconfig['pfb_dnsbl_rule'] === 'on' ? true:false,
	'on'
))->setWidth(7)
  ->setHelp('This will create \'Floating\' Firewall permit rules to allow traffic from the Selected Interface(s) to access<br />'
		. 'the <strong>DNSBL Webserver</strong>. (ICMP and Webserver ports only).'
		. '<br /><br />'
		. 'This option is not designed to bypass DNSBL for the non-selected LAN segments<br />'
		. 'This option is only required for networks with multiple LAN Segments.');

$group->add(new Form_Select(
	'dnsbl_allow_int',
	NULL,
	$pconfig['dnsbl_allow_int'],
	$options_dnsbl_interface,
	TRUE
))->setAttribute('style', 'width: auto')
  ->setAttribute('size', $options_dnsbl_interface_cnt);
$section->add($group);

$section->addInput(new Form_Select(
	'global_log',
	'Global Logging/Blocking Mode',
	$pconfig['global_log'],
	$options_global_log
))->setHelp($options_global_log_txt)
  ->setAttribute('style', 'width: auto');

$section->addInput(new Form_Select(
	'dnsbl_webpage',
	'Blocked Webpage',
	$pconfig['dnsbl_webpage'],
	$options_dnsbl_webpage
))->sethelp('Default: <strong>dnsbl_default.php</strong><br />Select the DNSBL Blocked Webpage.<br /><br />'
	. 'Custom block web pages can be added to: <strong>/usr/local/www/pfblockerng/www/</strong> folder.')
  ->setAttribute('style', 'width: auto')
  ->setAttribute('size', $options_dnsbl_webpage_cnt);

$section->addInput(new Form_Checkbox(
	'pfb_cache',
	gettext('Resolver cache'),
	'Enable',
	$pconfig['pfb_cache'] === 'on' ? true:false,
	'on'
))->setHelp('Default: <strong>Enabled</strong><br />Enable the backup and restore of the DNS Resolver Cache on DNSBL Update|Reload|Cron events');

// Create page anchor for DNSBL Whitelist
$section->addInput(new Form_StaticText(
	NULL,
	'<div id="Whitelist"></div>'));

$form->add($section);

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

$section = new Form_Section('DNSBL Whitelist', 'DNSBL_Whitelist_customlist', COLLAPSIBLE|SEC_CLOSED);
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
		Note: The domains listed in the TOP1M *may* be malicious in nature, consider limiting this feature.<br /><br />
		Whitelist(s) available:<br />

		<ul>
			<li><a target="_blank" href="https://tranco-list.eu/">Tranco TOP1M</a></li>
			<li><a target="_blank" href="https://s3-us-west-1.amazonaws.com/umbrella-static/index.html">Cisco Umbrella TOP1M</a></li>
			<li><a target="_blank" href="https://aws.amazon.com/alexa-top-sites/">Alexa TOP1M (out-of-date)</a></li>
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
	$options_alexa_type
))->setHelp('Default: Tranco TOP1M. To change the TOP1M type, select type and Save, followed by a \'Force Reload - DNSBL\'');

$section->addInput(new Form_Select(
	'alexa_count',
	gettext('Domain count'),
	$pconfig['alexa_count'],
	$options_alexa_count
))->sethelp('<strong>Default: Top 1k</strong><br />Select the <strong>number</strong> of TOP1M \'Top Domain global ranking\' to whitelist.');

$section->addInput(new Form_Select(
	'alexa_inclusion',
	gettext('TLD Inclusion'),
	$pconfig['alexa_inclusion'],
	$options_alexa_inclusion,
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
	'The TLD Blacklist is used to block a whole TLD (IE: pw).<br /><br />'
	. '<span class="text-danger">Note:</span><br />'
	. 'DO NOT add domains to the TLD Whitelist, Instead, add them to any DNSBL Group Customlist or to a Source URL (Feed) that you manage.<br /><br />'
	. 'The TLD Whitelist is used to allow access to the specific domain/sub-domains that is blocked by a TLD Blacklist; while blocking all others.<br />'
	. 'TLD Blacklist/Whitelist: A <strong>static</strong> zone entry is used in the DNS Resolver for this feature, therefore no Alerts will be generated.<br />'
	. '<br />When the \'python Blocking mode\' feature is enabled. The TLD Whitelist is not utilized and instead uses the DNSBL Whitelist.'
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

if ($pfb['dnsbl_py_blacklist']) {
	$tld_whitelist_text = "<span class=\"text-danger\">TLD Whitelist is not utilized for Unbound python mode! Use DNSBL Whitelist instead.</span><br /><br />{$tld_whitelist_text}";
}

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

$section = new Form_Section('DNSBL IPs');
$section->addInput(new Form_StaticText(
	NULL,
	'When IPs are found in any Domain based Feed, these IPs will be added to the <strong>pfB_DNSBL_IP</strong> IP Aliastable and<br />'
	. ' a firewall rule will be added to block those IPs.<br /><br />'
	. '<span class="text-danger">Note: </span>To utilize this feature, select the appropriate List Action and define the Inbound/Outbound Interfaces in the <strong>IP Tab</strong>.'
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

				<strong><u>\'Alias Deny\' Rule:</u></strong><br />
				<strong>\'Alias Deny\'</strong> rules create an <a href="/firewall_aliases.php">alias</a> for the list (and do nothing else).
				This enables a pfBlockerNG list to be used by name, in any firewall rule or pfSense function, as desired.
			</div>';

$section->addInput(new Form_Select(
	'action',
	gettext('List Action'),
	$pconfig['action'],
	$options_action
))->setHelp($list_action_text);

$section->addInput(new Form_Select(
	'aliaslog',
	gettext('Enable Logging'),
	$pconfig['aliaslog'],
	$options_aliaslog
))->sethelp('Default: <strong>Enable</strong><br />Select - Logging to Status: System Logs: FIREWALL ( Log )<br />'
		. 'This can be overriden by the \'Global Logging\' Option in the General Tab.'
);
$form->add($section);

// Print Advanced Firewall Rule Settings (Inbound and Outbound) section
foreach (array( 'In' => 'Source', 'Out' => 'Destination') as $adv_mode => $adv_type) {
	$advmode = strtolower($adv_mode);

	$section = new Form_Section("DNSBL IPs - Advanced {$adv_mode}bound Firewall Rule Settings", "adv{$advmode}boundsettings", COLLAPSIBLE|SEC_CLOSED);
	$section->addInput(new Form_StaticText(
		"dnsbl_ip_text_{$adv_type}",
		"<span class=\"text-danger\">Note:</span>&nbsp; In general, Auto-Rules are created as follows:<br />
			<dl class=\"dl-horizontal\">
				<dt>{$adv_mode}bound</dt><dd>'any' port, 'any' protocol, 'any' destination and 'any' gateway</dd>
			</dl>
			Configuring the Adv. {$adv_mode}bound Rule settings, will allow for more customization of the {$adv_mode}bound Auto-Rules.")
		)->addClass('dnsbl_ip');

	$section->addInput(new Form_Checkbox(
		'autoaddrnot_' . $advmode,
		"Invert {$adv_type}",
		NULL,
		$pconfig['autoaddrnot_' . $advmode] === 'on' ? true:false,
		'on'
	))->setHelp("Option to invert the sense of the match. ie - Not (!) {$adv_type} Address(es)")
	  ->addClass('dnsbl_ip');

	$group = new Form_Group("Custom DST Port");
	$group->add(new Form_Checkbox(
		'autoports_' . $advmode,
		'Custom DST Port',
		NULL,
		$pconfig['autoports_' . $advmode] === 'on' ? true:false,
		'on'
	))->setHelp('Enable')
	  ->setWidth(2)
	  ->addClass('dnsbl_ip');

	$group->add(new Form_Input(
		'aliasports_' . $advmode,
		'Custom Port',
		'text',
		$pconfig["aliasports_{$advmode}"]
	))->setHelp("<a target=\"_blank\" href=\"/firewall_aliases.php?tab=port\">Click Here to add/edit Aliases</a>
			Do not manually enter port numbers.<br />Do not use 'pfB_' in the Port Alias name."
	)->setWidth(8)
	 ->addClass('dnsbl_ip');
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
	))->setHelp('Enable')->setWidth(1)
	  ->addClass('dnsbl_ip');

	$group->add(new Form_Checkbox(
		'autonot_' . $advmode,
		NULL,
		NULL,
		$pconfig["autonot_{$advmode}"] === 'on' ? true:false,
		'on'
	))->setHelp('Invert')->setWidth(1)
	  ->addClass('dnsbl_ip');

	$group->add(new Form_Input(
		'aliasaddr_' . $advmode,
		"Custom {$custom_location}",
		'text',
		$pconfig['aliasaddr_' . $advmode]
	))->sethelp('<a target="_blank" href="/firewall_aliases.php?tab=ip">Click Here to add/edit Aliases</a>'
		. 'Do not manually enter Addresses(es).<br />Do not use \'pfB_\' in the \'IP Network Type\' Alias name.<br />'
		. "Select 'invert' to invert the sense of the match. ie - Not (!) {$custom_location} Address(es)"
	)->setWidth(8)
	 ->addClass('dnsbl_ip');
	$section->add($group);

	$group = new Form_Group('Custom Protocol');
	$group->add(new Form_Select(
		'autoproto_' . $advmode,
		NULL,
		$pconfig['autoproto_' . $advmode],
		$options_autoproto_in
	))->setHelp("<strong>Default: any</strong><br />Select the Protocol used for {$adv_mode}bound Firewall Rule(s).<br />
		<span class=\"text-danger\">Note:</span>&nbsp;Do not use 'Any' with Adv. {$adv_mode}bound Rules as it will bypass these settings!")
	  ->addClass('dnsbl_ip');
	$section->add($group);

	$group = new Form_Group('Custom Gateway');
	$group->add(new Form_Select(
		'agateway_' . $advmode,
		NULL,
		$pconfig['agateway_' . $advmode],
		$options_agateway_in
	))->setHelp("Select alternate Gateway or keep 'default' setting.")
	  ->addClass('dnsbl_ip');

	$section->add($group);
	$form->add($section);
}

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

function enable_tld() {
	if ($('#pfb_tld').prop('checked')) {
		$('#TLD_Exclusion').show();
		$('#TLD_BW_list').show();
	} else {
		$('#TLD_Exclusion').hide();
		$('#TLD_BW_list').hide();
	}
}

function enable_ports() {
	if ($('#dnsbl_interface').val() == 'lo0') {
		hideInput('pfb_dnsport', true);
		hideInput('pfb_dnsport_ssl', true);
	} else {
		hideInput('pfb_dnsport', false);
		hideInput('pfb_dnsport_ssl', false);
	}
}

function enable_python() {

	var python = true;
	if ($('#dnsbl_mode').val() == 'dnsbl_unbound') {
		var python = false;
	};

	if (python && $('#pfb_py_block').prop('checked')) {
		hideCheckbox('pfb_dnsbl_sync', true);
	} else {
		hideCheckbox('pfb_dnsbl_sync', false);
	}

	hideCheckbox('pfb_control', !python);
	hideCheckbox('pfb_py_reply', !python);
	hideCheckbox('pfb_py_block', !python);
	hideCheckbox('pfb_hsts', !python);
	hideCheckbox('pfb_idn', !python);
	hideCheckbox('pfb_regex', !python);
	hideCheckbox('pfb_noaaaa', !python);
	hideCheckbox('pfb_cname', !python);
	hideCheckbox('pfb_gp', !python);
	hideInput('pfb_regex_list', !python);
	hideInput('pfb_noaaaa_list', !python);
	hideInput('pfb_gp_bypass_list', !python);
	hideCheckbox('pfb_pytld', !python);
	hideCheckbox('pfb_pytld_sort', !python);
	hideMultiClass('pfb_python', !python);
	hideCheckbox('pfb_py_nolog', !python);

	if (!python) {
		$('.dnsbl_unbound_tld').show();
		$('#tldwhitelist').attr('readonly', false).css('background-color', '#FAFAFA');
	} else {
		$('.dnsbl_unbound_tld').hide();
		$('#tldwhitelist').attr('readonly', true).css('background-color', '#DEDEDE');
	}

	if ($('#dnsbl_mode').val() == 'dnsbl_python' && $('#pfb_py_block').prop('checked') == false) {
		$('.dnsbl_unbound_tld').show();
	}
}

function enable_python_pytld() {
	if ($('#dnsbl_mode').val() == 'dnsbl_python') {
		if ($('#pfb_pytld').prop('checked')) {
			hideCheckbox('pfb_pytld_sort', false);
			hideMultiClass('pfb_python', false);
			$('#dnsbl_python_tld_allow_text').show();
		} else {
			hideCheckbox('pfb_pytld_sort', true);
			hideMultiClass('pfb_python', true);
			$('#dnsbl_python_tld_allow_text').hide();
		}
	} else {
		hideMultiClass('pfb_python', true);
	}
}

function enable_python_regex() {
	if ($('#dnsbl_mode').val() == 'dnsbl_python' && $('#pfb_regex').prop('checked')) {
		$('#Python_regex_list').show();
	} else {
		$('#Python_regex_list').hide();
	}
}

function enable_python_noaaaa() {
	if ($('#dnsbl_mode').val() == 'dnsbl_python' && $('#pfb_noaaaa').prop('checked')) {
		$('#Python_noaaaa_list').show();
	} else {
		$('#Python_noaaaa_list').hide();
	}
}

function enable_python_gp() {
	if ($('#dnsbl_mode').val() == 'dnsbl_python' && $('#pfb_gp').prop('checked')) {
		$('#Python_Group_Policy').show();
	} else {
		$('#Python_Group_Policy').hide();
	}
}

function enable_dnsblip() {
	if ($('#action').val() != 'Disabled') {
		hideInput('aliaslog', false);
		$('#advinboundsettings').show();
		$('#advoutboundsettings').show();
	} else {
		hideInput('aliaslog', true);
		$('#advinboundsettings').hide();
		$('#advoutboundsettings').hide();
	}
}

events.push(function(){
	$('#pfb_tld').click(function() {
		enable_tld();
	});
	enable_tld();

	$('#dnsbl_interface').click(function() {
		enable_ports();
	});
	enable_ports();

	$('#dnsbl_mode').click(function() {
		enable_python();
		enable_python_pytld();
		enable_python_regex();
		enable_python_noaaaa();
		enable_python_gp();
	});
	enable_python();

	$('#pfb_py_block').click(function() {
		enable_python();
		enable_python_pytld();
	});

	$('#pfb_pytld').click(function() {
		enable_python_pytld();
	});
	enable_python_pytld();

	$('#pfb_regex').click(function() {
		enable_python_regex();
	});
	enable_python_regex();

	$('#pfb_noaaaa').click(function() {
		enable_python_noaaaa();
	});
	enable_python_noaaaa();

	$('#pfb_gp').click(function() {
		enable_python_gp();
	});
	enable_python_gp();

	$('#action').click(function() {
		enable_dnsblip();
	});
	enable_dnsblip();

	$('label[class="col-sm-2 control-label"]').each(function() {
		var found = $(this).text();
		if (found.indexOf('(py)') >= 0) {
			$(this).html(found.replace('(py)', '&emsp;<i class="fa-solid fa-bolt" title="DNSBL Python"></i>'));
		}
	});
});

//]]>
</script>
<script src="pfBlockerNG.js" type="text/javascript"></script>
<?php include('foot.inc');?>
