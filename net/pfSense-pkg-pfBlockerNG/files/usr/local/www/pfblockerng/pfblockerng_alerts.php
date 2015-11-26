<?php
/*
	pfBlockerNG_Alerts.php

	pfBlockerNG
	Copyright (c) 2015 BBcan177@gmail.com
	All rights reserved.

	Portions of this code are based on original work done for
	pfSense from the following contributors:

	Parts based on works from Snort_alerts.php
	Copyright (c) 2015 Bill Meeks
	All rights reserved.

	Javascript Hostname Lookup modifications by J. Nieuwenhuizen

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
require_once('guiconfig.inc');
require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');

global $g, $pfb, $rule_list, $pfb_localsub;
pfb_global();

// Application paths
$pathgeoip	= "{$pfb['prefix']}/bin/geoiplookup";
$pathgeoip6	= "{$pfb['prefix']}/bin/geoiplookup6";

// Define file locations
$filter_logfile = "{$g['varlog_path']}/filter.log";
$pathgeoipdat	= "{$pfb['geoipshare']}/GeoIP.dat";
$pathgeoipdat6	= "{$pfb['geoipshare']}/GeoIPv6.dat";

// Proofpoint ET IQRisk header name reference
$et_header = $config['installedpackages']['pfblockerngreputation']['config'][0]['et_header'];
$pfb['et_header'] = TRUE;
if (empty($et_header)) {
	$pfb['et_header'] = FALSE;
}

// Collect pfBlockerNGSuppress alias and create pfbsuppression.txt
if ($pfb['supp'] == 'on') {
	pfb_create_suppression_file();
}

// Collect number of suppressed hosts
$pfbsupp_cnt = 0;
if (file_exists("{$pfb['supptxt']}")) {
	$pfbsupp_cnt = exec("{$pfb['grep']} -c ^ {$pfb['supptxt']}");
}

// Alerts tab customizations
$aglobal_array = array('pfbdenycnt' => 25, 'pfbpermitcnt' => 5, 'pfbmatchcnt' => 5, 'pfbdnscnt' => 5, 'alertrefresh' => 'on', 'hostlookup' => 'on');
$pfb['aglobal'] = &$config['installedpackages']['pfblockerngglobal'];
foreach ($aglobal_array as $type => $value) {
	${"$type"} = $pfb['aglobal'][$type] != '' ? $pfb['aglobal'][$type] : $value;
}

// Save Alerts tab customizations
if (isset($_POST['save'])) {
	$pfb['aglobal']['alertrefresh']	= htmlspecialchars($_POST['alertrefresh']) ?: 'off';
	$pfb['aglobal']['hostlookup']	= htmlspecialchars($_POST['hostlookup']) ?: 'off';
	unset($aglobal_array['alertrefresh'], $aglobal_array['hostlookup']);

	foreach ($aglobal_array as $type => $value) {
		if (ctype_digit(htmlspecialchars($_POST[$type]))) {
			$pfb['aglobal'][$type] = htmlspecialchars($_POST[$type]);
		}
	}

	write_config('pfBlockerNG pkg: updated ALERTS tab settings.');
	header('Location: /pfblockerng/pfblockerng_alerts.php');
	exit;
}

// Define alerts log filter rollup window variable and collect widget alert pivot details
if (isset($_REQUEST['rule'])) {
	$filterfieldsarray[0]		= htmlspecialchars($_REQUEST['rule']);
	$pfbdenycnt			= $pfbpermitcnt = $pfbmatchcnt = htmlspecialchars($_REQUEST['entries']);
	$pfb['filterlogentries']	= TRUE;
} else {
	$pfb['filterlogentries']	= FALSE;
}

// Re-enable any Alert 'filter settings' on page refresh
if (isset($_REQUEST['refresh'])) {
	$refresharr = unserialize(urldecode($_REQUEST['refresh']));
	if (isset($refresharr)) {
		foreach ($refresharr as $key => $type) {
			$filterfieldsarray[htmlspecialchars($key)] = htmlspecialchars($type) ?: null;
		}
	}
	$pfb['filterlogentries']	= TRUE;
}

// Filter Alerts based on user defined 'filter settings'
if (isset($_POST['filterlogentries_submit'])) {
	$pfb['filterlogentries'] = TRUE;
	$filterfieldsarray = array();

	foreach (array( 0 => 'rule', 2 => 'int', 6 => 'proto', 7 => 'srcip', 8 => 'dstip',
			9 => 'srcport', 10 => 'dstport', 90 => 'dnsbl', 99 => 'date') as $key => $type) {

		$type = htmlspecialchars($_POST['filterlogentries_' . "{$type}"]) ?: null;
		if ($key == 6) {
			$type = strtolower("{$type}");
		}
		$filterfieldsarray[$key] = $type ?: null;
	}
}

if (isset($_POST['filterlogentries_clear'])) {
	$pfb['filterlogentries'] = FALSE;
	$filterfieldsarray = array();
}

// Add IP to the suppression alias
if (isset($_POST['addsuppress'])) {
	if (isset($_POST['ip'])) {
		$ip	= htmlspecialchars($_POST['ip']);
		$table	= htmlspecialchars($_POST['table']);
		$descr	= htmlspecialchars($_POST['descr']);
		$cidr	= htmlspecialchars($_POST['cidr']);

		// If description or CIDR field is empty, exit.
		if (empty($descr) || empty($cidr)) {
			header('Location: /pfblockerng/pfblockerng_alerts.php');
			exit;
		}

		if (is_ipaddr($ip)) {
			$savemsg1 = "Host IP address {$ip}";
			if (is_ipaddrv4($ip)) {
				// Explode IP into evaluation strings
				$ix = ip_explode($ip);

				if ($cidr == 32) {
					$pfb_pfctl = exec("{$pfb['pfctl']} -t {$table} -T show | grep {$ip} 2>&1");
					if (!empty($pfb_pfctl)) {
						$savemsg2 = ' : Removed /32 entry';
						exec("{$pfb['pfctl']} -t {$table} -T delete {$ip}");
					} else {
						exec("{$pfb['pfctl']} -t {$table} -T delete {$ix[5]} 2>&1", $pfb_pfctl);
						if (preg_grep("/1\/1 addresses deleted/", $pfb_pfctl)) {
							$savemsg2 = ' : Removed /24 entry, added 254 addr';
							for ($k=0; $k <= 255; $k++) {
								if ($k != $ix[4]) {
									exec("{$pfb['pfctl']} -t {$table} -T add {$ix[6]}{$k}");
								}
							}
						}
						else {
							$savemsg = gettext("Not Suppressed. Host IP address {$ip} is blocked by a CIDR other than /24");
							header('Location: /pfblockerng/pfblockerng_alerts.php');
							exit;
						}
					}
				} else {
					$cidr = 24;
					$savemsg2 = ' : Removed /24 entry';
					exec("{$pfb['pfctl']} -t {$table} -T delete {$ix[5]} 2>&1", $pfb_pfctl);
					if (!preg_grep("/1\/1 addresses deleted/", $pfb_pfctl)) {
						$savemsg2 = ' : Removed all entries';
						// Remove 0-255 IP address from alias table
						for ($j=0; $j <= 255; $j++) {
							exec("{$pfb['pfctl']} -t {$table} -T delete {$ix[6]}{$j}");
						}
					}
				}
			}
			elseif (is_ipaddrv6($ip)) {
				$cidr = '';
				$savemsg2 = ' : Removed entry';
				exec("{$pfb['pfctl']} -t {$table} -T delete {$ip}");
			}

			// Collect pfBlockerNGSuppress alias contents
			$pfbfound = $pfbupdate = FALSE;
			if (isset($config['aliases']['alias'])) {
				foreach ($config['aliases']['alias'] as $pfb_key => $alias) {
					if ($alias['name'] == 'pfBlockerNGSuppress') {
						$slist = array(explode(' ', $alias['address']), explode('||', $alias['detail']));
						$pfbfound = TRUE;
						break;

					}
				}
			}

			// Call function to create suppression alias if not found.
			if (!$pfbfound) {
				$pfb_key = @max($pfb_key, 0) ? ++$pfb_key : 0;
				pfb_create_suppression_alias();
			}

			// Save new suppress IP to pfBlockerNGSuppress alias
			if (in_array("{$ix[5]}", $slist[0]) || in_array("{$ip}/32", $slist[0])) {
				$savemsg = gettext("Host IP address {$ip} already exists in the pfBlockerNG suppress table.");
			} else {
				if ($cidr == 24) {
					$slist[0][] = "{$ix[5]}";
				} elseif ($cidr == 32) {
					$slist[0][] = "{$ip}/32";
				} else {
					$slist[0][] = "{$ip}";
				}
				$slist[1][] = $descr;

				// Sort suppress list and save
				array_multisort($slist[0], SORT_ASC, SORT_NUMERIC, $slist[1]);
				$config['aliases']['alias'][$pfb_key]['address']	= implode(' ', $slist[0]);
				$config['aliases']['alias'][$pfb_key]['detail']		= implode('||', $slist[1]);
				$savemsg = gettext($savemsg1) . gettext($savemsg2) . gettext(' and added Host to the pfBlockerNG Suppress Table.');
				$pfbupdate = TRUE;
			}

			if ($pfbupdate) {
				// Save Suppress alias changes to pfSense config file
				write_config("pfBlockerNG: Added {$ip} to IP Suppress List");
			}
		}
	}
}

// Add domain to the suppression list
if (isset($_POST['addsuppressdom'])) {
	$domain		= htmlspecialchars($_POST['domain']);
	$domainparse	= str_replace('.', '\.', $domain);
	$pfb['dsupp']	= &$config['installedpackages']['pfblockerngdnsblsettings']['config'][0]['suppression'];

	// Collect existing suppression list
	$dnssupp_ex = collectsuppression();

	// Query for domain in Unbound DNSBL file.
	$dnsbl_query = exec("/usr/bin/grep -Hm1 ' \"{$domain} 60 IN A' {$pfb['dnsbl_file']}.conf");

	// Save new suppress domain to suppress list.
	if (empty($dnsbl_query)) {
		$savemsg = gettext("Domain: [ {$domain} ] does not exist in the Unbound Resolver DNSBL");
		exec("/usr/local/sbin/unbound-control -c {$pfb['dnsbldir']}/unbound.conf flush {$domain}.");
	} else {
		// Remove domain from Unbound resolver pfb_dnsbl.conf file
		exec("{$pfb['sed']} -i '' '/ \"{$domain} 60 IN A/d' {$pfb['dnsbl_file']}.conf");

		$cache_dumpfile = '/var/tmp/unbound_cache';
		unlink_if_exists("{$cache_dumpfile}");
		$chroot_cmd = "chroot -u unbound -g unbound / /usr/local/sbin/unbound-control -c {$g['unbound_chroot_path']}/unbound.conf";

		exec("{$chroot_cmd} dump_cache > $cache_dumpfile");
		exec("{$chroot_cmd} reload");

		if (file_exists($cache_dumpfile) && filesize($cache_dumpfile) > 0) {
			exec("{$chroot_cmd} load_cache < $cache_dumpfile");
		}

		exec("/usr/local/sbin/unbound-control -c {$pfb['dnsbldir']}/unbound.conf flush {$domain}");

		if (!in_array($domain, $dnssupp_ex)) {
			$dnssupp_ex[]	= $domain;
			$dnssupp_new	= base64_encode(implode("\n", $dnssupp_ex));
			$pfb['dsupp']	= "{$dnssupp_new}";
			write_config("pfBlockerNG: Added {$domain} to DNSBL suppress list");
		}
		$savemsg = gettext("Removed Domain: [ {$domain} ] from Unbound Resolver DNSBL. You may need to flush your browsers DNS Cache");
	}
}

// Collect pfBlockerNG rule names and tracker ids
$rule_list = array();
exec("{$pfb['pfctl']} -vv -sr | {$pfb['grep']} 'pfB_'", $results);
if (!empty($results)) {
	foreach ($results as $result) {

		// Find rule tracker ids
		$id = strstr($result, '(', FALSE);
		$id = ltrim(strstr($id, ')', TRUE), '(');

		// Find rule descriptions
		$descr = ltrim(stristr($result, '<pfb_', FALSE), '<');
		$descr = strstr($descr, ':', TRUE);

		// Create array of rule description and tracker id
		$rule_list['id'][] = $id;
		$rule_list[$id]['name'] = $descr;
	}
}

// Define common variables and arrays for report tables
$fields_array	= $pfb_local = $pfb_localsub = $dnsbl_int = $local_hosts = array();

$pfblines	= exec("/usr/local/sbin/clog {$filter_logfile} | {$pfb['grep']} -c ^");
$fields_array	= conv_log_filter_lite($filter_logfile, $pfblines, $pfblines, $pfbdenycnt, $pfbpermitcnt, $pfbmatchcnt);
$continents	= array('pfB_Africa', 'pfB_Antartica', 'pfB_Asia', 'pfB_Europe', 'pfB_NAmerica', 'pfB_Oceania', 'pfB_SAmerica', 'pfB_Top');

$supp_ip_txt	= "Clicking this Suppression Icon, will immediately remove the block.\n\nSuppressing a /32 CIDR is better than suppressing the full /24";
$supp_ip_txt	.= " CIDR.\nThe Host will be added to the pfBlockerNG suppress alias table.\n\nOnly 32 or 24 CIDR IPs can be suppressed with the '+' icon.";
$supp_ip_txt	.= "\nTo manually add host(s), edit the 'pfBlockerNGSuppress' alias in the alias Tab.\nManual entries will not remove existing blocked hosts";

// Collect gateway IP addresses for inbound/outbound list matching
$int_gateway = get_interfaces_with_gateway();
if (isset($int_gateway)) {
	foreach ($int_gateway as $gateway) {
		$convert = get_interface_ip($gateway);
		$pfb_local[] = $convert;
	}
}

// Collect virtual IP aliases for inbound/outbound list matching
if (isset($config['virtualip']['vip'])) {
	foreach ($config['virtualip']['vip'] as $list) {
		if (!empty($list['subnet']) && !empty($list['subnet_bits'])) {
			if ($list['subnet_bits'] >= 24) {
				$pfb_local = array_merge(subnetv4_expand("{$list['subnet']}/{$list['subnet_bits']}"), $pfb_local);
			} else {
				$pfb_localsub[] = "{$list['subnet']}/{$list['subnet_bits']}";
			}

			// Collect VIP for Alerts hostlookup
			$local_hosts[$list['subnet']] = strtolower("{$list['descr']}");
		}
	}
}

// Collect NAT IP addresses for inbound/outbound list matching
if (isset($config['nat']['rule'])) {
	foreach ($config['nat']['rule'] as $natent) {
		$pfb_local[] = $natent['target'];

		// Collect NAT for Alerts hostlookup
		$local_hosts[$natent['target']] = strtolower("{$natent['descr']}");
	}
}

// Collect 1:1 NAT IP addresses for inbound/outbound list matching
if (isset($config['nat']['onetoone'])) {
	foreach ($config['nat']['onetoone'] as $onetoone) {
		$pfb_local[] = $onetoone['source']['address'];
	}
}

// Convert any 'Firewall Aliases' to IP address format
if (isset($config['aliases']['alias'])) {
	for ($cnt = 0; $cnt <= count($pfb_local); $cnt++) {
		foreach ($config['aliases']['alias'] as $i=> $alias) {
			if (isset($alias['name']) && isset($pfb_local[$cnt])) {
				if ($alias['name'] == $pfb_local[$cnt]) {
					$pfb_local[$cnt] = $alias['address'];
				}
			}
		}
	}
}

// Collect all interface addresses for inbound/outbound list matching
if (isset($config['interfaces'])) {
	foreach ($config['interfaces'] as $int) {
		if ($int['ipaddr'] != 'dhcp') {
			if (!empty($int['ipaddr']) && !empty($int['subnet'])) {
				if ($int['subnet'] >= 24) {
					$pfb_local = array_merge(subnetv4_expand("{$int['ipaddr']}/{$int['subnet']}"), $pfb_local);
				} else {
					$pfb_localsub[] = "{$int['ipaddr']}/{$int['subnet']}";
				}

				// Collect DNSBL Interfaces
				$dnsbl_int[] = array("{$int['ipaddr']}/{$int['subnet']}",  "{$int['descr']}");

			}
		}
	}
}

// Remove any duplicate IPs
$pfb_local = array_unique($pfb_local);
$pfb_localsub = array_unique($pfb_localsub);

// Collect DHCP hostnames/IPs
$local_hosts = array();

// Collect dynamic DHCP hostnames/IPs
$leasesfile = "{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases";
if (file_exists("{$leasesfile}")) {
	$leases = file("{$leasesfile}");
	if (!empty($leases)) {
		foreach ($leases as $line) {
			if (strpos($line, '{') !== FALSE) {
				$end = FALSE;
				$data = explode(' ', $line);
				$ip = $data[1];
			}
			if (strpos($line, 'client-hostname') !== FALSE) {
				$data = explode(' ', $line);
				$hostname = str_replace(array('"', ';'), '', $data[3]);
			}
			if (strpos($line, '}') !== FALSE) {
				$end = TRUE;
			}
			if ($end) {
				if (!empty($ip) && !empty($hostname)) {
					$local_hosts[$ip] = $hostname;
				}
				$ip = $hostname = '';
			}
		}
	}
}

// Collect static DHCP hostnames/IPs
if (isset($config['dhcpd'])) {
	foreach ($config['dhcpd'] as $dhcp) {
		if (isset($dhcp['staticmap'])) {
			foreach ($dhcp['staticmap'] as $smap) {
				$local_hosts[$smap['ipaddr']] = strtolower("{$smap['hostname']}");
			}
		}
	}
}

// Collect Unbound Host overrides
$hosts = $config['unbound']['hosts'];
if (isset($hosts)) {
	foreach ($hosts as $host) {
		$local_hosts[$host['ip']] = strtolower("{$host['descr']}");
	}
}

// Collect configured pfSense interfaces
$pf_int = get_configured_ip_addresses();
if (isset($pf_int)) {
	$local_hosts = array_merge($local_hosts, array_flip(array_filter($pf_int)));
}
$pf_int = get_configured_ipv6_addresses();
if (isset($pf_int)) {
	$local_hosts = array_merge($local_hosts, array_flip(array_filter($pf_int)));
}

// FUNCTION DEFINITIONS


// Collect existing suppression list
function collectsuppression() {
	global $pfb;
	$dnssupp_ex = array();

	$custom_list = pfbng_text_area_decode($pfb['dnsblconfig']['suppression']);
	if (!empty($custom_list)) {
		$dnssupp_ex = array_filter( explode("\n", pfbng_text_area_decode($pfb['dnsblconfig']['suppression'])));
	}
	return ($dnssupp_ex);
}


// Host resolve function lookup
function getpfbhostname($type = 'src', $hostip, $countme = 0, $host) {
	global $local_hosts;

	$hostnames['src'] = $hostnames['dst'] = '';
	$hostnames[$type] = "<div id='gethostname_{$countme}' name='{$hostip}'></div>";

	// Report DHCP hostnames if found.
	if (isset($local_hosts[$host])) {
		if ($type == 'src') {
			$hostnames['dst'] = $local_hosts[$host];
		} else {
			$hostnames['src'] = $local_hosts[$host];
		}
	}
	return $hostnames;
}


// Function to Filter Alerts report on user defined input
function pfb_match_filter_field($flent, $fields) {
	if (isset($fields)) {
		foreach ($fields as $key => $field) {
			if (empty($field)) {
				continue;
			}

			if (strpos($field, '!') !== FALSE) {
				$field = substr($field, 1);
				$field_regex = str_replace('/', '\/', str_replace('\/', '/', $field));
				if (@preg_match("/{$field_regex}/i", $flent[$key])) {
					return FALSE;
				}
			}
			else {
				$field_regex = str_replace('/', '\/', str_replace('\/', '/', $field));
				if (!@preg_match("/{$field_regex}/i", $flent[$key])) {
					return FALSE;
				}
			}
		}
	}
	return TRUE;
}


// For subnet addresses - Determine if alert host 'dest' is within a local IP range.
function ip_in_pfb_localsub($subnet) {
	global $pfb_localsub;

	if (!empty($pfb_localsub)) {
		foreach ($pfb_localsub as $line) {
			if (ip_in_subnet($subnet, $line)) {
				return TRUE;
			}
		}
	}
	return FALSE;
}


// Parse filter log for pfBlockerNG alerts
function conv_log_filter_lite($logfile, $nentries, $tail, $pfbdenycnt, $pfbpermitcnt, $pfbmatchcnt) {
	global $pfb, $rule_list, $filterfieldsarray;
	$fields_array	= array();
	$denycnt	= $permitcnt = $matchcnt = 0;
	$logarr		= '';

	if (file_exists($logfile)) {
		// Collect filter.log entries
		exec("/usr/local/sbin/clog {$logfile} | {$pfb['grep']} -v '\"CLOG\"\|\"\033\"' | {$pfb['grep']} 'filterlog:' | /usr/bin/tail -r -n {$tail}", $logarr);
	} else {
		 return;
	}

	if (!empty($logarr) && !empty($rule_list['id'])) {
		foreach ($logarr as $logent) {

			$pfbalert	= array();
			$flog		= explode(' ', $logent);
			// Remove 'extra space' from single date entry (days 1-9)
			if (empty($flog[1])) {
				array_splice($flog, 1, 1);
			}
			$rule_data	= explode(',', $flog[5]);

			// Skip alert if rule is not a pfBNG alert
			if (!in_array($rule_data[3], $rule_list['id'])) {
				continue;
			}

			$pfbalert[0]		= $rule_data[3];	// Rulenum
			$pfbalert[1]		= $rule_data[4];	// Realint
			$pfbalert[3]		= $rule_data[6];	// Act
			$pfbalert[4]		= $rule_data[8];	// Version

			if ($pfbalert[4] == 4) {
				$pfbalert[5]	= $rule_data[15];	// Protocol ID
				$pfbalert[6]	= $rule_data[16];	// Protocol
				$pfbalert[7]	= $rule_data[18];	// SRC IP
				$pfbalert[8]	= $rule_data[19];	// DST IP
				$pfbalert[9]	= $rule_data[20];	// SRC Port
				$pfbalert[10]	= $rule_data[21];	// DST Port
				$pfbalert[11]	= $rule_data[23];	// TCP Flags
			} else {
				$pfbalert[5]	= $rule_data[13];	// Protocol ID
				$pfbalert[6]	= $rule_data[12];	// Protocol
				$pfbalert[7]	= $rule_data[15];	// SRC IP
				$pfbalert[8]	= $rule_data[16];	// DST IP
				$pfbalert[9]	= $rule_data[17];	// SRC Port
				$pfbalert[10]	= $rule_data[18];	// DST Port
				$pfbalert[11]	= $rule_data[20];	// TCP Flags
			}

			if ($pfbalert[5] == 6 || $pfbalert[5] == 17) {
				// skip
			} else {
				$pfbalert[9] = $pfbalert[10] = $pfbalert[11] = '';
			}

			$pfbalert[99] = "{$flog[0]} {$flog[1]} {$flog[2]}"; // Date/Timestamp

			// Skip repeated alerts 
			if ("{$pfbalert[1]}{$pfbalert[3]}{$pfbalert[7]}{$pfbalert[8]}{$pfbalert[10]}" == $previous_alert) {
				continue;
			}

			$pfbalert[2] = convert_real_interface_to_friendly_descr($rule_data[4]);					// Friendly Interface Name
			$pfbalert[6] = str_replace('TCP', 'TCP-', strtoupper($pfbalert[6]), $pfbalert[6]) . $pfbalert[11];	// Protocol Flags

			// If alerts filtering is selected, process filters as required.
			if ($pfb['filterlogentries'] && !pfb_match_filter_field($pfbalert, $filterfieldsarray)) {
				continue;
			}

			if ($pfbalert[3] == 'block') {
				if ($denycnt < $pfbdenycnt) {
					$fields_array['Deny'][] = $pfbalert;
					$denycnt++;
				}
			}
			elseif ($pfbalert[3] == 'pass') {
				if ($permitcnt < $pfbpermitcnt) {
					$fields_array['Permit'][] = $pfbalert;
					$permitcnt++;
				}
			}
			elseif ($pfbalert[3] == 'unkn(%u)') {
				if ($matchcnt < $pfbmatchcnt) {
					$fields_array['Match'][] = $pfbalert;
					$matchcnt++;
				}
			}

			// Exit function if sufficinet matches found.
			if ($denycnt >= $pfbdenycnt && $permitcnt >= $pfbpermitcnt && $matchcnt >= $pfbmatchcnt) {
				unset($pfbalert, $logarr);
				return $fields_array;
			}

			// Collect details for repeated alert comparison
			$previous_alert = "{$pfbalert[1]}{$pfbalert[3]}{$pfbalert[7]}{$pfbalert[8]}{$pfbalert[10]}";
		}
		unset($pfbalert, $logarr);
		return $fields_array;
	}
}


$pgtitle = gettext('pfBlockerNG: Alerts');
include_once('head.inc');
?>
<body link="#000000" vlink="#0000CC" alink="#000000">
<form action="/pfblockerng/pfblockerng_alerts.php" method="post">
<input type="hidden" name="ip" id="ip" value=""/>
<input type="hidden" name="table" id="table" value=""/>
<input type="hidden" name="descr" id="descr" value=""/>
<input type="hidden" name="cidr" id="cidr" value=""/>
<input type="hidden" name="domain" id="domain" value=""/>
<?php

include_once('fbegin.inc');

// refresh every 60 secs
if ($alertrefresh == 'on') {
	if ($pfb['filterlogentries']) {
		// Refresh page with 'Filter options' if defined.
		$refreshentries = urlencode(serialize($filterfieldsarray));
		echo "<meta http-equiv=\"refresh\" content=\"60;url=/pfblockerng/pfblockerng_alerts.php?refresh={$refreshentries}\" />\n";
	} else {
		echo "<meta http-equiv=\"refresh\" content=\"60;url=/pfblockerng/pfblockerng_alerts.php\" />\n";
	}
}
if ($savemsg) {
	print_info_box($savemsg);
}

$skipcount = $counter = $resolvecounter = 0;
?>
	<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
			<?php
				$tab_array = array();
				$tab_array[] = array(gettext("General"), false, "/pkg_edit.php?xml=pfblockerng.xml");
				$tab_array[] = array(gettext("Update"), false, "/pfblockerng/pfblockerng_update.php");
				$tab_array[] = array(gettext("Alerts"), true, "/pfblockerng/pfblockerng_alerts.php");
				$tab_array[] = array(gettext("Reputation"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_reputation.xml");
				$tab_array[] = array(gettext("IPv4"), false, "/pkg.php?xml=/pfblockerng/pfblockerng_v4lists.xml");
				$tab_array[] = array(gettext("IPv6"), false, "/pkg.php?xml=/pfblockerng/pfblockerng_v6lists.xml");
				$tab_array[] = array(gettext("DNSBL"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_dnsbl.xml");
				$tab_array[] = array(gettext("Country"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_top20.xml");
				$tab_array[] = array(gettext("Logs"), false, "/pfblockerng/pfblockerng_log.php");
				$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_sync.xml");
				display_top_tabs($tab_array, true);
			?>
		</td>
	</tr>
	<tr>
	<td><div id="mainarea">
		<table id="maintable" class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="4">
			<tr>
				<td colspan="3" class="vncell" align="left"><?php echo gettext("LINKS :"); ?>&nbsp;
				<a href='/firewall_aliases.php' target="_blank"><?php echo gettext("Firewall Alias"); ?></a>&nbsp;
				<a href='/firewall_rules.php' target="_blank"><?php echo gettext("Firewall Rules"); ?></a>&nbsp;
				<a href='/diag_logs_filter.php' target="_blank"><?php echo gettext("Firewall Logs"); ?></a><br /></td>
			</tr>
			<tr>
			<td width="10%" class="vncell"><?php echo gettext('Alert Settings'); ?></td>
			<td width="90%" class="vtable">
				<input name="pfbdenycnt" type="text" class="formfld unknown" id="pdbdenycnt" size="1"
					title="Enter the number of 'Deny' Alerts to Show"  value="<?=htmlspecialchars($pfbdenycnt);?>"/>
				<?php printf(gettext('%sDeny%s.&emsp;') , '<strong>', '</strong>'); ?>
				<?php if ($pfb['dnsbl'] == "on"): ?>
					<input name="pfbdnscnt" type="text" class="formfld unknown" id="pdbdnscnt" size="1"
						title="Enter the number of 'DNSBL' Alerts to Show" value="<?=htmlspecialchars($pfbdnscnt);?>"/>
					<?php printf(gettext('%sDNSBL%s.&emsp;') , '<strong>', '</strong>'); ?>
				<?php endif; ?>
				<input name="pfbpermitcnt" type="text" class="formfld unknown" id="pdbpermitcnt" size="1"
					title="Enter the number of 'Permit' Alerts to Show" value="<?=htmlspecialchars($pfbpermitcnt);?>"/>
				<?php printf(gettext('%sPermit%s.&emsp;'), '<strong>', '</strong>'); ?>
				<input name="pfbmatchcnt" type="text" class="formfld unknown" id="pdbmatchcnt" size="1"
					title="Enter the number of 'Match' Alerts to Show" value="<?=htmlspecialchars($pfbmatchcnt); ?>"/>
				<?php printf(gettext('%sMatch%s.'), '<strong>', '</strong>'); ?>

				<?php echo gettext('&emsp;Auto-Refresh');?>&emsp;<input name="alertrefresh" type="checkbox" value="on"
					title="Click to enable Auto-Refresh of this Tab once per minute"
				<?php if ($config['installedpackages']['pfblockerngglobal']['alertrefresh']=="on") echo "checked"; ?>/>&nbsp;

				<?php echo gettext('&nbsp;Auto-Resolve');?>&emsp;<input name="hostlookup" type="checkbox" value="on"
					title="Click to enable Auto-Resolve of Hostnames. Country Blocks/Permit/Match Lists will not auto-resolve"
				<?php if ($config['installedpackages']['pfblockerngglobal']['hostlookup']=="on") echo "checked"; ?>/>&emsp;
				<input name="save" type="submit" class="formbtns" value="Save" title="<?=gettext('Save settings');?>"/><br />

				<?php printf(gettext('Enter number of log entries to view.')); ?>&emsp;
				<?php printf(gettext("Currently Suppressing &nbsp; %s$pfbsupp_cnt%s &nbsp; Hosts."), '<strong>', '</strong>');?>
			</td>
			</tr>
			<tr>
				<td colspan="3" class="listtopic"><?php echo gettext("Alert Log View Filter"); ?></td>
			</tr>
			<tr id="filter_enable_row" style="display:<?php if (!$pfb['filterlogentries']) {echo "table-row;";} else {echo "none;";} ?>">
				<td width="10%" class="vncell"><?php echo gettext('Filter Options'); ?></td>
				<td width="90%" class="vtable">
					<input name="show_filter" id="show_filter" type="button" class="formbtns" value="<?=gettext("Show Filter");?>"
						onclick="enable_showFilter();" />
					&emsp;<?=gettext("Click to display advanced filtering options dialog");?>
				</td>
			</tr>
			<tr id="filter_options_row" style="display:<?php if (!$pfb['filterlogentries']) {echo "none;";} else {echo "table-row;";} ?>">
				<td colspan="2">
					<table width="100%" border="0" cellspacing="0" cellpadding="1" summary="action">
					<tr>
						<td valign="top">
							<div align="center"><?=gettext("Date");?></div>
							<div align="center"><input id="filterlogentries_date" name="filterlogentries_date" class="formfld search"
								type="text" size="15" value="<?= $filterfieldsarray[99] ?>" /></div>
						</td>
						<td valign="top">
							<div align="center"><?=gettext("Source IP Address");?></div>
							<div align="center"><input id="filterlogentries_srcip" name="filterlogentries_srcip" class="formfld search"
								type="text" size="28" value="<?= $filterfieldsarray[7] ?>" /></div>
						</td>
						<td valign="top">
							<div align="center"><?=gettext("Source Port");?></div>
							<div align="center"><input id="filterlogentries_srcport" name="filterlogentries_srcport" class="formfld search"
								type="text" size="5" value="<?= $filterfieldsarray[9] ?>" /></div>
						</td>
						<td valign="top">
							<div align="center"><?=gettext("Interface");?></div>
							<div align="center"><input id="filterlogentries_int" name="filterlogentries_int" class="formfld search"
								type="text" size="15" value="<?= $filterfieldsarray[2] ?>" /></div>
						</td>
					</tr>
					<tr>
						<td valign="top">
							<div align="center"><?=gettext("Rule Number Only");?></div>
							<div align="center"><input id="filterlogentries_rule" name="filterlogentries_rule" class="formfld search"
								type="text" size="15" value="<?= $filterfieldsarray[0] ?>" /></div>
						</td>
						<td valign="top">
							<div align="center"><?=gettext("Destination IP Address/Domain Name");?></div>
							<div align="center"><input id="filterlogentries_dstip" name="filterlogentries_dstip" class="formfld search"
								type="text" size="28" value="<?= $filterfieldsarray[8] ?>" /></div>
						</td>
						<td valign="top">
							<div align="center"><?=gettext("Destination Port");?></div>
							<div align="center"><input id="filterlogentries_dstport" name="filterlogentries_dstport" class="formfld search"
								type="text" size="5" value="<?= $filterfieldsarray[10] ?>" /></div>
						</td>
						<td valign="top">
							<div align="center"><?=gettext("Protocol");?></div>
							<div align="center"><input id="filterlogentries_proto" name="filterlogentries_proto" class="formfld search"
								type="text" size="15" value="<?= $filterfieldsarray[6] ?>" /></div>
						</td>
						<td valign="top" colspan="3">
							&nbsp;
						</td>
					</tr>

						<?php if ($pfb['dnsbl'] == 'on'): ?>
							<tr>
								<td valign="top">
									<div align="center"><?=gettext("DNSBL URL");?></div>
									<div align="center"><input id="filterlogentries_dnsbl" name="filterlogentries_dnsbl"
									class="formfld search" type="text" size="15" value="<?= $filterfieldsarray[90] ?>" /></div>
								</td>
								<td valign="top" colspan="3">
									&nbsp;
								</td>
							</tr>
						<?php else: ?>
							<tr>
								<td valign="top" colspan="3">
									&nbsp;
								</td>
							</tr>
						<?php endif; ?>

					<tr>
						<td colspan="3" style="vertical-align:bottom">
							<br /><?printf(gettext('Regex Style Matching Only! %1$s Regular Expression Help link%2$s.'), '
								<a target="_blank" href="http://www.php.net/manual/en/book.pcre.php">', '</a>');?>&emsp;
								<?=gettext("Precede with exclamation (!) as first character to exclude match.) ");?>
							<br /><?printf(gettext("Example: ( ^80$ - Match Port 80, ^80$|^8080$ - Match both port 80 & 8080 ) "));?><br />
						</td>
					</tr>
					<tr>
						<td colspan="3" style="vertical-align:bottom">
							<div align="left"><input id="filterlogentries_submit" name="filterlogentries_submit" type="submit"
								class="formbtns" value="<?=gettext("Apply Filter");?>" title="<?=gettext("Apply filter"); ?>" />
								&emsp;<input id="filterlogentries_clear" name="filterlogentries_clear" type="submit"
								class="formbtns" value="<?=gettext("Clear");?>" title="<?=gettext("Remove filter");?>" />
								&emsp;<input id="filterlogentries_hide" name="filterlogentries_hide" type="button"
								class="formbtns" value="<?=gettext("Hide");?>" onclick="enable_hideFilter();"
								title="<?=gettext("Hide filter options");?>" /></div>
						</td>
					</tr>
					</table>
				</td>
			</tr>

<!--Create three output windows 'Deny', 'DNSBL', 'Permit' and 'Match'-->
<?php foreach (array (	'Deny'		=> "{$pfb['denydir']}/* {$pfb['nativedir']}/*",
			'DNSBL'		=> "{$pfb['dnsdir']}",
			'Permit'	=> "{$pfb['permitdir']}/* {$pfb['nativedir']}/*",
			'Match'		=> "{$pfb['matchdir']}/* {$pfb['nativedir']}/*" ) as $type => $pfbfolder ):

	switch($type) {
		case 'Deny':
			$rtype = 'block';
			$pfbentries = "{$pfbdenycnt}";
			break;
		case 'Permit':
			$rtype = 'pass';
			$pfbentries = "{$pfbpermitcnt}";
			break;
		case 'Match':
			$rtype = 'unkn(%u)';
			$pfbentries = "{$pfbmatchcnt}";
			break;
		case 'DNSBL':
			$pfbentries = "{$pfbdnscnt}";
	}

	// Skip table output if $pfbentries is zero.
	if ($pfbentries == 0 && $skipcount != 3) {
		$skipcount++;
		continue;
	}

	// Print alternating line shading
	$alertRowEvenClass	= "style='background-color: #D8D8D8;'";
	$alertRowOddClass	= "style='background-color: #E8E8E8;'";
?>
			<table id="maintable" class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="6">
			<tr>
				<!--Print table info-->
				<td colspan="2" class="listtopic">
					<?php printf(gettext("&nbsp;{$type}&emsp; - &nbsp; Last %s Alert Entries."),"{$pfbentries}"); ?>
				</td>
			</tr>

<td width="100%" colspan="2">
<table id="pfbAlertsTable" style="table-layout: fixed;" width="100%" class="sortable" border="0" cellpadding="0" cellspacing="0">

<?php
// Process dns array for: DNSBL and generate output
if ($pfb['dnsbl'] == 'on' && $type == 'DNSBL') {
?>

	<colgroup>
		<col width="8%" align="center" axis="date">
		<col width="7%" align="center" axis="string">
		<col width="12%" align="center" axis="string">
		<col width="59%" align="center" axis="string">
		<col width="14%" align="center" axis="string">
	</colgroup>
	<thead>
		<tr class="sortableHeaderRowIdentifier">
			<th class="listhdrr" axis="date"><?php echo gettext("Date"); ?></th>
			<th class="listhdrr" axis="string"><?php echo gettext("IF"); ?></th>
			<th class="listhdrr" axis="string"><?php echo gettext("Source"); ?></th>
			<th class="listhdrr" axis="string"><?php echo gettext("Domain/Referer|URI|Agent"); ?></th>
			<th class="listhdrr" axis="string"><?php echo gettext("List"); ?></th>
		</tr>
	</thead>
	<tbody>

<?php
	$dns_array = $final = array();
	if (file_exists("{$pfb['dnslog']}")) {
		if (($handle = fopen("{$pfb['dnslog']}", 'r')) !== FALSE) {
			while (($line = fgetcsv($handle)) !== FALSE) {

				// Define missing data for HTTPS alerts
				if ($line[0] == 'DNSBL Reject HTTPS') {
					$line[3] = '<small>Unknown</small>';
					$line[4] = ' Not available for HTTPS alerts';
				}

				// Remove duplicate domain/srcips
				if (("{$line[2]}{$line[3]}") != $pdomain) {
					$final[] = $line;
				}
				$pdomain = "{$line[2]}{$line[3]}";
			}
		}
		fclose($handle);

		if (!empty($final)) {
			$dns_array = array_slice(array_reverse($final), 0, $pfbentries);
		}
	}

	if (!empty($dns_array)) {

		$supp_dom_txt  = "Clicking this Suppression icon, will immediately remove the blocked domain from Unbound Resolver.\n\n";
		$supp_dom_txt .= "To manually add Domain(s), edit the 'Domain Suppression' list in the DNSBL tab.\n";
		$supp_dom_txt .= "Manual entries will not immediatelty remove existing blocked hosts.";

		foreach ($dns_array as $aline) {

			// Determine interface name based on Source IP address
			$pfbalertdnsbl[1] = 'LAN';		// Define LAN Interface as 'default'
			if (!empty($dnsbl_int)) {
				foreach ($dnsbl_int as $subnet) {
					if (strpos($aline[3], 'Unknown') !== FALSE) {
						$pfbalertdnsbl[1] = 'Unknown';
						break;
					} elseif (ip_in_subnet($aline[3], $subnet[0])) {
						$pfbalertdnsbl[1] = "{$subnet[1]}";
						break;
					}
				}
			}

			$pfbalertdnsbl[99]	= $aline[1];	// Timestamp

			// SRC IP Address and Hostname
			if (isset($local_hosts[$aline[3]])) {
				$pfbalertdnsbl[7] = "{$aline[3]}<br /><small>{$local_hosts[$aline[3]]}</small>";
			} else {
				$pfbalertdnsbl[7] = $aline[3];
			}

			$pfbalertdnsbl[8]	= $aline[2];	// Blocked Domain
			$pfbalertdnsbl[90]	= $aline[4];	// DNSBL URL

			// Add 'https' icon to Domains as required.
			$pfb_https = '';
			if (strpos($aline[4], 'https://') !== FALSE || strpos($aline[4], 'Not available for HTTPS alerts') !== FALSE) {
				$pfb_https = "<img src=\"/themes/{$g['theme']}/images/icons/icon_frmfld_pwd.png\" alt='' width='11' height='11' border='0' 
						title='HTTPS alerts are not fully logged due to Browser security' />";
			}

			// If alerts filtering is selected, process filters as required.
			if ($pfb['filterlogentries'] && !pfb_match_filter_field($pfbalertdnsbl, $filterfieldsarray)) {
				continue;
			}

			// Collect the list that contains the blocked domain
			$domain = str_replace('.', '\.', $aline[2]);
			$sed_cmd = "{$pfb['sed']} -e 's/^.*[a-zA-Z]\///' -e 's/:.*//' -e 's/\..*/ /'";
			$pfb_query = exec("{$pfb['grep']} -Hm1 ' \"{$domain} 60 IN A' {$pfb['dnsdir']}/* | {$sed_cmd}");
			$pfb_alias = exec("{$pfb['grep']} -Hm1 ' \"{$domain} 60 IN A' {$pfb['dnsalias']}/* | {$sed_cmd}");

			if (empty($pfb_query)) {
				$pfb_query = 'no match';
			}

			// Truncate long list names
			$pfb_matchtitle = "The DNSBL Feed and Alias that blocked the indicated Domain.";
			if (strlen($pfb_query) >= 17 || strlen($pfb_alias) >= 25) {
				$pfb_matchtitle = "Feed: {$pfb_query} | Alias: {$pfb_alias}";
				$pfb_query	= substr($pfb_query, 0, 16) . '...';
				$pfb_alias	= substr($pfb_alias, 0, 24) . '...';
			}

			// Print alternating line shading
			$alertRowClass = $counter % 2 ? $alertRowEvenClass : $alertRowOddClass;

			$alert_dom  = "<a href='/pfblockerng/pfblockerng_threats.php?domain={$aline[2]}' title=\" " . gettext("Resolve Domain via DNS lookup");
			$alert_dom .= "\"> <img src=\"/themes/{$g['theme']}/images/icons/icon_log.gif\" width='11' height='11' border='0' ";
			$alert_dom .= "alt=\"Icon Reverse Resolve with DNS\" style=\"cursor: pointer;\" /></a>";

			// Collect existing suppression list
			$dnssupp_ex = collectsuppression();
			if (!in_array($pfbalertdnsbl[8], $dnssupp_ex)) {
				$supp_dom  = "<input type='image' name='addsuppressdom[]' onclick=\"domainlistid('{$domain}');\" ";
				$supp_dom .= "src=\"../themes/{$g['theme']}/images/icons/icon_pass_add.gif\" alt='' title=\"";
				$supp_dom .= gettext($supp_dom_txt) . "\" border='0' width='11' height='11' />&emsp;";
			}
			else {
				$supp_dom  = "<img src=\"../themes/{$g['theme']}/images/icons/icon_plus_d.gif\" alt='' border='0' width='11' height='11' ";
				$supp_dom .= "title='" . gettext("This domain is already in the DNSBL Suppress List") . "' />&emsp;";
			}

			// Truncate long URLs
			$url_title = '';
			if (strlen($pfbalertdnsbl[90]) >= 72) {
				$url_title = "{$pfbalertdnsbl[90]}";
				$pfbalertdnsbl[90] = substr(str_replace(array('?', '-'), '', $pfbalertdnsbl[90]), 0, 69) . '...';
			}

			echo "<tr {$alertRowClass}>
				<td class='listMRr' align='center'>{$pfbalertdnsbl[99]}</td>
				<td class='listMRr' align='center'><small>{$pfbalertdnsbl[1]}</small></td>
				<td class='listMRr' align='center'>{$pfbalertdnsbl[7]}</td>
				<td class='listMRr' align='left' title='{$url_title}' sorttable_customkey='{$pfbalertdnsbl[8]}'>
					{$alert_dom} {$supp_dom}{$pfbalertdnsbl[8]} {$pfb_https}<br />&emsp;&emsp;&emsp;<small>{$pfbalertdnsbl[90]}</small></td>
				<td class='listbg'  align='center' title='{$pfb_matchtitle}' style='white-space: word;'>
					{$pfb_query}<br /><small>{$pfb_alias}</small></td></tr>";
			$counter++;
		}
	}
}

