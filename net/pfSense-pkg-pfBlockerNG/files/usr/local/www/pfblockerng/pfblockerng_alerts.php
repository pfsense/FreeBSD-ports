<?php
/*
	pfBlockerNG_Alerts.php

	pfBlockerNG
	Copyright (c) 2015-2016 BBcan177@gmail.com
	All rights reserved.

	Portions of this code are based on original work done for
	pfSense from the following contributors:

	Parts based on works from Snort_alerts.php
	Copyright (c) 2016 Bill Meeks
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
$pathgeoip	= '/usr/local/bin/geoiplookup';
$pathgeoip6	= '/usr/local/bin/geoiplookup6';

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
$aglobal_array = array('pfbdenycnt' => 25, 'pfbpermitcnt' => 5, 'pfbmatchcnt' => 5, 'pfbdnscnt' => 5);
$pfb['aglobal'] = &$config['installedpackages']['pfblockerngglobal'];

$alertrefresh	= $pfb['aglobal']['alertrefresh'] != '' ? $pfb['aglobal']['alertrefresh'] : 'on';
$hostlookup	= $pfb['aglobal']['hostlookup'] != '' ? $pfb['aglobal']['hostlookup'] : 'on';
foreach ($aglobal_array as $type => $value) {
	${"$type"} = $pfb['aglobal'][$type] != '' ? $pfb['aglobal'][$type] : $value;
}

// Collect DNSBL suppression list
$pfb['dsupp'] = &$config['installedpackages']['pfblockerngdnsblsettings']['config'][0]['suppression'];
$dnssupp_ex = array();
$suppression = pfbng_text_area_decode($pfb['dnsblconfig']['suppression'], TRUE);
if (isset($suppression)) {
	foreach ($suppression as $dnssupp) {
		// Create 1) array for the suppressed domains 2) A string with the domain and comment text
		$dnssupp_ex[] = $dnssupp[0];
		$dnssupp_dat .= "{$dnssupp[0]}{$dnssupp[1]}\r\n";
	}
}

// Save Alerts tab customizations
if (isset($_POST['save'])) {
	$pfb['aglobal']['alertrefresh']	= htmlspecialchars($_POST['alertrefresh']) ?: 'off';
	$pfb['aglobal']['hostlookup']	= htmlspecialchars($_POST['hostlookup']) ?: 'off';
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

if ($_POST['filterlogentries_clear']) {
	$pfb['filterlogentries'] = FALSE;
	$filterfieldsarray = array();
}

// Add IP to the suppression alias
if ($_POST['addsuppress']) {
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
if ($_POST['addsuppressdom']) {
	$domain		= htmlspecialchars($_POST['domain']);
	$domainparse	= str_replace('.', '\.', $domain);

	// Query for Domain in Unbound DNSBL file.
	$dnsbl_query = exec("/usr/bin/grep -Hm1 ' \"{$domainparse} 60 IN A' {$pfb['dnsbl_file']}.conf");

	// Query Domain for CNAME(s)
	exec("/usr/bin/drill {$domain} @8.8.8.8 | /usr/bin/awk '/CNAME/ {sub(\"\.$\", \"\", $5); print $5;}'", $cname_list);
	if (!empty($cname_list)) {
		$cname = array();
		$dnsbl_query = 'Found';

		foreach ($cname_list as $query) {
			$cname[] = $query;
		}
	}

	// Save new suppress domain to suppress list.
	if (empty($dnsbl_query)) {
		$savemsg = gettext("Domain: [ ") . "{$domain}" . gettext(" ] does not exist in the Unbound Resolver DNSBL");
		exec("/usr/local/sbin/unbound-control -c {$pfb['dnsbldir']}/unbound.conf flush {$domain}.");
	}
	else {
		if (is_array($cname)) {
			// Remove Domain and CNAME(s) in Unbound resolver pfb_dnsbl.conf file
			$removed	= "{$domain} | ";
			$supp_string	= "{$domain}\r\n";
			exec("{$pfb['sed']} -i '' '/ \"{$domain} 60 IN A/d' {$pfb['dnsbl_file']}.conf");

			foreach ($cname as $name) {
				$removed	.= "{$name} | ";
				$supp_string	.= "{$name} # CNAME for ({$domain})\r\n";
				exec("{$pfb['sed']} -i '' '/ \"{$name} 60 IN A/d' {$pfb['dnsbl_file']}.conf");
			}
			$savemsg = gettext("Removed - Domain|CNAME(s) | ") . "{$removed}"
				. gettext("from Unbound Resolver DNSBL. You may need to flush your browsers DNS Cache");
		}
		else {
			// Remove domain from Unbound resolver pfb_dnsbl.conf file
			exec("{$pfb['sed']} -i '' '/ \"{$domain} 60 IN A/d' {$pfb['dnsbl_file']}.conf");
			$savemsg = gettext("Removed Domain: [ ") . "{$domain}" . gettext(" ] from Resolver DNSBL. You may need to flush your browsers DNS Cache");
		}

		$cache_dumpfile = '/var/tmp/unbound_cache';
		unlink_if_exists("{$cache_dumpfile}");
		$chroot_cmd = "chroot -u unbound -g unbound / /usr/local/sbin/unbound-control -c {$g['unbound_chroot_path']}/unbound.conf";

		exec("{$chroot_cmd} dump_cache > $cache_dumpfile");
		exec("{$chroot_cmd} reload");

		if (file_exists($cache_dumpfile) && filesize($cache_dumpfile) > 0) {
			exec("{$chroot_cmd} load_cache < $cache_dumpfile");
		}

		exec("/usr/local/sbin/unbound-control -c {$pfb['dnsbldir']}/unbound.conf flush {$domain}");
		if (is_array($cname)) {
			foreach ($cname as $name) {
				exec("/usr/local/sbin/unbound-control -c {$pfb['dnsbldir']}/unbound.conf flush {$name}");
			}
		}

		if (!in_array($domain, $dnssupp_ex)) {
			if (is_array($cname)) {
				$dnssupp_dat .= "{$supp_string}";
			} else {
				$dnssupp_dat .= "{$domain}";
			}
			$pfb['dsupp'] = base64_encode($dnssupp_dat);
			write_config("pfBlockerNG: Added {$domain} to DNSBL suppress list");
		}
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
if (is_array($config['virtualip']['vip'])) {
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
if (is_array($config['nat']['rule'])) {
	foreach ($config['nat']['rule'] as $natent) {
		$pfb_local[] = $natent['target'];

		// Collect NAT for Alerts hostlookup
		$local_hosts[$natent['target']] = strtolower("{$natent['descr']}");
	}
}

// Collect 1:1 NAT IP addresses for inbound/outbound list matching
if (is_array($config['nat']['onetoone'])) {
	foreach ($config['nat']['onetoone'] as $onetoone) {
		$pfb_local[] = $onetoone['source']['address'];
	}
}

// Convert any 'Firewall Aliases' to IP address format
if (is_array($config['aliases']['alias'])) {
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
if (is_array($config['interfaces'])) {
	foreach ($config['interfaces'] as $int) {
		if ($int['ipaddr'] != 'dhcp') {
			if (!empty($int['ipaddr']) && !empty($int['subnet'])) {
				if ($int['subnet'] >= 24) {
					$pfb_local = array_merge(subnetv4_expand("{$int['ipaddr']}/{$int['subnet']}"), $pfb_local);
				} else {
					$pfb_localsub[] = "{$int['ipaddr']}/{$int['subnet']}";
				}

				// Collect DNSBL Interfaces
				$dnsbl_int[] = array("{$int['ipaddr']}/{$int['subnet']}", "{$int['descr']}");

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
if (is_array($config['dhcpd'])) {
	foreach ($config['dhcpd'] as $dhcp) {
		if (is_array($dhcp['staticmap'])) {
			foreach ($dhcp['staticmap'] as $smap) {
				$local_hosts[$smap['ipaddr']] = strtolower("{$smap['hostname']}");
			}
		}
	}
}

// Collect Unbound Host overrides
$hosts = $config['unbound']['hosts'];
if (is_array($hosts)) {
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
$pgtitle = array(gettext('Firewall'), gettext("pfBlockerNG"), gettext("Alerts"));
include_once('head.inc');

// refresh every 60 secs
if ($alertrefresh == 'on') {
	if ($pfb['filterlogentries']) {
		// Refresh page with 'Filter options' if defined.
		$refreshentries = urlencode(serialize($filterfieldsarray));
		print ("<meta http-equiv=\"refresh\" content=\"60;url=/pfblockerng/pfblockerng_alerts.php?refresh={$refreshentries}\" />\n");
	} else {
		print ("<meta http-equiv=\"refresh\" content=\"60;url=/pfblockerng/pfblockerng_alerts.php\" />\n");
	}
}

if ($savemsg) {
	print_info_box($savemsg);
}

$tab_array   = array();
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

// Create Form
$form = new Form(false);
$form->setAction('/pfblockerng/pfblockerng_alerts.php');

// Build 'Shortcut Links' section
$section = new Form_Section(NULL);
$section->addInput(new Form_StaticText(
	NULL,
	'<small>'
	. '<a href="/firewall_aliases.php" target="_blank">Firewall Alias</a>&emsp;'
	. '<a href="/firewall_rules.php" target="_blank">Firewall Rules</a>&emsp;'
	. '<a href="/status_logs_filter.php" target="_blank">Firewall Logs</a></small>'
));
$form->add($section);

$section = new Form_Section('Alert Settings', 'alertsettings', COLLAPSIBLE|SEC_CLOSED);
$form->add($section);

// Build 'Alert Settings' group section
$group = new Form_Group(NULL);
$group->add(new Form_Input(
	'pfbdenycnt',
	'Deny',
	'number',
	$pfbdenycnt,
	[min => 0, max => 1000]
))->setHelp('Deny')->setAttribute('title', 'Enter number of \'Deny\' log entries to view. Set to \'0\' to disable');

$group->add(new Form_Input(
	'pfbdnscnt',
	'DNSBL',
	'number',
	$pfbdnscnt,
	[min => 0, max => 1000]
))->setHelp('DNSBL')->setAttribute('title', 'Enter number of \'DNSBL\' log entries to view. Set to \'0\' to disable');

$group->add(new Form_Input(
	'pfbpermitcnt',
	'Permit',
	'number',
	$pfbpermitcnt,
	[min => 0, max => 1000]
))->setHelp('Permit')->setAttribute('title', 'Enter number of \'Permit\' log entries to view. Set to \'0\' to disable');

$group->add(new Form_Input(
	'pfbmatchcnt',
	'Match',
	'number',
	$pfbmatchcnt,
	[min => 0, max => 1000]
))->setHelp('Match')->setAttribute('title', 'Enter number of \'Match\' log entries to view. Set to \'0\' to disable');

$group->add(new Form_Checkbox(
	'alertrefresh',
	'Auto-Refresh',
	NULL,
	($alertrefresh == 'on' ? TRUE : FALSE),
	'on'
))->setHelp('Auto-Refresh')->setAttribute('title', 'Select to \'Auto-Refresh\' page every 60 seconds.');

$group->add(new Form_Checkbox(
	'hostlookup',
	'Auto-Resolve',
	NULL,
	($hostookup == 'on' ? TRUE : FALSE),
	'on'
))->setHelp('Auto-Resolve')->setAttribute('title', 'Select to \'Auto-Resolve\' Hosts.');

$btn_save = new Form_Button(
	'save',
	'Save Settings',
	NULL,
	'fa-save'
);
$btn_save->removeClass('btn-primary')->addClass('btn-primary btn-xs');
$group->add(new Form_StaticText(
	NULL,
	$btn_save
));
$section->add($group);


// Build 'Alert Filter' group section
$filterstatus = SEC_CLOSED;
if ($pfb['filterlogentries']) {
	$filterstatus = SEC_OPEN;
}
$section = new Form_Section('Alert Filter', 'alertfilter', COLLAPSIBLE|$filterstatus);
$form->add($section);

$group = new Form_Group(NULL);
$group->add(new Form_Input(
	'filterlogentries_date',
	'Date',
	'text',
	$filterfieldsarray[99]
))->setAttribute('title', 'Enter filter \'Date\'.');

$group->add(new Form_Input(
	'filterlogentries_srcip',
	'Source IP Address',
	'text',
	$filterfieldsarray[7]
))->setAttribute('title', 'Enter filter \'Source IP Address\'.');

$group->add(new Form_Input(
	'filterlogentries_srcport',
	'Source:Port',
	'text',
	$filterfieldsarray[9]
))->setAttribute('title', 'Enter filter \'Source:Port\'.');

$group->add(new Form_Input(
	'filterlogentries_int',
	'Interface',
	'text',
	$filterfieldsarray[2]
))->setAttribute('title', 'Enter filter \'Interface\'.');
$section->add($group);

$group = new Form_Group(NULL);
$group->add(new Form_Input(
	'filterlogentries_rule',
	'Rule Number Only',
	'text',
	$filterfieldsarray[0]
))->setAttribute('title', 'Enter filter \'Rule Number\' only.');

$group->add(new Form_Input(
	'filterlogentries_dstip',
	'Dest. IP/Domain Name',
	'text',
	$filterfieldsarray[8]
))->setAttribute('title', 'Enter filter \'Destination IP Address/Domain Name\'.');

$group->add(new Form_Input(
	'filterlogentries_dstport',
	'Destination:Port',
	'text',
	$filterfieldsarray[10]
))->setAttribute('title', 'Enter filter \'Destination:Port\'.');

$group->add(new Form_Input(
	'filterlogentries_proto',
	'Protocol',
	'text',
	$filterfieldsarray[6]
))->setAttribute('title', 'Enter filter \'Protocol\'.');
$section->add($group);

if ($pfb['dnsbl'] == 'on') {
	$section->addInput(new Form_Input(
		'filterlogentries_dnsbl',
		'',
		'text',
		$filterfieldsarray[90],
		['placeholder' => 'DNSBL URL']
	))->setAttribute('title', 'Enter filter \'DNSBL URL\'.');
}

$group = new Form_Group(NULL);
$btnsubmit = new Form_Button(
	'filterlogentries_submit',
	gettext('Apply Filter'),
	NULL,
	'fa-filter'
	);
$btnsubmit->removeClass('btn-primary')->addClass('btn-primary btn-xs');

$btnclear = new Form_Button(
	'filterlogentries_clear',
	gettext('Clear Filter'),
	NULL,
	'fa-filter fa-rotate-180'
	);
$btnclear->removeClass('btn-primary')->addClass('btn-primary btn-xs');

$group->add(new Form_StaticText(
	'',
	$btnsubmit . $btnclear
	. '&emsp;<div class="infoblock">'
	. '<h6>Regex Style Matching Only! <a href="http://regexr.com/" target="_blank">Regular Expression Help link</a>. '
	. 'Precede with exclamation (!) as first character to exclude match.)</h6>'
	. '<h6>Example: ( ^80$ - Match Port 80, ^80$|^8080$ - Match both port 80 & 8080 )</h6>'
	. '</div>'
));

$section->add($group);

$form->addGlobal(new Form_Input('domain', 'domain', 'hidden', ''));
$form->addGlobal(new Form_Input('table', 'table', 'hidden', ''));
$form->addGlobal(new Form_Input('descr', 'descr', 'hidden', ''));
$form->addGlobal(new Form_Input('cidr', 'cidr', 'hidden', ''));
$form->addGlobal(new Form_Input('ip', 'ip', 'hidden', ''));
$form->addGlobal(new Form_Input('addsuppress', 'addsuppress', 'hidden', ''));
$form->addGlobal(new Form_Input('addsuppressdom', 'addsuppressdom', 'hidden', ''));
print($form);

$skipcount = $counter = $resolvecounter = 0;
// Create three output windows 'Deny', 'DNSBL', 'Permit' and 'Match'-->
foreach (array (	'Deny'		=> "{$pfb['denydir']}/* {$pfb['nativedir']}/*",
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
?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h2 class="panel-title">&nbsp;<?=gettext($type)?>
			<small>-&nbsp;<?=gettext(Last)?>&nbsp;<?=$pfbentries?>&nbsp;<?=gettext("Alert Entries")?></small>
		</h2>
	</div>
	<div class="panel-body">
		<div class="table-responsive">
		<table class="table table-striped table-hover table-compact sortable-theme-bootstrap" data-sortable>

<?php
// Process dns array for DNSBL and generate output
if ($pfb['dnsbl'] == 'on' && $type == 'DNSBL') {
?>
			<thead>
				<tr>
					<th><?=gettext("Date")?></th>
					<th><?=gettext("IF")?></th>
					<th><?=gettext("Source")?></th>
					<th><!----- Buttons -----></th>
					<th><?=gettext("Domain/Referer|URI|Agent")?></th>
					<th><?=gettext("List")?></th>
				</tr>
			</thead>
			<tbody>
<?php
	$dns_array = $final = array();
	if (file_exists("{$pfb['dnslog']}")) {
		if (($handle = @fopen("{$pfb['dnslog']}", 'r')) !== FALSE) {
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
		@fclose($handle);

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
				$pfb_https = '&nbsp;<i class="fa fa-key icon-pointer" title="HTTPS alerts are not fully logged due to Browser security"></i>';
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

			$alert_dom = '<a class="fa fa-info icon-pointer icon-primary" title="Click for Threat Source Lookup." ' .
					'href="/pfblockerng/pfblockerng_threats.php?domain=' . $aline[2] . '"></a>';

			if (!in_array($pfbalertdnsbl[8], $dnssupp_ex)) {
				$supp_dom = '<i class="fa fa-plus icon-pointer icon-primary" id="DNSBLSUP' . $domain . '"' .
						'" title="' . $supp_dom_txt . '"></i>';
			}
			else {
				$supp_dom = '<i class="fa fa-plus-square-o icon-pointer" title="This domain is already in the DNSBL Suppress List"></i>&emsp;';
			}

			// Truncate long URLs
			$url_title = '';
			if (strlen($pfbalertdnsbl[90]) >= 72) {
				$url_title = "{$pfbalertdnsbl[90]}";
				$pfbalertdnsbl[90] = substr(str_replace(array('?', '-'), '', $pfbalertdnsbl[90]), 0, 69) . '...';
			}

			print ("<tr>
				<td>{$pfbalertdnsbl[99]}</td>
				<td><small>{$pfbalertdnsbl[1]}</small></td>
				<td>{$pfbalertdnsbl[7]}</td>
				<td>{$alert_dom} {$supp_dom}</td>
				<td  title=\"{$url_title}\">{$pfbalertdnsbl[8]} {$pfb_https}
					<br />&nbsp;&nbsp;<small>{$pfbalertdnsbl[90]}</small></td>
				<td title=\"{$pfb_matchtitle}\">{$pfb_query}
					<br /><small>{$pfb_alias}</small></td></tr>");
			$counter++;
		}
	}
}

if ($type != 'DNSBL') {
?>
			<thead>
				<tr>
					<th><?=gettext("Date")?></th>
					<th><?=gettext("IF")?></th>
					<th><?=gettext("Rule")?></th>
					<th><?=gettext("Proto")?></th>
					<th><!----- Buttons -----></th>
					<th><?=gettext("Source")?></th>
					<th><!----- Buttons -----></th>
					<th><?=gettext("Destination")?></th>
					<th><?=gettext("CC")?></th>
					<th><?=gettext("List")?></th>
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

				$alert_ip = '<a class="fa fa-info icon-pointer icon-primary" href="/pfblockerng/pfblockerng_threats.php?host=' .
						$host . '" title="Click for Threat Source Lookup."></a>';

				if ($pfb_query != 'Country' && $rtype == 'block' && $pfb['supp'] == 'on') {
					$supp_ip = '<i class="fa fa-plus icon-pointer icon-primary"' .
						'id="PFBIPSUP' . $host . '|' . $rule_list[$rulenum]['name'] . '" title="Add IP to Suppress List"></i>';
				}

				if ($rtype == 'block' && $hostlookup == 'on') {
					$hostname = getpfbhostname('src', $fields[7], $counter, $fields[8]);
				} else {
					$hostname = '';
				}

				$src_icons_1	= "{$alert_ip}&nbsp;{$supp_ip}";
				$src_icons_2	= "{$alert_ip}";
				$dst_icons_1	= '';
				$dst_icons_2	= '';

			} else {
				// Outbound
				$rule = "{$rule_list[$rulenum]['name']}<br /><small>({$rulenum})</small>";
				$host = $fields[8];

				$alert_ip = '<a class="fa fa-info icon-pointer icon-primary" href="/pfblockerng/pfblockerng_threats.php?host=' .
						$host . '" title="Click for Threat Source Lookup."></a>';

				if ($pfb_query != 'Country' && $rtype == 'block' && $pfb['supp'] == 'on') {
					$supp_ip = '<i class="fa fa-plus icon-pointer icon-primary"' .
						'id="PFBIPSUP' . $host . '|' . $rule_list[$rulenum]['name'] . '" title="Add IP to Suppress List"></i>';
				}

				if ($rtype == 'block' && $hostlookup == 'on') {
					$hostname = getpfbhostname('dst', $fields[8], $counter, $fields[7]);
				} else {
					$hostname = '';
				}

				$src_icons_1	= '';
				$src_icons_2	= '';
				$dst_icons_1	= "{$alert_ip}&nbsp;{$supp_ip}&nbsp;";
				$dst_icons_2	= "{$alert_ip}";
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
					$pfb_query = find_reported_header($host, $pfbfolder, FALSE);

					// Report specific ET IQRisk details
					if ($pfb['et_header'] && strpos($pfb_query[1], "{$et_header}") !== FALSE) {
						$ET_orig = $pfb_query;
						$pfb_query = find_reported_header($host, "{$pfb['etdir']}/*", FALSE);

						// On 'no match', ET IQRisk category is unknown.
						if ($pfb_query[1] == 'no match') {
							$pfb_query = $ET_orig;
						}
					}

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

			print ("<tr>
				<td>{$fields[99]}</td>
				<td><small>{$fields[2]}</small></td>
				<td>{$rule}</td>
				<td><small>{$fields[6]}</small></td>
				<td>{$src_icons}</td>
				<td>{$fields[97]}{$srcport}<br /><small>{$hostname['src']}</small></td>
				<td>{$dst_icons}</td>
				<td>{$fields[98]}{$dstport}<br /><small>{$hostname['dst']}</small></td>
				<td>{$country}</td>
				<td title=\"{$pfb_matchtitle}\">{$pfb_match[1]}<br /><small>{$pfb_match[2]}</small></td></tr>");
			$counter++;
			if ($rtype == 'block') {
				$resolvecounter = $counter;
			}
		}
	}
}

		// Print final table info
			if ($pfbentries != $counter) {
				$msg = ' - Insufficient Firewall Alerts found.';
			}
			if ($type == 'DNSBL') {
				$colspan = "colspan='7'";
			} else {
				$colspan = "colspan='10'";
			}

			print ("<td {$colspan} style='font-size:10px; background-color: #F0F0F0;' >Found {$counter} Alert Entries {$msg}</td>");
			$counter = 0; $msg = '';
?>
			</tbody>
		</table>
		</div>
	</div>
</div>
<?php
endforeach;	// End - Create four output windows ('Deny', 'DNSBL', 'Permit' and 'Match')
unset($fields_array);

include('foot.inc');
?>

<script type="text/javascript">
//<![CDATA[

// Auto-resolve of alerted hostnames
function findhostnames(counter) {
	getip = $('#gethostname_' + counter).attr('name');
	geturl = "/pfblockerng/pfblockerng_alerts_ar.php";
	$.get( geturl, { "getpfhostname": getip } )
	.done(function( data ) {
			$('#gethostname_' + counter).prop('title' , data );
			var str = data;
			if(str.length > 32) str = str.substring(0,29)+"...";
			$('#gethostname_' + counter).html( str );
		}
	)
}

var alertlines = "<?=$resolvecounter?>";
var autoresolve = "<?=$hostlookup?>";
if ( autoresolve == 'on' ) {
	for (alertcount = 0; alertcount < alertlines; alertcount++) {
		setTimeout(findhostnames(alertcount), 30);
	}
}

events.push(function() {

	$('[id^=DNSBLSUP]').click(function(event) {
		if (confirm(event.target.title)) {
			var domain = this.id.replace(/DNSBLSUP|\\/gi, "");
			$('#domain').val(domain);

			if (domain) {
				$('#addsuppressdom').val('true');
				$('form').submit();
			}
		}
	});

	$('[id^=PFBIPSUP]').click(function(event) {
		var iprule = this.id.replace("PFBIPSUP", "");
		var arr = iprule.split('|');	// Split iprule into (IP/Rulename)
		$('#ip').val(arr[0]);
		$('#table').val(arr[1]);

		var description = prompt("Please enter Suppression Description");
		$('#descr').val(description);

		if (description.value != "") {
			var cidr = prompt("Please enter CIDR [ 32 or 24 CIDR only supported ]","32");
			$('#cidr').val(cidr);

			if (arr[0] && arr[1] && description && cidr) {
				$('#addsuppress').val('true');
				$('form').submit();
			}
		}
	});
});

//]]>
</script>
