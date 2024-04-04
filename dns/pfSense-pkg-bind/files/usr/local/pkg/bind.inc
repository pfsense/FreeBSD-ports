<?php
/*
 * bind.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013 Juliano Oliveira
 * Copyright (c) 2013 Adriano Brancher
 * Copyright (c) 2013 Marcello Coutinho
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
require_once("globals.inc");
require_once("config.inc");
require_once("util.inc");
require_once("pfsense-utils.inc");
require_once("pkg-utils.inc");
require_once("service-utils.inc");
if (!function_exists("filter_configure")) {
	require_once("filter.inc");
}
require_once("notices.inc");
require_once("xmlrpc_client.inc");

define('BIND_LOCALBASE', '/usr/local');
define('CHROOT_LOCALBASE', '/var/etc/named');

function bind_zone_validate($post, &$input_errors) {

	if (array_key_exists("mail", $_POST)) {
		$_POST['mail'] = preg_replace("/@/", ".", $post['mail']);
	}

	if (isset($_POST['rpz']) && ($_POST['rpz'] == "on")) {
		if (($_POST['type'] !== "master") && ($_POST['type'] !== "slave")) {
			$input_errors[] = "Only a master or slave zone can be used in a response policy";
		}
	}
	switch ($_POST['type']) {
		case 'redirect':
			$_POST['tll'] = 300;
			$_POST['refresh'] = 0;
			$_POST['serial'] = 0;
			$_POST['retry'] = 0;
			$_POST['expire'] = 0;
			$_POST['minimum'] = 0;
			if ($_POST['mail'] == '') {
				$input_errors[] = "The field 'Mail Admin Zone' is required for {$_POST['type']} zones.";
			}
			// Intentionally fall through to 'master' zone case
		case 'master':
			if ($_POST['nameserver'] == '') {
				$input_errors[] = "The field 'Name server' is required for {$_POST['type']} zones.";
			}
			for ($i = 0; $i < count($_POST); $i++) {
				if (array_key_exists("hostname$i", $_POST)) {
					if ($_POST['reverso'] == "on") {
						$_POST["hostvalue$i"] = "";
						if (!preg_match("/(PTR|NS)/", $_POST["hosttype$i"])) {
							$input_errors[] = 'On reverse zones, valid record types are NS or PTR';
						}
					}
					if (!preg_match("/(MX|NS)/", $_POST["hosttype$i"]) && ($_POST["hostname$i"] == "")) {
						$input_errors[] = 'Record cannot be empty for '.$_POST["hosttype$i"].' type ';
					}
					if ($_POST["hosttype$i"] == "MX" && ($_POST["hostvalue$i"] == "")) {
						$_POST["hostvalue$i"] = "10";
					}
					if ($_POST["hosttype$i"] != "MX" && ($_POST["hostvalue$i"] != "")) {
						$_POST["hostvalue$i"] = "";
					}
					if ($_POST["hostdst$i"] == "") {
						$input_errors[] = 'Alias or IP address cannot be empty.';
					}
				}
			}
			break;
		case 'slave':
			if ($_POST['slaveip'] == "") {
				$input_errors[] = 'The field \'Master Zone IP\' is required for slave zones.';
			}
			break;
		case 'forward':
			if ($_POST['forwarders'] == "") {
				$input_errors[] = 'The field \'Forwarders\' is required for forward zones.';
			}
			break;
		default:
			break;
	}
}

if (!function_exists('pf_version')) {
        function pf_version() {
                return substr(trim(file_get_contents("/etc/version")), 0, 5);
        }
}

// parse the zone file which was exported with "named-compilezone -F text" or "RNDC dumpdb -zones"
function bind_parse_rndc_zone_dump($value, $zone = '', $include_comment_only = false) {
	$reg_host = "{(?<name>.+?)?(?:\s+?(?<ttl>\d*))?\s+(?<class>IN)?\s+(?<type>\p{L}+)\s+(?<rdata>.*)}";
	$regzone = "{;.+?\'(?<zone>.+?)\/.+/(?<view>.*)\'}";
	$zone_data_parsed = [];

	if ($value) {
		if ($zone !== '' && !str_ends_with($zone,'.')){
			$zone .=  '.';
		}

		$view = '';
		$origin = $zone;
		$defaultTTL = '8700';
		$last_name = '';
		$data_rows = [];
		$item_continue = false;
		$index = 0;

		// normalize multi row values
		foreach (explode("\n", $value) as $line) {
			if (preg_match($regzone, $line)) {
				// pass new zone marker.
				array_push($data_rows, ['raw' => $line]);
				continue;
			}

			// split comments and values
			$split_comment = bind_mb_explode_escaped(';', $line);
			$line_without_comment = trim($split_comment[0]);
			
			// everything after the first ; will be used as comment
			unset($split_comment[0]);
			$line_comment = implode(';', $split_comment);
				if (!$item_continue) {
					// detect multiline start
					$split_multiline = bind_mb_explode_escaped('(', $line);
					if (count($split_multiline) > 1){
						$item_continue = [
							'comment' => $line_comment, 
							'raw' => implode('', $split_multiline), 
							'index' => $index,
							'rowcount' => 1
						];
						continue;
					} else {
						$item = [
							'comment' => $line_comment, 
							'raw' => $line_without_comment, 
							'index' => $index,
							'rowcount' => 1
						];
					}
				} else {
					// detect multiline end
					$split_multiline = bind_mb_explode_escaped(')', $line);
					if (count($split_multiline) > 1){
						$item_continue['raw'] .= implode('', $split_multiline);
						$item_continue['rowcount'] = $index - $item_continue['index'] + 1;
						$item = $item_continue;
						$item_continue = false;
					}else{
						$item_continue['comment'] .= ' ' . $line_comment;
						$item_continue['raw'] .= ' ' . $line_without_comment;
						continue;
					}
				}

				$item['raw'] = trim($item['raw']);
				$item['comment'] = trim($item['comment']);
				array_push($data_rows, $item);
			$index++;
		}

		// process zone records
		foreach ($data_rows as $data_row) {
			if ((empty($data_row['raw']) || trim($data_row['raw']) == '') && (empty($data_row['comment']) || trim($data_row['comment']) == '')) {
				// empty row
			} elseif ($include_comment_only && (empty($data_row['raw']) || trim($data_row['raw']) == '')) {
				// comment only
				$record = [
					'zone' => $zone,
					'view' => $view,
					'index' => $data_row['index'],
					'rowcount' => $data_row['rowcount'],
					'comment' => $data_row['comment'],
					'name' => '',
					'ttl' => '',
					'type' => ';',
					'rdata' => $data_row['comment'],
					'class' => ''
				];
				array_push($zone_data_parsed, $record);

			} elseif (preg_match('{\$TTL\s+(?<ttl>\d+\w?)\s*}', $data_row['raw'], $matches)) {
				// find @TTL
				$defaultTTL = $matches['ttl'];
				$last_name = '';

			} elseif (preg_match('{\$ORIGIN\s+(?<origin>\S+)\s*}', $data_row['raw'], $matches)) {
				// find @ORIGIN
				$origin = $matches['origin'];
				$last_name = '';

			} elseif (preg_match($regzone, $data_row['raw'], $matches)) {
				// find ZONE NAME in BIND Dump
				$zone = $matches['zone'] . '.';
				$view = $matches['view'];
				$origin = $zone;
				$last_name = '';

			} elseif (preg_match($reg_host, $data_row['raw'], $matches)) {
				// regular zone record
				$record = [
					'zone' => $zone,
					'view' => $view,
					'index' => $data_row['index'],
					'rowcount' => $data_row['rowcount'],
					'comment' => $data_row['comment'],
					'name' => $matches['name'],
					'ttl' => $matches['ttl'],
					'type' => strtoupper($matches['type']),
					'rdata' => $matches['rdata'],
					'class' => strtoupper($matches['class'])
				];

				if (!$record['name']) { $record['name'] = $last_name; }
				if (!$record['ttl']) { $record['ttl'] = $defaultTTL; }

				// convert name to FQDN
				if ($record['name'] == '@' || $record['name'] == '.') {
					$record['name'] = $origin;
				} elseif (!str_ends_with($record['name'], '.')) {
					$record['name'] = $record['name'] . ".{$origin}";
				}

				// split host. only for display
				$record['name_part1'] = $record['name'];
				$a = strripos($record['name'], ".{$origin}.");
				if (strtolower($record['name']) == strtolower("{$origin}.")) {
					$record['name_part1'] = $record['name'];
					$record['name_part2'] = '';
				} elseif ($a > 0) {
					$record['name_part1'] = substr($record['name'], 0, $a);
					$record['name_part2'] = ".{$origin}.";
				}

				bind_expand_zone_record($record);
				// remember name if next record has no name
				$last_name = $record['name'];
				array_push($zone_data_parsed, $record);
			}
		}
	}

	return $zone_data_parsed;
}

/* unescape zone record. 
TODO: Maybe there are additional escape rules? */
function bind_escape_dns_string($val)
{
	$search = ['\\', '"', ';'];
	$replace = ['\\\\', '\\"', '\\;'];
	return '"' . str_replace($search, $replace, $val) . '"';
}