if ($type != 'DNSBL') {
?>

	<colgroup>
		<col width="7%" align="center" axis="date">
		<col width="6%" align="center" axis="string">
		<col width="15%" align="center" axis="string">
		<col width="6%" align="center" axis="string">
		<col width="21%" align="center" axis="string">
		<col width="21%" align="center" axis="string">
		<col width="3%" align="center" axis="string">
		<col width="13%" align="center" axis="string">
	</colgroup>
	<thead>
		<tr class="sortableHeaderRowIdentifier">
			<th class="listhdrr" axis="date"><?php echo gettext("Date"); ?></th>
			<th class="listhdrr" axis="string"><?php echo gettext("IF"); ?></th>
			<th class="listhdrr" axis="string"><?php echo gettext("Rule"); ?></th>
			<th class="listhdrr" axis="string"><?php echo gettext("Proto"); ?></th>
			<th class="listhdrr" axis="string"><?php echo gettext("Source"); ?></th>
			<th class="listhdrr" axis="string"><?php echo gettext("Destination"); ?></th>
			<th class="listhdrr" axis="string"><?php echo gettext("CC"); ?></th>
			<th class="listhdrr" axis="string"><?php echo gettext("List"); ?></th>
		</tr>
	</thead>
	<tbody>

<?php
}

