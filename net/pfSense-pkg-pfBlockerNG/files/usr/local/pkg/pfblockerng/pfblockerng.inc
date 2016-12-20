<?php
/*
	pfBlockerNG.inc

	pfBlockerNG
	Copyright (c) 2015-2016 BBcan177@gmail.com
	All rights reserved.

	Based upon pfBlocker by
	Copyright (c) 2011-2012 Marcello Coutinho
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:


	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.


	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.

*/

require_once('util.inc');
require_once('functions.inc');
require_once('pkg-utils.inc');
require_once('pfsense-utils.inc');
require_once('globals.inc');
require_once('services.inc');
require_once('service-utils.inc');
if (file_exists('/usr/local/pkg/pfblockerng/pfblockerng_extra.inc')) {
	require_once('/usr/local/pkg/pfblockerng/pfblockerng_extra.inc');	// 'include functions' not yet merged into pfSense
}

global $g, $config, $pfb;

// Folders
$pfb['dbdir']		= "{$g['vardb_path']}/pfblockerng";
$pfb['aliasdir']	= "{$g['vardb_path']}/aliastables";
$pfb['logdir']		= "{$g['varlog_path']}/pfblockerng";
$pfb['etdir']		= "{$pfb['dbdir']}/ET";
$pfb['nativedir']	= "{$pfb['dbdir']}/native";
$pfb['denydir']		= "{$pfb['dbdir']}/deny";
$pfb['matchdir']	= "{$pfb['dbdir']}/match";
$pfb['permitdir']	= "{$pfb['dbdir']}/permit";
$pfb['origdir']		= "{$pfb['dbdir']}/original";
$pfb['dnsdir']		= "{$pfb['dbdir']}/dnsbl";
$pfb['dnsorigdir']	= "{$pfb['dbdir']}/dnsblorig";
$pfb['dnsalias']	= "{$pfb['dbdir']}/dnsblalias";
$pfb['geoipshare']	= '/usr/local/share/GeoIP';
$pfb['ccdir']		= '/usr/local/share/GeoIP/cc';
$pfb['ccdir_tmp']	= '/tmp/geoip_cc';
$pfb['dnsbl_tmp']	= '/tmp/dnsbl_tmp';
$pfb['dnsbl_tmpdir']	= '/tmp/DNSBL_TMP';

// Application Paths
$pfb['grep']	= '/usr/bin/grep';
$pfb['awk']	= '/usr/bin/awk';
$pfb['cut']	= '/usr/bin/cut';
$pfb['sed']	= '/usr/bin/sed';
$pfb['cat']	= '/bin/cat';
$pfb['ls']	= '/bin/ls';
$pfb['pfctl']	= '/sbin/pfctl';

// Folder Array
$pfb['folder_array'] = array(	"{$pfb['dbdir']}", "{$pfb['logdir']}", "{$pfb['ccdir']}", "{$pfb['origdir']}", "{$pfb['nativedir']}",
				"{$pfb['denydir']}", "{$pfb['matchdir']}","{$pfb['permitdir']}", "{$pfb['aliasdir']}",
				"{$pfb['dnsdir']}", "{$pfb['dnsorigdir']}", "{$pfb['dnsalias']}");
// Files
$pfb['errlog']		= "{$pfb['logdir']}/error.log";
$pfb['extraslog']	= "{$pfb['logdir']}/extras.log";
$pfb['log']		= "{$pfb['logdir']}/pfblockerng.log";
$pfb['dnslog']		= "{$pfb['logdir']}/dnsbl.log";
$pfb['dnserrlog']	= "{$pfb['logdir']}/dnsbl_error.log";
$pfb['master']		= "{$pfb['dbdir']}/masterfile";
$pfb['supptxt']		= "{$pfb['dbdir']}/pfbsuppression.txt";
$pfb['dnsbl_info']	= "{$pfb['dbdir']}/dnsbl_info";
$pfb['dnsbl_tld_data']	= '/usr/local/pkg/pfblockerng/dnsbl_tld';
$pfb['dnsbl_tld_txt']	= "{$pfb['dnsdir']}/DNSBL_TLD";
$pfb['dnsbl_remove']	= '/tmp/dnsbl_remove';
$pfb['dnsbl_conf']	= '/var/unbound/pfb_dnsbl_lighty.conf';
$pfb['dnsbl_cert']	= '/var/unbound/dnsbl_cert.pem';
$pfb['script']		= '/usr/local/pkg/pfblockerng/pfblockerng.sh';
$pfb['aliasarchive']	= '/usr/local/etc/aliastables.tar.bz2';
$pfb['geoip_tmp']	= '/tmp/pfb_continent';

// Unbound files and folders
$pfb['dnsbl_file']	= '/var/unbound/pfb_dnsbl';	// Filename Extension not referenced
$pfb['dnsbldir']	= '/var/unbound';

// Array definitions
$pfb['continents'] = array (	'Africa'		=> 'pfB_Africa',
				'Antartica'		=> 'pfB_Antartica',
				'Asia'			=> 'pfB_Asia',
				'Europe'		=> 'pfB_Europe',
				'North America'		=> 'pfB_NAmerica',
				'Oceania'		=> 'pfB_Oceania',
				'South America'		=> 'pfB_SAmerica',
				'Top Spammers'		=> 'pfB_Top',
				'Proxy and Satellite'	=> 'pfB_PS'
				);

// Base rule array
$pfb['base_rule_reg'] = array('ipprotocol' => 'inet');

// Floating rules, base rule array
$pfb['base_rule_float'] = array('quick' => 'yes', 'floating' => 'yes', 'ipprotocol' => 'inet');

// Define Arrays for managing the mastefile
foreach (array('existing', 'actual') as $pftype) {
	$pfb[$pftype]['match']	= array('type' => 'match',	'folder' => "{$pfb['matchdir']}");
	$pfb[$pftype]['permit']	= array('type' => 'permit',	'folder' => "{$pfb['permitdir']}");
	$pfb[$pftype]['deny']	= array('type' => 'deny',	'folder' => "{$pfb['denydir']}");
	$pfb[$pftype]['native']	= array('type' => 'native',	'folder' => "{$pfb['nativedir']}");
	$pfb[$pftype]['dnsbl']	= array('type' => 'dnsbl',	'folder' => "{$pfb['dnsdir']}");
}

// Default cURL options
$pfb['curl_defaults'] = array(  CURLOPT_USERAGENT	=> 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 Chrome/43.0.2357.65 Safari/537.36',
				CURLOPT_SSL_CIPHER_LIST	=> 'TLSv1.2, TLSv1.1, TLSv1',
				CURLOPT_FOLLOWLOCATION	=> true,
				CURLOPT_SSL_VERIFYPEER	=> true,
				CURLOPT_SSL_VERIFYHOST	=> true,
				CURLOPT_FRESH_CONNECT	=> true,
				CURLOPT_FILETIME	=> true,
				CURLOPT_CONNECTTIMEOUT	=> 15,
				);

$pfb['rfc7231'] = array(100 => '100 Continue',			101 => '101 Switching Protocols',		102 => '102 Processing',

			200 => '200 OK',			201 => '201 Created',				202 => '202 Accepted',
			203 => '203 Non-Authoritative Info',	204 => '204 No Content',			205 => '205 Reset Content',
			206 => '206 Partial Content',		207 => '207 Multi-Status',			208 => '208 Already Reported',
			226 => '226 IM Used',

			300 => '300 Multiple Choices',		301 => '301 Moved Permanently',			302 => '302 Found',
			303 => '303 See Other',			304 => '304 Not Modified',			305 => '305 Use Proxy',
			306 => '306 Switch Proxy',		307 => '307 Temporary Redirect',		308 => '308 Permanent Redirect',

			400 => '400 Bad Request',		401 => '401 Unauthorized',			402 => '402 Payment Required',
			403 => '403 Forbidden',			404 => '404 Not Found',				405 => '405 Method Not Allowed',
			406 => '406 Not Acceptable',		407 => '407 Proxy Authentication Required',	408 => '408 Request Timeout',
			409 => '409 Conflict',			410 => '410 Gone',				411 => '411 Length Required',
			412 => '412 Precondition Failed',	413 => '413 Request Entity Too Large',		414 => '414 Request-URI Too Long',
			415 => '415 Unsupported Media Type',	416 => '416 Requested Range Not Satisfiable',	417 => '417 Expectation Failed',
			418 => '418 Im a teapot',		419 => '419 Authentication Timeout',		420 => '420 Method Failure',
			421 => '421 Misdirected Request',	422 => '422 Unprocessable Entity',		423 => '423 Locked',
			424 => '424 Failed Dependency',		426 => '426 Upgrade Required',			428 => '428 Precondition Required',
			429 => '429 Too Many Requests',		431 => '431 Request Header Fields Large',	440 => '440 Login Timeout',
			444 => '444 No Response',		449 => '449 Retry With',			450 => '450 Blocked Windows Parental Controls',
			451 => '451 Unavailable Legal Reasons',	494 => '494 Request Header too Large',		495 => '495 Cert Error',
			496 => '496 No Cert',			497 => '497 HTTP to HTTPS',			498 => '498 Token expired/invalid',
			499 => '499 Client Closed Request',

			500 => '500 Internal Server Error',	501 => '501 Not Implemented',			502 => '502 Bad Gateway',
			503 => '503 Service Unavailable',	504 => '504 Gateway Timeout',			505 => '505 HTTP Version Not Supported',
			506 => '506 Variant Also Negotiates',	507 => '507 Insufficient Storage',		508 => '508 Loop Detected',
			509 => '509 Bandwidth Limit Exceeded',	510 => '510 Not Extended',			511 => '511 Network Authentication Required',
			598 => '598 Network read timeout error',599 => '599 Network connect timeout error'
			);


// [ $pfb ] pfBlockerNG global array. This needs to be called to get the updated settings.
function pfb_global() {
	global $g, $config, $pfb;

	// Create folders if not exist.
	foreach ($pfb['folder_array'] as $folder) {
		safe_mkdir("{$folder}", 0755);
	}

	// General variables
	$pfb['config']		= $config['installedpackages']['pfblockerng']['config'][0];
	$pfb['dnsblconfig']	= $config['installedpackages']['pfblockerngdnsblsettings']['config'][0];

	$pfb['enable']		= $pfb['config']['enable_cb'];			// Enable/Disable of pfBlockerNG
	$pfb['keep']		= $pfb['config']['pfb_keep'];			// Keep blocklists on pfBlockerNG Disable
	$pfb['supp']		= $pfb['config']['suppression'];		// Enable Suppression
	$pfb['logmax']		= $pfb['config']['log_maxlines'];		// Max lines in pfblockerng.log file
	$pfb['cc']		= $pfb['config']['database_cc'];		// Disable Country database CRON updates
	$pfb['min']		= $pfb['config']['pfb_min']		?: '0';	// User defined CRON start minute
	$pfb['hour']		= $pfb['config']['pfb_hour']		?: '0';	// Start hour of the scheduler
	$pfb['interval']	= $pfb['config']['pfb_interval']	?: '1';	// Hour cycle for scheduler
	$pfb['24hour']		= $pfb['config']['pfb_dailystart']	?: '0';	// Start hour of the 'Once a day' schedule
	$pfb['maxmind_locale']	= $pfb['config']['maxmind_locale']	?: 'en';// MaxMind Localized Language setting
	$pfb['iplocal']		= $config['interfaces']['lan']['ipaddr'];	// Lan IP address
	$pfb['dnsbl']		= $pfb['dnsblconfig']['pfb_dnsbl'];		// Enabled state of DNSBL
	$pfb['dnsbl_port']	= $pfb['dnsblconfig']['pfb_dnsport'];		// Lighttpd web server http port setting
	$pfb['dnsbl_port_ssl']	= $pfb['dnsblconfig']['pfb_dnsport_ssl'];	// Lighttpd web server https port setting
	$pfb['dnsbl_alexa']	= $pfb['dnsblconfig']['alexa_enable'];		// Alexa whitelist

	// Max daily download failure threshold (default to '0' unlimited failures)
	$pfb['skipfeed']	= $pfb['config']['skipfeed'] != '' ? $pfb['config']['skipfeed'] : 0;

	if (isset($config['unbound']['enable'])) {
		$pfb['unbound_state'] = 'on';
	} else {
		$pfb['unbound_state'] = '';
	}

	// cURL - system proxy server setttings, if configured
	if (!empty($config['system']['proxyurl'])) {
		$pfb['curl_defaults']['CURLOPT_PROXY']			= $config['system']['proxyurl'];
		if (!empty($config['system']['proxyport'])) {
			$pfb['curl_defaults']['CURLOPT_PROXYPORT']	= $config['system']['proxyport'];
		}
		if (!empty($config['system']['proxyuser']) && !empty($config['system']['proxypass'])) {
			$pfb['curl_defaults']['CURLOPT_PROXYAUTH']	= 'CURLAUTH_ANY | CURLAUTH_ANYSAFE';
			$pfb['curl_defaults']['CURLOPT_PROXYUSERPWD']	= "{$config['system']['proxyuser']}:{$config['system']['proxypass']}";
		}
	}

	// Set pfBlockerNG to disabled on 're-install'
	if (isset($pfb['install']) && $pfb['install']) {
		$pfb['enable'] = $pfb['dnsbl'] = '';
		$pfb['install']	= FALSE;
	}
}

pfb_global();

// DNSBL Lighttpd HTTPS Daemon (Scans Lighttpd dnsbl_error.log for requested https domain names)
if (isset($argv[1]) && $argv[1] == 'dnsbl') {
	set_time_limit(0);
	pfb_livetail($pfb['dnserrlog'], 'dnsbl');
	exit;
}


// Function to convert string to lowercase (Not for comment line section)
function pfb_strtolower($line) {
	if (strpos($line, '#') === FALSE) {
		return trim(strtolower($line));
	}
	return trim($line);
}


// Function to decode alias custom entry box.
// Default (False, True): Return as string with comments
function pfbng_text_area_decode($text, $mode=FALSE, $type=TRUE) {

	if ($mode) {
		$custom = array();
	}

	$customlist = explode("\r\n", base64_decode($text));
	if (!empty($customlist)) {
		foreach ($customlist as $line) {
			if (substr(trim($line), 0, 1) != '#' && !empty($line)) {

				// '#' commentline found
				if (strpos($line, '#') !== FALSE) {
					if ($mode) {
						if ($type) {
							// Split line into two elements (array)
							$custom[] = array_map('pfb_strtolower', preg_split('/(?=#)/', $line));
						} else {
							// Remove commentline
							$custom[] = trim(strtolower(strstr($line, '#', TRUE)));
						}
					} else {
						// Remove commentline	
						$custom .= trim(strtolower(strstr($line, '#', TRUE))) . "\n";
					}
				}

				// No '#' commentline found
				else {
					$line = trim(strtolower($line));

					if ($mode) {
						if ($type) {
							$custom[][0] = $line;
						} else {
							$custom[] = $line;
						}
					} else {
						$custom .= "{$line}\n";
					}
				}
			}
		}
		return $custom;
	}
}


// Manage log files line limit
function pfb_log_mgmt() {
	global $pfb;
	pfb_global();

	if ($pfb['logmax'] == 'nolimit') {
		// Skip Log mgmt
	} else {
		foreach (array('log', 'errlog', 'dnslog', 'extraslog') as $logtype) {
			if (file_exists($pfb[$logtype])) {
				exec("/usr/bin/tail -n {$pfb['logmax']} {$pfb[$logtype]} > /tmp/pfblog; /bin/mv -f /tmp/pfblog $pfb[$logtype]");
			}
		}
	}
}


// Record log messsages to pfBlockerNG log file and/or error log file.
function pfb_logger($log, $logtype) {
	global $g, $pfb;

	$now = date('m/d/y H:i:s', time());

	// Only log timestamp if new
	if (strpos($log, 'NOW') !== FALSE) {
		if ($now == $pfb['pnow']) {
			$log = str_replace(' [ NOW ]', '', "{$log}");
		} else {
			$log = str_replace('NOW', $now, "{$log}");
		}
		$pfb['pnow'] = "{$now}";
	}

	// Print to pfBlockerNG log and Error log
	if ($logtype == 2) {
		@file_put_contents("{$pfb['log']}", "{$log}", FILE_APPEND);
		@file_put_contents("{$pfb['errlog']}", "{$log}", FILE_APPEND);

	// Print to Extras log
	} elseif ($logtype == 3) {
		@file_put_contents("{$pfb['extraslog']}", "{$log}", FILE_APPEND);

	// Print to screen and Extras log
	} elseif ($logtype == 4) {
		
		// Print to screen
		if (!$g['pfblockerng_install'] && !$pfb['extras_update']) {
			print "{$log}";
		}
		
		// Print to Installation window
		if ($g['pfblockerng_install']) {
			update_status("{$log}");
		}
		@file_put_contents("{$pfb['extraslog']}", "{$log}", FILE_APPEND);

	// Print to pfBlockerNG log
	} else {
		@file_put_contents("{$pfb['log']}", "{$log}", FILE_APPEND);
	}
}


// Determine 'list' details
function pfb_determine_list_detail($list='', $header='', $confconfig='', $key='') {
	global $config, $pfb, $pfbarr;
	$pfbarr = array();

	switch($list) {
		case 'Deny_Both':
		case 'Deny_Inbound':
		case 'Deny_Outbound':
		case 'Alias_Deny':
			$pfbarr = array('adv' => TRUE, 'folder' => "{$pfb['denydir']}", 'orig' => "{$pfb['origdir']}", 'reuse' => "{$pfb['reuse']}");
			break;
		case 'unbound':
			$pfbarr = array('adv' => FALSE, 'folder' => "{$pfb['dnsdir']}", 'orig' => "{$pfb['dnsorigdir']}", 'reuse' => "{$pfb['reuse_dnsbl']}");
			break;
		case 'Permit_Both':
		case 'Permit_Inbound':
		case 'Permit_Outbound':
		case 'Alias_Permit':
			$pfbarr = array('adv' => FALSE, 'folder' => "{$pfb['permitdir']}", 'orig' => "{$pfb['origdir']}", 'reuse' => "{$pfb['reuse']}");
			break;
		case 'Match_Both':
		case 'Match_Inbound':
		case 'Match_Outbound':
		case 'Alias_Match':
			$pfbarr = array('adv' => FALSE, 'folder' => "{$pfb['matchdir']}", 'orig' => "{$pfb['origdir']}", 'reuse' => "{$pfb['reuse']}");
			break;
		case 'Alias_Native':
			$pfbarr = array('adv' => FALSE, 'folder' => "{$pfb['nativedir']}", 'orig' => "{$pfb['origdir']}", 'reuse' => "{$pfb['reuse']}");
			break;
	}

	// Collect proper alias table description (alias only vs autorules)
	if (strpos($list, 'Alias') !== FALSE) {
		$pfbarr['descr'] = '';
	} else {
		$pfbarr['descr'] = ' Auto ';
	}

	// Determine length of header to format log output
	$tabtype = strlen($header);
	if ($tabtype > 19) {
		$pfbarr['logtab'] = '';
	} elseif ($tabtype > 11) {
		$pfbarr['logtab'] = "\t";
	} elseif ($tabtype < 4) {
		$pfbarr['logtab'] = "\t\t\t";
	} else {
		$pfbarr['logtab'] = "\t\t";
	}

	if (!empty($confconfig)) {
		// Configure autoports/protocol and auto destination if required.
		$conf_config = $config['installedpackages'][$confconfig]['config'][$key];
		$autotype = array( 'autoports' => 'aliasports', 'autoaddr' => 'aliasaddr');
		foreach (array('_out', '_in') as $dir) {

			$pfbarr['aproto' . $dir]	= $conf_config['autoproto' . $dir];
			$pfbarr['anot' . $dir]		= $conf_config['autonot' . $dir];
			$pfbarr['aaddrnot' . $dir]	= $conf_config['autoaddrnot' . $dir];
			$pfbarr['agateway' . $dir]	= $conf_config['agateway' . $dir];

			foreach ($autotype as $akey => $atype) {
				if ($conf_config[$akey . $dir] == 'on' && isset($config['aliases']['alias'])) {
					foreach ($config['aliases']['alias'] as $palias) {
						if ($palias['name'] == $conf_config[$atype . $dir]) {
							if (!empty($palias['address'])) {
								$dalias = "{$atype}{$dir}";
								switch($akey) {
									case 'autoports':
										$ctype = "aports{$dir}";
										$pfbarr[$ctype] = $conf_config[$dalias];
										break;
									case 'autoaddr':
										$ctype = "aaddr{$dir}";
										$pfbarr[$ctype] = $conf_config[$dalias];
										break;
								}
							}
						}
					}
				}
			}
		}
	}

	// Force 'Alias Native' setting to any Alias with 'Advanced Inbound/Outbound -Invert src/dst' settings.
	// This will bypass Deduplication and Reputation features.
	if ($pfbarr['aaddrnot_in'] == 'on' || $pfbarr['aaddrnot_out'] == 'on') {
		$pfbarr['adv'] = FALSE;
		$pfbarr['folder'] = "{$pfb['nativedir']}";
	}

	return $pfbarr;
}


// Determine if cron task requires updating
function pfblockerng_cron_exists($crontask, $pfb_min, $pfb_hour) {
	global $config;

	if (isset($config['cron']['item'])) {
		foreach ($config['cron']['item'] as $item) {
			if (strpos($item['command'], $crontask) !== FALSE) {
				if ($item['minute'] != $pfb_min) {
					return FALSE;
				}
				if ($pfb_hour == 'maxmind' && !empty($item['hour'])) {
					// MaxMind hour is randomized. Skip comparison.
					return TRUE;
				}
				if ($item['hour'] != $pfb_hour) {
					return FALSE;
				}
				return TRUE;
			}
		}
	}
	return FALSE;
}


// Calculate the cron task base hour setting
function pfb_cron_base_hour() {
	global $pfb;

	switch($pfb['interval']) {
		case 'Disabled':
		case 1:
			return;
			break;
		case 2:
			$j = 11; $k = 2;
			break;
		case 3:
			$j = 7; $k = 3;
			break;
		case 4:
			$j = 5; $k = 4;
			break;
		case 6:
			$j = 3; $k = 6;
			break;
		case 8:
			$j = 2; $k = 8;
			break;
		case 12:
			$j = 1; $k = 12;
			break;
		case 24:
			return array($pfb['24hour']);
			break;
		default:
			$pfb['interval'] = 1;
			return;
	}

	$shour	= intval(substr($pfb['hour'], 0, 2));
	$sch	= strval($shour);

	for ($i=0; $i < $j; $i++) {
		$shour += $k;
		if ($shour >= 24) {
			$shour -= 24;
		}
		$sch .= ',' . strval($shour);
	}

	$sch = explode(',', $sch);
	sort($sch);
	return $sch;
}


// Collect 'gateway(s)' and 'gateway group(s)' for Adv. In/Outbound customizations
function pfb_get_gateways() {
	global $config;

	$gateway = $gateway_item = $gateway_group = array();
	if (is_array($config['gateways']['gateway_item'])) {
		$gateway_item = $config['gateways']['gateway_item'];
	}
	if (is_array($config['gateways']['gateway_group'])) {
		$gateway_group = $config['gateways']['gateway_group'];
	}
	$gateway = array_merge($gateway_item, $gateway_group);

	return $gateway;
}


// Collect all Interfaces for DNSBL Firewall Permit Rule
function pfb_build_if_list() {
	global $config;
	$pfb_list = array();

	if (is_array($config['ifgroups']['ifgroupentry'])) {
		foreach ($config['ifgroups']['ifgroupentry'] as $ifgen) {
			$pfb_list[] = array('name' => $ifgen['ifname'], 'value' => $ifgen['ifname']);
		}
	}

	foreach (get_configured_interface_with_descr() as $ifent => $ifdesc) {
		if ($ifdesc != 'WAN') {
			$pfb_list[] = array('name' => $ifdesc, 'value' => $ifent);
		}
	}

	if (ipsec_enabled()) {
		$pfb_list[] = array('name' => 'IPsec', 'value' => 'enc0');
	}

	if ($config['openvpn']['openvpn-server'] || $config['openvpn']['openvpn-client']) {
		$pfb_list[] = array('name' => 'OpenVPN', 'value' => 'openvpn');
	}

	return $pfb_list;
}


// Create suppression alias
function pfb_create_suppression_alias() {
	global $config;

	// Reload config.xml to get any recent changes
	$config = parse_config(true);

	// Collect existing pfSense alias(es)
	if (isset($config['aliases']['alias'])) {
		$new_aliases = &$config['aliases']['alias'];
	}

	// Create new pfBlockerNGSuppress alias
	$new_aliases[] = array( 'name'		=> 'pfBlockerNGSuppress',
				'address'	=> '',
				'descr'		=> 'pfBlockerNG Suppression List (24|32 CIDR only)',
				'type'		=> 'network',
				'detail'	=> ''
				);
	write_config('pfBlockerNG: saving suppression alias');
}


// Create suppression file from alias
function pfb_create_suppression_file() {
	global $config, $pfb;

	// Find pfBlockerNGSuppress array ID number
	$pfbfound = FALSE;
	if (isset($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $key => $alias) {
			if ($alias['name'] == 'pfBlockerNGSuppress') {
				$pfbfound = TRUE;
				break;
			}
		}

		if ($pfbfound) {
			$pfb_suppress = str_replace(' ', "\n", $config['aliases']['alias'][$key]['address']);
			if (!empty($pfb_suppress)) {
				@file_put_contents("{$pfb['supptxt']}", $pfb_suppress, LOCK_EX);
			} else {
				unlink_if_exists("{$pfb['supptxt']}");
			}
		} else {
			// Delete suppression file if alias is empty.
			unlink_if_exists("{$pfb['supptxt']}");
		}
	}

	// Call function to create suppression alias.
	if (!$pfbfound) {
		pfb_create_suppression_alias();
	}
}


// Function to update DNSBL aliases and widget stats
function dnsbl_alias_update($mode, $alias, $pfbfolder, $lists_dnsbl_current, $alias_cnt) {
	global $pfb;

	if ($mode == 'update') {
		// Create master alias file
		$pfb_output = @fopen("{$pfb['dnsalias']}/{$alias}", 'w');
		foreach ($lists_dnsbl_current as $clist) {
			if (($handle = @fopen("{$pfbfolder}/{$clist}.txt", 'r')) !== FALSE) {
				while (($line = @fgets($handle, 3072)) !== FALSE) {
					@fwrite($pfb_output, $line);
				}
			}
			@fclose($handle);
		}
		@fclose($pfb_output);

		// Update DNSBL alias statistics
		$dns_now = date('M d H:i:s', time());
		$pfbfound = FALSE;
		foreach ($pfb['dnsbl_info_stats'] as $key => $line) {
			// Update existing alias stats
			if ($line[0] == "{$alias}") {
				$pfbfound = TRUE;
				$pfb['dnsbl_info_stats'][$key][1] = "{$dns_now}";
				$pfb['dnsbl_info_stats'][$key][2] = "{$alias_cnt}";
				break;
			}
		}

		if (!$pfbfound) {
			$pfb['dnsbl_info_stats'][] = explode(',', "{$alias},{$dns_now},{$alias_cnt},0");
		}
	}
	elseif ($mode == 'disabled') {
		// Record disabled alias statistics
		$pfbfound = FALSE;
		if (!empty($pfb['dnsbl_info_stats'])) {
			foreach ($pfb['dnsbl_info_stats'] as $line) {
				if ($line[0] == "{$alias}") {
					$pfbfound = TRUE;
					break;
				}
			}
		}

		if (!$pfbfound) {
			$dns_now = date('M d H:i:s', time());
			$pfb['dnsbl_info_stats'][] = explode(',', "{$alias},{$dns_now},disabled,0");
		}
	}
}