// similar to php explode() but the delimiter can be escaped. Additionally quoted text can be extracted.
function bind_mb_explode_escaped($delimiter, $str, $trim_outer_quote_whitespace = true,  $escapeChar = '\\', $quoteChar = '"', $encoding = 'UTF-8') {
	$split = [];
	$index = 0;
	$in_quotes = false;
	$is_escaped = false;
	$was_in_quotes = false;
	$split[$index] = '';
	$whitespaces = [' ', "\n", "\r", "\t"];

	$length = mb_strlen($str, $encoding);
	for ($x = 0; $x < $length; $x++) {
		$char = mb_substr($str, $x, 1, $encoding);

		// Detect escape char
		if ($char === $escapeChar && !$is_escaped){
			$is_escaped = true;
			continue;
		}

		// detect if in quotes
		if ($char === $quoteChar && !$is_escaped){
			$in_quotes = ($in_quotes === false);
			if ($in_quotes){
				$was_in_quotes = true;
			}
		}

		// detect delimiter
		if ($char === $delimiter  && !$is_escaped && (!$in_quotes  || $delimiter === $quoteChar)){
			$index ++;
			$split[$index] = '';
			continue;
		}

		// whitespace handling outside quotes.
		if ($trim_outer_quote_whitespace && !$in_quotes && $was_in_quotes && in_array($char , $whitespaces)){
			if (in_array($char , $whitespaces)){
				continue;
			}else{
				$was_in_quotes = false;
			}
		}

		$split[$index] = $split[$index] . $char;
		$is_escaped = false;
	}

    return $split;
}

// unescape zone record. 
// TODO: Maybe there are additional escape rules?
function bind_unescape_dns_string($val)
{
	$search = ['\\;', '\\"', '\\\\'];
	$replace = [';', '"', '\\'];

	$ret = str_replace($search, $replace, $val);
	return $ret;
}