// Process fields array for: Deny/Permit/Match and generate output
if (!empty($fields_array[$type]) && !empty($rule_list) && $type != 'DNSBL') {
	foreach ($fields_array[$type] as $fields) {
		$rulenum = $alert_ip = $supp_ip = $pfb_query = '';

		/* Fields_array Reference	[0]	= Rulenum			[6]	= Protocol
						[1]	= Real Interface		[7]	= SRC IP
						[2]	= Friendly Interface Name	[8]	= DST IP
						[3]	= Action			[9]	= SRC Port
						[4]	= Version			[10]	= DST Port
						[5]	= Protocol ID			[11]	= Flags
						[99]	= Timestamp	*/

		$rulenum = $fields[0];
		if ($counter < $pfbentries) {
			// Cleanup port output
			if ($fields[6] == 'ICMP' || $fields[6] == 'ICMPV6') {
				$srcport = '';
			} else {
				$srcport = ":{$fields[9]}";
				$dstport = ":{$fields[10]}";
			}

			// Don't add suppress icon to Country block lines
			if (in_array(substr($rule_list[$rulenum]['name'], 0, -3), $continents)) {
				$pfb_query = 'Country';
			}

			// Add DNS resolve and suppression icons to external IPs only. GeoIP code to external IPs only.
			if (in_array($fields[8], $pfb_local) || ip_in_pfb_localsub($fields[8])) {
				// Destination is gateway/NAT/VIP
				$rule = "{$rule_list[$rulenum]['name']}<br /><small>({$rulenum})</small>";
				$host = $fields[7];

				$alert_ip  = "<a href='/pfblockerng/pfblockerng_threats.php?host={$host}' title=\" " . gettext("Resolve host via Rev. DNS lookup");
				$alert_ip .= "\"> <img src=\"/themes/{$g['theme']}/images/icons/icon_log.gif\" width='11' height='11' border='0' ";
				$alert_ip .= "alt=\"Icon Reverse Resolve with DNS\" style=\"cursor: pointer;\" /></a>";

				if ($pfb_query != 'Country' && $rtype == 'block' && $pfb['supp'] == 'on') {
					$supp_ip  = "<input type='image' name='addsuppress[]' onclick=\"hostruleid('{$host}','{$rule_list[$rulenum]['name']}');\" ";
					$supp_ip .= "src=\"../themes/{$g['theme']}/images/icons/icon_pass_add.gif\" alt='' title=\"";
					$supp_ip .= gettext($supp_ip_txt) . "\" border='0' width='11' height='11' />";
				}

				if ($rtype == 'block' && $hostlookup == 'on') {
					$hostname = getpfbhostname('src', $fields[7], $counter, $fields[8]);
				} else {
					$hostname = '';
				}
		
				$src_icons_1	= "{$alert_ip}&nbsp;{$supp_ip}&nbsp;";
				$src_icons_2	= "{$alert_ip}&nbsp;";
				$dst_icons_1	= '';
				$dst_icons_2	= '';

			} else {
				// Outbound
				$rule = "{$rule_list[$rulenum]['name']}<br /><small>({$rulenum})</small>";
				$host = $fields[8];

				$alert_ip  = "<a href='/pfblockerng/pfblockerng_threats.php?host={$host}' title=\"" . gettext("Resolve host via Rev. DNS lookup");
				$alert_ip .= "\"> <img src=\"/themes/{$g['theme']}/images/icons/icon_log.gif\" width='11' height='11' border='0' ";
				$alert_ip .= "alt=\"Icon Reverse Resolve with DNS\" style=\"cursor: pointer;\" /></a>";

				if ($pfb_query != 'Country' && $rtype == 'block' && $pfb['supp'] == 'on') {
					$supp_ip  = "<input type='image' name='addsuppress[]' onclick=\"hostruleid('{$host}','{$rule_list[$rulenum]['name']}');\" ";
					$supp_ip .= "src=\"../themes/{$g['theme']}/images/icons/icon_pass_add.gif\" alt='' title=\"";
					$supp_ip .= gettext($supp_ip_txt) . "\" border='0' width='11' height='11' />";
				}

				if ($rtype == 'block' && $hostlookup == 'on') {
					$hostname = getpfbhostname('dst', $fields[8], $counter, $fields[7]);
				} else {
					$hostname = '';
				}

				$src_icons_1	= '';
				$src_icons_2	= '';
				$dst_icons_1	= "{$alert_ip}&nbsp;{$supp_ip}&nbsp;";
				$dst_icons_2	= "{$alert_ip}&nbsp;";
			}

			// Determine Country code of host
			if (is_ipaddrv4($host)) {
				$country = substr(exec("{$pathgeoip} -f {$pathgeoipdat} {$host}"), 23, 2);
			} else {
				$country = substr(exec("{$pathgeoip6} -f {$pathgeoipdat6} {$host}"), 26, 2);
			}

			// Find the header which alerted this host
			if ($pfb_query != 'Country') {
				if (strpos($rule, 'pfB_DNSBLIP') !== FALSE) {
					$pfb_match[1] = 'DNSBLIP';	// Default pfB_DNSBLIP
					$pfb_match[2] = '';
				}
				else {
					// Report specific ET IQRisk details
					if ($pfb['et_header'] && strpos($pfb_query, "{$et_header}") !== FALSE) {
						$pfbfolder = "{$pfb['etdir']}/*";
					}

					$pfb_query = find_reported_header($host, $pfbfolder, FALSE);

					// Split list column into two lines.
					$pfb_match[1] = "{$pfb_query[1]}";
					$pfb_match[2] = "{$pfb_query[0]}";

					// Remove Suppression Icon for 'no match' and all subnets except for '/32 & /24'.
					if ($pfb_query[2]) {
						$src_icons = $src_icons_1;
						$dst_icons = $dst_icons_1;
					} else {
						$src_icons = $src_icons_2;
						$dst_icons = $dst_icons_2;
					}
				}
			}
			else {
				$pfb_match[1] = 'Country';
				$pfb_match[2] = '';
				$src_icons = $src_icons_2;	// Remove 'suppress icon'
				$dst_icons = $dst_icons_2;
			}

			// Add []'s to IPv6 addresses and add a zero-width space as soft-break opportunity after each colon if we have an IPv6 address (from Snort)
			if ($fields[4] == '6') {
				$fields[97] = '[' . str_replace(':', ':&#8203;', $fields[7]) . ']';
				$fields[98] = '[' . str_replace(':', ':&#8203;', $fields[8]) . ']';
			}
			else {
				$fields[97] = $fields[7];
				$fields[98] = $fields[8];
			}

			// Truncate long list names
			$pfb_matchtitle = "Country Block rules cannot be Suppressed.\n\n To allow a particular Country IP, either remove the particular Country or add the host\nto a Permit Alias in the Firewall tab.\n\nIf the IP is not listed beside the list, this means that the block is a /32 entry.\nOnly /32 or /24 CIDR hosts can be suppressed.\n\nIf (Duplication) Checking is not enabled. You may see /24 and /32 CIDR blocks for a given blocked host";

			if (strlen($pfb_match[1]) >= 17) {
				$pfb_matchtitle = $pfb_match[1];
				$pfb_match[1]	= substr($pfb_match[1], 0, 16) . '...';
			}

			// Print alternating line shading 
			$alertRowClass = $counter % 2 ? $alertRowEvenClass : $alertRowOddClass;
			echo "<tr {$alertRowClass}>
				<td class='listMRr' align='center'>{$fields[99]}</td>
				<td class='listMRr' align='center'><small>{$fields[2]}</small></td>
				<td class='listMRr' align='center' title='The pfBlockerNG Rule that Blocked this Host.'>{$rule}</td>
				<td class='listMRr' align='center'><small>{$fields[6]}</small></td>
				<td class='listMRr' align='center' sorttable_customkey='{$fields[97]}'>{$src_icons}{$fields[97]}{$srcport}<br />
					<small>{$hostname['src']}</small></td>
				<td class='listMRr' align='center' sorttable_customkey='{$fields[98]}'>{$dst_icons}{$fields[98]}{$dstport}<br />
					<small>{$hostname['dst']}</small></td>
				<td class='listMRr' align='center'>{$country}</td>
				<td class='listbg' align='center' title='{$pfb_matchtitle}' style=\"font-size: 10px word-wrap:break-word;\">
					{$pfb_match[1]}<br /><small>{$pfb_match[2]}</small></td></tr>";
			$counter++;
			if ($rtype == 'block') {
				$resolvecounter = $counter;
			}
		}
	}
}
?>

	</tbody>
	<tr>
		<!--Print final table info-->
		<?php
			if ($pfbentries != $counter) {
				$msg = ' - Insufficient Firewall Alerts found.';
			}
			if ($type == 'DNSBL') {
				$colspan = "colspan='7'";
			} else {
				$colspan = "colspan='8'";
			}

			echo (" <td {$colspan} style='font-size:10px; background-color: #F0F0F0;' >Found {$counter} Alert Entries {$msg}</td>");
			$counter = 0; $msg = '';
		?>
	</tr>
	</table>
	</table>