// Function to update DNSBL_IP aliastable
function dnsbl_ip_aliastable_update() {
	global $pfb;

	$dnsbl_aliastable = TRUE;

	// Collect DNSBL IP addresses into 'pfB_DNSBLIP' aliastable
	$dnsbl_ip = glob("{$pfb['dnsdir']}/*.ip");
	if (!empty($dnsbl_ip)) {

		if ($pfb['updateip'] || !file_exists("{$pfb['aliasdir']}/pfB_DNSBLIP.txt")) {
			$pfb['updateip'] = TRUE;
			$pfb_ips = @fopen("{$pfb['aliasdir']}/pfB_DNSBLIP.txt", 'w');
			foreach ($dnsbl_ip as $d_ip) {
				if (($handle = @fopen("{$d_ip}", 'r')) !== FALSE) {
					while (($line = @fgets($handle, 1024)) !== FALSE) {
						@fwrite($pfb_ips, $line);
					}
				}
				@fclose($handle);
			}
			@fclose($pfb_ips);
		}

		// Update DNSBL_IPs aliastable
		if ($pfb['updateip'] && file_exists("{$pfb['aliasdir']}/pfB_DNSBLIP.txt")) {
			$result = '';
			$list_cnt = exec("{$pfb['grep']} -c ^ {$pfb['aliasdir']}/pfB_DNSBLIP.txt");
			exec("{$pfb['pfctl']} -t pfB_DNSBLIP -T replace -f {$pfb['aliasdir']}/pfB_DNSBLIP.txt 2>&1", $result);

			$log  = "\n[ DNSBL_IP ]\t\t Updating aliastable [ NOW ]... \n  ";
			$log .= implode($result);
			$log .= "\n  Total IP count = {$list_cnt}";
			pfb_logger("{$log}\n", 1);
		}
		else {
			$dnsbl_aliastable = FALSE;
		}
	}
	else {
		$dnsbl_aliastable = FALSE;
	}

	if (!$dnsbl_aliastable) {
		pfb_logger("\n  DNSBL: Flush DNSBL_IP", 1);
		// Flush DNSBL IPs aliastable when empty
		exec("{$pfb['pfctl']} -t pfB_DNSBLIP -T flush 2>&1", $result);
		// Add empty placeholder '1.1.1.1' to aliastable
		exec("{$pfb['pfctl']} -t pfB_DNSBLIP -T add 1.1.1.1");
		@file_put_contents("{$pfb['aliasdir']}/pfB_DNSBLIP.txt", "1.1.1.1\n", LOCK_EX);
	}
}


// Function to save DNSBL Alias statistics
function dnsbl_save_stats() {
	global $pfb;

	// Save alias statistics to file (Remove any feeds that are not referenced)
	$handle = @fopen("{$pfb['dnsbl_info']}", 'w');
	@fwrite($handle, "# Keeping this file open in a file editor will interrupt DNSBL!\n");
	if (!empty($pfb['dnsbl_info_stats'])) {
		foreach ($pfb['dnsbl_info_stats'] as $alias) {
			if (in_array($alias[0], $pfb['alias_dnsbl_all'])) {
				@fputcsv($handle, $alias);
			}
		}
	}
	@fclose($handle);
}


// Create DNSBL VIP and NAT rules, lighttpd conf and services
function pfb_create_dnsbl($mode) {
	global $config, $pfb;

	// Reload config.xml to get any recent changes
	$config = parse_config(true);

	$new_nat = $new_vip = $pfb_ex_nat = $pfb_ex_vip = $dnsbl_ex_nat = $dnsbl_ex_vip = array();
	$pfb['dnsbl_vip_changed'] = $pfbupdate = FALSE;

	if ((!empty($pfb['dnsbl_port']) && !empty($pfb['dnsbl_port_ssl']) && !empty($pfb['dnsbl_vip']) && $mode == 'enable') || $mode == 'disable') {

		// DNSBL NAT rules generation
		$pfbfound = FALSE;
		// Collect existing pfSense NAT rules
		if (isset($config['nat']['rule'])) {
			foreach ($config['nat']['rule'] as $ex_nat) {
				if (strpos($ex_nat['descr'], 'pfB DNSBL') !== FALSE) {
					// Collect DNSBL NAT rules
					$dnsbl_ex_nat[] = $ex_nat;
					$pfbfound = TRUE;
				} else {
					// Collect all 'other' NAT rules
					$pfb_ex_nat[] = $ex_nat;
				}
			}
		}

		if ($mode == 'enable') {
			// Generate new DNSBL NAT per DNSBL listening ports
			$selected_ports = array("{$pfb['dnsbl_port']}" => '80', "{$pfb['dnsbl_port_ssl']}" => '443');
			foreach ($selected_ports as $port => $lport) {
				$dnsbl_new_nat[] =	array ( 'source'		=> array('any'  => ''),
								'destination'		=> array('address' => "{$pfb['dnsbl_vip']}", 'port' => "{$lport}"),
								'protocol'		=> 'tcp',
								'target'		=> '127.0.0.1',
								'local-port'		=> "{$port}",
								'interface'		=> "{$pfb['dnsbl_iface']}",
								'descr'			=> 'pfB DNSBL - DO NOT EDIT',
								'associated-rule-id'	=> '',
								'natreflection'		=> 'purenat'
								);
			}

			// Compare existing to new and if they are not identical update
			if ($dnsbl_ex_nat !== $dnsbl_new_nat) {
				$pfbupdate = TRUE;
				$new_nat = array_merge($pfb_ex_nat, $dnsbl_new_nat);
			} else {
				$new_nat = array_merge($pfb_ex_nat, $dnsbl_ex_nat);
			}
		} else {
			$new_nat = array_merge($pfb_ex_nat, $new_nat);
			// Update when DNSBL NAT found but is now disabled.
			if ($pfbfound) {
				$pfbupdate = TRUE;
			}
		}

		// DNSBL VIP generation
		$dnsbl_new_vip[] = array (	'mode'		=> 'ipalias',
						'interface'	=> "{$pfb['dnsbl_iface']}",
						'descr'		=> 'pfB DNSBL - DO NOT EDIT',
						'type'		=> 'single',
						'subnet_bits'	=> '32',
						'subnet'	=> "{$pfb['dnsbl_vip']}"
						);

		$pfbfound = FALSE;
		// Collect existing pfSense VIPs
		if (isset($config['virtualip']['vip'])) {
			foreach ($config['virtualip']['vip'] as $ex_vip) {
				if (strpos($ex_vip['descr'], 'pfB DNSBL') !== FALSE) {
					// Collect DNSBL VIP
					$dnsbl_ex_vip[] = $ex_vip;
					$pfbfound = TRUE;
				} else {
					// Collect all 'other' VIPs
					$pfb_ex_vip[] = $ex_vip;
				}
			}
		}

		if ($mode == 'enable') {
			// Compare existing to new and if they are not identical update
			if ($dnsbl_ex_vip !== $dnsbl_new_vip) {
				$pfb['dnsbl_vip_changed'] = TRUE;
				$pfbupdate = TRUE;
				$new_vip = array_merge($pfb_ex_vip, $dnsbl_new_vip);
			} else {
				$new_vip = array_merge($pfb_ex_vip, $dnsbl_ex_vip);
			}
		} else {
			$new_vip = array_merge($pfb_ex_vip, $new_vip);
			// Update when DNSBL NAT found but is now disabled.
			if ($pfbfound) {
				$pfbupdate = TRUE;
			}
		}

		// Only create DNSBL lighttpd conf file if not exists, or listening port changed
		if (!file_exists($pfb['dnsbl_conf']) && $mode == 'enable' || $pfbupdate && $mode == 'enable') {

// Create Lighttpd conf file for DNSBL
$pfb_conf = <<<EOF
#
#pfBlockerNG Lighttpd DNSBL configuration file
#
server.bind			= "0.0.0.0"
server.port			= "{$pfb['dnsbl_port']}"
server.event-handler		= "freebsd-kqueue"
server.network-backend		= "freebsd-sendfile"
server.dir-listing		= "disable"
server.document-root		= "/usr/local/www/pfblockerng/www/"
server.errorlog			= "/var/log/pfblockerng/dnsbl_error.log"
server.pid-file			= "/var/run/dnsbl.pid"
server.modules			= ( "mod_access", "mod_fastcgi", "mod_rewrite" )

server.indexfiles		= ( "index.php" )
mimetype.assign			= ( ".html" => "text/html", ".gif" => "image/gif" )
url.access-deny			= ( "~", ".inc" )
fastcgi.server			= ( ".php" => ( "localhost" => ( "socket" => "/var/run/php-fpm.socket", "broken-scriptfilename" => "enable" ) ) )

debug.log-condition-handling	= "enable"

\$HTTP["host"] =~ ".*" {
	url.rewrite-once = ( ".*" => "index.php" )
}

\$SERVER["socket"] == "0.0.0.0:{$pfb['dnsbl_port_ssl']}" {
	ssl.engine		= "enable"
	ssl.pemfile		= "{$pfb['dnsbl_cert']}"
	ssl.use-sslv2		= "disable"
	ssl.use-sslv3		= "disable"
	ssl.honor-cipher-order	= "enable"
	ssl.cipher-list		= "AES128+EECDH:AES256+EECDH:AES128+EDH:AES256+EDH:AES128-SHA:AES256-SHA:!aNULL:!eNULL:!DSS"

	\$HTTP["host"] =~ ".*" {
		url.rewrite-once = ( ".*" => "index.php" )
	}
}

EOF;
			$log = "\nSaving new DNSBL web server configuration to port [ {$pfb['dnsbl_port']} and {$pfb['dnsbl_port_ssl']} ]\n";
			pfb_logger("{$log}", 1);

			$pfbupdate = TRUE;
			@file_put_contents($pfb['dnsbl_conf'], $pfb_conf, LOCK_EX);
			unset($pfb_conf);
		}

		// Update config.xml, if changes required
		if ($pfbupdate) {
			$log = "Saving pfSense config...\n";
			pfb_logger("{$log}", 1);
			$config['nat']['rule'] = $new_nat;
			$config['virtualip']['vip'] = $new_vip;
			write_config('pfBlockerNG: saving DNSBL changes');

			// Execute ifconfig to enable VIP address
			$iface = get_real_interface("{$pfb['dnsbl_iface']}");
			if (!empty($iface) && !empty($pfb['dnsbl_vip'])) {
				mwexec('/sbin/ifconfig ' . escapeshellarg("{$iface}") . ' inet '. escapeshellarg("{$pfb['dnsbl_vip']}") . '/32 alias');
				$log = "VIP address configured. Widget Packet statistics reset.\n";
				pfb_logger("{$log}", 1);
				filter_configure();
			} else {
				$log = "DNSBL ifconfig error : Interface:{$iface}, VIP:{$pfb['dnsbl_iface']}\n";
				pfb_logger("{$log}", 1);
			}
		}
	}

	// Save settings, restart services as required
	if ($mode == 'enable') {
		// Remove any existing and create link for DNSBL lighttpd executable
		unlink_if_exists('/usr/local/sbin/lighttpd_pfb');
		link('/usr/local/sbin/lighttpd', '/usr/local/sbin/lighttpd_pfb');

		// Create DNSBL SSL certificate
		if (!file_exists ("{$pfb['dnsbl_cert']}")) {
			$log = "New DNSBL Cert Created.\n";
			pfb_logger("{$log}", 1);
			exec("/usr/bin/openssl req -new -x509 -keyout {$pfb['dnsbl_cert']} -out {$pfb['dnsbl_cert']} -days 3650 -nodes");
		}

		if ($pfbupdate || !is_service_running('dnsbl')) {
			$log = "Restarting Service DNSBL...\n";
			pfb_logger("{$log}", 1);
			restart_service('dnsbl');
		}
	} else {
		// Remove DNSBL VIP address 
		$iface = get_real_interface("{$pfb['dnsbl_iface']}");
		if (!empty($iface) && !empty($pfb['dnsbl_vip'])) {
			mwexec('/sbin/ifconfig ' . escapeshellarg($iface) . ' delete ' . escapeshellarg("{$pfb['dnsbl_vip']}"));
			filter_configure();
		}

		if (is_service_running('dnsbl')) {
			pfb_logger("Stop Service DNSBL\n", 1);
			stop_service('dnsbl');
		}
	}
}


// Define DNSBL Unbound include settings
function pfb_unbound_dnsbl($mode) {
	global $config, $pfb;

	// Reload config.xml to get any recent changes
	$config = parse_config(true);

	$pfbupdate = FALSE;
	$unbound_include = "server:include: {$pfb['dnsbl_file']}.conf";

	// Collect Unbound custom option pfSense conf line
	$pfb['unboundconfig']	= &$config['unbound']['custom_options'];
	if (!empty($pfb['unboundconfig'])) {
		$unbound_custom = base64_decode($pfb['unboundconfig']);
	} else {
		$unbound_custom = '';
	}

	// Determine if DNSBL include line exists
	if (!empty($unbound_custom)) {
		// Append DNSBL Unbound pfSense conf integration
		if (!strstr($unbound_custom, 'pfb_dnsbl.conf')) {
			if ($mode == 'enabled') {
				$pfbupdate = TRUE;
				$unbound_custom .= "\n{$unbound_include}";
				$log = "\nDNSBL - Adding to existing Unbound custom options\n";
			}
		}
		else {
			// Remove DNSBL Unbound pfSense conf integration when disabled
			if ($mode == 'disabled') {
				$custom = explode ("\n", $unbound_custom);
				foreach ($custom as $key => $line) {
					if (strpos($line, 'pfb_dnsbl.conf') !== FALSE) {
						$pfbupdate = TRUE;
						$log = "\nDNSBL - Removing DNSBL Unbound custom options\n";
						unset($custom[$key]);
					}
				}
				$unbound_custom = implode("\n", $custom);
			}
		}
	}
	else {
		// Add DNSBL Unbound pfSense conf integration
		if ($mode == 'enabled') {
			$pfbupdate = TRUE;
			$unbound_custom = "{$unbound_include}";
			$log = "\nDNSBL - Adding Unbound custom 'include' option\n";
		}
	}

	// Update config.xml, if changes required
	if ($pfbupdate) {
		pfb_logger("{$log}", 1);
		$unbound_custom = base64_encode(str_replace("\r\n", "\n", $unbound_custom));
		$pfb['unboundconfig'] = "{$unbound_custom}";
		write_config('pfBlockerNG: saving Unbound config');
	}
}


// Search for TLD match
function tld_search($dparts, $j, $k) {
	global $tlds;

	$tld = implode('.', array_slice($dparts, -$j, $j, TRUE));
	if (isset($tlds[$tld])) {
		return implode('.', array_slice($dparts, -$k, $k, TRUE));
	}
	return NULL;
}


// Function to determine if each Domain is a Sub-Domain ('transparent' zone) or a whole Domain ('redirect' zone)
function tld_analysis() {
	global $pfb, $tlds;

	pfb_logger("Executing TLD\n", 1);

	$domain_cnt = 0;
	$pfb_found = FALSE;				// Flag to determine if TLD 'redirect' zones found
	rmdir_recursive("{$pfb['dnsbl_tmpdir']}");
	safe_mkdir("{$pfb['dnsbl_tmpdir']}");

	unlink_if_exists("{$pfb['dnsbl_file']}.tsp");
	unlink_if_exists("{$pfb['dnsbl_tld_txt']}.txt");
	unlink_if_exists("{$pfb['dnsbl_tld_txt']}.bk");
	unlink_if_exists("{$pfb['dnsbl_remove']}.tsp");
	unlink_if_exists("{$pfb['dnsbl_remove']}");
	unlink_if_exists("{$pfb['dnsbl_tmp']}.sup");
	unlink_if_exists("{$pfb['dnsbl_tmp']}.adup");

	// Master TLD Domain list
	if (file_exists("{$pfb['dnsbl_tld_data']}")) {
		$tlds = array_flip(file("{$pfb['dnsbl_tld_data']}", FILE_IGNORE_NEW_LINES));
	} else {
		pfb_logger("\n ** TLD Master data file missing. Terminating TLD **\n", 1);
		return;
	}

	// Collect TLD Blacklist(s). If configured the whole TLD will be blocked
	$tld_blacklist = array_flip(pfbng_text_area_decode($pfb['dnsblconfig']['tldblacklist'], TRUE, FALSE));

	// Collect TLD Whitelist(s). If configured, create a 'static local-zone' Resolver entry
	$whitelist = pfbng_text_area_decode($pfb['dnsblconfig']['tldwhitelist'], TRUE, FALSE);
	$tld_whitelist = array();
	if (!empty($tld_blacklist) && !empty($whitelist)) {
		foreach ($whitelist as $list) {

			$parts = array_map('trim', explode('|', $list));
			if (strpos($parts[0], '.') !== FALSE && is_ipaddr($parts[1])) {

				$dparts = explode('.', $parts[0]);
				$dcnt   = count($dparts);

				for ($i=($dcnt-1); $i > 0; $i--) {
					$d_query = implode('.', array_slice($dparts, -$i, $i, TRUE));
					if (isset($tlds[$d_query])) {
						$tld = $d_query;
						break;
					}
					else {
						$tld = end($dparts);
					}
				}
			}

			if (!empty($tld) && is_ipaddr($parts[1])) {
				$tld_whitelist[$tld][] = array($parts[0], $parts[1]);
				pfb_logger(" TLD Whitelist {$parts[0]} | {$parts[1]}\n", 1);
				$tld = '';
			} else {
				pfb_logger("\n TLD Whitelist - Missing data | {$list}\n", 1);
			}
		}
	}

	// Process TLD Blacklist(s). If configured the whole TLD will be blocked
	if (!empty($tld_blacklist)) {

		$tld_list = '';
		pfb_logger(" Blocking full TLD/Sub-Domain(s)... |", 1);

		foreach ($tld_blacklist as $tld => $key) {

			unset($tld_blacklist[$tld]);			// Remove old entry
			$tld = trim($tld, '.');				// Remove any leading/trailing dots
			$tld_blacklist[$tld] = '';			// Add new TLD entry

			$dnsbl_file = "{$pfb['dnsbl_tmpdir']}/DNSBL_{$tld}.txt";
			if (!file_exists($dnsbl_file)) {

				$tld_cnt++;
				$pfb_found = TRUE;
				$tld_cnt = @max((array((substr_count($tld, '.') +1), $tld_cnt) ?: 1));	// Collect List of TLDs and save to DNSBL folder

				// If a 'TLD Whitelist' exists, use 'static local-zone'
				if (isset($tld_whitelist[$tld])) {

					pfb_logger("{$tld}(static)|", 1);
					$dnsbl_line = "local-zone: \"{$tld}\" \"static\"\n";
					$whitelist = $tld_whitelist[$tld];

					foreach ($whitelist as $list) {
						$dnsbl_line .= "local-data: \"{$list[0]} A {$list[1]}\"\n";

						// Collect list of TLDs and save to DNSBL folder
						$tld_list .= "{$tld} | {$list[0]} A {$list[1]}\n";
					}
				}

				// Create 'redirect' zone for whole TLD
				else {
					pfb_logger("{$tld}|", 1);
					$dnsbl_line = "local-zone: \"{$tld}\" redirect local-data: \"{$tld} 60 IN A {$pfb['dnsbl_vip']}\"\n";

					// Collect List of TLDs and save to DNSBL folder
					$tld_list .= "{$tld}\n";
				}

				@file_put_contents($dnsbl_file, $dnsbl_line, LOCK_EX);

				// Add TLD to remove file (To be removed from 'transparent' zone)
				@file_put_contents("{$pfb['dnsbl_remove']}.tsp", ".{$tld} 60\n", FILE_APPEND | LOCK_EX);

				// Remove any 'TLD Blacklists' from the 'TLD master list'
				if (isset($tlds[$tld])) {
					unset($tlds[$tld]);
				}
			}
		}

		// Save a list of TLDs in DNSBL folder (DNSBL total line count verification)
		if (!empty($tld_list)) {
			@file_put_contents("{$pfb['dnsbl_tld_txt']}.txt", $tld_list, LOCK_EX);

			// Add 'TLD' to Alias/Feeds array
			$pfb['tld_update']['DNSBL_TLD']['feeds']	= array('DNSBL_TLD');
			$pfb['tld_update']['DNSBL_TLD']['count']	= $tld_cnt;
			$pfb['alias_dnsbl_all'][]			= 'DNSBL_TLD';
		}
		else {
			unlink_if_exists("{$pfb['dnsbl_tld_txt']}.bk");
			unlink_if_exists("{$pfb['dnsbl_tld_txt']}.txt");
		}
		pfb_logger(" completed\n", 1);
	}
	else {
		unlink_if_exists("{$pfb['dnsbl_tld_txt']}.bk");
		unlink_if_exists("{$pfb['dnsbl_tld_txt']}.txt");
	}

	// Collect TLD Exclusion list and remove any 'TLD Exclusions' from the 'TLD master list'
	$exclusion = pfbng_text_area_decode($pfb['dnsblconfig']['tldexclusion'], TRUE, FALSE);
	if (!empty($exclusion)) {
		foreach ($exclusion as $key => $exclude) {
			$exclude = trim($exclude, '.');		// Remove any leading/trailing dots

			// Collect exclusion
			if (strpos($exclude, '.') !== FALSE) {
				$tld_exclusion[] = $exclude;
			}

			// Remove Exclusion from TLDS array
			if (isset($tlds[$exclude])) {
				unset($tlds[$exclude]);
			}
		}
	}

	pfb_logger("TLD analysis", 1);

	// [ $pfb['dnsbl_file']}.tsp	] Final DNSBL output file (using 'transparent' zone)
	// [ $pfb['dnsbl_remove']	] File of Sub-Domains to be removed (from 'redirect' zone)

	// Analyse DNSBL: 1) 'redirect' zone for whole Domain 2) 'transparent' zone only
	if (($fhandle = @fopen("{$pfb['dnsbl_file']}.conf", 'r')) !== FALSE) {
		while (($line = @fgets($fhandle, 3072)) !== FALSE) {

			if (empty($line)) {
				continue;
			}

			// Display progress indicator
			if ($domain_cnt % 100000 == 0) {
				// Memory limitation exceeded for 'redirect' zones
				if ($domain_cnt >= $pfb['domain_max_cnt']) {
					pfb_logger('x', 1);
				} else {
					pfb_logger('.', 1);
				}
			}

			$eparts	= explode(' ', str_replace('"', '', $line));
			$domain = $eparts[1];

			$dparts = explode('.', $domain);
			$dcnt	= count($dparts);
			$tld	= end($dparts);
			$dfound = '';

			// Determine if TLD exists in TLD Blacklist
			if (!empty($tld_blacklist)) {

				// Determine minimum 'tld level' for loop efficiency
				$min_cnt = @min(array($tld_cnt, ($dcnt -1)));

				for ($i=1; $i <= $min_cnt; $i++) {
					$d_query = implode('.', array_slice($dparts, -$i, $i, TRUE));
					if (isset($tld_blacklist[$d_query])) {
						continue 2;			// Whole TLD being blocked
					}
				}
			}

			if ($domain_cnt <= $pfb['domain_max_cnt']) {

				// Search TLD master list (Levels 1-4)
				// If Domain is a Sub-Domain, create 'transparent' zone. Otherwise create 'redirect' zone
				switch($dcnt) {
					case ($dcnt > 5):
						break;
					case '5':
						$dfound = tld_search($dparts, 4, 5);
						break;
					case '4':
						$dfound = tld_search($dparts, 3, 4);
						break;
					case '3':
						$dfound = tld_search($dparts, 2, 3);
						break;
					case '2':
						$dfound = implode('.', array_slice($dparts, -2, 2, TRUE));
						break;
				}
			}

			// If Domain is in the TLD Exclusion(s), use 'transparent zone'
			if (!empty($domain) && !empty($tld_exclusion) && in_array($domain, $tld_exclusion)) {
				$dfound = '';
			}

			// Create 'redirect' zone for Domain
			if (!empty($dfound)) {

				$pfb_found = TRUE;
				$domain_line = "local-zone: \"{$dfound}\" redirect local-data: \"{$dfound} 60 IN A {$pfb['dnsbl_vip']}\"\n";
				@file_put_contents("{$pfb['dnsbl_file']}.tsp", $domain_line, FILE_APPEND | LOCK_EX);

				// Add Domain to remove file (for 'redirect zone' Domains)
				// This removes any of these sub-domains
				@file_put_contents("{$pfb['dnsbl_remove']}", ".{$dfound} 60\n\"{$dfound} 60\n", FILE_APPEND | LOCK_EX);

				// Add Domain to remove file (for 'transparent zone' Domains)
				// This will remove this domains and sub-domains, listed in the 'transparent' zone
				@file_put_contents("{$pfb['dnsbl_remove']}.tsp", ".{$dfound} 60\n", FILE_APPEND | LOCK_EX);
			}

			// Create 'transparent zone' for Sub-Domain
			else {
				if (!empty($tld)) {
					$dnsbl_file = "{$pfb['dnsbl_tmpdir']}/DNSBL_{$tld}.txt";

					// Create a temp file for each TLD. w/ 'transparent' header followed by each 'local-data' line
					if (!file_exists($dnsbl_file)) {
						$dnsbl_header = "local-zone: \"{$tld}\" \"transparent\"\n";
						@file_put_contents($dnsbl_file, $dnsbl_header, LOCK_EX);
					}
					@file_put_contents($dnsbl_file, $line, FILE_APPEND | LOCK_EX);
				}
				else {
					$oline = htmlentities($line);
					pfb_logger("\nDebug: Missing TLD: {$oline}", 1);
				}
			}

			// Increment Domain counter
			$domain_cnt++;
		}
	}
	else {
		pfb_logger(" TLD Input file could not be opened.\n", 1);
	}

	@fclose($fhandle);
	unset($tlds, $tld_blacklist, $tld_exclusion);

	// TLD 'redirect zones' found. Finalize TLD function
	if ($pfb_found) {
		$log  = " completed\n";
		// Print TLD exceedance error message
		if ($domain_cnt >= $pfb['domain_max_cnt']) {
			$log .= "** TLD Domain count exceeded. [ {$pfb['domain_max_cnt']} ] All subsequent Domains listed as-is **\n";
		}
		$log .= 'Finalizing TLD... ';
		pfb_logger("{$log}", 1);

		// Create a csv list of 'recently updated' DNSBL Feeds, as ordered by User
		$dnsbl_feeds = '';
		foreach ($pfb['tld_update'] as $alias => $data) {
			foreach ($data['feeds'] as $feed) {
				$dnsbl_feeds .= "{$feed},";
			}
		}

		// Execute Domain De-duplication
		exec("{$pfb['script']} domaintld x x x {$dnsbl_feeds} >> {$pfb['log']} 2>&1");

		// Update DNSBL Alias and Widget Stats
		foreach ($pfb['tld_update'] as $alias => $data) {

			// Create Alias summary file for each DNSBL Alias
			$lists_dnsbl_current = array();
			foreach ($data['feeds'] as $feed) {
				$lists_dnsbl_current[] = "{$feed}";
			}
			dnsbl_alias_update('update', $alias, $pfb['dnsdir'], $lists_dnsbl_current, $data['count']);
		}
	}
	else {
		pfb_logger(" no changes\n", 1);
	}

	// Save DNSBL Alias statistics
	dnsbl_save_stats();
}


