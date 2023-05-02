<?php
/*
 * binddump.inc
 *
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

require_once('pfsense-utils.inc');
require_once('services.inc');
require_once("/usr/local/pkg/bind.inc");

if (!BIND_LOCALBASE) {
	define('BIND_LOCALBASE', '/usr/local');
}
if (!CHROOT_LOCALBASE) {
	define('CHROOT_LOCALBASE', '/var/etc/named');
}


function binddump_sync_package()
{
	configure_binddump();
}
function configure_binddump()
{
	// nothing to do right now.
}

function binddump_get_rndc_zone_dump_parsed()
{
	$rndc_conf_path = BIND_LOCALBASE . "/etc/rndc.conf";
	$rndc = "/usr/local/sbin/rndc -q -c " . $rndc_conf_path;

	$output = null;
	$retval = null;
	exec("{$rndc} dumpdb -zones", $output, $retval);
	if ($retval !== 0) {
		throw new Exception('Exception during zone compiling. Code:' . $retval . " \n Message: " . $output);
	}
	$dumpFile = CHROOT_LOCALBASE . '/etc/namedb/named_dump.db';

	if (binddump_waitfor_string_in_file($dumpFile, "; Dump complete", 30)) {
		$result = binddump_parse_rndc_zone_dump(file_get_contents($dumpFile));
		return $result;
	}

	return false;
}

function binddump_compilezone($view, $zone)
{
	$zoneFile = CHROOT_LOCALBASE . "/etc/namedb/master/{$view}/{$zone}.DB";
	if (!file_exists($zoneFile)) {
		$zoneFile = CHROOT_LOCALBASE . "/etc/namedb/slave/{$view}/{$zone}.DB";
		if (!file_exists($zoneFile)) {
			throw new Exception('Zone file not found: ' . $zoneFile);
		}
	}

	$tmpfname = tempnam("/tmp", "zoneConvert");
	$output = null;
	$retval = null;
	exec("/usr/local/sbin/named-compilezone -F text -q -i none -s full -o " . escapeshellarg($tmpfname) . " " . escapeshellarg($zone) . " " . escapeshellarg($zoneFile), $output, $retval);
	if ($retval !== 0) {
		throw new Exception('Exception during zone compiling. Code:' . $retval . " \n Message: " . $output);
	}

	$result = file_get_contents($tmpfname);
	unlink($tmpfname);

	return $result;
}

function binddump_addremove_items_to_zone($zone, $view, $items_add, $items_remove)
{
	$rndc_conf_path = BIND_LOCALBASE . "/etc/rndc.conf";
	$rndc = "/usr/local/sbin/rndc -q -c " . $rndc_conf_path;

	if (str_ends_with($view, ' (signed)' )){
		// Signed DB and unsigned DB will automatically sync when serial is increased
		$view = substr($view, 0, -9);
	}
	$zone_file = CHROOT_LOCALBASE . "/etc/namedb/master/{$view}/{$zone}.DB";
	$changed = false;

	exec("{$rndc} freeze " . escapeshellarg($zone) . " IN " . escapeshellarg($view));

	try{
		$zoneData = binddump_parse_rndc_zone_dump(binddump_compilezone($view, $zone));
		$zoneDataNew = [];
	
		foreach ($zoneData as $record) {
			if ($record['type'] == 'SOA') {
				$serial = (int) ($record['serial'] ?: 0);
				$record['serial'] = $serial + 1;
			}
	
			foreach ($items_remove as $item) {
				if (
					$record['name'] == $item['name'] &&
					$record['rdata'] == $item['rdata'] &&
					$record['type'] == $item['type']
				) {
					//Skip if removed
					$changed = true;
					continue 2;
				}
			}

			array_push($zoneDataNew, binddump_zone_record_to_string($record));
		}
	
		foreach ($items_add as $item) {
			array_push($zoneDataNew, binddump_zone_record_to_string($item));
			$changed = true;
		}
	
		if ($changed) {
			file_put_contents($zone_file, implode("\n", $zoneDataNew) . "\n");
		}
	}catch(Exception $e){
		throw $e;

	}finally{
		exec("{$rndc} thaw " . escapeshellarg($zone) . " IN " . escapeshellarg($view));
		unset($zoneDataNew);
		unset($zoneData);
	}

	return $changed;
}

function binddump_strip_after_string($str, $char)
{
	$pos = strpos($str, $char);
	if ($pos !== false) {
		return substr($str, 0, $pos);
	} else {
		return $str;
	}
}
function binddump_mb_explode_escaped($delimiter, $str, $trim_outer_quote_whitespace = true,  $escapeChar = '\\', $quoteChar = '"', $encoding = 'UTF-8') {
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

// Parse files created with RNDC dumpdb -zones
// parse the zone file which was exported with "named-compilezone -F text" or "RNDC dumpdb -zones"
function binddump_parse_rndc_zone_dump($value, $zone = '', $include_comment_only = false) {
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
			$split_comment = binddump_mb_explode_escaped(';', $line);
			$line_without_comment = trim($split_comment[0]);
			
			// everything after the first ; will be used as comment
			unset($split_comment[0]);
			$line_comment = implode(';', $split_comment);

				if (!$item_continue) {
					// detect multiline start
					$split_multiline = binddump_mb_explode_escaped('(', $line);
					if (count($split_multiline) > 1){
						$item_continue = [
							'comment' => $line_comment, 
							'raw' => implode('', $split_multiline), 
							'index' => $index
						];
						continue;
					} else {
						$item = [
							'comment' => $line_comment, 
							'raw' => $line_without_comment, 
							'index' => $index
						];
					}
				} else {
					// detect multiline end
					$split_multiline = binddump_mb_explode_escaped(')', $line);
					if (count($split_multiline) > 1){
						$item_continue['raw'] .= implode('', $split_multiline);
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

				binddump_expand_zone_record($record);
				// remember name if next record has no name
				$last_name = $record['name'];
				array_push($zone_data_parsed, $record);
			}
		}
	}

	return $zone_data_parsed;
}

function binddump_escape_dns_string($val)
{
	$search = ['\\', '"', ';'];
	$replace = ['\\\\', '\\"', '\\;'];
	return '"' . str_replace($search, $replace, $val) . '"';
}

function binddump_unescape_dns_string($val)
{
	$search = ['\\;', '\\"', '\\\\'];
	$replace = [';', '"', '\\'];

	$ret = str_replace($search, $replace, $val);
	return $ret;
}

function binddump_expand_zone_record(&$record)
{
	// parse rdata
	$val = preg_split("/[\s,]*\\\"([^\\\"]+)\\\"[\s,]*|" .
		"[\s,]*'([^']+)'[\s,]*|" .
		"[\s,]+/", $record['rdata'], 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

	$record['_extended'] = [];

	switch ($record['type']) {
		case 'MX':
			if (count($val) == 2) {
				$record['priority'] = $val[0];
				$record['host'] = $val[1];
			}
			break;

		case 'SRV':
			if (count($val) == 4) {
				$record['priority'] = $val[0];
				$record['weight'] = $val[1];
				$record['port'] = $val[2];
				$record['host'] = $val[3];
			}
			break;

		case 'NS':
			if (count($val) == 1) {
				$record['nameserver'] = $val[0];
			}
			break;

		case 'PTR':
			if (count($val) == 1) {
				$record['ip'] = binddump_ptr_to_ip($record['name']);
				$record['host'] = $val[0];
				$record['_extended'] = ['ip'];
			}
			break;

		case 'A':
		case 'AAAA':
			if (count($val) == 1) {
				$record['ip'] = $val[0];
				$record['ptr'] = binddump_ip_to_ptr($record['ip']);
				$record['host'] = $record['name'];
				$record['_extended'] = ['ptr'];
			}
			break;

		case 'TXT':
		case 'SPF': //SPF does not realy exist but BIND threats it like TXT
			$record['txt'] = binddump_unescape_dns_string(implode('', binddump_mb_explode_escaped('"',$record['rdata'])));
			break;

		case 'CNAME':
			if (count($val) == 1) {
				$record['host'] = $val[0];
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
			}
			break;
	}
}

function binddump_collapse_zone_record(&$record)
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
			$str = mb_str_split(binddump_escape_dns_string($record['txt']), 127, 'UTF-8');
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

function binddump_zone_record_to_string($record)
{
	binddump_collapse_zone_record($record);

	return ($record['name'] ?: ' ') . "\t" .
		($record['ttl'] ?: ' ') .
		' IN ' .
		($record['type'] ?: ' ') . "\t" .
		($record['rdata'] ?: ' ');
}

function binddump_ip_to_ptr($ip)
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

function binddump_ptr_to_ip($ptr)
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

function binddump_waitfor_string_in_file($filename, $string, $timeout)
{
	$start = $now = time();

	while (($now - $start) < $timeout) {
		$testfile = @fopen($filename, "r");

		if ($testfile != FALSE) {
			while (($line = fgets($testfile)) !== false) {
				if (strpos($line, $string) !== false) {
					fclose($testfile);
					return (true);
				}
			}
			fclose($testfile);
		}

		sleep(1);
		$now = time();
	}
	throw new Exception('Timeout while waiting for end of file');
}

function binddump_get_zonelist(){
	global $config;

	if (is_array($config['installedpackages']['bindzone'])) {
		$bindzone = $config['installedpackages']['bindzone']['config'];
	} else {
		$bindzone = array();
	}
	return $bindzone;
}

function binddump_reverse_zonename($zone) {
	if ($zone['reverso'] == "on") {
		if ($zone['reversv6o'] == "on") {
			return $zone['name'] . ".ip6.arpa";
		} else {
			return $zone['name'] . ".in-addr.arpa";
		}
	} else {
		return $zone['name'];
	}
}
?>
