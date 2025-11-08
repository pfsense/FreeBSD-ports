<?php
/*
 * pfblockerng.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2015-2024 BBcan177@gmail.com
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

require_once('config.lib.inc');
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

define('LOG_PREFIX_PKG_PFBLOCKERNG', 'pfBlockerNG');

global $g, $pfb;
if (!is_array($pfb)) {
	$pfb = array();
}

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
$pfb['ggrep']	= '/usr/local/bin/ggrep';	// textproc/gnugrep
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
$pfb['log']		= "{$pfb['logdir']}/pfblockerng.log";
$pfb['dnslog']		= "{$pfb['logdir']}/dnsbl.log";
$pfb['dnsreplylog']	= "{$pfb['logdir']}/dns_reply.log";
$pfb['ip_blocklog']	= "{$pfb['logdir']}/ip_block.log";
$pfb['ip_permitlog']	= "{$pfb['logdir']}/ip_permit.log";
$pfb['ip_matchlog']	= "{$pfb['logdir']}/ip_match.log";
$pfb['unilog']		= "{$pfb['logdir']}/unified.log";
$pfb['errlog']		= "{$pfb['logdir']}/error.log";
$pfb['pyerrlog']	= "{$pfb['logdir']}/py_error.log";
$pfb['extraslog']	= "{$pfb['logdir']}/extras.log";
$pfb['dnsbl_parse_err']	= "{$pfb['logdir']}/dnsbl_parsed_error.log";

$pfb['master']		= "{$pfb['dbdir']}/masterfile";
$pfb['supptxt']		= "{$pfb['dbdir']}/pfbsuppression.txt";
$pfb['dnsbl_supptxt']	= "{$pfb['dbdir']}/pfbdnsblsuppression.txt";
$pfb['geoip_isos']	= "{$pfb['dbdir']}/geoip.txt";
$pfb['dnsbl_info']	= '/var/unbound/pfb_py_dnsbl.sqlite';
$pfb['dnsbl_resolver']	= '/var/unbound/pfb_py_resolver.sqlite';
$pfb['dnsbl_cache']	= '/var/unbound/pfb_py_cache.sqlite';
$pfb['asn_cache']	= "{$pfb['dbdir']}/asn_cache.sqlite";
$pfb['ip_cache']	= "{$pfb['dbdir']}/ip_cache.sqlite";

$pfb['script']		= '/usr/local/pkg/pfblockerng/pfblockerng.sh';
$pfb['feeds']		= '/usr/local/www/pfblockerng/pfblockerng_feeds.json';
$pfb['aliasarchive']	= '/usr/local/etc/aliastables.tar.bz2';

$pfb['dnsbl_tld_txt']	= "{$pfb['dnsdir']}/DNSBL_TLD.txt";
$pfb['dnsbl_tld_data']	= '/usr/local/pkg/pfblockerng/dnsbl_tld';
$pfb['dnsbl_conf']	= '/var/unbound/pfb_dnsbl_lighty.conf';
$pfb['dnsbl_cert']	= '/var/unbound/dnsbl_cert.pem';
$pfb['unbound_py_conf']	= '/var/unbound/pfb_unbound.ini';
$pfb['unbound_py_wh']	= '/var/unbound/pfb_py_whitelist.txt';
$pfb['unbound_py_data']	= '/var/unbound/pfb_py_data.txt';
$pfb['unbound_py_zone'] = '/var/unbound/pfb_py_zone.txt';
$pfb['unbound_py_ss']	= '/var/unbound/pfb_py_ss.txt';
$pfb['unbound_py_count']= '/var/unbound/pfb_py_count';

$pfb['dnsbl_safesearch']		= '/usr/local/pkg/pfblockerng/pfb_dnsbl.safesearch.conf';
$pfb['dnsbl_youtube_restrict']		= '/usr/local/pkg/pfblockerng/pfb_dnsbl.youtube_restrict.conf';
$pfb['dnsbl_youtube_restrictmoderate']	= '/usr/local/pkg/pfblockerng/pfb_dnsbl.youtube_restrictmoderate.conf';
$pfb['dnsbl_doh']			= '/usr/local/pkg/pfblockerng/pfb_dnsbl.doh.conf';

// tmp files
$pfb['geoip_tmp']		= '/tmp/pfb_continent';
$pfb['ip_unlock']		= '/tmp/ip_unlock';

$pfb['dnsbl_tld_remove']	= '/tmp/dnsbl_tld_remove';
$pfb['dnsbl_add']		= '/tmp/dnsbl_add';
$pfb['dnsbl_add_zone']		= '/tmp/dnsbl_add_zone';
$pfb['dnsbl_add_data']		= '/tmp/dnsbl_add_data';
$pfb['dnsbl_remove']		= '/tmp/dnsbl_remove';
$pfb['dnsbl_remove_zone']	= '/tmp/dnsbl_remove_zone';
$pfb['dnsbl_remove_data']	= '/tmp/dnsbl_remove_data';
$pfb['dnsbl_unlock']		= '/tmp/dnsbl_unlock';
$pfb['states_tmp']		= '/tmp/pfb_states';

// Unbound files and folders
$pfb['dnsbl_file']	= '/var/unbound/pfb_dnsbl';	// Filename Extension not referenced
$pfb['dnsbldir']	= '/var/unbound';

// Array definitions
$pfb['continents'] = array (	'Top Spammers'		=> 'pfB_Top',
				'Africa'		=> 'pfB_Africa',
				'Antarctica'		=> 'pfB_Antarctica',
				'Asia'			=> 'pfB_Asia',
				'Europe'		=> 'pfB_Europe',
				'North America'		=> 'pfB_NAmerica',
				'Oceania'		=> 'pfB_Oceania',
				'South America'		=> 'pfB_SAmerica',
				'Proxy and Satellite'	=> 'pfB_PS'
				);

$pfb['continent_list'] = array_flip(array('pfB_Africa', 'pfB_Antarctica', 'pfB_Asia', 'pfB_Europe', 'pfB_NAmerica', 'pfB_Oceania', 'pfB_SAmerica', 'pfB_Top'));

// Base rule array
$pfb['base_rule_reg'] = array('ipprotocol' => 'inet');

// Floating rules, base rule array
$pfb['base_rule_float'] = array('quick' => 'yes', 'floating' => 'yes', 'ipprotocol' => 'inet');

// Define Arrays for managing the IP mastefile
foreach (array('existing', 'actual') as $pftype) {
	$pfb[$pftype]['match']	= array();
	$pfb[$pftype]['permit']	= array();
	$pfb[$pftype]['deny']	= array();
	$pfb[$pftype]['native']	= array();
	$pfb[$pftype]['dnsbl']	= array();
}

// Default cURL options
$pfb['curl_defaults'] = array(  CURLOPT_USERAGENT	=> 'pfSense/pfBlockerNG cURL download agent-' . system_get_uniqueid(),
				CURLOPT_SSL_CIPHER_LIST	=> 'TLSv1.3, TLSv1.2',
				CURLOPT_FOLLOWLOCATION	=> true,
				CURLOPT_SSL_VERIFYPEER	=> true,
				CURLOPT_SSL_VERIFYHOST	=> true,
				CURLOPT_FRESH_CONNECT	=> true,
				CURLOPT_FILETIME	=> true,
				CURLOPT_TCP_NODELAY	=> true,
				CURLOPT_CONNECTTIMEOUT	=> 15,
				CURLOPT_AUTOREFERER	=> true,
				CURLOPT_MAXREDIRS	=> 10,
				CURLOPT_HTTP_VERSION	=> CURL_HTTP_VERSION_NONE,
				CURLOPT_FORBID_REUSE	=> true,
				CURLOPT_SSL_ENABLE_ALPN	=> true,
				CURLOPT_SSL_ENABLE_NPN	=> true,
				);

// RFC7231 HTTP response codes
$pfb['rfc7231'] = array(
			1 => 'Unsupported Protocol',		2 => 'Early Initilization failed',		3 => 'Malformed URL',
			4 => 'Requested feature not found',	5 => 'Could not Resolve Proxy',			6 => 'Could not Resolve Host',
			7 => 'Failed to connect',		8 => 'Failed to parse data',			9 => 'Denied access',
			10 => 'FTP failed',			11 => 'FTP password failure',			12 => 'FTP Accept timeout',
			13 => 'FTP PASV failure',		14 => 'FTP 227 Format failure',			15 => 'FTP Host lookup failure',
			16 => 'HTTP2 framing layer failure',	17 => 'FTP Could not set transfer mode',	18 => 'File Transfer failure',
			19 => 'FTP weird reply',		20 => 'Obsolete error',				21 => 'FTP quote command error',
			22 => 'HTTP Returned Error code',	23 => 'Write Error',				24 => 'Obsolete error',
			25 => 'FTP failted starting upload',	26 => 'Read Error',				27 => 'Memory allocation request error',
			28 => 'Operation timed out',		29 => 'Obsolete error',				30 => 'FTP Port command failure',
			31 => 'FTP REST failure',		32 => 'Obsolete error',				33 => 'Range Request failure',
			34 => 'HTTP Post error',		35 => 'SSL Connect error',			37 => 'Could not read file',
			38 => 'LDAP Cannot bind',		39 => 'LDAP Search failed',			40 => 'Obsolete error',
			41 => 'Function not found',		42 => 'Aborted by callback',			43 => 'Bad parameter',
			44 => 'Obsolete error',			45 => 'Interface failure',			46 => 'Obsolete error',
			47 => 'Too many redirects',		48 => 'Unknown option',				49 => 'Setopt Syntax error',
			50 => 'Obsolete error',			51 => 'Obsolete error',				52 => 'Curl got nothing',
			53 => 'SSL Engine not found',		54 => 'SSL Engine failure',			55 => 'Failed sending data',
			56 => 'Failure receving data',		57 => 'Obsolete error',				58 => 'Local client certificate error',
			59 => 'Cipher error',			60 => 'SSL certificate error',			61 => 'Transfer encoding error',
			62 => 'Obsolete error',			63 => 'Maximum file size exceeded',		64 => 'FTP SSL level failure',
			65 => 'Rewinding operation failed',	66 => 'SSL Engine failure',			67 => 'Login Denied',
			68 => 'TFTP server not found',		69 => 'TFTP permission error',			70 => 'Out of disk space on server',
			71 => 'Illegal TFTP operation',		72 => 'Unknown TFTP transfer ID',		73 => 'File already exists',
			74 => 'TFTP no such user',		75 => 'Obsolete error',				76 => 'Obsolete error',
			77 => 'SSL CA certificate error',	78 => 'Remote file not found',			79 => 'SSH connection failure',
			80 => 'SSH connection failure',		81 => 'Socket is not ready',			82 => 'CRL Bad File error',
			83 => 'SSL Issurer failure',		84 => 'FTP PRET failure',			85 => 'RTSP CSeq number error',
			86 => 'RTSP Session mismatch',		87 => 'FTP Bad file list',			88 => 'Chunk callback error',
			89 => 'No connection available',	90 => 'Pin Key match failure',			91 => 'Invalid Certificate status',
			92 => 'HTTP/2 framing error',		93 => 'Recursive API call error',		94 => 'Authentication function error',
			95 => 'HTTP/3 layer error',
			96 => 'QUIC connect error',		97 => 'Proxy handshake error',			98 => 'SSL Client certificate required',
			99 => 'Unrecoveranle Poll error',

			100 => '100 Continue',			101 => '101 Switching Protocols',		102 => '102 Processing',

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
			521 => '521 Web Server is down',	598 => '598 Network read timeout error',	599 => '599 Network connect timeout error',

			520 => 'CF 520 Unknown Error',		522 => 'CF 522 Connection Timed Out',
			523 => 'CF 523 Origin is Unreachable',	524 => 'CF 524 A Timeout Occured',		525 => 'CF 525 SSL Handshake Failed',
			526 => 'CF 526 Invalid SSL Certificate',527 => 'CF 527 Railgun Error'
			);

// File download Mime-Types
$pfb['mime_types'] = array_flip(array(	'inode/x-empty',
					'text/x-file',
					'text/plain',
					'text/html',
					'text/xml',
					'text/csv',
					'application/csv',
					'application/json',
					'application/x-ndjson',
					'application/x-tar',
					'application/gzip',
					'application/x-gzip',
					'application/x-bzip2',
					'application/zip'));

// pfb_filter constants
define('PFB_FILTER_HTML', 1);
define('PFB_FILTER_URL', 2);
define('PFB_FILTER_WORD', 3);
define('PFB_FILTER_WORD_DOT', 4);
define('PFB_FILTER_TLD', 5);
define('PFB_FILTER_DOMAIN', 6);
define('PFB_FILTER_HOSTNAME', 7);
define('PFB_FILTER_IPV4', 8);
define('PFB_FILTER_IP', 9);
define('PFB_FILTER_ALPHA', 10);
define('PFB_FILTER_ALNUM', 11);
define('PFB_FILTER_NUM', 12);
define('PFB_FILTER_CSV', 13);
define('PFB_FILTER_CSV_WHOIS', 14);
define('PFB_FILTER_CSV_CRON', 15);
define('PFB_FILTER_FILE_MIME_COMPARE', 16);
define('PFB_FILTER_FILE_MIME', 17);
define('PFB_FILTER_FILE_MIME_COMPRESSED', 18);
define('PFB_FILTER_ATYPE', 19);
define('PFB_FILTER_HEX_COLOR', 20);
define('PFB_FILTER_ON_OFF', 21);

// Function to filter/sanitize user input
function pfb_filter($input, $type, $reference='Unknown', $default='', $escape=FALSE) {
	global $pfb;

	$header = "\n PFB_FILTER - {$type} | {$reference} [ NOW ]";

	$return_type = $default;
	if (in_array($type, array(PFB_FILTER_URL, PFB_FILTER_FILE_MIME_COMPARE, PFB_FILTER_FILE_MIME, PFB_FILTER_FILE_MIME_COMPRESSED))) {
		$return_type = FALSE;
	}

	if (!in_array($type, array(PFB_FILTER_ON_OFF, PFB_FILTER_NUM))) {
		if (empty($input) || is_null($input)) {
			return $return_type;
		}
	}

	// Check for control characters
	if (is_array($input)) {
		foreach ($input as $vline) {
			if (preg_match("/[\p{C}]+/", $vline)) {
				pfb_logger("{$header} Control characters found [ " . htmlspecialchars($vline) . " ]", 6); 
				return $return_type;
			}
		}
	}
	else {
		if (preg_match("/[\p{C}]+/", $input)) {
			pfb_logger("{$header} Control characters found [ " . htmlspecialchars($input) . " ]", 6);
			return $return_type;
		}
	}

	$result = FALSE;
	switch ($type) {
		case PFB_FILTER_HTML:
			$result = htmlspecialchars(trim($input));
			break;
		case PFB_FILTER_URL:
			// Validate URL input
			$is_RSYNC = FALSE;
			if (strpos($input, '::') !== FALSE && !$escape) {
				$rsync = explode('::', $input);
				if (count($rsync) == 2 && !empty($rsync[0]) && filter_var($rsync[0], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
					$is_RSYNC = TRUE;
				}
			}

			if ($is_RSYNC || filter_var($input, FILTER_VALIDATE_URL)) {
				if ($is_RSYNC) {
					$data = array('host' => $rsync[0], 'path' => $rsync[1]);
				} else {
					$data = parse_url($input);
					if (!in_array($data['scheme'], array('http', 'https', 'rsync', 'ftp'))) {
						pfb_logger("{$header} Invalid URL Scheme [ " . htmlspecialchars($input) . " ]", 6);
						return FALSE;
					}
				}

				$validate_list = '';
				if (!$data || !is_array($data)) {
					pfb_logger("{$header} Invalid URL (missing data) [ " . htmlspecialchars($input) . " ]", 6);
					return FALSE;
				} elseif (!isset($data['host']) || empty($data['host'])) {
					pfb_logger("{$header} Invalid URL (missing hostname) [ " . htmlspecialchars($input) . " ]", 6);
					return FALSE;
				} elseif (is_ipaddr($data['host'])) {
					$validate_list = array(array('type' => 'IP', 'data' => $data['host']));
				} else {
					$validate_list = resolve_host_addresses("{$data['host']}.");
				}

				$pfsense_configured = FALSE;
				if (!empty($validate_list) && is_array($validate_list)) {
					foreach ($validate_list as $validate) {
						if ($validate['type'] == 'CNAME' && !empty($validate['data'])) {

							if (is_ipaddr($validate['data'])) {
								$cname_list = resolve_host_addresses($validate['data']);
							} else {
								$cname_list = resolve_host_addresses("{$validate['data']}.");
							}

							if (!empty($cname_list) && is_array($cname_list)) {
								foreach ($cname_list as $cname) {
									if (!empty($cname['data']) && is_ipaddr_configured($cname['data'])) {
										$pfsense_configured = TRUE;
										break 2;
									}
								}
							}
						}
						if (!empty($validate['data']) && is_ipaddr_configured($validate['data'])) {
							$pfsense_configured = TRUE;
							break;
						}
					}
				}
				else {
					// Cannot resolve host
					pfb_logger("{$header} Invalid URL (cannot resolve) [ " . htmlspecialchars($input) . " ]", 6);
					return FALSE;
				}

				$path = pathinfo($data['path'], PATHINFO_DIRNAME);

				// Validate only pfSense URLS (localfile check and Alerts Tab refresh)
				if ($escape) {
					if ($pfsense_configured ||
					    ($data['host'] == '127.0.0.1') ||
					    ($data['host'] == '::1')) {

						// Allow '/usr/local/www'
						if ($path == '/') {
							return TRUE;
						}
					}

					if ($reference == 'alerts refresh') {
						pfb_logger("{$header} Invalid URL (alerts tab) [ " . htmlspecialchars($input) . " ]", 6);
					}
					return FALSE;
				}

				// pfSense URL
				if (($pfsense_configured) ||
				    ($data['host'] == '127.0.0.1') ||
				    ($data['host'] == '::1')) {

					// Allow '/usr/local/www'
					if ($path == '/') {
						return TRUE;
					}
					pfb_logger("{$header} Invalid URL (not allowed) [ " . htmlspecialchars($input) . " ]", 6);
					return FALSE;
				}

				// all other remote URLs no path validation
				return TRUE;
			}

			// Local file path validation
			else {
				$path		= pathinfo($input, PATHINFO_DIRNAME) . '/';
				$allowed_path	= array_flip(array(	'/var/db/pfblockerng/',
									'/var/db/pfblockerng/deny/',
									'/var/db/pfblockerng/permit/',
									'/var/db/pfblockerng/match/',
									'/var/db/pfblockerng/native/',
									'/var/db/pfblockerng/original/',
									'/var/db/pfblockerng/dnsbl/',
									'/var/db/pfblockerng/dnsblorig/',
									'/var/db/pfblockerng/ET/',
									'/var/db/pfblockerng/ut1/',
									'/var/db/pfblockerng/shallalist/',
									'/usr/local/www/',
									'/usr/local/share/GeoIP/',
									'/usr/local/share/GeoIP/cc/' ));
				if (isset($allowed_path[$path])) {
					return TRUE;
				}
			}
			pfb_logger("\n[PFB_FILTER - {$type}] Invalid URL (not allowed2) [ " . htmlspecialchars($input) . " ]", 6);
			return FALSE;
			break;
		case PFB_FILTER_WORD:
			// Validate for 'Any word character (letter, number, underscore)'
			if (!preg_match("/\W/", $input)) {
				$result = htmlspecialchars($input);
			}
			break;
		case PFB_FILTER_WORD_DOT:
			// Validate for '(letter, number, underscore, dash) dot (letter, number, underscore, dash)'
			if (preg_match("/^[a-zA-Z0-9_\-]+\.{1}[a-zA-Z0-9_\-]+$/", $input)) {
				$result = htmlspecialchars($input);
			}
			break;
		case PFB_FILTER_TLD:
			// Validate TLD
			if (preg_match("/^[a-zA-Z0-9_\.\-]+$/", $input)) {
				$result = htmlspecialchars($input);
			}
			break;
		case PFB_FILTER_DOMAIN:
			// Validate domain
			if ((strpos($input, '.') !== FALSE) &&			// Exclude no dots
			    (strpos($input, '..') === FALSE) &&			// Exclude double dot
			    (strlen($input) < 255) &&				// Validate length of domain (Max 255 chars)
			    (pfb_validate_domain_labels($input) !== FALSE) &&	// Validate length of labels (Max of 63 chars)
			    (preg_match("/^[a-zA-Z0-9_\.\-]+$/", $input))) {  	// Exclude any other characters
				$result = TRUE;
			}
			if ($result) {
				$result = htmlspecialchars($input);
			}
			break;
		case PFB_FILTER_HOSTNAME:
			// Validate hostname
			if (is_hostname($input)) {
				$result = htmlspecialchars($input);
			}
			break;
		case PFB_FILTER_IPV4:
			// Validate IPv4
			if (is_ipaddrv4($input)) {
				$result = htmlspecialchars($input);
			}
			break;
		case PFB_FILTER_IP:
			// Validate any IP address v4/v6
			if (is_ipaddr($input)) {
				$result = htmlspecialchars($input);
			}
			break;
		case PFB_FILTER_ALPHA:
			// Validate input is alphabetic only
			if (ctype_alpha($input)) {
				$result = htmlspecialchars($input);
			}
			break;
		case PFB_FILTER_ALNUM:
			// Validate input is alphanumeric only
			if (ctype_alnum($input)) {
				$result = htmlspecialchars($input);
			}
			break;
		case PFB_FILTER_NUM:
			// Validate input is number only
			if (preg_match("/^[0-9]+$/", $input)) {
				$result = htmlspecialchars($input);
			}
			break;
		case PFB_FILTER_CSV:
			// Validate CSV string
			if (preg_match("/^[a-zA-Z0-9,_-]+$/", $input)) {
				$result = htmlspecialchars($input);
			}
			break;
		case PFB_FILTER_CSV_WHOIS:
			// Validate Whoisconvert string
			if (preg_match("/^[a-zA-Z0-9,\._\-]+$/", $input)) {
				$result = htmlspecialchars($input);
			}
			break;
		case PFB_FILTER_CSV_CRON:
			// Validate CSV string (cron hour setting)
			if ($input == '*' || preg_match("/^[0-9,]+$/", $input)) {
				$result = htmlspecialchars($input);
			}
			break;
		case PFB_FILTER_FILE_MIME_COMPARE:
			// Validate mime-type
			// $input [0] path/file, [1] mime-type to be validated against
			if (isset($retval)) {
				unset($retval);
			}
			if (isset($output)) {
				unset($output);
			}
			if (!file_exists($input[0])) {
				pfb_logger("{$header} Invalid Mime-type (file missing): [" .  htmlspecialchars($input[0]) . "|" . htmlspecialchars($input[1]) . "]", 2);
				return FALSE;
			}
			exec("/usr/bin/file -b --mime-type " . escapeshellarg($input[0]) . " 2>&1", $output, $retval);
			if ($retval != 0 || empty($output[0]) || $output[0] !== $input[1]) {
				pfb_logger("{$header} Invalid Mime-type: [" .  htmlspecialchars($input[0]) . "|" . htmlspecialchars($input[1]) . "]", 2);
				return FALSE;
			}
			return TRUE;
			break;
		case PFB_FILTER_FILE_MIME:
			// Validate File Mime-types
			// $input [0] path/file escaped, [1] path/file [2] URL

			if (isset($retval)) {
				unset($retval);
			}
			if (isset($output)) {
				unset($output);
			}
			if (!file_exists($input[1])) {
				pfb_logger("{$header} Downloaded file not found: [" .  htmlspecialchars($input[0]) . "|" . htmlspecialchars($input[1]) . "]", 2);
				return FALSE;
			}
			exec("/usr/bin/file -b --mime-type {$input[0]} 2>&1", $output, $retval);
			if ($retval != 0 || empty($output[0]) || !isset($pfb['mime_types'][$output[0]])) {

				// Exceptions
				$hostname = parse_url($input[2], PHP_URL_HOST);
				if ($output[0] == 'text/x-asm' &&
				    ($hostname == 'easylist-downloads.adblockplus.org' || $hostname == 'easylist.to' )) {
					$output[0] = 'text/plain';
				}
				elseif ($output[0] == 'application/octet-stream' && $hostname == 'ipinfo.io') {
					$output[0] = 'text/plain';
				}
				else {
					pfb_logger("\n[PFB_FILTER - {$type}] Failed or invalid Mime Type: [" .  htmlspecialchars($output[0]) . "|" . htmlspecialchars($retval) . "]", 2);
					unlink_if_exists($input[1]);
					return FALSE;
				}
			}
			$result = htmlspecialchars($output[0]);
			break;
		case PFB_FILTER_FILE_MIME_COMPRESSED:
			// Validate File Mime-types in compressed files
			// $input [0] path/file escaped, [1] path/file [2] URL
			if (isset($retval)) {
				unset($retval);
			}
			if (isset($output)) {
				unset($output);
			}
			if (!file_exists($input[1])) {
				pfb_logger("{$header} Downloaded file not found: [" .  htmlspecialchars($input[0]) . "|" . htmlspecialchars($input[1]) . "]", 2);
				return FALSE;
			}
			exec("/usr/bin/file -bZ --mime-type {$input[0]} 2>&1", $output, $retval);
			if ($retval != 0 || empty($output[0]) || !isset($pfb['mime_types'][$output[0]])) {
				pfb_logger("{$header} Failed or invalid Mime Type Compressed: [" .  htmlspecialchars($output[0]) . "|" . htmlspecialchars($retval) . "]", 2);
				unlink_if_exists($input[1]);
				return FALSE;
			}
			$result = htmlspecialchars($output[0]);
			break;
		case PFB_FILTER_ATYPE:
			// Validate atype entry in category_edit.php
			if (preg_match("/^[a-zA-Z0-9\.|_]+$/", $input)) {
				$result = htmlspecialchars($input);
			}
			break;
		case PFB_FILTER_HEX_COLOR:
			// Alerts Tab - Hex code validation
			if ($input == 'none' || preg_match("/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/", $input)) {
				$result = htmlspecialchars($input);
			}
			break;
		case PFB_FILTER_ON_OFF:
			// Validate on or off
			if ($input == 'on' || $input == '') {
				$result = htmlspecialchars($input);
			} else {
				pfb_logger("{$header} Invalid on/off [ " . htmlspecialchars($input) . " ]", 6);
			}
			break;
		default:
			pfb_logger("{$header} Type invalid [ " . htmlspecialchars($input) . " ]", 6);
			break;
	}

	if ($result && $escape) {
		$result = escapeshellarg($result);
	}

	// Log validation errors
	if (!empty($input) && empty($result) &&
	    !in_array($type, array(PFB_FILTER_URL, PFB_FILTER_FILE_MIME_COMPARE, PFB_FILTER_FILE_MIME, PFB_FILTER_FILE_MIME_COMPRESSED, PFB_FILTER_ON_OFF, PFB_FILTER_NUM))) {

		// Exceptions
		if (in_array($reference, array(	'DNSBL_Download'))) {
			//
		} else {
			pfb_logger("{$header} Failed validation [ " . htmlspecialchars($input) . " ]", 6);
		}
	}

	return $result == FALSE ? $default : $result;
}

function pfb_get_vips(): array {
	$vips = get_specialnet('', [SPECIALNET_VIPS]);
	$vips4 = [];
	$vips6 = [];

	foreach (get_configured_vip_list() as $vip_id => $vip_address) {
		if (!isset($vips[$vip_address])) {
			continue;
		}
		switch (is_ipaddr($vip_address)) {
			case 4:
				$vips4[$vip_id] = $vips[$vip_address];
				break;
			case 6:
				$vips6[$vip_id] = $vips[$vip_address];
				break;
			default:
				continue;
		}
	}

	return [
		'v4' => $vips4,
		'v6' => $vips6
	];
}

function pfb_get_vip_options(int $family, bool $pkg_xml_format = false) {
	$vips = pfb_get_vips();
	if ($family == AF_INET) {
		$selection = &$vips['v4'];
	} elseif ($family == AF_INET6) {
		$selection = &$vips['v6'];
	} else {
		$selection = [];
	}

	if ($pkg_xml_format) {
		$options = [[
			'id' => 'none',
			'descr' => ''
		]];
		foreach ($selection as $vip_id => $vip_address) {
			$options[] = [
				'id' => $vip_id,
				'descr' => $vip_address
			];
		}
	} else {
		$options = array_merge(
			['none' => ''],
			$selection
		);
	}

	return $options;
}

function pfb_validate_vips(string $interface, string|null $vip4 = '', string|null $vip6 = ''): array {
	$enabled4 = !empty($vip4);
	$enabled6 = !empty($vip6);
	if (!$enabled4 && !$enabled6) {
		return [false, gettext('no VIP configured')];
	}

	if ($enabled4 && ($interface != get_configured_vip_interface($vip4))) {
		return [false, sprintf(gettext('IPv4 VIP not on interface %s'), convert_friendly_interface_to_friendly_descr($interface))];
	}
	if ($enabled6 && ($interface != get_configured_vip_interface($vip6))) {
		return [false, sprintf(gettext('IPv6 VIP not on interface %s'), convert_friendly_interface_to_friendly_descr($interface))];
	}

	$vips = pfb_get_vips();
	if ($enabled4) {
		if (!isset($vips['v4'][$vip4])) {
			return [false, gettext('invalid IPv4 VIP')];
		} elseif (count(where_is_ipaddr_configured(get_configured_vip_ipv4($vip4), '' , true, true, ''))) {
			return [false, gettext('IPv4 VIP overlaps with an existing subnet')];
		}
	}
	if ($enabled6) {
		if (!isset($vips['v6'][$vip6])) {
			return [false, gettext('invalid IPv6 VIP')];
		} elseif ($enabled6 && count(where_is_ipaddr_configured(get_configured_vip_ipv6($vip6), '' , true, true, ''))) {
			return [false, gettext('IPv6 VIP overlaps with an existing subnet')];
		}
	}

	return [true, null];
}

// Validate length of labels (Max of 63 chars)
function pfb_validate_domain_labels($input) {

	$labels = explode('.', $input);
	if (!empty($labels) && is_array($labels)) {
		foreach ($labels as $label) {
			if (strlen($label) > 63) {
				return FALSE;
			}
		}
		return TRUE;
	}
	return FALSE;
}


// [ $pfb ] pfBlockerNG global array. This needs to be called to get the updated settings.
function pfb_global() {
	global $g, $pfb;

	// Create folders if not exist.
	foreach ($pfb['folder_array'] as $folder) {
		safe_mkdir("{$folder}", 0755);
	}

	// Reload config.xml to get any recent changes
	config_read_file(false, true);

	// General variables
	$pfb['config']		= config_get_path('installedpackages/pfblockerng/config/0', []);
	$pfb['ipconfig']	= config_get_path('installedpackages/pfblockerngipsettings/config/0', []);
	$pfb['dnsblconfig']	= config_get_path('installedpackages/pfblockerngdnsblsettings/config/0', []);
	$pfb['blconfig']	= config_get_path('installedpackages/pfblockerngblacklist', []);
	$pfb['config_global']	= config_get_path('installedpackages/pfblockerngglobal', []);

	$pfb['enable']		= $pfb['config']['enable_cb'];			// Enable/Disable of pfBlockerNG
	$pfb['keep']		= $pfb['config']['pfb_keep'];			// Keep blocklists on pfBlockerNG Disable
	$pfb['interval']	= $pfb['config']['pfb_interval']	?: '1';	// Hour cycle for scheduler

	// Validate Cron settings
	if (!is_numeric($pfb['interval'])) {
		$pfb['interval'] = '1';
	}
        foreach (array( 'pfb_min'		=> 'min',			// User defined CRON start minute
			'pfb_hour'		=> 'hour',			// Start hour of the scheduler
			'pfb_dailystart'	=> '24hour'			// Start hour of the 'Once a day' schedule
		) as $conf_value => $pfb_value) {

		$pfb_variable = $pfb['config'][$conf_value] ?: '0';
		if (!is_numeric($pfb_variable)) {
			$pfb[$pfb_value] = '0';
		} else {
			$pfb[$pfb_value] = $pfb_variable;
		}
	}

	$pfb['supp']		= $pfb['ipconfig']['suppression'];			// Enable Suppression
	$pfb['cc']		= $pfb['ipconfig']['database_cc'];			// Disable Country database CRON updates
	$pfb['maxmind_locale']	= $pfb['ipconfig']['maxmind_locale']	?: 'en';	// MaxMind Localized Language setting
	$pfb['asn_reporting']	= $pfb['ipconfig']['asn_reporting']	?: 'disabled';	// ASN Reporting
	$pfb['asn_token']	= $pfb['ipconfig']['asn_token']		?: '';		// ASN Token (IPinfo)

	$pfb['maxmind_account']	= pfb_filter($pfb['ipconfig']['maxmind_account'], PFB_FILTER_WORD, 'pfb_global')	?: '';		// Maxmind Account ID
	$pfb['maxmind_key']	= pfb_filter($pfb['ipconfig']['maxmind_key'], PFB_FILTER_WORD, 'pfb_global')		?: '';		// Maxmind License Key

	$pfb['dnsbl']		= $pfb['dnsblconfig']['pfb_dnsbl'];									// Enabled state of DNSBL
	$pfb['dnsbl_vip4'] = get_configured_vip_ipv4($pfb['dnsblconfig']['pfb_dnsvip4']) ?? '';
	$pfb['dnsbl_vip6'] = get_configured_vip_ipv6($pfb['dnsblconfig']['pfb_dnsvip6']) ?? '';
	$pfb['dnsbl_iface']	= isset($pfb['dnsblconfig']['dnsbl_interface']) ? $pfb['dnsblconfig']['dnsbl_interface'] : 'lo0';	// VIP Local Interface setting
	$vips_valid_result = pfb_validate_vips($pfb['dnsbl_iface'], $pfb['dnsblconfig']['pfb_dnsvip4'], $pfb['dnsblconfig']['pfb_dnsvip6']);
	if (!$vips_valid_result[0]) {
		// Force DNSBL to be disabled if VIPs are invalid.
		$pfb['dnsbl'] = '';
		pfb_logger("\nDNSBL disabled: {$vips_valid_result[1]}", 1);
	}

	$pfb['dnsbl_port']	= $pfb['dnsblconfig']['pfb_dnsport'];									// Lighttpd web server http port setting
	$pfb['dnsbl_port_ssl']	= $pfb['dnsblconfig']['pfb_dnsport_ssl'];								// Lighttpd web server https port setting
	$pfb['dnsbl_alexa']	= $pfb['dnsblconfig']['alexa_enable'];									// TOP1M whitelist
	$pfb['dnsbl_alexatype'] = isset($pfb['dnsblconfig']['alexa_type'])	? $pfb['dnsblconfig']['alexa_type'] 	 : 'tranco';	// TOP1M type (Tranco, Alexa or Cisco)
	$pfb['dnsbl_res_cache']	= $pfb['dnsblconfig']['pfb_cache'];									// DNSBL Option to backup/restore Resolver cache
	$pfb['dnsbl_sync']	= $pfb['dnsblconfig']['pfb_dnsbl_sync'];								// Live Updates to Resolver without a Reload
	$pfb['dnsbl_global_log']= isset($pfb['dnsblconfig']['global_log']) 	? $pfb['dnsblconfig']['global_log']	 : '';		// Global Logging/Blocking mode

	$pfb['dnsbl_mode']	= $pfb['dnsblconfig']['dnsbl_mode'];			// DNSBL Mode (Unbound/python mode)
	$pfb['dnsbl_py_reply']	= $pfb['dnsblconfig']['pfb_py_reply'];			// DNSBL Resolver python DNS Reply logging
	$pfb['dnsbl_py_block']	= $pfb['dnsblconfig']['pfb_py_block'];			// DNSBL Resolver python blocking mode
	$pfb['dnsbl_hsts']	= $pfb['dnsblconfig']['pfb_hsts'];			// DNSBL Resolver python block HSTS via Null Block mode
	$pfb['dnsbl_idn']	= $pfb['dnsblconfig']['pfb_idn'];			// DNSBL Resolver python block IDN domains
	$pfb['dnsbl_regex']	= $pfb['dnsblconfig']['pfb_regex'];			// DNSBL Resolver python regex
	$pfb['dnsbl_regex_list']= $pfb['dnsblconfig']['pfb_regex_list'];		// DNSBL Resolver python regex list
	$pfb['dnsbl_cname']	= $pfb['dnsblconfig']['pfb_cname'];			// DNSBL Resolver python CNAME Validation
	$pfb['dnsbl_pytld']	= $pfb['dnsblconfig']['pfb_pytld'];			// DNSBL Resolver python TLD Allow option
	$pfb['dnsbl_py_nolog']	= $pfb['dnsblconfig']['pfb_py_nolog'];			// DNSBL Resolver python - Log events via DNSBL Webserver vs python
	$pfb['dnsbl_noaaaa']	= $pfb['dnsblconfig']['pfb_noaaaa'];			// DNSBL Resolver python no AAAA
	$pfb['dnsbl_noaaaa_list']=$pfb['dnsblconfig']['pfb_noaaaa_list'];		// DNSBL Resolver python no AAAA list

	$pfb['dnsbl_gp']		= $pfb['dnsblconfig']['pfb_gp'];		// DNSBL Resolver python - DNSBL Bypass
	$pfb['dnsbl_gp_bypass_list']	= $pfb['dnsblconfig']['pfb_gp_bypass_list'];	// DNSBL Resolver python - List of Local IPs to bypass DNSBL

	// DNSBL Resolver mode (Unbound/Python)
	$pfb['dnsbl_py_blacklist'] = FALSE;
	if ($pfb['dnsbl_mode'] == 'dnsbl_python' && $pfb['dnsbl_py_block'] == 'on') {
		$pfb['dnsbl_py_blacklist'] = TRUE;
	}

	// SafeSearch
	$pfb['safesearch_enable']	= config_get_path('installedpackages/pfblockerngsafesearch/safesearch_enable', 'Disable');
	$pfb['safesearch_youtube']	= config_get_path('installedpackages/pfblockerngsafesearch/safesearch_youtube', 'Disable');
	$pfb['safesearch_doh']		= config_get_path('installedpackages/pfblockerngsafesearch/safesearch_doh', 'Disable');
	$pfb['safesearch_doh_list']	= explode(',', config_get_path('installedpackages/pfblockerngsafesearch/safesearch_doh_list', ''));

	// DNSBL SafeSearch
	$pfb['dnsbl_safe_search'] = FALSE;
	if ($pfb['safesearch_enable'] !== 'Disable' ||
	    $pfb['safesearch_youtube'] !== 'Disable' ||
	    $pfb['safesearch_doh'] !== 'Disable') {
		$pfb['dnsbl_safe_search'] = TRUE;
	}

	// External DNS Server for TLD drill and CNAME Queries
	$pfb['extdns'] = pfb_filter($pfb['config_global']['pfbextdns'], PFB_FILTER_IPV4, 'pfb_global', '8.8.8.8');

	// Unbound chroot cmd
	$pfb['chroot_cmd'] = "/usr/sbin/chroot -u unbound -g unbound / /usr/local/sbin/unbound-control -c {$g['unbound_chroot_path']}/unbound.conf";

	// Define SQLite3 parameters
	$pfb['sqlite_timeout']	= 100000;

	// Max daily download failure threshold (default to '0' unlimited failures)
	$pfb['skipfeed']	= $pfb['config']['skipfeed'] != '' ? $pfb['config']['skipfeed'] : 0;

	if (config_path_enabled('unbound')) {
		$pfb['unbound_state'] = 'on';
	} else {
		$pfb['unbound_state'] = '';
	}

	// cURL - system proxy server setttings, if configured
	if (!empty(config_get_path('system/proxyurl'))) {
		$pfb['curl_defaults'][CURLOPT_PROXY]			= config_get_path('system/proxyurl');
		if (!empty(config_get_path('system/proxyport'))) {
			$pfb['curl_defaults'][CURLOPT_PROXYPORT]	= config_get_path('system/proxyport');
		}
		if (!empty(config_get_path('system/proxyuser')) && !empty(config_get_path('system/proxypass'))) {
			$pfb['curl_defaults'][CURLOPT_PROXYAUTH]	= 'CURLAUTH_ANY | CURLAUTH_ANYSAFE';
			$pfb['curl_defaults'][CURLOPT_PROXYUSERPWD]	= config_get_path('system/proxyuser') . ':' . config_get_path('system/proxypass');
		}
	}
	else {
		$pfb['curl_defaults'][CURLOPT_TCP_FASTOPEN]		= true;
	}

	// Set pfBlockerNG to disabled on 're-install'
	if (isset($pfb['install']) && $pfb['install']) {
		$pfb['enable'] = $pfb['dnsbl'] = '';
		$pfb['install']	= FALSE;
	}
}
pfb_global();

// Function to get pfBlockerNG package version
function pfb_pkg_ver() {
	$pkg_ver = 'v??';
	foreach (config_get_path('installedpackages/package', []) as $pkg_info_data) {
		if (strpos($pkg_info_data['name'], 'pfBlockerNG') !== FALSE) {
			$pkg_ver = 'v' . $pkg_info_data['version'];
			break;
		}
	}
	return $pkg_ver;
}


// Firewall Filter Service
function pfb_filter_service() {
	$LOG_PREFIX_PKG_PFBLOCKERNG = LOG_PREFIX_PKG_PFBLOCKERNG;

	$rc		= array();
	$rc['file']	= 'pfb_filter.sh';
	$rc['start']	= <<<EOF

	# Check if pfBlockerNG is enabled
	pfbcheck="\$(/usr/local/sbin/read_xml_tag.sh string installedpackages/pfblockerng/config/enable_cb)"
	if [ "\${pfbcheck}" != 'on' ]; then
		exit
	fi

	# Ensure all processes are stopped
	rc_stop

	# clog is not required for pfSense 2.5 and above
	filter_type='tail_pfb'
	if [ -e "/usr/local/sbin/clog_pfb" ]; then
		filter_type='clog_pfb'
	fi

	# Compare php/php_pfb versions
	php_path='/usr/local/bin'

	phpver="\$(/usr/bin/stat \${php_path}/php | cut -d '/' -f1)"
	if [ -e "\${php_path}/php_pfb" ]; then
		phppfbver="\$(/usr/bin/stat \${php_path}/php_pfb | cut -d '/' -f1)"
	else
		phppfbver=''
	fi

	# Create new hard link for php_pfb on version mismatch
	if [ "\${phpver}" != "\${phppfbver}" ]; then
		if [ -e "\${php_path}/php_pfb" ]; then
			tmpfile="\$(/usr/bin/mktemp /tmp/pfb_php.XXXXXX)"
			/bin/mv "\${php_path}/php_pfb" "\${tmpfile}"
		fi

		/bin/ln "\${php_path}/php" "\${php_path}/php_pfb"
	fi

	# Start pfBlockerNG Firewall filter Daemon
	if [ -e '/var/log/filter.log' ]; then
		/usr/bin/logger -p daemon.info -t "\${filter_type}" "INFO [{$LOG_PREFIX_PKG_PFBLOCKERNG}] Firewall Filter Service started"

		if [ "\${filter_type}" == 'clog_pfb' ]; then
			/usr/local/sbin/clog_pfb -f /var/log/filter.log | /usr/local/bin/php_pfb -f /usr/local/pkg/pfblockerng/pfblockerng.inc filterlog &
		else
			/usr/bin/tail_pfb -n0 -F /var/log/filter.log | /usr/local/bin/php_pfb -f /usr/local/pkg/pfblockerng/pfblockerng.inc filterlog &
		fi
	fi

EOF;
	$rc['stop']	= <<<EOF

	# clog is not required for pfSense 2.5 and above
	filter_type='tail_pfb'
	if [ -e "/usr/local/sbin/clog_pfb" ]; then
		filter_type='clog_pfb'
	fi

	# Terminate pfBlockerNG Firewall filter Daemon (clog) and filter Daemon, if found
	/usr/bin/logger -p daemon.info -t "\${filter_type}" "INFO [{$LOG_PREFIX_PKG_PFBLOCKERNG}] Firewall Filter Service stopped"
	/usr/bin/logger -p daemon.info -t php_pfb "INFO [{$LOG_PREFIX_PKG_PFBLOCKERNG}] filterlog daemon stopped"
	pidnum="\$(/bin/ps -wax | /usr/bin/grep '[c]log_pfb -f /var/log/filter.log\|[t]ail_pfb -n0 -F /var/log/filter.log\|[p]fblockerng.inc filterlog' | /usr/bin/awk '{print \$1}')"
	if [ ! -z "\${pidnum}" ]; then
		for i in \${pidnum}; do
			/bin/kill -9 "\${i}"
		done
	fi

EOF;
	write_rcfile($rc);
}


// DNSBL Service
function pfb_dnsbl_service() {
	$LOG_PREFIX_PKG_PFBLOCKERNG = LOG_PREFIX_PKG_PFBLOCKERNG;

	$rc		= array();
	$rc['file']	= 'pfb_dnsbl.sh';
	$rc['start']	= <<<EOF

	# Check if DNSBL is enabled
	dnsblcheck="\$(/usr/local/sbin/read_xml_tag.sh string installedpackages/pfblockerngdnsblsettings/config/pfb_dnsbl)"
	if [ "\${dnsblcheck}" != 'on' ]; then
		exit
	fi

	# Ensure all processes are stopped
	rc_stop

	# Start DNSBL Lighttpd webserver (Unbound/Python mode) and DNSBL HTTPS Daemon (Unbound mode only)
	if [ -e '/var/unbound/pfb_dnsbl_lighty.conf' ]; then
		/usr/bin/logger -p daemon.info -t lighttpd_pfb "INFO [{$LOG_PREFIX_PKG_PFBLOCKERNG}] DNSBL Webserver started"
		/usr/local/sbin/lighttpd_pfb -f /var/unbound/pfb_dnsbl_lighty.conf
	fi

	# Check if Unbound mode is enabled
	unbound_mode="\$(/usr/local/sbin/read_xml_tag.sh string installedpackages/pfblockerngdnsblsettings/config/dnsbl_mode)"

	# Start DNSBL Resolver queries Daemon
	if [ "\${unbound_mode}" == 'dnsbl_unbound' ]; then
		/usr/local/bin/php -f /usr/local/pkg/pfblockerng/pfblockerng.inc queries &
	elif [ ! -e '/var/unbound/pfb_unbound.py' ]; then
		/usr/bin/logger -p daemon.err -t php_pfb "ERROR [{$LOG_PREFIX_PKG_PFBLOCKERNG}] DNSBL missing python script - forced reload required"
	fi

EOF;
	$rc['stop']	= <<<EOF

	# Terminate DNSBL Lighttpd webserver, if found
	/usr/bin/logger -p daemon.info -t lighttpd_pfb "INFO [{$LOG_PREFIX_PKG_PFBLOCKERNG}] DNSBL Webserver stopped"
	pidnum="\$(/bin/pgrep lighttpd_pfb)"
	if [ ! -z "\${pidnum}" ]; then
		/usr/bin/killall lighttpd_pfb
	fi

	# Terminate DNSBL queries Daemon, if found
	pidnum="\$(/bin/ps -wax | /usr/bin/grep '[p]fblockerng.inc queries' | /usr/bin/awk '{print \$1}')"
	if [ ! -z "\${pidnum}" ]; then
		for i in \${pidnum}; do
			/bin/kill -9 "\${i}"
		done
	fi

EOF;
	write_rcfile($rc);
}


// Create Firewall filter service
if (!file_exists('/usr/local/etc/rc.d/pfb_filter.sh')) {
	pfb_filter_service();
}

// Create DNSBL service
if (!file_exists('/usr/local/etc/rc.d/pfb_dnsbl.sh')) {
	pfb_dnsbl_service();
}

// clog is not required for pfSense 2.5 and above
if (substr(trim(file_get_contents('/etc/version')), 0, 3) > '2.5' && file_exists('/usr/local/sbin/clog_pfb')) {
	unlink_if_exists('/usr/local/sbin/clog_pfb');
	unlink_if_exists('/usr/bin/tail_pfb');
	link('/usr/bin/tail', '/usr/bin/tail_pfb');
	restart_service('pfb_filter');
}

// Commandline arguments for daemons
if (isset($argv[1])) {

	// DNSBL Lighttpd HTTPS daemon (Collects HTTPS events from the Lighttpd dnsbl_error.log)
	if ($argv[1] == 'dnsbl') {
			ignore_user_abort(TRUE);
			set_time_limit(0);
			pfb_daemon_dnsbl();
			exit;
	}

	// DNSBL Lighttpd HTTP daemon (Collects HTTP events from the index.php script)
	elseif ($argv[1] == 'index') {
			ignore_user_abort(TRUE);
			set_time_limit(0);
			pfb_daemon_dnsbl_index();
			exit;
	}

	// DNSBL daemon to monitor Resolver queries and manage SQLite3 database
	elseif ($argv[1] == 'queries') {
			ignore_user_abort(TRUE);
			set_time_limit(0);
			pfb_daemon_queries();
			exit;
	}

	// IP filter daemon to convert filter.log to ip_block|ip_permit|ip_match log format
	elseif ($argv[1] == 'filterlog') {
			ignore_user_abort(TRUE);
			set_time_limit(0);
			if (!file_exists('/var/log/filter.log')) {
				logger(LOG_INFO, localize_text('pfSense Firewall log missing'), LOG_PREFIX_PKG_PFBLOCKERNG);
				exit;
			}
			pfb_daemon_filterlog();
			exit;
	}
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
function pfbng_text_area_decode($text, $mode=FALSE, $type=TRUE, $idn=FALSE) {

	if ($mode) {
		$custom = array();
	}

	$customlist = explode("\r\n", base64_decode($text));
	if (!empty($customlist)) {
		foreach ($customlist as $line) {
			if (substr(trim($line), 0, 1) != '#' && !empty($line)) {
				if ($idn && !ctype_print($line)) {
					$line_old = $line;
					// Convert encodings to UTF-8
					$line = mb_convert_encoding($line, 'UTF-8',
						mb_detect_encoding($line, 'UTF-8, ASCII, ISO-8859-1'));
					if (strpos($line, '#') !== FALSE) {
						$tmpline = preg_split('/(?=#)/', $line);
						if (substr($tmpline[0], 0, 1) == '.') {
							// idn_to_ascii() returns empty string if it starts with '.' 
							$tmpline[0] = idn_to_ascii(ltrim($tmpline[0], '.'));
							if (!empty($tmpline[0])) {
								$tmpline[0] = '.' . $tmpline[0];
							}
						} elseif (is_string($tmpline[0]) && (strlen($tmpline[0]) > 0)) {
							$tmpline[0] = idn_to_ascii($tmpline[0]);
						}
						if (empty($tmpline[0])) {
							$log = "\nError converting IDN line '{$line_old}'\n";
							pfb_logger($log, 2);
							continue;
						}
						$line = implode(' ', $tmpline);
					} else {
						if (substr($line, 0, 1) == '.') {
							$line = idn_to_ascii(ltrim($line, '.'));
							if (!empty($line)) {
								$line = '.' . $line;
							}
						} elseif (is_string($line) && (strlen($line) > 0)) {
							$line = idn_to_ascii($line);
						}
						if (empty($line)) {
							$log = "\nError converting IDN line '{$line_old}'\n";
							pfb_logger($log, 2);
							continue;
						}
					}
				}
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
	global $g, $pfb;
	pfb_global();

	$chroot_folder = '/var/unbound';

	foreach (array(	'log', 'errlog', 'extraslog', 'ip_blocklog', 'ip_permitlog', 'ip_matchlog',
			'dnslog', 'dnsbl_parse_err', 'dnsreplylog', 'unilog') as $logtype) {

		// Max lines in Log file
		$logmax = pfb_filter($pfb['config']['log_max_' . $logtype], PFB_FILTER_NUM, 'pfb_log_mgmt', 20000);

		if ($logmax != 'nolimit' && file_exists($pfb[$logtype])) {
			if ($logtype == 'dnslog' || $logtype == 'dnsreplylog' || $logtype == 'unilog') {

				// Set DNSBL python logfile permissions using chroot folder
				if (is_dir("{$chroot_folder}/var/log/pfblockerng")) {
					$final_log_file = "{$chroot_folder}{$pfb[$logtype]}";
					$temp = tempnam("{$chroot_folder}/var/log/pfblockerng", 'pfb_log_');
				} else {
					$final_log_file = $pfb[$logtype];
					$temp = tempnam("{$g['tmp_path']}/", 'pfb_log_');
				}

				if (file_exists($final_log_file)) {
					exec("/usr/bin/tail -n " . escapeshellarg($logmax) . " " . escapeshellarg($final_log_file) . " > " . escapeshellarg($temp));
					@chown($temp, 'unbound');
					@chgrp($temp, 'unbound');
					exec("/bin/mv -f " . escapeshellarg($temp) . " " . escapeshellarg($final_log_file));
				}
			}
			else {
				$temp = tempnam("{$g['tmp_path']}/", 'pfb_log_');
				exec("/usr/bin/tail -n " . escapeshellarg($logmax) . " {$pfb[$logtype]} > " . escapeshellarg($temp));
				exec("/bin/mv -f " . escapeshellarg($temp) . " {$pfb[$logtype]}");
			}
			unlink_if_exists($temp);
		}
	}
}


// Record log messsages to pfBlockerNG log file and/or error log file.
function pfb_logger($log, $logtype) {
	global $g, $pfb;

	$now = date('m/j/y H:i:s', time());

	// Only log timestamp if new
	if (strpos($log, 'NOW') !== FALSE) {
		$elog = str_replace('NOW', $now, "{$log}");	// Always report timestamp to errorlog
		if ($now == $pfb['pnow']) {
			$log = str_replace(' [ NOW ]', '', "{$log}");
		} else {
			$log = str_replace('NOW', $now, "{$log}");
		}
		$pfb['pnow'] = "{$now}";
	}
	else {
		$elog = "{$log} [ {$now} ]";
	} 

	switch ($logtype) {

		// Print to pfBlockerNG log
		case 1:
			@file_put_contents("{$pfb['log']}", "{$log}", FILE_APPEND);
			break;

		// Print to pfBlockerNG log and Error log
		case 2:
			@file_put_contents("{$pfb['log']}", "{$log}", FILE_APPEND);
			@file_put_contents("{$pfb['errlog']}", "{$elog}", FILE_APPEND);
			break;

		// Print to Extras log
		case 3:
			@file_put_contents("{$pfb['extraslog']}", "{$log}", FILE_APPEND);
			break;

		// Print to screen and Extras log
		case 4:
			if (!$g['pfblockerng_install'] && !$pfb['extras_update']) {
				print "{$log}";
			}
			@file_put_contents("{$pfb['extraslog']}", "{$log}", FILE_APPEND);
			break;

		// Print to debugger
		case 5:
			@file_put_contents("/tmp/pfb_debug", "{$now} | {$elog}", FILE_APPEND);
			break;

		// Print to Error log
		case 6:
			@file_put_contents("{$pfb['errlog']}", "{$elog}", FILE_APPEND);
			break;
		default:
			break;
	}
}


// Record failed IP/DNSBL Feed parse errors
function pfb_parsed_fail($header, $line='', $oline, $logfile) {

	$line   = $line ?: 'null';
	$now	= date('m/j/y H:i:s', time());

	$log	= "{$now},{$header},{$line},{$oline}";
	@file_put_contents("{$logfile}", "{$log}", FILE_APPEND);
}


// Determine 'list' details
function pfb_determine_list_detail($list='', $header='', $confconfig='', $key='') {
	global $pfb, $pfbarr;
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
	if ($tabtype > 27) {
		$pfbarr['logtab'] = '';
	} elseif ($tabtype > 19) {
		$pfbarr['logtab'] = "\t";
	} elseif ($tabtype > 11) {
		$pfbarr['logtab'] = "\t\t";
	} elseif ($tabtype < 4) {
		$pfbarr['logtab'] = "\t\t\t\t";
	} else {
		$pfbarr['logtab'] = "\t\t\t";
	}

	// Configure autoports/protocol and auto destination if required.
	if (!empty($confconfig) && is_array(config_get_path("installedpackages/{$confconfig}/config/{$key}"))) {

		$conf_config	= config_get_path("installedpackages/{$confconfig}/config/{$key}");
		$autotype	= array( 'autoports' => 'aliasports', 'autoaddr' => 'aliasaddr');

		foreach (array('_out', '_in') as $dir) {

			$pfbarr['aproto' . $dir]	= $conf_config['autoproto' . $dir];
			$pfbarr['anot' . $dir]		= $conf_config['autonot' . $dir];
			$pfbarr['aaddrnot' . $dir]	= $conf_config['autoaddrnot' . $dir];
			$pfbarr['agateway' . $dir]	= $conf_config['agateway' . $dir];

			foreach ($autotype as $akey => $atype) {
				if ($conf_config[$akey . $dir] == 'on') {
					foreach (config_get_path('aliases/alias', []) as $palias) {
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
function pfblockerng_cron_exists($pfb_cmd, $pfb_min, $pfb_hour, $pfb_mday, $pfb_wday) {
	foreach (config_get_path('cron/item', []) as $item) {
		if (strpos($item['command'], $pfb_cmd) !== FALSE) {
			if ($item['command'] != $pfb_cmd) {
				return FALSE;
			}
			if ($item['minute'] != $pfb_min) {
				return FALSE;
			}
			if ($item['mday'] != $pfb_mday) {
				return FALSE;
			}
			if ($item['wday'] != $pfb_wday) {
				return FALSE;
			}
			if ($pfb_hour == 'random' && $item['hour'] != '') {
				// MaxMind/Blacklist hour is randomized. Skip comparison.
				return TRUE;
			}
			if ($item['hour'] != $pfb_hour) {
				return FALSE;
			}
			return TRUE;
		}
	}
	return FALSE;
}


// Calculate the cron task base hour setting
function pfb_cron_base_hour($freq) {
	global $pfb;

	switch($freq) {
		case 'Disabled':
		case 1:
		case '01hour':
			$j = 23; $k = 1;
			break;
		case 2:
		case '02hours':
			$j = 11; $k = 2;
			break;
		case 3:
		case '03hours':
			$j = 7; $k = 3;
			break;
		case 4:
		case '04hours':
			$j = 5; $k = 4;
			break;
		case 6:
		case '06hours':
			$j = 3; $k = 6;
			break;
		case 8:
		case '08hours':
			$j = 2; $k = 8;
			break;
		case 12:
		case '12hours':
			$j = 1; $k = 12;
			break;
		case 24:
			return array($pfb['24hour']);
			break;
		default:
			$pfb['interval'] = 1;
			return [];
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
	$gateway = array();
	$gateway['default'] = 'default';

	foreach (config_get_path('gateways/gateway_item', []) as $item) {
		$gateway[$item['name']] = $item['name'];
	}
	foreach (config_get_path('gateways/gateway_group', []) as $item) {
		$gateway[$item['name']] = $item['name'];
	}

	return $gateway;
}


// Collect all Interfaces for General Tab and DNSBL Firewall Permit Rule
function pfb_build_if_list($show_wan=FALSE, $show_groups=FALSE) {
	$pfb_interfaces = array();

	foreach (get_configured_interface_with_descr() as $ifent => $ifdesc) {
		if ($show_wan || $ifent != 'wan') {
			$pfb_interfaces[$ifent] = $ifdesc;
		}
	}

	if ($show_groups) {
		foreach (config_get_path('ifgroups/ifgroupentry', []) as $ifgen) {
			$pfb_interfaces[$ifgen['ifname']] = $ifgen['ifname'];
		}
	}

	if (ipsec_enabled()) {
		$pfb_interfaces['enc0'] = 'IPsec';
	}

	if (config_get_path('openvpn/openvpn-server') || config_get_path('openvpn/openvpn-client')) {
		$pfb_interfaces['openvpn'] = 'OpenVPN';
	}

	if (config_get_path('l2tp/mode') == 'server') {
		$pfb_interfaces['l2tp'] = 'L2TP VPN';
	}

	if (function_exists('is_wg_enabled') && is_wg_enabled()) {
		$pfb_interfaces['wireguard'] = 'WireGuard';
	}

	return $pfb_interfaces;
}


// Create suppression file from suppression list
function pfb_create_suppression_file() {
	global $pfb;

	$v4suppression = pfbng_text_area_decode($pfb['ipconfig']['v4suppression'], FALSE, TRUE);
	if (!empty($v4suppression)) {
		@file_put_contents("{$pfb['supptxt']}", $v4suppression, LOCK_EX);
	} else {
		unlink_if_exists("{$pfb['supptxt']}");
	}
}


// Function to update DNSBL aliases and widget stats
function dnsbl_alias_update($mode, $alias, $pfbfolder, $lists_dnsbl_current, $alias_cnt) {
	global $pfb;

	if ($mode == 'update') {

		// Create master alias file
		if ($lists_dnsbl_current != '') {
			$pfb_output = @fopen("{$pfb['dnsalias']}/{$alias}", 'w');
			foreach ($lists_dnsbl_current as $clist) {
				if (($handle = @fopen("{$pfbfolder}/{$clist}.txt", 'r')) !== FALSE) {
					while (($line = @fgets($handle)) !== FALSE) {
						@fwrite($pfb_output, $line);
					}
				}
				@fclose($handle);
			}
			@fclose($pfb_output);
		}

		// Update DNSBL alias statistics
		$dns_now = date('M j H:i:s', time());
		$pfbfound = FALSE;

		if (!empty($pfb['dnsbl_info_stats'])) {
			foreach ($pfb['dnsbl_info_stats'] as $key => $line) {

				// Update existing alias stats
				if ($line['groupname'] == "{$alias}") {
					$pfbfound = TRUE;
					$pfb['dnsbl_info_stats'][$key]['timestamp']	= "{$dns_now}";
					$pfb['dnsbl_info_stats'][$key]['entries']	= "{$alias_cnt}";
					break;
				}
			}
		}

		if (!$pfbfound) {
			$pfb['dnsbl_info_stats'][] = array ( 'groupname' => $alias, 'timestamp' => $dns_now, 'entries' => $alias_cnt, 'counter' => 0);
		}
	}
	elseif ($mode == 'disabled') {
		// Record disabled alias statistics
		$pfbfound = FALSE;
		if (!empty($pfb['dnsbl_info_stats'])) {
			foreach ($pfb['dnsbl_info_stats'] as $line) {
				if ($line['groupname'] == "{$alias}") {
					$pfbfound = TRUE;
					break;
				}
			}
		}

		if (!$pfbfound) {
			$dns_now = date('M j H:i:s', time());
			$pfb['dnsbl_info_stats'][] = array ('groupname' => $alias, 'timestamp' => $dns_now, 'entries' => 'disabled', 'counter' => 0);
		}
	}
}


// Function to save DNSBL Group statistics
function dnsbl_save_stats() {
	global $pfb;

	// Save group statistics to SQLite3 database (Remove any feeds that are not referenced)
	$db_update = $db_delete = '';
	pfb_logger("\nSaving DNSBL statistics...", 1);

	// Collect existing SQL group names
	$sql_groupnames = array();
	$db_handle = pfb_open_sqlite(1, 'Collect Group');
	if ($db_handle) {
		$result = $db_handle->query("SELECT * FROM dnsbl;");
		if ($result) {
			while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
				$sql_groupnames[$res['groupname']] = '';
			}
		}
	}
	pfb_close_sqlite($db_handle);

	// Compare SQL database Group names to latest Group names
	if (!empty($pfb['dnsbl_info_stats'])) {

		$db_handle = pfb_open_sqlite(1, 'Save DNSBL stats');
		if ($db_handle) {

			foreach ($pfb['dnsbl_info_stats'] as $group) {

				// Keep row
				$pfb_delete = FALSE;
				if (in_array($group['groupname'], $pfb['alias_dnsbl_all'])) {

					// Update existing row
					if (isset($sql_groupnames[$group['groupname']])) {
						$db_update 	= "UPDATE dnsbl SET timestamp = :timestamp, entries = :entries"
								. " WHERE groupname = :groupname;\n";
					}

					// Add new row
					else {
						$db_update	= "INSERT INTO dnsbl (groupname, timestamp, entries, counter)"
								. " VALUES (:groupname, :timestamp, :entries, 0);\n";
					}
				}

				// Remove row
				else {
					$db_update	= "DELETE FROM dnsbl WHERE groupname = :groupname;\n";
					$pfb_delete	= TRUE;
				}

				if (is_numeric($group['entries'])) {
					$group['groupname'] = pfb_filter($group['groupname'], PFB_FILTER_HTML, 'dnsbl_save_stats');

					$stmt = $db_handle->prepare($db_update);
					if ($stmt) {
						$stmt->bindValue(':groupname', $group['groupname'], SQLITE3_TEXT);
						if (!$pfb_delete) {
							$group['timestamp'] = pfb_filter($group['timestamp'], PFB_FILTER_HTML, 'dnsbl_save_stats');

							$stmt->bindValue(':timestamp', $group['timestamp'], SQLITE3_TEXT);
							$stmt->bindValue(':entries', $group['entries'], SQLITE3_TEXT);
						}
						$stmt->execute();
					}
				}
			}
		}
		pfb_close_sqlite($db_handle);
	}
	else {
		$db_delete = 'DROP TABLE dnsbl;';
		$db_handle = pfb_open_sqlite(1, 'Delete table');
		if ($db_handle) {
			$db_handle->exec("BEGIN TRANSACTION;"
				. "{$db_delete}"
				. "END TRANSACTION;");
		}
		pfb_close_sqlite($db_handle);
	}
	pfb_logger(" completed [ NOW ]", 1);
}


// Function to create DNSBL Lighttpd configuration file
function pfb_create_lighttpd() {
	global $pfb;

	$lighttpd_bind = $pfb['dnsbl_iface'] != 'lo0' ? "127.0.0.1" : "{$pfb['dnsbl_vip4']}";
	$lighttpd_port = $pfb['dnsbl_iface'] != 'lo0' ? "{$pfb['dnsbl_port']}" : '80';

	$pfb_conf = '';
	$pfb_conf = <<<EOF
#
#pfBlockerNG DNSBL Lighttpd configuration file
#
server.tag			= "pfBlockerNG DNSBL"
server.bind			= "{$lighttpd_bind}"
server.port			= "{$lighttpd_port}"
server.event-handler		= "freebsd-kqueue"
server.network-backend		= "freebsd-sendfile"
server.dir-listing		= "disable"
server.document-root		= "/usr/local/www/pfblockerng/www/"
server.max-request-size		= "1"
server.pid-file			= "/var/run/dnsbl.pid"

EOF;

if (!$pfb['dnsbl_py_blacklist'] || $pfb['dnsbl_py_nolog'] == 'on') {
	$pfb_conf .= <<<EOF
server.errorlog			= "|/usr/local/bin/php -f /usr/local/pkg/pfblockerng/pfblockerng.inc dnsbl"

EOF;
}

	if (file_exists('/usr/local/lib/lighttpd/mod_openssl.so')) {
		if (!$pfb['dnsbl_py_blacklist'] || $pfb['dnsbl_py_nolog'] == 'on') {
			$pfb_conf .= 'server.modules			= ( "mod_access", "mod_auth", "mod_accesslog", "mod_fastcgi", "mod_rewrite", "mod_openssl" )';
		} else {
			$pfb_conf .= 'server.modules			= ( "mod_auth", "mod_fastcgi", "mod_rewrite", "mod_openssl" )';
		}
	} else {
		if (!$pfb['dnsbl_py_blacklist'] || $pfb['dnsbl_py_nolog'] == 'on') {
			$pfb_conf .= 'server.modules			= ( "mod_access", "mod_auth", "mod_accesslog", "mod_fastcgi", "mod_rewrite" )';
		} else {
			$pfb_conf .= 'server.modules			= ( "mod_auth", "mod_fastcgi", "mod_rewrite" )';
		}
	}

	$pfb_conf .= <<<EOF

index-file.names		= ( "index.php" )
mimetype.assign			= ( ".html" => "text/html", ".gif" => "image/gif" )
url.access-deny			= ( "~", ".inc" )
fastcgi.server			= ( ".php" => ( "localhost" => ( "socket" => "/var/run/php-fpm.socket", "broken-scriptfilename" => "enable" ) ) )

EOF;
	if (!$pfb['dnsbl_py_blacklist'] || $pfb['dnsbl_py_nolog'] == 'on') {

	$pfb_conf .= <<<EOF
debug.log-condition-handling	= "enable"
accesslog.use-syslog		= "disable"
accesslog.format		= "INDEX!%r!%V!%h!%{Referer}i * %r * %{User-Agent}i"
accesslog.filename		= "|/usr/local/bin/php -f /usr/local/pkg/pfblockerng/pfblockerng.inc index"

EOF;
}
	// Lighttpd v1.4.58+ conditional error log with 'ssl.verifyclient.activate' to collect the domain name
	$lighty_ver = exec('/usr/local/sbin/lighttpd -v 2>&1');
	if ((!$pfb['dnsbl_py_blacklist'] || $pfb['dnsbl_py_nolog'] == 'on') &&
  	    (strpos($lighty_ver, 'lighttpd/1.4.58') !== FALSE ||
	    strpos($lighty_ver, 'lighttpd/1.4.59') !== FALSE ||
	    strpos($lighty_ver, 'lighttpd/1.4.60') !== FALSE ||
	    strpos($lighty_ver, 'lighttpd/1.4.61') !== FALSE ||
	    strpos($lighty_ver, 'lighttpd/1.4.67') !== FALSE ||
	    strpos($lighty_ver, 'lighttpd/1.5') !== FALSE)) {

		$pfb_conf .= <<<EOF
ssl.verifyclient.activate	= "enable"

EOF;
	}

	$pfb_conf .= <<<EOF

\$HTTP["scheme"] == "http" {
	url.rewrite-once = ( ".*" => "/index.php" )
}

\$HTTP["remoteip"] =~ ".*" {

EOF;

	if ($pfb['dnsbl_iface'] != 'lo0') {
		$pfb_conf .= <<<EOF

	\$SERVER["socket"] == "127.0.0.1:{$pfb['dnsbl_port_ssl']}" {
		ssl.engine			= "enable"
		ssl.pemfile			= "/var/unbound/dnsbl_cert.pem"
		ssl.dh-file			= "/etc/dh-parameters.4096"
		ssl.ec-curve			= "secp384r1"
		ssl.honor-cipher-order		= "enable"
		ssl.openssl.ssl-conf-cmd	= ("MinProtocol"	=> "TLSv1.2",
					 	   "Options"		=> "-ServerPreference",
						   "CipherString"	=> "EECDH+AESGCM:AES256+EECDH:CHACHA20:!SHA1:!SHA256:!SHA384")
	}

	\$SERVER["socket"] == "{$pfb['dnsbl_vip4']}:80" {
		#
	}

EOF;
	}
	$pfb_conf .= <<<EOF

	\$SERVER["socket"] == "{$pfb['dnsbl_vip4']}:443" {
		ssl.engine			= "enable"
		ssl.pemfile			= "/var/unbound/dnsbl_cert.pem"
		ssl.dh-file			= "/etc/dh-parameters.4096"
		ssl.ec-curve			= "secp384r1"
		ssl.honor-cipher-order		= "enable"
		ssl.openssl.ssl-conf-cmd	= ("MinProtocol"	=> "TLSv1.2",
                                                   "Options"		=> "-ServerPreference",
                                                   "CipherString"	=> "EECDH+AESGCM:AES256+EECDH:CHACHA20:!SHA1:!SHA256:!SHA384")
	}

EOF;
	if (!empty($pfb['dnsbl_vip6'])) {
		if ($pfb['dnsbl_iface'] != 'lo0') {
			$pfb_conf .= <<<EOF

	\$SERVER["socket"] == "[::1]:{$pfb['dnsbl_port']}" {
		#
	}

	\$SERVER["socket"] == "[::1]:{$pfb['dnsbl_port_ssl']}" {
		ssl.engine			= "enable"
		ssl.pemfile			= "/var/unbound/dnsbl_cert.pem"
		ssl.dh-file			= "/etc/dh-parameters.4096"
		ssl.ec-curve			= "secp384r1"
		ssl.honor-cipher-order 		= "enable"
		ssl.openssl.ssl-conf-cmd	= ("MinProtocol"	=> "TLSv1.2",
						   "Options"		=> "-ServerPreference",
						   "CipherString"	=> "EECDH+AESGCM:AES256+EECDH:CHACHA20:!SHA1:!SHA256:!SHA384")
	}

EOF;
		}
		$pfb_conf .= <<<EOF

	\$SERVER["socket"] == "[{$pfb['dnsbl_vip6']}]:80" {
		#
	}

	\$SERVER["socket"] == "[{$pfb['dnsbl_vip6']}]:443" {
		ssl.engine			= "enable"
		ssl.pemfile			= "/var/unbound/dnsbl_cert.pem"
		ssl.dh-file			= "/etc/dh-parameters.4096"
		ssl.ec-curve			= "secp384r1"
		ssl.honor-cipher-order		= "enable"
		ssl.openssl.ssl-conf-cmd	= ("MinProtocol"	=> "TLSv1.2",
						   "Options"		=> "-ServerPreference",
						   "CipherString"	=> "EECDH+AESGCM:AES256+EECDH:CHACHA20:!SHA1:!SHA256:!SHA384")
	}

EOF;
	}
	$pfb_conf .= <<<EOF

	\$HTTP["host"] =~ ".*" {
		url.rewrite-once = ( ".*" => "/index.php" )
	}
}

EOF;
	return $pfb_conf;
}


// Function to create DNSBL SSL certificate
function pfb_create_dnsbl_cert() {
	global $pfb, $cert_strict_values;

	$cert		= array();
	$cert['refid']	= uniqid();
	$cert['descr']	= sprintf(gettext("pfBlockerNG DNSBL (%s)"), $cert['refid']);
	$cert_hostname	= config_get_path('system/hostname') . "-pfBNG-DNSBL-{$cert['refid']}";

	$dn = array(
		'organizationName'	=> "pfBlockerNG DNSBL Self-Signed Certificate",
		'commonName'		=> $cert_hostname,
		'subjectAltName'	=> "DNS:{$cert_hostname}");

	$old_err_level = error_reporting(0); /* otherwise openssl_ functions throw warnings directly to a page screwing menu tab */
	if (!cert_create($cert, null, 2048, $cert_strict_values['max_server_cert_lifetime'] ?: 398, $dn, 'self-signed', 'sha256')) {
		while ($ssl_err = openssl_error_string()) {
			logger(LOG_ERR, localize_text("Could not create DNSBL Certificate: openssl library returns: %s", $ssl_err), LOG_PREFIX_PKG_PFBLOCKERNG);
		}
		error_reporting($old_err_level);
		return null;
	}
	error_reporting($old_err_level);
	$privatekey	= base64_decode($cert['prv']);
	$publickey	= base64_decode($cert['crt']);
 
	@file_put_contents("{$pfb['dnsbl_cert']}", "{$privatekey}{$publickey}", LOCK_EX);
}


// Create DNSBL VIP and NAT rules, lighttpd conf and services
function pfb_create_dnsbl($mode) {
	global $pfb;
	pfb_global();

	$new_nat = $pfb_ex_nat = $dnsbl_ex_nat = array();
	$pfbupdate = FALSE;

	if ((!empty($pfb['dnsbl_port']) && !empty($pfb['dnsbl_port_ssl']) && ($pfb['dnsbl'] == 'on') && $mode == 'enabled') || $mode == 'disabled') {

		// DNSBL NAT rules generation
		$pfbfound = FALSE;
		// Collect existing pfSense NAT rules
		foreach (config_get_path('nat/rule', []) as $ex_nat) {
			if (strpos($ex_nat['descr'], 'pfB DNSBL') !== FALSE) {
				// Collect DNSBL NAT rules
				$dnsbl_ex_nat[] = $ex_nat;
				$pfbfound = TRUE;
			} else {
				// Collect all 'other' NAT rules
				$pfb_ex_nat[] = $ex_nat;
			}
		}

		if ($mode == 'enabled') {

			// Generate new DNSBL NAT per DNSBL listening ports except for 'localhost' interface setting.
			$dnsbl_new_nat = array();
			if ($pfb['dnsbl_iface'] != 'lo0') {
				$selected_ports = array("{$pfb['dnsbl_port']}" => '80', "{$pfb['dnsbl_port_ssl']}" => '443');
				foreach ($selected_ports as $port => $lport) {

					$dnsbl_new_nat[] =	array ( 'source'		=> array('any'  => ''),
									'destination'		=> array('address' => "{$pfb['dnsbl_vip4']}", 'port' => "{$lport}"),
									'protocol'		=> 'tcp',
									'target'		=> '127.0.0.1',
									'local-port'		=> "{$port}",
									'interface'		=> "{$pfb['dnsbl_iface']}",
									'descr'			=> 'pfB DNSBL - DO NOT EDIT',
									'associated-rule-id'	=> 'pass',
									'natreflection'		=> 'purenat'
									);

					// Add DNSBL IPv6 NAT Rules
					if (!empty($pfb['dnsbl_vip6'])) {
						$dnsbl_new_nat[] = array (	'source'		=> array('any'  => ''),
										'destination'		=> array('address' => "{$pfb['dnsbl_vip6']}",
														'port' => "{$lport}"),
										'protocol'		=> 'tcp',
										'target'		=> '::1',
										'local-port'		=> "{$port}",
										'interface'		=> "{$pfb['dnsbl_iface']}",
										'ipprotocol'		=> 'inet6',
										'descr'			=> 'pfB DNSBL - DO NOT EDIT',
										'associated-rule-id'	=> 'pass',
										'natreflection'		=> 'purenat'
										);
					}
				}
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

		$pfbfound = FALSE;

		if ($mode != 'enabled') {
			// Update when DNSBL NAT found but is now disabled.
			if ($pfbfound) {
				$pfbupdate = TRUE;
			}
		}

		// Compare previous Lighttpd conf
		$pfb_lighty_conf = pfb_create_lighttpd();
		$pfb_lighty_conf_ex = @file_get_contents($pfb['dnsbl_conf']);
		if ($pfb_lighty_conf !== $pfb_lighty_conf_ex) {
			$pfbupdate = TRUE;
			@file_put_contents($pfb['dnsbl_conf'], $pfb_lighty_conf, LOCK_EX);
			$log = "\nSaving new DNSBL web server configuration to port [ {$pfb['dnsbl_port']} and {$pfb['dnsbl_port_ssl']} ]";
			pfb_logger("{$log}", 1);
		}

		// Update config.xml, if changes required
		if ($pfbupdate) {
			config_set_path('nat/rule', $new_nat);
			write_config('pfBlockerNG: saving DNSBL changes');
		}
	}

	// Save settings, restart services as required
	if ($mode == 'enabled') {

		// Create DNSBL SSL certificate
		if (!file_exists("{$pfb['dnsbl_cert']}")) {
			pfb_create_dnsbl_cert();

			$log = "\nNew DNSBL certificate created";
			pfb_logger("{$log}", 1);
		}

		if ($pfbupdate || !is_service_running('pfb_dnsbl')) {

			// Remove any existing and create link for DNSBL lighttpd executable
			unlink_if_exists('/usr/local/sbin/lighttpd_pfb');
			link('/usr/local/sbin/lighttpd', '/usr/local/sbin/lighttpd_pfb');

			$log = "\nRestarting DNSBL Service";
			pfb_logger("{$log}", 1);
			restart_service('pfb_dnsbl');
		}
	}
	else {
		if (is_service_running('pfb_dnsbl')) {
			pfb_logger("Stop Service DNSBL\n", 1);
			stop_service('pfb_dnsbl');
		}
	}
}


// Define DNSBL Unbound include settings (config.xml)
function pfb_unbound_dnsbl($mode) {
	global $g, $pfb;
	pfb_global();

	// Reload config.xml to get any recent changes
	config_read_file(false, true);

	$pfbupdate = FALSE;
	$unbound_include = "server:include: {$pfb['dnsbl_file']}.*conf";

	// Collect Unbound custom option pfSense conf line
	$pfb['unboundconfig']	= config_get_path('unbound/custom_options');

	if (!empty($pfb['unboundconfig'])) {
		$unbound_custom = base64_decode($pfb['unboundconfig']);
	} else {
		$unbound_custom = '';
	}

	// Determine if DNSBL include line exists
	if (!empty($unbound_custom)) {
		// Append DNSBL Unbound pfSense conf integration
		if (!strstr($unbound_custom, 'pfb_dnsbl.*conf')) {
			if ($mode == 'enabled') {
				if (!$pfb['dnsbl_py_blacklist']) {
					$pfbupdate = TRUE;
					$unbound_custom .= "\n{$unbound_include}";
					$log = "\nAdding DNSBL Unbound mode (Resolver adv. setting)";
				}

				// To be removed when SafeSearch CNAME python mode has been fixed
				elseif ($pfb['safesearch_enable'] !== 'Disable') {
					$pfbupdate = TRUE;
					$unbound_custom .= "\n{$unbound_include}";
					$log = "\nAdding DNSBL SafeSearch CNAME mode (Resolver adv. setting)";
				}
			}
		}
		else {
			// Remove DNSBL Unbound pfSense conf integration when disabled
			// or when DNSBL python mode is enabled but not when SafeSearch is enabled
			if ($mode == 'disabled' || $pfb['dnsbl_py_blacklist']) {
				$custom = explode ("\n", $unbound_custom);
				foreach ($custom as $key => $line) {
					if (strpos($line, 'pfb_dnsbl.*conf') !== FALSE) {
						if (!$pfb['dnsbl_py_blacklist']) {
							$log = "\nRemoving DNSBL SafeSearch mode (Resolver adv. setting)";
							unset($custom[$key]);
							$pfbupdate = TRUE;
						}

						// To be removed when SafeSearch CNAME python mode has been fixed
						elseif ($mode == 'enabled' && $pfb['safesearch_enable'] !== 'Disable') {
							//
						}

						else {
							$log = "\nRemoving DNSBL Unbound mode and/or DNSBL SafeSearch CNAME mode (Resolver adv. setting)";
							unset($custom[$key]);
							$pfbupdate = TRUE;
						}
					}
				}
				$unbound_custom = implode("\n", $custom);
			}
		}
	}
	else {
		// Add DNSBL Unbound pfSense conf integration
		if ($mode == 'enabled') {
			if (!$pfb['dnsbl_py_blacklist']) {
				$pfbupdate = TRUE;
				$unbound_custom = "{$unbound_include}";
				$log = "\nAdding DNSBL Unbound mode (Resolver adv. setting)";
			}

			// To be removed when SafeSearch CNAME python mode has been fixed
			elseif ($pfb['safesearch_enable'] !== 'Disable') {
				$pfbupdate = TRUE;
				$unbound_custom = "{$unbound_include}";
				$log = "\nAdding DNSBL SafeSearch CNAME mode (Resolver adv. setting)";
			}
		}
	}

	// Remove the previous include line, see Bug #6603
	$custom = explode ("\n", $unbound_custom);
	foreach ($custom as $key => $line) {
		if (strpos($line, 'pfb_dnsbl.conf') !== FALSE) {
			$pfbupdate = TRUE;
			$log .= "\nDNSBL - Removing previous DNSBL Unbound custom option\n";
			unset($custom[$key]);
		}
	}
	$unbound_custom = implode("\n", $custom);

	// Update config.xml, if changes required
	if ($pfbupdate) {
		pfb_logger("{$log}", 1);
		$unbound_custom = base64_encode(str_replace("\r\n", "\n", $unbound_custom));
		$pfb['unboundconfig'] = "{$unbound_custom}";
		config_set_path('unbound/custom_options', $pfb['unboundconfig']);
		write_config('pfBlockerNG: saving Unbound custom options');
	}

	// Modify unbound.conf file as required
	if (file_exists("{$pfb['dnsbldir']}/unbound.conf")) {
		$conf = file("{$pfb['dnsbldir']}/unbound.conf");
		if (empty($conf)) {
			pfb_logger("\nDNS Resolver configuration file missing or empty, Exiting!", 1);
		}

		$unbound = FALSE;	// Unbound mode
		$unbound_py = FALSE;	// Unbound python mode

		$u_update = FALSE;
		$u_msg = '';
		foreach ($conf as $key => $line) {

			if (empty($line)) {
				continue;
			}

			elseif (strpos($line, 'pfb_dnsbl.*conf') !== FALSE) {
				if ($mode == 'enabled') {
					if (!$pfb['dnsbl_py_blacklist']) {
						$unbound = TRUE;
					}

					// To be removed when SafeSearch CNAME python mode has been fixed
					elseif ($mode == 'enabled' && $pfb['safesearch_enable'] !== 'Disable') {
						$unbound = TRUE;
					}

					else {
						$u_update = TRUE;
						$u_msg .= "  Removed DNSBL SafeSearch mode\n";
						unset($conf[$key]);
					}
				} else {
					$u_update = TRUE;
					$u_msg .= "  Removed DNSBL Unbound mode\n"; 
					unset($conf[$key]);
				}
			}

			elseif (strpos($line, 'module-config:') !== FALSE) {
				if ($mode == 'enabled' && $pfb['dnsbl_mode'] == 'dnsbl_python') {
					if (strpos($line, 'module-config: "python') !== FALSE) {
						$unbound_py = TRUE;
					} else {
						$u_update = TRUE;
						$u_msg .= "  Added DNSBL Unbound Python mode\n";
						$conf[$key] = str_replace('module-config: "', 'module-config: "python ', $line);
					}
				}
				else {
					// Only remove python module if script is 'pfb_unbound'
					if (strpos($line, 'module-config: "python') !== FALSE && in_array("python-script: pfb_unbound.py\n", $conf)) {
						$u_update = TRUE;
						$u_msg .= "  Removed DNSBL Unbound Python mode\n";
						$conf[$key] = str_replace('module-config: "python ', 'module-config: "', $line);
					}
				}
			}

			// Remove any DNSBL VIPs added to unbound.conf on 'disable'
			elseif ((strpos($line, 'interface:') !== FALSE) && ($mode == 'disabled') &&
			    !empty($pfb['dnsbl_vip4']) && (strpos($line, $pfb['dnsbl_vip4']) !== FALSE)) { 
				$u_update = TRUE;
				$u_msg .= "  Removed DNSBL VIP from Unbound Interface settings\n";
				unset($conf[$key]);
			}

			elseif (strpos($line, 'python-script: pfb_unbound.py') !== FALSE) {
				if ($mode == 'enabled' && $pfb['dnsbl_mode'] == 'dnsbl_python') {
					$unbound_py = TRUE;
				} else {
					$u_update = TRUE;
					$u_msg .= "  Removed DNSBL Unbound Python mode script\n";
					unset($conf[$key-1]); // remove 'python:' line above
					unset($conf[$key]);
				}
			}
		}

		// Add Unbound include line
		if (!$unbound && $mode == 'enabled') {
			if (!$pfb['dnsbl_py_blacklist']) {
				$u_update = TRUE;
				$u_msg .= "  Added DNSBL Unbound mode\n";
				$conf[] = "\nserver:include: {$pfb['dnsbl_file']}.*conf\n";
			}

			// To be removed when SafeSearch CNAME python mode has been fixed
			elseif ($mode == 'enabled' && $pfb['safesearch_enable'] !== 'Disable') {
				$u_update = TRUE;
				$u_msg .= "  Added DNSBL SafeSearch CNAME mode\n";
				$conf[] = "\nserver:include: {$pfb['dnsbl_file']}.*conf\n";
			}
		}

		// Add python script line
		if (!$unbound_py && $mode == 'enabled' && $pfb['dnsbl_mode'] == 'dnsbl_python') {
			$u_update = TRUE;
			$u_msg .= "  Added DNSBL Unbound Python mode script\n";
			$conf[] = "\npython:\npython-script: pfb_unbound.py\n";
		}

		if ($mode == 'enabled' && $pfb['dnsbl_mode'] == 'dnsbl_python') {
			if (!file_exists("{$g['unbound_chroot_path']}/pfb_unbound.py")) {
				@copy("/usr/local/pkg/pfblockerng/pfb_unbound.py", "{$g['unbound_chroot_path']}/pfb_unbound.py");
			}
			if (!file_exists("{$g['unbound_chroot_path']}/pfb_unbound_include.inc")) {
				@copy("/usr/local/pkg/pfblockerng/pfb_unbound_include.inc", "{$g['unbound_chroot_path']}/pfb_unbound_include.inc");
			}
			if (!file_exists("{$g['unbound_chroot_path']}/pfb_py_hsts.txt")) {
				@copy("/usr/local/pkg/pfblockerng/pfb_py_hsts.txt", "{$g['unbound_chroot_path']}/pfb_py_hsts.txt");
			}
		} else {
			unlink_if_exists("{$g['unbound_chroot_path']}/pfb_unbound.py");
			unlink_if_exists("{$g['unbound_chroot_path']}/pfb_unbound_include.inc");
			unlink_if_exists("{$g['unbound_chroot_path']}/pfb_py_hsts.txt");
		}

		// Save changes to unbound.conf
		if ($u_update) {
			pfb_logger("\nDNS Resolver ( {$mode} ) unbound.conf modifications:\n{$u_msg}", 1);
			@file_put_contents("{$pfb['dnsbldir']}/unbound.tmp", $conf, LOCK_EX);
			@chown("{$pfb['dnsbldir']}/unbound.tmp", 'unbound');
			@chgrp("{$pfb['dnsbldir']}/unbound.tmp", 'unbound');
			return TRUE;
		}
	}
	else {
		pfb_logger("\n\n*** [ Unbound.conf file missing. Exiting! ] ***\n\n", 1);
	}

	return FALSE;
}


// Create DNSBL Whitelist
function pfb_unbound_python_whitelist($mode='') {
	global $pfb;
	pfb_global();

	$dnsbl_whitelist = '';
	$dnsbl_white = pfbng_text_area_decode($pfb['dnsblconfig']['suppression'], TRUE, FALSE, TRUE);
	if (!empty($dnsbl_white)) {
		foreach ($dnsbl_white as $key => $line) {
			if (!empty($line)) {
				if (substr($line, 0, 4) == 'www.') {
					$line = substr($line, 4);
				}

				// Minimize the python whitelist queries to the smallest tld segment count
				if (!isset($tld_segments)) {
					$tld_segments = (substr_count($line, '.') +1);
				}
				$tld_segments = @min((array((substr_count($line, '.') +1), $tld_segments) ?: 1));

				if (substr($line, 0, 1) == '.') {
					$line = ltrim($line, '.');
					$dnsbl_whitelist .= "{$line},1\n";
				} else {
					$dnsbl_whitelist .= "{$line},0\n";
				}
			}
		}
	}

	if ($mode == 'alerts') {
		@file_put_contents($pfb['unbound_py_wh'], $dnsbl_whitelist, LOCK_EX);
	} else {
		return $dnsbl_whitelist;
	}
}


// Unbound python configuration file
function pfb_unbound_python($mode) {
	global $pfb;
	pfb_global();

	// Reload config.xml to get any recent changes
	config_read_file(false, true);

	$pfbpython = FALSE;

	// Ensure log file permissions are set as 'unbound:unbound'
	foreach (array('dnsbl.log', 'dns_reply.log', 'unified.log') as $logfile) {

		if (!file_exists("{$pfb['logdir']}/{$logfile}")) {
			touch("{$pfb['logdir']}/{$logfile}");
		}

		@chown("{$pfb['logdir']}/{$logfile}", 'unbound');
		@chgrp("{$pfb['logdir']}/{$logfile}", 'unbound');
	}

	// Add python settings to DNS Resolver configuration
	$python_enable = 'off';
	if ($mode == 'enabled' && $pfb['dnsbl_mode'] == 'dnsbl_python') {
		$python_enable = 'on';

		if (!config_path_enabled('unbound', 'python') ||
		    config_get_path('unbound/python_script') != 'pfb_unbound') {

			config_set_path('unbound/python', '');
			config_set_path('unbound/python_order', 'pre_validator');
			config_set_path('unbound/python_script', 'pfb_unbound');

			$pfbpython = TRUE;
			$log = 'Added DNSBL Unbound python integration settings';
			pfb_logger("\n{$log}", 1);
			write_config("pfBlockerNG: {$log}");
		}

		// If DNSBL python blocking mode enabled
		if ($pfb['dnsbl_py_blacklist']) {

			// Create DNSBL Whitelist
			$dnsbl_whitelist = pfb_unbound_python_whitelist();

			// Compare previous DNSBL Whitelist to new Whitelist
			$pfb_py_whitelist_ex = @file_get_contents($pfb['unbound_py_wh']);
			if ($dnsbl_whitelist !== $pfb_py_whitelist_ex) {
				$pfbpython = TRUE;
				@file_put_contents($pfb['unbound_py_wh'], $dnsbl_whitelist, LOCK_EX);
			}
		}

		// Remove previous whitelist and reload
		elseif (file_exists($pfb['unbound_py_wh'])) {
			unlink_if_exists($pfb['unbound_py_wh']);
			$pfbpython = TRUE;
		}

		if (!isset($tld_segments)) {
			$tld_segments = '1';
		}

		$python_reply = 'off';
		if ($pfb['dnsbl_py_reply'] == 'on') {
			$python_reply = 'on';
		}

		$python_blocking = 'off';
		if ($pfb['dnsbl_py_blacklist']) {
			$python_blocking = 'on';
		}

		$python_hsts = 'off';
		if ($pfb['dnsbl_hsts'] == 'on') {
			$python_hsts = 'on';
		}

		$python_idn = 'off';
		if ($pfb['dnsbl_idn'] == 'on') {
			$python_idn = 'on';
		}

		$python_cname = 'off';
		if ($pfb['dnsbl_cname'] == 'on') {
			$python_cname = 'on';
		}

		$python_control = 'off';
		if ($pfb['dnsbl_control'] == 'on') {
			$python_control = 'on';
		}

		$python_noaaaa = 'off';
		if ($pfb['dnsbl_noaaaa'] == 'on') {
			$python_noaaaa = 'on';
		}

		$python_tld = 'off';
		if ($pfb['dnsbl_pytld'] == 'on') {
			$python_tlds = '';
			if (!empty($pfb['dnsblconfig']['pfb_pytlds_gtld'])) {
				$python_tld = 'on';
				$python_tlds = $pfb['dnsblconfig']['pfb_pytlds_gtld'];
			}
			if (!empty($pfb['dnsblconfig']['pfb_pytlds_cctld'])) {
				$python_tld = 'on';
				$python_tlds .= ',' . $pfb['dnsblconfig']['pfb_pytlds_cctld'];
			}
			if (!empty($pfb['dnsblconfig']['pfb_pytlds_itld'])) {
				$python_tld = 'on';
				$python_tlds .= ',' . $pfb['dnsblconfig']['pfb_pytlds_itld'];
			}
			if (!empty($pfb['dnsblconfig']['pfb_pytlds_bgtld'])) {
				$python_tld = 'on';
				$python_tlds .= ',' . $pfb['dnsblconfig']['pfb_pytlds_bgtld'];
			}
			$python_tlds = ltrim($python_tlds, ',');
		}

		$python_nolog = 'off';
		if ($pfb['dnsbl_py_nolog'] == 'on') {
			$python_nolog = 'on';
		}

		$now = date('m/j/y H:i:s', time());

		$pfb_py_conf = <<<EOF
; pfBlockerNG DNSBL Unbound python configuration file
; pfb_unbound.ini [ File created: {$now} ]
[MAIN]
dnsbl_ipv4	= {$pfb['dnsbl_vip4']}
dnsbl_ipv6	= {$pfb['dnsbl_vip6']}
python_enable	= {$python_enable}
python_reply	= {$python_reply}
python_blocking	= {$python_blocking}
python_hsts	= {$python_hsts}
python_idn	= {$python_idn}
python_tld_seg	= {$tld_segments}
python_tld	= {$python_tld}
python_tlds	= {$python_tlds}
python_nolog	= {$python_nolog}
python_cname	= {$python_cname}
python_control	= {$python_control}

EOF;
		if ($pfb['dnsbl_regex'] == 'on' && isset($pfb['dnsbl_regex_list']) && !empty($pfb['dnsbl_regex_list'])) {
			$regex = '';
			$regex_list = pfbng_text_area_decode($pfb['dnsbl_regex_list'], TRUE, TRUE, FALSE);
			if (!empty($regex_list)) {
				$counter = 1;
				$key_index = array();
				foreach ($regex_list as $key => $list) {
					if (!isset($list[1])) {
						$list[1] = "Regex_{$counter}";
					} else {
						$list[1] = trim(ltrim($list[1], '#'));
						$list[1] = preg_replace("/\W/", '', str_replace(' ', '_', $list[1]));
					}

					// Check if key exists
					if (!isset($key_index[$list[1]])) {
						$regex .= "{$list[1]} = {$list[0]}\n";
						$key_index[$list[1]] = '';
					} else {
						$regex .= "{$list[1]}_{$counter} = {$list[0]}\n";
						$key_index["{$list[1]}_{$counter}"] = '';
					}
					$counter++;
				}
				$pfb_py_conf .= <<<EOF

[REGEX]
{$regex}
EOF;
			}
		}

		if ($pfb['dnsbl_noaaaa'] == 'on' && isset($pfb['dnsbl_noaaaa_list']) && !empty($pfb['dnsbl_noaaaa_list'])) {
			$noaaaa = '';
			$noaaaa_list = pfbng_text_area_decode($pfb['dnsbl_noaaaa_list'], TRUE, TRUE, TRUE);
			if (!empty($noaaaa_list)) {
				foreach ($noaaaa_list as $key => $list) {
					if (substr($list[0], 0, 1) == '.') {
						$list[0] = ltrim($list[0], '.');
						$noaaaa .= "{$key} = {$list[0]},1\n";
					} else {
						$noaaaa .= "{$key} = {$list[0]},0\n";
					}
				}
				$pfb_py_conf .= <<<EOF

[noAAAA]
{$noaaaa}
EOF;
			}
		}

		if ($pfb['dnsbl_gp'] == 'on' && isset($pfb['dnsbl_gp_bypass_list']) && !empty($pfb['dnsbl_gp_bypass_list'])) {
			$gp_bypass = '';
			$gp_bypass_list = pfbng_text_area_decode($pfb['dnsbl_gp_bypass_list'], TRUE, TRUE, FALSE);
			if (!empty($gp_bypass_list)) {
				foreach ($gp_bypass_list as $key => $list) {
					$gp_bypass .= "{$key} = {$list[0]}\n";
				}
				$pfb_py_conf .= <<<EOF

[GP_Bypass_List]
{$gp_bypass}
EOF;
			}
		}

		// Compare previous ini file to new ini (bypass file timestamp string)
		$pfb_py_conf_ex		= @file_get_contents($pfb['unbound_py_conf']);
		$pfb_py_conf_ex2	= preg_replace("/File created:.* ]/", "File created: {$now} ]", $pfb_py_conf_ex);

		if ($pfb_py_conf !== $pfb_py_conf_ex2) {
			$pfbpython = TRUE;
			@file_put_contents($pfb['unbound_py_conf'], $pfb_py_conf, LOCK_EX);
		}
	}

	else {
		$mode = 'disabled';

		// Remove python settings from DNS Resolver configuration
		if (config_path_enabled('unbound', 'python') && config_get_path('unbound/python_script') == 'pfb_unbound') {
			config_del_path('unbound/python');
			config_set_path('unbound/python_order', '');
			config_set_path('unbound/python_script', '');

			$log = 'Removing DNSBL Unbound python integration settings';
			pfb_logger("\n{$log}", 1);
			write_config("pfBlockerNG: {$log}");
		}
	}

	// Mount lib and bin folders
	$base_py = '/usr/local';

	$log = '';
	foreach (array('/bin', '/lib') as $dir) {
		$validate = exec("/sbin/mount | {$pfb['grep']} " . escapeshellarg("{$pfb['dnsbldir']}{$base_py}{$dir}") . " 2>&1");

		if ($mode == 'enabled' && empty($validate)) {
			if (!is_dir("{$pfb['dnsbldir']}{$base_py}{$dir}")) {
				$log .= "\n  Creating: {$pfb['dnsbldir']}{$base_py}{$dir}";
				safe_mkdir("{$pfb['dnsbldir']}{$base_py}{$dir}");
			}
			$pfbpython = TRUE;
			$log .= "\n  Mounting: /usr/local{$dir}";
			exec("/sbin/mount_nullfs -o ro " . escapeshellarg("/usr/local{$dir}") . ' '
				. escapeshellarg("{$pfb['dnsbldir']}{$base_py}{$dir}") . " 2>&1", $output, $retval);
			if ($retval != 0) {
				$log .= "\n  Failed to mount [ /usr/local{$dir} ] to [ {$pfb['dnsbldir']}{$base_py}{$dir} ]!";
			}
		}
	}

	if (!empty($log)) {
		pfb_logger("\nAdding DNSBL Unbound python mounts:{$log}\n", 1);
	}

	// Set flag to unmount python folders after the next reboot is completed to avoid crashing Unbound
	if ($mode == 'disabled') {
		$pfb['dnsbl_python_unmount'] = TRUE;
	}
	return $pfbpython;
}


// Unbound python unmount
function pfb_unbound_python_unmount() {
	global $pfb;

	// Unmount lib and bin folders
	$base_py = '/usr/local';

	$log = '';
	foreach (array('/bin', '/lib') as $dir) {
		$validate = exec("/sbin/mount | {$pfb['grep']} " . escapeshellarg("{$pfb['dnsbldir']}{$base_py}{$dir}") . " 2>&1");
		if (!empty($validate)) {
			$log .= "\n  Unmounting: /usr/local{$dir}";
			exec("/sbin/umount -t nullfs " . escapeshellarg("{$pfb['dnsbldir']}{$base_py}{$dir}") . " 2>&1", $output, $retval);
			if ($retval != 0) {
				$log .= "\n  Failed to unmount [ {$pfb['dnsbldir']}{$base_py}{$dir} ]!";
			}
		}

		foreach (array( "/usr/local{$dir}", '/usr/local', '/usr') as $folder) {
			if (is_dir("{$pfb['dnsbldir']}{$folder}")) {
				$log .= "\n  Removing: {$pfb['dnsbldir']}{$folder}";
				@rmdir("{$pfb['dnsbldir']}{$folder}");
			}

			// Delete remaining subfolders on next loop
			if ($dir == '/bin') {
				break;
			}
		}
	}

	if (!empty($log)) {
		pfb_logger("\nRemoving DNSBL Unbound python mounts:{$log}\n", 1);
	}
}


// Search for TLD match
function tld_search($tld, $dparts, $j, $k) {
	global $tlds;

	$tld_query = implode('.', array_slice($dparts, -$j, $j, TRUE));
	if (isset($tlds[$tld][$tld_query])) {
		return implode('.', array_slice($dparts, -$k, $k, TRUE));
	}
	return NULL;
}


// Function to determine if each Domain is a Sub-Domain ('transparent' zone) or a whole Domain ('redirect' zone)
function tld_analysis() {
	global $pfb, $tlds;

	if (!file_exists("{$pfb['dnsbl_file']}.raw")) {
		pfb_logger("\n\nTLD Analysis not required.", 1);
		return;
	}

	pfb_logger("\nTLD:\n", 1);

	$domain_cnt = 0;
	$pfb_found = FALSE;				// Flag to determine if TLD 'redirect' zones found

	rmdir_recursive("{$pfb['dnsbl_tmpdir']}");
	if (!$pfb['dnsbl_py_blacklist']) {
		safe_mkdir("{$pfb['dnsbl_tmpdir']}");
	}
	unlink_if_exists("{$pfb['dnsbl_file']}.tsp");
	unlink_if_exists("{$pfb['dnsbl_tld_txt']}.*");
	unlink_if_exists("{$pfb['dnsbl_tld_remove']}.tsp");
	unlink_if_exists("{$pfb['dnsbl_tld_remove']}");
	unlink_if_exists("{$pfb['dnsbl_tmp']}.sup");
	unlink_if_exists("{$pfb['dnsbl_tmp']}.adup");

	// Master TLD Domain list
	if (($t_handle = @fopen("{$pfb['dnsbl_tld_data']}", 'r')) !== FALSE) {
		while (($line = @fgets($t_handle)) !== FALSE) {
			$line	= rtrim($line, "\x00..\x1F");
			$tld	= substr($line, strrpos($line, '.') + 1);

			if (!empty($tld)) {
				if (!is_array($tlds[$tld])) {
					$tlds[$tld] = array();
				}
				$tlds[$tld][$line] = '';
			}
		}
		@fclose($t_handle);
	} else {
		pfb_logger("\n ** TLD Master data file missing. Terminating TLD **\n", 1);
		return;
	}

	// DNSBL python - create file handles for data, zone, and remove files
	if ($pfb['dnsbl_py_blacklist']) {
		$p_data = @fopen("{$pfb['unbound_py_data']}.raw", 'w');
		$p_zone = @fopen("{$pfb['unbound_py_zone']}.raw", 'w');
		$p_tsp = @fopen("{$pfb['dnsbl_tld_remove']}", 'w');

		if ((get_resource_type($p_data) != 'stream') ||
		    (get_resource_type($p_zone) != 'stream') ||
		    (get_resource_type($p_tsp) != 'stream')) {

			pfb_logger("\nFailed to create DNSBL python data|zone|remove file handles! Exiting\n", 1);
			foreach (array($p_data, $p_zone, $p_tsp) as $handlex) {
				if ($handlex) {
					@fclose($handlex);
				}
			}
			return;
		}
	}

	// Collect TLD Blacklist(s). If configured the whole TLD will be blocked
	$tld_blacklist = pfbng_text_area_decode($pfb['dnsblconfig']['tldblacklist'], TRUE, FALSE, TRUE);
	if (!empty($tld_blacklist)) {
		$tld_blacklist = array_flip($tld_blacklist);
	}

	// Collect TLD Whitelist(s). If configured, create a 'static local-zone' Resolver entry (Not required for python mode blocking)
	$whitelist = array();
	if (!$pfb['dnsbl_py_blacklist']) {
		$whitelist = pfbng_text_area_decode($pfb['dnsblconfig']['tldwhitelist'], TRUE, FALSE, TRUE);
		$tld_whitelist = array();
	}

	$extdns_esc = escapeshellarg("@{$pfb['extdns']}");

	if (!empty($tld_blacklist) && !empty($whitelist)) {
		foreach ($whitelist as $domain) {

			// Use user-defined IP address
			if (strpos($domain, '|') !== FALSE) {
				list($domain, $resolved_host) = array_map('trim', explode('|', $domain));
			}

			// Resolve Domain IP address
			else {
				$domain_esc = escapeshellarg($domain);
				$resolved_host = exec("/usr/bin/drill {$extdns_esc} {$domain_esc} | grep -v '^;\|^\$' | head -1 | cut -f5 2>&1");
			}

			$tld = '';
			if (strpos($domain, '.') !== FALSE && is_ipaddr($resolved_host)) {
				$dparts = explode('.', $domain);
				$dcnt	= count($dparts);
				$tld	= end($dparts);

				for ($i=($dcnt-1); $i > 0; $i--) {
					$d_query = implode('.', array_slice($dparts, -$i, $i, TRUE));
					if (isset($tlds[$tld][$d_query])) {
						$tld = $d_query;
						break;
					}
				}
			}

			$resolved_host = pfb_filter($resolved_host, PFB_FILTER_IP, 'tld_analysis');
			if (!empty($tld) && !empty($resolved_host)) {
				if (!is_array($tld_whitelist[$tld])) {
					$tld_whitelist[$tld] = array();
				}
				$tld_whitelist[$tld][] = array($domain, $resolved_host);
				pfb_logger(" TLD Whitelist {$domain}|{$resolved_host}\n", 1);
			} elseif (!empty($resolved_host)) {
				pfb_logger("\n TLD Whitelist - Missing data | {$domain} | {$resolved_host} |\n", 1);
			}
		}
	}

	// Process TLD Blacklist(s). If configured the whole TLD will be blocked
	if (!empty($tld_blacklist)) {

		$tld_list	= '';
		$tld_cnt	= 0;
		$tld_segments	= 0;
		pfb_logger(" Blocking full TLD/Sub-Domain(s)... |", 1);

		foreach ($tld_blacklist as $tld => $key) {

			unset($tld_blacklist[$tld]);			// Remove old entry
			$tld = trim($tld, '.');				// Remove any leading/trailing dots
			$tld_blacklist[$tld] = '';			// Add new TLD entry

			// DNSBL python - TLD Blacklist (Set logging type to enabled '1')
			if ($pfb['dnsbl_py_blacklist']) {
				$tld_cnt++;
				$pfb_found = TRUE;
				$tld_segments = @max((array((substr_count($tld, '.') +1), $tld_segments) ?: 1));

				pfb_logger("{$tld}|", 1);
				@fwrite($p_zone, ",{$tld},,1,DNSBL_TLD,DNSBL_TLD\n");

				// Add TLD to remove file
				@fwrite($p_tsp, ".{$tld},,\n");

				// Collect List of TLDs and save to DNSBL folder
				$tld_list .= ",{$tld},,\n";

				// Remove any 'TLD Blacklists' from the 'TLD master list'
				if (isset($tlds[$tld])) {
					unset($tlds[$tld]);
				}
				continue;
			}

			// (Unbound mode only. Cannot have duplicate zones defined
			elseif (!empty($pfb['safesearch_tlds']) && isset($pfb['safesearch_tlds'][$tld])) {
				pfb_logger("\n{$tld}(Removed due to SafeSearch conflict)", 1);
				continue;
			}

			$dnsbl_file = "{$pfb['dnsbl_tmpdir']}/DNSBL_{$tld}.txt";
			if (!file_exists($dnsbl_file)) {

				$tld_cnt++;
				$pfb_found = TRUE;
				$tld_segments = @max((array((substr_count($tld, '.') +1), $tld_segments) ?: 1));

				// If a 'TLD Whitelist' exists, use 'static local-zone'
				if (isset($tld_whitelist[$tld])) {

					pfb_logger("{$tld}(static)|", 1);
					$dnsbl_line = "local-zone: \"{$tld}\" \"static\"\n";
					$whitelist = $tld_whitelist[$tld];

					foreach ($whitelist as $list) {
						$ip_type = is_ipaddr($list[1]);
						$ip_esc = escapeshellarg($list[0]);

						switch ($ip_type) {
							case 4:
								$dnsbl_line .= "local-data: \"{$list[0]} A {$list[1]}\"\n";
								$tld_list .= "{$tld} | {$list[0]} A {$list[1]}\n";
								if (!empty($pfb['dnsbl_vip6'])) {
									$r = exec("/usr/bin/drill {$extdns_esc} AAAA {$ip_esc} | grep -v '^;\|^\$' | head -1 | cut -f5 2>&1");
									if (is_ipaddrv6($r)) {
										$dnsbl_line .= "local-data: \"{$list[0]} AAAA {$r}\"\n";
										$tld_list .= "{$list[0]} AAAA {$r}\"\n";
									}
								}
								break;
							case 6:
								$r = exec("/usr/bin/drill {$extdns_esc} A {$ip_esc} | grep -v '^;\|^\$' | head -1 | cut -f5 2>&1");
								if (is_ipaddrv4($r)) {
									$dnsbl_line .= "local-data: \"{$list[0]} A {$r}\"\n";
									$tld_list .= "{$tld} | {$list[0]} A {$r}\"\n";
								}
								$dnsbl_line .= "local-data: \"{$list[0]} AAAA {$list[1]}\"\n";
								$tld_list .= "{$tld} | {$list[0]} AAAA {$list[1]}\"\n";
								break;
							default:
								break;
						}
					}
				}

				// Create 'redirect' zone for whole TLD
				else {
					pfb_logger("{$tld}|", 1);

					$ipv6_dnsbl = '';
					if (!empty($pfb['dnsbl_vip6'])) {
						$ipv6_dnsbl = " local-data: \"{$tld} 60 IN AAAA {$pfb['dnsbl_vip6']}\"";
					}
					$dnsbl_line = "local-zone: \"{$tld}\" redirect local-data: \"{$tld} 60 IN A {$pfb['dnsbl_vip4']}\"{$ipv6_dnsbl}\n";

					// Collect List of TLDs and save to DNSBL folder
					$tld_list .= "{$tld}\n";
				}

				@file_put_contents($dnsbl_file, $dnsbl_line, LOCK_EX);

				// Add TLD to remove file (To be removed from 'transparent' zone)
				@file_put_contents("{$pfb['dnsbl_tld_remove']}.tsp", ".{$tld} 60\n", FILE_APPEND | LOCK_EX);

				// Remove any 'TLD Blacklists' from the 'TLD master list'
				if (isset($tlds[$tld])) {
					unset($tlds[$tld]);
				}
			}
		}

		// Save a list of TLDs in DNSBL folder (DNSBL total line count verification)
		if (!empty($tld_list)) {
			@file_put_contents("{$pfb['dnsbl_tld_txt']}", $tld_list, LOCK_EX);

			// Add 'TLD' to Alias/Feeds array
			if (!is_array($pfb['tld_update']['DNSBL_TLD'])) {
				$pfb['tld_update']['DNSBL_TLD'] = array();
			}

			$pfb['tld_update']['DNSBL_TLD']['feeds']	= array('DNSBL_TLD');
			$pfb['tld_update']['DNSBL_TLD']['count']	= $tld_cnt;
			$pfb['alias_dnsbl_all'][]			= 'DNSBL_TLD';
		}
		else {
			unlink_if_exists("{$pfb['dnsbl_tld_txt']}");
		}
		pfb_logger(" completed\n", 1);
	}
	else {
		unlink_if_exists("{$pfb['dnsbl_tld_txt']}");
	}

	// Collect TLD Exclusion list and remove any 'TLD Exclusions' from the 'TLD master list'
	$exclusion = pfbng_text_area_decode($pfb['dnsblconfig']['tldexclusion'], TRUE, FALSE, TRUE);
	$tld_exclusion = array();
	if (!empty($exclusion)) {
		foreach ($exclusion as $key => $exclude) {
			$exclude = trim($exclude, '.');		// Remove any leading/trailing dots

			// Collect exclusion
			if (strpos($exclude, '.') !== FALSE) {
				$tld_exclusion[$exclude] = '';
			}

			// Remove Exclusion from TLDS array
			if (isset($tlds[$exclude])) {
				unset($tlds[$exclude]);
			}
		}
	}

	pfb_logger("TLD analysis", 1);

	// [ $pfb['dnsbl_file']}.tsp	] Final DNSBL output file (using 'transparent' zone)
	// [ $pfb['dnsbl_tld_remove']	] File of Sub-Domains to be removed (from 'redirect' zone)

	// DNSBL Unbound: Analyse DNSBL: 1) 'redirect' zone for whole Domain 2) 'transparent' zone only
	// DNSBL python: Analyse DNSBL into local and zone files

	if (($fhandle = @fopen("{$pfb['dnsbl_file']}.raw", 'r')) !== FALSE) {
		while (($line = @fgets($fhandle)) !== FALSE) {
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

			// DNSBL python blocking mode
			if ($pfb['dnsbl_py_blacklist']) {
				$eparts = explode(',', $line, 3);
				$domain = $eparts[1];
				$dparts = explode('.', $domain);
				$dcnt   = count($dparts);
				$tld    = end($dparts);
				$d_info = $eparts[2]; // Logging Type/Header/Alias group details
				$dfound	= '';
			}

			// DNSBL Unbound blocking mode
			else {
				$eparts = explode(' ', str_replace('"', '', $line), 3);
				$domain = $eparts[1];
				$s_info = trim($eparts[2]);

				if (!empty($pfb['dnsbl_vip6'])) {
					// Determine if DNSBL Logging is disabled and switch to '::0'
					if (strpos($s_info, ' A 0.0.0.0') !== FALSE) {
						$s_info6 = str_replace(' A 0.0.0.0', ' AAAA ::0', $s_info);
					} else {
						$s_info6 = str_replace(' A ', ' AAAA ::', $s_info);
					}
				}

				$dparts = explode('.', $domain);
				$dcnt	= count($dparts);
				$tld	= end($dparts);
				$dfound = '';
			}

			// Determine if TLD exists in TLD Blacklist (skip for DNSBL python)
			if (!$pfb['dnsbl_py_blacklist'] && !empty($tld_blacklist)) {

				// Determine minimum 'tld level' for loop efficiency
				$min_cnt = @min(array($tld_segments, ($dcnt -1)));

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
						$dfound = tld_search($tld, $dparts, 4, 5);
						break;
					case '4':
						$dfound = tld_search($tld, $dparts, 3, 4);
						break;
					case '3':
						$dfound = tld_search($tld, $dparts, 2, 3);
						break;
					case '2':
						$dfound = implode('.', array_slice($dparts, -2, 2, TRUE));
						break;
				}
			}

			// If Domain is in the TLD Exclusion(s), use 'transparent zone'
			if (!empty($domain) && isset($tld_exclusion[$domain])) {
				$dfound = '';
			}

			// Create 'redirect' zone for Domain
			if (!empty($dfound)) {
				$pfb_found = TRUE;

				// DNSBL python blocking mode
				if ($pfb['dnsbl_py_blacklist']) {
					@fwrite($p_zone, ",{$dfound},{$d_info}");

					// TLD remove files - See below for description
					@fwrite($p_tsp, ".{$dfound},,\n");
				}
				else {
					$ipv6_dnsbl = '';
					if (!empty($pfb['dnsbl_vip6'])) {
						$ipv6_dnsbl = " local-data: \"{$dfound} {$s_info6}\"";
					}
					$domain_line = "local-zone: \"{$dfound}\" redirect local-data: \"{$dfound} {$s_info}\"{$ipv6_dnsbl}\n";
					@file_put_contents("{$pfb['dnsbl_file']}.tsp", $domain_line, FILE_APPEND | LOCK_EX);

					// Add Domain to remove file for [ 1- 'redirect zone' Domains 2- Unbound memory domains ]
					// This removes any of these domains and sub-domains
					@file_put_contents("{$pfb['dnsbl_tld_remove']}", ".{$dfound} 60\n\"{$dfound} 60\n", FILE_APPEND | LOCK_EX);

					// Add Domain to remove file for 'transparent zone' domains
					// This removes any of these sub-domains
					@file_put_contents("{$pfb['dnsbl_tld_remove']}.tsp", ".{$dfound} 60\n", FILE_APPEND | LOCK_EX);
				}
			}

			// Create 'transparent zone' for Sub-Domain
			else {
				// DNSBL python blocking mode
				if ($pfb['dnsbl_py_blacklist']) {
					@fwrite($p_data, ",{$domain},{$d_info}");
				}
				else {
					if (!empty($tld)) {
						$dnsbl_file = "{$pfb['dnsbl_tmpdir']}/DNSBL_{$tld}.txt";

						// Create a temp file for each TLD. w/ 'transparent' header followed by each 'local-data' line
						if (!file_exists($dnsbl_file)) {
							$dnsbl_header = "local-zone: \"{$tld}\" \"transparent\"\n";
							@file_put_contents($dnsbl_file, $dnsbl_header, LOCK_EX);
						}

						$ipv6_dnsbl = '';
						if (!empty($pfb['dnsbl_vip6'])) {
							$ipv6_dnsbl = " local-data: \"{$domain} {$s_info6}\"";
						}
						$domain_line = "local-data: \"{$domain} {$s_info}\"{$ipv6_dnsbl}\n";
						@file_put_contents($dnsbl_file, $domain_line, FILE_APPEND | LOCK_EX);
					}
					else {
						$oline = htmlentities($line);
						pfb_logger("\nDebug: Missing TLD: {$oline}", 1);
					}
				}
			}

			// Increment Domain counter
			$domain_cnt++;
		}
	}

	foreach (array($fhandle, $p_data, $p_zone, $p_tsp) as $handlex) {
		if ($handlex) {
			@fclose($handlex);
		}
	}
	unset($tlds, $tld_blacklist, $tld_exclusion);

	// TLD 'redirect zones' found. Finalize TLD function
	if ($pfb_found) {
		$log = " completed [ NOW ]\n";
		// Print TLD exceedance error message
		if ($domain_cnt >= $pfb['domain_max_cnt']) {
			$log .= "\n  ** TLD Domain count exceeded. [ {$pfb['domain_max_cnt']} ] All subsequent Domains listed as-is **\n\n";
		}
		$log .= "TLD finalize";
		pfb_logger("{$log}", 1);

		// Execute Domain De-duplication
		if ($pfb['dnsbl_py_blacklist']) {
			exec("{$pfb['script']} domaintldpy >> {$pfb['log']} 2>&1");
		}
		else {
			// Create a csv list of 'recently updated' DNSBL Feeds, as ordered by User
			$dnsbl_feeds = '';
			if (!empty($pfb['tld_update'])) {
				foreach ($pfb['tld_update'] as $alias => $data) {
					foreach ($data['feeds'] as $feed) {
						$dnsbl_feeds .= "{$feed},";
					}
				}
			}

			if (!empty(pfb_filter($dnsbl_feeds, PFB_FILTER_CSV, 'tld_analysis'))) {
				exec("{$pfb['script']} domaintld x x x {$dnsbl_feeds} >> {$pfb['log']} 2>&1");
			} else {
				pfb_logger("\nFailed to create list of DNSBL Feeds", 1);
			}
		}
		pfb_logger("\nTLD finalize... completed [ NOW ]\n", 1);

		// Update DNSBL Alias and Widget Stats
		if (!empty($pfb['tld_update'])) {
			foreach ($pfb['tld_update'] as $alias => $data) {

				// Create Alias summary file for each DNSBL Alias
				$lists_dnsbl_current = array();
				foreach ($data['feeds'] as $feed) {
					$lists_dnsbl_current[] = "{$feed}";
				}
				dnsbl_alias_update('update', $alias, $pfb['dnsdir'], $lists_dnsbl_current, $data['count']);
			}
		}
	}
	else {
		pfb_logger(" no changes\n", 1);
	}

	// Save DNSBL Alias statistics
	dnsbl_save_stats();

	if ($pfb['dnsbl_py_blacklist'] && file_exists("{$pfb['dnsbl_file']}.raw")) {
		unlink_if_exists("{$pfb['dnsbl_file']}.raw");
	}
}

// Function to Start Unbound
function pfb_stop_start_unbound($type) {
	global $g, $pfb;

	$final = array();
	if (file_exists("{$g['varrun_path']}/unbound.pid")) {
		pfb_logger("\nStopping Unbound Resolver", 1);
		sigkillbypid("{$g['varrun_path']}/unbound.pid", 'TERM');
	}

	// If unbound is still running, wait up to 30 seconds for it to terminate.
	for ($i=1; $i <= 30; $i++) {
		if (is_process_running('unbound')) {
			pfb_logger('.', 1);
			sleep(1);
		} else {
			pfb_logger("\nUnbound stopped in {$i} sec.", 1);
			break;
		}
	}

	// Add/Remove additional python mounts
	if (file_exists('/var/unbound/pfb_unbound_include.inc')) {
		$g['pfblockerng_include_verbose'] = TRUE;
		pfb_logger("\nAdditional mounts{$type}:", 1);
		require_once('/var/unbound/pfb_unbound_include.inc');
		unset($g['pfblockerng_include_verbose']);
	}

	// Remove Unbound python mounts
	if ($pfb['dnsbl_python_unmount']) {
		pfb_unbound_python_unmount();
	}

	pfb_logger("\nStarting Unbound Resolver", 1);
	exec("/usr/local/sbin/unbound -c /var/unbound/unbound.conf 2>&1", $final['result'], $final['retval']);
	return $final;
}


// Reload Resolver
function pfb_reload_unbound($mode, $cache=FALSE, $pfbpython=FALSE) {
	global $g, $pfb;

	$final = array();
	$type = '';
	if ($mode == 'enabled' && $pfb['dnsbl_py_blacklist']) {
		$type = ' (DNSBL python)';
	}

	if (!$pfb['dnsbl_py_blacklist'] && file_exists("{$pfb['dnsbl_file']}.raw")) {
		@rename("{$pfb['dnsbl_file']}.raw", "{$pfb['dnsbl_file']}.conf");
	}

	$cache_dumpfile = tempnam('/var/tmp/', 'unbound_cache_');
	if ($mode == 'enabled' && is_process_running('unbound') && !$pfb['dnsbl_python_unmount'] && !$pfbpython) {

		$log = "\nReloading Unbound Resolver{$type}";
		pfb_logger($log, 1);

		if ($cache && $pfb['dnsbl_res_cache'] == 'on') {
			exec("{$pfb['chroot_cmd']} dump_cache > " . escapeshellarg($cache_dumpfile) . " 2>&1");
			pfb_logger('.', 1);
		}
	}

	$final = pfb_stop_start_unbound($type);
	pfb_logger('.', 1);

	if ($final['retval'] != 0) {

		@copy("{$pfb['dnsbldir']}/unbound.conf", "{$pfb['dnsbldir']}/unbound.conf.error");
		if ($mode == 'enabled') {
			if (!$pfb['dnsbl_py_blacklist']) {
				$log = "\nDNSBL {$mode} FAIL - restoring Unbound conf *** Fix error(s) and a Force Reload required! ***\n";

				// Try to restore previous DNSBL database
				if (file_exists("{$pfb['dnsbl_file']}.bk")) {
					@rename("{$pfb['dnsbl_file']}.bk", "{$pfb['dnsbl_file']}.conf");
				}

				// Wipe DNSBL database
				else {
					$log .= ' Restore previous database Failed!';
					unlink_if_exists("{$pfb['dnsbl_file']}.conf");
					touch("{$pfb['dnsbl_file']}.conf");

					// Restore previous unbound.conf
					if (file_exists("{$pfb['dnsbldir']}/unbound.bk")) {
						@rename("{$pfb['dnsbldir']}/unbound.bk", "{$pfb['dnsbldir']}/unbound.conf");
					}
				}
			}
			else {
				$log = "\nDNSBL {$mode} FAIL  *** Fix error(s) and a Force Reload required! ***\n";
				if (file_exists("{$pfb['dnsbldir']}/unbound.bk")) {
					@rename("{$pfb['dnsbldir']}/unbound.bk", "{$pfb['dnsbldir']}/unbound.conf");
				}
			}
			pfb_logger("{$log}", 2);
		}
		else {
			$log = "\nDNSBL {$mode} - Unbound conf update FAIL *** Fix error(s) and a Force Reload required! ***\n";
			pfb_logger("{$log}", 2);
		}

		$log = htmlspecialchars(implode("\n", $final['result']));
		pfb_logger("\n\n====================\n\n{$log}\n\n====================\n\n", 2);
		$final = pfb_stop_start_unbound($type);
	}

	// Confirm that Resolver is running
	if (is_process_running('unbound')) {
		pfb_logger('.', 1);

		// $final['result'] will be appended with previous result above
		exec("{$pfb['chroot_cmd']} status 2>&1", $final['result'], $final['retval']);
		pfb_logger('.', 1);
		if (preg_grep("/is running.../", $final['result'])) {
			pfb_logger(" completed [ NOW ]", 1);

			// Restore Resolver cache
			if ($cache && $pfb['dnsbl_res_cache'] == 'on' && file_exists($cache_dumpfile) && filesize($cache_dumpfile) > 0) {
				exec("{$pfb['chroot_cmd']} load_cache < " . escapeshellarg($cache_dumpfile) . " 2>&1");
				$log = "\nResolver cache restored [ NOW ]";
				pfb_logger($log, 1);
			}
		}
		else {
			$log = htmlspecialchars(implode("\n", $final['result']));
			pfb_logger(" Not completed. [ NOW ]\n{$log}\n", 1);
		}
	}
	else {
		$log = htmlspecialchars(implode("\n", $final['result']));
		pfb_logger(" Not completed. [ NOW ]\n{$log}\n", 1);
	}
	unlink_if_exists($cache_dumpfile);
}


// Function to clear Unbound/DNSBL work files
function pfb_unbound_clear_work_files() {
	global $pfb;

	foreach (array( $pfb['dnsbl_cache'],
			"{$pfb['dnsbldir']}/unbound.bk",
			"{$pfb['dnsbldir']}/unbound.tmp",
			"{$pfb['dnsbl_file']}.bk",
			"{$pfb['dnsbl_file']}.tsp",
			"{$pfb['dnsbl_file']}.sync",
			"/tmp/dnsbl_remove*",
			"/tmp/dnsbl_add*",
			"/tmp/dnsbl_tld*",
			"{$pfb['unbound_py_data']}.raw",
			"{$pfb['unbound_py_zone']}.raw",
			"/var/tmp/unbound_cache_*") as $remove) {
		unlink_if_exists($remove);
	}
}


// Load new DNSBL updates to Unbound Resolver
function pfb_update_unbound($mode, $pfbupdate, $pfbpython) {
	global $g, $pfb;

	if ($mode == 'enabled') {
		$ext = '.bk';
	} else {
		$ext = '.*';	// Remove all DNSBL Unbound files
	}

	// Execute TLD analysis, if configured
	if ($pfb['enable'] == 'on' && $pfb['dnsbl'] == 'on' && !$pfb['save']) {
		if ($pfb['dnsbl_tld']) {
			tld_analysis();
		} else {
			unlink_if_exists("{$pfb['dnsbl_tld_txt']}");
		}
	}

	// Create file marker to disable DNSBL Queries daemon to avoid unbound-control collisions
	touch("{$pfb['dnsbl_file']}.sync");

	// Marker file(s) to instruct Unbound to be reloaded
	if ($pfb['reuse_dnsbl'] == 'on' ||
	    file_exists("{$pfb['dnsbl_file']}.reload") ||
	    file_exists("{$pfb['dnsbl_unlock']}")) {

		$pfbupdate = TRUE;
		unlink_if_exists("{$pfb['dnsbl_file']}.reload");
		unlink_if_exists("{$pfb['dnsbl_unlock']}");
		unlink_if_exists("{$pfb['dnsbl_unlock']}.data");
	}

	// Backup existing unbound.conf and rename new unbound.conf file
	if (file_exists("{$pfb['dnsbldir']}/unbound.tmp")) {
		@copy("{$pfb['dnsbldir']}/unbound.conf", "{$pfb['dnsbldir']}/unbound.bk");
		@rename("{$pfb['dnsbldir']}/unbound.tmp", "{$pfb['dnsbldir']}/unbound.conf");
	}

	// When pfBlockerNG is disabled and 'keep blocklists' is disabled.
	if ($pfb['enable'] == '' && $pfb['keep'] == '' && !$pfb['install']) {
		unlink_if_exists("{$pfb['dnsbl_file']}{$ext}");
	}

	// Disable DNSBL
	if (($pfb['enable'] != 'on' || $pfb['dnsbl'] != 'on') && !$pfb['install']) {

		pfb_reload_unbound('disabled', FALSE, $pfbpython);
		if (is_service_running('pfb_dnsbl')) {
			pfb_logger("\nStop Service DNSBL", 1);
			stop_service('pfb_dnsbl');
		}
		pfb_unbound_clear_work_files();

		// Unmount Unbound python 'lib/bin' folders after Unbound has been reloaded with the python integration enabled
		if ($pfb['dnsbl_python_unmount']) {
			pfb_unbound_python_unmount();
			unset($pfb['dnsbl_python_unmount']);
		}
		pfb_logger("\nDNSBL is disabled\n", 1);
		return;
	}

	// Load new DNSBL updates
	if (is_service_running('unbound')) {

		// 'Live sync' new DNSBL updates utilizing unbound-control
		if (!$pfb['dnsbl_py_blacklist'] && $pfb['dnsbl_sync'] && !$pfbpython &&
		    file_exists("{$pfb['dnsbl_file']}.conf") && filesize("{$pfb['dnsbl_file']}.conf") > 0) {

			$sync_fail = FALSE;

			pfb_logger("\nResolver Live Sync analysis", 1);
			exec("{$pfb['script']} dnsbl_livesync >> {$pfb['log']} 2>&1");
			pfb_logger(" completed [ NOW ]", 1);

			$ucsync = array(array(	'dnsbl_remove_zone',	'local_zones_remove',	'Remove local-zone(s)' ),
					array(	'dnsbl_remove_data',	'local_datas_remove',	'Remove local-data(s)' ),
					array(	'dnsbl_add_zone',	'local_zones',		'Add local-zone(s)' ),
					array(	'dnsbl_add_data',	'local_datas',		'Add local-data(s)' ));

			// Disable zone updates when TLD is disabled
			if (!$pfb['dnsbl_tld']) {
				unset($ucsync[0], $ucsync[2]);
			}

			pfb_logger("\nResolver Live Sync finalizing:", 1);
			foreach ($ucsync as $skey => $sync) {
				$file = $pfb[$sync[0]];

				if (filesize("{$file}") > 0) {
					$result = array();
					exec("{$pfb['chroot_cmd']} {$sync[1]} < {$file}", $result, $retval);
					$result	= implode("\n", $result);
					$log	= "\n\t{$sync[2]}:\t\t{$result}";
				}
				else {
					$log	= "\n\t{$sync[2]}:\t\tno changes";
				}
				pfb_logger("{$log}", 1);

				if (!$sync_fail && !empty($retval)) {
					$sync_fail = TRUE;
				}
			}

			if ($sync_fail) {
				pfb_logger("\nResolver Live Sync ... FAILED!", 1);
				pfb_reload_unbound('reload', FALSE, $pfbpython);
			}
		}

		// Do a full Reload of Unbound
		else {
			pfb_reload_unbound($mode, TRUE, $pfbpython);
		}
	}

	// Start Unbound Service with new DNSBL Updates
	else {
		pfb_reload_unbound($mode, FALSE, $pfbpython);
	}

	if ($pfbpython) {
		$log = "\nRestarting DNSBL Service (DNSBL python)";
		pfb_logger("{$log}", 1);
		restart_service('pfb_dnsbl');
	}

	$dnsbl_cnt = exec("/bin/cat {$pfb['dnsdir']}/*.txt | {$pfb['grep']} -c ^ 2>&1");

	// Unbound blocking mode enabled
	if (!$pfb['dnsbl_py_blacklist']) {
		$final_cnt = exec("{$pfb['grep']} -v '\"transparent\"\|\"static\"' {$pfb['dnsbl_file']}.conf | {$pfb['grep']} -c ^ 2>&1");
		if ($final_cnt == $dnsbl_cnt) {
			$log = "\nDNSBL update [ {$final_cnt} | PASSED  ]... completed [ NOW ]";
		} else {
			$log = "\n*** DNSBL update [ {$final_cnt} ] [ {$dnsbl_cnt} ] ... OUT OF SYNC ! *** [ NOW ]";
		}
		pfb_logger("{$log}", 1);
	}

	// Python blocking mode enabled
	else {
		$tld_cnt = @file_get_contents($pfb['unbound_py_count']);
		$dnsbl_cnt = $dnsbl_cnt - $tld_cnt;
		$final_cnt = exec("/usr/bin/find {$pfb['unbound_py_data']} {$pfb['unbound_py_zone']} -type f 2>/dev/null | xargs cat | {$pfb['grep']} -c ^ 2>&1");
		if ($final_cnt == $dnsbl_cnt) {
			$log = "\nDNSBL update [ {$final_cnt} | PASSED  ]... completed [ NOW ]";
		} else {
			$log = "\n*** DNSBL update [ {$final_cnt} ] [ {$dnsbl_cnt} ] ... OUT OF SYNC ! *** [ NOW ]";
		}
		pfb_logger("{$log}", 1);
	}

	// DEBUG Live Sync
	if (!$pfb['dnsbl_py_blacklist'] && $pfb['enable'] == 'on' && $pfb['dnsbl'] == 'on' && $pfb['dnsbl_sync'] && !$pfbupdate && !$pfbpython && !$sync_fail) {
		pfb_logger("\n\nDNSBL DEBUG", 1);
		$datacnt = $zonecnt = 0;

		exec("{$pfb['chroot_cmd']} list_local_data | {$pfb['grep']} '{$pfb['dnsbl_vip4']}\|0\.0\.0\.0\$' | {$pfb['grep']} -v 'AAAA' | {$pfb['grep']} -c ^ 2>&1", $datacnt, $retval);
		$datacnt = implode($datacnt);
		pfb_logger('.', 1);

		if ($pfb['dnsbl_tld']) {
			exec("{$pfb['chroot_cmd']} list_local_zones | {$pfb['grep']} \"redirect\" | {$pfb['grep']} -c ^ 2>&1", $zonecnt, $retval);
			pfb_logger('.', 1);
			$zonecnt = implode($zonecnt);

			$tldcnt = array('0');
			if (file_exists('/var/db/pfblockerng/dnsbl/DNSBL_TLD.txt')) {
				exec("{$pfb['grep']} -c ' A \| AAAA ' /var/db/pfblockerng/dnsbl/DNSBL_TLD.txt 2>&1", $tldcnt, $retval);
			}
			$tldcnt = implode($tldcnt);
			$datacnt = $datacnt + $tldcnt;
		}
		pfb_logger("[ Data(s): {$datacnt}\tZone(s): {$zonecnt} | NOW ]", 1);
	}

	// Clear work files
	pfb_unbound_clear_work_files();
	pfb_logger("\n------------------------------------------------------------------------", 1);
}


// Process TOP1M database
function pfblockerng_top1m() {
	global $pfb;

	if (empty($pfb['dnsbl_alexa_inc'])) {
		pfb_logger("\n  TOP1M: No TLD Inclusions found.\n", 1);
		return;
	}

	// Array of TLDs to include in Whitelist
	$pfb_include = explode(',', $pfb['dnsbl_alexa_inc']);
	if (!empty($pfb_include)) {
		$pfb_include = array_flip($pfb_include);
	}

	$linecnt = $x = 0;
	pfb_logger(" Building TOP1M Whitelist [", 1);

	if (($handle = @fopen("{$pfb['dbdir']}/top-1m.csv", 'r')) !== FALSE) {
		$pfb_output = @fopen("{$pfb['dbdir']}/pfbalexawhitelist.txt", 'w');
		while (($line = @fgets($handle)) !== FALSE) {

			if (strpos($line, '.') === FALSE || strpos($line, ',') === FALSE || empty($line)) {
				continue;
			}

			// Display progress indicator
			if ($linecnt % 100000 == 0) {
				pfb_logger('.', 1);
			}

			// Collect Domain TLD
			$csvline	= str_getcsv($line, ',', '"', "\\");
			$tld		= substr($csvline[1], strrpos($csvline[1], '.') + 1);

			if (isset($pfb_include[$tld])) {
				// Whitelist both 'www.example.com' and 'example.com'
				if (substr($csvline[1], 0, 4) == 'www.') {
					$csvline[1] = substr($csvline[1], 4);
				}
				$x++;

				// Create three whitelist options per TOP1M whitelisted Domain
				if ($pfb['dnsbl_py_blacklist']) {
					@fwrite($pfb_output, ".{$csvline[1]},,\n,{$csvline[1]},,\n,www.{$csvline[1]},,\n");
				} else {
					@fwrite($pfb_output, ".{$csvline[1]} 60\n\"{$csvline[1]} 60\n\"www.{$csvline[1]} 60\n");
				}
			}

			if ($x >= $pfb['dnsbl_alexa_cnt']) {
				break;
			}
			$linecnt++;
		}
		pfb_logger("] [ Parsed {$linecnt} lines | Found {$x} of {$pfb['dnsbl_alexa_cnt']} ]...", 1);
	}
	else {
		$log = "\nTOP1M conversion Failed. File: top-1m.csv, not found...";
		pfb_logger("{$log}", 2);
	}

	if ($handle) {
		@fclose($handle);
	}
	if ($pfb_output) {
		@fclose($pfb_output);
	}

	// Remove Top1M update file marker
	unlink_if_exists("{$pfb['dbdir']}/top-1m.update");
}


// Function to remove any leading zeros in octets and to exclude private/reserved addresses.
function sanitize_ipaddr($ipaddr, $custom, $pfbcidr) {
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

	// Exclude private/reserved IPs (bypass exclusion for custom lists)
	if ($pfb['supp'] == 'on' && !$custom) {

		// Remove 'loopback' and '0.0.0.0' IPs
		if ($ip[0] == 127 || $ip[0] == 0 || empty($ip[0])) {
			return;
		}

		// Advanced IPv4 Tunable (Set CIDR Block size limit)
		if ($pfbcidr != 'Disabled' && !empty($mask) && $mask < $pfbcidr) {
			pfb_logger("\n  Suppression CIDR Limit: {$ip_final}/{$mask}", 1);
			$mask = '32';
		}

		if ($mask > 32) {
			$mask = '';
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


// Validate IPv6 IP addresses
function validate_ipv6($ipaddr) {
	if (strpos($ipaddr, '/') !== FALSE) {
		return is_subnetv6($ipaddr);
	}
	return is_ipaddrv6($ipaddr);
}


// Validate IP addresses
function validate_ip($ipaddr) {
	if (strpos($ipaddr, '/') !== FALSE) {
		return is_subnet($ipaddr);
	}
	return is_ipaddr($ipaddr);
}


// Function to check for loopback addresses (IPv4 range: 127.0.0.0/8, excluding IPv6)
function FILTER_FLAG_NO_LOOPBACK_RANGE($value) {
	// http://www.php.net/manual/en/filter.filters.flags.php
	return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? $value : (((ip2long($value) & 0xff000000) == 0x7f000000) ? FALSE : $value);
}


// Explode IP for evaluations
function ip_explode($ip) {

	$ix1	= '';
	$ix	= explode('.', $ip);
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
function find_reported_header($ip, $pfbfolder, $geoip=FALSE) {
	global $pfb;

	// Find exact IP match
	$q_ip	= escapeshellarg(str_replace('.', '\.', "^{$ip}"));
	$query	= exec("{$pfb['grep']} -s {$q_ip} {$pfbfolder} 2>&1");
	if (!empty($query)) {
		$rx = pfb_parse_query($query);
		return $rx;
	}
	else {
		$v4_type = FALSE;
		if (substr_count($ip, ':') > 1) {
			$query		= strstr($ip, ':', TRUE);	// IPv6 Prefix
			$query_esc	= escapeshellarg("^{$query}:");
		} else {
			$query		= strstr($ip, '.', TRUE);	// IPv4 Octet #1
			$query_esc	= escapeshellarg("^{$query}\.");
			$v4_type	= TRUE;
		}

		$result = array();
		if (!$geoip) {
			exec("{$pfb['grep']} -s {$query_esc} {$pfbfolder} 2>&1", $result);
		} else {
			$geoip_list = "Africa\|Antarctica\|Asia\|Europe\|North_America\|South_America\|Oceania\|Proxy_and_Satellite\|Top_Spammers";
			exec("{$pfb['grep']} -s {$query_esc} {$pfb['ccdir']}/*.txt | {$pfb['grep']} -v '{$geoip_list}' 2>&1", $result);
		}

		$cidrs = array();
		if (!empty($result)) {
			foreach ($result as $line) {
				$rx = pfb_parse_query($line);

				// Collect all CIDRs for analysis if Alert is from a CIDR
				if (strpos($rx[1], '/') !== FALSE) {
					$cidrs[] = $rx;
				}
			}
		}

		if (isset($result)) {
			unset($result);
		}

		// Determine which CIDR alerted the IP address
		if (!empty($cidrs)) {
			foreach ($cidrs as $line) {
				$validate = FALSE;
				if ($v4_type) {
					list($addr, $mask) = explode('/', $line[1]);
					$mask = (0xffffffff << (32 - $mask)) & 0xffffffff;
					$validate = ((ip2long($ip) & $mask) == (ip2long($addr) & $mask));
				}
				else {
					/* Normalize IPv6 prefix to its start address to avoid PHP errors
					 * https://redmine.pfsense.org/issues/14256
					 */
					list($prefix, $length) = explode('/', $line[1]);
					$prefix = gen_subnetv6($prefix, $length);
					$subnet = "{$prefix}/{$length}";

					$validate = (Net_IPv6::isInNetmask($ip, $subnet));
				}

				// Return header on CIDR match
				if ($validate) {
					unset($cidrs);
					return $line;
				}
			}
			unset($cidrs);
		}
	}

	if (isset($result)) {
		unset($result);
	}
	return array('Unknown', 'Unknown');
}


// Function to download feeds
function pfb_download($list_url, $file_dwn, $pflex=FALSE, $header, $format, $logtype, $vtype='', $timeout=300, $type='', $username='', $password='', $srcint=FALSE) {
	global $pfb;
	$http_status = '';
	$elog = ">> {$pfb['log']} 2>&1";

	// Remove any leading/trailing whitespace
	$list_url = trim($list_url);

	// Re-evaluate URL
	if ($format == 'whois') {
		if (empty(pfb_filter($list_url, PFB_FILTER_DOMAIN, 'pfb_download'))) {
			pfb_logger("\n Failed", 2);
			return FALSE;
		}
	}
	elseif ($format == 'asn') {
		if (empty(pfb_filter($list_url, PFB_FILTER_ALNUM, 'pfb_download'))) {
			pfb_logger("\n Failed", 2);
			return FALSE;
		}
	}
	elseif (!pfb_filter($list_url, PFB_FILTER_URL, 'pfb_download_failure')) {
		pfb_logger("\n Failed", 2);
		return FALSE;
	}

	// Cron update function for md5 comparison
	if ($type == 'md5') {
		pfb_logger("\t\t\t\t( md5 feed )\t\t", 1);
	}

	$file_dwn_esc	= escapeshellarg("{$file_dwn}.raw");
	$file_org_esc	= escapeshellarg("{$file_dwn}.orig");
	$list_url_esc	= escapeshellarg("{$list_url}");
	$header_esc	= escapeshellarg("{$header}");

	$file_download	= trim("{$file_dwn_esc}", "'");
	$orig_download	= trim("{$file_org_esc}", "'");
	$list_download	= trim("{$list_url_esc}", "'");
	$head_download	= trim("{$header_esc}", "'");

	$md5_download	= trim(escapeshellarg("{$file_dwn}.md5.raw"), "'");

	// If the Cron update function 'md5 comparison' generated an md5 file, re-utilize instead of downloading twice
	if (file_exists("{$md5_download}")) {
		$list_download = "{$md5_download}";
		pfb_logger(' ( md5 feed ) ', 1);
	}

	// Download RSYNC format
	if ($format == 'rsync') {
		$result	= exec("/usr/local/bin/rsync --timeout=5 {$list_url_esc} {$file_dwn_esc}");
		if ($result == 0) {
			$http_status = '200';
		} else {
			$log = "\n  RSYNC Failed...\n";
			pfb_logger("{$log}", "{$logtype}");
			return FALSE;
		}
	}
	elseif ($format == 'whois' || $format == 'asn') {
		// Convert a Domain name/AS into its respective IP addresses
		exec("{$pfb['script']} whoisconvert {$header_esc} {$vtype} {$list_url_esc} {$elog}");
		return TRUE;
	}
	else {
		// Determine if URL is a pfSense localfile
		$localfile = FALSE;
		if (pfb_filter("{$list_download}", PFB_FILTER_URL, 'pfb_download', '', TRUE)) {
			$localfile = TRUE;
		}

		// Download localfile format
		if ($localfile) {
			$file_data = @file_get_contents("{$list_download}");
			if ($file_data === FALSE) {
				$error = error_get_last();
				$log = "\n[ {$header} ] {$error['message']}\n";
				pfb_logger("{$log}", "{$logtype}");
				return FALSE;
			} else {
				// Save original downloaded file
				@file_put_contents("{$file_download}", $file_data, LOCK_EX);
				$http_status = '200';
			}
		}

		// Download using cURL
		else {
			$remote_stamp = -1;
			if (($fhandle = @fopen("{$file_download}", 'w')) !== FALSE) {
				if (!($ch = curl_init("{$list_download}"))) {
					$log = "\nFailed to create cURL resource... Exiting...\n";
					pfb_logger("{$log}", "{$logtype}");
					return FALSE;
				}

				curl_setopt_array($ch, $pfb['curl_defaults']);	// Load curl default settings
				curl_setopt($ch, CURLOPT_FILE, $fhandle);	// Add $fhandle setting to cURL
				curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);	// Set cURL download timeout
				curl_setopt($ch, CURLOPT_ENCODING, 'gzip');	// Request 'gzip' encoding from server if available
				if ($srcint) {
					curl_setopt($ch, CURLOPT_INTERFACE, $srcint);	// Use a specific interface when downloading lists
					pfb_logger("\nList: {$header} will be downloaded via interface: {$srcint}\n", 1);
				}

				if (!empty($username) && !empty($password)) {
					curl_setopt($ch, CURLOPT_USERPWD, "{$username}:{$password}");
					curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
				}

				// Attempt 3 Downloads before failing.
				for ($retries = 1; $retries <= 3; $retries++) {
					if (curl_exec($ch)) {
						// Collect remote timestamp.
						$raw_filetime = curl_getinfo($ch, CURLINFO_FILETIME);
						if ($raw_filetime == -1) {
							$remote_stamp = -1;
						} elseif (!empty(pfb_filter($raw_filetime, PFB_FILTER_NUM, 'pfb_download - remote timestamp'))) {
							$remote_stamp = $raw_filetime;
						}
						break;	// Break on success
					}

					$curl_error = curl_errno($ch);
					if ($logtype != 3) {
						pfb_logger(" cURL Error: {$curl_error} [ NOW ]\n", 1);
					} else {
						pfb_logger(" {$header}\t\tcURL Error: {$curl_error} [ NOW ]\n\n", 3);
					}

					/* 'Flex' Downgrade cURL errors -	[ 35 - GET_SERVER_HELLO:sslv3		]
										[ 51 - NO alternative certificate	]
										[ 60 - Local Issuer Certificate Subject	]	*/

					// Allow downgrade of cURL settings 'Flex' after 1st failure, if user configured.
					if ($retries == 1 && $pflex && in_array($curl_error, array( '35', '51', '60'))) {
						curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
						curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
						curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'TLSv1.3, TLSv1.2, TLSv1.1, TLSv1, SSLv3');
						$log = "\n  Downgrading SSL settings (Flex)";
						pfb_logger("{$log}", "{$logtype}");
					}
					else {
						if ($retries == 3) {
							$log = curl_error($ch) . " |{$head_download}|{$list_download}| Retry [{$retries}] in 5 seconds...\n";
						} else {
							$log = curl_error($ch) . " Retry [{$retries}] in 5 seconds...\n";
						}
						pfb_logger("{$log}", "{$logtype}");
						sleep(5);
						pfb_logger('.', "{$logtype}");
					}
				}

				// Collect RFC7231 http status code
				$http_status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

				if (isset($pfb['rfc7231'][$http_status])) {
					if ($logtype < 3) {
						pfb_logger(". {$pfb['rfc7231'][$http_status]}", 2);
					} else {
						pfb_logger(" {$file_dwn}\t\t{$pfb['rfc7231'][$http_status]}\n", $logtype);
					}
				} else {
					pfb_logger(". Unknown Failure Code [{$http_status}]", $logtype);
				}
				if ($ch) {
					curl_close($ch);
				}
			}
			if ($fhandle) {
				@fclose($fhandle);
			}
		}
	}

	// Cron update function for md5 comparison
	if ($type == 'md5') {
		if ($http_status == '200') {
			return TRUE;
		}
		return FALSE;
	}

	// Remove any downloaded files with md5 extension
	unlink_if_exists("{$md5_download}");

	// '304 not modified' - Utilize previously downloaded file if available
	if ($http_status == '304' && file_exists("{$orig_download}")) {
		return TRUE;
	}

	if (file_exists($file_download) && ($http_status == '200' || $http_status == '221' || $http_status == '226')) {

		if (isset($retval)) {
			unset($retval);
		}

		// Validate File Mime-type
		$file_type = pfb_filter(array("{$file_dwn_esc}", "{$file_download}", "{$list_download}"), PFB_FILTER_FILE_MIME, 'pfb_download');
		if (!$file_type) {
			return FALSE;
		}

		// Create update file markers on new downloads available
		switch($type) {
			case 'geoip':
				touch("{$pfb['dbdir']}/geoip.update");
				break;
			case 'top1m':
				touch("{$pfb['dbdir']}/top-1m.update");
				break;
			case 'asn':
				touch("{$pfb['dbdir']}/asn.update");
				break;
			default:
				break;
		}

		// Set downloaded file timestamp to remote timestamp
		if (isset($remote_stamp) && $remote_stamp != -1) {
			@touch("{$file_download}", $remote_stamp);
		}

		// Decompress file if required
		if ($file_type == 'application/x-gzip' || $file_type == 'application/gzip') {
			if ($type == 'geoip') {
				// Extras - MaxMind downloads
				exec("/usr/bin/tar -xzf {$file_dwn_esc} --strip=1 -C {$pfb['geoipshare']} >/dev/null 2>&1");
				unlink_if_exists($file_dwn_esc);
				return TRUE;
			}
			elseif ($type == 'asn') {
				exec("/usr/bin/gunzip -c {$file_dwn_esc} > {$header_esc}", $output, $retval);

				// Update ASN Lookup table definitions
				exec("{$pfb['script']} asn_table >> {$logtype} 2>&1");

				unlink_if_exists($file_dwn_esc);
				return TRUE;
			}
			elseif ($type == 'blacklist') {
				// Extras - Blacklist downloads
				@rename("{$file_download}", strstr("{$file_download}", '.raw', TRUE));
				$file_esc = trim(escapeshellarg("{$file_dwn}"), "'");
				$filename = basename("{$file_esc}", '.tar.gz');

				if (!empty(pfb_filter($filename, PFB_FILTER_WORD, 'pfb_download category'))) {
					rmdir_recursive("{$pfb['dbdir']}/{$filename}/");
					safe_mkdir("{$pfb['dbdir']}/{$filename}/");

					// Extract Blacklist categories from sub-folders into a single folder structure
					$cmd = "--include='*domains' -s',.*/\\(.*\\)/\\(.*\\)/domains,{$filename}_\\1_\\2,' -s',.*/\\(.*\\)/domains,{$filename}_\\1,'";
					$filename_esc = escapeshellarg("{$pfb['dbdir']}/{$filename}/");
					exec("/usr/bin/tar -xf " . escapeshellarg("{$file_dwn}") . " {$cmd} -C {$filename_esc} >/dev/null 2>&1");

					// Rename any Category files with dashes
					$verifydir = "{$pfb['dbdir']}/{$filename}";
					if (is_dir("{$verifydir}")) {
						$list = glob("{$verifydir}/{$filename}*");
						if (is_array($list) && !empty($list)) {
							foreach ($list as $verify) {
								if (strpos($verify, '-') !== FALSE) {
									rename($verify, str_replace('-', '_', $verify));
								}
							}
						}
					}

					// Create update file indicator for update process
					touch("{$pfb['dbdir']}/{$filename}/{$filename}.update");
				}
				else {
					pfb_logger("\n Invalid filename [{$filename}]", 1);
					return FALSE;
				}
			}
			else {
				if (!pfb_filter(array("{$file_dwn_esc}", "{$file_download}", "{$list_download}"), PFB_FILTER_FILE_MIME_COMPRESSED, 'pfb_download')) {
					return FALSE;
				}
				pfb_logger('.', 1);
				exec("/usr/bin/gunzip -c {$file_dwn_esc} > {$file_org_esc}", $output, $retval);
			}
		}
		elseif ($file_type == 'application/x-bzip2') {
			if (!pfb_filter(array("{$file_dwn_esc}", "{$file_download}", "{$list_download}"), PFB_FILTER_FILE_MIME_COMPRESSED, 'pfb_download')) {
				return FALSE;
			}
			pfb_logger('.', 1);
			exec("/usr/bin/bzip2 -dkc {$file_dwn_esc} > {$file_org_esc}", $output, $retval);
		}
		elseif ($file_type == 'application/zip') {

			// Extras - MaxMind/TOP1M downloads
			if ($type == 'geoip' || $type == 'top1m') {
				// Determine if Zip contains multiple files
				exec("/usr/bin/tar -tf {$file_dwn_esc} 2>&1", $archive_count, $retval);
				if ($archive_count[0] == 'tar: Failed to set default locale') {
					unset($archive_count[0]);
				}
				if (count($archive_count) > 1) {
					exec("/usr/bin/tar -xf {$file_dwn_esc} --strip=1 -C {$header_esc} >/dev/null 2>&1");
				} else {
					exec("/usr/bin/tar -xOf {$file_dwn_esc} > {$header_esc}");
				}
				return TRUE;
			}

			pfb_logger('.', 1);

			/* TODO: FIX - Bypass ZIP Compression file mime type validation due to incompatability with ZIP files
			if (!pfb_filter(array("{$file_dwn_esc}", "{$file_download}", "{$list_download}"), PFB_FILTER_FILE_MIME_COMPRESSED, 'pfb_download')) {
				return FALSE;
			}
			*/
	
			// Check if ZIP archive contains xlsx files
			$xlsxtest = exec("/usr/bin/tar -tf {$file_dwn_esc}");
			if (strpos($xlsxtest, '.xlsx') !== FALSE) {
				unlink_if_exists("{$orig_download}");
				exec("{$pfb['script']} xlsx {$header_esc} {$elog}");
				if (file_exists("{$orig_download}")) {
					$retval = 0;
				}
			} else {
				pfb_logger('.', 1);
				// Process ZIP file (SFS and hpHosts workaround)
				exec("/usr/bin/tar -xOf {$file_dwn_esc} | /usr/bin/sed 's/,[[:space:]]/; /g' | /usr/bin/tr ',' '\n' > {$file_org_esc}", $output, $retval);
			}

			// TODO: Validate file contents after extraction (to be removed once ZIP compression file mime type is fixed above)
			if (!pfb_filter(array("{$file_org_esc}", "{$file_download}", "{$list_download}"), PFB_FILTER_FILE_MIME, 'pfb_download')) {
				unlink_if_exists("{$file_org_esc}");
				return FALSE;
			}
		}
		elseif ($file_type == 'application/x-7z-compressed') {
			if (!pfb_filter(array("{$file_dwn_esc}", "{$file_download}", "{$list_download}"), PFB_FILTER_FILE_MIME_COMPRESSED, 'pfb_download')) {
				return FALSE;
			}
			pfb_logger('.', 1);
			exec("/usr/local/bin/7z e -so {$file_dwn_esc} > {$file_org_esc}", $output, $retval);
		}
		else {
			// Uncompressed file format.
			if ($type == 'geoip' || $type == 'asn') {
				// Extras - MaxMind/TOP1M/ASN downloads
				@rename("{$file_download}", "{$head_download}");
				return TRUE;
			}
			elseif ($type == 'blacklist') {
				$retval = 0;
			}
			else {
				// Rename file to 'orig' format
				@rename("{$file_download}", "{$orig_download}");
				$retval = 0;
			}
		}

		if ($retval == 0) {
			// Set downloaded file timestamp to remote timestamp
			if (isset($remote_stamp) && $remote_stamp != -1) {
				@touch("{$orig_download}", $remote_stamp);
			}

			// Process Emerging Threats IQRisk if required
			if (strpos($list_url, 'iprepdata.txt') !== FALSE) {
				exec("{$pfb['script']} et {$header_esc} x x x x x {$pfb['etblock']} {$pfb['etmatch']} {$elog}");
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
		unlink_if_exists("{$file_download}");
	}
	return FALSE;
}


// Determine reason for download failure
function pfb_download_failure($alias, $header, $pfbfolder, $list_url, $format, $vtype) {
	global $pfb;
	$pfbfound = FALSE;

	// Re-evaluate URL
	if ($format == 'whois') {
		if (empty(pfb_filter($list_url, PFB_FILTER_DOMAIN, 'pfb_download_failure'))) {
			pfb_logger("\n Invalid WHOIS. Terminating Download! [ {$list_url} ]\n", 1);
		}
	}
	elseif ($format == 'asn') {
		if (empty(pfb_filter($list_url, PFB_FILTER_ALNUM, 'pfb_download_failure'))) {
			pfb_logger("\n Invalid ASN. Terminating Download! [ {$list_url} ]\n", 1);
		}
	}
	elseif (!pfb_filter($list_url, PFB_FILTER_URL, 'pfb_download_failure')) {
		pfb_logger("\n Invalid URL. Terminating Download! [ {$list_url} ]\n", 1);
	}
	else {
		// Determine if URL is a localfile
		$localfile = FALSE;
		if (pfb_filter($list_url, PFB_FILTER_URL, 'pfb_download_failure', '', TRUE)) {
			$localfile = TRUE;
		}

		// Log FAILED downloads and check if firewall or Snort/Suricata is blocking host
		$log = "\n\n [ {$alias} - {$header} ] Download FAIL [ NOW ]\n";
		pfb_logger("{$log}", 2);

		// Only perform these checks if they are not 'localfiles'
		if ($localfile) {
			$log = "   Local File Failure\n";
			pfb_logger("{$log}", 2);
		}
		else {
			// Determine if Firewall/IPS/DNSBL is blocking download.
			$data = parse_url("{$list_url}");

			$validate_list = array();
			if (is_ipaddr($data['host'])) {
				$validate_list = array(array('type' => 'IP', 'data' => $data['host']));
			} else {
				$validate_list = resolve_host_addresses($data['host']);
			}

			$validate_list_final = array();
			if (!empty($validate_list) && is_array($validate_list)) {
				foreach ($validate_list as $validate) {
					if ($validate['type'] == 'CNAME') {
						$cname_list = resolve_host_addresses($validate['data']);
						if (!empty($cname_list) && is_array($cname_list)) {
							foreach ($cname_list as $cname) {
								if (!empty($cname['data'])) {
									$validate_list_final[$cname['data']] = "Host:{$data['host']} | CNAME:{$cname['host']}";
								}
							}
						}
					}
					elseif (!empty($validate['data'])) {
						$validate_list_final[$validate['data']] = $data['host'];
					}
				}
			}
			else {
				pfb_logger("\n Cannot Resolve Host:{$data['host']}", 1);
			}

			if (!empty($validate_list_final)) {
				foreach ($validate_list_final as $ip => $host) {
					if (is_ipaddr($ip)) {

						// Query Firewall aliastables
						$result = find_reported_header($ip, "{$pfbfolder}/*", FALSE);
						if (!empty($result) && $result[0] != 'Unknown') {
							$log = " [ {$ip} ] Firewall IP block found in: [ {$result[0]} | {$result[1]} ] for HOST:{$host}!\n";
							pfb_logger("{$log}", 2);
							$pfbfound = TRUE;
						}

						// Determine if Host is listed in DNSBL
						if ($ip == $pfb['dnsbl_vip4'] || text_to_compressed_ip6($ip) == text_to_compressed_ip6("{$pfb['dnsbl_vip6']}") || $ip == '0.0.0.0') {
							$log = " [ {$host} ] Domain blocked via DNSBL!\n";
							pfb_logger("{$log}", 2);
							$pfbfound = TRUE;
						}

						// Query Snort/Suricata snort2c IP block table
						$ip_esc = escapeshellarg($ip);
						$result = substr(exec("{$pfb['pfctl']} -t snort2c -T test {$ip_esc} 2>&1"), 0, 1);
						if ($result > 0) {
							$log = " [ {$ip} ] IDS IP block found for HOST:{$host}!\n";
							pfb_logger("{$log}", 2);
							$pfbfound = TRUE;
						}
					}
					else {
						pfb_logger("\nInvalid IP or NXDOMAIN found for HOST:{$host}", 2);
						$pfbfound = TRUE;
					}
				}
			}

			if (!$pfbfound) {
				$log = "  DNSBL, Firewall, and IDS (Legacy mode only) are not blocking download.\n";
				pfb_logger("{$log}", 2);
			}
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
		$today_date = date('m/j/y', time());
		exec("{$pfb['grep']} 'FAIL' {$pfb['errlog']} | {$pfb['grep']} {$today_date}", $results);
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
	global $pfb;

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

	$pfbtracker = '177' . str_pad($pfbtracker, 7, '0', STR_PAD_LEFT);
	if (strlen($pfbtracker) > 10) {
        	$pfbtracker = substr($pfbtracker, 0, 10);
	}
	
	// If duplicate Tracker ID found, pre-define a Tracker ID (Starts at 1700000010)
	if (in_array($pfbtracker, $pfb['trackerids'])) {
		$pfbtracker = ($pfb['last_trackerid'] + 1);
		
		// Increment prefix (digits 1&2) and reset Last_tracker ID after 10 digits
		if (strlen($pfbtracker) > 10) {
			$tracker_prefix = substr($pfbtracker, 0, 2);
			$pfbtracker	= ($tracker_prefix + 1) . '00000010';
		}
		$pfb['last_trackerid'] = $pfbtracker;
	}
	
	$pfb['trackerids'][] = $pfbtracker;
	return (int)$pfbtracker;
}


// Define firewall rule settings
function pfb_firewall_rule($action, $pfb_alias, $vtype, $pfb_log, $agateway_in='default', $agateway_out='default',
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
			$rule['descr'] = "{$pfb_alias}{$pfb['suffix']}";
			if (!empty($asrc_out)) {
				$rule['source'] = array('address' => "{$asrc_out}");
			} else {
				$rule['source'] = array('any' => '');
			}
			if (!empty($asrc_out) && $anot_out == 'on') {
				$rule['source']['not'] = '';
			}
			if (!empty($aports_out)) {
				$rule['destination'] = array('address' => "{$pfb_alias}", 'port' => "{$aports_out}");
			} else {
				$rule['destination'] = array('address' => "{$pfb_alias}");
			}
			if ($aaddrnot_out == 'on') {
				$rule['destination']['not'] = '';
			}
			if (!empty($aproto_out)) {
				$rule['protocol'] = "{$aproto_out}";
			}
			if ($pfb['global_log'] == 'on' || $pfb_log == 'enabled') {
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
			$rule['descr'] = "{$pfb_alias}{$pfb['suffix']}";
			$rule['source'] = array('address' => "{$pfb_alias}");
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
			if ($pfb['global_log'] == 'on' || $pfb_log == 'enabled') {
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
			$rule['descr'] = "{$pfb_alias}{$pfb['suffix']}";
			if (!empty($asrc_out)) {
				$rule['source'] = array('address' => "{$asrc_out}");
			} else {
				$rule['source'] = array('any' => '');
			}
			if (!empty($asrc_out) && $anot_out == 'on') {
				$rule['source']['not'] = '';
			}
			if (!empty($aports_out)) {
				$rule['destination'] = array('address' => "{$pfb_alias}", 'port' => "{$aports_out}");
			} else {
				$rule['destination'] = array('address' => "{$pfb_alias}");
			}
			if ($aaddrnot_out == 'on') {
				$rule['destination']['not'] = '';
			}
			if (!empty($aproto_out)) {
				$rule['protocol'] = "{$aproto_out}";
			}
			if ($pfb['global_log'] == 'on' || $pfb_log == 'enabled') {
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
			$rule['descr'] = "{$pfb_alias}{$pfb['suffix']}";
			$rule['source'] = array('address' => "{$pfb_alias}");
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
			if ($pfb['global_log'] == 'on' || $pfb_log == 'enabled') {
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
			$rule['descr'] = "{$pfb_alias}{$pfb['suffix']}";
			if (!empty($asrc_out)) {
				$rule['source'] = array('address' => "{$asrc_out}");
			} else {
				$rule['source'] = array('any' => '');
			}
			if (!empty($asrc_out) && $anot_out == 'on') {
				$rule['source']['not'] = '';
			}
			if (!empty($aports_out)) {
				$rule['destination'] = array('address' => "{$pfb_alias}", 'port' => "{$aports_out}");
			} else {
				$rule['destination'] = array('address' => "{$pfb_alias}");
			}
			if ($aaddrnot_out == 'on') {
				$rule['destination']['not'] = '';
			}
			if (!empty($aproto_out)) {
				$rule['protocol'] = "{$aproto_out}";
			}
			if ($pfb['global_log'] == 'on' || $pfb_log == 'enabled') {
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
			$rule['descr'] = "{$pfb_alias}{$pfb['suffix']}";
			$rule['source'] = array('address' => "{$pfb_alias}");
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
			if ($pfb['global_log'] == 'on' || $pfb_log == 'enabled') {
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


// Archive IP aliastables and DNSBL database. ( Ramdisk installations only )
function pfb_aliastables($mode) {
	global $g, $pfb;

	$msg = '';
	$earlyshellcmd = '/usr/local/pkg/pfblockerng/pfblockerng.sh aliastables';

	// Reload config.xml to get any recent changes
	config_read_file(false, true);

	$a_earlyshellcmd = config_get_path('system/earlyshellcmd', []);
	$a_shellcmdsettings = config_get_path('installedpackages/shellcmdsettings/config', []);

	// Only execute function if Ramdisks are used.
	if (config_path_enabled('system', 'use_mfs_tmpvar')) {

		// Archive aliastable folder
		if ($mode == 'update') {
			pfb_logger("\nArchiving Aliastable folder", 1);

			$files_to_backup = '';
			$files = glob("{{$pfb['aliasdir']}/pfB_*.txt,{$pfb['dnsbl_file']}.conf,/var/unbound/pfb_unbound*,/var/unbound/pfb_py_*}", GLOB_BRACE);
			if (!empty($files)) {
				$files_to_backup = implode(' ', array_map('escapeshellarg', array_filter($files)));
			}

			// Archive IP Aliastables/Unbound DNSBL Database as required.
			if (!empty($files_to_backup)) {
				exec("/usr/bin/tar -jcvf {$pfb['aliasarchive']} {$files_to_backup} >/dev/null 2>&1");
				pfb_logger("\nArchiving selected pfBlockerNG files.\n", 1);
			} else {
				pfb_logger("\nNo Files to archive.\n", 1);
			}
		}

		// Check conf file for earlyshellcmd/shellcmd package settings
		elseif ($mode == 'conf') {

			// Add earlyshellcmd settings
			if (!empty($a_earlyshellcmd)) {
				if (!preg_grep("/pfblockerng.sh aliastables/", $a_earlyshellcmd)) {
					$a_earlyshellcmd[] = "{$earlyshellcmd}";
					$msg = "\n** Adding earlyshellcmd settings **\n";
				}
			}
			else {
				$a_earlyshellcmd = "{$earlyshellcmd}";
				$msg = "\n** Adding earlyshellcmd settings **\n";
			}

			// Add shellcmd package settings
			$found = FALSE;
			if (!empty($a_shellcmdsettings)) {
				foreach ($a_shellcmdsettings as $key => $shellcmd) {
					if (strpos($shellcmd['cmd'], 'pfblockerng.sh aliastables') !== FALSE) {
						$found = TRUE;
						break;
					}
				}
			}

			if (!$found) {
				$add = array(	'cmd' 		=> $earlyshellcmd,
						'cmdtype'	=> 'earlyshellcmd',
						'description'	=> 'pfBlockerNG earlyshellcmd. DO NOT EDIT/DELETE!');

				$a_shellcmdsettings[] = $add;
				$msg .= "\n** Adding shellcmd package settings **\n";
			}
		}
	}
	else {
		// Remove aliastables archive if found
		if (file_exists("{$pfb['aliasarchive']}")) {
			unlink_if_exists("{$pfb['aliasarchive']}");
		}

		// Remove earlyshellcmd settings
		if (!empty($a_earlyshellcmd)) {
			if (preg_grep("/pfblockerng.sh aliastables/", $a_earlyshellcmd)) {
				$a_earlyshellcmd = preg_grep("/pfblockerng.sh aliastables/", $a_earlyshellcmd, PREG_GREP_INVERT);
				$msg = "\n** Removing earlyshellcmd settings **\n";
			}
		}

		// Remove shellcmd package settings
		if (!empty($a_shellcmdsettings)) {
			foreach ($a_shellcmdsettings as $key => $shellcmd) {
				if (strpos($shellcmd['cmd'], 'pfblockerng.sh aliastables') !== FALSE) {
					unset($a_shellcmdsettings[$key]);
					$msg .= "\n** Removing shellcmd package settings**\n";
				}
			}
		}
	}

	if (!empty($msg)) {
		pfb_logger("{$msg}", 1);
		config_set_path('system/earlyshellcmd', $a_earlyshellcmd);
		config_set_path('installedpackages/shellcmdsettings/config', $a_shellcmdsettings);
		write_config('pfBlockerNG: saving earlyshellcmd');
	}
}


// Collect pfBlockerNG rule names and Tracker IDs
function pfb_filterrules() {
	global $pfb;

	$rule_list		= array();
	$rule_list['id']	= array();
	$rule_list['other']	= array();
	$rule_list['int']	= array();

	exec("{$pfb['pfctl']} -vvsr 2>&1", $results);
	if (!empty($results)) {
		foreach ($results as $result) {
			if (substr($result, 0, 1) == '@') {

				$type = strstr(ltrim(strstr($result, ' ', FALSE), ' '), ' ', TRUE);
				if (in_array($type, array('block', 'pass', 'match'))) {

					// Since pfSense CE 2.6 and pfSense Plus 22.01, pf rules use an 'ridentifier' string
					if (strrpos($result, 'ridentifier') !== FALSE) {
						$id_begin_delim = 'ridentifier ';
						$id_end_delim = ' ';
					} elseif (strpos($result, '(') !== FALSE && strpos($result, ')') !== FALSE) {
						$id_begin_delim = '(';
						$id_end_delim = ')';
					} else {
						continue;
					}

					// Get the rule ID
					$id_begin_offset	= strpos($result, $id_begin_delim) + strlen($id_begin_delim);
					$id_end_offset		= strpos($result, $id_end_delim, $id_begin_offset);

					if ($id_end_offset !== FALSE) {
						$id_length = $id_end_offset - $id_begin_offset;
					} else {
						$id_length = strlen($result) - $id_begin_offset;
					}
					$id = substr($result, $id_begin_offset, $id_length);
					if (!is_numeric($id)) {
						continue;
					}

					// Add the rule to the list
					if (strpos($result, ' <pfB_') !== FALSE) {
						$descr = ltrim(stristr($result, '<pfb_', FALSE), '<');
						$descr = strstr($descr, ':', TRUE);
						$type  = strstr(ltrim(strstr($result, ' ', FALSE), ' '), ' ', TRUE);
						if ($type == 'match') {
							$type = 'unkn(%u)';
						}
	
						if (!is_array($rule_list[$id])) {
							$rule_list[$id] = array();
						}
	
						$rule_list['id'][]	= $id;
						$rule_list[$id]['name']	= $descr;
						$rule_list[$id]['type']	= $type;
	
						$int = strstr(ltrim(strstr($result, ' on ', FALSE), ' on '), ' ', TRUE);
						if (!empty($int)) {
							 $rule_list['int'][$int] = '';
						}
					}
					else {
						// All other non-pfBlockerNG Tracker IDs
						$rule_list['other'][$id] = '';
					}
				}
			}
		}
	}
	return $rule_list;
}


// Function to remove existing firewall states for IPs that are have been recently added to IP block/reject aliastables
function pfb_remove_states() {
	global $pfb;

	$log = "\n===[  Kill States  ]==================================================\n";
	pfb_logger("{$log}", 1);

	$pfb_tables = array();
	// Collect all 'pfB_' and 'pfb_' rules that are 'Block/Reject' and do not have bypass states enabled
	foreach (config_get_path('aliases/alias', []) as $alias) {
		if ($alias['type'] == 'urltable' && strpos($alias['name'], 'pfB_') !== FALSE && strpos($alias['descr'], '[s]') === FALSE) {
			foreach (config_get_path('filter/rule', []) as $rule) {
				if ($alias['name'] === $rule['source']['address'] || $alias['name'] === $rule['destination']['address']) {

					if ($rule['type'] == 'block' ||
						$rule['type'] == 'reject' ||
						strpos($rule['descr'], '[ks]') !== FALSE) {

						if (isset($rule['source']['address']) && !isset($rule['source']['not'])) {
							$pfb_tables[]	= $rule['source']['address'];
						}
						elseif (isset($rule['destination']['address']) && !isset($rule['destination']['not'])) {
							$pfb_tables[]	= $rule['destination']['address'];
						}
					}
				}
			}
		}
	}
	$pfb_tables = array_unique($pfb_tables);

	// List of IPs to suppress
	$pfb_local = $pfb_localsub = array();

	// Collect IPv4 Suppression list IPs
	$v4suppression = pfbng_text_area_decode($pfb['ipconfig']['v4suppression'], TRUE, FALSE);
	foreach ($v4suppression as $line) {
		if (strpos($line, '/32') != FALSE) {
			$pfb_local[] = str_replace('/32', '', $line);
		}

		// Convert '/24' CIDRs
		$pfb_suppcidr = array();
		if (strpos($line, '/24') !== FALSE && is_ipaddrv4($line)) {
			$pfb_suppcidr	= subnetv4_expand($line);
			$pfb_local	= array_merge($pfb_local, $pfb_suppcidr);
		}
	}

	if (!empty($pfb_local)) {
		$pfb_local = array_flip($pfb_local);
	}

	// Collect Interface list that have pfB Rules assigned
	$data		= pfb_filterrules();
	$pfb_int	= $data['int'] ?: array();

	// Collect local IPs
	$data = pfb_collect_localip();
	if (!empty($data[0])) {
		$pfb_local = array_merge($pfb_local, $data[0]);
	}
	$pfb_localsub = $data[1] ?: array();

	// Collect DNS servers to suppress
	$pfb_dnsservers = get_dns_servers();
	if (!empty($pfb_dnsservers)) {
		$pfb_local = array_merge($pfb_local, $pfb_dnsservers);
	}

	// Remove any duplicate IPs
	if (!empty($pfb_local)) {
		$pfb_local = array_flip(array_unique($pfb_local));
	}

	// Collect any 'Permit' Customlist IPs to suppress
	$custom_supp = array();
	foreach (array('pfblockernglistsv4', 'pfblockernglistsv6') as $ip_type) {
		foreach (config_get_path("installedpackages{$ip_type}/config", []) as $list) {
			if (!empty($list['custom']) && strpos($list['action'], 'Permit_') !== FALSE) {
				$custom		= pfbng_text_area_decode($list['custom'], TRUE, FALSE);
				$custom_supp	= array_merge($custom_supp, $custom);
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

	// Collect firewall states and save to temp file
	exec("{$pfb['pfctl']} -s state > {$pfb['states_tmp']} 2>&1");

	$state_count = $all_states = 0;
	$states		= array();
	$states[4]	= array();
	$states[6]	= array();

	if (($s_handle = @fopen("{$pfb['states_tmp']}", 'r')) !== FALSE) {
		while (($sline = @fgets($s_handle)) !== FALSE) {

			$all_states++;

			// SAMPLE : em0 udp 93.15.36.22:6881 -> 192.168.0.3:681		MULTIPLE:MULTIPLE
			// SAMPLE : pppoe0 udp 35.170.3.40:57197 (192.168.0.45:681) -> 22.41.123.206:1001	MULTIPLE:MULTIPLE
			// SAMPLE : em0 tcp 2001:65c:1398:101:124[443] <- 2001:170:2f:3e:a4c4:7b23:fe5f:b36e[52725]	FIN_WAIT_2:FIN_WAIT_2

			if (!empty($sline)) {

				$detail	= array_filter(explode(' ', $sline));

				// Validate states for pfB Interfaces only
				if (!isset($pfb_int[$detail[0]])) {
					continue;
				}

				$count	= count($detail);
				if ($count == 6) {
					$orig_s_ip = $detail[2];
				}
				elseif ($count == 7) {
					$orig_s_ip = $detail[5];
				}
				else {
					continue; // Unknown state line
				}
			}
			else {
				continue;
			}

			$ip_version = 4;

			// Strip IPv6 port
			if (strpos($orig_s_ip, '[') !== FALSE) {
				list($s_ip, $s_port)  = explode('[', $orig_s_ip);
				$ip_version = 6;
			}

			// Strip IPv4 port
			elseif (strpos($orig_s_ip, ':') !== FALSE && substr_count($orig_s_ip, ':') == 1) {
				list($s_ip, $s_port)  = explode(':', $orig_s_ip);
				$ip_version = 4;
			}

			// No port listed
			else {
				$s_ip	= $orig_s_ip;
				$s_port = '';

				if (is_ipaddrv6($s_ip)) {
					$ip_version = 6;
				}
			}

			// Exclude local and reserved IPs (Validate unique IPs only once)
			if (!isset($states[$ip_version][$s_ip])) {

				if ($ip_version == 4) {
					if (isset($pfb_local[$s_ip]) ||
					    pfb_local_ip($s_ip, $pfb_localsub) ||
					    is_private_ip($s_ip) ||
					    substr($s_ip, 0, 2) == '0.' ||
					    substr($s_ip, 0, 4) == '127.' ||
					    substr($s_ip, 0, 3) >= 224) {
						continue;
					}
				}
				else {
					if (isset($pfb_local[$s_ip]) ||
					    pfb_local_ip($s_ip, $pfb_localsub) ||
					    !filter_var($s_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ||
					    !filter_var($s_ip, FILTER_CALLBACK, array('options' => 'FILTER_FLAG_NO_LOOPBACK_RANGE'))) {
						continue;
					}
				}

				// Exclude any 'Permit' Customlist IPs
				foreach ($custom_supp as $custom) {
					if (ip_in_subnet($s_ip, $custom)) {
						continue;
					}
				}
			}

			$state = rtrim($sline, "\x00..\x1F");
			$state_count++;

			// Collect IP for state removal verification
			if (!is_array($states[$ip_version][$s_ip])) {
				$states[$ip_version][$s_ip] = array();
			}

			if (!in_array($state, $states[$ip_version][$s_ip])) {
				$states[$ip_version][$s_ip][] = $state;
			}
		}
	}
	else {
		pfb_logger("\n No Firewall States found", 1);
	}

	if ($s_handle) {
		@fclose($s_handle);
	}
	unlink_if_exists("{$pfb['states_tmp']}");
	unset($pfb_local, $pfb_localsub, $custom_supp);

	$pfbfound = FALSE;
	foreach ($states as $ip_version => $details) {

		if (!empty($details)) {
			$log = "\nFirewall state(s) validation for [ " . count($details) . " ] IPv{$ip_version} address(es)...";
			pfb_logger("{$log}", 1);
			ksort($details, SORT_NATURAL);
		}

		foreach ($details as $s_ip => $state) {
			foreach ($pfb_tables as $s_table) {

				// Compare IP version and aliastable type
				if ($ip_version == 4 && strpos($s_table, '_v4') !== FALSE ||
				    $ip_version == 6 && strpos($s_table, '_v6') !== FALSE) {

					$s_table_esc	= escapeshellarg($s_table);
					$s_ip_esc	= escapeshellarg($s_ip);
					$result = substr(exec("{$pfb['pfctl']} -t {$s_table_esc} -T test {$s_ip_esc} 2>&1"), 0, 1);
					if ($result > 0) {

						$pfbfound = TRUE;
						$log = "\n\n\t[ {$s_table} ] Removed " . count($state) . " state(s) for [ {$s_ip} ]\n\n";
						pfb_logger("{$log}", 1);

						foreach ($state as $line) {
							pfb_logger("\t\t{$line}\n", 1);
						}

						// Kill all state entries originating from $s_ip
						exec("{$pfb['pfctl']} -k {$s_ip_esc} 2>&1");

						// Kill all state entries to the target $s_ip
						exec("{$pfb['pfctl']} -k 0.0.0.0/0 -k {$s_ip_esc} 2>&1");

						break;
					}
				}
			}
		}
	}
	unset($states, $pfb_tables);

	if ($pfbfound) {
		pfb_logger("\n======================================================================\n", 1);
	} else {
		pfb_logger("\nNo matching states found\n\n======================================================================\n", 1);
	}
}


// For subnet addresses - Determine if alert host 'dest' is within a local IP range.
function pfb_local_ip($subnet, $pfb_localsub) {

	if (!empty($pfb_localsub)) {
		foreach ($pfb_localsub as $line) {
			if (ip_in_subnet($subnet, $line)) {
				return TRUE;
			}
		}
	}
	return FALSE;
}


// Collect local IP addresses
function pfb_collect_localip() {
	$pfb_local = $pfb_localsub = array();

	// Collect gateway IP addresses for inbound/outbound list matching
	$int_gateway = get_interfaces_with_gateway();
	if (isset($int_gateway)) {
		foreach ($int_gateway as $gateway) {
			$pfb_local[] = get_interface_ip($gateway) ?: 'Disabled';
		}
	}

	// Collect virtual IP aliases for inbound/outbound list matching
	foreach (config_get_path('virtualip/vip', []) as $list) {
		if (!empty($list['subnet']) && !empty($list['subnet_bits']) && is_subnet("{$list['subnet']}/{$list['subnet_bits']}")) {
			if (is_ipaddrv4($list['subnet'])) {
				if ($list['subnet_bits'] >= 24) {
					$pfb_local	= array_merge(subnetv4_expand("{$list['subnet']}/{$list['subnet_bits']}"), $pfb_local);
				} else {
					$pfb_localsub[]	= "{$list['subnet']}/{$list['subnet_bits']}";
				}
			}
			elseif (is_ipaddrv6($list['subnet'])) {
				$pfb_localsub[] = gen_subnetv6("{$list['subnet']}", "{$list['subnet_bits']}");
			}
		}
		elseif (is_ipaddr($list['subnet'])) {
			$pfb_local[] = "{$list['subnet']}";
		}
	}

	// Collect NAT IP addresses for inbound/outbound list matching
	foreach (config_get_path('nat/rule', []) as $natent) {
		$pfb_local[] = $natent['target'];
	}

	// Collect 1:1 NAT IP addresses for inbound/outbound list matching
	foreach (config_get_path('nat/onetoone', []) as $onetoone) {
		$pfb_local[] = $onetoone['source']['address'];
	}

	// Convert any 'Firewall Aliases' to IP address format
	$aliases = config_get_path('aliases/alias', []);
	if (is_array($aliases)) {
		for ($cnt = 0; $cnt <= count($pfb_local); $cnt++) {
			foreach ($aliases as $i=> $alias) {
				if (isset($alias['name']) && isset($pfb_local[$cnt])) {
					if ($alias['name'] == $pfb_local[$cnt]) {
						$pfb_local[$cnt] = $alias['address'];
					}
				}
			}
		}
	}

	// Collect all interface addresses for inbound/outbound list matching
	foreach (config_get_path('interfaces', []) as $int) {
		if ($int['ipaddr'] != 'dhcp') {
			if (!empty($int['ipaddr']) && !empty($int['subnet']) && is_subnet("{$int['ipaddr']}/{$int['subnet']}")) {
				if (is_ipaddrv4($int['ipaddr'])) {
					if ($int['subnet'] >= 24) {
						$pfb_local	= array_merge(subnetv4_expand("{$int['ipaddr']}/{$int['subnet']}"), $pfb_local);
					} else {
						$pfb_localsub[]	= "{$int['ipaddr']}/{$int['subnet']}";
					}
				}
				elseif (is_ipaddrv6($int['ipaddr'])) {
					$pfb_localsub[] = gen_subnetv6("{$int['ipaddr']}", "{$int['subnet']}");
				}
			}
			elseif (is_ipaddr($int['ipaddr'])) {
				$pfb_local[] = "{$int['ipaddr']}";
			}
		}
	}

	// Remove any duplicate IPs
	if (!empty($pfb_local)) {
		$pfb_local = array_flip(array_filter(array_unique($pfb_local)));
	}
	$pfb_localsub = array_unique($pfb_localsub);

	return array($pfb_local, $pfb_localsub);
}


// Collect local hostnames
function pfb_collect_localhosts() {
	global $g, $pfb;

	// Collect DHCP hostnames/IPs
	$local_hosts = array();

	// Collect configured pfSense interfaces
	$pf_int = get_configured_ip_addresses();
	if (isset($pf_int)) {
		$local_hosts = array_merge($local_hosts, array_flip(array_filter($pf_int)));
	}
	$pf_int = get_configured_ipv6_addresses();
	if (isset($pf_int)) {
		$local_hosts = array_merge($local_hosts, array_flip(array_filter($pf_int)));
	}

	// Collect dynamic DHCP hostnames/IPs
	if (dhcp_is_backend('isc')) {

		// DHCP ISC
		$leasesfile = "{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases";

		$end = $hostname = '';
		if (file_exists("{$leasesfile}")) {
			if (($l_handle = @fopen("{$leasesfile}", 'r')) !== FALSE) {
				while (($line = @fgets($l_handle)) !== FALSE) {
					if (strpos($line, '{') !== FALSE) {
						$end = FALSE;
						$data = explode(' ', $line);
						$ip = $data[1];
					}
					if (strpos($line, 'client-hostname') !== FALSE) {
						$data = explode(' ', $line);
						$hostname = trim(str_replace(array('"', ';'), '', $data[3]));
					}
					if (strpos($line, '}') !== FALSE) {
						$end = TRUE;
					}
					if ($end) {
						if (!empty($ip)) {
							$ip = pfb_filter($ip, PFB_FILTER_IP, 'Collect dynamic DHCP hostnames');
						}

						if (!empty($hostname)) {
							$hostname = pfb_filter($hostname, PFB_FILTER_HOSTNAME, 'Collect dynamic DHCP hostnames');
						}

						if (!empty($ip) && !empty($hostname)) {
							$local_hosts[$ip] = $hostname;
						}
						$ip = $hostname = '';
					}
				}
			}
		}
	}
	else {

		// DHCP KEA
		$leases = system_get_kea4leases();
		if (isset($leases) && is_array($leases['lease'])) {
			foreach ($leases['lease'] as $lease) {
				if (!empty($lease)) {
					$ip		= pfb_filter($lease['ip'], PFB_FILTER_IP, 'Collect dynamic DHCP hostnames');
					$hostname	= pfb_filter($lease['hostname'], PFB_FILTER_HOSTNAME, 'Collect dynamic DHCP hostnames');

					if (!empty($ip) && !empty($hostname)) {
						$local_hosts[$ip] = $hostname;
					}
				}
			}
		}
	}

	// Collect static DHCP hostnames/IPs
	foreach (config_get_path('dhcpd', []) as $dhcp) {
		if (isset($dhcp['staticmap']) && is_array($dhcp['staticmap'])) {
			foreach ($dhcp['staticmap'] as $smap) {
				$local_hosts[$smap['ipaddr']] = strtolower("{$smap['hostname']}");
			}
		}
	}

	// Collect static DHCPv6 hostnames/IPs
	foreach (config_get_path('dhcpdv6', []) as $dhcpv6) {
		if (isset($dhcpv6['staticmap']) && is_array($dhcpv6['staticmap'])) {
			foreach ($dhcpv6['staticmap'] as $smap) {
				$local_hosts[$smap['ipaddrv6']] = strtolower("{$smap['hostname']}");
			}
		}
	}

	// Collect Unbound Host overrides
	$hosts = config_get_path('unbound/hosts', []);
	foreach ($hosts as $host) {
		$local_hosts[$host['ip']] = strtolower("{$host['descr']}");
	}

	// Collect NAT IP addresses by Target:Port
	foreach (config_get_path('nat/rule', []) as $natent) {
		$local_hosts["{$natent['target']}:{$natent['local-port']}"] = strtolower("{$natent['descr']}");
	}

	// Collect virtual IP aliases
	foreach (config_get_path('virtualip/vip', []) as $list) {
		if (!empty($list['subnet']) && !empty($list['subnet_bits'])) {
	
			// Use pfSense hostname for DNSBL vip
			if ($list['subnet'] == $pfb['dnsbl_vip4']) {
				$list['descr'] = config_get_path('system/hostname', 'pfSense') . '.' . (config_get_path('system/domain', 'localdomain'));
			}
			$local_hosts[$list['subnet']] = strtolower("{$list['descr']}");
		}
	}

	// Add localhost hostname
	if (!isset($local_hosts['127.0.0.1'])) {
		$local_hosts['127.0.0.1'] = strtolower((config_get_path('system/hostname', 'pfSense')) . '.' . (config_get_path('system/domain', 'localdomain')));
	}

	return $local_hosts;
}


// Firewall filter.log parser daemon
function pfb_daemon_filterlog() {
	global $pfb;

	// ASN Reporting - cached setting
	if ($pfb['asn_reporting'] != 'disabled') {
		switch ($pfb['asn_reporting']) {
			case '1hour':
				$asn_cache = '-1 hour';
				break;
			case '4hour':
				$asn_cache = '-4 hours';
				break;
			case '12hour':
				$asn_cache = '-12 hours';
				break;
			case '24hour':
				$asn_cache = '-24 hours';
				break;
			case 'week':
				$asn_cache = '-1 week';
				break;
			default:
				$asn_cache = '-24 hours';
		}
	}

	// Application paths
	if (file_exists('/usr/local/bin/mmdblookup') && file_exists("{$pfb['geoipshare']}/GeoLite2-Country.mmdb")) {
		$pathgeoip = "/usr/local/bin/mmdblookup -f {$pfb['geoipshare']}/GeoLite2-Country.mmdb -i";
	} else {
		$pathgeoip = '';
	}

	// Proofpoint ET IQRisk header name reference
	$et_header	= config_get_path('installedpackages/pfblockerngreputation/config/0/et_header', '');
	$et_enabled = TRUE;
	if (empty($et_header)) {
		$et_enabled = FALSE;
	}

	$p_entry 	= '';
	$line_cnt	= 0;
	$rule_list	= $rule_list['other'] = array();

	if (file_exists('/usr/local/sbin/clog_pfb')) {

		// Parse full filter.log on first run, otherwise only parse new filter.log events
		if (!file_exists($pfb['ip_blocklog']) && !file_exists($pfb['ip_permitlog']) && !file_exists($pfb['ip_matchlog'])) {
			$filter_cnt = 0;
		} else {
			$filter_cnt = max( exec("{$pfb['grep']} -c ^ /var/log/filter.log 2>&1") -1, 0) ?: 0;
		}
		$skip_cnt = $filter_cnt;
	}
	else {
		$skip_cnt = $filter_cnt = 0;
	}

	/* filter.log reference: URL: https://docs.netgate.com/pfsense/en/latest/monitoring/logs/raw-filter-format.html

		$line -> $f

		[ BSD format ]	[ syslog format ]
		[0][1][2]	[1]			= Date/timestamp
		[5]		[7]			= Event Details

		$f[5] -> $d

		[0]	= Rule number
		[1]	= Sub-rule number
		[2]	= Anchor
		[3]	= Tracker ID
		[4]	= Real Interface
		[5]	= Reason
		[6]	= Action
		[7]	= Direction
		[8]	= IP version
		[9]	= IP Specific data

		IPv4	IPv6
		[10]	[]	=
		[11]	[]	=
		[12]	[]	=
		[13]	[]	=
		[14]	[]	=
		[15]	[13]	= Protocol ID
		[16]	[12]	= Protocol
		[17]	[]	=
		[18]	[15]	= SRC IP
		[19]	[16]	= DST IP
		[20]	[17]	= SRC Port
		[21]	[18]	= DST Port
		[22]	[]	=
		[23]	[20]	= TCP Protocol Flags

	Final output reference:
		[0]	= Date/Timestamp
		[1]	= Rulenum
		[2]	= Real Interface
		[3]	= Friendly Interface name
		[4]	= Action
		[5]	= Version
		[6]	= Protocol ID
		[7]	= Protocol
		[8]	= SRC IP
		[9]	= DST IP
		[10]	= SRC Port
		[11]	= DST Port
		[12]	= Direction
		[13]	= GeoIP code
		[14]	= IP Alias Name
		[15]	= IP evaluated
		[16]	= Feed Name
		[17]	= gethostbyaddr resolved hostname
		[18]	= Client Hostname
		[19]	= Duplicate ID indicator		*/

	// Disable pfctl Tracker ID lookup on too many lookup failures
	$max_retries = 0;
	
	if (($s_handle = @fopen('php://stdin', 'r')) !== FALSE) {
		logger(LOG_NOTICE, localize_text('filterlog daemon started'), LOG_PREFIX_PKG_PFBLOCKERNG);
		while (!feof($s_handle)) {
			$line = @fgets($s_handle);

			// Only parse new filter events
			if ($filter_cnt > 0 && $skip_cnt > 0) {
				$skip_cnt--;
				continue;
			}

			$log_type = 'BSD';
			$f_pos = 5;
			if (substr($line, 0, 1) == '<') {
				$log_type = 'syslog';
				$f_pos = 7;
			}

			// Remove any '^M' characters
			$line = htmlspecialchars(rtrim($line, "\x00..\x1F"));

			$f = explode(' ', $line);

			// Remove double space for single date entry nuance
			if ($log_type == 'BSD' && empty($f[1])) {
				array_splice($f, 1, 1);
			}
			$d = explode(',', $f[$f_pos]);

			// Attempt to find the Tracker ID, if not found wait 5secs for filter_reload
			$other = FALSE;
			if (!empty($d[3])) {
				for ($retries = 1; $retries <= 5; $retries++) {

					// Skip known non-pfBlockerNG Tracker IDs
					if (isset($rule_list['other'][$d[3]])) {
						$other = TRUE;
						break;
					}

					// Break on pfBlockerNG rule Tracker ID and Rule type (block|permit|match)
					// If the user switched a manual rule from one type to another, the Tracker ID will stay the same
					// So this comparison will refresh the data on changes
					if (isset($rule_list[$d[3]]) && $rule_list[$d[3]]['type'] == $d[6]) {
						break;
					}

					// Collect updated pfctl Rule data, Host and local IP data
					else {
						$rule_list	= pfb_filterrules();
						$data		= pfb_collect_localip();
						$pfb_local	= $data[0] ?: array();
						$pfb_localsub	= $data[1] ?: array();

						$local_hosts	= pfb_collect_localhosts();
					}

					if ($retries < 5 && $max_retries < 30) {
						$max_retries++;
						sleep(5);
					} else {
						$other = TRUE;
					}
				}

				if ($other) {
					continue;
				}

				// Duplicate entry comparison: "Tracker ID/Action/SRC IP/DST IP/DST Port"
				$dup_entry = '+';
				if ($d[8] == 4 && "{$d[3]}{$d[6]}{$d[18]}{$d[19]}{$d[21]}" == $p_entry) {
					$dup_entry = '-';
				} elseif ($d[8] == 6 && "{$d[3]}{$d[6]}{$d[15]}{$d[16]}{$d[18]}" == $p_entry) {
					$dup_entry = '-';
				}

				if ($dup_entry == '+') {

					$int		= convert_real_interface_to_friendly_descr($d[4]);
					$pfb_alias	= pfb_filter($rule_list[$d[3]]['name'], PFB_FILTER_WORD, 'pfb_daemon_filterlog', 'Unknown');

					// Action setting variables
					if ($d[6] == 'block') {
						$folder = "{$pfb['denydir']}/* {$pfb['nativedir']}/*";
						$iplog	= "{$pfb['ip_blocklog']}";
						$l_type = 'Block';
					}
					elseif ($d[6] == 'pass') {
						$folder = "{$pfb['permitdir']}/* {$pfb['nativedir']}/*";
						$iplog	= "{$pfb['ip_permitlog']}";
						$l_type = 'Permit';
					}
					elseif ($d[6] == 'unkn(%u)') {
						$d[6]	= 'match';
						$folder = "{$pfb['matchdir']}/* {$pfb['nativedir']}/*";
						$iplog	= "{$pfb['ip_matchlog']}";
						$l_type = 'Match';
					}

					if ($d[8] == 4) {
						$srcip		= $d[18] ?: 'Unknown';
						$dstip		= $d[19] ?: 'Unknown';
						$tcp_flags	= $d[16] == 'tcp' ? $d[23] : '';	// Keep protocol flags for TCP only
					} else {
						$srcip		= $d[15] ?: 'Unknown';
						$dstip		= $d[16] ?: 'Unknown';
						$tcp_flags	= $d[12] == 'tcp' ? $d[20] : '';
					}

					// Determine if DST IP or SRC IP is the external host
					if (isset($pfb_local[$dstip]) || pfb_local_ip($dstip, $pfb_localsub)) {
						$dir	= 'in';
						$host	= $srcip;
						$client	= $dstip;
						$port	= $d[21];
					} else {
						$dir	= 'out';
						$host	= $dstip;
						$client	= $srcip;
						$port	= $d[20];
					}

					if (!is_ipaddr($host)) {
						continue;
					}
					if (!is_ipaddr($client)) {
						continue;
					}
					if (!is_port($port)) {
						$port = '';
					}

					switch (TRUE) {
						case isset($local_hosts["{$client}:{$port}"]) && !empty($local_hosts["{$client}:{$port}"]):
							$hostname = $local_hosts["{$client}:{$port}"];
							break;
						case isset($local_hosts[$client]) && !empty($local_hosts[$client]):
							$hostname = $local_hosts[$client];
							break;
						default:
							$hostname = 'Unknown';
					}

					$ip_cache = FALSE;
					$db_handle = pfb_open_sqlite(7, 'Query ip cache');
					if ($db_handle) {
						$db_update = "SELECT * FROM ipcache WHERE host = :host;";
						$stmt = $db_handle->prepare($db_update);
						if ($stmt) {
							$stmt->bindValue(':host', $host, SQLITE3_TEXT);
							$result = $stmt->execute();
							if ($result) {
								$ip_cache = $result->fetchArray(SQLITE3_ASSOC);
							}
						}
					}
					pfb_close_sqlite($db_handle);

					if (!$ip_cache) {

						// Find the header which alerted this host
						$geoip_folder = FALSE;
						$geoip_validate = substr($pfb_alias, 0, -3);
						if (isset($pfb['continent_list'][$geoip_validate])) {
							$geoip_folder = TRUE;
						}
						$pfb_query = find_reported_header($host, $folder, $geoip_folder);

						// Report specific ET IQRisk details
						if ($et_enabled && strpos($pfb_query[0], "{$et_header}") !== FALSE) {
							$ET_orig = $pfb_query;
							$pfb_query = find_reported_header($host, "{$pfb['etdir']}/*", FALSE);

							// ET IQRisk category is unknown.
							if ($pfb_query[1] == 'Unknown') {
								$pfb_query = $ET_orig;
							}
							else {
								// Prepend ET Header name
								$pfb_query[0] = "{$et_header}:{$pfb_query[0]}";
							}
						}

						// Determine GeoIP isocode of host
						if (!empty($pathgeoip)) {
							$geoip = exec("{$pathgeoip} " . escapeshellarg($host) . " country iso_code | grep -v '^\$' | cut -d '\"' -f2 2>&1");
							$geoip = pfb_filter($geoip, PFB_FILTER_ALPHA, 'pfb_daemon_filterlog', 'Unk');
						}
						else {
							$geoip = 'Unk';
						}

						$resolved_host = gethostbyaddr($host) ?: 'Unknown';
						if ($host == $resolved_host || $resolved_host == 'Unknown') {
							$resolved_host = 'Unknown';
						}

						// Save entry to IP cache
						$db_update = "INSERT into ipcache ( host, q0, q1, geoip, resolved_host ) VALUES ( :host, :q0, :q1, :geoip, :resolved_host )";
						$db_handle = pfb_open_sqlite(7, 'Add to IP cache');
						if ($db_handle) {
							$pfb_host	= pfb_filter($host, PFB_FILTER_HTML, 'pfb_daemon_filterlog');
							$pfb_query[0]	= pfb_filter($pfb_query[0], PFB_FILTER_HTML, 'pfb_daemon_filterlog');
							$pfb_query[1]	= pfb_filter($pfb_query[1], PFB_FILTER_HTML, 'pfb_daemon_filterlog');
							$geoip		= pfb_filter($geoip, PFB_FILTER_HTML, 'pfb_daemon_filterlog');
							$resolved_host	= pfb_filter($resolved_host, PFB_FILTER_HOSTNAME, 'pfb_daemon_filterlog', 'Unknown');

							$stmt = $db_handle->prepare($db_update);
							if ($stmt) {
								$stmt->bindValue(':host', $host, SQLITE3_TEXT);
								$stmt->bindValue(':q0', $pfb_query[0], SQLITE3_TEXT);
								$stmt->bindValue(':q1', $pfb_query[1], SQLITE3_TEXT);
								$stmt->bindValue(':geoip', $geoip, SQLITE3_TEXT);
								$stmt->bindValue(':resolved_host', $resolved_host, SQLITE3_TEXT);
								$stmt->execute();
							}
						}
						pfb_close_sqlite($db_handle);
					}

					// Use cached entries
					else {
						$host		= htmlspecialchars($ip_cache['host'])		?: 'Unknown';
						$pfb_query	= array();
						$pfb_query[0]	= htmlspecialchars($ip_cache['q0'])		?: 'Unknown';
						$pfb_query[1]	= htmlspecialchars($ip_cache['q1'])		?: 'Unknown';
						$geoip		= htmlspecialchars($ip_cache['geoip'])		?: 'Unknown';
						$resolved_host	= htmlspecialchars($ip_cache['resolved_host'])	?: 'Unknown';
					}

					// Modify host for ASN query
					if ($d[8] == 4) {
						// For ASN, query for IP Network address (x.x.x.0)
						$ix = ip_explode($host);
						$ip = "{$ix['6']}0";
					} else {
						// For ASN, query for IP Network address with /48 subnet
						$ip = gen_subnetv6($host, 48);
					}
					$ip = pfb_filter($ip, PFB_FILTER_IP, 'pfb_daemon_filterlog');

					// Determine ASN number of host
					$asn = 'null';
					if (($pfb['asn_reporting'] != 'disabled' || !empty($pfb['asn_token'])) && !empty($ip)) {

						$is_asn_cache = FALSE;
						$db_handle = pfb_open_sqlite(5, 'Query ASN cache');
						if ($db_handle) {

							// Clear cached entries older than defined cache setting
							$db_update = "DELETE FROM asncache WHERE timestamp <= datetime('now', 'localtime', :asn_cache)";
							$stmt = $db_handle->prepare($db_update);
							if ($stmt) {
								$stmt->bindValue(':asn_cache', $asn_cache, SQLITE3_TEXT);
								$stmt->execute();
							}

							// Query for cached Host IP
							$db_update = "SELECT * FROM asncache WHERE host = :host;";
							$stmt = $db_handle->prepare($db_update);
							if ($stmt) {
								$stmt->bindValue(':host', $ip, SQLITE3_TEXT);
								$result = $stmt->execute();
								if ($result) {
									$is_asn_cache = $result->fetchArray(SQLITE3_ASSOC);
								}
							}
						}
						pfb_close_sqlite($db_handle);

						// If Host IP is not in ASN cache, collect ASN from ASN database
						if (!$is_asn_cache) {
							$asn = exec("{$pfb['script']} iptoasn " . escapeshellarg($ip) . " 2>&1");

							// Convert any IDN to ASCII
							$asn_final = '';
							if (!ctype_print($asn)) {
								if (strpos($asn, '|') !== FALSE) {
									$asn_ex = explode('|', $asn);
									if (is_array($asn_ex) && !empty($asn_ex)) {
										foreach ($asn_ex as $asn_element) {
											if (strpos($asn_element, ':') !== FALSE) {
												$asn_element_ex = explode(':', $asn_element);
												if (is_array($asn_element_ex) && (is_string($asn_element_ex[1]) && (strlen($asn_element_ex[1]) > 0))) {
													if (!ctype_print($asn_element_ex[1])) {
														$asn_element_ex[1] = mb_convert_encoding($asn_element_ex[1], 'UTF-8',
															mb_detect_encoding($asn_element_ex[1], 'UTF-8, ASCII, ISO-8859-1'));
														$asn_element_ex[1] = idn_to_ascii($asn_element_ex[1]);
													}
													$asn_final .= "{$asn_element_ex[0]}:{$asn_element_ex[1]}|";
												}
											}
										}
									}
								}
							}
							else {
								$asn_final = $asn;
							}
							$asn = pfb_filter($asn_final, PFB_FILTER_HTML, 'pfb_daemon_filterlog - asn');

							if (empty($asn) || strpos($asn, 'ASN:') === FALSE) {
								$asn = 'Unknown';
							}

							// Save entry to ASN cache
							else {
								$db_update = "INSERT into asncache ( asn, host, timestamp ) VALUES ( :asn, :host, datetime('now', 'localtime'))";
								$db_handle = pfb_open_sqlite(5, 'Add to ASN cache');
								if ($db_handle) {
									$stmt	= $db_handle->prepare($db_update);
									if ($stmt) {
										$asn = "|{$asn}";
										$stmt->bindValue(':asn', $asn, SQLITE3_TEXT);
										$stmt->bindValue(':host', $ip, SQLITE3_TEXT);
										$stmt->execute();
									}
								}
								pfb_close_sqlite($db_handle);
							}
						}

						// Use cached ASN Entry
						else {
							$asn = htmlspecialchars($is_asn_cache['asn'])	?: 'Unknown';
						}
					}

					// Log: "Date timestamp/Tracker ID/Interface/Interface Name/Action/IP Version"
					if ($log_type == 'BSD') {
						$log = "{$f[0]} {$f[1]} {$f[2]},{$d[3]},{$d[4]},{$int},{$d[6]},{$d[8]},";
					} else {
						$ts = date('M j H:i:s', strtotime($f[1]));
						$log = "{$ts},{$d[3]},{$d[4]},{$int},{$d[6]},{$d[8]},";
					}

					// Details:	"Direction/GeoIP/Aliasname/IP evaluated/Feed Name/Resolved Hostname/Client Hostname/ASN/Duplicate status"
					$details	= "{$dir},{$geoip},{$pfb_alias},{$pfb_query[1]},{$pfb_query[0]},{$resolved_host},{$hostname},{$asn}";

					// Reverse Match text
					if ($d[6] == 'match') {
						$d[6] = 'unkn(%u)';
					}

					if ($d[8] == 4) {
						// Previous:	"Tracker ID/Action/SRC IP/DST IP/DST Port"
						$p_entry	= "{$d[3]}{$d[6]}{$d[18]}{$d[19]}{$d[21]}";
						$d[16]		= str_replace('TCP', 'TCP-', strtoupper($d[16]), $d[16]) . $tcp_flags;

						// Log:		"Protocol ID/Protocol/SRC IP/DST IP/SRC Port/DST Port"
						$log		.= "{$d[15]},{$d[16]},{$d[18]},{$d[19]},{$d[20]},{$d[21]}";
					}
					else {
						$p_entry	= "{$d[3]}{$d[6]}{$d[15]}{$d[16]}{$d[18]}";
						$d[12]		= str_replace('TCP', 'TCP-', strtoupper($d[12]), $d[12]) . $tcp_flags;
						$log		.= "{$d[13]},{$d[12]},{$d[15]},{$d[16]},{$d[17]},{$d[18]}";
					}
				}
				@file_put_contents("{$iplog}", "{$log},{$details},{$dup_entry}\n", FILE_APPEND | LOCK_EX);

				// Write to Unified Log
				if ($dup_entry == '+') {
					@file_put_contents("{$pfb['unilog']}", "{$l_type},{$log},{$details},{$dup_entry}\n", FILE_APPEND | LOCK_EX);
				}
			}
		}
	}
	else {
		logger(LOG_ERR, localize_text('filterlog - Failed to read STDIN'), LOG_PREFIX_PKG_PFBLOCKERNG);
	}
	if ($s_handle) {
		@fclose($s_handle);
	}
}


// Function to parse grep output and return Aliasname and IP fields
function pfb_parse_query($line) {
	$rx = explode('.txt:', $line);
	$rx[0] = ltrim(strrchr($rx[0], '/'), '/');
	return $rx;
}


// Function to output Alias/Feed name string
function pfb_parse_line($line) {
	$match = strstr($line, ':local', TRUE);
	if (strpos($match, '.txt') !== FALSE) {
		$match = strstr($match, '.txt', TRUE);
	}
	$match = substr($match, strrpos($match, '/') + 1);
	return $match;
}


// Functon to find which DNSBL Feed/Groupname blocked this event
function pfb_dnsbl_parse($mode='daemon', $domain, $src_ip, $req_agent) {
	global $pfb;

	$dnsbl_cache = FALSE;
	$db_handle = pfb_open_sqlite(4, 'Query cache');
	if ($db_handle) {
		$db_update = "SELECT * FROM dnsblcache WHERE domain = :domain;";
		$stmt = $db_handle->prepare($db_update);
		if ($stmt) {
			$stmt->bindValue(':domain', $domain, SQLITE3_TEXT);
			$result = $stmt->execute();
			if ($result) {
				$dnsbl_cache = $result->fetchArray(SQLITE3_ASSOC);
			}
		}
	}
	pfb_close_sqlite($db_handle);

	// If domain is not in DNSBL cache, query for blocked domain details
	if (!$dnsbl_cache) {
		$o_domain = $domain; // Store original domain for CNAME query

		while (TRUE) {

			$domainparse = str_replace('.', '\.', $domain);
			if ($pfb['dnsbl_py_blacklist']) {
				$dquery_esc	= escapeshellarg(",{$domainparse},,");
				$pfb_feed	= exec("{$pfb['grep']} -shm1 {$dquery_esc} {$pfb['unbound_py_data']} 2>&1");
			} else {
				$dquery_esc	= escapeshellarg(" \"{$domainparse} 60");
				$pfb_feed	= pfb_parse_line(exec("{$pfb['grep']} -sHm1 {$dquery_esc} {$pfb['dnsdir']}/*.txt 2>&1"));
			}
			$pfb_group = $pfb_mode = $pfb_final = 'Unknown';

			// Exact Domain match found
			if (!empty($pfb_feed)) {

				if ($pfb['dnsbl_py_blacklist']) {
					list($dummy, $pfb_final, $empty_placeholder, $log_type, $pfb_feed, $pfb_group) = explode(',', $pfb_feed);
					$pfb_mode = 'DNSBL';
				}
				else {
					$pfb_group = pfb_parse_line(exec("{$pfb['grep']} -sHm1 {$dquery_esc} {$pfb['dnsalias']}/* 2>&1"));

					// Determine DNSBL Type
					$pfb_mode = 'DNSBL';
					$ip = gethostbyname("dnsbl.test.{$domain}");
					if ($ip == $pfb['dnsbl_vip4'] || text_to_compressed_ip6($ip) == text_to_compressed_ip6("{$pfb['dnsbl_vip6']}") || $ip == '0.0.0.0') {
						$pfb_mode = 'TLD';
					}
					$pfb_final = $domain;
				}
			}

			else {
				$dparts = explode('.', $domain);
				if (!$pfb['dnsbl_py_blacklist']) {
					unset($dparts[0]);
				}
				$dcnt = count($dparts);

				for ($i=0; $i <= $dcnt; $i++) {

					$domainparse		= str_replace('.', '\.', implode('.', $dparts));
					$domainparse_esc	= escapeshellarg("^{$domainparse}$");
					if (!$pfb['dnsbl_py_blacklist']) {
						$dquery_esc = escapeshellarg(" \"{$domainparse} 60");

						// Determine if TLD exists in TLD Blacklist
						if (file_exists("{$pfb['dnsbl_tld_txt']}")) {
							exec("/usr/bin/grep -l {$domainparse_esc} {$pfb['dnsbl_tld_txt']} 2>&1", $match);
							if (!empty($match[0])) {
								$pfb_group = $pfb_feed = $pfb_mode = 'DNSBL_TLD';
								$pfb_final = $domainparse;
								break;
							}
						}
					}

					if ($pfb['dnsbl_py_blacklist']) {
						$dquery_esc	= escapeshellarg(",{$domainparse},,");
						$pfb_feed	= exec("{$pfb['grep']} -shm1 {$dquery_esc} {$pfb['unbound_py_zone']} 2>&1");

						// Collect Alias Group name
						if (!empty($pfb_feed)) {
							list($dummy, $d_found, $empty_placeholder, $log_type, $pfb_feed, $pfb_group) = explode(',', $pfb_feed);

							$pfb_final = str_replace('\.', '.', $domainparse);
							$pfb_mode = 'TLD';
							if ($pfb_feed == 'DNSBL_TLD') {
								$pfb_mode = 'DNSBL_TLD';
							}
							break;
						}
					}
					else {
						$pfb_feed = pfb_parse_line(exec("{$pfb['grep']} -sHm1 {$dquery_esc} {$pfb['dnsdir']}/*.txt 2>&1"));
					
						// Collect Alias Group name
						if (!empty($pfb_feed)) {
							$pfb_group	= pfb_parse_line(exec("{$pfb['grep']} -sHm1 {$dquery_esc} {$pfb['dnsalias']}/* 2>&1"));
							$pfb_mode	= 'TLD';
							$pfb_final	= str_replace('\.', '.', $domainparse);
							break;
						}
					}
					unset($dparts[$i]);
				}
			}

			// Query for CNAME(s)
			if (empty($pfb_feed)) {
				if (!isset($cnames)) {
					$domain_esc = escapeshellarg($domain);
					$extdns_esc = escapeshellarg("@{$pfb['extdns']}");
					exec("/usr/bin/drill {$domain_esc} {$extdns_esc} | /usr/bin/awk '/CNAME/ {sub(\"\.\$\", \"\", \$5); print \$5;}'", $cnames);
					if (is_array($cnames) && !empty($cnames)) {
						foreach ($cnames as $key => $cname) {
							// Remove invalid CNAMES
							if (empty(pfb_filter($cname, PFB_FILTER_DOMAIN, 'pfb_dnsbl_parse'))) {
								unset($cnames[$key]);
								continue;
							} 
                                                }
						$cname_cnt = count($cnames);
					}
				}
				if (is_array($cnames) && !empty($cnames)) {
					$domain = array_shift($cnames);
				}

				if ($cname_cnt == 0) {
					$domain = $o_domain; // No CNAME match found, revert back to original domain
					break;
				}
				$cname_cnt--;
			}
			else {
				break;
			}
		}
		$pfb_feed = $pfb_feed ?: 'Unknown';

		if (isset($cnames)) {
			unset($cnames);
			if ($pfb_feed != 'Unknown') {
				$pfb_mode = "{$pfb_mode}-CNAME";
			}
		}

		// Save entry to DNSBL cache
		$db_update = "INSERT into dnsblcache ( type, domain, groupname, final, feed ) VALUES ( :type, :domain, :groupname, :final, :feed )";
		$db_handle = pfb_open_sqlite(4, 'Add to DNSBL cache');
		if ($db_handle) {
			$domain		= pfb_filter($domain, PFB_FILTER_HTML, 'pfb_dnsbl_parse');
			$pfb_group	= pfb_filter($pfb_group, PFB_FILTER_HTML, 'pfb_dnsbl_parse');
			$pfb_final	= pfb_filter($pfb_final, PFB_FILTER_HTML, 'pfb_dnsbl_parse');
			$pfb_feed	= pfb_filter($pfb_feed, PFB_FILTER_HTML, 'pfb_dnsbl_parse');

			$stmt = $db_handle->prepare($db_update);
			if ($stmt) {
				$stmt->bindValue(':type', $pfb_mode, SQLITE3_TEXT);
				$stmt->bindValue(':domain', $domain, SQLITE3_TEXT);
				$stmt->bindValue(':groupname', $pfb_group, SQLITE3_TEXT);
				$stmt->bindValue(':final', $pfb_final, SQLITE3_TEXT);
				$stmt->bindValue(':feed', $pfb_feed, SQLITE3_TEXT);
				$stmt->execute();
			}
		}
		pfb_close_sqlite($db_handle);
	}

	// Use cached entries
	else {
		$pfb_mode	= htmlspecialchars($dnsbl_cache['type'])	?: 'Unknown';
		$pfb_group	= htmlspecialchars($dnsbl_cache['groupname'])	?: 'Unknown';
		$pfb_final	= htmlspecialchars($dnsbl_cache['final'])	?: 'Unknown';
		$pfb_feed	= htmlspecialchars($dnsbl_cache['feed'])	?: 'Unknown';
	}

	if ($mode == 'daemon') {
		$details = "{$domain},{$src_ip},{$req_agent},{$pfb_mode},{$pfb_group},{$pfb_final},{$pfb_feed}";
		return array ($pfb_group, $details);
	} else {
		return array ('pfb_mode' => $pfb_mode, 'pfb_group' => $pfb_group, 'pfb_final' => $pfb_final, 'pfb_feed' => $pfb_feed);
	}
}


// DNSBL Lighttpd 'dnsbl_error.log' conditional log parser
function pfb_daemon_dnsbl() {
	global $pfb;

	if (($h_handle = @fopen('php://stdin', 'r')) !== FALSE) {
		logger(LOG_NOTICE, localize_text('DNSBL parser daemon started'), LOG_PREFIX_PKG_PFBLOCKERNG);

		$chk_ver	= FALSE;
		$lighty_47	= FALSE;
		$lighty_58	= FALSE;
		$lighty_59	= FALSE;
		$checkpos	= 3; 	// Pre Lighttpd v1.4.47

		while (!feof($h_handle)) {
			$pfb_buffer = @fgets($h_handle);

			if (!$chk_ver) {

				// Lighttpd v1.4.58+ conditional error log with 'ssl.verifyclient.activate' to collect the domain name
				if (!$lighty_47 && strpos($pfb_buffer, 'lighttpd/1.4.58') !== FALSE) {
					$chk_ver	= TRUE;
					$lighty_58	= TRUE;
					continue;
				}

				// Lighttpd v1.4.59+ syntax changes to IP line
                                elseif (!$lighty_47 &&
				    (strpos($pfb_buffer, 'lighttpd/1.4.59') !== FALSE ||
				    strpos($pfb_buffer, 'lighttpd/1.4.6') !== FALSE ||
				    strpos($pfb_buffer, 'lighttpd/1.4.7') !== FALSE ||
				    strpos($pfb_buffer, 'lighttpd/1.5') !== FALSE)) {
					$chk_ver	= TRUE;
					$lighty_59	= TRUE;
					continue;
				}

				// Lighttpd v1.4.47 uses mod_openssl with a different conditional log formatting
				elseif (strpos($pfb_buffer, 'global/HTTPscheme') !== FALSE) {
					$lighty_47	= TRUE;
					$checkpos	= 1;
					continue;
				}
			}

			if ($lighty_58 || $lighty_59) {

				// Verify HTTP["remoteip"]
				if (empty($src_ip)) {
					if ($lighty_58) {
						if (strpos($pfb_buffer, '["re') !== FALSE) {
							$src_ip = strstr($pfb_buffer, ') compare', TRUE);
							$src_ip = ltrim(strstr($src_ip, '] (', FALSE), '] (');
							$src_ip = (filter_var($src_ip, FILTER_VALIDATE_IP) !== FALSE) ? $src_ip : '';
						}
					} else {
						if (strpos($pfb_buffer, '["re') !== FALSE && strpos($pfb_buffer, ' compare to ') !== FALSE) {
							$src_ip = strstr($pfb_buffer, ' compare to ', FALSE);
							$src_ip = trim(ltrim($src_ip, ' compare to '));
							$src_ip = (filter_var($src_ip, FILTER_VALIDATE_IP) !== FALSE) ? $src_ip : '';
						}
					}
					continue;
				}

				if (!empty($src_ip) && strpos($pfb_buffer, "SSL: ") === FALSE) {
					continue;
				}
				if (empty($domain) && strpos($pfb_buffer, "SSL: can't verify client without ssl.") !== FALSE) {
					$domain = str_replace(' server name ', '', strstr(trim($pfb_buffer), ' server name ', FALSE));
				}

				if (!empty($domain) && !empty($src_ip)) {

					$domain = pfb_filter($domain, PFB_FILTER_DOMAIN, 'pfb_daemon_dnsbl');
					$src_ip = pfb_filter($src_ip, PFB_FILTER_IP, 'pfb_daemon_dnsbl');
					if (!empty($domain) && !empty($src_ip)) {

						// URL/Referer/URI/Agent String not available for HTTPS events
						$req_agent	= 'Unknown';
						$type		= 'DNSBL-HTTPS';

						pfb_log_event($type, $domain, $src_ip, $req_agent);
						$domain = $src_ip = '';
					}
				}
			}
			else {
				// Parse only HTTP["xxx"] log lines
				if (strpos($pfb_buffer, 'HTTP[') === FALSE) {
					continue;
				}

				// Verify only HTTPS lines
				if (strpos($pfb_buffer, '( https') !== FALSE) {
					if ($lighty_47) {
						continue;
					}
					$checkpos = 0;
				}

				// Verify HTTP["remoteip"]
				if ($checkpos == 1 && strpos($pfb_buffer, '["re') !== FALSE) {
					$src_ip = strstr($pfb_buffer, ' ) compare', TRUE);
					$src_ip = ltrim(strstr($src_ip, '] ( ', FALSE), '] ( ');
					$src_ip = (filter_var($src_ip, FILTER_VALIDATE_IP) !== FALSE) ? $src_ip : '';
				}

				// Verify HTTP["host"]
				elseif ($checkpos == 2 && strpos($pfb_buffer, '["ho') !== FALSE) {
					$lighty_47	= FALSE;
					$domain		= strstr($pfb_buffer, ' ) compare', TRUE);
					$domain		= ltrim(strstr($domain, '] ( ', FALSE), '] ( ');

					// URL/Referer/URI/Agent String not available for HTTPS events
					$req_agent	= 'Unknown';
					$type		= 'DNSBL-HTTPS';

					// Log event and Increment SQLite DNSBL Group counter
					if (!empty($domain) && !empty($src_ip)) {

						$domain = pfb_filter($domain, PFB_FILTER_DOMAIN, 'pfb_daemon_dnsbl');
						$src_ip = pfb_filter($src_ip, PFB_FILTER_IP, 'pfb_daemon_dnsbl');
						if (!empty($domain) && !empty($src_ip)) {

							// URL/Referer/URI/Agent String not available for HTTPS events
							$req_agent	= 'Unknown';
							$type		= 'DNSBL-HTTPS';

						 	pfb_log_event($type, $domain, $src_ip, $req_agent);
							$domain = $src_ip = '';
						}
					}
				}
				$checkpos++;
			}
		}
	}
	else {
		logger(LOG_ERR, localize_text('DNSBL conditional log parser - Failed to open handle'), LOG_PREFIX_PKG_PFBLOCKERNG);
	}
	@fclose($h_handle);
}


// DNSBL Lighttpd 'index.php' event parser
function pfb_daemon_dnsbl_index() {
	global $pfb;

	// Replace any [',' or '|' ] in HTTP_REFERER, REQUEST_URI or HTTP_USER_AGENT fields
	// Replace placeholder characters [ '!' and '*' ] to [ ',' and '|' ]
	$p1 = array( ',', '!', '|', ' * ' );
	$p2 = array( '', ',', '--', '|' );

	if (($i_handle = @fopen('php://stdin', 'r')) !== FALSE) {
		while (!feof($i_handle)) {
			$pfb_buffer = @fgets($i_handle);
			$pfb_buffer = str_replace($p1, $p2, $pfb_buffer);

			if (substr($pfb_buffer, 0, 6) == 'INDEX,' && substr_count($pfb_buffer, ',') == 4) {
				$csvline = str_getcsv($pfb_buffer, ',', '', '"');

				// Determine blocked domain type (Full, 1x1 or JS)
				if (isset($csvline[1]) && !empty($csvline[1])) {
					$request = strstr($csvline[1], '/', FALSE);
					$request = strstr($request, ' ', TRUE);

					if (strlen($request) < 2) {
						$type = 'DNSBL-Full';
					}
					else {
						if (pathinfo($request, PATHINFO_EXTENSION) == 'js') {
							$type = 'DNSBL-JS';
						} else {
							$type = 'DNSBL-1x1';
						}
					}
				}
				else {
					$type = 'DNSBL-Unknown';
				}

				$csvline[2] = pfb_filter($csvline[2], PFB_FILTER_DOMAIN, 'pfb_daemon_dnsbl_index');
				$csvline[3] = pfb_filter($csvline[3], PFB_FILTER_IP, 'pfb_daemon_dnsbl_index');
				$csvline[4] = pfb_filter($csvline[4], PFB_FILTER_HTML, 'pfb_daemon_dnsbl_index');
			}
			else {
				continue;
			}

			// Log event and increment SQLite DNSBL Group counter
			if (!empty($csvline[2]) && !empty($csvline[3])) {
				pfb_log_event($type, $csvline[2], $csvline[3], $csvline[4]);
			}
		}
	}
	else {
		logger(LOG_ERR, localize_text('DNSBL index event parser - Failed to open handle'), LOG_PREFIX_PKG_PFBLOCKERNG);
	}
	@fclose($i_handle);
}


// Function to create/open SQLite3 database(s)
function pfb_open_sqlite($table, $message) {
	global $pfb;

	if ($table == 1) {
		$database	= $pfb['dnsbl_info'];
		$db_table	= 'dnsbl';
		$db_create	= "CREATE TABLE IF NOT EXISTS dnsbl ( groupname TEXT, timestamp TEXT, entries INTEGER, counter INTEGER );";
	} elseif ($table == 2) {
		$database	= $pfb['dnsbl_resolver'];
		$db_table	= 'lastevent';
		$db_create	= "CREATE TABLE IF NOT EXISTS lastevent ( row INTEGER, groupname TEXT, entry TEXT, details TEXT );";
	} elseif ($table == 3) {
		$database	= $pfb['dnsbl_resolver'];
		$db_table	= 'resolver';
		$db_create	= "CREATE TABLE IF NOT EXISTS resolver ( row INTEGER, totalqueries INTEGER, queries INTEGER );";
	} elseif ($table == 4) {
		$database	= $pfb['dnsbl_cache'];
		$db_table	= 'dnsblcache';
		$db_create	= "CREATE TABLE IF NOT EXISTS dnsblcache ( type TEXT, domain TEXT, groupname TEXT, final TEXT, feed TEXT );";
	} elseif ($table == 5) {
		$database	= $pfb['asn_cache'];
		$db_table	= 'asncache';
		$db_create	= "CREATE TABLE IF NOT EXISTS asncache ( asn TEXT, host TEXT, timestamp TEXT );";
	} elseif ($table == 6) {
		$database	= $pfb['dnsbl_resolver'];
		$db_table	= 'stats';
		$db_create	= "CREATE TABLE IF NOT EXISTS lastclear ( row INTEGER, lastipclear TEXT, lastdnsblclear TEXT );";
	} elseif ($table == 7) {
		$database	= $pfb['ip_cache'];
		$db_table	= 'ipcache';
		$db_create      = "CREATE TABLE IF NOT EXISTS ipcache ( host TEXT, q0 TEXT, q1 TEXT, geoip TEXT, resolved_host TEXT );";
	}

	try {
		$db_handle = new SQLite3($database);
		$db_handle->busyTimeout("{$pfb['sqlite_timeout']}");
	}
	catch (Exception $e) {
		@file_put_contents($pfb['errlog'], "\nDNSBL_SQL: Failed to open DB - {$message}", FILE_APPEND | LOCK_EX);

		try {
			$db_handle = new SQLite3($database);
			$db_handle->busyTimeout("{$pfb['sqlite_timeout']}");
		}
		catch (Exception $e) {
			@file_put_contents($pfb['errlog'], "\nDNSBL_SQL: Failed to open DB 2nd attempt - {$message}", FILE_APPEND | LOCK_EX);
		}
	}

	if ($db_handle) {

		// Validate database integrity
		$validate = $pfb_validate = FALSE;
		try {
			$validate = $db_handle->query("PRAGMA integrity_check;");
		}
		catch (Exception $e) {
			@file_put_contents($pfb['errlog'], "\nDNSBL_SQL: Failed to validate database - {$message}", FILE_APPEND | LOCK_EX);
		}

		if ($validate) {
			while ($res = $validate->fetchArray(SQLITE3_ASSOC)) {
				if ($res['integrity_check'] == 'ok') {
					$pfb_validate = TRUE;
				}
			}
		}

		if (!$pfb_validate) {
			logger(LOG_WARNING, localize_text("DNSBL SQLite3 database [ %s ] corrupt. Table deletion/re-creation completed.", $db_table), LOG_PREFIX_PKG_PFBLOCKERNG);
			@copy("{$database}", "{$database}.invalid");
			$db_create = "DROP TABLE {$db_table}; {$db_create}";
		}

		try {
			$db_handle->exec("BEGIN TRANSACTION; PRAGMA journal_mode = delete;"
					. "{$db_create}"
					. "END TRANSACTION;");
		}
		catch (Exception $e) {
			@file_put_contents($pfb['errlog'], "\nDNSBL_SQL: Database failure - {$message}", FILE_APPEND | LOCK_EX);
			return;
		}

		if ($table <= 4 && file_exists($database)) {
			@chown($database, 'unbound');
			@chgrp($database, 'unbound');
		}
		return $db_handle;
	}
	return FALSE;
}


// Function to close SQLite3 database
function pfb_close_sqlite($db_handle) {
	if (!empty($db_handle)) {
		$db_handle->close();
		unset($db_handle);
	}
}


// Function to Log event, Increment SQLite 'dnsbl' database and save last event to SQLite 'lastevent' Database
function pfb_log_event($type, $domain, $src_ip, $req_agent) {
	global $pfb;

	$datereq	= date('M j H:i:s', time());
	$req_agent	= str_replace("'", '', $req_agent);

	// Collect lastevent without saving new event
	$result		= pfb_dnsbl_lastevent('', "{$domain}{$src_ip}", '');
	$p_entry	= $result['entry'];
	$details	= $result['details'];

	// Duplicate entry comparison: "Domain/SRC IP"
	if ("{$domain}{$src_ip}" == $p_entry) {
		$dup_entry	= '-';
		$pfb_group	= $result['groupname'];
	}

	// If not duplicate entry, determine TLD type, Group Name and Feed name
	else {
		$dup_entry	= '+';
		$data		= pfb_dnsbl_parse('daemon', $domain, $src_ip, $req_agent);
		$pfb_group	= $data[0];
		$details	= $data[1];
		$req_agent	= $data[2];

		// Save new lastevent
		pfb_dnsbl_lastevent($pfb_group, "{$domain}{$src_ip}", $details);
	}

	$log = "{$type},{$datereq},{$details},{$dup_entry}\n";
	@file_put_contents($pfb['dnslog'], "{$log}", FILE_APPEND | LOCK_EX);
	
	// Write to Unified Log
	@file_put_contents($pfb['unilog'], "{$log}", FILE_APPEND | LOCK_EX);

	// Increment DNSBL Widget counter
	if (!empty($pfb_group)) {

		$db_handle = pfb_open_sqlite(1, 'Increment Counter');
		if ($db_handle) {

			$pfb_group	= pfb_filter($pfb_group, PFB_FILTER_HTML, 'pfb_log_event');
			$db_update	= "UPDATE dnsbl SET counter = counter + 1 WHERE groupname = :pfb_group";

			$stmt = $db_handle->prepare($db_update);
			if ($stmt) {
				$stmt->bindValue(':pfb_group', $pfb_group, SQLITE3_TEXT);
				$stmt->execute();
			}
		}
		pfb_close_sqlite($db_handle);
	}
}


// Function to 1) Collect lastevent and 2) Save lastevent to SQLite 'lastevent' Database
function pfb_dnsbl_lastevent($p_group, $p_entry, $p_details) {
	global $pfb;

	$db_update	= '';
	$final		= array();

	$db_handle = pfb_open_sqlite(2, 'LastEvent');
	if ($db_handle) {
		$result	= $db_handle->query("SELECT * FROM lastevent WHERE row = 0;");
		if ($result) {
			$final = $result->fetchArray(SQLITE3_ASSOC);
		}
	}
	pfb_close_sqlite($db_handle);

	// Collect or update existing row
	if (!empty($final)) {

		// Only collect lastevent entry
		if (empty($p_group)) {
			;
		}

		// Update lastevent entry
		else {
			$db_update = "UPDATE lastevent SET groupname=:p_group, entry=:p_entry, details=:p_details WHERE row = 0";
		}
	}

	// Add new lastevent entry
	else {
		$db_update = "INSERT into lastevent (row, groupname, entry, details) VALUES (0, :p_group, :p_entry, :p_details )";
	}

	if (!empty($db_update)) {
		$db_handle = pfb_open_sqlite(2, 'LastEvent');
		if ($db_handle) {

			$p_group	= pfb_filter($p_group, PFB_FILTER_HTML, 'pfb_dnsbl_lastevent');
			$p_entry	= pfb_filter($p_entry, PFB_FILTER_HTML, 'pfb_dnsbl_lastevent');
			$p_details	= pfb_filter($p_details, PFB_FILTER_HTML, 'pfb_dnsbl_lastevent');

			$stmt = $db_handle->prepare($db_update);
			if ($stmt) {
				$stmt->bindValue(':p_group', $p_group, SQLITE3_TEXT);
				$stmt->bindValue(':p_entry', $p_entry, SQLITE3_TEXT);
				$stmt->bindValue(':p_details', $p_details, SQLITE3_TEXT);
				$stmt->execute();
			}
		}
		pfb_close_sqlite($db_handle);
	}
	return $final;
}


// Function to collect and update the Unbound Resolver query/pid entries into SQLite3 database
function pfb_daemon_queries() {
	global $g, $pfb;

	$sleep_freq	= config_get_path('installedpackages/pfblockerngglobal/widget-dnsblquery', 5);

	$nice		= '/usr/bin/nice -n20';
	$unbound_pid	= "{$g['varrun_path']}/unbound.pid";

	while (TRUE) {
		sleep($sleep_freq);

		// If 'Live Sync' file marker exists skip DNSBL Queries daemon to avoid unbound-control collisions
		if (is_platform_booting() || $g['pfblockerng_install'] || file_exists("{$pfb['dnsbl_file']}.sync")) {
			continue;
		}

		// Collect Unbound Resolver pid
		$pid = exec("{$nice} /usr/bin/pgrep -anx 'unbound' 2>&1");
		if (!empty($pid)) {
			$output	= exec("{$pfb['chroot_cmd']} stats_noreset | {$nice} {$pfb['grep']} 'total.num.queries=' | cut -d '=' -f2 2>&1");
			$query	= intval($output);
			if (!is_numeric($query)) {
				$query = '';
			}

			// On an Unbound Reload, the query stat is reset, therefore reuse previous query value
			if ($query < $p_query) {
				$t_query	= $query;
				$query		= $query + $p_query;
				$pid		= '';
			}
		}

		// On initial daemon load
		if (empty($p_pid)) {
			$p_pid = $pid;
			continue;
		}

		$pfb_found		= FALSE;
		$stats			= array();
		$stats['totalqueries']	= 0;
		$stats['queries']	= 0;

		$db_handle = pfb_open_sqlite(3, 'Resolver collect queries');
		if ($db_handle) {
			$result = $db_handle->query("SELECT * FROM resolver WHERE row = 0;");
			if ($result) {
				while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
					$stats = $res;
					$pfb_found = TRUE;
				}
			}

			// Create new row
			if (!$pfb_found) {
				$db_update = "INSERT INTO resolver ( row, totalqueries, queries ) VALUES ( 0, 0, 0 );";
				$db_handle->exec("BEGIN TRANSACTION;"
						. "{$db_update}"
						. "END TRANSACTION;");
			}
		}
		pfb_close_sqlite($db_handle);

		// If Unbound Resolver pid has changed, clear SQLite database 'queries' entry, and update 'totalqueries/pid' entries
		if ($pfb_found && $pid != $p_pid) {
			$totalqueries = ($stats['totalqueries'] ?: 0) + ($query ?: 0);
			pfBlockerNG_clearsqlite('update_totalqueries', $totalqueries);
		}
		else {
			if ($query != '' && $query != $p_query && is_numeric($query)) {

				// Update existing row
				$db_handle = pfb_open_sqlite(3, 'Widget update queries');
				if ($db_handle) {
					$db_update = "UPDATE resolver SET queries = :query WHERE row = 0";
					$stmt = $db_handle->prepare($db_update);
					if ($stmt) {
						$stmt->bindValue(':query', $query, SQLITE3_INTEGER);
						$stmt->execute();
					}
				}
				pfb_close_sqlite($db_handle);
			}
		}

		$p_pid = $pid;
		if (isset($t_query)) {
			$p_query = $t_query;
			unset($t_query);
		} else {
			$p_query = $query;
		}
	}
}


// Read logfile in realtime (livetail)
// Reference: http://stackoverflow.com/questions/3218895/php-how-to-read-a-file-live-that-is-constantly-being-written-to
function pfb_livetail($logfile, $mode) {
	global $pfb;

	if (!file_exists("{$logfile}")) {
		touch("{$logfile}");
	}

	$len		= @filesize("{$logfile}");	// Start at EOF
	$lastpos_old	= $pfb_output = '';

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

	while (TRUE) {
		usleep(300000); //0.3s
		clearstatcache(false, "{$logfile}");
		$len = @filesize("{$logfile}");

		if ($len < $lastpos) {
			$lastpos = $len;	// File deleted or reset
		}
		else {
			$f = @fopen("{$logfile}", 'rb+');
			if ($f === false) {
				break;
			}
			@fseek($f, $lastpos);

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
			if ($f) {
				@fclose($f);
			}

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
				if ($f) {
					@fclose($f);
				}

				// Call log mgmt function
				pfb_log_mgmt();
				break;
			}
		}
	}
}


// Load/convert Feeds (w/alternative aliasname(s), if user-configured) and return as array
function convert_feeds_json() {
	global $pfb;

	$aconfig		= config_get_path('installedpackages/pfblockerngglobal', []);
	$pfb['feeds_list']	= $merge_feeds = $feed_info = array();

	$feed_info_raw = json_decode(@file_get_contents("{$pfb['feeds']}"), TRUE);
	if (json_last_error() !== JSON_ERROR_NONE || !is_array($feed_info_raw)) {
		return array('blank' => '');
	}

	$feed_count = array();
	foreach ($feed_info_raw as $type => $info) {

		if (!is_array($info) || (isset($info[0]) && $info[0] == '*')) {
			continue;
		}

		$feed_count[$type] = 0;
		foreach ($info as $aliasname => $data) {
			$l_aliasname = strtolower($aliasname);

			$feed_count[$type] = $feed_count[$type] + count($data['feeds']);
			foreach ($data['feeds'] as $key => $feed) {

				// Remove discontinued Feeds
				if (isset($feed['status']) && $feed['status'] == 'discontinued') {
					unset($data['feeds'][$key]);
					$feed_count[$type]--;
					continue;
				}

				if (isset($feed['alternate'])) {
					foreach ($feed['alternate'] as $alternate) {
						$feed_count[$type]++;
					}
				}
			}

			if (!array_key_exists($type, $pfb['feeds_list'])) {
				$pfb['feeds_list'][$type] = array();
			}
			if (!array_key_exists($type, $feed_info)) {
				$feed_info[$type] = array();
			}

			// Use alternative Aliasname(s) and/or merge multiple aliasname Feeds together (if user configured)
			if (!empty($aconfig['feed_' . $l_aliasname])) {

				$alt_feed = $aconfig['feed_' . $l_aliasname];
				$pfb['feeds_list'][$type][$aliasname] = $alt_feed;	// Global list of all known Feed aliasnames

				if (!is_array($merge_feeds[$alt_feed])) {
					$merge_feeds[$alt_feed] = array();
				}
				$merge_feeds[$alt_feed] = array_merge( $merge_feeds[$alt_feed], (array)$data['feeds'] );

				if (!isset($feed_info[$type][$alt_feed])) {

					// Modify 'info' and 'description' fields to reference user-defined aliasname
					foreach (array('info', 'description') as $atype) {
						$match = strpos($data[$atype], $aliasname);
						if ($match !== FALSE) {
							$data[$atype] = substr_replace($data[$atype], $aconfig['feed_' . $l_aliasname], $match, strlen($aliasname));
						}
					}
					$feed_info[$type][$aconfig['feed_' . $l_aliasname]] = $data;
				}
				$feed_info[$type][$alt_feed]['feeds'] = $merge_feeds[$alt_feed];
			}
			else {
				$pfb['feeds_list'][$type][$aliasname] = $aliasname;
				$feed_info[$type][$aliasname] = $data;
			}
		}
	}
	$feed_info['count'] = $feed_count;
	return $feed_info;
}


// Define Alerts Tab 'default GET request' (Top row)
function pfb_alerts_default_page() {
	global $pfb;

	if (isset($pfb['config_global']) &&
	    isset($pfb['config_global']['pfbpageload'])) {
		switch($pfb['config_global']['pfbpageload']) {
			case 'dnsbl_stat':
				return '?view=dnsbl_stat';
			case 'dnsbl_reply_stat':
				return '?view=dnsbl_reply_stat';
			case 'ip_block_stat':
				return '?view=ip_block_stat';
			case 'ip_permit_stat':
				return '?view=ip_permit_stat';
			case 'ip_match_stat':
				return '?view=ip_match_stat';
			case 'reply':
				return '?view=reply';
			case 'unified':
				return '?view=unified';
		}
	}
	return '';
}


// Clear IP Alias Packet Counts (widget)
function pfBlockerNG_clearip() {
	global $pfb;

	exec("{$pfb['pfctl']} -z 2>&1");

	/* TODO: Clear only pfB counters
	$pfb_tables = array();
	exec("{$pfb['pfctl']} -sTables | {$pfb['grep']} 'pfB_' 2>&1", $pfb_tables);
	if (!empty($pfb_tables)) {
		foreach ($pfb_tables as $table) {
			exec("{$pfb['pfctl']} -t {$table} -T zero");
		}
	}
	*/
}


// Clear DNSBL SQLite database statistics/queries as required
function pfBlockerNG_clearsqlite($mode, $totalqueries = 0) {
	global $g, $pfb;

	// Format of todo array: database, error message, SQLite command
	$todo = array();

	// Clear SQLite database 'queries' entry and update totalqueries (+queries), if Unbound Resolver PID changed (Reload)
	if ($mode == 'update_totalqueries') {
		$todo[] = array(3, 'Clear Resolver queries', 'UPDATE resolver SET totalqueries = :totalqueries, queries = 0 WHERE row = 0;');
	}
	elseif ($mode == 'clearip') {
		$lastipclear = date('M j H:i:s', time()); 
		$todo[] = array(6, 'Reset IP last clear timestamp', 'UPDATE lastclear SET lastipclear = :lastipclear WHERE row = 0;');
	}
	elseif ($mode == 'cleardnsbl') {
		$lastdnsblclear = date('M j H:i:s', time()); 
		$todo[] = array(1, 'Clear Widget counters', 'UPDATE dnsbl SET counter = 0;');
		$todo[] = array(3, 'Clear Resolver queries', 'UPDATE resolver SET totalqueries = 0, queries = 0;');
		$todo[] = array(6, 'Reset DNSBL last clear timestamp', 'UPDATE lastclear SET lastdnsblclear = :lastdnsblclear WHERE row = 0;');
	}

	// Clear Unbound Resolver statistics
	if ($mode != 'clearip' && is_process_running('unbound')) {
		exec("{$pfb['chroot_cmd']} flush_stats 2>&1");
	}

	if (!empty($todo)) {
		foreach ($todo as $data) {
			$db_handle = pfb_open_sqlite($data[0], $data[1]);

			if ($db_handle) {
				if ($mode == 'update_totalqueries') {
					if (is_numeric($totalqueries)) {
						$stmt = $db_handle->prepare($data[2]);
						if ($stmt) {
							$stmt->bindValue(':totalqueries', $totalqueries, SQLITE3_INTEGER);
							$stmt->execute();
						}
					}
				}
				elseif ($mode == 'clearip') {
					$stmt = $db_handle->prepare($data[2]);
					if ($stmt) {
						$stmt->bindValue(':lastipclear', $lastipclear, SQLITE3_TEXT);
						$stmt->execute();
					}
				}
				elseif ($mode == 'cleardnsbl') {
					$stmt = $db_handle->prepare($data[2]);
					if ($stmt) {
						$stmt->bindValue(':lastdnsblclear', $lastdnsblclear, SQLITE3_TEXT);
						$stmt->execute();
					}
				}
				else {
					$db_handle->exec("BEGIN TRANSACTION;"
							. "{$data[2]}"
							. "END TRANSACTION;");
				}
			}
			pfb_close_sqlite($db_handle);
		}
	}
}


// Function to read/lock/unlock IP/Domains from Aliastables/DNSBL (Called via Alerts Page)
function pfb_unlock($mode, $type, $remove='', $r_type='', $filename_unlock) {
	global $pfb;

	if ($type == 'ip') {
		$filename = $pfb['ip_unlock'];
	} elseif ($type == 'dnsbl') {
		$filename = $pfb['dnsbl_unlock'];
	} elseif ($type == 'dnsbl_data') {
		$filename = "{$pfb['dnsbl_unlock']}.data";
	} else {
		return;
	}

	if ($mode == 'read') {
		$filename_unlock = array();
		if (($handle = @fopen("{$filename}", 'r')) !== FALSE) {
			while (($line = @fgetcsv($handle)) !== FALSE) {
				if (!empty($line)) {
					$filename_unlock[$line[0]] = $line[1];
				}
			}
		}
		if ($handle) {
			@fclose($handle);
		}

		if (empty($filename_unlock)) {
			unlink_if_exists("{$filename}");
		}
		return $filename_unlock;
	}
	elseif ($mode == 'dnsbl_data') {
		$filename_unlock = '';
		$data = array();
		if (($handle = @fopen("{$filename}", 'r')) !== FALSE) {
			while (($line = @fgets($handle)) !== FALSE) {
				if (strpos($line, $remove) !== FALSE) {
					$data = explode(',', $line);
				} else {
					$filename_unlock .= "{$line}"; 
				}
			}
		}
		if ($handle) {
			@fclose($handle);
		}

		if (empty($filename_unlock)) {
			unlink_if_exists("{$filename}");
		} else {
			@file_put_contents("{$filename}", "{$filename_unlock}", LOCK_EX);
		}
		return $data;
	}
	elseif ($mode == 'unlock' && isset($filename_unlock[$remove])) {
		return;
	}
	elseif (empty($remove)) {
		return;
	}

	// Add/Remove IP/Domain in unlock file
	if (($pfb_output = @fopen("{$filename}", 'w')) !== FALSE) {
		foreach ($filename_unlock as $key => $line) {

			// 'Remove locked IP/Domains' or 'Add existing unlocked IP/Domain' in unlock file
			if ($mode == 'unlock' || ($mode == 'lock' && $key != $remove)) {
				@fwrite($pfb_output, "{$key},{$line}\n");
			}
		}

		// Add IP/Domain to unlock file
		if ($mode == 'unlock') {
			$filename_unlock[$remove] = $r_type;
			@fwrite($pfb_output, "{$remove},{$r_type}\n");
		}
	}
	if ($pfb_output) {
		@fclose($pfb_output);
	}

	if (empty($filename_unlock)) {
		unlink_if_exists("{$filename}");
	}
}


// Function to clear pfBlockerNG folder/files
function pfb_clear_contents() {
	global $pfb;

	unlink_if_exists("{$pfb['dbdir']}/masterfile");
	unlink_if_exists("{$pfb['dbdir']}/mastercat");
	unlink_if_exists("{$pfb['supptxt']}");
	unlink_if_exists("{$pfb['dnsbl_supptxt']}");
	unlink_if_exists("{$pfb['dnsbl_info']}");
	unlink_if_exists("{$pfb['dnsbl_resolver']}");
	unlink_if_exists("{$pfb['dnsbl_cache']}");
	unlink_if_exists("/var/tmp/unbound_cache_*");
	unlink_if_exists("{$pfb['asn_cache']}");
	unlink_if_exists("{$pfb['ip_cache']}");
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


// Main pfBlockerNG function
function sync_package_pfblockerng($cron='') {
	global $g, $pfb, $pfbarr;
	pfb_global();

	$pfb['conf_mod']		= FALSE;	// Flag to check for mods to the config.xml file. ('$pfb_config' array to hold changes)
	$pfb['filter_configure']	= FALSE;	// Flag to call filter_configure once

	// Detect boot process or package installation
	if (is_platform_booting() || $g['pfblockerng_install']) {
		// Create DNSBL NAT, VIP, Lighttpd service and certs if required on reboot.
		if ($pfb['dnsbl'] == 'on') {
			pfb_create_dnsbl('enabled');
		}
		$log = 'Sync terminated during boot process.';
		pfb_logger("\n{$log}\nUPDATE PROCESS ENDED [ NOW ]\n", 1);
		logger(LOG_INFO, localize_text($log), LOG_PREFIX_PKG_PFBLOCKERNG);
		return;
	}

	// Reloads existing lists without downloading new lists when defined 'on'
	$pfb['reuse'] = $pfb['config']['pfb_reuse'];
	$pfb['reuse_dnsbl'] = '';

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
		$log = " UPDATE PROCESS START [ " . pfb_pkg_ver() . " ] [ NOW ]\n";
		pfb_logger("{$log}", 1);
	} else {
		if ($cron != 'noupdates') {
			$log = "\n**Saving configuration [ NOW ]**\n";
			pfb_logger("{$log}", 1);
		}
	}

	// Call function for Ramdisk processes.
	pfb_aliastables('conf');

	// If table limit not defined, set default to 2M
	if (empty(config_get_path('system/maximumtableentries'))) {
		config_set_path('system/maximumtableentries', '2000000');
		write_config('pfBlockerNG: save max Firewall table entries limit', false);
	}
	$pfb['table_limit'] = config_get_path('system/maximumtableentries');

	// Collect local web gui configuration
	$pfb['weblocal'] = config_get_path('system/webgui/protocol', 'http');
	$pfb['port'] = config_get_path('system/webgui/port');
	if (empty($pfb['port'])) {
		if (config_get_path('system/webgui/protocol') == 'http') {
			$pfb['port'] = '80';
		} else {
			$pfb['port'] = '443';
		}
	}
	$pfb['weblocal'] .= "://127.0.0.1:{$pfb['port']}/pfblockerng/pfblockerng.php";

	// Define Inbound/Outbound action is not user selected.
	$pfb['deny_action_inbound']  = $pfb['ipconfig']['inbound_deny_action']	?: 'block';
	$pfb['deny_action_outbound'] = $pfb['ipconfig']['outbound_deny_action']	?: 'reject';

	$pfb['float']		= $pfb['ipconfig']['enable_float'];				// Enable/Disable floating autorules
	$pfb['dup']		= $pfb['ipconfig']['enable_dup'];				// Enable remove of duplicate IPs utilizing grepcidr
	$pfb['agg']		= $pfb['ipconfig']['enable_agg'];				// Enable aggregation of CIDRs
	$pfb['order']		= $pfb['ipconfig']['pass_order'];				// Order of the autorules
	$pfb['global_log']	= $pfb['ipconfig']['enable_log'];				// Enable Global IP logging
	$pfb['suffix']		= $pfb['ipconfig']['autorule_suffix'];				// Suffix used for autorules
	$pfb['kstates'] 	= $pfb['ipconfig']['killstates'];				// Firewall states removal

	$pfb['ip_ph']		= pfb_filter($pfb['ipconfig']['ip_placeholder'], PFB_FILTER_IPV4, 'Placeholder IP Address', '127.1.7.7');	// Placeholder IP Address

	// DNSBL settings
	$pfb['dnsbl_ip']	= $pfb['dnsblconfig']['action']		?:  'Disabled';		// Enable/Disable IP blocking from DNSBL lists
	$pfb['dnsbl_rule']	= $pfb['dnsblconfig']['pfb_dnsbl_rule'] ?: 'Disabled';		// Auto create a Floating Pass Rule for other Lan subnets
	$pfb['dnsbl_alexa_cnt']	= $pfb['dnsblconfig']['alexa_count']	?: '1000';		// TOP1M whitelist domain setting
	$pfb['dnsbl_alexa_inc']	= $pfb['dnsblconfig']['alexa_inclusion']?: '';			// TOP1M TLDs inclusions for whitelisting
	$pfb['dnsbl_tld']	= $pfb['dnsblconfig']['pfb_tld'];				// Enable TLD Function
	$pfb['dnsbl_control']	= $pfb['dnsblconfig']['pfb_control']	?: '';			// Python Control integration

	// Validate pfB Script variable
	$pfb['dnsbl_alexa_inc'] = pfb_filter($pfb['dnsbl_alexa_inc'], PFB_FILTER_CSV, 'Validate pfB Script variable');

	// Reputation config variables
	$pfb['config_rep'] = config_get_path('installedpackages/pfblockerngreputation/config/0', []);

	// Validate pfB Script variables
	foreach (array(
			'enable_rep'		=> 'rep',					// Enable/Disable 'Max' Reputation
			'enable_pdup'		=> 'prep',					// Enable/Disable 'pRep' Reputation
			'enable_dedup'		=> 'drep',					// Enable/Disable 'dRep' Reputation
			'et_update'		=> 'etupdate',					// Perform a Force Update on ET categories
			'ccwhite'		=> 'ccwhite',					// Action for whitelist Country category
			'ccblack'		=> 'ccblack',					// Action for blacklist Country category
			'etblock'		=> 'etblock',					// Emerging Threats IQRisk block categories
			'etmatch'		=> 'etmatch',					// Emerging Threats IQRisk match categories
			'p24_max_var'		=> 'max',					// 'Max' variable setting for Reputation
			'p24_dmax_var'		=> 'dmax',					// 'dMax' variable setting for Reputation
			'p24_pmax_var'		=> 'pmax',					// 'pMax' variable setting for Reputation
			'ccexclude'		=> 'ccexclude'					// List of Countries to whitelist
		) as $conf_value => $pfb_value) {

		$pfb_variable = $pfb['config_rep'][$conf_value] ?: 'x';
		if (empty(pfb_filter($pfb_variable, PFB_FILTER_CSV, 'Validate pfB Script variables'))) {
			$pfb[$pfb_value] = 'x';
		} else {
			$pfb[$pfb_value] = $pfb_variable;
		}
	}

	// Starting variable to skip Reputation functions, if no changes are required
	$pfb['repcheck'] = FALSE;
	// $pfb['save'] is used to determine if user pressed "save" button to avoid collision with CRON.

	// For 'script' calls using exec() (used to shorten length of line)
	$elog = ">> {$pfb['log']} 2>&1";


	#################################
	#	Configure ARRAYS	#
	#################################

	$new_aliases		= array();		// An array of aliases (full details)
	$new_aliases_list	= array();		// An array of alias names
	$pfb_alias_lists	= array();		// An array of aliases that have updated lists via CRON/force update. ('Reputation' disabled)
	$pfb_alias_lists_all	= array();		// An array of all active aliases. ('Reputation' enabled)

	$ip_types		= array( 'pfblockernglistsv4' => '_v4', 'pfblockernglistsv6' => '_v6');
	$cont_types		= array( 'countries4' => '_v4', 'countries6' => '_v6');

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
		$cont_key = 'pfblockerng' . strtolower(str_replace(' ', '', $continent));
		if (!empty(config_get_path("installedpackages/{$cont_key}/config"))) {
			$continent_config = config_get_path("installedpackages/{$cont_key}/config/0");
			if ($continent_config['action'] != 'Disabled' && in_array($continent_config['action'], $action)) {
				$pfb['autorules'] = TRUE;
				break;
			}
		}
	}

	if (!$pfb['autorules']) {
		foreach ($ip_types as $ip_type => $vtype) {
			foreach(config_get_path("installedpackages/{$ip_type}/config", []) as $list) {
				if ($list['action'] != 'Disabled' && in_array($list['action'], $action)) {
					$pfb['autorules'] = TRUE;
					break;
				}
			}
		}
	}

	// Check if DNSBL auto permit rule or DNSBL 'Auto Deny' rules for DNSBL IPs are defined
	if (!empty($pfb['dnsblconfig']['dnsbl_allow_int']) || strpos($pfb['dnsblconfig']['action'], 'Deny_') !== FALSE) {
		$pfb['autorules'] = TRUE;
	}

	// Configure auto rule suffix.
	$pfbfound = FALSE;
	$pfb_suffix_match = '';
	foreach (config_get_path('filter/rule', []) as $rule) {

		// Query for previous IPv4 pfBlockerNG 'alias type' aliasnames which are not in the new '_v4' suffix format
		foreach (array('source', 'destination') as $rtype) {
			if (substr($rule[$rtype]['address'], 0, 4) == 'pfB_' &&
				substr($rule[$rtype]['address'], -3) != '_v4' &&
				$rule['ipprotocol'] == 'inet') {
				$pfb['autorules'] = TRUE;	// Set flag to re-configure Firewall rules and add missing '_v4' suffix
			}
		}

		// Collect any pre-existing suffix
		if (!empty($pfb_suffix_match) && preg_match('/^pfB_\w+(\s.*)/', $rule['descr'], $pfb_suffix_real)) {
			$pfb_suffix_match = $pfb_suffix_real[1];
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
		$pfb['suffix'] = '';
		if ($pfb['autorules']) {
			$pfb['suffix'] = $pfb_suffix_match;	// Use existing suffix match
		}
	}


	#########################################################
	#	Configure INBOUND/OUTBOUND INTERFACES		#
	#########################################################

	// Collect pfSense interface order
	$ifaces = pfb_build_if_list(TRUE, FALSE);

	foreach (array('inbound', 'outbound') as $type) {
		$pfb["{$type}_interfaces"] = $pfb["{$type}_floating"] = array();

		if (!empty($pfb['ipconfig']["{$type}_interface"])) {
			$interfaces = explode(',', $pfb['ipconfig']["{$type}_interface"]);

			// CSV string for 'pfB_' match rules
			$pfb["{$type}_floating"]		= ltrim(implode(',', $interfaces), ',');

			// Assign base rule/interfaces
			if ($pfb['float'] == 'on') {
				$pfb['base_rule']		= $pfb['base_rule_float'];
				$pfb["{$type}_interfaces"]	= explode(' ', $pfb["{$type}_floating"]);
			} else {
				$pfb['base_rule']		= $pfb['base_rule_reg'];
				$pfb["{$type}_interfaces"]	= $interfaces;
			}
		}
	}

	// Determine max Domain count available for DNSBL TLD analysis (Avoid Unbound memory exhaustion)
	$pfs_memory = (round(get_single_sysctl('hw.physmem') / (1024*1024)) ?: 1000);

	$pfb['pfs_mem'] = [
		'0' => '100000',
		'1500' => '150000',
		'2000' => '200000',
		'2500' => '250000',
		'3000' => '400000',
		'4000' => '600000',
		'5000' => '1000000',
		'6000' => '1500000',
		'7000' => '2000000',
		'8000' => '2500000',
		'12000' => '3000000',
		'16000' => '4000000',
		'32000' => '8000000'
	];

	if ($pfb['dnsbl_py_blacklist']) {
		array_walk($pfb['pfs_mem'], function (&$value) {
			$value = $value * 3;
		});
	}

	foreach ($pfb['pfs_mem'] as $pfb_mem => $domain_max) {
		if ($pfs_memory >= $pfb_mem) {
			$pfb['domain_max_cnt'] = $domain_max;
		}
	}


	#################################################
	#	Clear Removed Lists from Masterfiles	#
	#################################################

	$pfb['sync_master']	= TRUE;		// Process to keep IP Masterfiles in sync with valid Lists from config.conf file
	$pfb['remove']		= FALSE;	// Flag to execute pfctl and rules ordering or reload of DNSBL domains
	$pfb['summary']		= FALSE;	// Execute final summary as a list was removed

	// Don't execute this function when pfBlockerNG is disabled and 'keep blocklists' is enabled.
	if ($pfb['enable'] == '' && $pfb['keep'] == 'on') {
		$pfb['sync_master'] = FALSE;
	}

	if ($pfb['sync_master']) {

		// Find all enabled Continents lists
		foreach ($pfb['continents'] as $continent => $pfb_alias) {
			$cont_key = 'pfblockerng' . strtolower(str_replace(' ', '', $continent));
			if (!empty(config_get_path("installedpackages/{$cont_key}/config")) && $pfb['enable'] == 'on') {
				$continent_config = config_get_path("installedpackages/{$cont_key}/config/0");
				if ($continent_config['action'] != 'Disabled') {
					foreach ($cont_types as $c_type => $vtype) {
						if (!empty($continent_config[$c_type])) {

							// Force 'Alias Native' setting to any Alias with 'Advanced Inbound/Outbound -Invert src/dst' settings.
							// This will bypass Deduplication and Reputation features.
							if ($continent_config['autoaddrnot_in'] == 'on' ||
							    $continent_config['autoaddrnot_out'] == 'on') {
								$pfb['existing']['native'][]		= "{$pfb_alias}{$vtype}";
							}
							else {
								if (strpos($continent_config['action'], 'Match') !== FALSE) {
									$pfb['existing']['match'][]	= "{$pfb_alias}{$vtype}";
								}
								elseif (strpos($continent_config['action'], 'Permit') !== FALSE) {
									$pfb['existing']['permit'][]	= "{$pfb_alias}{$vtype}";
								}
								elseif (strpos($continent_config['action'], 'Deny') !== FALSE) {
									$pfb['existing']['deny'][]	= "{$pfb_alias}{$vtype}";
								}
								elseif ($continent_config['action'] == 'Alias_Native') {
									$pfb['existing']['native'][]	= "{$pfb_alias}{$vtype}";
								}
							}
						}
					}
				}
			}
		}

		// Find all enabled IPv4/IPv6 lists and DNSBL lists
		// Find all enabled IPv4 'Custom List' header names and check if 'Emerging Threats Update' and 'Custom List Update' needs force updating
		$list_types = array(	'pfblockernglistsv4'		=> '_v4',
					'pfblockernglistsv6'		=> '_v6',
					'pfblockerngdnsbl'		=> '_v4'
					);

		$pfb_invalid = FALSE;
		foreach ($list_types as $ltype => $vtype) {
			$lists = array();

			$type_config = config_get_path("installedpackages/{$ltype}/config");
			if (!empty($type_config) && $pfb['enable'] == 'on') {
				foreach ($type_config as $list) {

					// If only the 'customlist' is defined. Remove the 'List row' data.
					if (isset($list['row']) && empty($list['row'][0]['url'])) {
						unset($list['row']);
					}

					if (!empty($list['custom'])) {
						$list['row'][] = array( 'header'	=> "{$list['aliasname']}_custom",
									'custom'	=> $list['custom'],
									'state'		=> 'Enabled',
									'url'		=> 'custom'
									);
					}
					$lists[] = $list;
				}
			}

			// ADD DNSBL IP
			if ($ltype == 'pfblockernglistsv4' && $pfb['enable'] == 'on' && $pfb['dnsbl'] == 'on' && $pfb['dnsbl_ip'] != 'Disabled') {
				$list = array();
				$list['action'] = "{$pfb['dnsbl_ip']}";
				$list['row'][]	= array('format'	=> 'auto',
							'state'		=> 'Enabled',
							'url'		=> "{$pfb['dbdir']}/DNSBLIP{$vtype}.txt",
							'header'	=> 'DNSBLIP');
				$lists[] = $list;
			}

			if (!empty($lists)) {
				foreach ($lists as $key => $list) {

					// Remove any spaces or special characters in existing Aliasnames
					if (preg_match("/\W/", $list['aliasname'])) {
						$pfb_invalid = TRUE;
						$list['aliasname'] = preg_replace("/\W/", '', $list['aliasname']);
						config_set_path("installedpackages/{$ltype}/config/{$key}/aliasname", $list['aliasname']);
					}

					if (isset($list['row']) && $list['action'] != 'Disabled') {

						// Force 'Alias Native' setting to any Alias with 'Advanced Inbound/Outbound -Invert src/dst' settings.
						// This will bypass Deduplication and Reputation features.
						if ($list['action'] != 'unbound' && ($list['autoaddrnot_in'] == 'on' ||
						    $list['autoaddrnot_out'] == 'on')) {
							$list['action'] = 'Alias_Native';
						}

						foreach ($list['row'] as $hkey => $row) {

							// Remove any spaces or special characters in existing Header names
							if (preg_match("/\W/", $row['header'])) {
								$pfb_invalid	= TRUE;
								$row['header']	= preg_replace("/\W/", '', $row['header']);
								config_set_path("installedpackages/{$ltype}/config/{$key}/row/{$hkey}/header", $row['header']);
							}

							if ($ltype == 'pfblockerngdnsbl') {
								$header = "{$row['header']}";
							} else {
								$header = "{$row['header']}{$vtype}";
							}

							// Collect enabled lists
							if (!empty($row['url']) && $row['state'] != 'Disabled') {
								if (strpos($list['action'], 'Match') !== FALSE) {
									$pfb['existing']['match'][]	= "{$header}";
								}
								elseif (strpos($list['action'], 'Permit') !== FALSE) {
									$pfb['existing']['permit'][]	= "{$header}";
								}
								elseif (strpos($list['action'], 'Deny') !== FALSE) {
									$pfb['existing']['deny'][]	= "{$header}";
								}
								elseif ($list['action'] == 'Alias_Native') {
									$pfb['existing']['native'][]	= "{$header}";
								}
								elseif ($list['action'] == 'unbound') {
									$pfb['existing']['dnsbl'][]	= "{$header}";
								}
							}
						}
					}
				}
			}
		}

		// Save any existing Alias/Header names that have spaces or special characters
		if ($pfb_invalid) {
			write_config('pfBlockerNG: Remove spaces/special characters in Alias/Header names');
		}

		// If 'TLD' enabled and TLD Blacklists are defined, add to enabled DNSBL lists
		if ($pfb['dnsbl_tld']) {
			$tld_blacklist = pfbng_text_area_decode($pfb['dnsblconfig']['tldblacklist'], TRUE, FALSE, TRUE);
			if (!empty($tld_blacklist)) {
				$pfb['existing']['dnsbl'][] = 'DNSBL_TLD';
			}
		}

		// Add 'Reputation - ccwhite Action' if found
		if ($pfb['ccwhite'] == 'match' && file_exists("{$pfb['matchdir']}/matchdedup_v4.txt")) {
			$pfb['existing']['match'][] = 'matchdedup_v4';
		}

		// Add enabled 'DNSBL Blacklist categories'
		if (isset($pfb['blconfig']) &&
		    $pfb['blconfig']['blacklist_enable'] != 'Disable' &&
		    !empty($pfb['blconfig']['blacklist_selected'])) {

			$selected = array_flip(explode(',', $pfb['blconfig']['blacklist_selected'])) ?: array();
			foreach ($pfb['blconfig']['item'] as $item) {

				if (isset($selected[$item['xml']]) && !empty($item['selected'])) {
					$categories = explode(',', $item['selected']) ?: array();
					foreach ($categories as $category) {
						if (!empty($category)) {
							$category = str_replace('-', '_', $category);
							$pfb['existing']['dnsbl'][] = "{$item['title']}_{$category}";
						}
					}
				}
			}
		}

		// Collect all .txt file names for each list type
		$list_types = array(	'match' => $pfb['matchdir'], 'permit' => $pfb['permitdir'], 'deny' => $pfb['denydir'],
					'native' => $pfb['nativedir'], 'dnsbl' => $pfb['dnsdir']);

		// Collect all previouly downloaded filename headers
		foreach ($list_types as $pftype => $pfbfolder) {

			$pfb_files = glob("{$pfbfolder}/*.txt");
			foreach ($pfb_files as $pfb_list) {
				$pfb['actual'][$pftype][] = basename($pfb_list, '.txt');
			}

			$results = array_diff($pfb['actual'][$pftype], $pfb['existing'][$pftype]);

			// Remove any DNSBL Orig files that are not referenced
			if ($pftype == 'dnsbl') {
				$orig_list = glob("{$pfb['dnsorigdir']}/*.orig");
				if (!empty($orig_list) && is_array($orig_list)) {
					$orig_list_final = array();
					foreach ($orig_list as $pfb_orig) {
						$orig_list_final[] = basename($pfb_orig, '.orig');
					}

					$results_flip = array_flip($pfb['actual'][$pftype]);
					if (!empty($results_flip) && is_array($results_flip)) {
						foreach ($orig_list_final as $orig) {
							if (!isset($results_flip[$orig])) {
								unlink_if_exists("{$pfb['dnsorigdir']}/{$orig}.*");
							}
						}
					}
				}
			}

			if (empty($results)) {
				continue;	// No changes required
			}

			$f_result = implode(',', $results);
			if (empty(pfb_filter($f_result, PFB_FILTER_CSV, 'Failed to complete sync of Feeds'))) {
				pfb_logger("\nFailed to complete sync of Feeds!", 1);
				continue;
			}

			$log = "\n[ Removing '{$pftype}' \tList(s) : {$f_result} ]";
			pfb_logger("{$log}", 1);

			// Process to remove lists from IP Masterfile/DB folder if they are not referenced any longer
			switch ($pftype) {
				case 'deny':
					// Script to Remove un-associated List(s)
					exec("{$pfb['script']} remove x x x {$f_result} {$elog}");
					$pfb['summary'] = $pfb['remove'] = TRUE;
					break;
				case 'match':
				case 'permit':
				case 'native':
					foreach ($results as $pfb_result) {
						if (!empty(pfb_filter($pfb_result, PFB_FILTER_WORD, "Remove un-associated List - {$pftype}"))) {
							unlink_if_exists("{$pfbfolder}/{$pfb_result}.txt");
							unlink_if_exists("{$pfb['origdir']}/{$pfb_result}.*");
						} else {
							pfb_logger("\nFailed to remove Native Feed [$pfb_result}]", 1);
						}
					}
					$pfb['summary'] = $pfb['remove'] = TRUE;
					break;
				case 'dnsbl':
					foreach ($results as $pfb_result) {
						if (!empty(pfb_filter($pfb_result, PFB_FILTER_WORD, "Remove un-associated List - {$pftype}"))) {
							unlink_if_exists("{$pfb['dnsorigdir']}/{$pfb_result}.*");
						} else {
							pfb_logger("\nFailed to remove DNSBL Feed [{$pfb_result}]", 1);
						}
					}

					rmdir_recursive("{$pfb['dnsdir']}");
					safe_mkdir("{$pfb['dnsdir']}");

					pfb_logger("\n ** DNSBL Changes found, Reloading...\n", 1);
					$pfb['reuse_dnsbl'] = 'on';
					break;
			}

			// Allow rebuilding of changed Alias to purge 'SKIP' Lists (when pfBlockerNG is enabled)
			if ($pfb['enable'] == 'on' && $pftype != 'dnsbl') {
				foreach ($ip_types as $ltype => $vtype) {
					foreach ($results as $removed_header) {
						foreach (config_get_path("installedpackages/{$ltype}/config", []) as $list) {
							if (!empty($list['row'])) {
								foreach ($list['row'] as $row) {
									$removed = rtrim($removed_header, ',');
									if ($row['header'] == $removed) {
										$pfb['summary'] = $pfb['remove'] = TRUE;

										// Add Alias to update array
										$pfb_alias_lists[]	= "pfB_{$list['aliasname']}{$vtype}";
										$pfb_alias_lists_all[]	= "pfB_{$list['aliasname']}{$vtype}";
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
		pfb_clear_contents();
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

	if (!$pfb['save']) {
		$log = "\n===[  DNSBL Process  ]================================================\n";
		pfb_logger("{$log}", 1);
	}

	// Define DNSBL arrays and variables
	$pfb['alias_dnsbl_all']		= array();	// Array of all DNSBL aliases
	$pfb['tld_update']		= array();	// Array of all DNSBL Aliases/Feeds used for TLD Function
	$pfb['domain_update']		= FALSE;	// Flag to signal update Unbound
	$pfb['updateip']		= FALSE;	// Flag to signal updates to DNSBL IP lists

	$dnsbl_error = FALSE;
	if ($pfb['enable'] == 'on' && $pfb['dnsbl'] == 'on' && !$pfb['save']) {

		// Terminate if DNSBL VIP is empty
		if (empty($pfb['dnsbl_port']) || empty($pfb['dnsbl_port_ssl'])) {
			$log = "\n\n===[  DNSBL Ports are not defined. Exiting  ]======\n";
			pfb_logger("{$log}", 1);
			$dnsbl_error = TRUE;
		}
	}

	if ($pfb['enable'] == 'on' && $pfb['dnsbl'] == 'on' && !$pfb['save'] && !$dnsbl_error) {
		if ((config_get_path('installedpackages/pfblockerngdnsbl/config') != null) ||
		    (config_get_path('installedpackages/pfblockerngblacklist/blacklist_enable') == 'Enable')) {

			$dnsbl_missing = FALSE;

			// Collect existing DNSBL group statistics
			// SQLite3 Database format [ group name, updated timestamp, total domain count, total blocked count ]

			$pfb['dnsbl_info_stats'] = array();
			if (file_exists("{$pfb['dnsbl_info']}")) {
				pfb_logger("\n Loading DNSBL Statistics...", 1);

				$db_handle = pfb_open_sqlite(1, 'Reading DNSBL database');
				if ($db_handle) {
					$result = $db_handle->query("SELECT * FROM dnsbl;");
					if ($result) {
						while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
							$pfb['dnsbl_info_stats'][] = $res;
						}
					}
				}
				else {
					pfb_logger(" FAILED", 1);
					unlink_if_exists("{$pfb['dnsbl_info']}");
					$dnsbl_missing = TRUE;
				}

				pfb_close_sqlite($db_handle);
				pfb_logger(" completed", 1);
			}
			else {
				$dnsbl_missing = TRUE;
			}

			if ($pfb['dnsbl_py_blacklist']) {

				// When DNSBL python blocking mode is enabled, zone should not exist, and data should exist
				if (!$pfb['dnsbl_tld']) {
					if (file_exists($pfb['unbound_py_zone'])) {
						$dnsbl_missing = TRUE;
					}
					if (!file_exists($pfb['unbound_py_data'])) {
						$dnsbl_missing = TRUE;
					}
				}

				// When DNSBL python blocking mode is enabled, atleast one 'data or zone' file must exist
				else {
					$found = FALSE;
					if (file_exists($pfb['unbound_py_data'])) {
						$found = TRUE;
					}
					if (file_exists($pfb['unbound_py_zone'])) {
						$found = TRUE;
					} 
					if (!$found) {
						$dnsbl_missing = TRUE;
					}
				}
			}

			// Check if DNSBL database is missing, when DNSBL python blocking mode is not enabled
			if (!$pfb['dnsbl_py_blacklist'] && !file_exists("{$pfb['dnsbl_file']}.conf")) {
				$dnsbl_missing = TRUE;
			}

			if ($dnsbl_missing) {
				$log = "\n Missing DNSBL stats and/or Unbound DNSBL files - Rebuilding\n";
				pfb_logger("{$log}", 1);
				$pfb['reuse_dnsbl'] = 'on';
				touch("{$pfb['dnsbl_file']}.reload");
			}

			// SafeSearch
			pfb_logger("\n Loading DNSBL SafeSearch...", 1);
			$safesearch_hosts = array();
			$pfb['safesearch_tlds'] = array();
			$safesearch_types = array(	array( 'safesearch_enable',	'Enable',	'safesearch'),
							array( 'safesearch_youtube',	'Strict',	'youtube_restrict'),
							array( 'safesearch_youtube',	'Moderate',	'youtube_restrictmoderate'),
							array( 'safesearch_doh',	'Enable',	'doh')
							);

			$safesearch_update = $pfb_found = FALSE;
			$CNAME_LOG = '';

			// Remove deprecated firefox conf file
			if (file_exists("{$pfb['dnsbldir']}/pfb_dnsbl.firefoxdoh.conf")) {
				unlink_if_exists("{$pfb['dnsbldir']}/pfb_dnsbl.firefoxdoh.conf");
				$safesearch_update = TRUE;
			}

			foreach ($safesearch_types as $safe_type) {
				if ($pfb[$safe_type[0]] == $safe_type[1]) {
					$pfb_found = TRUE;

					// SafeSearch DoH validation (Add/remove comment '#' to DoH entries)
					if ($safe_type[0] == 'safesearch_doh') {
						$doh_file = file($pfb["dnsbl_{$safe_type[2]}"]);
						if (!empty($doh_file)) {
							$doh_file_final = '';
							foreach ($doh_file as $host) {
								if (strpos($host, '#') !== FALSE) {
									$host = ltrim(str_replace('#', '', $host));
								}

								$doh_found = FALSE;
								if (!empty($pfb['safesearch_doh_list'])) {
									foreach ($pfb['safesearch_doh_list'] as $doh) {
										if (strpos($host, $doh) !== FALSE) {
											$doh_found = TRUE;
											break;
										}
									}
								}
								if (!$doh_found) {
									$doh_file_final .= "# {$host}";
								} else {
									$doh_file_final .= "{$host}";
								}
							}
							if (!empty($doh_file_final)) {
								@file_put_contents($pfb["dnsbl_{$safe_type[2]}"], $doh_file_final, LOCK_EX);
							}
						}
					}

					if ($pfb['dnsbl_py_blacklist']) {

						// Python Mode determine if user manually entered these SafeSearch CNAMES to avoid duplicate zone errors
						if ($safe_type[0] == 'safesearch_enable') {
							$DDG = $PIX = $ss_cname = FALSE;
							if (file_exists('/var/unbound/unbound.conf')) {
								$pfb_py_conf_ex = @file_get_contents('/var/unbound/unbound.conf');
								if (strstr($pfb_py_conf_ex, 'duckduckgo.com')) {
									$CNAME_LOG = "\n *** Found manual Resolver entry for duckduckgo.com. This manual entry should be removed in future!\n";
									$DDG = TRUE;
								}
								if (strstr($pfb_py_conf_ex, 'pixabay.com')) {
									$CNAME_LOG .= "\n *** Found manual Resolver entry for pixabay.com. This manual entry should be removed in future!\n";
									$PIX = TRUE;
								}
							}
							$ss_ex = @file_get_contents("{$pfb['dnsbldir']}/pfb_dnsbl.{$safe_type[2]}.conf");
						}
						unlink_if_exists("{$pfb['dnsbldir']}/pfb_dnsbl.{$safe_type[2]}.conf"); // Remove Unbound mode file

						$safesearch_file = file($pfb["dnsbl_{$safe_type[2]}"]);
						if (!empty($safesearch_file)) {
							foreach ($safesearch_file as $host) {

								if (substr($host, 0, 1) == '#') {
									continue;
								}

								$line = str_replace('"', '', strstr($host, '"', False));
								$host_ip = trim(str_replace('A ', '', strstr($line, 'A ', FALSE)));
								$domain = strstr($line, ' ', TRUE);
								if (substr($domain, 0, 4) == 'www.') {
									$domain = substr($domain, 4);
								}

								if (strpos($line, ' A ') !== FALSE) {
									$safesearch_hosts[$domain]['A'] = $host_ip;
								} elseif (strpos($line, ' AAAA ') !== FALSE) {
									$safesearch_hosts[$domain]['AAAA'] = $host_ip;

								# CNAME SafeSearch is not compatible in Python Mode, use Unbound mode for now
								} elseif (strpos($line, ' CNAME ') !== FALSE) {
									$ss_cname = TRUE;
									if (!$DDG && strpos($line, 'duckduckgo.com') !== FALSE) {
										@file_put_contents("{$pfb['dnsbldir']}/pfb_dnsbl.{$safe_type[2]}.conf", "{$host}", FILE_APPEND);
									}
									if (!$PIX && strpos($line, 'pixabay.com') !== FALSE) {
										@file_put_contents("{$pfb['dnsbldir']}/pfb_dnsbl.{$safe_type[2]}.conf", "{$host}", FILE_APPEND);
									}
									# $cname = trim(str_replace('CNAME ', '', strstr($line, 'CNAME', FALSE)));
									# $safesearch_hosts[$domain]['A'] = 'cname';
									# $safesearch_hosts[$domain]['AAAA'] = $cname;
								} elseif (strpos($line, 'always_nxdomain') !== FALSE) {
									$safesearch_hosts[$domain]['nxdomain'] = '';
								}
							}
						}

						if ($safe_type[2] == 'safesearch' && ($ss_cname && $ss_ex !== @file_get_contents("{$pfb['dnsbldir']}/pfb_dnsbl.{$safe_type[2]}.conf")) ||
						    (($DDG || $PIX) && !$ss_cname)) {
							$safesearch_update = TRUE;
						}
					}
					else {
						unlink_if_exists("/var/unbound/pfb_py_ss.*"); // Remove Python mode file
						$ss_cname = FALSE;

						// Determine if user manually entered these SafeSearch CNAMES to avoid duplicate zone errors
						if ($safe_type[0] == 'safesearch_enable') {

							// Step one - See if DDG and PIX are manually configured
							$DDG = $PIX = FALSE;
							if (file_exists('/var/unbound/unbound.conf')) {
								$pfb_py_conf_ex = @file_get_contents('/var/unbound/unbound.conf');
								if (strstr($pfb_py_conf_ex, 'duckduckgo.com')) {
									$CNAME_LOG = "\n *** Found manual Resolver entry for duckduckgo.com. This manual entry should be removed in future!\n";
									$DDG = TRUE;
								}
								if (strstr($pfb_py_conf_ex, 'pixabay.com')) {
									$CNAME_LOG .= "\n *** Found manual Resolver entry for pixabay.com. This manual entry should be removed in future!\n";
									$PIX = TRUE;
								}
							}

							// Step two - If they are configured rebuild SafeSearch file
							$safesearch_file = file($pfb["dnsbl_{$safe_type[2]}"]);
							$ss_lines = '';
							if (!empty($safesearch_file)) {
								foreach ($safesearch_file as $host) {
									if ($DDG && strpos($host, 'duckduckgo.com') !== FALSE) {
										$ss_lines .= "# {$host}";
										$ss_cname = TRUE;
									}
									elseif ($PIX && strpos($host, 'pixabay.com') !== FALSE) {
										$ss_lines .= "# {$host}";
										$ss_cname = TRUE;
									}
									else {
										$ss_lines .= "{$host}";
									}
								}
							}

							// Step three - Compare previous SafeSearch file to new and update if changes are found 
							if ($ss_cname) {
								if (file_exists("{$pfb['dnsbldir']}/pfb_dnsbl.{$safe_type[2]}.conf")) {
									$ss_lines_ex = @file_get_contents("{$pfb['dnsbldir']}/pfb_dnsbl.{$safe_type[2]}.conf");

									if ($ss_lines !== $ss_lines_ex) {
										@file_put_contents("{$pfb['dnsbldir']}/pfb_dnsbl.{$safe_type[2]}.conf", $ss_lines, LOCK_EX);
										$safesearch_update = TRUE;
									}
								}
								else {
									@file_put_contents("{$pfb['dnsbldir']}/pfb_dnsbl.{$safe_type[2]}.conf", $ss_lines, LOCK_EX);
									$safesearch_update = TRUE;
								}
							}
						}

						// Copy file if not exists or has been updated
						if (!file_exists("{$pfb['dnsbldir']}/pfb_dnsbl.{$safe_type[2]}.conf") ||
							@md5_file($pfb["dnsbl_{$safe_type[2]}"]) !==
							@md5_file("{$pfb['dnsbldir']}/pfb_dnsbl.{$safe_type[2]}.conf")) {

							// Temp Workaround for Duplicate CNAME Zone issue:
							if (!$ss_cname) {
								@copy($pfb["dnsbl_{$safe_type[2]}"], "{$pfb['dnsbldir']}/pfb_dnsbl.{$safe_type[2]}.conf");
								$safesearch_update = TRUE;
							}
						}

						// Collect SafeSearch domains for wildcard whitelisting
						exec("cut -d '\"' -f2 {$pfb['dnsbldir']}/pfb_dnsbl.{$safe_type[2]}.conf | cut -d ' ' -f1", $safesearch_hosts);

						// Collect all Domains/TLDs for 'TLD Blacklist' validation (Unbound mode only. Cannot have duplicate zones)
						if (!$pfb['dnsbl_py_blacklist'] && !empty($safesearch_hosts)) {
							foreach ($safesearch_hosts as $host) {
								$safesearch_tlds[$host] = '';
								for ($i= substr_count($host, '.'); $i > 0; $i--) {
									$host = ltrim(strstr($host, '.', FALSE), '.');
									$pfb['safesearch_tlds'][$host] = '';
								}
							}
						}
					}
				}
				elseif (file_exists("{$pfb['dnsbldir']}/pfb_dnsbl.{$safe_type[2]}.conf")) {
					unlink("{$pfb['dnsbldir']}/pfb_dnsbl.{$safe_type[2]}.conf");
					$safesearch_update = TRUE;
				}
			}

			// Python mode create a CSV list of SafeSearch hosts
			if ($pfb['dnsbl_py_blacklist'] && !empty($safesearch_hosts)) {
				foreach ($safesearch_hosts as $host => $data) {
					if (isset($data['nxdomain'])) {
						$line = "{$host},nxdomain,nxdomain\n";
					} else {
						$line = "{$host}," . ($data['A'] ?: '') . ',' . ($data['AAAA'] ?: '') . "\n"
							. "www.{$host}," . ($data['A'] ?: '') . ',' . ($data['AAAA'] ?: '') . "\n";
					}
					@file_put_contents('/var/unbound/pfb_py_ss.raw', $line, FILE_APPEND);
				}

				// Copy file if not exists or has been updated
				if (!file_exists("{$pfb['unbound_py_ss']}") ||
				    @md5_file("{$pfb['unbound_py_ss']}") !==
				    @md5_file('/var/unbound/pfb_py_ss.raw')) {
					@rename('/var/unbound/pfb_py_ss.raw', "{$pfb['unbound_py_ss']}");
					$safesearch_update = TRUE;
					touch("{$pfb['dnsbl_file']}.reload");
				}
				unlink_if_exists('/var/unbound/pfb_py_ss.raw');
			}
			else {
				unlink_if_exists("/var/unbound/pfb_py_ss.*");
			}

			// Wildcard whitelist SafeSearch domains (Unbound mode only)
			$pfb_whitelist = '';
			if (!$pfb['dnsbl_py_blacklist'] && !empty($safesearch_hosts)) {
				$safesearch_hosts = array_unique($safesearch_hosts);
				foreach ($safesearch_hosts as $line) {
					if (!empty($line)) {

						// Remove 'www.' prefix
						if (substr($line, 0, 4) == 'www.') {
							$line = substr($line, 4);
						}

						if ($pfb['dnsbl_py_blacklist']) {
							$pfb_whitelist .= ".{$line},,\n,{$line},,\n";
						} else {
							$pfb_whitelist .= ".{$line} 60\n\"{$line} 60\n";
						}
					}
				}
			}

			$s_log = ' disabled';
			if ($pfb_found) {
				$s_log = ' enabled';
			}
			pfb_logger("{$s_log}{$CNAME_LOG}", 1);

			// Collect Whitelist, create string, and save to file (for grep -vF -f cmd)
			pfb_logger("\n Loading DNSBL Whitelist...", 1);
			$pfb_white = pfbng_text_area_decode($pfb['dnsblconfig']['suppression'], TRUE, FALSE, TRUE);
			if (!empty($pfb_white)) {
				foreach ($pfb_white as $line) {
					if (!empty($line)) {
						$wildcard = FALSE;
						if (substr($line, 0, 1) == '.') {
							$line = ltrim($line, '.');
							$wildcard = TRUE;
						}

						// Remove 'www.' prefix
						if (substr($line, 0, 4) == 'www.') {
							$line = substr($line, 4);
						}

						if ($wildcard) {
							if ($pfb['dnsbl_py_blacklist']) {
								$pfb_whitelist .= ".{$line},,\n,{$line},,\n";
							} else {
								$pfb_whitelist .= ".{$line} 60\n\"{$line} 60\n";
							}
						} else {
							if ($pfb['dnsbl_py_blacklist']) {
								$pfb_whitelist .= ",{$line},,\n,www.{$line},,\n";
							} else {
								$pfb_whitelist .= "\"{$line} 60\n\"www.{$line} 60\n";
							}
						}
					}
				}
			}

			//  Added due to SWC Feed
			if ($pfb['dnsbl_py_blacklist']) {
				$pfb_whitelist .= ",localhost.localdomain,,\n";
			} else {
				$pfb_whitelist .= "\"localhost.localdomain 60\n";
			}

			@file_put_contents("{$pfb['dnsbl_supptxt']}", $pfb_whitelist, LOCK_EX);
			pfb_logger(" completed", 1);

			if (!$pfb['dnsbl_py_blacklist'] && $safesearch_update) {
				$log = "\n DNSBL - SafeSearch changes found - Rebuilding!\n";
				pfb_logger("{$log}", 1);
				$pfb['reuse_dnsbl'] = 'on';
				touch("{$pfb['dnsbl_file']}.reload");
			}

			// Call TOP1M whitelist process
			if ($pfb['dnsbl_alexa'] == 'on') {
				pfb_logger("\n Loading TOP1M Whitelist...", 1);

				// Check if TOP1M database exists
				if (!file_exists("{$pfb['dbdir']}/top-1m.csv")) {
					// Check if TOP1M download already in progress
					exec('/bin/ps -wax', $result_cron);
					if (!preg_grep("/pfblockerng[.]php\s+al/", $result_cron)) {
						$log = "\n TOP1M Database downloading ( approx 21MB ) ... Please wait ...\n";
						pfb_logger("{$log}", 1);
						exec('/usr/local/bin/php /usr/local/www/pfblockerng/pfblockerng.php al');
					}
					else {
						$log = "\n TOP1M download already in process...\n";
						pfb_logger("{$log}", 1);
					}
				}

				// Process TOP1M database
				if (!file_exists("{$pfb['dbdir']}/pfbalexawhitelist.txt") ||
				    file_exists("{$pfb['dbdir']}/top-1m.update")) {
					pfblockerng_top1m();
					$pfb['reuse_dnsbl'] = 'on';
					touch("{$pfb['dnsbl_file']}.reload");
					pfb_logger("\n DNSBL - TOP1M changes found - Rebuilding!\n", 1);
				}
				pfb_logger(" completed", 1);
			}
			pfb_logger("\n", 1);

			// List of invalid Domains to skip parsed failed logging function
			$dnsbl_skip = array_flip (array('broadcasthost',
							'local',
							'localhost',
							'<pre>',
							'Vault',
							'Site',
							'list',
							'::1',
							':',
							'ip6-localhost',
							'ip6-localnet',
							'ip6-mcastprefix',
							'ip6-allnodes',
							'ip6-allrouters',
							'ip6-allhosts'
							));

			// List of Alienvault OTX Indicator Types
			$alienvault_types = array_flip(array('domain', 'hostname', 'URL'));

			// Collect feeds and custom list configuration and format into one array ($lists).
			$lists = array();

			// Add DNSBL Category to '$lists array'
			if (isset($pfb['blconfig']) &&
			    $pfb['blconfig']['blacklist_enable'] != 'Disable' &&
			    !empty($pfb['blconfig']['blacklist_selected'])) {

				$bl_count = 0;
				$bl_validate = FALSE;

				$selected = array_flip(explode(',', $pfb['blconfig']['blacklist_selected'])) ?: array();
				foreach ($pfb['blconfig']['item'] as $item) {
					$type = "{$item['xml']}";

					if (isset($selected[$type]) && !empty($item['selected'])) {

						$bl_count++;
						$categories = explode(',', $item['selected']) ?: array();

						$list			= array();
						$list['aliasname']	= "{$item['title']}";
						$list['action']		= 'unbound';
						$list['logging']	= $pfb['blconfig']['blacklist_logging'] ?: 'enabled';
						$list['filter_alexa']	= '';

						$feedname	= strtolower($item['title']);
						$update_flag	= "{$pfb['dbdir']}/{$feedname}/{$feedname}.update";

						foreach ($categories as $category) {

							// Replace dash to underscore in Header name
							$category = str_replace('-', '_', $category);

							if (!empty($category)) {
								$list['row'][] = array(	'format'	=> 'auto',
											'state'		=> 'Enabled',
											'url'		=> "{$pfb['dbdir']}/{$type}/{$type}_{$category}",
											'header'	=> "{$item['title']}_{$category}"
											);

								// If update available set Update flag for each selected Category
								if (file_exists("{$update_flag}")) {
									touch("{$pfb['dnsdir']}/{$item['title']}_{$category}.update");
								}
							}
						}
						unlink_if_exists("{$update_flag}");

						if (isset($list['row'])) {
							$lists[] = $list;
						}

						// Check if Blacklist database has not been previously downloaded
						if (!is_dir("{$pfb['dbdir']}/{$type}") ||
						    is_dir("{$pfb['dbdir']}/{$type}") && (count(scandir("{$pfb['dbdir']}/{$type}")) <= 2)) {
							if (!is_array($bl_validate)) {
								$bl_validate = array();
							}
							$bl_validate[$type] = $item['size'];
						}
					}
				}

				// Download Blacklist databases that are not previously downloaded
				if ($bl_validate) {

					// Create commandline arguments for download script
					$bl_string = $bl_sources = '';
					foreach ($bl_validate as $type => $size) {
						$bl_string	.= ",{$type}";
						$bl_sources	.= " {$type} (~{$size}MB) |";
					}

					if (!empty(pfb_filter($bl_string, PFB_FILTER_CSV, 'Blacklist database commandline arguments'))) {
						$bl_string	= ltrim($bl_string, ',');
						$bl_sources	= rtrim($bl_sources, ' |');

						// Check if Blacklist download already in progress
						exec('/bin/ps -wax', $result_cron);
						if (!preg_grep("/pfblockerng[.]php\s+?(bl|bls)/", $result_cron)) {

							$log = "\nDownloading Blacklist Database(s) [{$bl_sources} ] ... Please wait ...\n";
							pfb_logger("{$log}", 1);
							exec("/usr/local/bin/php /usr/local/www/pfblockerng/pfblockerng.php bls {$bl_string} 2>&1", $pfb_return);

							if (is_array($pfb_return)) {
								foreach ($pfb_return as $key => $return_output) {

									pfb_logger("{$return_output}\n", 1);

									// On download failure, remove associated Blacklist category configuration
									if (strpos($return_output, 'Failed') !== FALSE) {
										unset($lists[$key]);
									}
								}
							}
						}
						else {
							$log = "\nBlacklist Database download already in process... Try again later...\n";
							pfb_logger("{$log}", 1);

							// Remove Blacklist Category updates until database download is completed
							while ($bl_count != 0) {
								array_pop($lists);
								$bl_count--;
							}
						}
					}
					else {
						pfb_logger("\n Invalid Blacklist arguments [{$bl_string}]", 1);
					}
				}
				else {
					pfb_logger(" Blacklist database(s) ... exists.\n", 1);
				}
			}

			foreach (config_get_path('installedpackages/pfblockerngdnsbl/config', []) as $list) {
				// If only the 'customlist' is defined. Remove the 'List row' data.
				if (isset($list['row']) && empty($list['row'][0]['url'])) {
					unset($list['row']);
				}

				if (!empty($list['custom'])) {
						$list['row'][] = array( 'header'	=> "{$list['aliasname']}_custom",
												'custom'	=> $list['custom'],
												'state'		=> 'Enabled',
												'url'		=> 'custom'
						);
				}

				// Move DNSBL Group to primary position before the Blacklist settings
				if ($list['order'] == 'primary') {
					$list_primary = array();
					$list_primary[] = $list;
					$lists = array_merge($list_primary, $lists);
				} else {
					$lists[] = $list;
				}
			}

			foreach ($lists as $list) {

				// Reset variables once per alias
				$lists_dnsbl_current	= array();		// Array of all active Lists in current alias
				$pfb['aliasupdate']	= FALSE;		// Flag to signal changes to alias
				$pfb['domain_clear']	= FALSE;		// Flag to signal no Aliases defined or all Aliases disabled.
				$alias_cnt		= 0;

				if ($list['action'] != 'Disabled' && isset($list['row'])) {
					$alias				= "DNSBL_{$list['aliasname']}";
					if (empty(pfb_filter($alias, PFB_FILTER_WORD, 'DNSBL - Processes'))) {
						pfb_logger("\n Invalid Aliasname:{$list['aliasname']}, *skipping*", 1);
						continue;
					}
					$pfb['alias_dnsbl_all'][]	= "{$alias}";

					foreach ($list['row'] as $key => $row) {
						if (!empty($row['url']) && $row['state'] != 'Disabled') {

							// Empty header field validation check
							$header		= pfb_filter($row['header'], PFB_FILTER_WORD, 'DNSBL - Processes');
							if (empty($header)) {
								$log = "\n[ {$row['url']} ]{$logtab} Header Field cannot be empty. *Skipping* \n";
								pfb_logger("{$log}", 2);
								continue;
							}
							$header_esc	= escapeshellarg($header);

							$liteparser	= FALSE;	// Minimal DNSBL Parser
							$rev_format	= FALSE;	// Host style format is reversed
							$domain_data_ip	= array();	// Array of IPs found in feed
							$domain_data	= '';		// List of Domains found in feed

							// If row is a custom_list, set flag.
							if (isset($row['custom'])) {
								$custom = TRUE;
							} else {
								$custom = FALSE;
							}

							// Global DNSBL Logging/Blocking mode
							if (!empty($pfb['dnsbl_global_log'])) {
								$list['logging'] = $pfb['dnsbl_global_log'];
							}

							// Force Unbound mode Logging for 'disabled_log' to 'enabled'
							if (!$pfb['dnsbl_py_blacklist'] && $list['logging'] == 'disabled_log') {
								$list['logging'] = 'enabled';
							}

							// If Null Blocking mode is selected, use '0.0.0.0|::0', otherwise utilize DNSBL WebServer/DNSBL VIP
							if ($list['logging'] == 'disabled') {
								$sinkhole_type4 = '0.0.0.0';
								$sinkhole_type6 = '::0';
								$logging_type	= '2';	// Null Blocking no logging
							}
							elseif ($list['logging'] == 'disabled_log') {
								$logging_type	= '0';	// Null Blocking logging
							} else {
								$sinkhole_type4 = "{$pfb['dnsbl_vip4']}";
								$sinkhole_type6 = "{$pfb['dnsbl_vip6']}";
								$logging_type	= '1';	// DNSBL VIP logging
							}

							// Determine 'list' details (return array $pfbarr)
							pfb_determine_list_detail($list['action'], $header, 'pfblockerngdnsblsettings', '0');
							$pfbadv		= $pfbarr['adv'];
							$pfbfolder	= $pfbarr['folder'];
							$pfborig	= $pfbarr['orig'];
							$pfbreuse	= $pfbarr['reuse'];
							$logtab		= $pfbarr['logtab'];

							if (file_exists("{$pfbfolder}/{$header}.txt") &&
							    !file_exists("{$pfbfolder}/{$header}.update") &&
							    !file_exists("{$pfbfolder}/{$header}.fail") &&
							    $pfbreuse == '') {


								if ($row['state'] == 'Hold') {
									$log = "\n[ {$header} ]{$logtab} static hold. [ NOW ]";
								} else {
									$log = "\n[ {$header} ]{$logtab} exists. [ NOW ]";
								}
								pfb_logger("{$log}", 1);

								// Collect existing list stats
								$lists_dnsbl_all[]	= "{$row['header']}.txt";
								$lists_dnsbl_current[]	= "{$row['header']}";
								$file_esc		= escapeshellarg("{$pfbfolder}/{$header}.txt");
								$list_cnt		= exec("{$pfb['grep']} -c ^ {$file_esc}");
								$alias_cnt		= $alias_cnt + $list_cnt;
							}
							else {
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
											$row['format'], 1, '', '', '', '', '', $srcint)) {

											// Determine reason for download failure
											pfb_download_failure($alias, $header, $pfbfolder, $row['url'], $row['format'], '');

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
									$custom_list = pfbng_text_area_decode($row['custom'], FALSE, TRUE, TRUE);
									@file_put_contents("{$file_dwn}.orig", $custom_list, LOCK_EX);
									unset($custom_list);
									$liteparser = TRUE;
								}

								// Variables for Easylists
								$easylist = $validate_header = FALSE;
								$e_replace = array( '||', '.^', '^' );

								$run_once = $csv_parser = FALSE;
								$csv_type = '';
								$ipcount = $ip_cnt = 0;

								// Parse downloaded file for Domain names
								if (($fhandle = @fopen("{$file_dwn}.orig", 'r')) !== FALSE) {
									if (($dhandle = @fopen("{$pfbfolder}/{$header}.bk", 'w')) !== FALSE) {
										while (($line = @fgets($fhandle)) !== FALSE) {

											// Collect original line
											$oline = $line;

											// Validate EasyList/AdBlock/uBlock/ADGuard Feeds
											if (!$validate_header) {
												if (strpos($line, '[Adblock Plus ') !== FALSE ||
												    strpos($line, '[Adblock Plus]') !== FALSE ||
												    strpos($line, '[uBlock Origin') !== FALSE ||
												    strpos($line, '! Title: AdGuard') !== FALSE) {
													$easylist = $validate_header = TRUE;
													continue;
												}
												elseif (substr($line, 0, 1) === '!') {
													continue;
												}
												else {
													$validate_header = TRUE;
												}
											}

											// Remove any '^M' characters
											if (strpos($line, "\r") !== FALSE) {
												$line = rtrim($line, "\x00..\x1F");
											}

											// Remove invalid charaters
											$line = trim($line, " \t\n\r\0\x0B\xC2\xA0");

											if ($easylist) {
												if (substr($line, 0, 2) !== '||' ||
												    substr($line, -1) !== '^' ||
												    strpos($line, '$') !== FALSE ||
												    strpos($line, '*') !== FALSE ||
												    strpos($line, '/') !== FALSE) {
													continue;
												}

												$lite = TRUE;
												$line = str_replace($e_replace, '', $line);
											}
											else {
												// If 'tab' character found, replace with whitespace
												if (strpos($line, "\x09") !== FALSE) {
													$line = str_replace("\x09", ' ', $line);
												}

												// If '%20' found, remove.
												if (strpos($line, '%20') !== FALSE) {
													$line = str_replace('%20', '', $line);
												}

												// Remove comment lines and special format considerations
												if (substr($line, 0, 1) == '#') {
													// Exit (hpHosts) when end of domain names found.
													if (strpos($line, 'Append critical updates below') !== FALSE) {
														break;
													}

													// Spamhaus format validation
													if (strpos($line, 'The Spamhaus Project Ltd') !== FALSE) {
														$rev_format = TRUE;
													}

													if ($line == '#family,type,url,status,first_seen,'
															. 'first_active,last_active,last_update') {
														$csv_type	= 'h3x';
														$csv_parser	= TRUE;
													}
													continue;
												}

												// Remove slash comment lines
												if (substr($line, 0, 2) == '//') {
													continue;
												}

												// Remove any 'End of line' comments (Some contains commas)
												if (strpos($line, ' #') !== FALSE) {
													$line = strstr($line, ' #', TRUE);
												}

												// Convert CSV line into array
												if ($csv_parser) {
													$csvline = str_getcsv($line, ',', '"', '"');
												}
												elseif (!$run_once) {
													if (substr_count($line, ',') >= 2) {
														$csvline = str_getcsv($line, ',', '"', '"');
														$csv_parser = TRUE;
													}
													$run_once = TRUE;
												}
											}

											// Remove blank lines
											if (empty($line)) {
												continue;
											}

											// CSV parser
											if ($csv_parser) {

												$csv_found = FALSE;
												$csv_count = count($csvline);

												switch ($csv_type) {
													case 'pt':
														if ($csv_count == 8) {
															if (strpos($csvline[1], ' ') !== FALSE) {
																$line = str_replace(' ', '', $csvline[1]);
															} else {
																$line = $csvline[1];
															}
															$csv_found = TRUE;
														}
														break;
													case 'bbc':
														if ($csv_count == 4) {
															$line		= $csvline[0];
															$csv_found	= TRUE;
														}
														break;
													case 'h3x':
														if ($csv_count == 8) {
															$line		= $csvline[2];
															if (strpos($line, 'btc://') !== FALSE) {
																continue 2;
															}
															$csv_found	= TRUE;
														}
														break;
													case 'otx':
														if ($csv_count == 3) {
															if (isset($alienvault_types[$csvline[0]])) {
																$line		= $csvline[1];
																$csv_found	= TRUE;
															} else {
																continue 2;
															}
														}
														break;
													case 'pon':
														if ($csv_count == 9) {
															$line		= $csvline[2];
															$csv_found	= TRUE;

															// Collect additional IP csv entry
															if (is_ipaddrv4($csvline[0]) &&
															    $pfb['dnsbl_ip'] != 'Disabled') {
																$parsed = sanitize_ipaddr($line, $custom, 'Disabled');
																if (validate_ipv4($parsed)) {
																	$domain_data_ip[] = $parsed;
																	$pfb['updateip'] = TRUE;
																	$ipcount++;
																}
															}
														}
														break;
													case 'et':
														if ($csv_count == 3) {
															$line		= $csvline[0];
															$csv_found	= TRUE;
														}
														break;
													default:

														// Parse Phishtank Feed
														if (strpos($line, 'phish_id,url,'
																. 'phish_detail_url') !== FALSE) {
															$csv_type = 'pt';
															continue 2;
														}

														// Parse Bambenek Consulting Feed
														elseif (strpos($csvline[3], 'osint.'
															. 'bambenekconsulting.com') !== FALSE) {
															$csv_type	= 'bbc';
															$line		= $csvline[0];
															$csv_found	= TRUE;
															$liteparser	= TRUE;
														}

														// Parse Alienvault OTX pulse Feed
														elseif ($line == 'Indicator type,Indicator,'
																. 'Description') {
															$csv_type	= 'otx';
															$liteparser	= FALSE;
															continue 2;
														}

														// Parse Ponomocup Feed
														elseif (strpos($csvline[0], 'timestamp') !== FALSE) {
															$csv_type	= 'pon';
															$liteparser	= TRUE;
															continue 2;
														}

														// Parse Proofpoint/ET IQRisk IPRep Feed
														elseif ($line == 'domain, category, score') {
															$csv_type	= 'et';
															$liteparser	= TRUE;
															continue 2;
														}

														// Reset variables for CSV determination
														else {
															$csv_parser = $run_once = FALSE;
														}
														break;
												}

												// Record Failed CSV Parse event
												if (!$csv_found || empty($csv_type)) {
													pfb_parsed_fail($header, '', $oline, $pfb['dnsbl_parse_err']);
													continue;
												}
											}
											$line = trim($line);

											if (!$easylist) {

												// Typical Host Feed format - Remove characters before space
												if (!$rev_format && strpos($line, ' ') !== FALSE) {
													$line = trim(strstr($line, ' ', FALSE));
												}

												// Remove characters after space
												if (strpos($line, ' ') !== FALSE) {
													$line = strstr($line, ' ', TRUE);
												}

												// Determine if line contains only an alpha-numeric Domain name
												if (!$liteparser) {

													$lite = FALSE;
													if (strpos($line, '.') !== FALSE &&
													    ctype_alnum(str_replace('.', '', $line))) {
														$lite = TRUE;
													}
												}
												else {
													$lite = TRUE;
												}
											}

											if (!$lite) {

												// If 'http|https|telnet|ftp://' found, remove
												if (strpos($line, '://') !== FALSE) {
													$line = substr($line, strpos($line, '://') + 3);
												}

												// If '/' character found, remove characters after '/'
												if (strpos($line, '/') !== FALSE) {
													$line = strstr($line, '/', TRUE);
												}

												// If '#' character found, remove characters after '#'
												if (strpos($line, '#') !== FALSE) {
													$line = strstr($line, '#', TRUE);
												}

												// If '?' character found, remove characters after '?'
												if (strpos($line, '?') !== FALSE) {
													$line = strstr($line, '?', TRUE);
												}

												// If special characters found, parse line for host
												if (strpos($line, ';') !== FALSE) {
													$host = parse_url($line);
													if (isset($host['host'])) {
														$line = $host['host'];
													} else {
														$line = strstr($line, ';', TRUE);
													}
												}

												// Remove any Port numbers at end of line
												if (strpos($line, ':') !== FALSE) {
													$line = preg_replace("/:[0-9]{1,5}$/", '', $line);
												}
											}
											$line = trim($line);

											// Collect any IPs found in domain feed
											if (is_ipaddrv4($line)) {
												if ($pfb['dnsbl_ip'] != 'Disabled') {
													$parsed = sanitize_ipaddr($line, $custom, 'Disabled');
													if (validate_ipv4($parsed)) {
														$domain_data_ip[] = $parsed;
														$pfb['updateip'] = TRUE;
														$ipcount++;
													}
												}
												continue;
											}

											// Convert IDN (Unicode domains) to ASCII (punycode)
											if (!ctype_print($line)) {

												// Convert encodings to UTF-8
												$line = mb_convert_encoding($line, 'UTF-8',
													mb_detect_encoding($line, 'UTF-8, ASCII, ISO-8859-1'));

												$log = "\n  IDN converted: [ {$line} ]\t";
												$line = idn_to_ascii($line);
												if (!empty($line)) {
													pfb_logger("{$log} [ {$line} ]", 1);
												}
												else {
													// Record failed parsed line
													pfb_parsed_fail($header, '', $oline, $pfb['dnsbl_parse_err']);
													continue;
												}
											}

											// Remove leading/trailing dots
											$line = trim(trim($line), '.');

											// Domain Validation
											if (empty(pfb_filter($line, PFB_FILTER_DOMAIN, 'DNSBL_Download'))) {

												// Reset lite parser
												$liteparser = FALSE;

												// Skip yHost '@' prefixed lines
												if (substr($line, 0, 1) == '@') {
													continue;
												}

												// Log invalid Domains
												if (!isset($dnsbl_skip[$line])) {
													pfb_parsed_fail($header, $line, $oline, $pfb['dnsbl_parse_err']);
												}
												continue;
											}

											// For DNSBL python, save domain and Logging type
											if ($pfb['dnsbl_py_blacklist']) {
												$domain_data = ',' . strtolower($line)
													. ",,{$logging_type},{$header},{$alias}\n";
											}
											else {
												$ipv6_dnsbl = "\n";
												if (!empty($pfb['dnsbl_vip6']) && !$pfb['dnsbl_tld']) {
													$ipv6_dnsbl = " local-data: \"" . strtolower($line)
															. " 60 IN AAAA {$sinkhole_type6}\"\n";
												}
												$domain_data = "local-data: \"" . strtolower($line)
														. " 60 IN A {$sinkhole_type4}\"{$ipv6_dnsbl}";
											}
											@fwrite($dhandle, $domain_data);
										}
									}
									if ($dhandle) {
										@fclose($dhandle);
									}
								}
								if ($fhandle) {
									@fclose($fhandle);
								}
								if (isset($csvline)) {
									unset($csvline);
								}

								// Remove duplicates and save any IPs found in domain feed
								if (!empty($domain_data_ip)) {
									$domain_data_ip = implode("\n", array_unique($domain_data_ip)) . "\n";
									@file_put_contents("{$pfbfolder}/{$header}_v4.ip", $domain_data_ip, LOCK_EX);
									$ip_cnt = exec("{$pfb['grep']} -c ^ " . escapeshellarg("{$pfbfolder}/{$header}_v4.ip"));
								}
								else {
									// Remove previous IP feed
									unlink_if_exists("{$pfbfolder}/{$header}_v4.ip");
								}

								// Validate feed with Unbound-checkconf
								if (!empty($domain_data)) {
									$conf  = "server:\n";
									$conf .= "chroot: {$pfb['dnsbldir']}\n";
									$conf .= "username: \"unbound\"\n";
									$conf .= "directory: \"{$pfb['dnsbldir']}\"\n";
									$conf .= "pidfile: \"/var/run/unbound.pid\"\n";
									$conf .= "server:include: {$pfbfolder}/{$header}.bk";
									@file_put_contents("{$pfb['dnsbldir']}/check.conf", $conf, LOCK_EX);

									pfb_logger(".\n", 1);

									// Bypass TOP1M whitelist, if user configured
									$pfb_alexa = 'Disabled';
									if ($pfb['dnsbl_alexa'] == 'on' &&
									    $list['filter_alexa'] == 'on' &&
									    file_exists("{$pfb['dbdir']}/pfbalexawhitelist.txt")) {
										$pfb_alexa = 'on';
									}

									// DNSBL python requires a different deduplication process
									$dup_mode = '';
									if ($pfb['dnsbl_py_blacklist']) {
										$dup_mode = 'python';
									}

									// Call script to process DNSBL 'De-Duplication / Whitelisting / TOP1M Whitelisting'
									exec("{$pfb['script']} dnsbl_scrub {$header_esc} {$pfb_alexa} {$dup_mode} {$elog}");

									if ($ip_cnt > 0) {
										pfb_logger("  IPv4 count={$ip_cnt}\n", 1);
									}

									if (!$pfb['dnsbl_py_blacklist']) {
										$result = array();
										exec("/usr/local/sbin/unbound-checkconf {$pfb['dnsbldir']}/check.conf 2>&1", $result);
									} else {
										$result = array('unbound-checkconf: no errors');
									}
									unlink_if_exists("{$pfb['dnsbldir']}/check.conf");
								}
								else {
									$log = "\n No Domains Found! Ensure only domain based Feeds are used for DNSBL!\n";
									pfb_logger("{$log}", 1);

									// Copy downloaded file to /tmp for debugging
									$ts = date('M_j', time());
									@copy("{$file_dwn}.orig", "/tmp/Error_{$header}_{$ts}.orig");

									unlink_if_exists("{$pfbfolder}/{$header}.bk");
									$result = array('unbound-checkconf: no errors');
								}

								// If parse error found, use previously downloaded file if available
								if (!$pfb['dnsbl_py_blacklist'] && !preg_grep("/unbound-checkconf: no errors/", $result)) {
									unlink_if_exists("{$pfbfolder}/{$header}.bk");

									pfb_logger("\n  DNSBL FAIL - Skipped! Use previous data, if found:\n", 2);
									$log = htmlspecialchars(implode("\n", $result));
									pfb_logger("{$log}\n", 1);

									// Create failed marker file
									touch("{$pfbfolder}/{$header}.fail");
								}

								// Save DNSBL feed info for next steps
								$pfb['domain_update']	= $pfb['aliasupdate'] = $pfb['summary'] = TRUE;
								$lists_dnsbl_all[]	= "{$row['header']}.txt";
								$lists_dnsbl_current[]	= "{$row['header']}";

								// Rename newly downloaded file to final location
								if (file_exists("{$pfbfolder}/{$header}.bk")) {
									@rename("{$pfbfolder}/{$header}.bk", "{$pfbfolder}/{$header}.txt");
								}

								// Create empty placeholder file
								if (!file_exists("{$pfbfolder}/{$header}.txt")) {
									touch("{$pfbfolder}/{$header}.txt");
								}

								$list_cnt	= exec("{$pfb['grep']} -c ^ " . escapeshellarg("{$pfbfolder}/{$header}.txt"));
								$alias_cnt	= $alias_cnt + $list_cnt;

								// Remove update file indicator
								unlink_if_exists("{$pfbfolder}/{$header}.update");
							}
						}
					}

					// If changes found update DNSBL alias and TLD disabled, call function to update DNSBL alias
					if ($pfb['aliasupdate'] && !$pfb['dnsbl_tld']) {
						dnsbl_alias_update('update', $alias, $pfbfolder, $lists_dnsbl_current, $alias_cnt);
					}

					// Collect Alias/Feeds for post TLD function
					if ($pfb['dnsbl_tld']) {
						if (!is_array($pfb['tld_update'][$alias])) {
							$pfb['tld_update'][$alias] = array();
						}
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

		// Add DNSBL Python options to widget statistics
		if ($pfb['dnsbl_mode'] == 'dnsbl_python') {
			if ($pfb['dnsbl_idn'] == 'on') {
				$idn_cnt = 1;
				$pfb['alias_dnsbl_all'][] = 'DNSBL_IDN';
				dnsbl_alias_update('update', 'DNSBL_IDN', '', '', $idn_cnt);
			}
			if ($pfb['dnsbl_pytld'] == 'on') {
				$pytld_cnt = 0;
				foreach (array('gtld', 'cctld', 'itld', 'bgtld') as $pytld) {
					if (isset($pfb['dnsblconfig']['pfb_pytlds_' . $pytld]) && !empty($pfb['dnsblconfig']['pfb_pytlds_' . $pytld])) {
						$p_cnt = count(explode(',', $pfb['dnsblconfig']['pfb_pytlds_' . $pytld]));
						if (is_numeric($p_cnt) && $p_cnt > 0) {
							$pytld_cnt += $p_cnt;
						}
					}
				}

				$pfb['alias_dnsbl_all'][] = 'DNSBL_TLD_Allow';
				dnsbl_alias_update('update', 'DNSBL_TLD_Allow', '', '', $pytld_cnt);
			}
			if ($pfb['dnsbl_regex'] == 'on') {
				$regex_cnt = 0;
				if (isset($pfb['dnsbl_regex_list'])) {
					$regex_cnt = count(pfbng_text_area_decode($pfb['dnsbl_regex_list'], TRUE, FALSE, FALSE)) ?: 0;
				}
				$pfb['alias_dnsbl_all'][] = 'DNSBL_Regex';
				dnsbl_alias_update('update', 'DNSBL_Regex', '', '', $regex_cnt);
			}
		}

		// Save DNSBL Alias statistics (Not for TLD mode)
		if ($pfb['domain_update'] && !$pfb['dnsbl_tld']) {
			dnsbl_save_stats();
		}
	}

	// Collect all DNSBL IP feeds (IPv4 only) into DNSBLIP_v4.txt
	if ($pfb['dnsbl_ip'] != 'Disabled' && ($pfb['updateip'] || !file_exists("{$pfb['dbdir']}/DNSBLIP_v4.txt"))) {

		$dnsbl_ip = glob("{$pfb['dnsdir']}/*_v4.ip");
		if (!empty($dnsbl_ip)) {
			$pfb_ips = @fopen("{$pfb['dbdir']}/DNSBLIP_v4.txt", 'w');
			foreach ($dnsbl_ip as $d_ip) {
				if (($handle = @fopen("{$d_ip}", 'r')) !== FALSE) {
					while (($line = @fgets($handle)) !== FALSE) {
						@fwrite($pfb_ips, $line);
					}
				}
				if ($handle) {
					@fclose($handle);
				}
			}
			if ($pfb_ips) {
				@fclose($pfb_ips);
			}
		}

		// Add empty placeholder IP
		else {
			@file_put_contents("{$pfb['dbdir']}/DNSBLIP_v4.txt", "{$pfb['ip_ph']}\n", LOCK_EX);
		}
		unlink_if_exists($pfb['ip_cache']);
		touch("{$pfb['denydir']}/DNSBLIP_v4.update");
	}

	// Remove DNSBL IP feed, if disabled
	if ($pfb['dnsbl_ip'] == 'Disabled') {
		unlink_if_exists("{$pfb['dbdir']}/DNSBLIP_v4.txt");
		unlink_if_exists("{$pfb['denydir']}/DNSBLIP_v4.*");
	}

	#########################################
	#	UPDATE Unbound DNS Database	#
	#########################################

	if ($pfb['domain_update']) {
		if (!empty($lists_dnsbl_all)) {
			pfb_logger("\n------------------------------------------------------------------------\n", 1);

			pfb_logger('Assembling DNSBL database...', 1);
			unlink_if_exists("{$pfb['dnsbl_file']}.raw");
			$pfb_output = @fopen("{$pfb['dnsbl_file']}.raw", 'w');
			foreach ($lists_dnsbl_all as $current_list) {
				if (($handle = @fopen("{$pfb['dnsdir']}/{$current_list}", 'r')) !== FALSE) {
					while (($line = @fgets($handle)) !== FALSE) {
						@fwrite($pfb_output, $line);
					}
				}
				if ($handle) {
					@fclose($handle);
				}
			}
			if ($pfb_output) {
				@fclose($pfb_output);
			}
			pfb_logger("... completed [ NOW ]", 1);

			// DNSBL Python blocking mode, if TLD is not enabled
			if ($pfb['dnsbl_py_blacklist'] && !$pfb['dnsbl_tld']) {
				unlink_if_exists($pfb['unbound_py_data']);
				unlink_if_exists($pfb['unbound_py_zone']);
				unlink_if_exists($pfb['unbound_py_count']);
				rename("{$pfb['dnsbl_file']}.raw", $pfb['unbound_py_data']);
			}
		}

		else {
			$log = "\nDNSBL not Updated!\n";
			pfb_logger("{$log}", 1);
		}
	}
	else {
		if ($pfb['enable'] == 'on' && $pfb['dnsbl'] == 'on') {

			// When DNSBL is enabled and no Aliases are defined, or all Aliases are Disabled
			if (empty($lists_dnsbl_all) && !$pfb['save']) {
				pfb_logger("\nClearing all DNSBL Feeds", 1);
				$pfb['domain_clear'] = TRUE;

				// Clear out Unbound pfb_dnsbl.conf file
				if (!$pfb['dnsbl_py_blacklist']) {
					$pfb_output = @fopen("{$pfb['dnsbl_file']}.conf", 'w');
					@fwrite($pfb_output, '');
					@fclose($pfb_output);
				}

				// Remove DNSBL Python files
				else {
					unlink_if_exists($pfb['unbound_py_data']);
					unlink_if_exists($pfb['unbound_py_zone']);
					unlink_if_exists($pfb['unbound_py_wh']);
					unlink_if_exists($pfb['unbound_py_count']);
				}
			}
		}
		else {
			foreach (array("{$pfb['dnsbl_file']}.conf", $pfb['unbound_py_data'], $pfb['unbound_py_zone']) as $pfb_file) {
				if (file_exists($pfb_file)) {
					$pfb['domain_clear'] = TRUE;
					@unlink($pfb_file);
				}
			}
		}
	}

	#################################
	#	UNBOUND INTEGRATION	#
	#################################

	$pfbupdate = $pfbpython = FALSE;
	if ($pfb['enable'] == 'on' && $pfb['dnsbl'] == 'on' && $pfb['unbound_state'] == 'on') {
		$mode = 'enabled';
	}
	elseif (($pfb['enable'] == '' || $pfb['dnsbl'] == '') && !$pfb['install']) {
		$mode = 'disabled';
	}

	// Modify Unbound python configuration and mount lib/bin folders, as required
	$pfbpython = pfb_unbound_python($mode);

	// Modify Unbound.conf file, as required
	$pfbupdate = pfb_unbound_dnsbl($mode);

	// Modify DNSBL NAT and VIP and lighttpd web server conf, as required.
	pfb_create_dnsbl($mode);

	// Load new DNSBL updates to Unbound Resolver, as required
	if ($pfb['domain_update'] || $pfbupdate || $pfbpython ||$pfb['domain_clear'] || $safesearch_update) {

		// Create backup of existing DNSBL Unbound database
		if (!$pfb['dnsbl_py_blacklist'] && file_exists("{$pfb['dnsbl_file']}.conf")) {
			@copy("{$pfb['dnsbl_file']}.conf", "{$pfb['dnsbl_file']}.bk");
		}

		pfb_update_unbound($mode, $pfbupdate, $pfbpython);
	}


	#################################
	#	Assign Countries	#
	#################################

	if (!$pfb['save']) {
		$log = "\n\n===[  GeoIP Process  ]============================================\n";
		pfb_logger("{$log}", 1);
	}

	// Download MaxMind Databases if not found
	$maxmind_verify = FALSE;
	if (!empty($pfb['maxmind_key']) && !empty($pfb['maxmind_account'])) {

		$maxmind_verify = TRUE;
		if (!file_exists("{$pfb['geoipshare']}/GeoLite2-Country.mmdb") ||
		    !file_exists("{$pfb['geoipshare']}/GeoLite2-Country-Blocks-IPv4.csv") ||
		    !file_exists("{$pfb['dbdir']}/geoip.txt") ||
		    !file_exists("{$pfb['ccdir']}/Top_Spammers_v4.info")) {

			// Check if MaxMind download already in progress
			exec('/bin/ps -wax', $result_cron);
			if (!preg_grep("/pfblockerng[.]php\s+dc/", $result_cron)) {
				$log = "\nMaxMind Database downloading and processing ( approx 4MB ) ... Please wait ...\n";
				pfb_logger("{$log}", 1);
				exec("/usr/local/bin/php /usr/local/www/pfblockerng/pfblockerng.php dc >> {$pfb['log']} 2>&1");
				restart_service('pfb_filter');
			}
			else {
				$log = "\nMaxMind download already in process...\n";
				pfb_logger("{$log}", 1);
			}
		}
	}

	$maxmind_run_once = TRUE;
	foreach ($pfb['continents'] as $continent => $pfb_alias) {
		$cont_key = 'pfblockerng' . strtolower(str_replace(' ', '', $continent));
		if (!empty(config_get_path("installedpackages/{$cont_key}/config"))) {
			$continent_config = config_get_path("installedpackages/{$cont_key}/config/0");
			$cc_name = 'pfblockerng' . strtolower(str_replace(' ', '', $continent));
			if (isset($continent_config['action']) && $continent_config['action'] != 'Disabled' && $pfb['enable'] == 'on') {

				// Maxmind License Key verification and user notification
				if ($maxmind_run_once && !$maxmind_verify) {
					$mmsg = 'MaxMind now requires a License Key! Review the IP tab: MaxMind settings for more information.';
					pfb_logger("\n\nURGENT:\n    {$mmsg}\n", 1);
					file_notice('pfBlockerNG MaxMind', $mmsg, 'pfBlockerNG', '/pfblockerng/pfblockerng_ip.php', 2);
					$maxmind_run_once = FALSE;
				}

				$urlvalue = '';	// Firewall: Aliases value field

				// Determine if Continent lists require action (IPv4 and IPv6)
				foreach ($cont_types as $c_type => $vtype) {

					$cc_alias = "{$pfb_alias}{$vtype}";

					// Determine 'list' details (return array $pfbarr)
					pfb_determine_list_detail($continent_config['action'], "{$cc_alias}", $cc_name, '0');
					$pfbadv		= $pfbarr['adv'];
					$pfbdescr	= $pfbarr['descr'];
					$pfbfolder	= $pfbarr['folder'];
					$pfborig	= $pfbarr['orig'];
					$logtab		= $pfbarr['logtab'];

					if (!empty($continent_config[$c_type])) {

						// Collect selected GeoIP ISOs
						if (($pfb_output = @fopen("{$pfb['geoip_tmp']}", 'w')) !== FALSE) {
							foreach (explode(',', $continent_config[$c_type]) as $iso) {

								$urlvalue .= "{$iso},";
								$isofile = "{$pfb['ccdir']}/{$iso}{$vtype}.txt";
								if (($handle = @fopen("{$isofile}", 'r')) !== FALSE) {
									while (($line = @fgets($handle)) !== FALSE) {
										@fwrite($pfb_output, $line);
									}
								}
								else {
									pfb_logger("\nCould not open ISO [ {$iso}{$vtype} ]\n", 1);
								}
								if ($handle) {
									@fclose($handle);
								}
							}
						}
						else {
							pfb_logger("\n[ {$cc_alias} ] Could not create GeoIP file handle\n", 1);
						}
						if ($pfb_output) {
							@fclose($pfb_output);
						}

						// Collect md5 of new Continent data
						$continent		= 'md5_0';
						if (file_exists("{$pfb['geoip_tmp']}")) {
							$continent	= @md5_file("{$pfb['geoip_tmp']}");
						}

						// Collect md5 of existing Continent data
						$continent_ex		= 'md5_1';
						if (file_exists("{$pfborig}/{$cc_alias}.orig")) {
							$continent_ex	= @md5_file("{$pfborig}/{$cc_alias}.orig");
						}

						// Check if pfBlockerNG pfctl Continent tables are empty (pfBlockerNG was disabled w/ "keep", then re-enabled)
						$pfctlck = exec("{$pfb['pfctl']} -vvsTables | {$pfb['grep']} -A1 {$cc_alias} | {$pfb['awk']} '/Addresses/ {s+=\$2}; END {print s}'");

						if (empty($pfctlck) && file_exists("{$pfbfolder}/{$cc_alias}.txt")) {
							@copy("{$pfbfolder}/{$cc_alias}.txt", "{$pfb['aliasdir']}/{$cc_alias}.txt");
							// Collect updated alias lists ('Reputation' disabled)
							$pfb_alias_lists[] = "{$cc_alias}";
						}

						// Collect active alias lists (Used for pfctl update when 'Reputation' is enabled).
						$pfb_alias_lists_all[] = "{$cc_alias}";

						// Compare existing (original file) and new Continent data
						if ($continent == $continent_ex && !empty($pfctlck)
						    && file_exists("{$pfbfolder}/{$cc_alias}.txt") && $pfb['reuse'] == ''
						    && !file_exists("{$pfb['dbdir']}/geoip.update")) {
							if (!$pfb['save']) {
								$log = "\n[ {$cc_alias} ]{$logtab} exists. [ NOW ]";
								pfb_logger("{$log}", 1);
							}
						} else {
							// Do not proceed with changes on user 'save'
							if (!$pfb['save']) {
								$log = "\n[ {$cc_alias} ]{$logtab} Changes found... Updating\n";
								pfb_logger("{$log}", 1);

								// Execute Reputation functions, when changes are found.
								if ($pfbadv && $vtype == '_v4') {
									$pfb['repcheck'] = TRUE;
								}

								// Collect updated alias lists ('Reputation' disabled)
								$pfb_alias_lists[] = "{$cc_alias}";

								if ($continent != 'md5_0') {
									@rename("{$pfb['geoip_tmp']}", "{$pfborig}/{$cc_alias}.orig");
									@copy("{$pfborig}/{$cc_alias}.orig", "{$pfbfolder}/{$cc_alias}.txt");

									// Call Aggregate process
									if ($pfb['agg'] == 'on' && $vtype == '_v4') {
										exec("{$pfb['script']} cidr_aggregate {$cc_alias} {$pfbfolder} {$elog}");
									}

									// Call Duplication process
									if ($pfb['dup'] == 'on' && $vtype == '_v4' && $pfbadv) {
										exec("{$pfb['script']} continent {$cc_alias} {$elog}");
									}

									// Save Continent data to aliastable folder
									@copy("{$pfbfolder}/{$cc_alias}.txt", "{$pfb['aliasdir']}/{$cc_alias}.txt");
								}

								// Check if file exists and is > 0 in size and save alias file
								$file_chk = 0;
								$cont_chk = "{$pfbfolder}/{$cc_alias}.txt";
								if (file_exists($cont_chk) && @filesize($cont_chk) > 0) {
									$file_chk = exec("{$pfb['grep']} -cv '^#\|^\$' {$cont_chk}");
								}

								if ($file_chk <= 1) {
									if ($vtype == '_v6') {
                                                                                $p_ip = "::{$pfb['ip_ph']}";
                                                                        } else {
                                                                                $p_ip = $pfb['ip_ph'];
                                                                        }

									@file_put_contents("{$pfbfolder}/{$cc_alias}.txt", "{$p_ip}\n", LOCK_EX);
									@copy("{$pfbfolder}/{$cc_alias}.txt", "{$pfb['aliasdir']}/{$cc_alias}.txt");
									$log = "[ {$cc_alias} ] Found no unique IPs, adding '{$p_ip}' to avoid empty file\n";
									pfb_logger("{$log}", 1);
								}
							}
						}

						if (file_exists("{$pfbfolder}/{$cc_alias}.txt")) {
							// Create alias config
							$new_aliases_list[] = "{$cc_alias}";
							$new_aliases[] = array( 'name'		=> "{$cc_alias}",
										'url'		=> "{$pfb['weblocal']}?pfb={$cc_alias}",
										'updatefreq'	=> '32',
										'address'	=> '',
										'descr'		=> "pfBlockerNG {$pfbdescr} GeoIP Alias [ {$urlvalue} ]",
										'type'		=> 'urltable',
										'detail'	=> 'DO NOT EDIT THIS ALIAS'
										);

							// Define firewall rule settings
							pfb_firewall_rule($continent_config['action'], $cc_alias, $vtype, $continent_config['aliaslog'],
							    $pfbarr['agateway_in'], $pfbarr['agateway_out'], $pfbarr['aaddrnot_in'], $pfbarr['aaddr_in'],
							    $pfbarr['aports_in'], $pfbarr['aproto_in'], $pfbarr['anot_in'], $pfbarr['aaddrnot_out'],
							    $pfbarr['aaddr_out'], $pfbarr['aports_out'], $pfbarr['aproto_out'], $pfbarr['anot_out']);
						}
						else {
							// unlink Continent list
							unlink_if_exists("{$pfb['aliasdir']}/{$cc_alias}.txt");
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
	$pfb['range']	= '/((?:(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\.){3}(?:25[0-5]|2[0-4]\d|[01]?\d\d?))-((?:(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\.){3}(?:25[0-5]|2[0-4]\d|[01]?\d\d?))/';
	$pfb['ipv4']	= '/(?:(?:25[0-5]|2[0-4]\d|[01]?\d\d?)\.){3}(?:25[0-5]|2[0-4]\d|[01]?\d\d?)(?:\/(?:\d|[12]\d|3[0-2])\b)?/';

	// IPv6 REGEX Definitions - Reference: http://labs.spritelink.net/regex
	$pfb['ipv6'] = '/(?:(?:(?:[[:xdigit:]]{1,4}:){7}(?:[[:xdigit:]]{1,4}|:))|(?:(?:[[:xdigit:]]{1,4}:){6}(?::[[:xdigit:]]{1,4}|(?:(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(?:\.(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(?:(?:[[:xdigit:]]{1,4}:){5}(?:(?:(?::[[:xdigit:]]{1,4}){1,2})|:(?:(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(?:\.(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(?:(?:[[:xdigit:]]{1,4}:){4}(?:(?:(?::[[:xdigit:]]{1,4}){1,3})|(?:(?::[[:xdigit:]]{1,4})?:(?:(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(?:\.(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(?:(?:[[:xdigit:]]{1,4}:){3}(?:(?:(?::[[:xdigit:]]{1,4}){1,4})|(?:(?::[[:xdigit:]]{1,4}){0,2}:(?:(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(?:\.(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(?:(?:[[:xdigit:]]{1,4}:){2}(?:(?:(?::[[:xdigit:]]{1,4}){1,5})|(?:(?::[[:xdigit:]]{1,4}){0,3}:(?:(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(?:\.(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(?:(?:[[:xdigit:]]{1,4}:){1}(?:(?:(?::[[:xdigit:]]{1,4}){1,6})|(?:(?::[[:xdigit:]]{1,4}){0,4}:(?:(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(?:\.(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(?::(?:(?:(?::[[:xdigit:]]{1,4}){1,7})|(?:(?::[[:xdigit:]]{1,4}){0,5}:(?:(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(?:\.(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:)))(?:%.+)?(?:\/(?:1(?:2[0-8]|[01]\d)|[1-9]?\d)\b)?/';

	if ($pfb['enable'] == 'on' && !$pfb['save']) {

		$pfb['supp_update'] = FALSE;
		$runonce_v4 = $runonce_v6 = TRUE;
		$lists = array();

		// Collect lists and custom list configuration and format into one array ($lists).
		foreach	($ip_types as $ip_type	=> $vtype) {
			foreach (config_get_path("installedpackages/{$ip_type}/config", []) as $key => $list) {
				if (!is_array($list)) {
					$list = array();
				}
				if (!is_array($list['row'])) {
					$list['row'] = array();
				}

				$list['vtype']	= "{$vtype}";	// Collect list IP type
				$list['key']	= "{$key}";	// Collect list array key location

				// If only the 'customlist' is defined. Remove the 'List row' data.
				if (empty(array_get_path($list, 'row/0/url'))) {
					unset($list['row']);
				}

				if (!empty($list['custom'])) {
					array_init_path($list, 'row');
					$list['row'][] = array(	'header'	=> "{$list['aliasname']}_custom",
											'custom'	=> $list['custom'],
											'state'		=> 'Enabled',
											'url'		=> 'custom'
					);
				}
				$lists[] = $list;
			}

			// Add DNSBLIP, if configured (IPv4 only)
			if ($pfb['dnsbl'] == 'on' && $pfb['dnsbl_ip'] != 'Disabled' && $vtype == '_v4') {

				$list = array(	'aliasname'	=> 'DNSBLIP',
						'vtype'		=> "{$vtype}",
						'key'		=> 0,
						'dnsblip'	=> '',
						'action'	=> "{$pfb['dnsbl_ip']}",
						);

				$list['row'][] = array(	'format'	=> 'auto',
							'state'		=> 'Enabled',
							'url'		=> "{$pfb['dbdir']}/DNSBLIP{$vtype}.txt",
							'header'	=> 'DNSBLIP');
				$lists[] = $list;
			}
		}

		$maxmind_run_once = $asn_run_once = TRUE;
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
				$alias = "pfB_{$list['aliasname']}{$list['vtype']}";	// Capture Alias name
				if (empty(pfb_filter($alias, PFB_FILTER_WORD, 'Download and Collect IPv4/IPv6 lists'))) {
					pfb_logger("\n Invalid Aliasname:{$list['aliasname']}{$list['vtype']}, *skipping*", 1);
					continue;
				}

				foreach	($list['row'] as $row) {
					if (!empty($row['url']) && $row['state'] != 'Disabled') {
						$header = "{$row['header']}{$list['vtype']}";	// Capture Header/Label name
						if (empty(pfb_filter($header, PFB_FILTER_WORD, 'Download and Collect IPv4/IPv6 lists'))) {
							pfb_logger("\n Invalid Aliasname:{$list['aliasname']}{$list['vtype']} | Header:{$row['header']}{$list['vtype']}, *skipping*", 1);
							continue;
						}
						$header_esc = escapeshellarg($header);

						// If row is a custom_list, set	flag.
						if (isset($row['custom'])) {
							$custom	= TRUE;
						} else {
							$custom	= FALSE;
						}

						// Maxmind License Key verification
						if ($maxmind_run_once && $row['format'] == 'geoip') {
							$mmsg = 'MaxMind now requires an Account ID and License Key! Review the IP tab: MaxMind settings for more information.';
							if (empty($pfb['maxmind_key']) || empty($pfb['maxmind_account'])) {
								pfb_logger("\n\nURGENT:\n    {$mmsg}.\n", 1);
								file_notice('pfBlockerNG MaxMind', $mmsg, 'pfBlockerNG', '/pfblockerng/pfblockerng_ip.php', 2);
							}
							$maxmind_run_once = FALSE;
						}

						// IPinfo ASN Token verification
						if ($asn_run_once && $row['format'] == 'asn') {
							$mmsg = 'To utilize the ASN functionality, you must register for a free IPinfo Account. Review IP Tab for more information.';
							if (empty($pfb['asn_token'])) {
								pfb_logger($mmsg, $logtype);
								file_notice('pfBlockerNG ASN', $mmsg, 'pfBlockerNG', '/pfblockerng/pfblockerng_ip.php', 2);
							}
							$asn_run_once = FALSE;
						}

						// IPv4 Advanced Tunables
						$pfbcidr = 'Disabled';
						if (isset($list['suppression_cidr']) && $list['suppression_cidr'] != 'Disabled' && is_numeric($list['suppression_cidr'])) {
							$pfbcidr = "{$list['suppression_cidr']}";
						}
						
						// cURL Source Interface (sets CURLOPT_INTERFACE)
						$srcint = $list['srcint'] ?: FALSE;

						// IP v4/6 Advanced Tunable - (Pre/Post Script processing)
						$pfb_script_pre = FALSE;
						if (isset($list['script_pre']) && !empty($list['script_pre'])) {
							$script_pre = basename($list['script_pre']);
							if (file_exists("/usr/local/pkg/pfblockerng/{$script_pre}")) {
								$pfb_script_pre = "/usr/local/pkg/pfblockerng/{$script_pre}";
							}
						}

						$pfb_script_post = FALSE;
						if (isset($list['script_post']) && !empty($list['script_post'])) {
							$script_post = basename($list['script_post']);
							if (file_exists("/usr/local/pkg/pfblockerng/{$script_post}")) {
								$pfb_script_post = "/usr/local/pkg/pfblockerng/{$script_post}";
							}
						}

						// Determine 'list' details (return array $pfbarr)
						if (isset($list['dnsblip'])) {
							$list_type = 'pfblockerngdnsblsettings';
						} else {
							$list_type = "{$ip_type}";
						}

						pfb_determine_list_detail($list['action'], $header, $list_type, $list['key']);
						$pfbadv		= $pfbarr['adv'];
						$pfbfolder	= $pfbarr['folder'];
						$pfborig	= $pfbarr['orig'];
						$pfbreuse	= $pfbarr['reuse'];
						$logtab		= $pfbarr['logtab'];

						// Collect active alias list (Used for pfctl update when 'Reputation' is enabled.
						$pfb_alias_lists_all[] = "{$alias}";

						// Set update flags on new downloads available for GeoIP and ASN
						if ($row['format'] == 'geoip' && file_exists("{$pfb['dbdir']}/geoip.update")) {
							touch("{$pfbfolder}/{$header}.update");
						}
						if ($row['format'] == 'asn' && file_exists("{$pfb['dbdir']}/asn.update")) {
							touch("{$pfbfolder}/{$header}.update");
						}

						if (file_exists("{$pfbfolder}/{$header}.txt") &&
						    !file_exists("{$pfbfolder}/{$header}.update") &&
						    !file_exists("{$pfbfolder}/{$header}.fail") &&
						    $pfbreuse == '') {

							if ($row['state'] == 'Hold') {
								$log = "\n[ {$header} ]{$logtab} static hold. [ NOW ]";
							} else {
								$log = "\n[ {$header} ]{$logtab} exists. [ NOW ]";
							}
							pfb_logger("{$log}", 1);
						}
						else {
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

								// Adjust 'geoip' format to GeoIP path location
								if ($row['format'] == 'geoip') {
									if (strpos($row['url'], ' ') !== FALSE) {
										$row['url'] = strstr($row['url'], ' ', TRUE);
									}
									if (!empty(pfb_filter($row['url'], PFB_FILTER_WORD, 'Adjust geoip format to GeoIP path location'))) {
										$row['url'] = "/usr/local/share/GeoIP/cc/{$row['url']}{$list['vtype']}.txt";
									} else {
										$row['url'] = '';
									}
								}

								// Remove 'whois' source field description
								elseif ($row['format'] == 'asn') {
									if (strpos($row['url'], ' ') !== FALSE) {
										$row['url'] = strstr($row['url'], ' ', TRUE);
									}
								}

								// Determine if	list needs to be downloaded or reuse previously downloaded file.
								if ($pfbreuse == 'on' && file_exists("{$file_dwn}.orig")) {
									// File exists/reuse

									// Process Emerging Threats IQRisk if required
									if (strpos($row['url'], 'iprepdata.txt') !== FALSE) {
										if (file_exists("{$file_dwn}.raw")) {
											$file_dwn_esc = escapeshellarg("{$file_dwn}.raw");
											$file_org_esc = escapeshellarg("{$file_dwn}.orig");
											exec("/usr/bin/gunzip -c {$file_dwn_esc} > {$file_org_esc}");
										}
										exec("{$pfb['script']} et {$header_esc} x x x x x {$pfb['etblock']} {$pfb['etmatch']} {$elog}");
									}
								}
								else {
									// Download list
									if (!pfb_download($row['url'], $file_dwn, $pflex, $header, $row['format'],
										1, $list['vtype'], '', '', '', '', $srcint)) {

										// Determine reason for download failure
										pfb_download_failure($alias, $header, $pfbfolder, $row['url'], $row['format'], $list['vtype']);

										// Utilize previously download file (If 'fail' marker exists)
										if (file_exists("{$pfbfolder}/{$header}.fail") &&
										    file_exists("{$file_dwn}.orig")) {
											pfb_logger("\n  Restoring previously downloaded file contents...", 2);
										}
										else {
											if ($pfbadv) {
												// Script to Remove failed lists from masterfile
												exec("{$pfb['script']} remove x x x {$header_esc} {$elog}");
											}
											continue;
										}
									}
									else {
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
									$custom_list = str_replace("\n", ',', pfbng_text_area_decode($list['custom'], FALSE, TRUE, TRUE));
									if (!empty(pfb_filter($custom_list, PFB_FILTER_CSV_WHOIS, 'Process Domain/AS based custom list'))) {
										exec("{$pfb['script']} whoisconvert {$header_esc} {$list['vtype']} {$custom_list} {$elog}");
									} else {
										pfb_logger("\nFailed to process customlist [AS/Whois convert | " . htmlspecialchars($custom_list) . " ]", 1);
									}
								}
								else {
									// Process IP based custom list
									$custom_list = pfbng_text_area_decode($list['custom'], FALSE, TRUE, FALSE);
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

							// IPv4/6 Advanced Tunable - (Pre Script processing)
							if ($pfb_script_pre && file_exists("{$pfb_script_pre}")) {
								pfb_logger("\nExecuting pre-script: {$list['script_pre']}\n", 1);
								$file_dwn_esc = escapeshellarg("{$file_dwn}.orig");
								@copy("{$file_dwn}.orig", "{$file_dwn}.orig.pre"); // Save original file for restoration
								exec("{$pfb_script_pre} {$file_dwn_esc} {$list['vtype']} {$elog}");
							}

							if (($fhandle = @fopen("{$file_dwn}.orig", 'r')) !== FALSE) {
								while (($line = @fgets($fhandle)) !== FALSE) {
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
														$cidr = sanitize_ipaddr($cidr, $custom, $pfbcidr);
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
											$parsed = sanitize_ipaddr($line, $custom, $pfbcidr);
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

										if (strpos($oline, '-') !== FALSE && strpos($oline, '.') !== FALSE) {
											// Network range 192.168.0.0-192.168.0.254
											if (preg_match($pfb['range'], $oline, $matches)) {
												$a_cidr = ip_range_to_subnet_array($matches[1], $matches[2]);
												if (!empty($a_cidr)) {
													foreach ($a_cidr as $cidr) {
														$cidr = sanitize_ipaddr($cidr, $custom, $pfbcidr);
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

												// Workaround to skip cloudflare error pages
												if (strpos($oline, 'cf-footer-item') === FALSE) {
													$parsed = sanitize_ipaddr($match, $custom, $pfbcidr);
													if (validate_ipv4($parsed)) {
														$ip_data .= $parsed . "\n";
													}
												}
											}
											continue;
										}
									}

									if ($list['vtype'] == '_v6') {
										// Auto IPv6 parser
										if ($pftype == 'auto') {
											if (strpos($line, ':') !== FALSE) {

												// Remove any comments
												if (strpos($line, '#') !== FALSE) {
													$line = str_replace('#', '', strstr($line, '#', TRUE));
												}

												if (validate_ipv6($line)) {
													$ip_data .= $line . "\n";
													continue;
												}
											}
										}

										// Range parser
										if (strpos($line, '-') !== FALSE && strpos($line, ':') !== FALSE) {
											$matches = explode('-', $line);
											if (count($matches) == 2) {
												$a_cidr = ip_range_to_subnet_array($matches[0],$matches[1]);
												if (!empty($a_cidr)) {
													foreach ($a_cidr as $cidr) {
														if (!empty($cidr)) {
															if (validate_ipv6($cidr)) {
																$ip_data .= $cidr . "\n";
															}
															else {
																$parse_error = TRUE;
															}
														}
													}
												}
												if (!$parse_error) {
													continue;
												}
											}
											else {
												$parse_error = TRUE;
											}
										}

										// IPv6 Regex parser
										if (preg_match_all($pfb['ipv6'], $oline, $matches)) {
											$matches = array_unique($matches[0]);
											foreach ($matches as $match) {

												// Remove any comments
												if (strpos($match, '#') !== FALSE) {
													$match = str_replace('#', '', strstr($match, '#', TRUE));
												}

												// Workaround to skip cloudflare error pages
												if (strpos($oline, 'cf-footer-item') === FALSE) {
													if (validate_ipv6($match)) {
														$ip_data .= $match . "\n";
													}
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
							if ($fhandle) {
								@fclose($fhandle);
							}
							pfb_logger("\n", 1);

							// IP v4/6 Advanced Tunable - (Post Script processing)
							if ($pfb_script_post && file_exists("{$pfb_script_post}")) {
								pfb_logger("\nExecuting post-script: {$list['script_pre']}\n", 1);
								$file_org_esc = escapeshellarg("{$file_dwn}.orig");
								@copy("{$file_dwn}.orig", "{$file_dwn}.orig.post"); // Save original file for restoration
								exec("{$pfb_script_post} {$file_org_esc} {$list['vtype']} {$elog}");
							}

							if (!$custom) {
								// Check to see if list actually failed download or has no IPs listed.
								$file_chk = '';
								if (file_exists("{$file_dwn}.orig") && @filesize("{$file_dwn}.orig") > 0) {
									$file_org_esc = escapeshellarg("{$file_dwn}.orig");
									$file_chk = exec("{$pfb['grep']} -cv '^#\|^\$' {$file_org_esc}");
								}

								if ($file_chk == 0) {
									if ($list['vtype'] == '_v6') {
										$p_ip = "::{$pfb['ip_ph']}";
									} else {
										$p_ip = $pfb['ip_ph'];
									}

									$ip_data	= "{$p_ip}\n";
									$log		= "  Empty file, Adding '{$p_ip}' to avoid download failure.\n";
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
										exec("{$pfb['script']} {$args} {$header_esc} {$pfb['max']} {$pfb['drep']} {$pfb['ccexclude']} {$pfb['ccwhite']} {$pfb['ccblack']} {$elog}");
									}
								}

								if (!$pfbadv && $list['vtype'] == '_v4') {
									// Call Aggregate process
									if ($pfb['agg'] == 'on') {
										exec("{$pfb['script']} cidr_aggregate {$header_esc} {$pfbfolder} {$elog}");
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
									$log = "[ {$alias} {$header} ] No IPs found! Ensure only IP based Feeds are used! ]\n";
								} else {
									$log = "[ {$alias} {$header} ] Custom List: No IPs found! Ensure only IP based Feeds are used! ]\n";
								}
								pfb_logger("{$log}", 1);
							}
							unset($ip_data);

							// Remove update file indicator
							unlink_if_exists("{$pfbfolder}/{$header}.update");

							// Restore Original Downloaded file after Post Script function
							if (file_exists("{$file_dwn}.orig.post")) {
								@rename("{$file_dwn}.orig.post", "{$file_dwn}.orig");
							}
							// Restore Original Downloaded file after Pre Script function 
							if (file_exists("{$file_dwn}.orig.pre")) {
								@rename("{$file_dwn}.orig.pre", "{$file_dwn}.orig");
							}
						}
					}
				}
			}
		}
	}

	// Remove database update file markers
	unlink_if_exists("{$pfb['dbdir']}/geoip.update");
	unlink_if_exists("{$pfb['dbdir']}/asn.update");

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

	foreach ($ip_types as $ip_type => $vtype) {
		$lists = config_get_path("installedpackages/{$ip_type}/config");

		// Add DNSBLIP, if configured (IPv4 only)
		if ($pfb['dnsbl'] == 'on' && $pfb['dnsbl_ip'] != 'Disabled' && $vtype == '_v4') {

			$list = array(	'aliasname'	=> 'DNSBLIP',
					'vtype'		=> "{$vtype}",
					'key'		=> 0,
					'dnsblip'	=> '',
					'action'	=> "{$pfb['dnsbl_ip']}",
					'aliaslog'	=> "{$pfb['dnsblconfig']['aliaslog']}");

			$list['row'][] = array( 'format'	=> 'auto',
						'state'		=> 'Enabled',
						'url'		=> "{$pfb['dbdir']}/DNSBLIP{$vtype}.txt",
						'header'	=> 'DNSBLIP');
			$lists[] = $list;
		}

		if (!empty($lists) && $pfb['enable'] == 'on') {
			$pfbrunonce = TRUE;
			foreach ($lists as $key => $list) {
				$alias = "pfB_{$list['aliasname']}{$vtype}";
				if (empty(pfb_filter($alias, PFB_FILTER_WORD, 'Configure Aliases and Firewall Rules'))) {
					pfb_logger("\n Invalid Aliasname:{$list['aliasname']}{$vtype}, *skipping*", 1);
					continue;
				}
				$alias_esc = escapeshellarg($alias);

				// Skip any Alias that are 'enabled' but Lists/customlists are not defined.
				if (empty($list['row'][0]['url']) && empty($list['custom'])) {
					exec("{$pfb['pfctl']} -t {$alias_esc} -T kill 2>&1", $result);
					continue;
				}

				if (isset($list['dnsblip'])) {
					$list_type = 'pfblockerngdnsblsettings';
				} else {
					$list_type = "{$ip_type}";
				}

				// Determine 'list' details (return array $pfbarr)
				pfb_determine_list_detail($list['action'], '', $list_type, $key);
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
				}
				else {
					if (!empty($pfb_alias_lists)) {
						$final_alias = array_unique($pfb_alias_lists);
					}
				}

				if ($list['action'] != 'Disabled') {
					$pfbupdate	= FALSE;
					$alias_ips	= '';	// IP Collection of all Lists in the Alias
					$urlvalue	= '';	// Firewall: Aliases value field

					if (isset($list['row'])) {
						foreach ($list['row'] as $row) {
							if (!empty($row['url']) && $row['state'] != 'Disabled') {

								$header = "{$row['header']}{$vtype}";
								if (empty(pfb_filter($header, PFB_FILTER_WORD, 'Configure Aliases and Firewall Rules'))) {
									pfb_logger("\n Invalid Aliasname:{$list['aliasname']}{$vtype} | Header:{$row['header']}{$vtype}, *skipping*", 1);
									continue;
								}
								$header_esc	= escapeshellarg($header);
								$urlvalue	.= "{$header},";

								$pfctlck = exec("{$pfb['pfctl']} -vvsTables | {$pfb['grep']} -A1 {$alias_esc} | {$pfb['awk']} '/Addresses/ {s+=\$2}; END {print s}'");

								// Update alias if list file exists and its been updated or if the alias URL table is empty.
								if (file_exists("{$pfbfolder}/{$header}.txt") && (in_array($alias, $final_alias) || empty($pfctlck))) {
									// Script to run suppression process (print header only)
									if ($pfbrunonce && $pfb['supp'] == 'on' && $vtype == '_v4' && $pfb['supp_update']) {
										exec("{$pfb['script']} suppress suppressheader {$elog}");
										$pfbrunonce = FALSE;
									}
									// Script to run suppression process (body)
									if ($pfb['supp'] == 'on' && $vtype == '_v4' && $pfb['supp_update'] && $pfbadv) {
										if ($pfb['dup'] == 'on') {
											exec("{$pfb['script']} suppress {$header_esc} {$pfbfolder} on {$elog}");
										} else {
											exec("{$pfb['script']} suppress {$header_esc} {$pfbfolder} off {$elog}");
										}
									}
									$alias_ips .= file_get_contents("{$pfbfolder}/{$header}.txt");
									$pfbupdate = TRUE;
								}
							}
						}
					}

					// check custom network list
					$aliasname = "{$list['aliasname']}_custom{$vtype}";

					// Update alias if list file exists and its been updated or if the alias URL table is empty.
					$alias_esc = escapeshellarg($alias);
					$pfctlck = exec("{$pfb['pfctl']} -vvsTables | {$pfb['grep']} -A1 {$alias_esc} | {$pfb['awk']} '/Addresses/ {s+=\$2}; END {print s}'");
					if (!empty($list['custom'])) {
						$urlvalue .= "{$aliasname},";
						if (file_exists("{$pfbfolder}/{$aliasname}.txt") && in_array($alias, $final_alias) ||
						    file_exists("{$pfbfolder}/{$aliasname}.txt") && empty($pfctlck)) {
							$alias_ips .= file_get_contents("{$pfbfolder}/{$aliasname}.txt");
							$pfbupdate = TRUE;
						}
					}

					// Determine validity of alias URL tables/rules. ie: Don't create empty URL tables or aliases
					if (empty($alias_ips) && empty($pfctlck)) {
						unlink_if_exists("{$pfb['aliasdir']}/{$alias}.txt");
					}
					else {
						// Save only aliases that have been updated.
						if ($pfbupdate) {
							@file_put_contents("{$pfb['aliasdir']}/{$alias}.txt", $alias_ips, LOCK_EX);
						}

						// Add '[s]' to Alias descriptions (Bypass States removal feature)
						$adescr = "pfBlockerNG {$pfbdescr} Alias";
						if ($list['stateremoval'] == 'disabled') {
							$adescr = "pfBlockerNG {$pfbdescr} Alias [s]";
						}

						// Create alias
						$new_aliases_list[] = "{$alias}";
						$new_aliases[] = array(	'name'		=> "{$alias}",
									'url'		=> "{$pfb['weblocal']}?pfb={$alias}",
									'updatefreq'	=> '32',
									'address'	=> '',
									'descr'		=> "{$adescr} [ {$urlvalue} ]",
									'type'		=> 'urltable',
									'detail'	=> 'DO NOT EDIT THIS ALIAS'
									);

						// Define firewall rule settings
						pfb_firewall_rule($list['action'], $alias, $vtype, $list['aliaslog'], $pfbarr['agateway_in'], $pfbarr['agateway_out'],
						    $pfbarr['aaddrnot_in'], $pfbarr['aaddr_in'], $pfbarr['aports_in'], $pfbarr['aproto_in'], $pfbarr['anot_in'],
						    $pfbarr['aaddrnot_out'], $pfbarr['aaddr_out'], $pfbarr['aports_out'], $pfbarr['aproto_out'], $pfbarr['anot_out']);
					}
				}
				else {
					// unlink previous pfblockerNG alias list
					unlink_if_exists("{$pfb['aliasdir']}/{$alias}.txt");
				}
			}
		}
	}
	// Clear variables
	$alias_ips = '';

	// Define DNSBL VIP Ports alias
	if ($pfb['dnsbl_rule'] != 'Disabled' && !empty($pfb['dnsbl_port']) && !empty($pfb['dnsbl_port_ssl'])
	    && !empty($pfb['dnsblconfig']['dnsbl_allow_int']) && ($pfb['dnsbl'] == 'on')) {

		$new_aliases_list[] = 'pfB_DNSBL_Ports';
		$new_aliases[] = array( 'name'		=> 'pfB_DNSBL_Ports',
					'address'	=> $pfb['dnsbl_iface'] != 'lo0' ? "{$pfb['dnsbl_port']} {$pfb['dnsbl_port_ssl']}" : '80 443',
					'descr'		=> 'pfBlockerNG DNSBL VIP Ports',
					'type'		=> 'port',
					'detail'	=> 'DO NOT EDIT THIS PORT||DO NOT EDIT THIS PORT'
					);

		if (!empty($pfb['dnsbl_vip6'])) {
			$new_aliases_list[] = 'pfB_DNSBL_VIPs';
			$new_aliases[] = array(	'name'		=> 'pfB_DNSBL_VIPs',
						'address'	=> "{$pfb['dnsbl_vip4']} {$pfb['dnsbl_vip6']}",
						'descr'		=> 'pfBlockerNG DNSBL VIPs',
						'type'		=> 'host',
						'detail'	=> 'DO NOT EDIT THIS HOST||DO NOT EDIT THIS HOST'
						);
		}
	}

	#########################################
	#	UPDATE pfSense ALIAS TABLES	#
	#########################################

	// Reload config.xml to get any recent changes
	config_read_file(false, true);

	$exist_aliases = config_get_path('aliases/alias', []);
	foreach ($exist_aliases as $cbalias) {

		if (substr($cbalias['name'], 0, 4) == 'pfB_') {
			// Remove unreferenced pfB aliastable files
			if (!in_array($cbalias['name'], $new_aliases_list)) {
				unlink_if_exists("{$pfb['aliasdir']}/{$cbalias['name']}.*");
			}
		}
		else {
			$new_aliases[] = $cbalias;
		}
	}

	// Update config.xml, if changes required
	if ($exist_aliases !== $new_aliases) {
		config_set_path('aliases/alias', $new_aliases);
		write_config('pfBlockerNG: saving Aliases');
	}
	unset($new_aliases, $exist_aliases);

	#########################
	#	Assign Rules	#
	#########################

	// Only execute if autorules are defined or if an alias has been removed.
	if ($pfb['autorules'] || $pfb['enable'] == '' || $pfb['remove']) {
		$message = '';
		if (!empty($pfb['deny_inbound']) || !empty($pfb['permit_inbound']) || !empty($pfb['match_inbound'])) {
			if (empty($pfb['inbound_interfaces'])) {
				$message = " Unable to apply rules. Inbound interface option not configured.";
			}
		}
		if (!empty($pfb['deny_outbound']) || !empty($pfb['permit_outbound']) || !empty($pfb['match_outbound'])) {
			if (empty($pfb['outbound_interfaces'])) {
				$message .= "\n Unable to apply rules. Outbound interface option not configured.";
			}
		}

		if (empty($message)) {
			$new_rules = $permit_rules = $match_rules = $other_rules = $fpermit_rules = $fmatch_rules = $fother_rules = array();

			// Reload config.xml to get any recent changes
			config_read_file(false, true);

			// New vs old rules array comparison
			$orig_rules_nocreated = $new_rules_nocreated = array();

			// Collect all existing rules
			$rules = config_get_path('filter/rule', []);

			// Collect existing pfSense rules 'pass', 'match' and 'other' pfSense rules into new arrays.
			if (!empty($rules)) {
				foreach ($rules as $rule) {

					// Remove all existing rules that start with 'pfB_' in the Rule Description
					if (substr($rule['descr'], 0, 4) != 'pfB_') {

						// Upgrade previous IPv4 pfBlockerNG 'alias type' aliasnames to new '_v4' suffix format
						foreach (array('source', 'destination') as $rtype) {
							if (substr($rule[$rtype]['address'], 0, 4) == 'pfB_' &&
							    substr($rule[$rtype]['address'], -3) != '_v4' &&
							    $rule['ipprotocol'] == 'inet') {

								// Add '_v4' suffix
								$rule[$rtype]['address'] = "{$rule[$rtype]['address']}_v4";
							}
						}

						// Floating rules collection 'Floating Pass/Match', balance to 'other'
						if ($pfb['float'] == 'on') {
							if ($pfb['order'] == 'order_0' && $rule['floating'] == 'yes') {
								$fother_rules[] = $rule;
							}
							else {
								if ($rule['type'] == 'pass' && $rule['floating'] == 'yes') {
									$fpermit_rules[] = $rule;
								} elseif ($rule['type'] == 'match' && $rule['floating'] == 'yes') {
									$fmatch_rules[] = $rule;
								} elseif ($rule['floating'] == 'yes') {
									$fother_rules[] = $rule;
								} else {
									$other_rules[] = $rule;
								}
							}
						} else {
							// Collect only 'selected inbound and outbound interfaces'. balance to 'other'
							if (in_array($rule['interface'], $pfb['inbound_interfaces']) ||
							    in_array($rule['interface'], $pfb['outbound_interfaces'])) {
								// Floating rules 'off'. Collect 'floating other', pass, balance to 'other'
								if ($rule['floating'] == 'yes') {
									$fother_rules[] = $rule;
								} elseif ($rule['type'] == 'pass' || isset($rule['associated-rule-id'])) {
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
			#			IP FIREWALL RULES ORDER					#
			#  ORDER 0 |	pfB (p/m/b/r)	| All other	|				#
			#  ORDER 1 |	pfSense (p/m)	| pfB (p/m)	| pfB (b/r) 	| pfSense (b/r)	#
			#  ORDER 2 |	pfB (p/m)	| pfSense (p/m) | pfB (b/r)	| pfSense (b/r)	#
			#  ORDER 3 |	pfB (p/m)	| pfB (b/r) 	| pfSense (p/m)	| pfSense (b/r)	#
			#  ORDER 4 |	pfB (p/m)	| pfB (b/r)	| pfSense (b/r)	| pfSense (p/m)	#
			#################################################################################


			if ($pfb['float'] == '' && $pfb['order'] == 'order_1' && !empty($fother_rules)) {
				foreach ($fother_rules as $cb_rules) {
					$new_rules[] = $cb_rules;
				}
			}
			if ($pfb['float'] == 'on' && $pfb['order'] == 'order_1') {
				foreach (array($fpermit_rules, $fmatch_rules) as $rtype) {
					if (!empty($rtype)) {
						foreach ($rtype as $cb_rules) {
							$new_rules[] = $cb_rules;
						}
					}
				}
			}

			// Define DNSBL 'Floating' pass rule for selected 'OPT' segments to be able to access the LAN DNSBL VIP
			if ($pfb['enable'] == 'on' && $pfb['dnsbl'] == 'on' && $pfb['dnsbl_rule'] != 'Disabled'
			    && !empty($pfb['dnsbl_port']) && !empty($pfb['dnsbl_port_ssl'])
			    && !empty($pfb['dnsblconfig']['dnsbl_allow_int'])) {

				$rule			= $pfb['base_rule_float'];
				$rule['tracker']	= pfb_tracker('pfB_DNSBL_Ping', '', '');
				$rule['type']		= 'pass';
				$rule['direction']	= 'any';
				$rule['interface']	= "{$pfb['dnsblconfig']['dnsbl_allow_int']}";
				$rule['ipprotocol']	= (!empty($pfb['dnsbl_vip6']) ? $rule['ipprotocol'] = 'inet46' : $rule['ipprotocol'] = 'inet');
				$rule['descr']		= "pfB_DNSBL_Ping{$pfb['suffix']}";
				$rule['protocol']	= 'icmp';
				$rule['icmptype']	= 'echoreq';
				$rule['source']		= array('any' => '');
				$rule['destination']	= array('address' => (!empty($pfb['dnsbl_vip6']) ? 'pfB_DNSBL_VIPs' : $pfb['dnsbl_vip4']));
				$rule['created']	= array('time' => (int)microtime(true), 'username' => 'Auto');
				$new_rules[]		= $rule;

				$rule			= $pfb['base_rule_float'];
				$rule['tracker']	= pfb_tracker('pfB_DNSBL_Permit', '', '');
				$rule['type']		= 'pass';
				$rule['direction']	= 'any';
				$rule['interface']	= "{$pfb['dnsblconfig']['dnsbl_allow_int']}";
				$rule['ipprotocol']	= (!empty($pfb['dnsbl_vip6']) ? $rule['ipprotocol'] = 'inet46' : $rule['ipprotocol'] = 'inet');
				$rule['descr']		= "pfB_DNSBL_Permit{$pfb['suffix']}";
				$rule['protocol']	= 'tcp/udp';
				$rule['source']		= array('any' => '');
				$rule['destination']	= array('address' => (!empty($pfb['dnsbl_vip6']) ? 'pfB_DNSBL_VIPs' : $pfb['dnsbl_vip4']),
								 'port' => 'pfB_DNSBL_Ports');
				$rule['created']	= array('time' => (int)microtime(true), 'username' => 'Auto');
				$new_rules[]		= $rule;
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
					if (!empty($pfb['permit_inbound'])) {
						foreach ($pfb['permit_inbound'] as $cb_rules) {
							$cb_rules['interface'] = $inbound_interface;
							$cb_rules['tracker'] = pfb_tracker($cb_rules['descr'], $inbound_interface, 'permit_in');
							$new_rules[] = $cb_rules;
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
					if (!empty($pfb['permit_outbound'])) {
						foreach ($pfb['permit_outbound'] as $cb_rules) {
							$cb_rules['interface'] = $outbound_interface;
							$cb_rules['tracker'] = pfb_tracker($cb_rules['descr'], $outbound_interface, 'permit_out');
							$new_rules[] = $cb_rules;
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
				}
			}

			if ($pfb['float'] == 'on' && in_array($pfb['order'], array('order_0', 'order_3', 'order_4'))) {
				if ($pfb['order'] != 'order_3') {
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
			if ($pfb['float'] == 'on' && in_array($pfb['order'], array('order_1', 'order_2')) && !empty($fother_rules)) {
				foreach ($fother_rules as $cb_rules) {
					$new_rules[] = $cb_rules;
				}
			}
			if ($pfb['float'] == '' && $pfb['order'] != 'order_1' && !empty($fother_rules)) {
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
			if ($orig_rules_nocreated != $new_rules_nocreated) {
				config_set_path('filter/rule', $new_rules);
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

		if (!$pfb['save']) {
			$log = "\n===[  Aliastables / Rules  ]================================\n\n";
			pfb_logger("{$log}", 1);

			$log = localize_text("Firewall rule changes found, applying Filter Reload");
			logger(LOG_NOTICE, $log, LOG_PREFIX_PKG_PFBLOCKERNG);
			pfb_logger("{$log}\n", 1);
		}

		// Remove all pfB aliastables
		exec("{$pfb['pfctl']} -s Tables | {$pfb['grep']} '^pfB_'", $pfb_tables);
		if (isset($pfb_tables)) {
			foreach ($pfb_tables as $pfb_table) {
				$pfb_table_esc = escapeshellarg($pfb_table);
				exec("{$pfb['pfctl']} -t {$pfb_table_esc} -T kill 2>&1", $result);
			}
		}

		$pfb['filter_configure'] = TRUE;		// Set flag for filter_configure which will create the pfctl tables

		// Call function for Ramdisk processes.
		pfb_aliastables('update');
	}
	else {
		// Don't execute on user 'save'
		if (!$pfb['save']) {
			$log = "\n\n===[  Aliastables / Rules  ]==========================================\n\n";
			pfb_logger("{$log}", 1);

			$log = localize_text("No changes to Firewall rules, skipping Filter Reload");
			logger(LOG_NOTICE, $log, LOG_PREFIX_PKG_PFBLOCKERNG);
			pfb_logger("{$log}\n", 1);

			// Remove Alerts IP unlock file and force Reload of all Aliastables
			if (file_exists("{$pfb['ip_unlock']}")) {
				unlink_if_exists("{$pfb['ip_unlock']}");
				$pfb['repcheck'] = TRUE;
			}

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
						$final_esc	= escapeshellarg($final);
						$final_path_esc	= escapeshellarg("{$pfb['aliasdir']}/{$final}.txt");
						exec("{$pfb['pfctl']} -t {$final_esc} -T replace -f {$final_path_esc} 2>&1", $result);
						$log = implode($result);
					} else {
						$log = "Aliastable file not found\n";
					}
					pfb_logger("{$log}", 1);
				}
				pfb_logger("\n", 1);

				// Call function for Ramdisk processes.
				pfb_aliastables('update');
			} else {
				$log = "No Changes to Aliases, Skipping pfctl Update\n";
				pfb_logger("{$log}", 1);
			}
		}
	}
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
		pfb_logger("\nSaving config changes", 1);

		// Reload config.xml to get any recent changes and merge/save changes.
		config_read_file(false, true);
		config_set_path('', array_replace_recursive(config_get_path(''), $pfb_config));
		write_config('pfBlockerNG: save settings');

		pfb_logger("... completed", 1);
	}


	// Query NAT Rules for previous IPv4 pfBlockerNG aliasnames which are not in the new '_v4' suffix format
	$pfb_found	= FALSE;
	config_read_file(false, true);

	foreach (config_get_path('nat/rule', []) as $key => $ex_nat) {
		foreach (array('source', 'destination') as $rtype) {
			if (isset($ex_nat[$rtype]['address']) && substr($ex_nat[$rtype]['address'], 0, 4) == 'pfB_') {
				$pfb_suffix = substr($ex_nat[$rtype]['address'], -3);

				if ($pfb_suffix == '_v6') {
					continue;
				}

				// Add '_v4' suffix if missing
				elseif ($pfb_suffix != '_v4') {
					config_set_path("nat/rule/{$key}/{$rtype}/address", "{$ex_nat[$rtype]['address']}_v4");
					$pfb_found = TRUE;
				}
			}
		}
	}

	if ($pfb_found) {
		write_config("pfBlockerNG: update NAT rule(s) aliasnames to include '_v4' suffix");
	}

	#################################
	#  Call filter_configure once	#
	#################################

	$log = '';
	if ($pfb['filter_configure']) {

		// Remove any IPs in Alerts unlock feature
		unlink_if_exists("{$pfb['ip_unlock']}");

		// Remove IP Cache file
		unlink_if_exists($pfb['ip_cache']);

		require_once('filter.inc');
		filter_configure();

		// Stop/Restart pfb_filter service for new rule changes
		if ($pfb['enable'] == '') {
			if (is_service_running('pfb_filter')) {
				$log = 'Stopping firewall filter daemon';
				stop_service('pfb_filter');
			}
		}
		else {
			$log = 'Restarting firewall filter daemon';
			restart_service('pfb_filter');
		}
	}
	else {
		// Stop/Start Firewall filter daemon
		if ($pfb['enable'] == '' && is_service_running('pfb_filter')) {
			$log = 'Stopping firewall filter daemon';
			stop_service('pfb_filter');
		}
		elseif ($pfb['enable'] == 'on' && !is_service_running('pfb_filter')) {
			$log = 'Starting firewall filter daemon';
			start_service('pfb_filter');
		}
	}

	if (!empty($log)) {
		pfb_logger("\n\n** {$log} **\n", 1);
		logger(LOG_NOTICE, localize_text($log), LOG_PREFIX_PKG_PFBLOCKERNG);
	}


	#################################
	#	KILL STATES		#
	#################################

	if (!$pfb['save'] && $pfb['kstates'] && !$pfb['filter_configure']) {
		pfb_remove_states();
	}


	#########################################
	#	XMLRPC - sync process		#
	#########################################

	if (!is_platform_booting() && !$g['pfblockerng_install']) {
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
			$pfb_hour = implode(',', pfb_cron_base_hour($pfb['interval']));
		}

		if ($pfb_hour == 0 || !empty(pfb_filter($pfb_hour, PFB_FILTER_CSV_CRON, 'Define pfBlockerNG CRON job'))) {
			$pfb_mday	= '*';
			$pfb_month	= '*';
			$pfb_wday	= '*';
			$pfb_who	= 'root';

			// Determine if CRON job requires updating
			if (!pfblockerng_cron_exists($pfb_cmd, $pfb['min'], $pfb_hour, $pfb_mday, $pfb_wday)) {
				install_cron_job('pfblockerng.php cron', false);
				install_cron_job($pfb_cmd, true, $pfb['min'], $pfb_hour, $pfb_mday, $pfb_month, $pfb_wday, $pfb_who, false);
			}
		}
		else {
			pfb_logger("\n Failed to create pfBlockerNG cron task [{$pfb_hour}]", 1);
		}
	}
	else {
		// Clear any existing pfBlockerNG CRON jobs
		install_cron_job('pfblockerng.php cron', false);
	}

	// Define pfBlockerNG MaxMind/TOP1M/ASN Cron job
	if ($pfb['enable'] == 'on') {
		$pfb_gcmd = "/usr/local/bin/php /usr/local/www/pfblockerng/pfblockerng.php dcc >> {$pfb['extraslog']} 2>&1";
		// CRON hour is randomized between 0-23 Hour to minimize effect on Servers
		$pfb_gmin	= '0';
		$pfb_ghour	= rand(0,23);
		$pfb_gmday	= '*';
		$pfb_gmonth	= '*';
		$pfb_gwday	= '*';
		$pfb_gwho	= 'root';

		// Determine if CRON job requires updating
		if (!pfblockerng_cron_exists($pfb_gcmd, $pfb_gmin, 'random', $pfb_gmday, $pfb_gwday)) {
			install_cron_job('pfblockerng.php dcc', false);
			install_cron_job($pfb_gcmd, true, $pfb_gmin, $pfb_ghour, $pfb_gmday, $pfb_gmonth, $pfb_gwday, $pfb_gwho, false);
		}
	}
	else {
		// Clear any existing pfBlockerNG CRON jobs
		install_cron_job('pfblockerng.php dcc', false);
	}


	// Define pfBlockerNG Blacklist CRON job
	if ($pfb['enable'] == 'on' && $pfb['dnsbl'] == 'on' && $pfb['blconfig'] &&
	    $pfb['blconfig']['blacklist_enable'] != 'Disable' &&
	    $pfb['blconfig']['blacklist_freq'] != 'Never' &&
	    !empty($pfb['blconfig']['blacklist_selected']) &&
	    isset($pfb['blconfig']['item'])) {

		$bl_string = '';
		$selected = array_flip(explode(',', $pfb['blconfig']['blacklist_selected'])) ?: array();
		foreach ($pfb['blconfig']['item'] as $item) {
			if (isset($selected[$item['xml']]) && !empty($item['selected'])) {
				$bl_string .= ",{$item['xml']}";
			}
		}
		$bl_string = pfb_filter(ltrim($bl_string, ','), PFB_FILTER_CSV, 'Define pfBlockerNG Blacklist CRON job');

		if (!empty($bl_string)) {
			$pfb_bcmd = "/usr/local/bin/php /usr/local/www/pfblockerng/pfblockerng.php bl {$bl_string} >> {$pfb['extraslog']} 2>&1";

			$pfb_bmin	= '0';
			$pfb_bhour	= rand(0,23);
			$pfb_bmday	= '*';
			$pfb_bmonth	= '*';
			$pfb_bwday	= ($pfb['blconfig']['blacklist_freq'] == 'Weekly' ? '7' : '*');
			$pfb_bwho	= 'root';

			// Determine if CRON job requires updating
			if (!pfblockerng_cron_exists($pfb_bcmd, $pfb_bmin, 'random', $pfb_bmday, $pfb_bwday)) {
				install_cron_job('pfblockerng.php bl', false);
				install_cron_job($pfb_bcmd, true, $pfb_bmin, $pfb_bhour, $pfb_bmday, $pfb_bmonth, $pfb_bwday, $pfb_bwho, false);
			}
		}
		else {
			pfb_logger("\n Failed to create pfBlockerNG Blacklist cron task [{$bl_string}]", 1);
		}
	}
	else {
		// Clear any existing pfBlockerNG Blacklist CRON jobs
		install_cron_job('pfblockerng.php bl', false);
	}

	// Define pfBlockerNG clear [ dnsbl and/or IP ] counter CRON job
	foreach (array( 'clearip', 'cleardnsbl') as $type) {
		$pfb_cmd = "/usr/local/bin/php /usr/local/www/pfblockerng/pfblockerng.php {$type} >/dev/null 2>&1";
		if (config_get_path("installedpackages/pfblockerngglobal/widget-{$type}") !== null) {
			if (config_get_path("installedpackages/pfblockerngglobal/widget-{$type}") != 'never') {
				$pfb_day = '*';
				if (config_get_path("installedpackages/pfblockerngglobal/widget-{$type}") == 'weekly') {
					$pfb_day = '7';
				}

				// Remove unreferenced 'daily' or 'weekly' cron job
				$pfb_other = ($pfb_day == '*') ? '7' : '*';
				if (pfblockerng_cron_exists($pfb_cmd, '0', '0', '*', $pfb_other)) {
					install_cron_job("pfblockerng.php {$type}", false);
				}

				if (!pfblockerng_cron_exists($pfb_cmd, '0', '0', '*', $pfb_day)) {
					install_cron_job($pfb_cmd, true, '0', '0', '*', '*', $pfb_day, 'root', false);
				}
			}
			else {
				if (pfblockerng_cron_exists($pfb_cmd, '0', '0', '*', '*')) {
					install_cron_job("pfblockerng.php {$type}", false);
			}
				if (pfblockerng_cron_exists($pfb_cmd, '0', '0', '*', '7')) {
					install_cron_job("pfblockerng.php {$type}", false);
				}
			}
		}
		else {
			if (pfblockerng_cron_exists($pfb_cmd, '0', '0', '*', '*')) {
				install_cron_job("pfblockerng.php {$type}", false);
			}
			if (pfblockerng_cron_exists($pfb_cmd, '0', '0', '*', '7')) {
				install_cron_job("pfblockerng.php {$type}", false);
			}
		}
	}

	#################################
	#	FINAL REPORTING		#
	#################################

	// Only run with CRON or Force invoked process
	if ((!$pfb['save'] && $pfb['repcheck'] && $pfb['enable'] == 'on') || $pfb['summary']) {
		// Script to run final script processes.
		if ($pfb['dup'] == 'on') {
			exec("{$pfb['script']} closing on {$elog}");
		}
	}

	if ($pfb['enable'] == 'on' && !$pfb['save'] || $pfb['summary']) {
		$log = "\n UPDATE PROCESS ENDED [ NOW ]\n";
		pfb_logger("{$log}", 1);
	}
}


// Function to De-Install pfBlockerNG
function pfblockerng_php_pre_deinstall_command() {
	require_once('config.inc');
	global $g, $pfb;

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

		// Remove all pfB aliastables
		exec("{$pfb['pfctl']} -s Tables | {$pfb['grep']} '^pfB_'", $pfb_tables);
		if (isset($pfb_tables)) {
			foreach ($pfb_tables as $pfb_table) {
				$pfb_table_esc = escapeshellarg($pfb_table);
				exec("{$pfb['pfctl']} -t {$pfb_table_esc} -T kill 2>&1", $result);
			}
		}

		// Remove aliastables archive and earlyshellcmd if found.
		@unlink_if_exists("{$pfb['aliasarchive']}");
		if (config_get_path('system/earlyshellcmd') !== null) {
			$a_earlyshellcmd = config_get_path('system/earlyshellcmd', '');
			if (preg_grep("/pfblockerng.sh aliastables/", $a_earlyshellcmd)) {
				config_set_path('system/earlyshellcmd',
								preg_grep("/pfblockerng.sh aliastables/", $a_earlyshellcmd, PREG_GREP_INVERT));
			}
		}
		foreach (config_get_path('installedpackages/shellcmdsettings/config', []) as $key => $shellcmd) {
			if (strpos($shellcmd['cmd'], 'pfblockerng.sh aliastables') !== FALSE) {
				config_del_path("installedpackages/shellcmdsettings/config/{$key}");
			}
		}

		// Remove settings from config.xml
		pfb_remove_config_settings();

		unlink_if_exists("{$pfb['dnsbl_conf']}");
		unlink_if_exists("{$pfb['dnsbl_cert']}");
		unlink_if_exists("{$pfb['aliasarchive']}");
		unlink_if_exists("{$pfb['dnsbl_info']}");
		unlink_if_exists("{$pfb['dnsbl_resolver']}");

		unlink_if_exists("{$g['unbound_chroot_path']}/pfb_unbound.py");
		unlink_if_exists("{$g['unbound_chroot_path']}/pfb_unbound_include.inc");
		unlink_if_exists("{$g['unbound_chroot_path']}/pfb_py_hsts.txt");

		// Remove widget (code from Snort deinstall)
		$pfb['widgets'] = config_get_path('widgets/sequence', '');
		if (!empty($pfb['widgets'])) {
			$widgetlist = explode(',', $pfb['widgets']);
			foreach ($widgetlist as $key => $widget) {
				if (strpos($widget, 'pfblockerng') !== FALSE) {
					unset($widgetlist[$key]);
				}
			}
			config_set_path('widgets/sequence', implode(',', $widgetlist));
			write_config('pfBlockerNG: Remove widget', false);
		}
	}
	else {
		update_status(" All customizations/data will be retained...");
	}

	unlink_if_exists('/usr/local/sbin/lighttpd_pfb');
	unlink_if_exists('/usr/local/bin/php_pfb');
	unlink_if_exists('/usr/local/sbin/clog_pfb');
	unlink_if_exists('/usr/bin/tail_pfb');
	unlink_if_exists('/usr/local/etc/rc.d/pfb_filter.sh');
	unlink_if_exists('/usr/local/etc/rc.d/pfb_dnsbl.sh');

	unlink_if_exists("{$pfb['dnsbl_cache']}");
	unlink_if_exists("/var/tmp/unbound_cache_*");
	unlink_if_exists("{$pfb['ip_cache']}");

	// Remove incorrect xml setting
	config_del_path('installedpackages/pfblockerngantartica');

	update_status(" done.\n");
}


// Remove settings from config.xml
function pfb_remove_config_settings() {
	global $pfb;

	foreach (array(	'pfblockerng',
			'pfblockerngglobal',
			'pfblockerngsync',
			'pfblockerngreputation',
			'pfblockerngipsettings',
			'pfblockernglistsv4',
			'pfblockernglistsv6',
			'pfblockerngdnsbl',
			'pfblockerngdnsblsettings',
			'pfblockerngsafesearch',
			'pfblockerngblacklist',
			'pfblockerngafrica',
			'pfblockerngantarctica',
			'pfblockerngasia',
			'pfblockerngeurope',
			'pfblockerngnorthamerica',
			'pfblockerngoceania',
			'pfblockerngsouthamerica',
			'pfblockerngtopspammers',
			'pfblockerngproxyandsatellite' ) as $type) {

		config_del_path("installedpackages/{$type}");
	}
}


/* Uses XMLRPC to synchronize the changes to a remote node */
function pfblockerng_sync_on_changes() {
	// Create array of sync settings and exit if sync is disabled.
	$pfb_sync = config_get_path('installedpackages/pfblockerngsync/config/0', []);
	if (!empty($pfb_sync)) {
		if ($pfb_sync['varsynconchanges'] == 'disabled' || empty($pfb_sync['varsynconchanges'])) {
			return;
		}
		$synctimeout = $pfb_sync['varsynctimeout'] ?: 150;
	} else {
		return;
	}

	pfb_logger("\n===[  XMLRPC Sync ]===================================================\n", 1);
	logger(LOG_NOTICE, localize_text('XMLRPC sync is starting.'), LOG_PREFIX_PKG_PFBLOCKERNG);

	if (config_get_path('installedpackages/pfblockerngsync/config') != null) {
		switch ($pfb_sync['varsynconchanges']) {
			case 'manual':
				if (isset($pfb_sync['row'])) {
					$rs = $pfb_sync['row'];
				} else {
					logger(LOG_WARNING, localize_text('Manual XMLRPC sync is enabled but there are no replication targets configured.'), LOG_PREFIX_PKG_PFBLOCKERNG);
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
							logger(LOG_NOTICE, localize_text("XMLRPC sync to [ %s ] completed successfully.", "{$sync_to_ip}:{$port}"), LOG_PREFIX_PKG_PFBLOCKERNG);
						} else {
							pfb_logger(" Failed!\n", 1);
						}
					} else {
						pfb_logger(" terminated due to the following error(s): {$error}", 1);
						logger(LOG_ERR, localize_text("XMLRPC sync to [ %s:%s ] terminated due to the following error(s): %s", $sync_to_ip, $port, $error), LOG_PREFIX_PKG_PFBLOCKERNG);
					}
				}
			}
		}
	}
	pfb_logger("\n======================================================================\n", 1);
}


/* Do the actual XMLRPC sync */
function pfblockerng_do_xmlrpc_sync($sync_to_ip, $port, $protocol, $username, $password, $synctimeout) {
	global $g;
	$success = TRUE;

	// Take care of IPv6 literal address
	if (is_ipaddrv6($sync_to_ip)) {
		$sync_to_ip = "[{$sync_to_ip}]";
	}

	$sections = [];

	// If User Disabled, remove 'General/IP/DNSBL Tab Customizations' from Sync
	if (config_get_path('installedpackages/pfblockerngsync/config/0/syncinterfaces') != 'on') {
		$sections = array_merge($sections, [
			'pfblockerng',
			'pfblockerngipsettings',
			'pfblockerngdnsblsettings'
		]);
	}
	$sections = array_merge($sections, [
		'pfblockernglistsv4',
		'pfblockernglistsv6',
		'pfblockerngreputation',
		'pfblockerngtopspammers',
		'pfblockerngafrica',
		'pfblockerngantarctica',
		'pfblockerngasia',
		'pfblockerngeurope',
		'pfblockerngnorthamerica',
		'pfblockerngoceania',
		'pfblockerngsouthamerica',
		'pfblockerngproxyandsatellite',
		'pfblockerngdnsbl',
		'pfblockerngblacklist',
		'pfblockerngglobal',
		'pfblockerngsafesearch'
	]);

	/* xml will hold the sections to sync */
	$xml = array();

	foreach ($sections as $section) {
		$section_config = config_get_path("installedpackages/{$section}");
		if (!isset($section_config)) {
			continue;
		}
		$xml[$section] = $section_config;
	}

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

function pfblockerng_plugin_xmlrpc_send() {
	if (config_get_path('installedpackages/pfblockerngsync/config/0/varsynconchanges') != 'auto') {
		return [];
	}

	$section_paths_temp = [
		'installedpackages/pfblockerngsync/config/0/varsynconchanges'
	];
	// If User Disabled, remove 'General/IP/DNSBL Tab Customizations' from Sync
	if (config_get_path('installedpackages/pfblockerngsync/config/0/syncinterfaces') != 'on') {
		$section_paths_temp = array_merge($section_paths_temp, [
			'installedpackages/pfblockerng',
			'installedpackages/pfblockerngipsettings',
			'installedpackages/pfblockerngdnsblsettings'
		]);
	}
	$section_paths_temp = array_merge($section_paths_temp, [
		'installedpackages/pfblockernglistsv4',
		'installedpackages/pfblockernglistsv6',
		'installedpackages/pfblockerngreputation',
		'installedpackages/pfblockerngtopspammers',
		'installedpackages/pfblockerngafrica',
		'installedpackages/pfblockerngantarctica',
		'installedpackages/pfblockerngasia',
		'installedpackages/pfblockerngeurope',
		'installedpackages/pfblockerngnorthamerica',
		'installedpackages/pfblockerngoceania',
		'installedpackages/pfblockerngsouthamerica',
		'installedpackages/pfblockerngproxyandsatellite',
		'installedpackages/pfblockerngdnsbl',
		'installedpackages/pfblockerngblacklist',
		'installedpackages/pfblockerngglobal',
		'installedpackages/pfblockerngsafesearch'
	]);

	$section_paths = [];
	foreach ($section_paths_temp as $path) {
		$section_config = config_get_path($path);
		if (!isset($section_config)) {
			continue;
		}
		$section_paths[] = $path;
	}

	return $section_paths;
}

function pfblockerng_plugin_xmlrpc_recv($new_sections) {
	if (array_get_path($new_sections, 'installedpackages/pfblockerngsync/config/0/varsynconchanges') != 'auto') {
		return [];
	}

	$section_paths = [
		'installedpackages/pfblockerng',
		'installedpackages/pfblockerngipsettings',
		'installedpackages/pfblockerngdnsblsettings',
		'installedpackages/pfblockernglistsv4',
		'installedpackages/pfblockernglistsv6',
		'installedpackages/pfblockerngreputation',
		'installedpackages/pfblockerngtopspammers',
		'installedpackages/pfblockerngafrica',
		'installedpackages/pfblockerngantarctica',
		'installedpackages/pfblockerngasia',
		'installedpackages/pfblockerngeurope',
		'installedpackages/pfblockerngnorthamerica',
		'installedpackages/pfblockerngoceania',
		'installedpackages/pfblockerngsouthamerica',
		'installedpackages/pfblockerngproxyandsatellite',
		'installedpackages/pfblockerngdnsbl',
		'installedpackages/pfblockerngblacklist',
		'installedpackages/pfblockerngglobal',
		'installedpackages/pfblockerngsafesearch'
	];

	$ret = [
		'xmlrpc_recv_result' => false
	];
	foreach ($section_paths as $path) {
		$old_section = config_get_path($path, []);
		$new_section = array_get_path($new_sections, $path, []);

		// Don't save empty configuration sections.
		if (empty($old_section) && empty($new_section)) {
			continue;
		}
		// Ignore unchanged configuration sections.
		if ($old_section === $new_section) {
			continue;
		}

		$ret[$path] = array_merge($old_section, $new_section);
		$ret['xmlrpc_recv_result'] = true;
	}

	return $ret;
}