// Validate Unbound conf
function pfb_validate_unbound($mode) {
	global $g, $pfb;

	if ($mode == 'enabled') {
		$ext = '.bk';
	} else {
		$ext = '.*';	// Remove all DNSBL Unbound files
	}

	pfb_logger(" completed\n", 1);

	// Execute TLD analysis, if configured
	if ($pfb['dnsbl'] == 'on' && !$pfb['save'] && $pfb['dnsbl_tld']) {
		tld_analysis();
	}
	else {
		unlink_if_exists("{$pfb['dnsbl_tld_txt']}.bk");
		unlink_if_exists("{$pfb['dnsbl_tld_txt']}.txt");
	}

	// Perform Validation of DNSBL Database. Disable validation if user defined.
	pfb_logger("Validating database...", 1);

	// Calculate memory usage (percentage)
	$pfb_totalmem			= get_single_sysctl('vm.stats.vm.v_page_count');
	if ($pfb_totalmem > 0) {
		$pfb_inactivemem	= get_single_sysctl('vm.stats.vm.v_inactive_count');
		$pfb_cachedmem		= get_single_sysctl('vm.stats.vm.v_cache_count');
		$pfb_freemem		= get_single_sysctl('vm.stats.vm.v_free_count');
		$pfb_usedmem		= $pfb_totalmem - ($pfb_inactivemem + $pfb_cachedmem + $pfb_freemem);
		$pfb_memusage		= round(($pfb_usedmem * 100) / $pfb_totalmem, 0);
	} else {
		$pfb_memusage		= 100;
	}

	// Execute final unbound-checkconf if memory usage is below 40% (Avoid memory exhaustion)
	if ($pfb_memusage < 40) {
		$result = array();
		exec("/usr/local/sbin/unbound-checkconf {$pfb['dnsbldir']}/unbound.tmp 2>&1", $result);
		pfb_logger(" completed [ NOW ]\n", 1);
	}
	else {
		pfb_logger(" Skipped [ NOW ]\n", 1);
		$result = array('unbound-checkconf: no errors');
	}

	if (preg_grep("/unbound-checkconf: no errors/", $result)) {
		@rename("{$pfb['dnsbldir']}/unbound.tmp", "{$pfb['dnsbldir']}/unbound.conf");

		// Reload Unbound Service
		if (is_service_running('unbound')) {
			pfb_logger('Reloading Unbound', 1);

			$cache_dumpfile = '/var/tmp/unbound_cache';
			unlink_if_exists("{$cache_dumpfile}");
			$chroot_cmd = "/usr/sbin/chroot -u unbound -g unbound / /usr/local/sbin/unbound-control -c {$g['unbound_chroot_path']}/unbound.conf";

			exec("{$chroot_cmd} dump_cache > {$cache_dumpfile} 2>&1");
			pfb_logger('.', 1);

			exec("{$chroot_cmd} reload 2>&1", $result, $retval);
			pfb_logger('.', 1);

			if ($retval != 0) {
				$log = '. Failed to Reload...';
				if (file_exists("{$pfb['dnsbl_file']}.bk")) {
					$log .= ' Restoring previous database..';
					@rename("{$pfb['dnsbl_file']}.bk", "{$pfb['dnsbl_file']}.conf");
				}
				else {
					$log .= ' Restore previous database Failed! .';
					unlink_if_exists("{$pfb['dnsbl_file']}.conf");
					touch("{$pfb['dnsbl_file']}.conf");
				}
				pfb_logger("{$log}", 1);

				// Reload Resolver
				exec("{$chroot_cmd} reload 2>&1");
			}

			if (file_exists($cache_dumpfile) && filesize($cache_dumpfile) > 0) {
				exec("{$chroot_cmd} load_cache < {$cache_dumpfile} 2>&1", $result);
				pfb_logger('.', 1);
			}
		}
		else {
			pfb_logger('Starting Unbound Service...', 1);
			// Code from services_unbound.php 'apply' 
			$retval = services_unbound_configure();
			if ($retval == 0) {
				clear_subsystem_dirty('unbound');
			}
			system_resolvconf_generate();	// Update resolv.conf
			system_dhcpleases_configure();	// Start or restart dhcpleases
		}

		$result = array();
		exec("/usr/local/sbin/unbound-control -c {$pfb['dnsbldir']}/unbound.conf status 2>&1", $result);
		pfb_logger('.', 1);
		if (preg_grep("/is running.../", $result)) {
			pfb_logger(" completed\n", 1);
		} else {
			pfb_logger(" Not completed.\n", 1);
		}

		$final_cnt = exec("{$pfb['grep']} -v '\"transparent\"\|\"static\"' {$pfb['dnsbl_file']}.conf | {$pfb['grep']} -c ^");
		$dnsbl_cnt = exec("/bin/cat {$pfb['dnsdir']}/*.txt | {$pfb['grep']} -c ^");
		if ($final_cnt == $dnsbl_cnt) {
			$log = "DNSBL update [ {$final_cnt} | PASSED  ]... completed [ NOW ]\n------------------------------------------";
		} else {
			$log = "\n*** DNSBL update [ {$final_cnt} ] [ {$dnsbl_cnt} ] ... OUT OF SYNC ! *** [ NOW ]\n------------------------------------------";
		}
		pfb_logger("{$log}", 1);

		// When pfBlockerNG is disabled and 'keep blocklists' is disabled.
		if ($pfb['enable'] == '' && $pfb['keep'] == '' && !$pfb['install']) {
			unlink_if_exists("{$pfb['dnsbl_file']}{$ext}");
		}

		// Persist/remove Unbound adv. custom options from pfSense conf file
		pfb_unbound_dnsbl($mode);
	}
	else {
		// Revert to previous DNSBL database
		if ($mode == 'enabled') {
			$log = "\nDNSBL {$mode} FAIL - restoring Unbound conf\n";
			pfb_logger("{$log}", 2);
			$log = htmlspecialchars(implode("\n", $result));
			pfb_logger("{$log}", 1);

			if (file_exists("{$pfb['dnsbl_file']}.bk")) {
				@rename("{$pfb['dnsbl_file']}.bk", "{$pfb['dnsbl_file']}.conf");
			}
		}
		else {
			$log = "\nDNSBL {$mode} - Unbound conf update FAIL\n";
			pfb_logger("{$log}", 1);
		}
	}
}


// Process Alexa database
function pfblockerng_alexa() {
	global $pfb;

	if (empty($pfb['dnsbl_alexa_inc'])) {
		pfb_logger("\n  Alexa: No TLD Inclusions found.\n", 1);
		return;
	}

	// Array of TLDs to include in Whitelist
	$pfb_include = array_flip(explode(',', $pfb['dnsbl_alexa_inc']));

	if (($handle = @fopen("{$pfb['dbdir']}/top-1m.csv", 'r')) !== FALSE) {
		$pfb_output = @fopen("{$pfb['dbdir']}/pfbalexawhitelist.txt", 'w');

		for ($x=1; $x <= $pfb['dnsbl_alexa_cnt']; ++$x) {
			$aline = @fgetcsv($handle);

			// Collect Domain TLD
			$tld = array_pop(explode('.', $aline[1]));

			if (isset($pfb_include[$tld])) {
				// Whitelist both 'www.example.com' and 'example.com'
				if (substr($aline[1], 0, 4) == 'www.') {
					$aline[1] = substr($aline[1], 4);
				}

				// Create three whitelist options per Alexa whitelisted Domain
				@fwrite($pfb_output, ".{$aline[1]} 60\n\"{$aline[1]} 60\n\"www.{$aline[1]} 60\n");
			}
			else {
				// Re-Increment $i count
				$x = @max(0, --$x);
			}
		}
	}
	else {
		$log = "\nAlexa conversion Failed. File: top-1m.csv, not found.\n";
		pfb_logger("{$log}", 2);
	}
	@fclose($handle);
	@fclose($pfb_output);
}


// Function to remove any leading zeros in octets and to exclude private/reserved addresses.
function sanitize_ipaddr($ipaddr, $custom) {
	global $pfb;

	list ($subnet, $mask) = explode('/', $ipaddr);
	$iparr = explode('.', $subnet);

	foreach ($iparr as $key => $octet) {
		// Remove any leading zeros in octets
		if ($octet == 0) {
			$ip[$key] = 0;
		} else {
			$ip[$key] = ltrim($octet, '0');
		}

		if ($key == 3) {
			// If mask is not defined and 4th octet is '0', set mask to '24'
			if ($octet == 0 && empty($mask)) {
				$mask = 24;
			}

			// If mask is '24', force 4th octet to '0'
			if ($mask == 24 && $octet != 0) {
				$ip[$key] = 0;
			}
		}
	}

	$mask = str_replace('32', '', $mask);	// Strip '/32' mask
	$ip_final = implode('.', $ip);

	// Exclude private/reserved IPs when suppression is enabled (bypass exclusion for custom lists)
	if ($pfb['supp'] == 'on' && !$custom) {

		// Remove 'loopback' and '0.0.0.0' IPs
		if ($ip[0] == 127 || $ip[0] == 0 || empty($ip[0])) {
			return;
		}

		if (!filter_var($ip_final, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== FALSE) {
			return;
		}
	}

	if (!empty($mask)) {
		return "{$ip_final}/{$mask}";
	}
	return "{$ip_final}";
}


// Validate IPv4 IP addresses
function validate_ipv4($ipaddr) {
	if (strpos($ipaddr, '/') !== FALSE) {
		return is_subnetv4($ipaddr);
	}
	return is_ipaddrv4($ipaddr);
}


// Function to check for loopback addresses (IPv4 range: 127.0.0.0/8, excluding IPv6)
function FILTER_FLAG_NO_LOOPBACK_RANGE($value) {
	// http://www.php.net/manual/en/filter.filters.flags.php
	return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? $value : (((ip2long($value) & 0xff000000) == 0x7f000000) ? FALSE : $value);
}


// Explode IP for evaluations
function ip_explode($ip) {

	$ix = explode('.', $ip);
	foreach ($ix as $key => $octet) {
		if ($key != 3) {
			$ix1 .= "{$octet}.";
		}
	}
	array_unshift($ix, $ip);
	$ix[] = "{$ix1}0/24";
	$ix[] = "{$ix1}";

	return $ix;
}


// Determine the header which Alerted an IP address and return the header name
function find_reported_header($ip, $pfbfolder, $exclude=FALSE) {

	if (substr_count($ip, ':') > 1) {
		$query = strstr($ip, ':', true);	// IPv6 Prefix
		$type = 6;
	} else {
		$query = strstr($ip, '.', true);	// IPv4 Octet #1
		$type = 4;
	}
	$arr = $cidrs = array();

	// Exclude MaxMind files for Alerts tab query; however include for Download failure queries.
	if ($exclude) {
		exec("/usr/bin/find {$pfbfolder} ! -name 'pfB_*.txt' -type f | xargs grep '^{$query}\.'", $result);
	} else {
		exec("/usr/bin/grep '^{$query}\.' {$pfbfolder}", $result);
	}

	if (!empty($result)) {
		foreach ($result as $line) {

			$rx = explode('.txt:', $line);
			$arr[$rx[1]][0] = substr(strrchr($rx[0], '/'), 1);	// Capture IP and header

			if ($rx[1] == $ip) {
				return array('', $arr[$rx[1]][0], TRUE);	// Return header on exact IP Match
			}

			// Collect all CIDRs for analysis if Alert is from a CIDR
			if (strpos($rx[1], '/') !== FALSE) {
				$cidrs[] = array($rx[1], $arr[$rx[1]][0]);
			}
		}

		// Determine which CIDR alerted the IP address
		if (!empty($cidrs)) {
			foreach ($cidrs as $line) {

				// Determine which CIDR alerted the IP address
				$validate = FALSE;
				if ($type == 4) {
					list($addr, $mask) = explode('/', $line[0]);
					$mask = (0xffffffff << (32 - $mask)) & 0xffffffff;
					$validate = ((ip2long($ip) & $mask) == (ip2long($addr) & $mask));
				}
				else {
					$validate = (Net_IPv6::isInNetmask($ip, $line[0]));
				}

				if ($validate) {
					// Mark all CIDRs except /24 as 'FALSE'
					$line[2] = FALSE;
					if (strpos($line[0], '/24') !== FALSE) {
						$line[2] = TRUE;
					}
					return $line;				// Return header on CIDR match
				}
			}
		}
	}

	if ($exclude) {
		return;		// Return null to Download failure function
	}

	// Query for any active pfBlockerNG CRON jobs
	exec('/bin/ps -wax', $result_cron);
	if (preg_grep("/pfblockerng[.]php\s+?(cron|update|updatednsbl)/", $result_cron)) {
		return array('updating..', 'CRON Task');
	}
	return array('', 'no match', FALSE);
}


// Function to download feeds
function pfb_download($list_url, $file_dwn, $pflex=FALSE, $header, $format, $logtype, $vtype, $timeout=300) {
	global $pfb;
	$http_status = '';
	$elog = ">> {$pfb['log']} 2>&1";

	// Download RSYNC format
	if ($format == 'rsync') {
		$result = exec("/usr/local/bin/rsync --timeout=5 {$list_url} {$file_dwn}.raw");
		if ($result == 0) {
			$http_status = '200 OK';
		} else {
			$log = "\n  RSYNC Failed...\n";
			pfb_logger("{$log}", "{$logtype}");
			return FALSE;
		}
	}
	elseif ($format == 'whois') {
		// Convert a Domain name/AS into its respective IP addresses
		exec("{$pfb['script']} whoisconvert {$header} {$vtype} {$list_url} {$elog}");
		return TRUE;
	}
	else {
		// Determine if URL is a localfile
		$host = @parse_url("{$list_url}");
		if (in_array($host['host'], array('127.0.0.1', $pfb['iplocal'], ''))) {
			$lof = 'local';
		} else {
			$lof = '';
		}

		// Download localfile format
		if ($lof == 'local') {
			if (!$file_data = @file_get_contents($list_url)) {
				$error = error_get_last();
				$log = "\n[ {$header} ] {$error['message']}\n";
				pfb_logger("{$log}", "{$logtype}");
				return FALSE;
			} else {
				// Save original downloaded file
				@file_put_contents("{$file_dwn}.raw", $file_data, LOCK_EX);
				$http_status = '200 OK';
			}
		}
	
		// Download using cURL
		else {
			if (($fhandle = @fopen("{$file_dwn}.raw", 'w')) !== FALSE) {
				if (!($ch = curl_init($list_url))) {
					$log = "\nFailed to create cURL resource... Exiting...\n";
					pfb_logger("{$log}", "{$logtype}");
					return FALSE;
				}

				curl_setopt_array($ch, $pfb['curl_defaults']);	// Load curl default settings
				curl_setopt($ch, CURLOPT_FILE, $fhandle);	// Add $fhandle setting to cURL
				curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);	// Set cURL download timeout
				curl_setopt($ch, CURLOPT_ENCODING, 'gzip');	// Request 'gzip' encoding from server if available

				// Attempt 3 Downloads before failing.
				for ($retries = 1; $retries <= 3; $retries++) {
					if (curl_exec($ch)) {
						// Collect remote timestamp.
						$remote_stamp = curl_getinfo($ch, CURLINFO_FILETIME);
						break;	// Break on success
					}

					$curl_error = curl_errno($ch);
					if ($logtype != 3) {
						pfb_logger(" cURL Error: {$curl_error}\n", 1);
					} else {
						pfb_logger(" {$header}\t\tcURL Error: {$curl_error}\n\n", 3);
					}

					/* 'Flex' Downgrade cURL errors -	[ 35 - GET_SERVER_HELLO:sslv3		]
										[ 51 - NO alternative certificate	]
										[ 60 - Local Issuer Certificate Subject	]	*/

					// Allow downgrade of cURL settings 'Flex' after 1st failure, if user configured.
					if ($retries == 1 && $pflex && in_array($curl_error, array( '35', '51', '60'))) {
						curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
						curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
						curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'TLSv1.2, TLSv1.1, TLSv1, SSLv3');
						$log = "\n[ ! ] Downgrading SSL settings (Flex) ";
						pfb_logger("{$log}", 1);
					}
					else {
						$log = curl_error($ch) . " Retry in 5 seconds...\n";
						pfb_logger("{$log}", "{$logtype}");
						sleep(5);
						pfb_logger('.', "{$logtype}");
					}
				}

				// Collect RFC7231 http status code
				$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

				if (isset($pfb['rfc7231'][$http_status])) {
					if ($logtype != 3) {
						pfb_logger(". {$pfb['rfc7231'][$http_status]}", 1);
					} else {
						pfb_logger(" {$file_dwn}\t\t{$pfb['rfc7231'][$http_status]}\n", 3);
					}
				} else {
					if ($logtype != 3) {
						pfb_logger(". unknown http status code ", 2);
					} else {
						pfb_logger(". unknown http status code ", 3);
					}
				}
				curl_close($ch);
			}
			@fclose($fhandle);
		}
	}

	if ($http_status == '200 OK') {
		// Collect file mime-type
		$file_type = exec("/usr/bin/file -b --mime-type {$file_dwn}.raw");
		unset($retval);

		// Decompress file if required
		if ($file_type == 'application/x-gzip') {
			if ($logtype == 3) {
				// Extras - MaxMind downloads
				@rename("{$file_dwn}.raw", strstr("{$file_dwn}.raw", '.raw', TRUE));
				exec("/usr/bin/gunzip -dfq {$file_dwn} {$pfb['geoipshare']}");
				return TRUE;
			}
			else {
				pfb_logger('.', 1);
				exec("/usr/bin/gunzip -c {$file_dwn}.raw > {$file_dwn}.orig", $output, $retval);
			}
		}
		elseif ($file_type == 'application/x-bzip2') {
			pfb_logger('.', 1);
			exec("/usr/bin/bzip2 -dkc {$file_dwn}.raw > {$file_dwn}.orig", $output, $retval);
		}
		elseif ($file_type == 'application/zip') {

			// Extras - MaxMind/Alexa downloads
			if ($logtype == 3) {
				// Determine if Zip contains multiple files
				$archive = exec("/usr/bin/tar -tf {$file_dwn}.raw | grep -c ^ 2>&1");
				if ($archive > 1) {
					exec("/usr/bin/tar -xf {$file_dwn}.raw --strip=1 -C {$header}");
				} else {
					exec("/usr/bin/tar -xOf {$file_dwn}.raw > {$header}");
				}

				unlink_if_exists("{$file_dwn}.raw");
				return TRUE;
			}

			pfb_logger('.', 1);

			// Check if ZIP archive contains xlsx files
			$xlsxtest = exec("/usr/bin/tar -tf {$file_dwn}.raw");
			if (strpos($xlsxtest, '.xlsx') !== FALSE) {
				unlink_if_exists("{$file_dwn}.orig");
				exec("{$pfb['script']} xlsx {$header} {$elog}");
				if (file_exists("{$file_dwn}.orig")) {
					$retval = 0;
				}
			} else {
				// Process ZIP file
				exec("/usr/bin/tar -xOf {$file_dwn}.raw | tr ',' '\n' > {$file_dwn}.orig", $output, $retval);
			}
		}
		else {
			// Uncompressed file format.
			if ($logtype == 3) {
				// Extras - MaxMind/Alexa downloads
				@rename("{$file_dwn}.raw", "{$header}");
				return TRUE;
			} else {
				// Rename file to 'orig' format
				@rename("{$file_dwn}.raw", "{$file_dwn}.orig");
				$retval = 0;
			}
		}

		if ($retval == 0) {
			// Set downloaded file timestamp to remote timestamp
			if (isset($remote_stamp)) {
				if ($remote_stamp != -1 && file_exists("{$file_dwn}.orig")) {
					@touch("{$file_dwn}.orig", $remote_stamp);
				}
				else {
					$log = "\n  Remote timestamp missing ";
					pfb_logger("{$log}", 1);
				}
			}

			// Process Emerging Threats IQRisk if required
			if (strpos($list_url, 'iprepdata.txt') !== FALSE) {
				exec("{$pfb['script']} et {$header} x x x x x {$pfb['etblock']} {$pfb['etmatch']} {$elog}");
			}
			return TRUE;
		}
		else {
			$log = "   Decompression Failed\n";
			pfb_logger("{$log}", 2);
			return FALSE;
		}
	}
	else {
		// Download failed
		unlink_if_exists("{$file_dwn}.raw");
	}
	return FALSE;
}


// Determine reason for download failure
function pfb_download_failure($alias, $header, $pfbfolder, $vtype, $list_url) {
	global $pfb;
	$pfbfound = FALSE;

	// Determine if URL is a localfile
	$host = @parse_url("{$list_url}");
	if (in_array($host['host'], array('127.0.0.1', $pfb['iplocal'], ''))) {
		$lof = 'local';
	} else {
		$lof = '';
	}

	// Log FAILED downloads and check if firewall or Snort/Suricata is blocking host
	$log = "\n\n [ {$alias} - {$header} ] Download FAIL [ NOW ]\n";
	pfb_logger("{$log}", 2);

	// Only perform these checks if they are not 'localfiles'
	if ($lof == 'local') {
		$log = "   Local File Failure\n";
		pfb_logger("{$log}", 2);
	} else {
		// Determine if Firewall/IPS/DNSBL is blocking download.
		$ip = @gethostbyname($host['host']);

		if (!empty($ip)) {
			// Query Firewall aliastables
			$result = find_reported_header($ip, "{$pfbfolder}/*", TRUE);
			if (!empty($result)) {
				$log = " [ {$ip} ] Firewall IP block found in: [ {$result[1]} | {$result[0]} ]\n";
				pfb_logger("{$log}", 2);
				$pfbfound = TRUE;
			}

			// Determine if Host is listed in DNSBL
			if ($ip == $pfb['dnsbl_vip']) {
				$log = " [ {$host['host']} ] Domain listed in DNSBL\n";
				pfb_logger("{$log}", 2);
				$pfbfound = TRUE;
			}

			// Query Snort/Suricata snort2c IP block table
			$result = exec("{$pfb['pfctl']} -t snort2c -T show | {$pfb['grep']} {$ip} 2>&1");
			if (!empty($result)) {
				$log = " [ {$ip} ] IDS IP block found!\n";
				pfb_logger("{$log}", 2);
				$pfbfound = TRUE;
			}
		}
		else {
			$log = "  Could not determine IP address of host.\n";
			pfb_logger("{$log}", 2);
		}

		if (!$pfbfound) {
			$log = "  Firewall and/or IDS are not blocking download.\n";
			pfb_logger("{$log}", 2);
		}
	}

	// Call function to get all previous download fails
	pfb_failures();

	// On download failure, create file marker for subsequent download attempts. ('0' no download failure threshold)
	if ($pfb['skipfeed'] == 0 || $pfb['failed'][$header] <= $pfb['skipfeed']) {
		touch("{$pfbfolder}/{$header}.fail");
		return;
	}

	unlink_if_exists("{$pfbfolder}/{$header}.fail");
	return;
}


// Collect all previously failed daily download notices
function pfb_failures() {
	global $pfb;
	$pfb['failed'] = array();

	if (file_exists("{$pfb['errlog']}")) {
		exec("{$pfb['grep']} 'FAIL' {$pfb['errlog']} | {$pfb['grep']} $(date +%m/%d/%y)", $results);
		if (!empty($results)) {
			foreach ($results as $result) {
				$header = explode(' ', $result);
				$pfb['failed'][$header[4]] += 1;
			}
		}
	}
	return;
}


// Convert unique Alias details (via ascii table number) and return a 10 digit tracker ID
function pfb_tracker($alias, $int, $text) {

	global $config, $pfb;

	$pfbtracker	= 0;
	$real_int	= get_real_interface($int);
	$ipaddr		= get_interface_ip($int);

	if (is_ipaddrv4($ipaddr)) {
		$ipaddr = ip2long32($ipaddr);
		$subnet = find_interface_subnet($real_int);
	}
	else {
		$ipaddr = get_interface_ipv6($real_int);
		$subnet = find_interface_subnetv6($real_int);
	}

	$search		= array( '1', '2', '3', '4', '5', '6', '7', '8', '9', '0' );
	$replace	= array( 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'zero' );
	$line		= "{$alias}{$int}{$text}{$real_int}{$ipaddr}{$subnet}";
	$line		= str_replace($search, $replace, $line);

	for ($i = 0; $i < strlen($line); $i++) {
		$pfbtracker += @ord($line[$i]);
	}

	// If duplicate Tracker ID found, pre-define a Tracker ID (Starts at 1770000010)
	if (in_array($pfbtracker, $pfb['trackerids'])) {
		$pfbtracker = ($pfb['last_trackerid'] + 1);
		$pfb['last_trackerid'] = $pfbtracker;
		return $pfbtracker;
	}
	else {
		$pfb['trackerids'][] = $pfbtracker;
		return '177' . str_pad($pfbtracker, 7, '0', STR_PAD_LEFT);
	}
}


// Define firewall rule settings
function pfb_firewall_rule($action, $pfb_alias, $vtype='', $pfb_log, $agateway_in='default', $agateway_out='default',
	    $aaddrnot_in='', $adest_in='', $aports_in='', $aproto_in='', $anot_in='',
	    $aaddrnot_out='', $asrc_out='', $aports_out='', $aproto_out='', $anot_out='') {

	global $pfb;
	$rule = array();

	switch ($action) {
		case 'Deny_Both':
		case 'Deny_Outbound':
			$rule = $pfb['base_rule'];
			$rule['type'] = "{$pfb['deny_action_outbound']}";
			if ($vtype == '_v6') {
				$rule['ipprotocol'] = 'inet6';
			}
			if ($pfb['float'] == 'on') {
				$rule['direction'] = 'any';
			}
			$rule['descr'] = "{$pfb_alias}{$vtype}{$pfb['suffix']}";
			if (!empty($asrc_out)) {
				$rule['source'] = array('address' => "{$asrc_out}");
			} else {
				$rule['source'] = array('any' => '');
			}
			if (!empty($asrc_out) && $anot_out == 'on') {
				$rule['source']['not'] = '';
			}
			if (!empty($aports_out)) {
				$rule['destination'] = array('address' => "{$pfb_alias}{$vtype}", 'port' => "{$aports_out}");
			} else {
				$rule['destination'] = array('address' => "{$pfb_alias}{$vtype}");
			}
			if ($aaddrnot_out == 'on') {
				$rule['destination']['not'] = '';
			}
			if (!empty($aproto_out)) {
				$rule['protocol'] = "{$aproto_out}";
			}
			if ($pfb['config']['enable_log'] == 'on' || $pfb_log == 'enabled') {
				$rule['log'] = '';
			}
			if (!empty($agateway_out) && $agateway_out != 'default') {
				$rule['gateway'] = "{$agateway_out}";
				if ($pfb['float'] == 'on') {
					$rule['direction'] = 'out';
				}
			}
			$rule['created'] = array('time' => (int)microtime(true), 'username' => 'Auto');
			$pfb['deny_outbound'][] = $rule;
			if ($action != 'Deny_Both') {
				break;
			}
		case 'Deny_Inbound':
			$rule = $pfb['base_rule'];
			$rule['type'] = "{$pfb['deny_action_inbound']}";
			if ($vtype == '_v6') {
				$rule['ipprotocol'] = 'inet6';
			}
			if ($pfb['float'] == 'on') {
				$rule['direction'] = 'any';
			}
			$rule['descr'] = "{$pfb_alias}{$vtype}{$pfb['suffix']}";
			$rule['source'] = array('address' => "{$pfb_alias}{$vtype}");
			if ($aaddrnot_in == 'on') {
				$rule['source']['not'] = '';
			}
			if (!empty($adest_in) && !empty($aports_in)) {
				$rule['destination'] = array('address' => "{$adest_in}", 'port' => "{$aports_in}");
			} elseif (!empty($adest_in) && empty($aports_in)) {
				$rule['destination'] = array('address' => "{$adest_in}");
			} elseif (empty($adest_in) && !empty($aports_in)) {
				$rule['destination'] = array('any' => '', 'port' => "{$aports_in}");
			} else {
				$rule['destination'] = array('any' => '');
			}
			if (!empty($adest_in) && $anot_in == 'on') {
				$rule['destination']['not'] = '';
			}
			if (!empty($aproto_in)) {
				$rule['protocol'] = "{$aproto_in}";
			}
			if ($pfb['config']['enable_log'] == 'on' || $pfb_log == 'enabled') {
				$rule['log'] = '';
			}
			if (!empty($agateway_in) && $agateway_in != 'default') {
				$rule['gateway'] = "{$agateway_in}";
				if ($pfb['float'] == 'on') {
					$rule['direction'] = 'in';
				}
			}
			$rule['created'] = array('time' => (int)microtime(true), 'username' => 'Auto');
			$pfb['deny_inbound'][] = $rule;
			break;
		case 'Permit_Both':
		case 'Permit_Outbound':
			$rule = $pfb['base_rule'];
			$rule['type'] = 'pass';
			if ($vtype == '_v6') {
				$rule['ipprotocol'] = 'inet6';
			}
			if ($pfb['float'] == 'on') {
				$rule['direction'] = 'any';
			}
			$rule['descr'] = "{$pfb_alias}{$vtype}{$pfb['suffix']}";
			if (!empty($asrc_out)) {
				$rule['source'] = array('address' => "{$asrc_out}");
			} else {
				$rule['source'] = array('any' => '');
			}
			if (!empty($asrc_out) && $anot_out == 'on') {
				$rule['source']['not'] = '';
			}
			if (!empty($aports_out)) {
				$rule['destination'] = array('address' => "{$pfb_alias}{$vtype}", 'port' => "{$aports_out}");
			} else {
				$rule['destination'] = array('address' => "{$pfb_alias}{$vtype}");
			}
			if ($aaddrnot_out == 'on') {
				$rule['destination']['not'] = '';
			}
			if (!empty($aproto_out)) {
				$rule['protocol'] = "{$aproto_out}";
			}
			if ($pfb['config']['enable_log'] == 'on' || $pfb_log == 'enabled') {
				$rule['log'] = '';
			}
			if (!empty($agateway_out) && $agateway_out != 'default') {
				$rule['gateway'] = "{$agateway_out}";
				if ($pfb['float'] == 'on') {
					$rule['direction'] = 'out';
				}
			}
			$rule['created'] = array('time' => (int)microtime(true), 'username' => 'Auto');
			$pfb['permit_outbound'][] = $rule;
			if ($action != 'Permit_Both') {
				break;
			}
		case 'Permit_Inbound':
			$rule = $pfb['base_rule'];
			$rule['type'] = 'pass';
			if ($vtype == '_v6') {
				$rule['ipprotocol'] = 'inet6';
			}
			if ($pfb['float'] == 'on') {
				$rule['direction'] = 'any';
			}
			$rule['descr'] = "{$pfb_alias}{$vtype}{$pfb['suffix']}";
			$rule['source'] = array('address' => "{$pfb_alias}{$vtype}");
			if ($aaddrnot_in == 'on') {
				$rule['source']['not'] = '';
			}
			if (!empty($adest_in) && !empty($aports_in)) {
				$rule['destination'] = array('address' => "{$adest_in}", 'port' => "{$aports_in}");
			} elseif (!empty($adest_in) && empty($aports_in)) {
				$rule['destination'] = array('address' => "{$adest_in}");
			} elseif (empty($adest_in) && !empty($aports_in)) {
				$rule['destination'] = array('any' => '', 'port' => "{$aports_in}");
			} else {
				$rule['destination'] = array('any' => '');
			}
			if (!empty($adest_in) && $anot_in == 'on') {
				$rule['destination']['not'] = '';
			}
			if (!empty($aproto_in)) {
				$rule['protocol'] = "{$aproto_in}";
			}
			if ($pfb['config']['enable_log'] == 'on' || $pfb_log == 'enabled') {
				$rule['log'] = '';
			}
			if (!empty($agateway_in) && $agateway_in != 'default') {
				$rule['gateway'] = "{$agateway_in}";
				if ($pfb['float'] == 'on') {
					$rule['direction'] = 'in';
				}
			}
			$rule['created'] = array('time' => (int)microtime(true), 'username' => 'Auto');
			$pfb['permit_inbound'][] = $rule;
			break;
		case 'Match_Both':
		case 'Match_Outbound':
			$rule = $pfb['base_rule_float'];
			$rule['type'] = 'match';
			if ($vtype == '_v6') {
				$rule['ipprotocol'] = 'inet6';
			}
			$rule['direction'] = 'any';
			$rule['descr'] = "{$pfb_alias}{$vtype}{$pfb['suffix']}";
			if (!empty($asrc_out)) {
				$rule['source'] = array('address' => "{$asrc_out}");
			} else {
				$rule['source'] = array('any' => '');
			}
			if (!empty($asrc_out) && $anot_out == 'on') {
				$rule['source']['not'] = '';
			}
			if (!empty($aports_out)) {
				$rule['destination'] = array('address' => "{$pfb_alias}{$vtype}", 'port' => "{$aports_out}");
			} else {
				$rule['destination'] = array('address' => "{$pfb_alias}{$vtype}");
			}
			if ($aaddrnot_out == 'on') {
				$rule['destination']['not'] = '';
			}
			if (!empty($aproto_out)) {
				$rule['protocol'] = "{$aproto_out}";
			}
			if ($pfb['config']['enable_log'] == 'on' || $pfb_log == 'enabled') {
				$rule['log'] = '';
			}
			if (!empty($agateway_out) && $agateway_out != 'default') {
				$rule['gateway'] = "{$agateway_out}";
				$rule['direction'] = 'out';
			}
			$rule['created'] = array('time' => (int)microtime(true), 'username' => 'Auto');
			$pfb['match_outbound'][] = $rule;
			if ($action != 'Match_Both') {
				break;
			}
		case 'Match_Inbound':
			$rule = $pfb['base_rule_float'];
			$rule['type'] = 'match';
			if ($vtype == '_v6') {
				$rule['ipprotocol'] = 'inet6';
			}
			$rule['direction'] = 'any';
			$rule['descr'] = "{$pfb_alias}{$vtype}{$pfb['suffix']}";
			$rule['source'] = array('address' => "{$pfb_alias}{$vtype}");
			if ($aaddrnot_in == 'on') {
				$rule['source']['not'] = '';
			}
			if (!empty($adest_in) && !empty($aports_in)) {
				$rule['destination'] = array('address' => "{$adest_in}", 'port' => "{$aports_in}");
			} elseif (!empty($adest_in) && empty($aports_in)) {
				$rule['destination'] = array('address' => "{$adest_in}");
			} elseif (empty($adest_in) && !empty($aports_in)) {
				$rule['destination'] = array('any' => '', 'port' => "{$aports_in}");
			} else {
				$rule['destination'] = array('any' => '');
			}
			if (!empty($adest_in) && $anot_in == 'on') {
				$rule['destination']['not'] = '';
			}
			if (!empty($aproto_in)) {
				$rule['protocol'] = "{$aproto_in}";
			}
			if ($pfb['config']['enable_log'] == 'on' || $pfb_log == 'enabled') {
				$rule['log'] = '';
			}
			if (!empty($agateway_in) && $agateway_in != 'default') {
				$rule['gateway'] = "{$agateway_in}";
				$rule['direction'] = 'in';
			}
			$rule['created'] = array('time' => (int)microtime(true), 'username' => 'Auto');
			$pfb['match_inbound'][] = $rule;
			break;
	}
	return;
}