<?php endforeach; ?>	<!--End - Create three output windows 'Deny', 'Permit' and 'Match'-->
<?php unset ($fields_array); ?>
</td></tr>
</table>
</td>

<script type="text/javascript">
//<![CDATA[

// This function inserts the passed HOST and table values into a hidden Form fields for postback.
function hostruleid(host,table) {
	document.getElementById("ip").value = host;
	document.getElementById("table").value = table;

	var description = prompt("Please enter Suppression Description");
	document.getElementById("descr").value = description;

	if (description.value != "") {
		var cidr = prompt("Please enter CIDR [ 32 or 24 CIDR only supported ]","32");
		document.getElementById("cidr").value = cidr;
	}
}

// This function collects the domain and list for the DNSBL suppression function.
function domainlistid(domain,domainlist) {
	document.getElementById("domain").value = domain;
}

// Auto-resolve of alerted hostnames
function findhostnames(counter) {
	getip = jQuery('#gethostname_' + counter).attr('name');
	geturl = "/pfblockerng/pfblockerng_alerts_ar.php";
	jQuery.get( geturl, { "getpfhostname": getip } )
	.done(function( data ) {
			jQuery('#gethostname_' + counter).prop('title' , data );
			var str = data;
			if(str.length > 32) str = str.substring(0,29)+"...";
			jQuery('#gethostname_' + counter).html( str );
		}
	)
}

var alertlines = <?php echo $resolvecounter; ?>;
var autoresolve = "<?php echo $config['installedpackages']['pfblockerngglobal']['hostlookup']; ?>";
if ( autoresolve == 'on' ) {
	for (alertcount = 0; alertcount < alertlines; alertcount++) {
		setTimeout(findhostnames(alertcount), 30);
	}
}

function enable_showFilter() {
	document.getElementById("filter_enable_row").style.display="none";
	document.getElementById("filter_options_row").style.display="table-row";
}

function enable_hideFilter() {
	document.getElementById("filter_enable_row").style.display="table-row";
	document.getElementById("filter_options_row").style.display="none";
}

//]]>
</script>
<?php include('fend.inc'); ?>
</form>
</body>
</html>