// expand rdata to individual fields
function bind_expand_zone_record(&$record)
{
	// parse rdata
	$val = preg_split("/[\s,]*\\\"([^\\\"]+)\\\"[\s,]*|" .
		"[\s,]*'([^']+)'[\s,]*|" .
		"[\s,]+/", $record['rdata'], 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

	switch ($record['type']) {
		case 'MX':
			if (count($val) == 2) {
				$record['priority'] = $val[0];
				$record['host'] = $val[1];
				$record['_expanded'] = true;
			}
			break;

		case 'SRV':
			if (count($val) == 4) {
				$record['priority'] = $val[0];
				$record['weight'] = $val[1];
				$record['port'] = $val[2];
				$record['host'] = $val[3];
				$record['_expanded'] = true;
			}
			break;

		case 'NS':
			if (count($val) == 1) {
				$record['nameserver'] = $val[0];
				$record['_expanded'] = true;
			}
			break;

		case 'PTR':
			if (count($val) == 1) {
				$record['ip'] = bind_ptr_to_ip($record['name']);
				$record['host'] = $val[0];
				$record['_expanded'] = true;
			}
			break;

		case 'A':
		case 'AAAA':
			if (count($val) == 1) {
				$record['ip'] = $val[0];
				$record['ptr'] = bind_ip_to_ptr($record['ip']);
				$record['host'] = $record['name'];
				$record['_expanded'] = true;
			}
			break;

		case 'TXT':
		case 'SPF':
			$record['txt'] = bind_unescape_dns_string(implode('', bind_mb_explode_escaped('"',$record['rdata'])));
			$record['_expanded'] = true;
			break;

		case 'CNAME':
			if (count($val) == 1) {
				$record['host'] = $val[0];
				$record['_expanded'] = true;
			}
			break;

		case 'SOA':
			if (count($val) == 7) {
				$record['mname'] = $val[0];
				$record['rname'] = $val[1];
				$record['serial'] = $val[2];
				$record['refresh'] = $val[3];
				$record['retry'] = $val[4];
				$record['expire'] = $val[5];
				$record['minimum'] = $val[6];
				$record['_expanded'] = true;
			}
			break;
	}
}

// merge individual values to rdata
function bind_collapse_zone_record(&$record)
{
	switch ($record['type']) {
		case 'MX':
			$record['rdata'] = $record['priority'] . ' ' . $record['host'];
			break;

		case 'SRV':
			$record['rdata'] = $record['priority'] . ' ' . $record['weight'] . ' ' . $record['port'] . ' ' . $record['host'];
			break;

		case 'NS':
			$record['rdata'] = $record['nameserver'];
			break;

		case 'PTR':
			$record['rdata'] = $record['host'];
			break;

		case 'A':
		case 'AAAA':
			$record['rdata'] = $record['ip'];
			break;

		case 'TXT':
        case 'SPF':
			// escape string and split by 127 chars... aprox 255 byte.
			$str = mb_str_split(bind_escape_dns_string($record['txt']), 127, 'UTF-8');
			$record['rdata'] = implode('" "', array_filter($str, 'strlen'));
			break;

		case 'CNAME':
			$record['rdata'] = $record['host'];
			break;

		case 'SOA':
			$record['rdata'] = $record['mname'] .
				' ' . $record['rname'] .
				' ' . $record['serial'] .
				' ' . $record['refresh'] .
				' ' . $record['retry'] .
				' ' . $record['expire'] .
				' ' . $record['minimum'];
			break;
	}
}

// read and parse current zone db
function bind_get_zone_dump_parsed($zonetype, $zoneview, $zonename, $zonename_reverse){
	$temp_zone_file = "/tmp/nameddump_{$zonetype}_{$zoneview}_{$zonename}.txt";
	$zonefile = CHROOT_LOCALBASE . "/etc/namedb/{$zonetype}/{$zoneview}/{$zonename}.DB";
	$current_zone_data_parsed = null;
	
	exec('/usr/local/sbin/named-compilezone -F text -i none -s full ' .
		' -o ' . escapeshellarg($temp_zone_file) . ' ' .
		escapeshellarg($zonename_reverse) . ' ' .
		escapeshellarg($zonefile) . ' 2>&1', $output, $resultCode);

	if ($resultCode == 0) {
		$current_zone_data = file_get_contents($temp_zone_file);
		$current_zone_data_parsed = bind_parse_rndc_zone_dump($current_zone_data, $zonename_reverse);
		unlink($temp_zone_file);
	} else {
		$error = "[bind] READ FAILED - Zone {$zonename_reverse} has lost dynamic entries.\n" . implode("\n", $output);
		file_notice("named_config", $error, "BIND DNS", "", 2);
		log_error($error);
	}

	return $current_zone_data_parsed;
}

// convert single zone record to string. Use bind_collapse_zone_record() to update rdata first.
function bind_zone_record_to_string($record)
{
	return ($record['name'] ?: ' ') . "\t" .
			($record['ttl'] ?: ' ') .
			' IN ' .
			($record['type'] ?: ' ') . "\t" .
			($record['rdata'] ?: ' ');
}

// convert IPv4 or IPv6 to it's PTR string
function bind_ip_to_ptr($ip)
{
	$ipstring = trim($ip);

	if (str_contains($ipstring, ':')) {
		$unpack = unpack('H*hex', inet_pton($ipstring));
		$hex = $unpack['hex'];
		return implode('.', array_reverse(str_split($hex))) . '.ip6.arpa';
	} else {
		$addr = implode('.', array_reverse(explode(".", $ipstring)));
		return $addr . '.in-addr.arpa';
	}
}

// convert IPv4 or IPv6 from PTR string to it's IP address
function bind_ptr_to_ip($ptr)
{
	$ptr = rtrim(trim($ptr), ".");

	if (str_ends_with($ptr, '.in-addr.arpa')) {
		$addr = explode(".", substr($ptr, 0, -13));
		return implode('.', array_reverse($addr));

	} elseif (str_ends_with($ptr, '.ip6.arpa')) {
		$mainptr = substr($ptr, 0, -9);
		$pieces = array_reverse(explode(".", $mainptr));
		$hex = implode("", $pieces);
		$ipbin = pack('H*', $hex);
		return inet_ntop($ipbin);
	}
}

// compares two parsed zones and returns the difference
function bind_diff_zonerecords($zone1, $zone2)
{
	$diff = [];
	foreach ($zone1 as $a) {
		$match = false;
		foreach ($zone2 as $b) {
			if (
				strtolower($a['name']) == strtolower($b['name']) &&
				strtolower($a['rdata']) == strtolower($b['rdata']) &&
				strtolower($a['type']) == strtolower($b['type'])
			) {
				$match = true;
				break;
			}
		}
		if (!$match) {
			array_push($diff, $a);
		}
	}
	return $diff;
}

function bind_set_serial_zoneconf($zone_conf, $new_zoneserial){
	// write new serial to zone_conf.
		$zone_conf_new = '';
		$serialfound = false;
		foreach(explode("\n" , $zone_conf) as $zone_conf_row){
			if ($serialfound == false && str_ends_with(trim($zone_conf_row), "; serial")){
				$zone_conf_new .= "\t\t{$new_zoneserial} ; serial\n";
				$serialfound = true;
			}else{
				$zone_conf_new .= $zone_conf_row . "\n";
			}
		}
		if ($serialfound){
			$zone_conf = $zone_conf_new;
		} else {
			log_error('[bind] Serial in zone_conf not found!');
		}
	return $zone_conf;
}

function bind_sync()
{
	global $config;
	$named_process = "named";
	$bind = $config['installedpackages']['bind']['config'][0];
	// Create rndc
	$rndc_confgen = "/usr/local/sbin/rndc-confgen";
	$rndc_port = $bind['controlport'] == "" ? "8953" : $bind['controlport'];
	$rndc_confgen_opts = "-p " . escapeshellarg($rndc_port);
	if (!file_exists(BIND_LOCALBASE."/etc/rndc-confgen.pfsense") && file_exists($rndc_confgen)) {
		exec("$rndc_confgen $rndc_confgen_opts ", $rndc_conf);
		$confgen_file = "";
		foreach ($rndc_conf as $line) {
			$confgen_file .= "{$line}\n";
		}
		file_put_contents(BIND_LOCALBASE."/etc/rndc-confgen.pfsense", $confgen_file);
	}
	$rndc_bindconf = "";
	$rndc_file = "";
	if (file_exists(BIND_LOCALBASE . "/etc/rndc-confgen.pfsense")) {
		$rndc_conf = file(BIND_LOCALBASE . "/etc/rndc-confgen.pfsense");
		$confgen = "rndc.conf";
		foreach ($rndc_conf as $line) {
			if (preg_match("/\bport \d+\b/", $line)) {
				$line = preg_replace("/\bport \d+\b/", "port {$rndc_port}", $line);
			}
			if ($confgen == "rndc.conf") {
				if (!preg_match("/^#/", $line)) {
					$rndc_file .= $line;
				}
			} else {
				if (!preg_match("/named.conf/", $line)) {
					$rndc_bindconf .= preg_replace('/#/', "", $line);
				}
			}
			if (preg_match("/named.conf/", $line)) {
				$confgen = "named.conf";
				file_put_contents(BIND_LOCALBASE . "/etc/rndc.conf", $rndc_file);
				@symlink(BIND_LOCALBASE . "/etc/rndc.conf", BIND_LOCALBASE . "/etc/namedb/rndc.conf");
			}
		}
	}

	if (is_array($config['installedpackages']['bindzone'])) {
		$bindzone = $config['installedpackages']['bindzone']['config'];
	} else {
		$bindzone = array();
	}
	$bind_enable = $bind['enable_bind'];
	$bind_dnssec_validation = $bind['bind_dnssec_validation'];
	$bind_forwarder = $bind['bind_forwarder'];
	$forwarder_ips = $bind['bind_forwarder_ips'];
	$ram_limit = $bind['bind_ram_limit'] ? $bind['bind_ram_limit'] : "256M";
	$hide_version = $bind['bind_hide_version'];
	$bind_notify = $bind['bind_notify'];
	$custom_options = base64_decode($bind['bind_custom_options']);
	$bind_global_settings = base64_decode($bind['bind_global_settings']);
	$bind_logging = $bind['bind_logging'];
	$bind_conf = "#Bind pfsense configuration\n";
	$bind_conf .= "#Do not edit this file!!!\n\n";
	$bind_conf .= "{$rndc_bindconf}\n";
	$bind_conf .= "{$bind_global_settings}\n";
	// Curly braces in the following <<<EOD are PHP {$variable}, not named.conf text { value; }
	$bind_conf .= <<<EOD

options {
	directory "/etc/namedb";
	pid-file "/var/run/named/pid";
	statistics-file "/var/log/named.stats";
	max-cache-size {$ram_limit};

EOD;

	$bind_conf .= "\tdnssec-validation ";
	if ($bind_dnssec_validation == 'on') {
		$bind_conf .= "yes";
	} elseif ($bind_dnssec_validation == 'off') {
		$bind_conf .= "no";
	} else {
		$bind_conf .= "auto";
	}
	$bind_conf .= ";\n\n";

	// Check response rate limit option
	//https://kb.isc.org/article/AA-01000/0/A-Quick-Introduction-to-Response-Rate-Limiting.html
	//http://ss.vix.su/~vjs/rl-arm.html
	if ($bind['rate_enabled'] == "on") {
		$rate_limit = $bind['rate_limit'] ? $bind['rate_limit'] : "15";
		$log_only = $bind['log_only'] == "no" ? "no" : "yes";
		// Curly braces in the following <<<EOD are PHP {$variable}, not named.conf text { value; }
		$bind_conf .= <<<EOD
	rate-limit {
		responses-per-second {$rate_limit};
		log-only {$log_only};
	};

EOD;
	}
	// Check IPs to listen on
	if (preg_match("/All/", $bind['listenon'])) {
		$bind_listenonv6 = "any;";
		$bind_listenon = "any;";
	} else {
		$bind_listenonv6 = "";
		$bind_listenon = "";
		foreach (explode(',', $bind['listenon']) as $listenon) {
			if (is_ipaddrv6($listenon)) {
				$bind_listenonv6 .= $listenon."; ";
			} elseif (is_ipaddr($listenon)) {
				$bind_listenon .= $listenon."; ";
			} else {
				$listenon = pfSense_get_interface_addresses(convert_friendly_interface_to_real_interface_name($listenon));
				if (is_ipaddr($listenon['ipaddr'])) {
					$bind_listenon .= $listenon['ipaddr']."; ";
				}
				if (is_ipaddrv6($listenon['ipaddr6'])) {
					$bind_listenonv6 .= $listenon['ipaddr6']."; ";
				}
			}
		}
	}
	$bind_listenonv6 = $bind_listenonv6 == "" ? "none;" : $bind_listenonv6;
	$bind_listenon = $bind_listenon == "" ? "none;" : $bind_listenon;
	// Print "<pre>$bind_listenonv6 $bind_listenon";

	$bind_listenport = $bind['listenport'] == "" ? "53" : $bind['listenport'];
	if (array_key_exists("ipv6allow", $config['system'])) {
		$bind_conf .= "\tlisten-on-v6 port {$bind_listenport} { {$bind_listenonv6} };\n";
	}
	$bind_conf .= "\tlisten-on port {$bind_listenport} { {$bind_listenon} };\n";

	// Forwarder config
	if ($bind_forwarder == 'on') {
		if (!empty($forwarder_ips) && !strpos($forwarder_ips, ';', -1)) {
			$forwarder_ips .= ';';
		}
		$bind_conf .= "\tforwarders { {$forwarder_ips} };\n";
	}
	if ($bind_notify == 'on') {
		$bind_conf .= "\tnotify yes;\n";
	}
	if ($hide_version == 'on') {
		$bind_conf .= "\tversion none;\n";
	}

	$bind_conf .= preg_replace("/^/m", "\t", $custom_options);
	$bind_conf .= "\n};\n\n";

	if ($bind_logging == 'on') {
		// See https://redmine.pfsense.org/issues/5524
		if (pf_version() < "2.3.3") {
			// Check if bind is included on syslog
			$syslog_files = array("/etc/inc/system.inc", "/var/etc/syslog.conf");
			$restart_syslog = 0;
			foreach ($syslog_files as $syslog_file) {
				$syslog_file_data = file_get_contents($syslog_file);
				if (!preg_match("/dnsmasq,named,filterdns/", $syslog_file_data) || !preg_match("/'dnsmasq','named','filterdns'/", $syslog_file_data)) {
					$syslog_file_data = preg_replace("/dnsmasq,filterdns/", "dnsmasq,named,filterdns", $syslog_file_data);
					$syslog_file_data = preg_replace("/'dnsmasq','filterdns'/", "'dnsmasq','named','filterdns'", $syslog_file_data);
					file_put_contents($syslog_file, $syslog_file_data);
					$restart_syslog++;
				}
			}
			if ($restart_syslog > 0) {
				system("/usr/bin/killall -HUP syslogd");
			}
		}
		$log_categories = explode(",", $bind['log_options']);
		$log_severity = $bind['log_severity'] ? $bind['log_severity'] : 'default';
		if (sizeof($log_categories) > 0 && ($log_categories[0] != "")) {
			// Curly braces in the following <<<EOD are PHP {$variable}, not named.conf text { value; }
			$bind_conf .= <<<EOD

logging {
	channel custom {
		syslog daemon;
		print-time no;
		print-severity yes;
		print-category yes;
		severity {$log_severity};
		};

EOD;
			foreach ($log_categories as $category) {
				$bind_conf .= "\tcategory {$category} { custom; };\n";
			}
			$bind_conf .= "};";
		}
	} else {
		$bind_conf .= "logging { category default { null; }; };";
	}
	$bind_conf .= "\n\n";

	// Config Zone domain

	// Add ACLS
	if (!is_array($config['installedpackages']['bindacls']) || !is_array($config['installedpackages']['bindacls']['config'])) {
		$config['installedpackages']['bindacls']['config'][] =
			array("name" => "none", "description" => "BIND Built-in ACL", "row" => array("value" => "", "description" => ""));
		$config['installedpackages']['bindacls']['config'][] =
			array("name" => "any", "description" => "BIND Built-in ACL", "row" => array("value" => "", "description" => ""));
		$config['installedpackages']['bindacls']['config'][] =
			array("name" => "localhost", "description" => "BIND Built-in ACL", "row" => array("value" => "", "description" => ""));
		$config['installedpackages']['bindacls']['config'][] =
			array("name" => "localnets", "description" => "BIND Built-in ACL", "row" => array("value" => "", "description" => ""));
		write_config("Created BIND Built-in ACLs");
	}
	$bindacls = $config['installedpackages']['bindacls']['config'];
	for ($i = 0; $i < sizeof($bindacls); $i++) {
		$aclname = $bindacls[$i]['name'];
		$aclhost = $bindacls[$i]['row'];
		if (($aclname != "none") && ($aclname != "any") && ($aclname != "localhost") && ($aclname != "localnets")) {
			$bind_conf .= "acl \"{$aclname}\" {\n";
			for ($u = 0; $u < sizeof($aclhost); $u++) {
				$aclhostvalue = $aclhost[$u]['value'];
				$bind_conf .= "\t{$aclhostvalue};\n";
			}
			$bind_conf .= "};\n\n";
		}
	}

	// Add Views
	if (is_array($config['installedpackages']['bindviews'])) {
		$bindview = $config['installedpackages']['bindviews']['config'];
	} else {
		$bindview = array();
	}


	

	for ($i = 0; $i < sizeof($bindview); $i++) {
		$views = $config['installedpackages']['bindviews']['config'][$i];
		$viewname = $views['name'];
		$viewrecursion = $views['recursion'];
		if ($views['match-clients'] == '') {
			$viewmatchclients = "none";
		} else {
			$viewmatchclients = str_replace(',', '; ', $views['match-clients']);
		}
		if ($views['allow-recursion'] == '') {
			$viewallowrecursion = "none";
		} else {
			$viewallowrecursion = str_replace(',', '; ', $views['allow-recursion']);
		}
		$viewcustomoptions = base64_decode($views['bind_custom_options']);

		$bind_conf .= "view \"{$viewname}\" { \n";
		$bind_conf .= "\trecursion {$viewrecursion};\n";
		$bind_conf .= "\tmatch-clients { $viewmatchclients; };\n";
		$bind_conf .= "\tallow-recursion { $viewallowrecursion; };\n";

		$rpz_zones = array();
		foreach ($bindzone as $zone) {
			if (isset($zone['rpz']) && ($zone['rpz'] == "on") &&
			    ($zone['disabled'] != "on") && ($zone['view'] == $viewname)) {
			    $rpz_zones[] = reverse_zonename($zone['name'], $zone['reverso'], $zone['reversv6o']);
			}
		}
		if (!empty($rpz_zones)) {
			$bind_conf .= "\n\tresponse-policy {\n";
			foreach ($rpz_zones as $rpz) {
				$bind_conf .= "\t\tzone \"{$rpz}\";\n";
			}
			$bind_conf .= "\t};\n";
		}

		if (!empty($viewcustomoptions)) {
			$bind_conf .= preg_replace("/^/m", "\t", $viewcustomoptions)."\n";
		}
		$bind_conf .= "\n";

		// Add Zones in View
		$write_config = 0;
		$custom_root_zone = false;
		for ($x = 0; $x < sizeof($bindzone); $x++) {
			$zone = $bindzone[$x];
			if ($zone['disabled'] == "on") {
				continue;
			}
			$zonename = $zone['name'];
			if ($zonename == ".") {
				$custom_root_zone = true;
			}
			$zonetype = $zone['type'];
			$zoneviewlist = explode(',', $zone['view']);
			$zonecustom = base64_decode($zone['custom']);
			$zoneipslave = $zone['slaveip'];
			$zoneforwarders = $zone['forwarders'];
			if (!empty($zoneforwarders)) {
				$zoneforwarders = rtrim($zoneforwarders, ';');
			}
			$zonereverso = $zone['reverso'];
			$zonereversv6o = $zone['reversv6o'];
			$zonerpz = (isset($zone["rpz"]) && ($zone["rpz"] === "on"));
			// Ensure zone view folder exists
			if ($zonetype != "forward") {
				foreach ($zoneviewlist as $zoneview) {
					safe_mkdir(CHROOT_LOCALBASE . "/etc/namedb/{$zonetype}/{$zoneview}", 0755);
				}
			}

			if (empty($zone['allowupdate'])) {
				$zoneallowupdate = "none";
			} else {
				$zoneallowupdate = str_replace(',', '; ', $zone['allowupdate']);
			}
			$zoneupdatepolicy = $zone['updatepolicy'];
			if (!empty($zoneupdatepolicy)) {
				$zoneupdatepolicy = rtrim($zoneupdatepolicy, ';');
			}
			if (empty($zone['allowquery'])) {
				$zoneallowquery = "none";
			} else {
				$zoneallowquery = str_replace(',', '; ', $zone['allowquery']);
			}
			if (empty($zone['allowtransfer'])) {
				$zoneallowtransfer = "none";
			} else {
				$zoneallowtransfer = str_replace(',', '; ', $zone['allowtransfer']);
			}

			if (in_array($viewname, $zoneviewlist)) {
				$zoneview = $viewname;
				// Add zone name
				$bind_conf .= "\tzone \"" . reverse_zonename($zonename, $zonereverso, $zonereversv6o) . "\" {\n";

				$bind_conf .= "\t\ttype {$zonetype};\n";
				// Add zone file (if not forwarder)
				if ($zonetype != "forward") {
					$bind_conf .= "\t\tfile \"/etc/namedb/{$zonetype}/{$zoneview}/{$zonename}.DB\";\n";
				}

				// Add zone statements
				switch ($zonetype) {
					case 'master':
						$bind_conf .= "\t\tallow-query { $zoneallowquery; };\n";
						$bind_conf .= "\t\tallow-transfer { $zoneallowtransfer; };\n";
						if ($zone['enable_updatepolicy'] == "on") {
							$bind_conf .= "\t\tupdate-policy { $zoneupdatepolicy; };\n";
						} else {
							$bind_conf .= "\t\tallow-update { $zoneallowupdate; };\n";
						}
						if ($zone['dnssec'] == "on") {
							// https://kb.isc.org/article/AA-00626/
							$bind_conf .= "\n\t\t# look for dnssec keys here:\n";
							$bind_conf .= "\t\tkey-directory \"/etc/namedb/keys\";\n\n";
							$bind_conf .= "\t\t# publish and activate dnssec keys:\n";
							$bind_conf .= "\t\tauto-dnssec maintain;\n\n";
							$bind_conf .= "\t\t# use inline signing:\n";
							$bind_conf .= "\t\tinline-signing yes;\n\n";
						}
						break;
					case 'slave':
						$bind_conf .= "\t\tmasters { $zoneipslave; };\n";
						$bind_conf .= "\t\tallow-query { $zoneallowquery; };\n";
						if (!preg_match('/allow-transfer/', $zonecustom)) {
							$bind_conf .= "\t\tallow-transfer { $zoneallowtransfer; };\n";
						}
						break;
					case 'forward':
						$bind_conf .= "\t\tforward only;\n";
						$bind_conf .= "\t\tforwarders { $zoneforwarders; };\n";
						break;
					case 'redirect':
						$bind_conf .= "\t\t# While using redirect zones, NXDOMAIN Redirection will not override DNSSEC\n";
						$bind_conf .= "\t\t# If the client has requested DNSSEC records (DO=1) and the NXDOMAIN response is signed then no substitution will occur\n";
						$bind_conf .= "\t\t# https://kb.isc.org/article/AA-00376/192/BIND-9.9-redirect-zones-for-NXDOMAIN-redirection.html\n";
						$bind_conf .= "\t\tallow-query { $zoneallowquery; };\n";
						break;
					default:
						break;
				}

				// Add custom zone statements
				if ($zonecustom != '') {
					$bind_conf .= "\t\t{$zonecustom}\n";
				}

				$bind_conf .= "\t};\n\n";

				// Create zone config DB file
				switch ($zonetype) {
					case 'master':
					case 'redirect':
						// Check/update slave dir permission
						chown(CHROOT_LOCALBASE . "/etc/namedb/{$zonetype}", "bind");
						chown(CHROOT_LOCALBASE . "/etc/namedb/{$zonetype}/{$zoneview}", "bind");
						$zonetll = $zone['tll'] ? $zone['tll'] : "43200";
						$zonemail = $zone['mail'] ? $zone['mail'] : "zonemaster.{$zonename}";
						$zonemail = $zonerpz ? "root.localhost" : preg_replace("/@/", ".", $zonemail);
						$zoneserial = $zone['serial'];
						$zonerefresh = $zone['refresh'] ? $zone['refresh'] : "3600";
						$zoneretry = $zone['retry'] ? $zone['retry'] : "600";
						$zoneexpire = $zone['expire'] ? $zone['expire'] : "86400";
						$zoneminimum = $zone['minimum'] ? $zone['minimum'] : "3600";
						$zonenameserver = $zonerpz ? "localhost" : $zone['nameserver'];
						$zoneipns = $zonerpz ? "" : $zone['ipns'];
						$zonereverso = $zone['reverso'];
						$zonereversv6o = $zone['reversv6o'];

						$zone_conf = "\$TTL {$zonetll}\n;\n";
						$zone_conf .= "\$ORIGIN " . reverse_zonename($zonename, $zonereverso, $zonereversv6o) . ".\n\n";
						$zone_conf .= ";\tDatabase file {$zonename}.DB for {$zonename} zone.\n";
						$zone_conf .= ";\tDo not edit this file!!!\n";
						$zone_conf .= ";\tZone version {$zoneserial}\n;\n";
						if (($zonereverso == "on") || ($zonetype == "redirect") || ($zonerpz)) {
							$zone_conf .= "@\t IN  SOA {$zonenameserver}. \t {$zonemail}. (\n";
						} else {
							$zone_conf .= "{$zonename}.\t IN  SOA {$zonenameserver}. \t {$zonemail}. (\n";
						}
						$zone_conf .= "\t\t{$zoneserial} ; serial\n"; // this line is used 1:1 to increment serial. if changing this line, change the reference too.
						$zone_conf .= "\t\t{$zonerefresh} ; refresh\n";
						$zone_conf .= "\t\t{$zoneretry} ; retry\n";
						$zone_conf .= "\t\t{$zoneexpire} ; expire\n";
						$zone_conf .= "\t\t{$zoneminimum} ; default_ttl\n\t\t)\n\n";
						$zone_conf .= ";\n; Zone Records\n;\n";

						if ($zonereverso == "on") {
							$zone_conf .= "\t IN NS \t{$zonenameserver}.\n";
						} else {
							$zone_conf .= "@ \t IN NS \t{$zonenameserver}.\n";
							if ($zoneipns != "") {
								$zone_conf .= "@ \t IN A \t{$zoneipns}\n";
							}
						}
						for ($y = 0; $y < sizeof($zone['row']); $y++) {
							$hosttype = $zone['row'][$y]['hosttype'];
							$hostname = $zone['row'][$y]['hostname'];
							if (preg_match("/(MX|NS)/", $zone['row'][$y]['hosttype']) && ($hostname == "")) {
								$hostname = "@";
							}
							$hostdst = $zone['row'][$y]['hostdst'];
							if (preg_match("/[a-zA-Z]/", $hostdst) && !preg_match("/(TXT|SPF|AAAA)/", $hosttype)) {
								$hostdst .= ".";
							}
							if (preg_match("/(TXT|SPF)/", $hosttype)) {
								if (!str_starts_with(trim($hostdst), '"')) {
									$str = mb_str_split(bind_escape_dns_string($hostdst), 127, 'UTF-8');
									$hostdst = implode('" "', array_filter($str, 'strlen'));
								}
							}
							$hostvalue = $zone['row'][$y]['hostvalue'];
							$zone_conf .= "{$hostname} \t IN {$hosttype} {$hostvalue} \t{$hostdst}\n";
						}

						// Register DHCP static mappings
						if (($zone['regdhcpstatic'] == 'on') && is_array($config['dhcpd'])) {
							$zoneparts = array_reverse(explode('.', $zonename));
							foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
								if (!isset($dhcpifconf['enable']) || !is_array($dhcpifconf['staticmap'])) {
									continue;
								}
								foreach ($dhcpifconf['staticmap'] as $host) {
									if (is_domain($host['domain'])) {
										$domain = $host['domain'];
									} elseif (is_domain($dhcpifconf['domain'])) {
										$domain = $dhcpifconf['domain'];
									} elseif (is_domain($config['system']['domain'])) {
										$domain = $config['system']['domain'];
									} else {
										continue;
									}
									if (!is_hostname($host['hostname']) || !is_ipaddr($host['ipaddr'])) {
										continue;
									}
									if ($zonereverso == "on") {
										$parts = explode('.', $host['ipaddr']);
										$intersect = array_intersect_assoc($parts, $zoneparts);
										if (count($zoneparts) == count($intersect)) {
											$diff = array_diff_assoc($parts, $zoneparts);
											$shortaddr = implode('.', array_reverse($diff));
											$zone_conf .= "{$shortaddr}\tIN PTR\t{$host['hostname']}.{$domain}.\n";
										}
									} else {
										$parts = array_reverse(explode('.', $domain));
										if ($parts === $zoneparts) {
											$zone_conf .= "{$host['hostname']}\tIN A\t{$host['ipaddr']}\n";
										}
									}
								}
							}
						}

						// Add custom zone records
						if ($zone['customzonerecords'] != "") {
							$zone_conf .= "\n\n;\n;custom zone records\n;\n" . base64_decode($zone['customzonerecords']) . "\n";
						}

						// Detect Zone changes
						$skipZoneUpdate = false;
						$diff = [];
						$zone_cache_file = CHROOT_LOCALBASE . "/etc/namedb/{$zonetype}/{$zoneview}/{$zonename}.confcache";
						$zonename_reverse = reverse_zonename($zonename, $zonereverso, $zonereversv6o);
						$rndc = "/usr/local/sbin/rndc -q -r -c " . BIND_LOCALBASE . "/etc/rndc.conf";
						$zonefile = CHROOT_LOCALBASE . "/etc/namedb/{$zonetype}/{$zoneview}/{$zonename}.DB";

						
						$zone_is_dynamic = ((
							($zone['enable_updatepolicy'] == "on") ||
							($zoneallowupdate != "none") ||
							file_exists(CHROOT_LOCALBASE . "/etc/namedb/{$zonetype}/{$zoneview}/{$zonename}.jnl"))
						);
						if ($zone_is_dynamic) {
							// read new and last zonedata to build diff
							if (file_exists($zone_cache_file)) {
								$zoneCacheString = file_get_contents($zone_cache_file);
								$zoneRows_cached_config = bind_parse_rndc_zone_dump($zoneCacheString, $zonename_reverse);
							} else {
								$zoneRows_cached_config = [];
							}
							// new records
							$zoneRows_new_config = bind_parse_rndc_zone_dump($zone_conf, $zonename_reverse);
							// removed records
							$diff = bind_diff_zonerecords($zoneRows_cached_config, $zoneRows_new_config);
							// all records which should not be in the dynamic section
							$diff = array_merge($zoneRows_new_config, $diff);
							if ($zoneCacheString == $zone_conf) {
								// no change in zone config. skip entire zone update.
								$skipZoneUpdate = true;
								log_error("[bind] INFO - Config file for zone {$zonename} skipped cause nothing changed.");
							}
						}

						if (!$skipZoneUpdate) {
							$process_running = is_process_running($named_process);
							$zone_conf_orig = $zone_conf;
							$zone_conf_dynamic = '';
							$current_zone_data_parsed = null;

							if ($zone_is_dynamic || $zone['increment_serial'] == "on") {
								// Freeze dynamic zones to prevent journal corruption.
								if ($process_running && file_exists($zonefile)) {
									exec("{$rndc} freeze " . escapeshellarg($zonename_reverse) . " IN " . escapeshellarg($zoneview));
								}

								// read current zone data
								if (($zone['ddns_merging'] == "on" || $zone['increment_serial'] == "on") && file_exists($zonefile)) {
									$current_zone_data_parsed = bind_get_zone_dump_parsed($zonetype, $zoneview, $zonename, $zonename_reverse);
								}

								// compare and merge zone data by adding existing records in db to zone_conf_dynamic
								if ($zone_is_dynamic && $current_zone_data_parsed && $zone['ddns_merging'] == "on"){
									$zonedata_to_merge = bind_diff_zonerecords($current_zone_data_parsed, $diff);
									$zone_conf_dynamic = "\n;\n; Merged Dynamic Zone Records\n;\n";
									foreach ($zonedata_to_merge as $row) {
										if ($row['type'] !== 'SOA') {
											// Add all records except SOA as this is already added to zone_conf.
											$zone_conf_dynamic .=  bind_zone_record_to_string($row) . "\n";
										}
									}
								}
							}

							//Increment serial 
							if($zone['increment_serial'] == "on"){
								$current_zoneserial = null;
								// get current serial from zone db
								if ($current_zone_data_parsed){
									foreach ($current_zone_data_parsed as $row) {
										if ($row["type"] == 'SOA') {
											$current_zoneserial = $row['serial'];
											break;
										}
									}
								}
								if ($current_zoneserial) {
									$zone_conf = bind_set_serial_zoneconf($zone_conf, $current_zoneserial + 1);
								}
							}

							if ($zone['validate_zone'] == "on") {
								// Save temporary file and perform content chek before overwriting any existing zone DB to prevent service downtime.
								// TODO: Serial number check. If it is out of range, bind won't load zone. A way to ckeck valid serial which takes care of secondary master is required.
								$tempDB = tempnam("/tmp", "validate_zone");
								file_put_contents($tempDB, $zone_conf . $zone_conf_dynamic);

								// validate and save to DB if successfull.
								exec('/usr/local/sbin/named-checkzone -F text '.
									'-o ' . escapeshellarg($zonefile) . ' '.
									escapeshellarg($zonename_reverse) . ' '.
									escapeshellarg($tempDB) . ' 2>&1', $output, $resultCode);

								unlink($tempDB);
							} else {
								file_put_contents($zonefile, $zone_conf . $zone_conf_dynamic);
								$resultCode = 0;
							}

							if ($resultCode == 0) {
								/* Validation successfull or disabled
								Save zone_conf to cache file for comparison on next update. */
								file_put_contents($zone_cache_file, $zone_conf_orig);
								$config['installedpackages']['bindzone']['config'][$x]['resultconfig'] = base64_encode($zone_conf . $zone_conf_dynamic);
								$write_config++;
							} else {
								// Validation failed. Keep old zone DB and send error notice.
								$error = "[bind] VALIDATION FAILED - Zone {$zonename_reverse} not saved. Code {$resultCode}\n\n" . implode("\n", $output);
								file_notice("named_config", $error, "BIND DNS", "", 2);
								log_error($error);
							}

							if ($process_running) {
								if ($zone_is_dynamic) {
									// Thaw frozen dynamic zone
										exec("{$rndc} thaw " . escapeshellarg($zonename_reverse) . " IN " . escapeshellarg($zoneview) . ' 2>&1', $output, $retval);
										if ($retval !== 0) {
											$error = "[bind] RNDC THAW throwed an exception. Zone {$zonename_reverse} may still be frozen. Code {$retval} \n " . implode("\n", $output);
											log_error($error);
											file_notice("named_config", $error, "BIND DNS", "", 1);
										}
								} else {
									// Reload static zone
									exec("{$rndc} reload " . escapeshellarg($zonename_reverse) . " IN " . escapeshellarg($zoneview) . ' 2>&1', $output, $retval);
									if ($retval !== 0) {
										log_error("[bind] RNDC RELOAD throwed an exception. Zone {$zonename_reverse}. Code {$retval}\n" . implode("\n", $output));
									}
								}
							}
						}


						// Check DNSSEC keys creation for master zones
						if ($zone['dnssec'] == "on") {
							$zone_found = 0;
							/* move DNSSEC keys from old chroot,
							 * see https://redmine.pfsense.org/issues/13002 */
							foreach (glob("/var/etc/named/etc/namedb/keys/K{$zonename}*key", GLOB_NOSORT) as $filename) {
								$newkey = CHROOT_LOCALBASE . "/etc/namedb/keys/" . basename($filename);
								if (!file_exists($newkey)) {
									rename($filename, $newkey);
								}
							}
							foreach (glob(CHROOT_LOCALBASE . "/etc/namedb/keys/K{$zonename}*key", GLOB_NOSORT) as $filename) {
								$zone_found++;
							}
							if ($zone_found == 0) {
								$key_restored = 0;
								if (is_array($config['installedpackages']['dnsseckeys']) && is_array($config['installedpackages']['dnsseckeys']['config'])) {
									foreach ($config['installedpackages']['dnsseckeys']['config'] as $filer) {
										if (preg_match("/K$zonename\.+/", $filer['fullfile'])) {
											file_put_contents($filer['fullfile'], base64_decode($filer['filedata']), LOCK_EX);
											chmod($filer['fullfile'], 0700);
											chown($filer['fullfile'], "bind");
											$key_restored++;
										}
									}
								}
								if ($key_restored > 0) {
									log_error("[bind] {$key_restored} DNSSEC keys restored from XML backup for {$zonename} zone.");
								}
								$dnssec_bin = "/usr/local/sbin/dnssec-keygen";
								$z = reverse_zonename($zonename, $zonereverso, $zonereversv6o);
								if (file_exists($dnssec_bin) && $key_restored == 0) {
									exec("{$dnssec_bin} -a ECDSAP256SHA256 -K " . CHROOT_LOCALBASE . "/etc/namedb/keys {$z}", $kout);
									exec("{$dnssec_bin} -a ECDSAP256SHA256 -K " . CHROOT_LOCALBASE . "/etc/namedb/keys -fk {$z}", $kout);
									foreach ($kout as $filename) {
										chown(CHROOT_LOCALBASE . "/etc/namedb/keys/{$filename}.key", "bind");
										chown(CHROOT_LOCALBASE . "/etc/namedb/keys/{$filename}.private", "bind");
									}
									log_error("[bind] DNSSEC keys for {$zonename} created.");
								}
							}
							// Get DS keys
							$dsfromkey = "/usr/local/sbin/dnssec-dsfromkey";
							foreach (glob(CHROOT_LOCALBASE."/etc/namedb/keys/K{$zonename}*key", GLOB_NOSORT) as $filename) {
								$zone_key = file_get_contents($filename);
								if (preg_match("/IN DNSKEY 257 /", $zone_key) && file_exists($dsfromkey)) {
									exec("$dsfromkey $filename", $dsset);
									$config['installedpackages']['bindzone']['config'][$x]['dsset'] = base64_encode(array_pop($dsset)."\n".array_pop($dsset));
									$write_config++;
								}
							}

							// Save DNSSEC keys to xml
							if ($zone['backupkeys'] == "on") {
								$dnssec_keys = 0;
								foreach (glob(CHROOT_LOCALBASE . "/etc/namedb/keys/K{$zonename}*", GLOB_NOSORT) as $filename) {
									$file_found = 0;
									if (is_array($config['installedpackages']['dnsseckeys']) && is_array($config['installedpackages']['dnsseckeys']['config'])) {
										foreach ($config['installedpackages']['dnsseckeys']['config'] as $filer) {
											if ($filer['fullfile'] == $filename) {
												$file_found++;
											}
										}
									}
									if ($file_found == 0) {
										$config['installedpackages']['dnsseckeys']['config'][] = array('fullfile' => $filename,
											'description' => "bind {$zonename} DNSSEC backup file",
											'filedata' => base64_encode(file_get_contents($filename)));
										$write_config++;
										$dnssec_keys++;
									}
								}
								if ($dnssec_keys > 0) {
									log_error("[bind] {$dnssec_keys} DNSSEC keys for {$zonename} zone saved on XML config.");
								}
							}
						}
						break;
					case 'slave':
						// Check/update slave dir permission
						chown(CHROOT_LOCALBASE . "/etc/namedb/{$zonetype}", "bind");
						chown(CHROOT_LOCALBASE . "/etc/namedb/{$zonetype}/{$zoneview}", "bind");
						// check if exists slave zone file
						$rsconfig = "";
						if ($zone['dnssec'] == "on") {
							if (file_exists(CHROOT_LOCALBASE . "/etc/namedb/{$zonetype}/{$zoneview}/{$zonename}.DB.signed")) {
								exec("/usr/local/sbin/named-checkzone -D -f raw -o - {$zonename} " . CHROOT_LOCALBASE . "/etc/namedb/{$zonetype}/{$zoneview}/{$zonename}.DB.signed", $slave_file);
							}
						} else {
							if (file_exists(CHROOT_LOCALBASE . "/etc/namedb/{$zonetype}/{$zoneview}/{$zonename}.DB")) {
								$slave_file = file(CHROOT_LOCALBASE . "/etc/namedb/{$zonetype}/{$zoneview}/{$zonename}.DB");
							}
						}
						// TODO: is is_array() the best test to use? Is it only checking for existence?
						if (is_array($slave_file)) {
							foreach ($slave_file as $zfile) {
								$rsconfig .= $zfile;
							}
						}
						$config['installedpackages']['bindzone']['config'][$x]['resultconfig'] = base64_encode($rsconfig);
						$write_config++;
						break;
					case 'forward':
						// Forwarder zone does not have a DB file
						$config['installedpackages']['bindzone']['config'][$x]['resultconfig'] = '';
						$write_config++;
						break;
					default:
						break;
				}
			}
		}
		if (!$custom_root_zone && ($bind_forwarder != 'on')) {
			$bind_conf .= "\tzone \".\" {\n";
			$bind_conf .= "\t\ttype hint;\n";
			$bind_conf .= "\t\tfile \"/etc/namedb/named.root\";\n";
			$bind_conf .= "\t};\n\n";
		}
		if ($write_config > 0) {
			write_config("BIND: Saved resulting config file for zone in xml");
		}
		$bind_conf .= "};\n";
	}
	$dirs = array("/etc/namedb/keys", "/var/run/named", "/var/dump", "/var/log", "/var/stats", "/dev");
	foreach ($dirs as $dir) {
		safe_mkdir(CHROOT_LOCALBASE . $dir, 0755);
	}
	// Handle /dev dirs for chroot
	$bind_dev_dir = CHROOT_LOCALBASE . "/dev";
	if (!file_exists("$bind_dev_dir/random")) {
		$dev_dirs = array("null", "zero", "random", "urandom");
		exec("/sbin/mount -t devfs devfs {$bind_dev_dir}", $dout);
		exec("/sbin/devfs -m {$bind_dev_dir} ruleset 1", $dout);
		exec("/sbin/devfs -m {$bind_dev_dir} rule add hide", $dout);
		foreach ($dev_dirs as $dev_dir) {
			exec("/sbin/devfs -m {$bind_dev_dir} rule add path {$dev_dir} unhide", $dout);
		}
		exec("/sbin/devfs -m {$bind_dev_dir} rule applyset", $dout);
	}
	// Handle /usr/local/lib/named for chroot
	$bind_plugin_dir = CHROOT_LOCALBASE . BIND_LOCALBASE . "/lib/named";
	rmdir_recursive($bind_plugin_dir);
	safe_mkdir($bind_plugin_dir, 0755);
	foreach (glob(BIND_LOCALBASE . "/lib/named/*.so") as $bind_plugin) {
		copy($bind_plugin, CHROOT_LOCALBASE . $bind_plugin);
	}
	// http://www.unixwiz.net/techtips/bind9-chroot.html
	file_put_contents(CHROOT_LOCALBASE . '/etc/namedb/named.conf', $bind_conf);
	file_put_contents(CHROOT_LOCALBASE . '/etc/namedb/rndc.conf', $rndc_file);

	if (!file_exists(CHROOT_LOCALBASE . "/etc/namedb/named.root")) {
		$curl_session = curl_init();
		curl_setopt($curl_session, CURLOPT_URL, "http://www.internic.net/domain/named.root");
		curl_setopt($curl_session, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl_session, CURLOPT_USERAGENT, $g['product_label'] . '/' . rtrim(file_get_contents("/etc/version")));
		set_curlproxy($curl_session);
		$named_root = curl_exec($curl_session);
		if (curl_errno($curl_session) || empty($named_root)) {
			$named_root = file_get_contents("/usr/local/etc/namedb/named.root");
		}
		curl_close($curl_session);
		file_put_contents(CHROOT_LOCALBASE . "/etc/namedb/named.root", $named_root, LOCK_EX);
	}
	if (!file_exists(CHROOT_LOCALBASE."/etc/localtime")) {
		copy("/etc/localtime", CHROOT_LOCALBASE . "/etc/localtime");
	}

	bind_write_rcfile();
	chown(CHROOT_LOCALBASE . "/etc/namedb/keys", "bind");
	chown(CHROOT_LOCALBASE . "/etc/namedb", "bind");
	chown(CHROOT_LOCALBASE . "/var/log", "bind");
	chown(CHROOT_LOCALBASE . "/var/run/named", "bind");
	chgrp(CHROOT_LOCALBASE . "/var/log", "bind");
	$bind_sh = "/usr/local/etc/rc.d/named.sh";
	
	if ($bind_enable == "on") {
		chmod($bind_sh, 0755);
		if (is_process_running($named_process)) {
			// Thaw all zones in case one missed.
			exec("{$rndc} thaw");
			exec("{$rndc} reconfig" . ' 2>&1', $output, $retval);
			if ($retval !== 0) {
				// restart service as Fallback to rndc reload
				log_error("[bind] WARNING \"RNDC reconfig\" CODE {$retval}:\n" . implode("\n", $output));
				restart_service("named");
				log_error("[bind] service restarted because reconfig throwed an exception.");
			}
		} else {
			restart_service("named");
		}
	} else {
		stop_service("named");
		chmod($bind_sh, 0644);
	}
	// Sync to backup servers
	bind_sync_on_changes();
}


function bind_print_javascript_type_zone() {
	$js = <<<'JS'
	<script>
	events.push(function(){
		on_type_zone_changed();
		$("textarea#resultconfig").attr("disabled", true);
		$("textarea#dsset").attr("disabled", true);
		$("select#type").change(function(){on_type_zone_changed();});

		function on_type_zone_changed() {
			var tipo = $("select#type").val();
			switch (tipo) {
				case 'master':
					$("input#slaveip").attr("disabled", true);
					$("input#tll").attr("disabled", false);
					$("input#nameserver").attr("disabled", false);
					$("input#reverso").attr("disabled", false);
					$("input#reversv6o").attr("disabled", false);
					$("input#forwarders").attr("disabled", true);
					$("input#dnssec").attr("disabled", false);
					$("input#backupkeys").attr("disabled", false);
					$("input#regdhcpstatic").attr("disabled", false);
					$("input#ipns").attr("disabled", false);
					$("input#mail").attr("disabled", false);
					$("input#serial").attr("disabled", false);
					$("input#refresh").attr("disabled", false);
					$("input#retry").attr("disabled", false);
					$("input#expire").attr("disabled", false);
					$("input#minimum").attr("disabled", false);
					$("select#allowquery\\[\\]").attr("disabled", false);
					$("select#allowupdate\\[\\]").attr("disabled", false);
					$("select#allowtransfer\\[\\]").attr("disabled", false);
					$("input#enable_updatepolicy").attr("disabled", false);
					$("input#updatepolicy").attr("disabled", true);
					$("input#rpz").attr("disabled", false);
					$("input#validate_zone").attr("disabled", false);
					$("input#ddns_merging").attr("disabled", false);
					$("input#increment_serial").attr("disabled", false);
					break;
				case 'slave':
					$("input#slaveip").attr("disabled", false);
					$("input#tll").attr("disabled", true);
					$("input#nameserver").attr("disabled", true);
					$("input#reverso").attr("disabled", false);
					$("input#reversv6o").attr("disabled", false);
					$("input#forwarders").attr("disabled", true);
					$("input#dnssec").attr("disabled", false);
					$("input#backupkeys").attr("disabled", false);
					$("input#regdhcpstatic").attr("disabled", false);
					$("input#ipns").attr("disabled", true);
					$("input#mail").attr("disabled", true);
					$("input#serial").attr("disabled", true);
					$("input#refresh").attr("disabled", true);
					$("input#retry").attr("disabled", true);
					$("input#expire").attr("disabled", true);
					$("input#minimum").attr("disabled", true);
					$("select#allowquery\\[\\]").attr("disabled", false);
					$("select#allowupdate\\[\\]").attr("disabled", true);
					$("select#allowtransfer\\[\\]").attr("disabled", true);
					$("input#enable_updatepolicy").attr("disabled", true);
					$("input#updatepolicy").attr("disabled", true);
					$("input#rpz").attr("disabled", false);
					$("input#validate_zone").attr("disabled", true);
					$("input#ddns_merging").attr("disabled", true);
					$("input#increment_serial").attr("disabled", true);
					break;
				case 'forward':
					$("input#slaveip").attr("disabled", true);
					$("input#tll").attr("disabled", true);
					$("input#nameserver").attr("disabled", true);
					$("input#reverso").attr("disabled", true);
					$("input#reversv6o").attr("disabled", true);
					$("input#forwarders").attr("disabled", false);
					$("input#dnssec").attr("disabled", true);
					$("input#backupkeys").attr("disabled", true);
					$("input#regdhcpstatic").attr("disabled", true);
					$("input#ipns").attr("disabled", true);
					$("input#mail").attr("disabled", true);
					$("input#serial").attr("disabled", true);
					$("input#refresh").attr("disabled", true);
					$("input#retry").attr("disabled", true);
					$("input#expire").attr("disabled", true);
					$("input#minimum").attr("disabled", true);
					$("select#allowquery\\[\\]").attr("disabled", true);
					$("select#allowupdate\\[\\]").attr("disabled", true);
					$("select#allowtransfer\\[\\]").attr("disabled", true);
					$("input#enable_updatepolicy").attr("disabled", true);
					$("input#updatepolicy").attr("disabled", true);
					$("input#rpz").attr("disabled", true);
					$("input#validate_zone").attr("disabled", true);
					$("input#ddns_merging").attr("disabled", true);
					$("input#increment_serial").attr("disabled", false);
					break;
				case 'redirect':
					$("input#slaveip").attr("disabled", true);
					$("input#tll").attr("disabled", true);
					$("input#nameserver").attr("disabled", false);
					$("input#reverso").attr("disabled", true);
					$("input#reversv6o").attr("disabled", true);
					$("input#forwarders").attr("disabled", false);
					$("input#dnssec").attr("disabled", true);
					$("input#backupkeys").attr("disabled", true);
					$("input#regdhcpstatic").attr("disabled", true);
					$("input#ipns").attr("disabled", true);
					$("input#mail").attr("disabled", false);
					$("input#serial").attr("disabled", false);
					$("input#refresh").attr("disabled", false);
					$("input#retry").attr("disabled", false);
					$("input#expire").attr("disabled", false);
					$("input#minimum").attr("disabled", false);
					$("select#allowquery\\[\\]").attr("disabled", false);
					$("select#allowupdate\\[\\]").attr("disabled", true);
					$("select#allowtransfer\\[\\]").attr("disabled", true);
					$("input#enable_updatepolicy").attr("disabled", true);
					$("input#updatepolicy").attr("disabled", true);
					$("input#rpz").attr("disabled", true);
					$("input#validate_zone").attr("disabled", true);
					$("input#ddns_merging").attr("disabled", true);
					$("input#increment_serial").attr("disabled", false);
					break;
				default:
					break;
			}
		}
	});
	</script>
JS;
	echo $js;
}

function bind_write_rcfile() {
	global $config;
	$bind = $config['installedpackages']['bind']['config'][0];
	$ip_version = ($bind['bind_ip_version'] ? $bind['bind_ip_version'] : "");
	$rc = array();
	$BIND_LOCALBASE = "/usr/local";
	$rc['file'] = 'named.sh';
	// Curly braces in the following <<<EOD are PHP {$variable}, not named.conf text { value; }
	$rc['start'] = <<<EOD
	if [ -z "`/bin/ps auxw | /usr/bin/grep "[n]amed " | /usr/bin/awk '{print $2}'`" ]; then
		{$BIND_LOCALBASE}/sbin/named {$ip_version} -c /etc/namedb/named.conf -u bind -t /var/etc/named/
	fi
EOD;
	$rc['stop'] = <<<EOD
	if [ -n "`/bin/ps auxw | /usr/bin/grep "[n]amed " | /usr/bin/awk '{print $2}'`" ]; then
		{$BIND_LOCALBASE}/sbin/rndc -q -r -c "{$BIND_LOCALBASE}/etc/rndc.conf" sync -clean 2>/dev/null
		{$BIND_LOCALBASE}/sbin/rndc -q -r -c "{$BIND_LOCALBASE}/etc/rndc.conf" stop -clean 2>/dev/null
		sleep 1
		/usr/bin/killall -TERM named 2>/dev/null
		sleep 2
	fi
EOD;
	// curly braces in the following <<<EOD are PHP {$variable}, not named.conf text { value; }
	$rc['restart'] = <<<EOD
	if [ -z "`/bin/ps auxw | /usr/bin/grep "[n]amed " | /usr/bin/awk '{print $2}'`" ]; then
		{$BIND_LOCALBASE}/sbin/named {$ip_version} -c /etc/namedb/named.conf -u bind -t /var/etc/named/
	else
		{$BIND_LOCALBASE}/sbin/rndc -q -r -c "{$BIND_LOCALBASE}/etc/rndc.conf" sync -clean 2>/dev/null
		{$BIND_LOCALBASE}/sbin/rndc -q -r -c "{$BIND_LOCALBASE}/etc/rndc.conf" stop -clean 2>/dev/null
		sleep 1
		/usr/bin/killall -TERM named 2>/dev/null
		sleep 3
		{$BIND_LOCALBASE}/sbin/named {$ip_version} -c /etc/namedb/named.conf -u bind -t /var/etc/named/
	fi
EOD;
	write_rcfile($rc);
}

/* Uses XMLRPC to synchronize the changes to a remote node */
function bind_sync_on_changes() {
	global $config, $g;

	if (is_array($config['installedpackages']['bindsync']['config'])) {
		$bind_sync = $config['installedpackages']['bindsync']['config'][0];
		$synconchanges = $bind_sync['synconchanges'];
		$synctimeout = (int)$bind_sync['synctimeout'] ?: 30;
		$master_zone_ip = $bind_sync['masterip'];
		switch ($synconchanges) {
			case 'manual':
				if (is_array($bind_sync['row'])) {
					$rs = $bind_sync['row'];
				} else {
					log_error("[bind] XMLRPC sync is enabled but there are no hosts to push on BIND config.");
					return;
				}
				break;
			case 'auto':
				if (is_array($config['hasync'])) {
					$hasync = $config['hasync'];
					$rs[0]['ipaddress'] = $hasync['synchronizetoip'];
					$rs[0]['username'] = $hasync['username'];
					$rs[0]['password'] = $hasync['password'];
					$rs[0]['syncdestinenable'] = FALSE;

					// XMLRPC sync is currently only supported over connections using the same protocol and port as this system
					if ($config['system']['webgui']['protocol'] == "http") {
						$rs[0]['syncprotocol'] = "http";
						$rs[0]['syncport'] = $config['system']['webgui']['port'] ?: '80';
					} else {
						$rs[0]['syncprotocol'] = "https";
						$rs[0]['syncport'] = $config['system']['webgui']['port'] ?: '443';
					}
					if ($hasync['synchronizetoip'] == "") {
						log_error("[bind] XMLRPC CARP/HA sync is enabled but there are no system backup hosts configured as replication targets.");
						return;
					} else {
						$rs[0]['syncdestinenable'] = TRUE;
					}
				} else {
					log_error("[bind] XMLRPC sync is enabled but there are no system backup hosts to push BIND config.");
					return;
				}
				break;
			default:
				return;
		}
		if (is_array($rs)) {
			log_error("[bind] XMLRPC sync is starting.");
			foreach ($rs as $sh) {
				// Only sync enabled replication targets
				if ($sh['syncdestinenable']) {
					$sync_to_ip = $sh['ipaddress'];
					$port = $sh['syncport'];
					/* If port is empty let's rely on the protocol selection */
					if ($port == "") {
						if ($config['system']['webgui']['protocol'] == "http") {
							$port = "80";
						} else {
							$port = "443";
						}
					}
					$username = $sh['username'] ?: 'admin';
					$password = $sh['password'];
					$protocol = $sh['syncprotocol'];
					/* If protocol is empty let's rely on the webGUI protocol selection */
					if ($protocol == "") {
						if ($config['system']['webgui']['protocol'] == "http") {
							$protocol = "http";
						} else {
							$protocol = "https";
						}
					}

					$error = '';
					$valid = TRUE;

					if ($password == "") {
						$error = "Password parameter is empty. ";
						$valid = FALSE;
					}
					if (!is_ipaddr($sync_to_ip) && !is_hostname($sync_to_ip) && !is_domain($sync_to_ip)) {
						$error .= "Misconfigured Replication Target IP Address or Hostname. ";
						$valid = FALSE;
					}
					if (!is_port($port)) {
						$error .= "Misconfigured Replication Target Port. ";
						$valid = FALSE;
					}
					if ($master_zone_ip == "") {
						$error .= "Zone master IP parameter is empty. ";
						$valid = FALSE;
					} elseif (!is_ipaddr($master_zone_ip)) {
						$error .= "Misconfigured zone master IP Address. ";
						$valid = FALSE;
					}
					if ($valid) {
						bind_do_xmlrpc_sync($sync_to_ip, $port, $protocol, $username, $password, $synctimeout, $master_zone_ip);
					} else {
						log_error("[bind] XMLRPC sync with '{$sync_to_ip}' aborted due to the following error(s): {$error}");
					}
				}
			}
			log_error("[bind] XMLRPC sync completed.");
		}
	}
}

if (!function_exists('pf_version')) {
	function pf_version() {
		return substr(trim(file_get_contents("/etc/version")), 0, 3);
	}
}

/* Do the actual XMLRPC sync */
function bind_do_xmlrpc_sync($sync_to_ip, $port, $protocol, $username, $password, $synctimeout, $master_zone_ip) {
	global $config, $g;

	if (($username == "") || ($password == "") || ($sync_to_ip == "") || ($port == "") || ($protocol == "") || ($master_zone_ip =="")) {
		log_error("[bind] A required XMLRPC sync parameter (username, password, replication target, port, protocol or zone master IP) is empty ... aborting pkg sync");
		return;
	}
	
	/* XML will hold the sections to sync */
	$xml = array();
	$xml['bind'] = $config['installedpackages']['bind'];
	$xml['bindacls'] = $config['installedpackages']['bindacls'];
	$xml['bindviews'] = $config['installedpackages']['bindviews'];
	$xml['bindzone'] = $config['installedpackages']['bindzone'];
	if (is_array($config['installedpackages']['dnsseckeys'])) {
		$xml['dnsseckeys'] = $config['installedpackages']['dnsseckeys'];
	}
	// Change master zone to slave on backup servers
	if (is_array($xml['bindzone']["config"])) {
		for ($x = 0; $x < sizeof($xml['bindzone']["config"]); $x++) {
			if ($xml['bindzone']["config"][$x]['type'] == "master") {
				$xml['bindzone']["config"][$x]['type'] = "slave";
				$xml['bindzone']["config"][$x]['slaveip'] = $master_zone_ip;
			}

		}
	}
	
	/* Commands to reload BIND settings on the destination sync host. */
	$execcmd = "require_once('/usr/local/pkg/bind.inc');\n";
	$execcmd .= "bind_sync('yes');";
	
	if (pf_version() >= "2.4") {
		// xmlrpc cannot encode NULL objects/arrays..
		foreach($xml as $xmlkey => $xmlvalue) {
			if (gettype($xmlvalue) == "NULL") {
				$xml[$xmlkey] = array();
			}
		}
		$rpc_client = new pfsense_xmlrpc_client();
		$rpc_client->setConnectionData($sync_to_ip, $port, $username, $password, $protocol);
		$resp = $rpc_client->xmlrpc_method('merge_installedpackages_section', $xml, $synctimeout);
		$resp = $rpc_client->xmlrpc_exec_php($execcmd, $synctimeout);
	} else {
		// pfSense before 2.4
		require_once('xmlrpc.inc');

		// Take care of IPv6 literal address
		if (is_ipaddrv6($sync_to_ip)) {
			$sync_to_ip = "[{$sync_to_ip}]";
		}

		$url = "{$protocol}://{$sync_to_ip}";

		/* Assemble XMLRPC payload */
		$params = array(
			XML_RPC_encode($password),
			XML_RPC_encode($xml)
		);

		/* Set a few variables needed for sync */
		log_error("[bind] Beginning bind XMLRPC sync to {$url}:{$port}.");
		$method = 'pfsense.merge_installedpackages_section_xmlrpc';
		$msg = new XML_RPC_Message($method, $params);
		$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
		$cli->setCredentials($username, $password);
		if ($g['debug']) {
			$cli->setDebug(1);
		}
		/* Send our XMLRPC message and timeout after defined sync timeout value */
		$resp = $cli->send($msg, $synctimeout);
		if (!$resp) {
			$error = "A communication error occurred while attempting BIND XMLRPC sync with {$url}:{$port}.";
			log_error($error);
			file_notice("sync_settings", $error, "BIND Settings Sync", "");
		} elseif ($resp->faultCode()) {
			$cli->setDebug(1);
			$resp = $cli->send($msg, $synctimeout);
			$error = "An error code was received while attempting BIND XMLRPC sync with {$url}:{$port} - Code ".$resp->faultCode().": ".$resp->faultString();
			log_error($error);
			file_notice("sync_settings", $error, "BIND Settings Sync", "");
		} else {
			log_error("[bind] XMLRPC sync successfully completed with {$url}:{$port}.");
		}

		/* Tell bind to reload our settings on the destination sync host. */
		$method = 'pfsense.exec_php';
		/* Assemble XMLRPC payload */
		$params = array(
			XML_RPC_encode($password),
			XML_RPC_encode($execcmd)
		);

		log_error("[bind] XMLRPC reload data {$url}:{$port}.");
		$msg = new XML_RPC_Message($method, $params);
		$cli = new XML_RPC_Client('/xmlrpc.php', $url, $port);
		$cli->setCredentials($username, $password);
		$resp = $cli->send($msg, $synctimeout);
		if (!$resp) {
			$error = "A communication error occurred while attempting BIND XMLRPC sync with {$url}:{$port} (pfsense.exec_php).";
			log_error($error);
			file_notice("sync_settings", $error, "BIND Settings Sync", "");
		} elseif ($resp->faultCode()) {
			$cli->setDebug(1);
			$resp = $cli->send($msg, $synctimeout);
			$error = "[bind] An error code was received while attempting BIND XMLRPC sync with {$url}:{$port} - Code ".$resp->faultCode().": ".$resp->faultString();
			log_error($error);
			file_notice("sync_settings", $error, "BIND Settings Sync", "");
		} else {
			log_error("BIND XMLRPC reload data success with {$url}:{$port} (pfsense.exec_php).");
		}
	}
}

function reverse_zonename($zonename, $zonereverso=false, $zonereversv6o=false) {
	if ($zonereverso == "on") {
		if ($zonereversv6o == "on") {
			return $zonename . ".ip6.arpa";
		} else {
			return $zonename . ".in-addr.arpa";
		}
	} else {
		return $zonename;
	}
}
?>