// Archive IP aliastables and DNSBL database. ( NanoBSD and Ramdisk installations only )
function pfb_aliastables($mode) {
	global $g, $config, $pfb;
	$earlyshellcmd = '/usr/local/pkg/pfblockerng/pfblockerng.sh aliastables';
	$msg = '';

	// Only execute function if platform is NanoBSD or Ramdisks are used.
	if (($g['platform'] != 'pfSense') || isset($config['system']['use_mfs_tmpvar'])) {
		conf_mount_rw();
		if ($mode == 'update') {
			// Archive aliastable folder
			pfb_logger("\n\nArchiving Aliastable folder\n", 1);
			$files_to_backup = '';
			if (glob("{$pfb['aliasdir']}/pfB_*.txt")) {
				$files_to_backup = "{$pfb['aliasdir']}/pfB_*.txt";
			}

			if ($pfb['dnsbl'] == 'on' && file_exists("{$pfb['dnsbl_file']}.conf")) {
				$files_to_backup .= " {$pfb['dnsbl_file']}.conf";
			}

			// Archive IP Aliastables/Unbound DNSBL Database as required.
			if (!empty($files_to_backup)) {
				exec("/usr/bin/tar -jcvf {$pfb['aliasarchive']} {$files_to_backup} >/dev/null 2>&1");
				pfb_logger("\n\nArchiving selected pfBlockerNG files.\n", 1);
			} else {
				pfb_logger("\n\nNo Files to archive.\n", 1);
			}
		}
		elseif ($mode == 'conf') {

			// Reload config.xml to get any recent changes
			$config = parse_config(true);

			// Check conf file for earlyshellcmd
			if (isset($config['system']['earlyshellcmd'])) {
				$a_earlyshellcmd = &$config['system']['earlyshellcmd'];
				if (!preg_grep("/pfblockerng.sh aliastables/", $a_earlyshellcmd)) {
					$a_earlyshellcmd[] = "{$earlyshellcmd}";
					$msg = "\n** Adding earlyshellcmd **\n";
				}
			}
			else {
				$config['system']['earlyshellcmd'] = "{$earlyshellcmd}";
				$msg = "\n** Adding earlyshellcmd **\n";
			}
		}
		conf_mount_ro();
	}
	else {
		if (file_exists("{$pfb['aliasarchive']}")) {
			// Remove aliastables archive if found.
			conf_mount_rw();
			@unlink_if_exists("{$pfb['aliasarchive']}");
			conf_mount_ro();
		}
		// Remove earlyshellcmd if found.
		if (isset($config['system']['earlyshellcmd'])) {
			$a_earlyshellcmd = &$config['system']['earlyshellcmd'];
			if (preg_grep("/pfblockerng.sh aliastables/", $a_earlyshellcmd)) {
				$a_earlyshellcmd = preg_grep("/pfblockerng.sh aliastables/", $a_earlyshellcmd, PREG_GREP_INVERT);
				$msg = "\n** Removing earlyshellcmd **\n";
			}
		}
	}

	if (!empty($msg)) {
		pfb_logger("{$msg}", 1);
		write_config('pfBlockerNG: saving earlyshellcmd');
	}
}


// Read logfile in realtime (livetail)
// Reference: http://stackoverflow.com/questions/3218895/php-how-to-read-a-file-live-that-is-constantly-being-written-to
function pfb_livetail($logfile, $mode) {
	global $pfb;

	if (!file_exists("{$logfile}")) {
		touch("{$logfile}");
	}

	// Start at EOF
	$lastpos_old = '';
	$len = @filesize("{$logfile}");
	$pfb_output = '';

	if ($mode == 'view') {
		// Start at EOF ( - 15000)
		if ($len > 15000) {
			$lastpos = ($len - 15000);
		} else {
			$lastpos = 0;
		}
	}
	else {
		$lastpos = $len;
	}

	while (true) {
		usleep(300000); //0.3s
		clearstatcache(false, "{$logfile}");
		$len = @filesize("{$logfile}");
		if ($len < $lastpos) {
			//file deleted or reset
			$lastpos = $len;
		} else {
			$f = @fopen("{$logfile}", 'rb+');
			if ($f === false) {
				break;
			}
			@fseek($f, $lastpos);

			// 'Force Update/Cron/Reload'
			if ($mode == 'force' || $mode == 'view') {
				while (!feof($f)) {
					$pfb_buffer = @fread($f, 2048);
					$pfb_output .= str_replace( array ("\r", "\")"), '', $pfb_buffer);
					// Refresh on new lines only. This allows Scrolling.
					if ($lastpos != $lastpos_old) {
						pfbupdate_output($pfb_output);
					}
					$lastpos_old = $lastpos;
					ob_flush();
					flush();

				}
				$lastpos = @ftell($f);
				@fclose($f);

				// Capture remaining output
				if ($mode != 'view' && strpos($pfb_output, 'UPDATE PROCESS ENDED') !== FALSE) {
					$f = @fopen($pfb['log'], 'rb');
					@fseek($f, $lastpos);
					$pfb_buffer = @fread($f, 2048);
					$pfb_output .= str_replace( "\r", '', $pfb_buffer);
					pfbupdate_output($pfb_output);
					clearstatcache(false, $pfb['log']);
					ob_flush();
					flush();
					@fclose($f);
			
					// Call log mgmt function
					pfb_log_mgmt();
					break;
				}
			}
			else {
				// DNSBL Lighttpd 'dnsbl_error.log' conditional log parser
				while (($pfb_buffer = @fgets($f, 1024)) !== FALSE) {
					if (strpos($pfb_buffer, 'HTTPhost') !== FALSE) {
						$checkpos = 0;
					}

					if ($checkpos == 3 && strpos($pfb_buffer, 'HTTP["host"]') !== FALSE) {
						$line = strstr($pfb_buffer, ' ) compare', TRUE);
						$line = ltrim(strstr($line, '] ( ', FALSE), '] ( ');
						if (!empty($line)) {
							$log = "DNSBL Reject HTTPS," . date('M d H:i:s', time()) . ",{$line}\n";
							@file_put_contents($pfb['dnslog'], $log, FILE_APPEND | LOCK_EX);

							// Query DNSBL Alias for Domain list.
							$query = str_replace('.', '\.', $line);
							exec("/usr/bin/grep -l ' \"{$query} 60 IN A' {$pfb['dnsalias']}/*", $match);
							$pfb_query = strstr($match[0], 'DNSBL', FALSE);

							if (!empty($pfb_query)) {
								// Increment DNSBL Alias counter
								if (($handle = @fopen("{$pfb['dnsbl_info']}", 'r')) !== FALSE) {
									@flock($handle, LOCK_EX);
									$pfb_output = @fopen("{$pfb['dnsbl_info']}.bk", 'w');
									@flock($pfb_output, LOCK_EX);

									// Find line with corresponding DNSBL Aliasname
									while (($line = @fgetcsv($handle)) !== FALSE) {
										if ($line[0] == $pfb_query) {
											$line[3] += 1;
										}
										@fputcsv($pfb_output, $line);
									}

									@fclose($pfb_output);
									@fclose($handle);
									@rename ("{$pfb['dnsbl_info']}.bk", "{$pfb['dnsbl_info']}");
								}
							}
						}
					}
					$checkpos++;
					ob_flush();
					flush();
				}

				// Delete parsed logfile contents
				clearstatcache(false, "{$logfile}");
				$tlen = @filesize("{$logfile}");
				if ($tlen > $lastpos) {
					$tlen = $tlen - $lastpos;
					@ftruncate($f, $tlen);
					@fclose($f);
					$lastpos = $tlen;
				}
			}
		}
	}
}


// Main pfBlockerNG function
function sync_package_pfblockerng($cron='') {
	global $g, $config, $pfb, $pfbarr;
	pfb_global();
	$pfb['conf_mod'] = FALSE;	// Flag to check for mods to the config.xml file. ('$pfb_config' array to hold changes)

	// Detect boot process or package installation
	if (platform_booting() || $g['pfblockerng_install']) {
		// Create DNSBL NAT, VIP, Lighttpd service and certs if required on reboot.
		if ($pfb['dnsbl'] == 'on') {
			pfb_create_dnsbl('enable');
		}
		$log = 'Sync terminated during boot process.';
		pfb_logger("\n{$log}\nUPDATE PROCESS ENDED [ NOW ]\n", 1);
		log_error("[pfBlockerNG] {$log}");
		return;
	}
	syslog(LOG_NOTICE, '[pfBlockerNG] Starting cron process.');

	// Reloads existing lists without downloading new lists when defined 'on'
	$pfb['reuse'] = $pfb['config']['pfb_reuse'];
	$pfb['reuse_dnsbl'] = '';
	$pfb['updatednsbl'] = FALSE;	// Set flag to allow DNSBL Reload, only when called via background cmd.

	// Define update process (update or reload)
	switch ($cron) {
		case 'noupdates':
			// Force update - Set 'save' variable when 'No updates' found.
			$pfb['save'] = TRUE;
			break;
		case 'cron':
			if ($pfb['reuse'] == 'on') {
				$pfb['reuse_dnsbl'] = 'on';
				unlink_if_exists("{$pfb['dbdir']}/masterfile");
				unlink_if_exists("{$pfb['dbdir']}/mastercat");
			}
			break;
		case 'updatednsbl':
			$pfb['reuse'] = '';
			$pfb['reuse_dnsbl'] = 'on';
			$pfb['updatednsbl'] = TRUE;
			break;
		case 'updateip':
			$pfb['reuse'] = 'on';
			$pfb['reuse_dnsbl'] = '';
			unlink_if_exists("{$pfb['dbdir']}/masterfile");
			unlink_if_exists("{$pfb['dbdir']}/mastercat");
			break;
	}

	// Start of pfBlockerNG logging to 'pfblockerng.log'
	if ($pfb['enable'] == 'on' && !$pfb['save']) {
		$log = " UPDATE PROCESS START [ NOW ]\n";
		pfb_logger("{$log}", 1);
	} else {
		if ($cron != 'noupdates') {
			$log = "\n**Saving configuration [ NOW ] ...\n";
			pfb_logger("{$log}", 1);
		}
	}

	// Call function for NanoBSD/Ramdisk processes.
	pfb_aliastables('conf');

	// If table limit not defined, set default to 2M 
	if (empty($config['system']['maximumtableentries'])) {
		$config['system']['maximumtableentries'] = '2000000';
		write_config('pfBlockerNG: save max Firewall table entries limit');
	}
	$pfb['table_limit'] = $config['system']['maximumtableentries'];

	// Collect local web gui configuration
	$pfb['weblocal'] = $config['system']['webgui']['protocol'] ?: 'http';
	$pfb['port'] = $config['system']['webgui']['port'];
	if (empty($pfb['port'])) {
		if ($config['system']['webgui']['protocol'] == 'http') {
			$pfb['port'] = '80';
		} else {
			$pfb['port'] = '443';
		}
	}
	$pfb['weblocal'] .= "://127.0.0.1:{$pfb['port']}/pfblockerng/pfblockerng.php";

	// Define Inbound/Outbound action is not user selected.
	$pfb['deny_action_inbound']  = $pfb['config']['inbound_deny_action']	?: 'block';
	$pfb['deny_action_outbound'] = $pfb['config']['outbound_deny_action']	?: 'reject';

	$pfb['openvpn']	= $pfb['config']['openvpn_action'];				// Enable OpenVPN autorules
	$pfb['ipsec']	= $pfb['config']['ipsec_action'];				// Enable IPSec autorules
	$pfb['float']	= $pfb['config']['enable_float'];				// Enable/Disable floating autorules
	$pfb['dup']	= $pfb['config']['enable_dup'];					// Enable remove of duplicate IPs utilizing grepcidr
	$pfb['agg']	= $pfb['config']['enable_agg'];					// Enable aggregation of CIDRs
	$pfb['order']	= $pfb['config']['pass_order'];					// Order of the autorules
	$pfb['suffix']	= $pfb['config']['autorule_suffix'];				// Suffix used for autorules
	$pfb['kstates'] = $pfb['config']['killstates'];					// Firewall states removal

	// DNSBL settings
	$pfb['dnsbl_vip']	= $pfb['dnsblconfig']['pfb_dnsvip']	?: '';		// Virtual IP local address
	$pfb['dnsbl_iface']	= $pfb['dnsblconfig']['dnsbl_interface']?: 'lan';	// VIP Local Interface setting
	$pfb['dnsbl_ip']	= $pfb['dnsblconfig']['action']		?: 'Disabled';	// Enable/Disable IP blocking from DNSBL lists
	$pfb['dnsbl_rule']	= $pfb['dnsblconfig']['pfb_dnsbl_rule'] ?: 'Disabled';	// Auto create a Floating Pass Rule for other Lan subnets
	$pfb['dnsbl_alexa_cnt']	= $pfb['dnsblconfig']['alexa_count'] ?: '1000';		// Alexa whitelist domain setting
	$pfb['dnsbl_alexa_inc']	= $pfb['dnsblconfig']['alexa_inclusion'] ?: '';		// Alexa TLDs inclusions for whitelisting
	$pfb['dnsbl_tld']	= $pfb['dnsblconfig']['pfb_tld'];			// Enable TLD Function

	// Reputation variables
	$pfb['config_rep'] = $config['installedpackages']['pfblockerngreputation']['config'][0];

	$pfb['rep']	= $pfb['config_rep']['enable_rep'];				// Enable/Disable 'Max' Reputation
	$pfb['prep']	= $pfb['config_rep']['enable_pdup'];				// Enable/Disable 'pRep' Reputation
	$pfb['drep']	= $pfb['config_rep']['enable_dedup']	?: 'x';			// Enable/Disable 'dRep' Reputation
	$pfb['etupdate']= $pfb['config_rep']['et_update'];				// Perform a Force Update on ET categories
	$pfb['ccwhite'] = $pfb['config_rep']['ccwhite'];				// Action for whitelist Country category
	$pfb['ccblack'] = $pfb['config_rep']['ccblack'];				// Action for blacklist Country category

	$pfb['etblock'] = $pfb['config_rep']['etblock']		?: 'x';			// Emerging Threats IQRisk block categories
	$pfb['etmatch'] = $pfb['config_rep']['etmatch']		?: 'x';			// Emerging Threats IQRisk match categories
	$pfb['max']	= $pfb['config_rep']['p24_max_var']	?: 'x';			//  'Max' variable setting for Reputation
	$pfb['dmax']	= $pfb['config_rep']['p24_dmax_var']	?: 'x';			// 'dMax' variable setting for Reputation
	$pfb['pmax']	= $pfb['config_rep']['p24_pmax_var']	?: 'x';			// 'pMax' variable setting for Reputation
	$pfb['ccexclude']= $pfb['config_rep']['ccexclude']	?: 'x';			// List of Countries to whitelist

	// Starting variable to skip Reputation functions, if no changes are required
	$pfb['repcheck'] = FALSE;
	// $pfb['save'] is used to determine if user pressed "save" button to avoid collision with CRON, defined in each pfBlockerNG XML file.

	// For 'script' calls using exec() (used to shorten length of line)
	$elog = ">> {$pfb['log']} 2>&1";


	#################################
	#	Configure ARRAYS	#
	#################################

	$new_aliases		= array();		// An array of aliases (full details)
	$new_aliases_list	= array();		// An array of alias names
	$pfb_alias_lists	= array();		// An array of aliases that have updated lists via CRON/force update. ('Reputation' disabled)
	$pfb_alias_lists_all	= array();		// An array of all active aliases. ('Reputation' enabled)


	#################################
	#	Tracker IDs		#
	#################################

	$pfb['trackerids']	= array();		// An array of pfBlockerNG Firewall rule Tracker IDs.
	$pfb['last_trackerid']	= 1700000009;		// Pre-defined 'starting' Tracker ID (Only used if duplicates found)


	#########################################
	#	Configure Rule Suffix		#
	#########################################

	// Discover if any rules are autorules (If no autorules found, $pfb['autorules'] is FALSE, skip rules re-order )
	// To configure auto rule suffix. pfBlockerNG must be disabled to change suffix and to avoid duplicate rules
	$pfb['autorules'] = FALSE;
	$action = array('Deny_Both', 'Deny_Inbound', 'Deny_Outbound', 'Match_Both', 'Match_Inbound',
			'Match_Outbound', 'Permit_Both', 'Permit_Inbound', 'Permit_Outbound');

	foreach ($pfb['continents'] as $continent => $pfb_alias) {
		if (isset($config['installedpackages']['pfblockerng' . strtolower(str_replace(' ', '', $continent))]['config'])) {
			$continent_config = $config['installedpackages']['pfblockerng' . strtolower(str_replace(' ', '', $continent))]['config'][0];
			if ($continent_config['action'] != 'Disabled' && in_array($continent_config['action'], $action)) {
				$pfb['autorules'] = TRUE;
				break;
			}
		}
	}

	if (!$pfb['autorules']) {
		$list_type = array('pfblockernglistsv4', 'pfblockernglistsv6');
		foreach ($list_type as $ip_type) {
			if (isset($config['installedpackages'][$ip_type]['config'])) {
				foreach($config['installedpackages'][$ip_type]['config'] as $list) {
					if ($list['action'] != 'Disabled' && in_array($list['action'], $action)) {
						$pfb['autorules'] = TRUE;
						break;
					}
				}
			}
		}
	}

	// Configure auto rule suffix. pfBlockerNG must be disabled to change suffix and to avoid duplicate rules
	$pfbfound = FALSE;
	if (isset($config['filter']['rule'])) {
		foreach ($config['filter']['rule'] as $rule) {
			// Collect any pre-existing suffix
			if (preg_match('/pfB_\w+(\s.*)/', $rule['descr'], $pfb_suffix_real) && $count == 0) {
				$pfb_suffix_match = $pfb_suffix_real[1];
			}
			// Query for existing pfB rules
			if (strpos($rule['descr'], 'pfB_') !== FALSE && $rule['descr'] != 'pfB_DNSBL_Allow_access_to_VIP') {
				$pfbfound = TRUE;
				break;
			}
		}
	}

	// Change suffix only if no pfB rules found and autorules are enabled.
	if ($pfb['autorules'] && !$pfbfound) {
		switch ($pfb['suffix']) {
			case 'autorule':
				$pfb['suffix'] = ' auto rule';
				break;
			case 'standard':
				$pfb['suffix'] = '';
				break;
			case 'ar':
				$pfb['suffix'] = ' AR';
				break;
		}
	} else {
		if ($pfb['autorules']) {
			// Use existing suffix match
			$pfb['suffix'] = $pfb_suffix_match;
		} else {
			// Leave rule suffix 'blank'
			$pfb['suffix'] = '';
		}
	}


	#########################################################
	#	Configure INBOUND/OUTBOUND INTERFACES		#
	#########################################################

	// Collect pfSense interface order
	$ifaces = get_configured_interface_list();

	foreach (array('inbound', 'outbound') as $type) {
		$pfb["{$type}_interfaces"] = $pfb["{$type}_floating"] = array();

		if (!empty($pfb['config']["{$type}_interface"])) {

			// Sort interface array to match pfSense interface order to allow floating rules to populate.
			$selected_interfaces	= explode(',', $pfb['config']["{$type}_interface"]);
			$sort_interfaces	= array_intersect($ifaces, $selected_interfaces);

			// If OpenVPN interfaces are not in pfB interface dropdown menu
			if ($pfb['openvpn'] == 'on') {
				if (!empty($config['openvpn']['openvpn-server'])) {
					if ($type == 'outbound' && !in_array('openvpn', $selected_interfaces)) {
						array_push($sort_interfaces, 'openvpn');
					}
				}
				if (!empty($config['openvpn']['openvpn-client'])) {
					if (!in_array('openvpn', $selected_interfaces)) {
						array_push($sort_interfaces, 'openvpn');
					}
				}
			}

			// Add firewall rules to IPSec interface if configured
			if ($pfb['ipsec'] == 'on' && $type == 'outbound') {
				if (!empty($config['ipsec'])) {
					if (!in_array('enc0', $selected_interfaces)) {
						array_push($sort_interfaces, 'enc0');
					}
				}
			}

			// CSV string for 'pfB_' match rules
			$pfb["{$type}_floating"]		= ltrim(implode(',', $sort_interfaces), ',');

			// Assign base rule/interfaces
			if ($pfb['float'] == 'on') {
				$pfb['base_rule']		= $pfb['base_rule_float'];
				$pfb["{$type}_interfaces"]	= explode(' ', $pfb["{$type}_floating"]);
			} else {
				$pfb['base_rule']		= $pfb['base_rule_reg'];
				$pfb["{$type}_interfaces"]	= $sort_interfaces;
			}
		}
	}

	// Determine max Domain count available for DNSBL TLD analysis (Avoid Unbound memory exhaustion)
	$pfs_memory = (round(get_single_sysctl('hw.physmem') / (1024*1024)) ?: 1000);
	$pfb['pfs_mem'] = array(   '0' => '100000', '1500' =>  '150000', '2000' =>  '200000', '2500' =>  '250000', '3000' =>  '400000',
				'4000' => '600000', '5000' => '1000000', '6000' => '1500000', '7000' => '2000000', '8000' => '2500000');

	foreach ($pfb['pfs_mem'] as $pfb_mem => $domain_max) {
		if ($pfs_memory >= $pfb_mem) {
			$pfb['domain_max_cnt'] = $domain_max;
		}
	}


	#################################################
	#	Clear Removed Lists from Masterfiles	#
	#################################################

	// Process to keep Masterfiles in sync with valid Lists from config.conf file.
	$pfb['sync_master'] = TRUE;

	// Don't execute this function when pfBlockerNG is disabled and 'keep blocklists' is enabled.
	if ($pfb['enable'] == '' && $pfb['keep'] == 'on') {
		$pfb['sync_master'] = FALSE;
	}

	if ($pfb['sync_master']) {
		$m_action = array('Match_Both', 'Match_Inbound', 'Match_Outbound', 'Alias_Match');
		$p_action = array('Permit_Both', 'Permit_Inbound', 'Permit_Outbound', 'Alias_Permit');

		// Find all enabled Continents lists
		foreach ($pfb['continents'] as $continent => $pfb_alias) {
			if (isset($config['installedpackages']['pfblockerng' . strtolower(str_replace(' ', '', $continent))]['config']) && $pfb['enable'] == 'on') {
				$continent_config = $config['installedpackages']['pfblockerng' . strtolower(str_replace(' ', '', $continent))]['config'][0];
				if ($continent_config['action'] != 'Disabled') {
					$cont_type = array('countries4' => '_v4', 'countries6' => '_v6');
					foreach ($cont_type as $c_type => $vtype) {
						if (!empty($continent_config[$c_type])) {
							// Set parameters for 'Match', 'Permit', 'Native' and 'Deny' actions.
							if (in_array($continent_config['action'], $m_action)) {
								$pfb['existing']['match'][] = "{$pfb_alias}{$vtype}";
							} elseif (in_array($continent_config['action'], $p_action)){
								$pfb['existing']['permit'][] = "{$pfb_alias}{$vtype}";
							} elseif ($continent_config['action'] == 'Alias_Native') {
								$pfb['existing']['native'][] = "{$pfb_alias}{$vtype}";
							} else {
								$pfb['existing']['deny'][] = "{$pfb_alias}{$vtype},";	// Add Trailing ','
							}
						}
					}
				}
			}
		}

		// Find all enabled IPv4/IPv6 lists and DNSBL lists
		// Find all enabled IPv4 'Custom List' header names and check if 'Emerging Threats Update' and 'Custom List Update' needs force updating
		$list_type = array('pfblockernglistsv4' => '_v4', 'pfblockernglistsv6' => '_v6', 'pfblockerngdnsbl' => '_v4', 'pfblockerngdnsbleasylist' => '_v4');
		foreach ($list_type as $ip_type => $vtype) {
			if (!empty($config['installedpackages'][$ip_type]['config']) && $pfb['enable'] == 'on') {
				foreach ($config['installedpackages'][$ip_type]['config'] as $key => $list) {
					if (isset($list['row']) && $list['action'] != 'Disabled') {

						// Force 'Alias Native' setting to any Alias with 'Advanced Inbound/Outbound -Invert src/dst' settings.
						// This will bypass Deduplication and Reputation features.
						if ($list['autoaddrnot_in'] == 'on' || $list['autoaddrnot_out'] == 'on') {
							$list['action'] = 'Alias_Native';
						}

						foreach ($list['row'] as $row) {
							if ($vtype == '_v4') {
								$header = "{$row['header']}";
							} else {
								$header = "{$row['header']}_v6";
							}

							// Collect enabled lists
							if (!empty($row['url']) && $row['state'] != 'Disabled') {
								if (in_array($list['action'], $m_action)) {
									$pfb['existing']['match'][] = "{$header}";
								} elseif (in_array($list['action'], $p_action)) {
									$pfb['existing']['permit'][] = "{$header}";
								} elseif ($list['action'] == 'Alias_Native') {
									$pfb['existing']['native'][] = "{$header}";
								} elseif ($list['action'] == 'unbound') {
									$pfb['existing']['dnsbl'][] = "{$header}";
								} else {
									$pfb['existing']['deny'][] = "{$header},";	// Add Trailing ','
								}

								// Check if 'Emerging Threats' needs updating.
								if ($pfb['etupdate'] == 'enabled' && strpos($row['url'], 'iprepdata.txt') !== FALSE) {
									unlink_if_exists("{$pfb['denydir']}/{$header}.txt");
									$pfb_config['installedpackages']['pfblockerngreputation']['config'][0]['et_update'] = 'disabled';
									$pfb['conf_mod'] = TRUE;
								}
							}
						}
					}

					// Collect custom list box aliases
					if (!empty($list['custom'])) {
						if ($vtype == '_v4') {
							$pfb_custom = "{$list['aliasname']}_custom";
						} else {
							$pfb_custom = "{$list['aliasname']}_custom_v6";
						}

						// Determine folder location for 'list'
						if (in_array($list['action'], $m_action)) {
							$pfb['existing']['match'][] = "{$pfb_custom}";
							$pfbfolder = "{$pfb['matchdir']}";
						} elseif (in_array($list['action'], $p_action)) {
							$pfb['existing']['permit'][] = "{$pfb_custom}";
							$pfbfolder = "{$pfb['permitdir']}";
						} elseif ($list['action'] == 'Alias_Native') {
							$pfb['existing']['native'][] = "{$pfb_custom}";
							$pfbfolder = "{$pfb['nativedir']}";
						} elseif ($list['action'] == 'unbound') {
							$pfb['existing']['dnsbl'][] = "{$pfb_custom}";
							$pfbfolder = "{$pfb['dnsdir']}";
						} else {
							$pfb['existing']['deny'][] = "{$pfb_custom},";	// Add Trailing ','
							$pfbfolder = "{$pfb['denydir']}";
						}

						// Determine if 'Custom List' needs force updating before next CRON event.
						if ($list['custom_update'] == 'enabled') {
							unlink_if_exists("{$pfbfolder}/{$pfb_custom}.txt");
							// Uncheck 'enabled' in list 'custom_update' setting
							$pfb_config['installedpackages'][$ip_type]['config'][$key]['custom_update'] = 'disabled';
							$pfb['conf_mod'] = TRUE;
						}
					}
				}
			}
		}

		// If 'TLD' enabled and TLD Blacklists are defined, add to enabled DNSBL lists
		if ($pfb['dnsbl_tld']) {
			$tld_blacklist = pfbng_text_area_decode($pfb['dnsblconfig']['tldblacklist'], TRUE, FALSE);
			if (!empty($tld_blacklist)) {
				$pfb['existing']['dnsbl'][] = 'DNSBL_TLD';
			}
		}

		// Collect all .txt file names for each list type
		$list_types = array(	'match' => $pfb['matchdir'], 'permit' => $pfb['permitdir'], 'deny' => $pfb['denydir'],
					'native' => $pfb['nativedir'], 'dnsbl' => $pfb['dnsdir']);

		foreach ($list_types as $pftype => $pfbfolder) {
			$pfb_files = glob("{$pfbfolder}/*.txt");
			foreach ($pfb_files as $pfb_list) {
				$pfb_file = basename($pfb_list, '.txt');
				if ($pftype == 'deny') {
					$pfb['actual'][$pftype][] = "{$pfb_file},";	// Add Trailing ','
				} else {
					$pfb['actual'][$pftype][] = "{$pfb_file}";
				}
			}
		}

		$pfb['remove']	= FALSE;	// Flag to execute pfctl and rules ordering or reload of DNSBL domains
		$pfb['summary'] = FALSE;	// Execute final summary as a list was removed

		// Process to remove lists from Masterfile/DB folder if they do not exist
		if (isset($pfb['existing'])) {
			foreach ($pfb['existing'] as $pfb_exist) {
				$existing_type = $pfb_exist['type'];
				$pfbfolder = $pfb_exist['folder'];
				foreach ($pfb['actual'] as $pfb_act) {
					$actual_type = $pfb_act['type'];
					if ($existing_type == $actual_type) {
						switch ($existing_type) {
							case 'deny':
								$results = array_diff($pfb_act, $pfb_exist);
								$f_result = implode($results);
								if (!empty($f_result)) {
									$log = "[ Removing List(s) : {$f_result} ]\n";
									pfb_logger("{$log}", 1);
									// Script to Remove un-associated Lists
									exec("{$pfb['script']} remove x x x {$f_result} {$elog}");
									$pfb['summary'] = $pfb['remove'] = TRUE;
								}
								break;
							case 'match':
							case 'permit':
							case 'native':
								$results = array_diff($pfb_act, $pfb_exist);
								// This variable ($f_result) used in next section below.
								$f_result = implode($results);
								if (!empty($results)) {
									foreach ($results as $pfb_result) {
										$log = "[ Removing List(s) : {$pfb_result} ]\n";
										pfb_logger("{$log}", 1);
										unlink_if_exists("{$pfbfolder}/{$pfb_result}.txt");
									}
									$pfb['summary'] = $pfb['remove'] = TRUE;
								}
								break;
							case 'dnsbl':
								$dresults = array_diff($pfb_act, $pfb_exist);
								if (!empty($dresults)) {
									foreach ($dresults as $pfb_result) {
										$log = "[ Removing List(s) : {$pfb_result} ]\n";
										pfb_logger("{$log}", 1);
										rmdir_recursive("{$pfb['dnsdir']}");
										safe_mkdir("{$pfb['dnsdir']}");
									}

									// Query for any active pfBlockerNG CRON jobs
									$result_cron = array();
									exec('/bin/ps -wax', $result_cron);
									if (preg_grep("/pfblockerng[.]php\s+?(cron|update|updatednsbl)/", $result_cron)) {
										$log = "\n ** DNSBL Reload Terminated due to active pfBlockerNG cron process\n";
										pfb_logger("{$log}", 1);
									} else {
										if ($pfb['enable'] == 'on' && $pfb['dnsbl'] == 'on') {	
											pfb_logger("\n ** Running Background Reload Task\n", 1);
											// Clear any existing pfBlockerNG Cron Jobs to avoid collision
											install_cron_job('pfblockerng.php cron', false);
											$cmd = "/usr/local/bin/php /usr/local/www/pfblockerng/pfblockerng.php";
											mwexec_bg("{$cmd} updatednsbl >> {$pfb['log']} 2>&1");
										}
									}
								}
						}

						// Allow rebuilding of changed Alias to purge 'SKIP' Lists (when pfBlockerNG is enabled)
						if (!empty($results) && $pfb['enable'] == 'on') {
							$list_type = array('pfblockernglistsv4' => '_v4', 'pfblockernglistsv6' => '_v6');
							foreach ($list_type as $ip_type => $vtype) {
								foreach ($results as $removed_header) {
									if (isset($config['installedpackages'][$ip_type]['config'])) {
										foreach ($config['installedpackages'][$ip_type]['config'] as $list) {
											$alias = 'pfB_' . preg_replace("/\W/", '', $list['aliasname']);
											if (!empty($list['row'])) {
												foreach ($list['row'] as $row) {
													$removed = rtrim($removed_header, ',');
													if ($row['header'] == $removed) {
														$pfb['summary'] = $pfb['remove'] = TRUE;
														// Add Alias to update array
														$pfb_alias_lists[] = "{$alias}";
														$pfb_alias_lists_all[] = "{$alias}";
													}
												}
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}

	#########################################################
	#	Clear Match/Pass/ET/Original Files/Folders	#
	#########################################################

	// When pfBlockerNG is Disabled and 'Keep Blocklists' is Disabled.
	if ($pfb['enable'] == '' && $pfb['keep'] == '' && !$pfb['install']) {
		$log = "\n  Removing DB Files/Folders \n";
		pfb_logger("{$log}", 1);

		unlink_if_exists("{$pfb['dbdir']}/masterfile");
		unlink_if_exists("{$pfb['dbdir']}/mastercat");
		unlink_if_exists("{$pfb['supptxt']}");
		unlink_if_exists("{$pfb['dnsbl_info']}");
		rmdir_recursive("{$pfb['origdir']}");
		rmdir_recursive("{$pfb['matchdir']}");
		rmdir_recursive("{$pfb['permitdir']}");
		rmdir_recursive("{$pfb['denydir']}");
		rmdir_recursive("{$pfb['nativedir']}");
		rmdir_recursive("{$pfb['etdir']}");
		rmdir_recursive("{$pfb['dnsdir']}");
		rmdir_recursive("{$pfb['dnsorigdir']}");
		rmdir_recursive("{$pfb['dnsalias']}");
	}


	#################################################
	#	Create IP Suppression Txt File		#
	#################################################

	if ($pfb['enable'] == 'on' && $pfb['supp'] == 'on') {
		pfb_create_suppression_file();
	}

	#########################################
	#	DNSBL - Processes		#
	#########################################

	if ($pfb['dnsbl'] == 'on' && !$pfb['save']) {
		// Terminate if DNSBL VIP is empty
		$dnsbl_error = FALSE;
		if (empty($pfb['dnsbl_vip']) || empty($pfb['dnsbl_port']) || empty($pfb['dnsbl_port_ssl'])) {
			$log = "\n\n===[  DNSBL Virtual IP and/or Ports are not defined. Exiting  ]======\n";
			pfb_logger("{$log}", 1);
			$dnsbl_error = TRUE;
		}

		if (!$pfb['updatednsbl']) {
			// Determine if a DNSBL Reload is running
			$result_cron = array();
			exec('/bin/ps -wax', $result_cron);
			if (preg_grep("/pfblockerng[.]php\s+?(updatednsbl)/", $result_cron)) {
				$log = "\n ** DNSBL Reload Terminated due to active pfBlockerNG cron process\n";
				pfb_logger("{$log}", 1);
				$dnsbl_error = TRUE;
			}
		}
	}

	if ($pfb['dnsbl'] == 'on' && !$pfb['save'] && !$dnsbl_error) {
		$log = "\n===[  DNSBL Process  ]================================================\n";
		pfb_logger("{$log}", 1);

		if (isset($config['installedpackages']['pfblockerngdnsbl']['config']) || isset($config['installedpackages']['pfblockerngdnsbleasylist']['config'])) {

			// Collect existing DNSBL alias statistics
			// CSV file format [ alias name , updated timestamp , total domain count, total blocked count ]
			if (file_exists("{$pfb['dnsbl_info']}")) {
				$pfb['dnsbl_info_stats'] = array_map('str_getcsv', @file("{$pfb['dnsbl_info']}"));
			} else {
				$pfb['dnsbl_info_stats'] = array();
			}

			// Rebuild DNSBL database or DNSBL statistics if files are not found
			$dnsbl_missing = FALSE;
			if (!file_exists("{$pfb['dnsbl_file']}.conf")) {
				$dnsbl_missing = TRUE;
			}
			if (!file_exists("{$pfb['dnsbl_info']}")) {
				$dnsbl_missing = TRUE;
			}

			if ($dnsbl_missing) {
				$log = "Missing DNSBL stats and/or Unbound DNSBL conf file - Rebuilding\n";
				pfb_logger("{$log}", 1);
				$pfb['reuse_dnsbl'] = 'on';
			}

			// Collect Whitelist, create string, and save to file (for grep -vF -f cmd)
			$pfb_white = pfbng_text_area_decode($pfb['dnsblconfig']['suppression'], TRUE, FALSE); 
			$pfb_whitelist = '';
			if (!empty($pfb_white)) {
				foreach ($pfb_white as $white) {
					if (!empty($white)) {
						if (substr($white, 0, 1) == '.') {
							$pfb_whitelist .= "{$white} 60\n";
							$pfb_whitelist .= "\"" . ltrim($white, '.') . " 60\n";
						}
						else {
							$pfb_whitelist .= "\"{$white} 60\n";
						}
					}
				}
			}
			$pfb_whitelist .= "\"localhost.localdomain 60\n";	// Added due to SWC Feed
			@file_put_contents("{$pfb['dnsbl_tmp']}.sup", $pfb_whitelist, LOCK_EX);

			// Call Alexa whitelist process
			if ($pfb['dnsbl_alexa'] == 'on') {
				// Check if Alexa database exists
				if (!file_exists("{$pfb['dbdir']}/top-1m.csv")) {
					// Check if Alexa download already in progress
					exec('/bin/ps -wax', $result_cron);
					if (!preg_grep("/pfblockerng[.]php\s+al/", $result_cron)) {
						$log = "\nAlexa Database downloading ( approx 21M ) ... Please wait ...\n";
						pfb_logger("{$log}", 1);
						exec("/usr/local/bin/php /usr/local/www/pfblockerng/pfblockerng.php al");
					}
					else {
						$log = "\nAlexa download already in process...\n";
						pfb_logger("{$log}", 1);
					}
				}

				// Process Alexa database
				pfblockerng_alexa();
			}

			// Collect feeds and custom list configuration and format into one array ($lists).
			$lists = array();

			// Add DNSBL EasyList to '$lists array'
			if (!empty($config['installedpackages']['pfblockerngdnsbleasylist']['config'])) {
				foreach($config['installedpackages']['pfblockerngdnsbleasylist']['config'] as $list) {
					$lists[] = $list;
				}
			}

			if (isset($config['installedpackages']['pfblockerngdnsbl']['config'])) {
				foreach ($config['installedpackages']['pfblockerngdnsbl']['config'] as $list) {
					// If only the 'customlist' is defined. Remove the 'List row' data.
					if (empty($list['row'][0]['url'])) {
						unset($list['row']);
					}

					if (!empty($list['custom'])) {
						$list['row'][] = array( 'header'	=> preg_replace("/\W/", '', $list['aliasname']) . '_custom',
									'custom'	=> $list['custom'],
									'state'		=> 'Enabled',
									'update'	=> $list['custom_update'],
									'url'		=> 'custom'
									);
					}
					$lists[] = $list;
				}
			}

			// Define DNSBL arrays and variables
			$pfb['alias_dnsbl_all']		= array();	// Array of all DNSBL aliases
			$pfb['tld_update']		= array();	// Array of all DNSBL Aliases/Feeds used for TLD Function
			$pfb['domain_update']		= FALSE;	// Flag to signal update Unbound
			$pfb['updateip']		= FALSE;	// Flag to signal updates to DNSBL IP lists

			foreach ($lists as $list) {

				// Reset variables once per alias
				$lists_dnsbl_current	= array();		// Array of all active Lists in current alias
				$pfb['aliasupdate']	= FALSE;		// Flag to signal changes to alias
				$pfb['domain_clear']	= FALSE;		// Flag to signal no Aliases defined or all Aliases disabled.
				$alias_cnt		= 0;

				if ($list['action'] != 'Disabled' && isset($list['row'])) {
					$alias				= 'DNSBL_' . preg_replace("/\W/", '', $list['aliasname']);
					$pfb['alias_dnsbl_all'][]	= "{$alias}";

					foreach ($list['row'] as $key => $row) {
						if (!empty($row['url']) && $row['state'] != 'Disabled') {

							$header		= "{$row['header']}";
							$liteparser	= FALSE;	// Minimal DNSBL Parser
							$domain_data_ip	= array();	// Array of IPs found in feed
							$domain_data	= '';		// List of Domains found in feed

							// If row is a custom_list, set flag.
							if (isset($row['custom'])) {
								$custom = TRUE;
							} else {
								$custom = FALSE;
							}

							// EasyList - collect enabled EasyList categories
							if (isset($list['easycat'])) {
								$easylist = explode(',', $list['easycat']);
							} else {
								unset($easylist);
							}

							// Determine 'list' details (return array $pfbarr)
							pfb_determine_list_detail($list['action'], $header, 'pfblockerngdnsblsettings', '0');
							$pfbadv		= $pfbarr['adv'];
							$pfbfolder	= $pfbarr['folder'];
							$pfborig	= $pfbarr['orig'];
							$pfbreuse	= $pfbarr['reuse'];
							$logtab		= $pfbarr['logtab'];

							// Empty header field validation check
							if (empty($header)) {
								$log = "\n[ {$row['url']} ]{$logtab} Header Field cannot be empty. *Skipping* \n";
								pfb_logger("{$log}", 2);
								continue;
							}

							if (file_exists("{$pfbfolder}/{$header}.txt") && $pfbreuse == '') {
								if ($row['state'] == 'Hold') {
									$log = "\n[ {$header} ]{$logtab} static hold. [ NOW ]";
								} else {
									$log = "\n[ {$header} ]{$logtab} exists. [ NOW ]";
								}
								pfb_logger("{$log}", 1);

								// Collect existing list stats
								$lists_dnsbl_all[]	= "{$row['header']}.txt";
								$lists_dnsbl_current[]	= "{$row['header']}";
								$list_cnt		= exec("{$pfb['grep']} -c ^ {$pfbfolder}/{$header}.txt");
								$alias_cnt		= $alias_cnt + $list_cnt;

								// Set flag to update DNSBL_IP
								if (file_exists("{$pfbfolder}/{$header}.ip")) {
									$pfb['updateip'] = TRUE;
								}

							} else {
								if ($pfbreuse == 'on' && file_exists("{$pfborig}/{$header}.orig")) {
									$log = "\n[ {$header} ]{$logtab} Reload [ NOW ]";
								} else {
									$log = "\n[ {$header} ]{$logtab} Downloading update [ NOW ]";
								}
								pfb_logger("{$log}", 1);
								$file_dwn = "{$pfborig}/{$header}";

								if (!$custom) {
									pfb_logger(' .', 1);

									// Allow cURL SSL downgrade 'Flex' if user configured.
									$pflex = FALSE;
									if ($row['state'] == 'Flex') {
										$pflex = TRUE;
									}

									// Determine if list needs to be downloaded or reuse previously downloaded file.
									if ($pfbreuse == 'on' && file_exists("{$file_dwn}.orig")) {
										// File exists/reuse
										pfb_logger(' completed .', 1);
									} else {
										// Download file
										if (!pfb_download($row['url'], $file_dwn, $pflex, $header,
											$row['format'], 1, '')) {

											// Determine reason for download failure
											pfb_download_failure($alias, $header, $pfbfolder,
												$list['vtype'], $row['url']);

											// Utilize previously download file (If 'fail' marker exists)
											if (file_exists("{$pfbfolder}/{$header}.fail") &&
											    file_exists("{$file_dwn}.orig")) {
												pfb_logger("\n  Restoring previously downloaded file\n ", 2);
											} else {
												continue;
											}
										}
										else {
											// Clear any previous download fail marker
											unlink_if_exists("{$pfbfolder}/{$header}.fail");
										}
									}
								}
								else {
									// Collect custom list data.
									$custom_list = pfbng_text_area_decode($row['custom'], FALSE, TRUE);
									@file_put_contents("{$file_dwn}.orig", $custom_list, LOCK_EX);
									unset($custom_list);
									$liteparser = TRUE;
								}

								// Parse downloaded file for Domain names

								$e_skip = $e_found = FALSE;	// Variables for Easylists
								$iqrisk = FALSE;		// Variable for ET IQRisk
								$h3x_feed = FALSE;		// Variable for H3x.eu Feed
								$otx_feed = FALSE;		// Variable for Alienvault OTX Pulse
								$fail_list = ''; $csvfail = $ipcount = $ip_cnt = 0;
								if (($fhandle = @fopen("{$file_dwn}.orig", 'r')) !== FALSE) {
									while (($line = @fgets($fhandle, 3072)) !== FALSE) {

										// On 'category match', parse EasyList feed 
										if (isset($easylist)) {
											if (substr($line, 24, 19) == 'easylist_adservers.' ||
											    substr($line, 91, 28) == 'easyprivacy_trackingservers.') {
												$e_found = TRUE;
											}
										}
										else {
											if (strpos($line, '[Adblock Plus ') !== FALSE) {
												pfb_logger("\n\n Terminated - Easylists can not be used.\n", 1);
												break;
											}
										}

										// Skip unuseable EasyList lines
										if (isset($easylist) && !$e_found) {
											continue;
										}

										// Remove any '^M' characters
										if (strpos($line, "\r") !== FALSE) {
											$line = preg_replace(array("/\^M\s+/", "/\^M/"), '', $line);
										}

										// If 'tab' character found, replace with whitespace
										if (strpos($line, "\x09") !== FALSE) {
											$line = str_replace("\x09", ' ', $line);
										}

										// If '%20' found, remove.
										if (strpos($line, '%20') !== FALSE) {
											$line = str_replace('%20', '', $line);
										}

										// If 'http(s)://' found, remove.
										if (strpos($line, 'http://') !== FALSE || strpos($line, 'https://') !== FALSE) {
											$line = preg_replace("(^https?://)", '', $line);
										}

										// Remove any leading/trailing whitespaces
										$line = trim($line);

										// Remove blank lines
										if (empty($line)) {
											continue;
										}

										// Remove comment lines and special format considerations
										if (substr($line, 0, 1) == '#') {
											// Exit (hpHosts) when end of domain names found.
											if (strpos($line, 'Append critical updates below') !== FALSE) {
												break;
											}

											// Spamhaus format validation
											if (strpos($line, 'The Spamhaus Project Ltd') !== FALSE) {
												$liteparser = TRUE;
											}

											if ($line == '#family,type,url,status,first_seen,first_active,last_active,last_update') {
												$h3x_feed = $liteparser = TRUE;
											}
											continue;
										}

										// Convert CSV line into array
										if (!isset($easylist) && substr_count($line, ',') >= 2) {
											$csvline = str_getcsv($line, ',', '', '"');
										} else {
											$csvline = '';
										}

										// CSV parser
										if (!isset($easylist) && count($csvline) >= 2) {
											// Parse Phishtank CSV
											if (strpos($csvline[2], 'www.phishtank.com/phish_detail.php') !== FALSE) {
												$liteparser = TRUE;
												if (count($csvline) == 8) {
													$host = parse_url($csvline[1]);
													$line = $host['host'];
												} else {
													//Record Failed attemps
													$csvfail++;
													$fail_list .= $line . '|';
													continue;
												}
											}

											// Parse Bambenek Consulting domain list
											elseif (strpos($csvline[3], 'osint.bambenekconsulting.com') !== FALSE) {
												$liteparser = TRUE;
												if (count($csvline) == 4) {
													$line = $csvline[0];
												} else {
													//Record Failed attemps
													$csvfail++;
													$fail_list .= $line . '|';
													continue;
												}
											}

											// Parse Malware Corpus Tracker h3x.eu
											elseif ($h3x_feed) {
												if (count($csvline) == 8) {
													$line = $csvline[2];
												} else {
													//Record Failed attemps
													$csvfail++;
													$fail_list .= $line . '|';
													continue;
												}
											}

											// Parse Alienvault OTX pulse
											elseif ($otx_feed || strpos($csvline[0], 'Indicator type') !== FALSE) {
												$otx_feed = $liteparser = TRUE;
												if (count($csvline) == 3 && $csvline[0] == "'domain'") {
													$line = str_replace("'", '', $csvline[1]);
												} else {
													continue;
												}
											}

											// Parse ET IQRisk IPRep domain list
											elseif ($iqrisk || $line == 'domain, category, score') {
												$iqrisk = $liteparser = TRUE;
												$line = $csvline[0];
											}
										}
										$line = trim($line);

										// Parser for EasyList, enable collect of selected EasyList categories
										if (isset($easylist) && (strpos($line, '! *** easylist:') !== FALSE) ||
										    strpos($line, '! *** Fetched from:') !== FALSE) {
											// Skip all previous Easylist entries

											// Collect EasyList feed
											if (substr($line, 24, 19) == 'easylist_adservers.') {
												if (in_array('ea', $easylist) ? $e_skip = FALSE : $e_skip = TRUE);
											}
											elseif (substr($line, 24, 25) == 'easylist_adservers_popup.') {
												if (in_array('eap', $easylist) ? $e_skip = FALSE : $e_skip = TRUE);
											}
											elseif (substr($line, 30, 16) == 'adult_adservers.') {
												if (in_array('aa', $easylist) ? $e_skip = FALSE : $e_skip = TRUE);
											}
											elseif (substr($line, 30, 22) == 'adult_adservers_popup.') {
												if (in_array('aap', $easylist) ? $e_skip = FALSE : $e_skip = TRUE);
											}

											// Collect EasyPrivacy feed
											elseif (substr($line, 91, 28) == 'easyprivacy_trackingservers.') {
												if (in_array('epts', $easylist) ? $e_skip = FALSE : $e_skip = TRUE);
											}
											elseif (substr($line, 91, 42) == 'easyprivacy_trackingservers_international.') {
												if (in_array('epti', $easylist) ? $e_skip = FALSE : $e_skip = TRUE);
											}

											// End of useable EasyList feed
											elseif (substr($line, 24, 20) == 'easylist_thirdparty.') {
												break;
											}
											// End of useable EasyPrivacy feed
											elseif (substr($line, 91, 23) == 'easyprivacy_thirdparty.') {
												break;
											}
										}

										// Parse EasyList line
										if (isset($easylist)) {
											if (!$e_skip) {
												if (substr($line, 0, 2) != '||') {
													continue;
												}
												if (strpos($line, '^') !== FALSE) {
													$line = trim(str_replace('|', '', strstr($line, '^', TRUE)));
												} elseif (strpos($line, '$') !== FALSE) {
													$line = trim(str_replace('|', '', strstr($line, '$', TRUE)));
												} else {
													$line = trim(str_replace('|', '', $line));
												}

												if (strpos($line, '/') !== FALSE) {
													$line = strstr($line, '/', TRUE);
												}
												if (strpos($line, '*.') !== FALSE) {
													$line = substr(strrchr($line, '*.'), 2);
												}
												$liteparser = TRUE;
											}
											else {
												continue;
											}
										}

										// Parser for all other Domain feeds (Initial line preparation)
										if (!$liteparser) {
											// If '#' character found, remove characters after '#'
											if (strpos($line, '#') !== FALSE) {
												$line = strstr($line, '#', TRUE);
											}

											// Remove any leading/trailing whitespaces
											$line = trim($line);

											// If 'space' character found, remove characters before space
											if (strpos($line, ' ') !== FALSE) {
												$line = strstr($line, ' ', FALSE);
											}

											// Remove any leading/trailing whitespaces
											$line = trim($line);

											// If 'space' character found, remove characters after space
											if (strpos($line, ' ') !== FALSE) {
												$line = strstr($line, ' ', TRUE);
											}

											// If '/' character found, remove characters after '/'
											if (strpos($line, '/') !== FALSE) {
												$line = strstr($line, '/', TRUE);
											}
										} else {
											// If 'space' character found, remove characters after space
											if (strpos($line, ' ') !== FALSE) {
												$line = strstr($line, ' ', TRUE);
											}

											// Remove any leading/trailing whitespaces
											$line = trim($line);
										}

										// If special characters found, parse line for host
										if ((strpos($line, ';') !== FALSE || strpos($line, '&') !== FALSE ||
										    strpos($line, '?') !== FALSE || strpos($line, ":") !== FALSE)) {
											$host = parse_url($line);
											$line = $host['host'];
										}

										// Collect any IPs found in domain feed
										if (is_ipaddrv4($line)) {
											if ($pfb['dnsbl_ip'] != 'Disabled') {
												$parsed = sanitize_ipaddr($line, $custom);
												if (validate_ipv4($parsed)) {
													$domain_data_ip[] = $parsed;
													$pfb['updateip'] = TRUE;
													$ipcount++;
												}
											}
											continue;
										}

										// Remove invalid domains
										if (strpos($line, '.') === FALSE || substr($line, -1) == '.' ||
										    substr($line, 0, 1) == '.' || strpos($line, '..') !== FALSE) {
											continue;
										}

										$line = strtolower($line);
										$domain_data .= "local-data: \"" . $line . " 60 IN A {$pfb['dnsbl_vip']}\"\n";
									}
								}
								@fclose($fhandle);
								unset($csvline, $easylist);	// Unset variables

								// Remove duplicates and save any IPs found in domain feed
								if (!empty($domain_data_ip)) {
									$domain_data_ip = implode("\n", array_unique($domain_data_ip)) . "\n";
									@file_put_contents("{$pfbfolder}/{$header}.ip", $domain_data_ip, LOCK_EX);
									$ip_cnt = exec("{$pfb['grep']} -c ^ {$pfbfolder}/{$header}.ip");
								}

								// Validate feed with Unbound-checkconf
								if (!empty($domain_data)) {
									$dnsbl_file = "{$pfbfolder}/{$header}";

									$conf  = "server:\n";
									$conf .= "chroot: {$pfb['dnsbldir']}\n";
									$conf .= "username: \"unbound\"\n";
									$conf .= "directory: \"{$pfb['dnsbldir']}\"\n";
									$conf .= "pidfile: \"/var/run/unbound.pid\"\n";
									$conf .= "server:include: {$dnsbl_file}.bk";
									@file_put_contents("{$pfb['dnsbldir']}/check.conf", $conf, LOCK_EX);

									@file_put_contents("{$dnsbl_file}.bk", $domain_data, LOCK_EX);
									pfb_logger(".\n", 1);
		
									// Bypass Alexa whitelist if user configured
									$pfb_alexa = 'Disabled';
									if ($list['filter_alexa'] == 'on' && file_exists("{$pfb['dbdir']}/pfbalexawhitelist.txt")) {
										$pfb_alexa = 'on';
									}

									// Call script to process DNSBL 'De-Duplication / Whitelisting / Alexa Whitelisting'
									exec("{$pfb['script']} dnsbl_scrub {$header} {$pfb_alexa} {$elog}");

									if ($ip_cnt > 0) {
										pfb_logger("  IP count={$ip_cnt}\n", 1);
									}

									$result = array();
									exec("/usr/local/sbin/unbound-checkconf {$pfb['dnsbldir']}/check.conf 2>&1", $result);
									@unlink_if_exists("{$pfb['dnsbldir']}/check.conf");
								}
								else {
									$log = "\n No Domains Found\n";
									pfb_logger("{$log}", 1);
									continue;
								}

								// Exit further processing of feed if parse error found.
								if (!preg_grep("/unbound-checkconf: no errors/", $result)) {
									@unlink_if_exists("{$dnsbl_file}.bk");
									$log = "\n[ DNSBL FAIL ] [ Skipping : {$row['header']} ]\n\n";
									pfb_logger("{$log}", 2);
									$log = htmlspecialchars(implode("\n", $result));
									pfb_logger("{$log}", 1);
									continue;
								}
								else {
									// Save DNSBL feed
									$pfb['domain_update']	= $pfb['aliasupdate'] = $pfb['summary'] = TRUE;
									$lists_dnsbl_all[]	= "{$row['header']}.txt";
									$lists_dnsbl_current[]	= "{$row['header']}";
									@rename("{$dnsbl_file}.bk", "{$dnsbl_file}.txt");
									$list_cnt		= exec("{$pfb['grep']} -c ^ {$dnsbl_file}.txt");
									$alias_cnt		= $alias_cnt + $list_cnt;

									// Print failed domain parsing info
									if ($csvfail > 0) {
										$log = " * Failed Lines: {$csvfail}\n |{$fail_list}\n\n";
										pfb_logger("{$log}", 1);
									}
								}
							}
						}
					}

					// If changes found update DNSBL alias and TLD disabled, call function to update DNSBL alias
					if ($pfb['aliasupdate'] && !$pfb['dnsbl_tld']) {
						dnsbl_alias_update('update', $alias, $pfbfolder, $lists_dnsbl_current, $alias_cnt);
					}

					// Collect Alias/Feeds for post TLD function
					if ($pfb['dnsbl_tld']) {
						$pfb['tld_update'][$alias]['feeds']	= $lists_dnsbl_current;
						$pfb['tld_update'][$alias]['count']	= $alias_cnt;
					}
				}
				else {
					dnsbl_alias_update('disabled', $alias, '', '', '');
				}
			}

		}

		// Remove any unused DNSBL aliases
		$daliases = glob("{$pfb['dnsalias']}/*");
		if (!empty($daliases)) {
			foreach ($daliases as $dlist) {
				if (!in_array(basename($dlist), $pfb['alias_dnsbl_all'])) {
					unlink_if_exists ("{$dlist}");
				}
			}
		}

		// Save DNSBL Alias statistics (Not for TLD mode)
		if (!$pfb['dnsbl_tld']) {
			dnsbl_save_stats();
		}
	}

	// Update DNSBL_IP aliastable (Not for TLD mode)
	if ($pfb['enable'] == 'on' && $pfb['dnsbl'] == 'on' && $pfb['dnsbl_ip'] != 'Disabled') {

		$new_aliases[] = array( 'name'		=> 'pfB_DNSBLIP',
					'url'		=> "{$pfb['weblocal']}?pfb=pfB_DNSBLIP",
					'updatefreq'	=> '32',
					'address'	=> '',
					'descr'		=> 'pfBlockerNG auto DNSBL IP Alias',
					'type'		=> 'urltable',
					'detail'	=> 'DO NOT EDIT THIS ALIAS'
					);

		// Determine 'list' details (return array $pfbarr)
		pfb_determine_list_detail('unbound', '', 'pfblockerngdnsblsettings', '0');

		// Define DNSBL_IP firewall rule settings
		if ($pfb['dnsbl_ip'] != 'Alias_Deny') {
			pfb_firewall_rule($pfb['dnsbl_ip'], 'pfB_DNSBLIP', '', $pfb['dnsblconfig']['aliaslog'], $pfbarr['agateway_in'], $pfbarr['agateway_out'],
			    $pfbarr['aaddrnot_in'], $pfbarr['aaddr_in'], $pfbarr['aports_in'], $pfbarr['aproto_in'], $pfbarr['anot_in'],
			    $pfbarr['aaddrnot_out'], $pfbarr['aaddr_out'], $pfbarr['aports_out'], $pfbarr['aproto_out'], $pfbarr['anot_out']);
		}

		if ($pfb['save']) {
			if (file_exists("{$pfb['aliasdir']}/pfB_DNSBLIP.txt")) {
				exec("{$pfb['pfctl']} -t pfB_DNSBLIP -T replace -f {$pfb['aliasdir']}/pfB_DNSBLIP.txt 2>&1", $result);
			} else {
				exec("{$pfb['pfctl']} -t pfB_DNSBLIP -T flush 2>&1");
				exec("{$pfb['pfctl']} -t pfB_DNSBLIP -T add 1.1.1.1");
				@file_put_contents("{$pfb['aliasdir']}/pfB_DNSBLIP.txt", "1.1.1.1\n", LOCK_EX);
			}
		}

		// Update DNSBL_IP aliastable
		if ($pfb['updateip'] || !$pfb['save']) {
			dnsbl_ip_aliastable_update();
			$pfb['updateip'] = FALSE;	// Reset variable
		}
	}

	#########################################
	#	UPDATE Unbound DNS Database	#
	#########################################

	if ($pfb['domain_update']) {
		if (!empty($lists_dnsbl_all)) {
			pfb_logger("\n------------------------------------------\nAssembling database...", 1);
			$pfb_output = @fopen("{$pfb['dnsbl_file']}.raw", 'w');
			foreach ($lists_dnsbl_all as $current_list) {
				if (($handle = @fopen("{$pfb['dnsdir']}/{$current_list}", 'r')) !== FALSE) {
					while (($line = @fgets($handle, 3072)) !== FALSE) {
						@fwrite($pfb_output, $line);
					}
				}
				@fclose($handle);
			}
			@fclose($pfb_output);
		}
		else {
			$log = "\nDNSBL not Updated!\n";
			pfb_logger("{$log}", 1);
		}
	}
	else {
		// When DNSBL is enabled and no Aliases are defined, or all Aliases are Disabled. Set flag to clear out Unbound pfb_dnsbl.conf file.
		if (empty($lists_dnsbl_all) && !$pfb['save']) {
			pfb_logger("\nClearing all DNSBL Feeds... ", 1);
			$pfb['domain_clear'] = TRUE;
			$pfb_output = @fopen("{$pfb['dnsbl_file']}.conf", 'w');
			@fwrite($pfb_output, '');
			@fclose($pfb_output);
		}
	}

	#################################
	#	UNBOUND INTEGRATION	#
	#################################

	$pfbupdate = FALSE;
	if (file_exists("{$pfb['dnsbldir']}/unbound.conf")) {
		$conf = file("{$pfb['dnsbldir']}/unbound.conf");
	}

	if ($pfb['enable'] == 'on' && $pfb['dnsbl'] == 'on' && $pfb['unbound_state'] == 'on') {
		// If new domain updates found, backup existing DNSBL domain feed
		if ($pfb['domain_update'] || $pfb['domain_clear']) {
			if (file_exists ("{$pfb['dnsbl_file']}.conf")) {
				// Create backup of existing DNSBL database
				@copy("{$pfb['dnsbl_file']}.conf", "{$pfb['dnsbl_file']}.bk");
			}
			// Rename new DNSBL database to '.conf'
			@rename("{$pfb['dnsbl_file']}.raw", "{$pfb['dnsbl_file']}.conf");
			@file_put_contents("{$pfb['dnsbldir']}/unbound.tmp", $conf, LOCK_EX);
		}

		// Add 'include:' line in Unbound conf file if not found
		if (isset($conf) && !strstr(implode($conf), 'pfb_dnsbl.conf')) {
			if (file_exists("{$pfb['dnsbl_file']}.conf")) {
				$log = "\nAdding Unbound Server:Include line...";
				pfb_logger("{$log}", 1);

				$pfbupdate = TRUE;
				$conf[] = "# Unbound custom options\n\nserver:include: {$pfb['dnsbl_file']}.conf\n";
				@file_put_contents("{$pfb['dnsbldir']}/unbound.tmp", $conf, LOCK_EX);
			}
		}

		// Validate new Unbound conf file before use.
		if ($pfb['domain_update'] || $pfbupdate || $pfb['domain_clear']) {
			pfb_validate_unbound('enabled');
		}

		// Create DNSBL NAT and VIP and lighttpd web server conf if required.
		pfb_create_dnsbl('enable');
	}
	else {
		// When DNSBL is disabled and not during an installation.
		if ($pfb['dnsbl'] == '' && !$pfb['install']) {
			// Remove 'Unbound' conf integration
			if (isset($conf) && stripos(implode($conf), 'pfb_dnsbl.conf') !== FALSE) {
				$pfbupdate = FALSE;
				foreach ($conf as $key => $line) {
					if (strpos($line, 'pfb_dnsbl.conf') !== FALSE) {
						$pfbupdate = TRUE;
						unset ($conf[$key]);
					}
				}
				if ($pfbupdate) {
					@file_put_contents("{$pfb['dnsbldir']}/unbound.tmp", $conf, LOCK_EX);
					// Validate new Unbound conf file before use.
					pfb_validate_unbound('disabled');
				}
			}

			// Remove DNSBL NAT, VIP and lighttpd service
			pfb_create_dnsbl('disable');
		}


		// Use applicable log message
		if (!$pfbupdate) {
			if (!$pfb['save']) {
				$log = "\n** DNSBL Disabled **\n";
				pfb_logger("{$log}", 1);
			}
		} else {
			$log = "\n\n===[   DNSBL Disabled   ]==========================================\n";
			pfb_logger("{$log}", 1);
		}
	}
	unset($conf);
	unlink_if_exists("{$pfb['dnsbl_file']}.bk");


	#################################
	#	Assign Countries	#
	#################################

	if (!$pfb['save']) {
		$log = "\n\n===[  Continent Process  ]============================================\n";
		pfb_logger("{$log}", 1);
	}

	foreach ($pfb['continents'] as $continent => $pfb_alias) {
		if (isset($config['installedpackages']['pfblockerng' . strtolower(str_replace(' ', '', $continent))]['config'])) {
			$continent_config = $config['installedpackages']['pfblockerng' . strtolower(str_replace(' ', '', $continent))]['config'][0];
			$cc_name = 'pfblockerng' . strtolower(str_replace(' ', '', $continent));
			if ($continent_config['action'] != 'Disabled' && $pfb['enable'] == 'on') {

				// Determine if Continent lists require action (IPv4 and IPv6)
				$cont_type = array('countries4' => '_v4', 'countries6' => '_v6');
				foreach ($cont_type as $c_type => $vtype) {

					// Determine 'list' details (return array $pfbarr)
					pfb_determine_list_detail($continent_config['action'], "{$pfb_alias}{$vtype}", $cc_name, '0');
					$pfbadv		= $pfbarr['adv'];
					$pfbdescr	= $pfbarr['descr'];
					$pfbfolder	= $pfbarr['folder'];
					$pfborig	= $pfbarr['orig'];
					$logtab		= $pfbarr['logtab'];

					if (!empty($continent_config[$c_type])) {
						$ccfile	= "{$pfb_alias}{$vtype}";

						// Collect selected GeoIP ISOs
						if (($pfb_output = @fopen("{$pfb['geoip_tmp']}", 'w')) !== FALSE) {
							foreach (explode(',', $continent_config[$c_type]) as $iso) {

								$isofile = "{$pfb['ccdir']}/{$iso}{$vtype}.txt";
								if (($handle = @fopen("{$isofile}", 'r')) !== FALSE) {
									while (($line = @fgets($handle, 1024)) !== FALSE) {
										@fwrite($pfb_output, $line);
									}
								}
								else {
									pfb_logger("\nCould not open ISO [ {$iso}{$vtype} ]\n", 1);
								}
								@fclose($handle);
							}
						}
						else {
							pfb_logger("\n[ {$pfb_alias}{$vtype} ] Could not create GeoIP file handle\n", 1);
						}
						@fclose($pfb_output);

						// Collect md5 of new Continent data
						$continent		= 'md5_0';
						if (file_exists("{$pfb['geoip_tmp']}")) {
							$continent	= @md5_file("{$pfb['geoip_tmp']}");
						}

						// Collect md5 of existing Continent data
						$continent_ex		= 'md5_1';
						if (file_exists("{$pfborig}/{$ccfile}.orig")) {
							$continent_ex	= @md5_file("{$pfborig}/{$ccfile}.orig");
						}
						
						// Check if pfBlockerNG pfctl Continent tables are empty (pfBlockerNG was disabled w/ "keep", then re-enabled)
						$pfctlck = exec("{$pfb['pfctl']} -vvsTables | {$pfb['grep']} -A1 {$pfb_alias}{$vtype} | {$pfb['awk']} '/Addresses/ {s+=$2}; END {print s}'");

						if (empty($pfctlck) && file_exists("{$pfbfolder}/{$ccfile}.txt")) {
							@copy("{$pfbfolder}/{$ccfile}.txt", "{$pfb['aliasdir']}/{$ccfile}.txt");
							// Collect updated alias lists ('Reputation' disabled)
							$pfb_alias_lists[] = "{$pfb_alias}{$vtype}";
						}

						// Collect active alias lists (Used for pfctl update when 'Reputation' is enabled).
						$pfb_alias_lists_all[] = "{$pfb_alias}{$vtype}";

						// Compare existing (original file) and new Continent data
						if ($continent == $continent_ex && !empty($pfctlck)
						    && file_exists("{$pfbfolder}/{$ccfile}.txt") && $pfb['reuse'] == '') {
							if (!$pfb['save']) {
								$log = "\n[ {$pfb_alias}{$vtype} ]{$logtab} exists. [ NOW ]";
								pfb_logger("{$log}", 1);
							}
						} else {
							// Do not proceed with changes on user 'save'
							if (!$pfb['save']) {
								$log = "\n[ {$pfb_alias}{$vtype} ]{$logtab} Changes found... Updating\n";
								pfb_logger("{$log}", 1);

								// Execute Reputation functions, when changes are found.
								$pfb['repcheck'] = TRUE;

								// Collect updated alias lists ('Reputation' disabled)
								$pfb_alias_lists[] = "{$pfb_alias}{$vtype}";

								if ($continent != 'md5_0') {
									@rename("{$pfb['geoip_tmp']}", "{$pfborig}/{$ccfile}.orig");
									@copy("{$pfborig}/{$ccfile}.orig", "{$pfbfolder}/{$ccfile}.txt");

									// Call Aggregate process
									if ($pfb['agg'] == 'on' && $vtype == '_v4') {
										exec("{$pfb['script']} cidr_aggregate {$ccfile} {$pfbfolder} {$elog}");
									}

									// Call Duplication process
									if ($pfb['dup'] == 'on' && $vtype == '_v4' && $pfbadv) {
										exec("{$pfb['script']} continent {$ccfile} {$elog}");
									}

									// Save Continent data to aliastable folder
									@copy("{$pfbfolder}/{$ccfile}.txt", "{$pfb['aliasdir']}/{$ccfile}.txt");
								}

								// Check if file exists and is > 0 in size and save alias file
								$file_chk = 0;
								$cont_chk = "{$pfbfolder}/{$ccfile}.txt";
								if (file_exists($cont_chk) && @filesize($cont_chk) > 0) {
									$file_chk = exec("{$pfb['grep']} -cv '^#\|^$' {$cont_chk}");
								}

								if ($file_chk <= 1) {
									@file_put_contents("{$pfbfolder}/{$ccfile}.txt", "1.1.1.1\n", LOCK_EX);
									@copy("{$pfbfolder}/{$ccfile}.txt", "{$pfb['aliasdir']}/{$ccfile}.txt");
									$log = "[ {$pfb_alias}{$vtype} ] Found no unique IPs, adding '1.1.1.1' to avoid empty file\n";
									pfb_logger("{$log}", 1);
								}
							}
						}

						if (file_exists("{$pfbfolder}/{$ccfile}.txt")) {
							// Create alias config
							$new_aliases_list[] = "{$pfb_alias}{$vtype}";
							$new_aliases[] = array( 'name'		=> "{$pfb_alias}{$vtype}",
										'url'		=> "{$pfb['weblocal']}?pfb={$pfb_alias}{$vtype}",
										'updatefreq'	=> '32',
										'address'	=> '',
										'descr'		=> "pfBlockerNG {$vtype} {$pfbdescr} Country Alias",
										'type'		=> 'urltable',
										'detail'	=> 'DO NOT EDIT THIS ALIAS'
										);

							// Define firewall rule settings
							pfb_firewall_rule($continent_config['action'], $pfb_alias, $vtype, $continent_config['aliaslog'],
							    $pfbarr['agateway_in'], $pfbarr['agateway_out'], $pfbarr['aaddrnot_in'], $pfbarr['aaddr_in'],
							    $pfbarr['aports_in'], $pfbarr['aproto_in'], $pfbarr['anot_in'], $pfbarr['aaddrnot_out'],
							    $pfbarr['aaddr_out'], $pfbarr['aports_out'], $pfbarr['aproto_out'], $pfbarr['anot_out']);
						}
						else {
							// unlink Continent list
							unlink_if_exists("{$pfb['aliasdir']}/{$ccfile}.txt");
						}
					}
				}
			}
		}
	}
	// Remove temp file
	unlink_if_exists("{$pfb['geoip_tmp']}");

	#################################################
	#	Download and Collect IPv4/IPv6 lists	#
	#################################################

	// IPv4 REGEX Definitions
	$pfb['range']	= '/((?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))-((?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))/';
	$pfb['ipv4']	= '/(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)(\/(3[012]|[12]?[0-9]))?/';

	// IPv6 REGEX Definitions - Reference: http://labs.spritelink.net/regex
	$pfb['ipv6'] = '/((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:)))(%.+)?(\/[0-9][0-9]?|1([01][0-9]|2[0-8]))?/';

	if ($pfb['enable'] == 'on' && !$pfb['save']) {

		$pfb['supp_update'] = FALSE;
		$runonce_v4 = $runonce_v6 = TRUE;
		$list_type = array('pfblockernglistsv4' => '_v4', 'pfblockernglistsv6'	=> '_v6');
		$lists = array();

		// Collect lists and custom list configuration and format into one array ($lists).
		foreach	($list_type as $ip_type	=> $vtype) {
			if (!empty($config['installedpackages'][$ip_type]['config'])) {
				foreach	($config['installedpackages'][$ip_type]['config'] as $key => $list) {
					if ($vtype == '_v4') {
						$list['vtype'] = '_v4';
					} else {
						$list['vtype'] = '_v6';
					}

					// Collect list array key location
					$list['key'] = "{$key}";

					// If only the 'customlist' is defined. Remove the 'List row' data.
					if (empty($list['row'][0]['url'])) {
						unset($list['row']);
					}

					if (!empty($list['custom'])) {
						$list['row'][] = array(	'header'	=> preg_replace("/\W/", '', $list['aliasname']) . '_custom',
									'custom'	=> $list['custom'],
									'state'		=> 'Enabled',
									'update'	=> $list['custom_update'],
									'url'		=> 'custom'
									);
					}
					$lists[] = $list;
				}
			}
		}

		foreach	($lists	as $list) {
			if ($runonce_v4 && $list['vtype'] == '_v4') {
				$runonce_v4 = FALSE;
				$log = "\n\n===[  IPv4 Process  ]=================================================\n";
				pfb_logger("{$log}", 1);
			} elseif ($runonce_v6 && $list['vtype'] == '_v6') {
				$runonce_v6 = FALSE;
				$log = "\n\n===[  IPv6 Process  ]=================================================\n";
				pfb_logger("{$log}", 1);
			}

			if ($list['action'] != 'Disabled' && isset($list['row'])) {
				// Capture alias name
				$alias = 'pfB_' . preg_replace("/\W/", '', $list['aliasname']);
				foreach	($list['row'] as $row) {
					if (!empty($row['url']) && $row['state'] != 'Disabled') {

						if ($list['vtype'] == '_v4') {
							$header = "{$row['header']}";
						} else {
							$header = "{$row['header']}_v6";
						}

						// If row is a custom_list, set	flag.
						if (isset($row['custom'])) {
							$custom	= TRUE;
						} else {
							$custom	= FALSE;
						}

						// Determine 'list' details (return array $pfbarr)
						$list_type = 'pfblockernglists' . str_replace('_', '', $list['vtype']);
						pfb_determine_list_detail($list['action'], $header, $list_type, $list['key']);
						$pfbadv		= $pfbarr['adv'];
						$pfbfolder	= $pfbarr['folder'];
						$pfborig	= $pfbarr['orig'];
						$pfbreuse	= $pfbarr['reuse'];
						$logtab		= $pfbarr['logtab'];

						// Collect active alias list (Used for pfctl update when 'Reputation' is enabled.
						$pfb_alias_lists_all[] = "{$alias}";

						if (file_exists("{$pfbfolder}/{$header}.txt") && $pfbreuse == '') {
							if ($row['state'] == 'Hold') {
								$log = "\n[ {$header} ]{$logtab} static hold. [ NOW ]";
							} else {
								$log = "\n[ {$header} ]{$logtab} exists. [ NOW ]";
							}
							pfb_logger("{$log}", 1);
						} else {
							if ($pfbreuse == 'on' && file_exists("{$pfborig}/{$header}.orig")) {
								$log = "\n[ {$header} ]{$logtab} Reload [ NOW ]";
							} else {
								$log = "\n[ {$header} ]{$logtab} Downloading update [ NOW ]";
							}
							pfb_logger("{$log}", 1);
							$file_dwn = "{$pfborig}/{$header}";

							// Force 'Alias Native' setting to any Alias with 'Advanced Inbound/Outbound -Invert src/dst' settings.
							// This will bypass Deduplication and Reputation features.
							if ($pfbarr['aaddrnot_in'] == 'on' || $pfbarr['aaddrnot_out'] == 'on') {
								pfb_logger("Using Alias Native\n", 1);
							}

							if (!$custom) {
								pfb_logger(' .', 1);

								// Allow cURL SSL downgrade 'Flex' if user configured.
								$pflex = FALSE;
								if ($row['state'] == 'Flex') {
									$pflex = TRUE;
								}

								// Determine if	list needs to be downloaded or reuse previously downloaded file.
								if ($pfbreuse == 'on' && file_exists("{$file_dwn}.orig")) {
									// File exists/reuse

									// Process Emerging Threats IQRisk if required
									if (strpos($row['url'], 'iprepdata.txt') !== FALSE) {
										if (file_exists("{$file_dwn}.raw")) {
											exec("/usr/bin/gunzip -c {$file_dwn}.raw > {$file_dwn}.orig");
										}
										exec("{$pfb['script']} et {$header} x x x x x {$pfb['etblock']} {$pfb['etmatch']} {$elog}");
									}
								} else {
									// Download list
									if (!pfb_download($row['url'], $file_dwn, $pflex, $header, $row['format'],
										1, $list['vtype'])) {

										// Determine reason for download failure
										pfb_download_failure($alias, $header, $pfbfolder, $list['vtype'], $row['url']);

										// Utilize previously download file (If 'fail' marker exists)
										if (file_exists("{$pfbfolder}/{$header}.fail") &&
										    file_exists("{$file_dwn}.orig")) {
											pfb_logger("\n  Restoring previously downloaded file contents ", 2);
										} else {
											if ($pfbadv) {
												// Script to Remove failed lists from masterfile
												exec("{$pfb['script']} remove x x x {$header} {$elog}");
											}
											continue;
										}
									} else {
										// Clear any previous download fail marker
										unlink_if_exists("{$pfbfolder}/{$header}.fail");
										pfb_logger('.', 1);
									}
								}
								pfb_logger(' completed .', 1);
							}
							else {
								if ($list['whois_convert'] == 'on') {
									// Process Domain/AS based custom list
									$custom_list = str_replace("\n", ',', pfbng_text_area_decode($list['custom'], FALSE, TRUE));
									exec("{$pfb['script']} whoisconvert {$header} {$list['vtype']} {$custom_list} {$elog}");
								} else {
									// Process IP based custom list
									$custom_list = pfbng_text_area_decode($list['custom'], FALSE, TRUE);
									@file_put_contents("{$file_dwn}.orig", $custom_list, LOCK_EX);
								}
								pfb_logger(' . completed .', 1);
							}

							$ip_data = '';		// IPs collected from feed
							$parse_fail = 0;	// Failed parsed lines from feed
							pfb_logger('.', 1);

							// Set 'auto' format for all lists, except for lists that require 'regex' parsing.
							if ($row['format'] == 'regex') {
								$pftype = 'regex';
							}
							else {
								$url = pathinfo($row['url']);

								// Strip any text after '?'
								if (strpos($url['extension'], '?') !== FALSE) {
									$url['extension'] = strstr($url['extension'], '?', TRUE);
								}

								// Determine if list is an IBlock list
								if (strpos($url['dirname'], 'iblocklist') !== FALSE) {
									$url['extension'] = 'iblock';
								}

								// Use 'regex' IP parser for non-standard IP lists.
								if (in_array($url['extension'], array('html', 'htm', 'php', 'aspx', 'cgi', 'csv', 'rules', ''))) {
									$pftype = 'regex';
								} else {
									$pftype = 'auto';
								}
							}

							if (($fhandle = @fopen("{$file_dwn}.orig", 'r')) !== FALSE) {
								while (($line = @fgets($fhandle, 1024)) !== FALSE) {

									// Record original line for regex matching, if required.
									$oline = $line;

									// Remove any leading/trailing whitespaces
									$line = trim($line);

									// Remove commentlines and blank lines
									if (substr($line, 0, 1) == '#' || empty($line)) {
										continue;
									}

									$parse_error = FALSE;
									if ($list['vtype'] == '_v4' && $pftype == 'auto') {

										// IBlock - parser sample ( JKS Media, LLC:4.53.2.12-4.53.2.15 )
										// Remove leading domain name details
										if (strpos($line, '-') !== FALSE && strpos($line, ':') !== FALSE) {
											$line = str_replace(':', '', strstr($line, ':', FALSE));
										}

										// If 'space' character found, remove characters after space
										if (strpos($line, ' ') !== FALSE) {
											$line = strstr($line, ' ', TRUE);
										}

										// If '#' character found, remove characters after '#'
										if (strpos($line, '#') !== FALSE) {
											$line = str_replace('#', '', strstr($line, '#', TRUE));
										}

										// Remove any leading/trailing whitespaces
										$line = trim($line);

										// Range parser
										if (strpos($line, '-') !== FALSE) {
											$matches = explode('-', $line);
											if (count($matches) == 2) {
												$a_cidr = ip_range_to_subnet_array($matches[0],$matches[1]);
												if (!empty($a_cidr)) {
													foreach ($a_cidr as $cidr) {
														$cidr = sanitize_ipaddr($cidr, $custom);
														if (!empty($cidr)) {
															if (validate_ipv4($cidr)) {
																$ip_data .= $cidr . "\n";
															}
															else {
																$parse_error = TRUE;
															}
														}
													}
													if (!$parse_error) {
														continue;
													}
												}
											}
											else {
												$parse_error = TRUE;
											}
										}

										if (!$parse_error) {
											// Single address parser
											$parsed = sanitize_ipaddr($line, $custom);
											if (validate_ipv4($parsed)) {
												$ip_data .= $parsed . "\n";
												continue;
											}
											else {
												$parse_error = TRUE;
											}
										}
									}

									if ($list['vtype'] == '_v4' && ($pftype == 'regex' || $parse_error)) {

										// Use regex as last alternative.

										if (strpos($oline, '-') !== FALSE) {
											// Network range 192.168.0.0-192.168.0.254
											if (preg_match($pfb['range'], $oline, $matches)) {
												$a_cidr = ip_range_to_subnet_array($matches[1], $matches[2]);
												if (!empty($a_cidr)) {
													foreach ($a_cidr as $cidr) {
														$cidr = sanitize_ipaddr($cidr, $custom);
														if (validate_ipv4($cidr)) {
															$ip_data .= $cidr . "\n";
														}
														else {
															$parse_fail++;
														}
													}
												}
												continue;
											}
										}

										// IPv4/CIDR format 192.168.0.0 | 192.168.0.0/16
										if (preg_match_all($pfb['ipv4'], $oline, $matches)) {
											$matches = array_unique($matches[0]);
											foreach ($matches as $match) {
												$parsed = sanitize_ipaddr($match, $custom);
												if (validate_ipv4($parsed)) {
													$ip_data .= $parsed . "\n";
												}
											}
											continue;
										}
									}

									if ($list['vtype'] == '_v6') {
										// Auto IPv6 parser
										if ($pftype == 'auto') {
											if (strpos($line, ':') !== FALSE) {
												if (is_ipaddrv6($line) || is_subnet($line)) {
													$ip_data .= $line . "\n";
													continue;
												}
											}
										}

										// IPv6 Regex parser
										if (preg_match_all($pfb['ipv6'], $oline, $matches)) {
											$matches = array_unique($matches[0]);
											foreach ($matches as $match) {
												if (is_ipaddrv6($match) || is_subnet($match)) {
													$ip_data .= $match . "\n";
												}
											}
										}
									}
								}

								// Check for parse failures
								if (!empty($line) && !preg_match('/[a-zA-Z,;|\"\'?]/', $line)) {
									$parse_fail++;
									$log = "[!] Parse Errors [ {$parse_fail} ]\n";
									pfb_logger("{$log}", 2);
								}
							}
							@fclose($fhandle);
							pfb_logger("\n", 1);

							if (!$custom) {
								// Check to see if list actually failed download or has no IPs listed.
								$file_chk = '';
								if (file_exists("{$file_dwn}.orig") && @filesize("{$file_dwn}.orig") > 0) {
									$file_chk = exec("{$pfb['grep']} -cv '^#\|^$' {$file_dwn}.orig");
								}

								if ($file_chk == 0) {
									$ip_data = "1.1.1.1\n";
									$log = "  Empty file, Adding '1.1.1.1' to avoid download failure.\n";
									pfb_logger("{$log}", 1);
								}
							}

							if (!empty($ip_data)) {
								// Save List to '.txt' format in appropriate folder
								@file_put_contents("{$pfbfolder}/{$header}.txt", "{$ip_data}", LOCK_EX);

								// Call 'shell script' functions (Deny Actions only)
								if ($pfbadv && $list['vtype'] == '_v4') {
									$args = '';
									// Call Process255
									if ($pfb['dup'] == 'on' || $pfb['agg'] == 'on') {
										$args  = '_255';
									}
									// Call Aggregate process
									if ($pfb['agg'] == 'on') {
										$args .= '_agg';
									}
									// Call Reputation Max process
									if ($pfb['rep'] == 'on') {
										$args .= '_rep';
									}
									// Call Duplication process
									if ($pfb['dup'] == 'on') {
										$args .= '_dup';
									}
									if (!empty($args)) {
										exec("{$pfb['script']} {$args} {$header} {$pfb['max']} {$pfb['drep']} {$pfb['ccexclude']} {$pfb['ccwhite']} {$pfb['ccblack']} {$elog}");
									}
								}

								if (!$pfbadv && $list['vtype'] == '_v4') {
									// Call Aggregate process
									if ($pfb['agg'] == 'on') {
										exec("{$pfb['script']} cidr_aggregate {$header} {$pfbfolder} {$elog}");
									}
								}

								// Collect updated alias lists ('Reputation' disabled)
								$pfb_alias_lists[] = "{$alias}";

								if ($pfbadv && $list['vtype'] == '_v4') {
									// Execute Reputation functions, when changes are found.
									$pfb['repcheck'] = TRUE;

									// Enable suppression process due to updates
									if ($pfb['supp'] == 'on') {
										$pfb['supp_update'] = TRUE;
									}
								}
							} else {
								if (!$custom) {
									$log = "[ {$alias} {$header} ] List Error ]\n";
								}
								else {
									$log = "[ {$alias} {$header} ] Custom List Error ]\n";
								}
								pfb_logger("{$log}", 1);
							}
							// Unset variables
							unset($ip_data);
						}
					}
				}
			}
		}
	}


	#################################
	#	REPUTATION PROCESSES	#
	#################################

	// IP Reputation processes (pMax and dMax)
	if ($pfb['prep'] == 'on' && $pfb['repcheck'] && !$pfb['save'] && $pfb['enable'] == 'on') {
		// Script to run prep process
		exec("{$pfb['script']} pmax x {$pfb['pmax']} {$elog}");
	}
	if ($pfb['drep'] == 'on' && $pfb['repcheck'] && !$pfb['save'] && $pfb['enable'] == 'on') {
		// Script to run drep process
		exec("{$pfb['script']} dmax x {$pfb['dmax']} {$pfb['drep']} {$pfb['ccexclude']} {$pfb['ccwhite']} {$pfb['ccblack']} {$elog}");
	}

	#################################################
	#	CONFIGURE ALIASES AND FIREWALL RULES	#
	#################################################

	$list_type = array('pfblockernglistsv4' => '_v4', 'pfblockernglistsv6' => '_v6');
	foreach ($list_type as $ip_type => $vtype) {
		if (!empty($config['installedpackages'][$ip_type]['config']) && $pfb['enable'] == 'on') {
			$pfbrunonce = TRUE;
			foreach ($config['installedpackages'][$ip_type]['config'] as $key => $list) {
				$alias = 'pfB_' . preg_replace("/\W/", '', $list['aliasname']);

				// Skip any Alias that are 'enabled' but Lists/customlists are not defined.
				if (empty($list['row'][0]['url']) && empty($list['custom'])) {
					exec("{$pfb['pfctl']} -t {$alias} -T kill 2>&1", $result);
					continue;
				}

				// Determine 'list' details (return array $pfbarr)
				pfb_determine_list_detail($list['action'], '', $ip_type, $key);
				$pfbadv		= $pfbarr['adv'];
				$pfbdescr	= $pfbarr['descr'];
				$pfbfolder	= $pfbarr['folder'];

				// Only Save aliases that have been updated.
				// When 'Reputation' is used, all aliases need to be updated.
				$final_alias = array();
				if ($pfb['drep'] == 'on' || $pfb['prep'] == 'on') {
					if (!empty($pfb_alias_lists_all)) {
						$final_alias = array_unique($pfb_alias_lists_all);
					}
				} else {
					if (!empty($pfb_alias_lists)) {
						$final_alias = array_unique($pfb_alias_lists);
					}
				}

				if ($list['action'] != 'Disabled') {
					$pfbupdate = FALSE;
					$alias_ips = '';	// IP Collection of all Lists in the Alias

					if (isset($list['row'])) {
						foreach ($list['row'] as $row) {
							if (!empty($row['url']) && $row['state'] != 'Disabled') {
								if ($vtype == '_v4') {
									$header = "{$row['header']}";
								} else {
									$header = "{$row['header']}_v6";
								}
								$pfctlck = exec("{$pfb['pfctl']} -vvsTables | {$pfb['grep']} -A1 {$alias} | {$pfb['awk']} '/Addresses/ {s+=$2}; END {print s}'");

								// Update alias if list file exists and its been updated or if the alias URL table is empty.
								if (file_exists("{$pfbfolder}/{$header}.txt") && (in_array($alias, $final_alias) || empty($pfctlck))) {
									// Script to run suppression process (print header only)
									if ($pfbrunonce && $pfb['supp'] == 'on' && $vtype == '_v4' && $pfb['supp_update']) {
										exec("{$pfb['script']} suppress x x x suppressheader {$elog}");

										// Process suppression for DNSBL IP table
										if ($pfb['dnsbl'] == 'on' && $pfb['dnsbl_ip'] != 'Disabled') {
											exec("{$pfb['script']} suppress x x x pfB_DNSBLIP\|{$pfb['aliasdir']}/ {$elog}");
										}
										$pfbrunonce = FALSE;
									}
									// Script to run suppression process (body)
									if ($pfb['supp'] == 'on' && $vtype == '_v4' && $pfb['supp_update'] && $pfbadv) {
										if ($pfb['dup'] == 'on') {
											exec("{$pfb['script']} suppress x x x {$header}\|{$pfbfolder}/ {$elog}");
										} else {
											exec("{$pfb['script']} suppress x x off {$header}\|{$pfbfolder}/ {$elog}");
										}
									}
									$alias_ips .= file_get_contents("{$pfbfolder}/{$header}.txt");
									$pfbupdate = TRUE;
								}
							}
						}
					}

					// check custom network list
					if ($vtype == '_v4') {
						$aliasname = preg_replace("/\W/", '', $list['aliasname']) . '_custom';
					} else {
						$aliasname = preg_replace("/\W/", '', $list['aliasname']) . '_custom_v6';
					}

					// Update alias if list file exists and its been updated or if the alias URL table is empty.
					$pfctlck = exec("{$pfb['pfctl']} -vvsTables | {$pfb['grep']} -A1 {$alias} | {$pfb['awk']} '/Addresses/ {s+=$2}; END {print s}'");

					if (!empty($list['custom'])) {
						if (file_exists("{$pfbfolder}/{$aliasname}.txt") && in_array($alias, $final_alias) ||
						    file_exists("{$pfbfolder}/{$aliasname}.txt") && empty($pfctlck)) {
							$alias_ips .= file_get_contents("{$pfbfolder}/{$aliasname}.txt");
							$pfbupdate = TRUE;
						}
					}
					// Determine validity of alias URL tables/rules. ie: Don't create empty URL tables or aliases
					if (empty($alias_ips) && empty($pfctlck)) {
						unlink_if_exists("{$pfb['aliasdir']}/{$alias}.txt");
					} else {
						// Save only aliases that have been updated.
						if ($pfbupdate) {
							@file_put_contents("{$pfb['aliasdir']}/{$alias}.txt", $alias_ips, LOCK_EX);
						}

						// Add '[s]' to Alias descriptions (Bypass States removal feature)
						$adescr = "pfBlockerNG {$pfbdescr} List Alias";
						if ($list['stateremoval'] == 'disabled') {
							$adescr = "pfBlockerNG {$pfbdescr} List Alias [s]";
						}

						// Create alias
						$new_aliases_list[] = "{$alias}";
						$new_aliases[] = array(	'name'		=> "{$alias}",
									'url'		=> "{$pfb['weblocal']}?pfb={$alias}",
									'updatefreq'	=> '32',
									'address'	=> '',
									'descr'		=> "{$adescr}",
									'type'		=> 'urltable',
									'detail'	=> 'DO NOT EDIT THIS ALIAS'
									);

						// Define firewall rule settings
						pfb_firewall_rule($list['action'], $alias, '', $list['aliaslog'], $pfbarr['agateway_in'], $pfbarr['agateway_out'],
						    $pfbarr['aaddrnot_in'], $pfbarr['aaddr_in'], $pfbarr['aports_in'], $pfbarr['aproto_in'], $pfbarr['anot_in'],
						    $pfbarr['aaddrnot_out'], $pfbarr['aaddr_out'], $pfbarr['aports_out'], $pfbarr['aproto_out'], $pfbarr['anot_out']);
					}
				} else {
					// unlink previous pfblockerNG alias list
					unlink_if_exists("{$pfb['aliasdir']}/{$alias}.txt");
				}
			}
		}
	}
	// Clear variables
	$alias_ips = '';


	#########################################
	#	UPDATE pfSense ALIAS TABLES	#
	#########################################

	// Reload config.xml to get any recent changes
	$config = parse_config(true);

	$exist_aliases = $config['aliases']['alias'];
	if (isset($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $cbalias) {

			// Skip DNSBL IP aliastable as its updated independently
			if ($cbalias['name'] == 'pfB_DNSBLIP') {
				continue;
			}

			if (substr($cbalias['name'], 0, 4) == 'pfB_') {
				// Remove unreferenced pfB aliastable files
				if (!in_array($cbalias['name'], $new_aliases_list)) {
					unlink_if_exists("{$pfb['aliasdir']}/{$cbalias['name']}.*");
				}
			} else {
				$new_aliases[] = $cbalias;
			}
		}
	}

	// Update config.xml, if changes required
	if ($exist_aliases != $new_aliases) {
		$config['aliases']['alias'] = $new_aliases;
		write_config('pfBlockerNG: saving Aliases');
	}
	// Unset variables
	unset($new_aliases, $exist_aliases);


	#########################
	#	Assign Rules	#
	#########################

	// Only execute if autorules are defined or if an alias has been removed.
	if ($pfb['autorules'] || $pfb['enable'] == '' || $pfb['remove']) {
		$message = '';
		if (count($pfb['deny_inbound']) > 0 || count($pfb['permit_inbound']) > 0 || count($pfb['match_inbound']) > 0) {
			if (empty($pfb['inbound_interfaces'])) {
				$message = " Unable to apply rules. Inbound interface option not configured.";
			}
		}
		if (count($pfb['deny_outbound']) > 0 || count($pfb['permit_outbound']) > 0 || count($pfb['match_outbound']) > 0) {
			if (empty($pfb['outbound_interfaces'])) {
				$message .= "\n Unable to apply rules. Outbound interface option not configured.";
			}
		}

		if (empty($message)) {
			$new_rules = $permit_rules = $match_rules = $other_rules = $fpermit_rules = $fmatch_rules = $fother_rules = array();

			// Reload config.xml to get any recent changes
			$config = parse_config(true);

			// New vs old rules array comparison
			$orig_rules_nocreated = $new_rules_nocreated = array();

			// Collect all existing rules
			$rules = $config['filter']['rule'];

			// Collect existing pfSense rules 'pass', 'match' and 'other' pfSense rules into new arrays.
			if (!empty($rules)) {
				foreach ($rules as $rule) {
					// Remove DNSBL floating rule
					if ($rule['descr'] == 'pfB_DNSBL_Allow_access_to_VIP') {
						// Remove 'created' tag
						if (isset($rule['created'])) {
							unset($rule['created']);
						}
						$orig_rules_nocreated[] = $rule;
						continue;
					}

					// Remove all exisiting rules that start with 'pfB_' in the Rule Description
					if (substr($rule['descr'], 0, 4) != 'pfB_') {
						// Floating rules collection 'Floating Pass/Match', balance to 'other'
						if ($pfb['float'] == 'on') {
							if ($rule['type'] == 'pass' && $rule['floating'] == 'yes') {
								$fpermit_rules[] = $rule;
							} elseif ($rule['type'] == 'match' && $rule['floating'] == 'yes') {
								$fmatch_rules[] = $rule;
							} elseif ($rule['floating'] == 'yes') {
								$fother_rules[] = $rule;
							} else {
								$other_rules[] = $rule;
							}
						} else {
							// Collect only 'selected inbound and outbound interfaces'. balance to 'other'
							if (in_array($rule['interface'], $pfb['inbound_interfaces']) ||
							    in_array($rule['interface'], $pfb['outbound_interfaces'])) {
								// Floating rules 'off'. Collect 'floating other', pass, balance to 'other'
								if ($rule['floating'] == 'yes') {
									$fother_rules[] = $rule;
								} elseif ($rule['type'] == 'pass') {
									if ($pfb['order'] == 'order_0') {
										$other_rules[] = $rule;
									} else {
										$permit_rules[] = $rule;
									}
								} else {
									$other_rules[] = $rule;
								}
							} else {
								if ($rule['floating'] == 'yes') {
									$fother_rules[] = $rule;
								} else {
									$other_rules[] = $rule;
								}
							}
						}
					}

					// Remove 'created' tag
					if (isset($rule['created'])) {
						unset($rule['created']);
					}
					$orig_rules_nocreated[] = $rule;
				}
			}

			#################################################################################
			#			PASS/MATCH RULES ORDER(p/m)				#
			#  ORDER 0 |	pfB (all)	| All other	|				#
			#  ORDER 1 |	pfSense (p/m)	| pfB (p/m)	| pfB (b/r) 	| pfSense (b/r)	#
			#  ORDER 2 |	pfB (p/m)	| pfSense (p/m) | pfB (b/r)	| pfSense (b/r)	#
			#  ORDER 3 |	pfB (p/m)	| pfB (b/r) 	| pfSense (p/m)	| pfSense (b/r)	#
			#  ORDER 4 |	pfB (p/m)	| pfB (b/r)	| pfSense (b/r)	| pfSense (p/m)	#
			#################################################################################

			if ($pfb['float'] == '') {
				if (!empty($fother_rules)) {
					foreach ($fother_rules as $cb_rules) {
						$new_rules[] = $cb_rules;
					}
				}
			}
			else {
				if ($pfb['order'] == 'order_1') {
					foreach (array($fpermit_rules, $fmatch_rules, $fother_rules) as $rtype) {
						if (!empty($rtype)) {
							foreach ($rtype as $cb_rules) {
								$new_rules[] = $cb_rules;
							}
						}
					}
				}
			}

			// Define DNSBL 'Floating' pass rule for selected 'OPT' segments to be able to access the LAN DNSBL VIP
			if ($pfb['enable'] == 'on' && $pfb['dnsbl'] == 'on' && $pfb['dnsbl_rule'] != 'Disabled'
			    && !empty($pfb['dnsblconfig']['dnsbl_allow_int']) && isset($pfb['dnsbl_vip'])) {
				$rule			= $pfb['base_rule_float'];
				$rule['tracker']	= pfb_tracker('pfB_DNSBL_Allow_access_to_VIP', '', '');
				$rule['type']		= 'pass';
				$rule['direction']	= 'any';
				$rule['interface']	= "{$pfb['dnsblconfig']['dnsbl_allow_int']}";
				$rule['descr']		= 'pfB_DNSBL_Allow_access_to_VIP';
				$rule['source']		= array('any' => '');
				$rule['destination']	= array('address' => "{$pfb['dnsbl_vip']}");
				$rule['created']	= array('time' => (int)microtime(true), 'username' => 'Auto');
				$new_rules[] = $rule;
			}

			// Define inbound interface rules
			if (!empty($pfb['inbound_interfaces'])) {
				$pfbrunonce = TRUE;
				foreach ($pfb['inbound_interfaces'] as $inbound_interface) {
					if ($pfb['order'] == 'order_1' && !empty($permit_rules)) {
						foreach ($permit_rules as $cb_rules) {
							if ($cb_rules['interface'] == $inbound_interface) {
								$new_rules[] = $cb_rules;
							}
						}
					}
					// Match inbound rules defined as floating only.
					if ($pfbrunonce && !empty($pfb['match_inbound'])) {
						foreach ($pfb['match_inbound'] as $cb_rules) {
							$cb_rules['interface'] = $pfb['inbound_floating'];
							$cb_rules['tracker'] = pfb_tracker($cb_rules['descr'], $inbound_interface, 'match_in');
							$new_rules[] = $cb_rules;
							$pfbrunonce = FALSE;
						}
					}
					if ($pfb['order'] != 'order_0' && !empty($pfb['permit_inbound'])) {
						foreach ($pfb['permit_inbound'] as $cb_rules) {
							$cb_rules['interface'] = $inbound_interface;
							$cb_rules['tracker'] = pfb_tracker($cb_rules['descr'], $inbound_interface, 'permit_in');
							$new_rules[] = $cb_rules;
						}
					}
					if ($pfb['order'] == 'order_2') {
						foreach (array($fpermit_rules, $fmatch_rules) as $rtype) {
							if (!empty($rtype)) {
								foreach ($rtype as $cb_rules) {
									$new_rules[] = $cb_rules;
								}
							}
						}
						if (!empty($permit_rules)) {
							foreach ($permit_rules as $cb_rules) {
								if ($cb_rules['interface'] == $inbound_interface) {
									$new_rules[] = $cb_rules;
								}
							}
						}
					}
					if (!empty($pfb['deny_inbound'])) {
						foreach ($pfb['deny_inbound'] as $cb_rules) {
							$cb_rules['interface'] = $inbound_interface;
							$cb_rules['tracker'] = pfb_tracker($cb_rules['descr'], $inbound_interface, 'deny_in');
							$new_rules[] = $cb_rules;
						}
					}
					if ($pfb['order'] == 'order_0' && !empty($pfb['permit_inbound'])) {
						foreach ($pfb['permit_inbound'] as $cb_rules) {
							$cb_rules['interface'] = $inbound_interface;
							$cb_rules['tracker'] = pfb_tracker($cb_rules['descr'], $inbound_interface, 'permit_in');
							$new_rules[] = $cb_rules;
						}
					}
				}
			}

			// Define outbound interface rules
			if (!empty($pfb['outbound_interfaces'])) {
				$pfbrunonce = TRUE;
				foreach ($pfb['outbound_interfaces'] as $outbound_interface) {
					if ($pfb['order'] == 'order_1' && !empty($permit_rules)) {
						foreach ($permit_rules as $cb_rules) {
							if ($cb_rules['interface'] == $outbound_interface) {
								$new_rules[] = $cb_rules;
							}
						}
					}
					// Match outbound rules defined as floating only.
					if ($pfbrunonce && !empty($pfb['match_outbound'])) {
						foreach ($pfb['match_outbound'] as $cb_rules) {
							$cb_rules['interface'] = $pfb['outbound_floating'];
							$cb_rules['tracker'] = pfb_tracker($cb_rules['descr'], $outbound_interface, 'match_out');
							$new_rules[] = $cb_rules;
							$pfbrunonce = FALSE;
						}
					}
					if ($pfb['order'] != 'order_0' && !empty($pfb['permit_outbound'])) {
						foreach ($pfb['permit_outbound'] as $cb_rules) {
							$cb_rules['interface'] = $outbound_interface;
							$cb_rules['tracker'] = pfb_tracker($cb_rules['descr'], $outbound_interface, 'permit_out');
							$new_rules[] = $cb_rules;
						}
					}
					if ($pfb['order'] == 'order_2' && !empty($permit_rules)) {
						foreach ($permit_rules as $cb_rules) {
							if ($cb_rules['interface'] == $outbound_interface) {
								$new_rules[] = $cb_rules;
							}
						}
					}
					if (!empty($pfb['deny_outbound'])) {
						foreach ($pfb['deny_outbound'] as $cb_rules) {
							$cb_rules['interface'] = $outbound_interface;
							$cb_rules['tracker'] = pfb_tracker($cb_rules['descr'], $outbound_interface, 'deny_out');
							$new_rules[] = $cb_rules;
						}
					}
					if ($pfb['order'] == 'order_0' && !empty($pfb['permit_outbound'])) {
						foreach ($pfb['permit_outbound'] as $cb_rules) {
							$cb_rules['interface'] = $outbound_interface;
							$cb_rules['tracker'] = pfb_tracker($cb_rules['descr'], $outbound_interface, 'permit_out');
							$new_rules[] = $cb_rules;
						}
					}
				}
			}

			if ($pfb['float'] == 'on' && in_array($pfb['order'], array('order_0', 'order_3', 'order_4'))) {
				if ($pfb['order'] == 'order_0') {
					$rule_order = array($fother_rules, $fpermit_rules, $fmatch_rules);
				} else {
					$rule_order = array($fpermit_rules, $fmatch_rules, $fother_rules);
				}
				foreach ($rule_order as $rtype) {
					if (!empty($rtype)) {
						foreach ($rtype as $cb_rules) {
							$new_rules[] = $cb_rules;
						}
					}
				}
			}
			if ($pfb['float'] == 'on' && $pfb['order'] == 'order_2' && !empty($fother_rules)) {
				foreach ($fother_rules as $cb_rules) {
					$new_rules[] = $cb_rules;
				}
			}
			if ($pfb['order'] == 'order_4' && !empty($other_rules)) {
				foreach ($other_rules as $cb_rules) {
					$new_rules[] = $cb_rules;
				}
			}
			if ($pfb['order'] == 'order_4' && !empty($permit_rules)) {
				foreach ($permit_rules as $cb_rules) {
					$new_rules[] = $cb_rules;
				}
			}
			if ($pfb['order'] == 'order_3' && !empty($permit_rules)) {
				foreach ($permit_rules as $cb_rules) {
					$new_rules[] = $cb_rules;
				}
			}
			if ($pfb['order'] != 'order_4' && !empty($other_rules)) {
				foreach ($other_rules as $cb_rules) {
					$new_rules[] = $cb_rules;
				}
			}

			// Unset arrays
			unset($pfb['permit_inbound'], $pfb['permit_outbound'], $pfb['deny_inbound'],
			    $pfb['deny_outbound'], $pfb['match_inbound'], $pfb['match_outbound']);
			unset($cb_rules, $other_rules, $fother_rules, $permit_rules, $fpermit_rules, $match_rules, $fmatch_rules);

			// Remove 'created' tag (New vs old rules array comparison)
			foreach ($new_rules as $rule) {
				if (isset($rule['created'])) {
					unset($rule['created']);
				}
				$new_rules_nocreated[] = $rule;
			}

			// Update config.xml, if changes required
			if ($pfb['autorules'] && $orig_rules_nocreated != $new_rules_nocreated) {
				$config['filter']['rule'] = $new_rules;
				write_config('pfBlockerNG: saving Firewall rules');
			}
		}
		else {
			$log = "\n\n{$message}\n";
			pfb_logger("{$log}", 1);
		}
	}

	#################################
	#	pfSense Integration	#
	#################################

	// If 'Rule Changes' are found, utilize the 'filter_configure()' function, if not, utilize 'pfctl replace' command
	if ($pfb['autorules'] && $orig_rules_nocreated != $new_rules_nocreated || $pfb['enable'] == '' || $pfb['remove']) {
		require_once('filter.inc');

		if (!$pfb['save']) {
			$log = "\n===[  Aliastables / Rules  ]================================\n\n";
			pfb_logger("{$log}", 1);

			$log = "Firewall rule changes found, applying Filter Reload\n";
			syslog(LOG_NOTICE, "[pfBlockerNG] {$log}");
			pfb_logger("{$log}", 1);
		}

		// Remove all pfB aliastables
		exec("{$pfb['pfctl']} -s Tables | grep '^pfB_'", $pfb_tables);
		if (isset($pfb_tables)) {
			foreach ($pfb_tables as $pfb_table) {
				exec("{$pfb['pfctl']} -t {$pfb_table} -T kill 2>&1", $result);
			}
		}

		// When DNSBL is disabled and not during an installation.
		if ($pfb['dnsbl'] == '' && !$pfb['install']) {
			unlink_if_exists("{$pfb['aliasdir']}/pfB_DNSBLIP.txt");
		}

		filter_configure();		// Load filter_configure which will create the pfctl tables
		$pfb_filter_configure = TRUE;	// Flag to skip State removal function due to pf reloading

		// Call function for NanoBSD/Ramdisk processes.
		pfb_aliastables('update');
	} else {
		// Don't execute on user 'save'
		if (!$pfb['save']) {
			$log = "\n\n===[  Aliastables / Rules  ]==========================================\n\n";
			pfb_logger("{$log}", 1);

			$log = "No changes to Firewall rules, skipping Filter Reload\n";
			syslog(LOG_NOTICE, "[pfBlockerNG] {$log}");
			pfb_logger("{$log}", 1);

			// Only Save Aliases that have been updated.
			// When 'Reputation' is used, all aliases need to be updated when any alias has been updated.
			$final_alias = array();
			if ($pfb['repcheck'] && ($pfb['drep'] == 'on' || $pfb['prep'] == 'on')) {
				if (!empty($pfb_alias_lists_all)) {
					$final_alias = array_unique($pfb_alias_lists_all);
				}
			} else {
				if (!empty($pfb_alias_lists)) {
					$final_alias = array_unique($pfb_alias_lists);
				}
			}

			if (!empty($final_alias)) {
				foreach ($final_alias as $final) {
					$log = "\n Updating: {$final}\n";
					pfb_logger("{$log}", 1);
					$result = '';
					if (file_exists("{$pfb['aliasdir']}/{$final}.txt")) {
						exec("{$pfb['pfctl']} -t {$final} -T replace -f {$pfb['aliasdir']}/{$final}.txt 2>&1", $result);
						$log = implode($result);
					} else {
						$log = "Aliastable file not found\n";
					}
					pfb_logger("{$log}", 1);
				}
				pfb_logger("\n", 1);

				// Call function for NanoBSD/Ramdisk processes.
				pfb_aliastables('update');
			} else {
				$log = "No Changes to Aliases, Skipping pfctl Update\n";
				pfb_logger("{$log}", 1);
			}
		}
	}
	// Unset arrays
	unset($rules, $new_rules, $orig_rules_nocreated, $new_rules_nocreated);


	#################################
	#	SAVE CONFIGURATION	#
	#################################

	// Uncheck reusing existing downloads check box
	if (!$pfb['save'] && $pfb['enable'] == 'on' && $pfb['config']['pfb_reuse'] == 'on') {
		$pfb_config['installedpackages']['pfblockerng']['config'][0]['pfb_reuse'] = '';
		$pfb['conf_mod'] = TRUE;
	}

	// Only save config.xml, if changes are found.
	if ($pfb['conf_mod'] && isset($pfb_config)) {
		// Reload config.xml to get any recent changes and merge/save changes.
		$config = parse_config(true);
		$config = array_replace_recursive($config, $pfb_config);
		write_config('pfBlockerNG: save settings');
	}


	#################################
	#	KILL STATES		#
	#################################

	if ($pfb['kstates'] && !$pfb_filter_configure) {

		$log = "\n===[  Kill States  ]==================================================\n\n";
		pfb_logger("{$log}", 1);

		$tablesin = $tablesout = array();
		// Collect all 'pfB_' Rules that are 'Block/Reject' and do not have bypass states enabled
		if (isset($config['aliases']['alias'])) {
			foreach ($config['aliases']['alias'] as $alias) {
				if ($alias['type'] == 'urltable' && strpos($alias['name'], 'pfB_') !== FALSE && strpos($alias['descr'], '[s]') === FALSE) {
					if (isset($config['filter']['rule'])) {
						foreach ($config['filter']['rule'] as $rule) {
							if ($alias['name'] === $rule['source']['address'] || $alias['name'] === $rule['destination']['address']) {
								if ($rule['type'] == 'block' || $rule['type'] == 'reject') {
									if (isset($rule['source']['address']) && !isset($rule['source']['not'])) {
										$tablesin[] = $rule['source']['address'];
									} elseif (isset($rule['destination']['address']) && !isset($rule['destination']['not'])) {
										$tablesout[] = $rule['destination']['address'];
									}
								}
							}
						}
					}
				}
			}
		}

		$pfb_supp = array();	// List of IPs to suppress

		// Collect pfBlockerNGSuppress alias IPs
		$pfb_suppalias = array();
		if (isset($config['aliases']['alias'])) {
			foreach ($config['aliases']['alias'] as $key => $alias) {
				if ($alias['name'] == 'pfBlockerNGSuppress') {
					// Strip any '/32' CIDR values
					$pfb_suppalias = str_replace('/32', '', explode(' ', $config['aliases']['alias'][$key]['address']));
				}
			}

			// Convert '/24' CIDRs
			if (!empty($pfb_suppalias)) {
				$pfb_suppaliascidr = array();
				foreach ($pfb_suppalias as $key => $subnet) {
					if (strpos($subnet, '/24') !== FALSE) {
						$pfb_suppaliascidr = subnetv4_expand($subnet);
						unset($pfb_suppalias[$key]);
					}
				}
				$pfb_supp = array_merge($pfb_supp, $pfb_suppaliascidr);
			}
			$pfb_supp = array_merge($pfb_supp, $pfb_suppalias);
		}

		$pfb_supp[] = '0.0.0.0';	// Add 0.0.0.0 to suppression array

		// Collect Gateway IP addresses to suppress
		$int_gateway = get_interfaces_with_gateway();
		if (isset($int_gateway)) {
			foreach ($int_gateway as $gateway) {
				$convert = get_interface_ip($gateway);
				$pfb_supp[] = $convert;
			}
		}

		// Collect DNS servers to suppress
		$pfb_dnsservers = get_dns_servers();
		if (!empty($pfb_dnsservers)) {
			$pfb_supp = array_merge($pfb_supp, $pfb_dnsservers);
		}

		// Remove any duplicate IPs
		$pfb_supp = array_unique($pfb_supp);

		// Collect any 'Permit' Customlist IPs to suppress
		$custom_supp = array();
		foreach (array('pfblockernglistsv4', 'pfblockernglistsv6') as $ip_type) {
			if (!empty($config['installedpackages'][$ip_type]['config'])) {
				foreach ($config['installedpackages'][$ip_type]['config'] as $list) {
					if (!empty($list['custom']) && strpos($list['action'], 'Permit_') !== FALSE) {
						$custom = pfbng_text_area_decode($list['custom'], TRUE, FALSE);
						$custom_supp = array_merge($custom_supp, $custom);
					}
				}
			}
		}
		$custom_supp = array_unique(array_filter($custom_supp));
		// Append '/32' CIDR as required
		foreach ($custom_supp as &$custom) {
			if (strpos($custom, '/') === FALSE) {
				$custom = $custom . '/32';
			}
		}

		$statesin = $statesout = array();
		exec("{$pfb['pfctl']} -s state", $s_matches);
		if (!empty($s_matches)) {
			foreach ($s_matches as $key => $sline) {

				// SAMPLE : em0 udp 93.15.36.22:6881 -> 192.168.0.3:681		MULTIPLE:MULTIPLE
				// SAMPLE : pppoe0 udp 35.170.3.40:57197 (192.168.0.45:681) -> 22.41.123.206:1001	MULTIPLE:MULTIPLE
				// SAMPLE : em0 tcp 2001:65c:1398:101:124[443] <- 2001:170:2f:3e:a4c4:7b23:fe5f:b36e[52725]	FIN_WAIT_2:FIN_WAIT_2

				// Remove '( text )' from state line
				if (strpos($sline, '(') !== FALSE) {
					$s_pos1 = strpos($sline, '(');
					$s_pos2 = strpos($sline, ')');
					$s_len = $s_pos2 - $s_pos1 + 2;
					$sline = substr_replace($sline, '', $s_pos1, $s_len);
				}

				if (!empty($sline)) {
					list($int, $proto, $dir1, $sdirection, $dir2) = explode(' ', $sline);

					if ($sdirection == '<-') {
						// Inbound State
						$s_ip = $dir1;
					} else {
						// Outbound State
						$s_ip = $dir2;
					}

					// Strip port
					if (strpos($s_ip, '[') !== FALSE) {
						// Strip IPv6 port
						$s_ip = strstr($s_ip, '[', TRUE);
					} else {
						if (strpos($s_ip, ':') !== FALSE) {
							// Strip IPv4 port
							$s_ip = strstr($s_ip, ':', TRUE);
						}
					}

					// Exclude private, reserved and loopback IPs
					if (filter_var($s_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) &&
					    filter_var($s_ip, FILTER_CALLBACK, array('options' => 'FILTER_FLAG_NO_LOOPBACK_RANGE'))) {
						if ($sdirection == '<-') {
							$statesin[]  = $s_ip;
						} else {
							$statesout[] = $s_ip;
						}
					}
				}
			}
		}

		$statesin  = array_unique($statesin);
		natsort($statesin);
		$statesout = array_unique($statesout);
		natsort($statesout);

		$pfbfound = FALSE;
		foreach (array('<-' => $statesin, '->' => $statesout) as $s_type => $s_state_ips) {
			foreach ($s_state_ips as $s_ip) {
				if (!in_array($s_ip, $pfb_supp)) {

					// Bypass any 'Permit' Customlist IPs
					$pfb_suppress = FALSE;
					foreach ($custom_supp as $custom) {
						if (ip_in_subnet($s_ip, $custom)) {
							$pfb_suppress = TRUE;
							break;
						}
					}

					if (!$pfb_suppress) {
						if ($s_type == '<-') {
							$type = '-Inbound';
							$s_tables = $tablesin;
						} else {
							$type = '-Outbound';
							$s_tables = $tablesout;
						}

						foreach ($s_tables as $s_table) {
							$result = substr(exec("{$pfb['pfctl']} -t {$s_table} -T test {$s_ip} 2>&1"), 0, 1);
							if ($result > 0) {
								$pfbfound = TRUE;
								$log = " [ {$s_table}{$type} ] Removed state(s) for [ {$s_ip} ]\n";
								pfb_logger("{$log}", 1);
								foreach ($s_matches as $s_line) {
									if (strpos($s_line, $s_type) !== FALSE && strpos($s_line, $s_ip) !== FALSE) {
										pfb_logger(" {$s_line}\n", 1);
									}
								}

								// Remove states
								if ($s_type == '<-') {
									// Kill all state entries originating from $s_ip
									exec("{$pfb['pfctl']} -k {$s_ip}");
								} else {
									// Kill all state entries to the target $s_ip
									exec("{$pfb['pfctl']} -k 0.0.0.0/0 -k {$s_ip}");
								}
							}
						}
					}
				}
			}
		}
		if ($pfbfound) {
			pfb_logger("\n======================================================================\n", 1);
		} else {
			pfb_logger(" No matching states found\n======================================================================\n", 1);
		}
	}


	#########################################
	#	XMLRPC - sync process		#
	#########################################

	if (!platform_booting() && !$g['pfblockerng_install']) {
		pfblockerng_sync_on_changes();
	}

	#########################################
	#	Define/Apply CRON Jobs		#
	#########################################

	// Replace CRON job with any user changes to $pfb_min
	if ($pfb['enable'] == 'on' && $pfb['interval'] != 'Disabled') {
		// Define pfBlockerNG CRON job
		$pfb_cmd = "/usr/local/bin/php /usr/local/www/pfblockerng/pfblockerng.php cron >> {$pfb['log']} 2>&1";
		// $pfb['min'] ( User defined variable. Variable defined at start of script )

		// Define CRON hour (CRON interval & start hour)
		if ($pfb['interval'] == 1) {
			$pfb_hour = '*';
		} elseif ($pfb['interval'] == 24) {
			$pfb_hour = $pfb['24hour'];
		} else {
			$pfb_hour = implode(',', pfb_cron_base_hour());
		}

		$pfb_mday	= '*';
		$pfb_month	= '*';
		$pfb_wday	= '*';
		$pfb_who	= 'root';

		// Determine if CRON job requires updating
		if (!pfblockerng_cron_exists($pfb_cmd, $pfb['min'], $pfb_hour)) {
			install_cron_job($pfb_cmd, true, $pfb['min'], $pfb_hour, $pfb_mday, $pfb_month, $pfb_wday, $pfb_who);
		}
	}
	else {
		// Clear any existing pfBlockerNG CRON jobs
		install_cron_job('pfblockerng.php cron', false);
	}

	if ($pfb['enable'] == 'on') {
		// Define pfBlockerNG MaxMind CRON job
		$pfb_gcmd = "/usr/local/bin/php /usr/local/www/pfblockerng/pfblockerng.php dcc >> {$pfb['extraslog']} 2>&1";
		// MaxMind GeoIP CRON hour is randomized between 0-23 Hour to minimize effect on MaxMind website
		$pfb_gmin	= '0';
		$pfb_ghour	= rand(0,23);
		$pfb_gmday	= '1-7';
		$pfb_gmonth	= '*';
		$pfb_gwday	= '*';
		$pfb_gwho	= 'root';

		// Determine if CRON job requires updating
		if (!pfblockerng_cron_exists($pfb_gcmd, $pfb_gmin, 'maxmind')) {
			install_cron_job($pfb_gcmd, true, $pfb_gmin, $pfb_ghour, $pfb_gmday, $pfb_gmonth, $pfb_gwday, $pfb_gwho);
		}
	}
	else {
		// Clear any existing pfBlockerNG CRON jobs
		install_cron_job('pfblockerng.php dcc', false);
	}


	#################################
	#	FINAL REPORTING		#
	#################################

	// Only run with CRON or Force invoked process
	if ((!$pfb['save'] && $pfb['repcheck'] && $pfb['enable'] == 'on') || $pfb['summary']) {
		// Script to run final script processes.
		exec("{$pfb['script']} closing {$pfb['dup']} {$elog}");
	}

	if ($pfb['enable'] == 'on' && !$pfb['save'] || $pfb['summary']) {
		$log = "\n UPDATE PROCESS ENDED [ NOW ]\n";
		pfb_logger("{$log}", 1);
	}
}


function pfblockerng_validate_input($post, &$input_errors) {
	global $config;

	foreach ($post as $key => $value) {

		if (substr($key, 0, 3) == 'url' && is_numeric( substr($key, 3, (strlen($key) - 3))) ) {
			if (empty($value)) {
				$input_url_empty = TRUE;
				continue;
			}
			if (substr($value, 0, 1) == ' ') {
				$input_errors[] = 'Leading whitespace not allowed in URL field';
			}
		}

		if (substr($key, 0, 6) == 'header' && is_numeric( substr($key, 6, (strlen($key) - 6))) ) {
			if ($input_url_empty && empty($value)) {
				$input_url_empty = FALSE;
				continue;
			}
			if ($input_url_empty && !empty($value)) {
				$input_errors[] = 'No URL Defined.';
			}
			if (substr($value, 0, 1) == ' ' || empty($value)) {
				$input_errors[] = 'Header field must be defined.';
			}
			if (preg_match("/\W/", $value)) {
				$input_errors[] = 'Header field cannot contain special or international characters.';
			}
		}

		if ($key == 'pfb_dnsbl' && $value == 'on') {
			$pfb_dnsbl_state = TRUE;
		}

		if ($pfb_dnsbl_state) {
			if ($key == 'pfb_dnsvip' && empty($value)) {
				$input_errors[] = 'DNSBL VIP address must be defined.';
			}
			if ($key == 'pfb_dnsport' && empty($value) || $key == 'pfb_dnsport_ssl' && empty($value)) {
				$input_errors[] = 'DNSBL Port must be defined.';
			}
			if ($key == 'pfb_dnsport' && !is_port($value) || $key == 'pfb_dnsport_ssl' && !is_port($value)) {
				$input_errors[] = 'DNSBL Port must be a valid port in the range of 1 - 65535';
			}
		}
	}
}


// Function to De-Install pfBlockerNG
function pfblockerng_php_pre_deinstall_command() {
	require_once('config.inc');
	global $config, $pfb;

	// Set these two variables to disable pfBlockerNG on de-install
	$pfb['save'] = $pfb['install'] = TRUE;

	update_status("Removing pfBlockerNG...");
	sync_package_pfblockerng();

	// Maintain pfBlockerNG settings and database files if $pfb['keep'] is ON.
	if ($pfb['keep'] != 'on') {
		update_status(" Removing all customizations/data...");
		// Remove pfBlockerNG log and DB folder
		rmdir_recursive("{$pfb['dbdir']}");
		rmdir_recursive("{$pfb['logdir']}");

		// Remove aliastables archive and earlyshellcmd if found.
		@unlink_if_exists("{$pfb['aliasarchive']}");
		if (isset($config['system']['earlyshellcmd'])) {
			$a_earlyshellcmd = &$config['system']['earlyshellcmd'];
			if (preg_grep("/pfblockerng.sh aliastables/", $a_earlyshellcmd)) {
				$a_earlyshellcmd = preg_grep("/pfblockerng.sh aliastables/", $a_earlyshellcmd, PREG_GREP_INVERT);
			}
		}

		// Remove settings from config
		if (isset($config['installedpackages']['pfblockerng']))
			unset($config['installedpackages']['pfblockerng']);
		if (isset($config['installedpackages']['pfblockerngglobal']))
			unset($config['installedpackages']['pfblockerngglobal']);
		if (isset($config['installedpackages']['pfblockerngsync']))
			unset($config['installedpackages']['pfblockerngsync']);
		if (isset($config['installedpackages']['pfblockerngreputation']))
			unset($config['installedpackages']['pfblockerngreputation']);
		if (isset($config['installedpackages']['pfblockernglistsv4']))
			unset($config['installedpackages']['pfblockernglistsv4']);
		if (isset($config['installedpackages']['pfblockernglistsv6']))
			unset($config['installedpackages']['pfblockernglistsv6']);
		if (isset($config['installedpackages']['pfblockerngdnsbl']))
			unset($config['installedpackages']['pfblockerngdnsbl']);
		if (isset($config['installedpackages']['pfblockerngdnsblsettings']))
			unset($config['installedpackages']['pfblockerngdnsblsettings']);
		if (isset($config['installedpackages']['pfblockerngdnsbleasylist']))
			unset($config['installedpackages']['pfblockerngdnsbleasylist']);
		if (isset($config['installedpackages']['pfblockerngafrica']))
			unset($config['installedpackages']['pfblockerngafrica']);
		if (isset($config['installedpackages']['pfblockerngantarctica']))
			unset($config['installedpackages']['pfblockerngantarctica']);
		if (isset($config['installedpackages']['pfblockerngasia']))
			unset($config['installedpackages']['pfblockerngasia']);
		if (isset($config['installedpackages']['pfblockerngeurope']))
			unset($config['installedpackages']['pfblockerngeurope']);
		if (isset($config['installedpackages']['pfblockerngnorthamerica']))
			unset($config['installedpackages']['pfblockerngnorthamerica']);
		if (isset($config['installedpackages']['pfblockerngoceania']))
			unset($config['installedpackages']['pfblockerngoceania']);
		if (isset($config['installedpackages']['pfblockerngsouthamerica']))
			unset($config['installedpackages']['pfblockerngsouthamerica']);
		if (isset($config['installedpackages']['pfblockerngtopspammers']))
			unset($config['installedpackages']['pfblockerngtopspammers']);
		if (isset($config['installedpackages']['pfblockerngproxyandsatellite']))
			unset($config['installedpackages']['pfblockerngproxyandsatellite']);

		unlink_if_exists('/usr/local/sbin/lighttpd_pfb');
		unlink_if_exists("{$pfb['dnsbl_conf']}");
		unlink_if_exists("{$pfb['dnsbl_cert']}");
		unlink_if_exists("{$pfb['dnsbl_info']}");
		unlink_if_exists("{$pfb['aliasarchive']}");
	}
	else {
		update_status(" All customizations/data will be retained...");
	}

	// Remove incorrect xml setting
	if (isset($config['installedpackages']['pfblockerngantartica'])) {
		unset($config['installedpackages']['pfblockerngantartica']);
	}

	// Remove widget (code from Snort deinstall)
	$pfb['widgets'] = $config['widgets']['sequence'];
	if (!empty($pfb['widgets'])) {
		$widgetlist = explode(',', $pfb['widgets']);
		foreach ($widgetlist as $key => $widget) {
			if (strpos($widget, 'pfblockerng-container') !== FALSE) {
				unset($widgetlist[$key]);
			}
		}
		$config['widgets']['sequence'] = implode(',', $widgetlist);
		write_config('pfBlockerNG: Remove widget');
	}

	update_status(" done.\n");
}


/* Uses XMLRPC to synchronize the changes to a remote node */
function pfblockerng_sync_on_changes() {
	global $config;

	// Create array of sync settings and exit if sync is disabled.
	if (isset($config['installedpackages']['pfblockerngsync']['config'][0])) {
		$pfb_sync = $config['installedpackages']['pfblockerngsync']['config'][0];
		if ($pfb_sync['varsynconchanges'] == 'disabled' || empty($pfb_sync['varsynconchanges'])) {
			return;
		}
		$synctimeout = $pfb_sync['varsynctimeout'] ?: 150;
	} else {
		return;
	}

	pfb_logger("\n===[  XMLRPC Sync ]===================================================\n", 1);
	syslog(LOG_NOTICE, '[pfBlockerNG] XMLRPC sync is starting.');

	if (isset($config['installedpackages']['pfblockerngsync']['config'])) {
		switch ($pfb_sync['varsynconchanges']) {
			case 'manual':
				if (isset($pfb_sync['row'])) {
					$rs = $pfb_sync['row'];
				} else {
					log_error('[pfBlockerNG] Manual XMLRPC sync is enabled but there are no replication targets configured.');
					return;
				}
				break;
			case 'auto':
				if (isset($config['hasync'])) {
					$system_carp			= $config['hasync'];
					$rs[0]['varsyncipaddress']	= $system_carp['synchronizetoip'];
					$rs[0]['varsyncusername']	= $system_carp['username'];
					$rs[0]['varsyncpassword']	= $system_carp['password'];
					$rs[0]['varsyncdestinenable']	= FALSE;

					// XMLRPC sync is currently only supported over connections using the same protocol and port as this system
					if ($config['system']['webgui']['protocol'] == 'http') {
						$rs[0]['varsyncprotocol']	= 'http';
						$rs[0]['varsyncport']		= $config['system']['webgui']['port'] ?: '80';
					} else {
						$rs[0]['varsyncprotocol']	= 'https';
						$rs[0]['varsyncport']		= $config['system']['webgui']['port'] ?: '443';
					}

					if (empty($system_carp['synchronizetoip'])) {
						log_error('[pfBlockerNG] Auto XMLRPC sync is enabled but there is no sync IP address configured.');
						return;
					} else {
						$rs[0]['varsyncdestinenable']	= TRUE;
					}
				} else {
					log_error('[pfBlockerNG] Auto XMLRPC sync is enabled but there are no replication targets configured.');
					return;
				}
				break;
			default:
				return;
				break;
		}
		if (isset($rs)) {
			foreach ($rs as $sh) {
				// Only sync enabled replication targets
				if ($sh['varsyncdestinenable']) {
					$sync_to_ip	= $sh['varsyncipaddress'];
					$port		= $sh['varsyncport'];
					$password	= $sh['varsyncpassword'];
					$protocol	= $sh['varsyncprotocol'];
					$username	= $sh['varsyncusername'] ?: 'admin';

					$validate = TRUE;
					$error = '| ';

					if (empty($password)) {
						$error .= 'Password parameter missing. | ';
						$validate = FALSE;
					}
					if (!is_ipaddr($sync_to_ip) && !is_hostname($sync_to_ip) && !is_domain($sync_to_ip)) {
						$error .= 'Mis-configured Target IP/Host address. | ';
						$validate = FALSE;
					}
					if (!is_port($port)) {
						$error .= 'Mis-configured Target Port setting. |';
						$validate = FALSE;
					}

					if ($validate) {
						pfb_logger("\n Sync with [ {$protocol}://{$sync_to_ip}:{$port} ] ...", 1);
						$success = pfblockerng_do_xmlrpc_sync($sync_to_ip, $port, $protocol, $username, $password, $synctimeout);

						if ($success) {
							pfb_logger(" done.\n", 1);
							syslog(LOG_NOTICE, "[pfBlockerNG] XMLRPC sync to [ {$sync_to_ip}:{port} ] completed successfully.");
						} else {
							pfb_logger(" Failed!\n", 1);
						}
					} else {
						pfb_logger(" terminated due to the following error(s): {$error}", 1);
						log_error("[pfBlockerNG] XMLRPC sync to [ {$sync_to_ip}:{port} ] terminated due to the following error(s): {$error}");
					}
				}
			}
		}
	}
	pfb_logger("\n======================================================================\n", 1);
}


/* Do the actual XMLRPC sync */
function pfblockerng_do_xmlrpc_sync($sync_to_ip, $port, $protocol, $username, $password, $synctimeout) {
	global $config, $g;
	$success = TRUE;

	// Take care of IPv6 literal address
	if (is_ipaddrv6($sync_to_ip)) {
		$sync_to_ip = "[{$sync_to_ip}]";
	}

	/* xml will hold the sections to sync */
	$xml = array();

	// If User Disabled, remove 'General Tab Customizations' from Sync
	if (!isset($config['installedpackages']['pfblockerngsync']['config']['syncinterfaces'])) {
		if (isset($config['installedpackages']['pfblockerng'])) {
			$xml['pfblockerng']		= $config['installedpackages']['pfblockerng'];
		}
		if (isset($config['installedpackages']['pfblockerngdnsblsettings'])) {
			$xml['pfblockerngdnsblsettings']= $config['installedpackages']['pfblockerngdnsblsettings'];
		}
	}

	if (isset($config['installedpackages']['pfblockerngreputation']))
		$xml['pfblockerngreputation']		= $config['installedpackages']['pfblockerngreputation'];
	if (isset($config['installedpackages']['pfblockernglistsv4']))
		$xml['pfblockernglistsv4']		= $config['installedpackages']['pfblockernglistsv4'];
	if (isset($config['installedpackages']['pfblockernglistsv6']))
		$xml['pfblockernglistsv6']		= $config['installedpackages']['pfblockernglistsv6'];
	if (isset($config['installedpackages']['pfblockerngtopspammers']))
		$xml['pfblockerngtopspammers']		= $config['installedpackages']['pfblockerngtopspammers'];
	if (isset($config['installedpackages']['pfblockerngafrica']))
		$xml['pfblockerngafrica']		= $config['installedpackages']['pfblockerngafrica'];
	if (isset($config['installedpackages']['pfblockerngantarctica']))
		$xml['pfblockerngantarctica']		= $config['installedpackages']['pfblockerngantarctica'];
	if (isset($config['installedpackages']['pfblockerngasia']))
		$xml['pfblockerngasia']			= $config['installedpackages']['pfblockerngasia'];
	if (isset($config['installedpackages']['pfblockerngeurope']))
		$xml['pfblockerngeurope']		= $config['installedpackages']['pfblockerngeurope'];
	if (isset($config['installedpackages']['pfblockerngnorthamerica']))
		$xml['pfblockerngnorthamerica']		= $config['installedpackages']['pfblockerngnorthamerica'];
	if (isset($config['installedpackages']['pfblockerngoceania']))
		$xml['pfblockerngoceania']		= $config['installedpackages']['pfblockerngoceania'];
	if (isset($config['installedpackages']['pfblockerngsouthamerica']))
		$xml['pfblockerngsouthamerica']		= $config['installedpackages']['pfblockerngsouthamerica'];
	if (isset($config['installedpackages']['pfblockerngproxyandsatellite']))
		$xml['pfblockerngproxyandsatellite']	= $config['installedpackages']['pfblockerngproxyandsatellite'];
	if (isset($config['installedpackages']['pfblockerngdnsbleasylist']))
		$xml['pfblockerngdnsbleasylist']	= $config['installedpackages']['pfblockerngdnsbleasylist'];
	if (isset($config['installedpackages']['pfblockerngdnsbl']))
		$xml['pfblockerngdnsbl']		= $config['installedpackages']['pfblockerngdnsbl'];

	// Execute applicable XMLRPC code as per pfSense version
	if (substr(trim(file_get_contents('/etc/version')), 0, 3) < '2.4') {

		require_once('xmlrpc.inc');
		require_once('xmlrpc_client.inc');

		$url = "{$protocol}://{$sync_to_ip}";

		/* assemble xmlrpc payload */
		$params = array(XML_RPC_encode($password), XML_RPC_encode($xml));

		/* set a few variables needed for sync code borrowed from filter.inc */
		$method = 'pfsense.merge_installedpackages_section_xmlrpc';
		$msg = new XML_RPC_Message($method, $params);
		$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
		$cli->setCredentials($username, $password);
		if ($g['debug']) {
			$cli->setDebug(1);
		}

		/* send our XMLRPC message and timeout after defined sync timeout value */
		$resp = $cli->send($msg, $synctimeout);

		if (!$resp) {
			log_error("[pfBlockerNG] XMLRPC communications error occurred while attempting sync with {$url}:{$port}.");
			file_notice('sync_settings', $error, 'pfBlockerNG Settings Sync', '');
			$success = FALSE;
		} elseif ($resp->faultCode()) {
			$cli->setDebug(1);
			$resp = $cli->send($msg, $synctimeout);
			log_error("[pfBlockerNG] XMLRPC errors syncing with {$url}:{$port} - Code " . $resp->faultCode() . ": " . $resp->faultString());
			file_notice('sync_settings', $error, 'pfBlockerNG Settings Sync', '');
			$success = FALSE;
		}
		return $success;
	}
	else {
		require_once('xmlrpc_client.inc');

		// xmlrpc cannot encode NULL objects/arrays
		foreach ($xml as $xmlkey => $xmlvalue) {
			if (gettype($xmlvalue) == 'NULL') {
				$xml[$xmlkey] = array();
			}
		}

		$synctimeout = intval($synctimeout);
		$rpc_client = new pfsense_xmlrpc_client();
		$rpc_client->setConnectionData($sync_to_ip, $port, $username, $password, $protocol);
		$resp = $rpc_client->xmlrpc_method('merge_installedpackages_section', $xml, $synctimeout);

		if (!isset($resp)) {
			return FALSE;
		} else {
			return TRUE;
		}
	}
}
?>
