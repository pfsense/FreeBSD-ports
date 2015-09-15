<?php
/*
	pfBlockerNG_Alerts.php

	pfBlockerNG
	Copyright (C) 2015 BBcan177@gmail.com
	All rights reserved.

	Portions of this code are based on original work done for
	pfSense from the following contributors:

	Parts based on works from Snort_alerts.php
	Copyright (C) 2015 Bill Meeks
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

// Auto-Resolve Hostnames
if (isset($_REQUEST['getpfhostname'])) {
	$getpfhostname = trim(htmlspecialchars($_REQUEST['getpfhostname']));
	if (strlen($getpfhostname) >= 8) {
		$hostname = htmlspecialchars(gethostbyaddr($getpfhostname), ENT_QUOTES);
	} else {
		$hostname = $getpfhostname;
	}
	if ($hostname == $getpfhostname) {
		$hostname = 'unknown';
	}
	echo $hostname;
	die;
}

require_once("util.inc");
require_once("guiconfig.inc");
require_once("/usr/local/pkg/pfblockerng/pfblockerng.inc");
global $rule_list, $pfb_localsub;
pfb_global();

$pfs_version = substr(trim(file_get_contents("/etc/version")),0,3);

if ($pfs_version == "2.2") {
	$prefix = "/usr/pbi/pfblockerng-" . php_uname("m");
} else {
	$prefix = "/usr/local";
}

// Application Paths
$pathgeoip	= "{$prefix}/bin/geoiplookup";
$pathgeoip6	= "{$prefix}/bin/geoiplookup6";

// Define File Locations
$filter_logfile = "{$g['varlog_path']}/filter.log";
$pathgeoipdat	= "{$prefix}/share/GeoIP/GeoIP.dat";
$pathgeoipdat6	= "{$prefix}/share/GeoIP/GeoIPv6.dat";

// Emerging Threats IQRisk Header Name Reference
$pfb['et_header'] = TRUE;
$et_header = $config['installedpackages']['pfblockerngreputation']['config'][0]['et_header'];
if (empty($et_header)) {
	$pfb['et_header'] = FALSE;
}

// Collect pfBlockerNGSuppress Alias and Create pfbsuppression.txt
if ($pfb['supp'] == "on") {
	pfb_create_suppression_file();
}

// Collect Number of Suppressed Hosts
if (file_exists("{$pfb['supptxt']}")) {
	$pfbsupp_cnt = exec ("/usr/bin/grep -c ^ {$pfb['supptxt']}");
} else {
	$pfbsupp_cnt = 0;
}

$pfb['global'] = &$config['installedpackages']['pfblockerngglobal'];

if (!isset($pfb['global']['pfbdenycnt'])) {
	$pfb['global']['pfbdenycnt']	= '25';
}
if (!isset($pfb['global']['pfbpermitcnt'])) {
	$pfb['global']['pfbpermitcnt']	= '5';
}
if (!isset($pfb['global']['pfbmatchcnt'])) {
	$pfb['global']['pfbmatchcnt']	= '5';
}
if (!isset($pfb['global']['pfbdnscnt'])) {
	$pfb['global']['pfbdnscnt']	= '5';
}
if (empty($pfb['global']['alertrefresh'])) {
	$pfb['global']['alertrefresh']	= 'off';
}
if (empty($pfb['global']['hostlookup'])) {
	$pfb['global']['hostlookup']	= 'off';
}

if (isset($_POST['save'])) {
	if (!is_array($pfb['global'])) {
		$pfb['global'] = array();
	}
	$pfb['global']['alertrefresh']		= $_POST['alertrefresh'] ? 'on' : 'off';
	$pfb['global']['hostlookup']		= $_POST['hostlookup'] ? 'on' : 'off';
	if (is_numeric($_POST['pfbdenycnt'])) {
		$pfb['global']['pfbdenycnt']	= $_POST['pfbdenycnt'];
	}
	if (is_numeric($_POST['pfbpermitcnt'])) {
		$pfb['global']['pfbpermitcnt']	= $_POST['pfbpermitcnt'];
	}
	if (is_numeric($_POST['pfbmatchcnt'])) {
		$pfb['global']['pfbmatchcnt']	= $_POST['pfbmatchcnt'];
	}
	if (is_numeric($_POST['pfbdnscnt'])) {
		$pfb['global']['pfbdnscnt']	= $_POST['pfbdnscnt'];
	}
	write_config("pfBlockerNG pkg: updated ALERTS tab settings.");
	header("Location: " . $_SERVER['PHP_SELF']);
	exit;
}

if (is_array($pfb['global'])) {
	$alertrefresh	= $pfb['global']['alertrefresh'];
	$hostlookup	= $pfb['global']['hostlookup'];
	$pfbdenycnt	= $pfb['global']['pfbdenycnt'];
	$pfbpermitcnt	= $pfb['global']['pfbpermitcnt'];
	$pfbmatchcnt	= $pfb['global']['pfbmatchcnt'];
	$pfbdnscnt	= $pfb['global']['pfbdnscnt'];
}


// Define Alerts Log filter Rollup window variable and collect Widget Alert Pivot details
if (isset($_REQUEST['rule'])) {
	$filterfieldsarray[0] = $_REQUEST['rule'];
	$pfbdenycnt = $pfbpermitcnt = $pfbmatchcnt = $_REQUEST['entries'];
	$pfb['filterlogentries'] = TRUE;
}
else {
	$pfb['filterlogentries'] = FALSE;
}


function pfb_match_filter_field($flent, $fields) {
	foreach ($fields as $key => $field) {
		if ($field == null) {
			continue;
		}
		if ((strpos($field, '!') === 0)) {
			$field = substr($field, 1);
			$field_regex = str_replace('/', '\/', str_replace('\/', '/', $field));
			if (@preg_match("/{$field_regex}/i", $flent[$key])) {
				return false;
			}
		}
		else {
			$field_regex = str_replace('/', '\/', str_replace('\/', '/', $field));
			if (!@preg_match("/{$field_regex}/i", $flent[$key])) {
				return false;
			}
		}
	}
	return true;
}


if ($_POST['filterlogentries_submit']) {
	// Set flag for filtering alert entries
	$pfb['filterlogentries'] = TRUE;

	// Note the order of these fields must match the order decoded from the alerts log
	$filterfieldsarray = array();
	$filterfieldsarray[0] = $_POST['filterlogentries_rule'] ? $_POST['filterlogentries_rule'] : null;
	$filterfieldsarray[2] = $_POST['filterlogentries_int'] ? $_POST['filterlogentries_int'] : null;
	$filterfieldsarray[6] = strtolower($_POST['filterlogentries_proto']) ? $_POST['filterlogentries_proto'] : null;

	// Remove any zero-length spaces added to the IP address that could creep in from a copy-paste operation
	$filterfieldsarray[7] = $_POST['filterlogentries_srcip'] ? str_replace("\xE2\x80\x8B", "", $_POST['filterlogentries_srcip']) : null;
	$filterfieldsarray[8] = $_POST['filterlogentries_dstip'] ? str_replace("\xE2\x80\x8B", "", $_POST['filterlogentries_dstip']) : null;

	$filterfieldsarray[9] = $_POST['filterlogentries_srcport'] ? $_POST['filterlogentries_srcport'] : null;
	$filterfieldsarray[10] = $_POST['filterlogentries_dstport'] ? $_POST['filterlogentries_dstport'] : null;
	$filterfieldsarray[99] = $_POST['filterlogentries_date'] ? $_POST['filterlogentries_date'] : null;
}


if ($_POST['filterlogentries_clear']) {
	$pfb['filterlogentries'] = TRUE;
	$filterfieldsarray = array();
}


// Collect pfBlockerNG Rule Names and Number
$rule_list = array();
exec("/sbin/pfctl -vv -sr | grep 'pfB_'", $results);
if (!empty($results)) {
	foreach ($results as $result) {

		// Find Rule Descriptions
		$descr = "";
		if (preg_match("/USER_RULE: (\w+)/",$result,$desc)) {
			$descr = $desc[1];
		}

		preg_match ("/@(\d+)\(/",$result, $rule);

		$id = $rule[1];
		// Create array of Rule Description and pfctl Rule Number
		$rule_list['id'][] = $id;
		$rule_list[$id]['name'] = $descr;
	}
}

// Add IP to the Suppression Alias
if (isset($_POST['addsuppress'])) {
	$ip = "";
	if (isset($_POST['ip'])) {
		$ip = $_POST['ip'];
		$table = $_POST['table'];
		$descr = $_POST['descr'];
		$cidr = $_POST['cidr'];

		// If Description or CIDR field is empty, exit.
		if (empty($descr) || empty($cidr)) {
			header("Location: " . $_SERVER['PHP_SELF']);
			exit;
		}

		if (is_ipaddr($ip)) {

			$savemsg1 = "Host IP address {$ip}";
			if (is_ipaddrv4($ip)) {
				$iptrim1 = preg_replace("/(\d{1,3})\.(\d{1,3}).(\d{1,3}).(\d{1,3})/", '$1.$2.$3.0/24', $ip);
				$iptrim2 = preg_replace("/(\d{1,3})\.(\d{1,3}).(\d{1,3}).(\d{1,3})/", '$1.$2.$3.', $ip);
				$iptrim3 = preg_replace("/(\d{1,3})\.(\d{1,3}).(\d{1,3}).(\d{1,3})/", '$4', $ip);

				if ($cidr == "32") {
					$pfb_pfctl = exec ("/sbin/pfctl -t {$table} -T show | grep {$iptrim1} 2>&1");

					if ($pfb_pfctl == "") {
						$savemsg2 = " : Removed /32 entry";
						exec ("/sbin/pfctl -t {$table} -T delete {$ip}");
					} else {
						$savemsg2 = " : Removed /24 entry, added 254 addr";
						exec ("/sbin/pfctl -t {$table} -T delete {$iptrim1}");
						for ($add_ip=0; $add_ip <= 255; $add_ip++){
							if ($add_ip != $iptrim3) {
								exec ("/sbin/pfctl -t {$table} -T add {$iptrim2}{$add_ip}");
							}
						}
					}
				} else {
					$cidr = 24;
					$savemsg2 = " : Removed /24 entry";
					exec ("/sbin/pfctl -t {$table} -T delete {$iptrim1} 2>&1", $pfb_pfctl);
					if (!preg_grep("/1\/1 addresses deleted/", $pfb_pfctl)) {
						$savemsg2 = " : Removed all entries";
						// Remove 0-255 IP Address from Alias Table
						for ($del_ip=0; $del_ip <= 255; $del_ip++){
							exec ("/sbin/pfctl -t {$table} -T delete {$iptrim2}{$del_ip}");
						}
					}
				}
			}

			// Collect pfBlockerNGSuppress Alias Contents
			$pfb_sup_list = array();
			$pfb_sup_array = array();
			$pfb['found'] = FALSE;
			$pfb['update'] = FALSE;
			if (is_array($config['aliases']['alias'])) {
				foreach ($config['aliases']['alias'] as $alias) {
					if ($alias['name'] == "pfBlockerNGSuppress") {
						$data = $alias['address'];
						$data2 = $alias['detail'];
						$arr1 = explode(" ",$data);
						$arr2 = explode("||",$data2);

						if (!empty($data)) {
							$row = 0;
							foreach ($arr1 as $host) {
								$pfb_sup_list[] = $host;
								$pfb_sup_array[$row]['host'] = $host;
								$row++;
							}
							$row = 0;
							foreach ($arr2 as $detail) {
								$pfb_sup_array[$row]['detail'] = $detail;
								$row++;
							}
						}
						$pfb['found'] = TRUE;
					}
				}
			}

			// Call Function to Create Suppression Alias if not found.
			if (!$pfb['found']) {
				pfb_create_suppression_alias();
			}

			// Save New Suppress IP to pfBlockerNGSuppress Alias
			if (in_array($ip . '/' . $cidr, $pfb_sup_list)) {
				$savemsg = gettext("Host IP address {$ip} already exists in the pfBlockerNG Suppress Table.");
			} else {
				if (!$pfb['found'] && empty($pfb_sup_list)) {
					$next_id = 0;
				} else {
					$next_id = count($pfb_sup_list);
				}
				$pfb_sup_array[$next_id]['host'] = $ip . '/' . $cidr;
				$pfb_sup_array[$next_id]['detail'] = $descr;

				$address = "";
				$detail = "";
				foreach ($pfb_sup_array as $pfb_sup) {
					$address .= $pfb_sup['host'] . " ";
					$detail .= $pfb_sup['detail'] . "||";
				}

				// Find pfBlockerNGSuppress Array ID Number
				if (is_array($config['aliases']['alias'])) {
					$pfb_id = 0;
					foreach ($config['aliases']['alias'] as $alias) {
						if ($alias['name'] == "pfBlockerNGSuppress") {
							break;
						}
						$pfb_id++;
					}
				}

				$config['aliases']['alias'][$pfb_id]['address']	= rtrim($address, " ");
				$config['aliases']['alias'][$pfb_id]['detail']	= rtrim($detail, "||");
				$savemsg = gettext($savemsg1) . gettext($savemsg2) . gettext(" and added Host to the pfBlockerNG Suppress Table.");
				$pfb['update'] = TRUE;
			}

			if ($pfb['found'] || $pfb['update']) {
				// Save all Changes to pfsense config file
				write_config("pfBlockerNG: Added {$ip} to IP Suppress List");
			}
		}
	}
}


// Host Resolve Function lookup
function getpfbhostname($type = 'src', $hostip, $countme = 0) {
	$hostnames['src'] = '';
	$hostnames['dst'] = '';
	$hostnames[$type] = '<div id="gethostname_' . $countme . '" name="' . $hostip . '"></div>';
	return $hostnames;
}


// For subnet addresses - Determine if Alert Host 'Dest' is within a Local IP Range.
function ip_in_pfb_localsub($subnet) {
	global $pfb_localsub;

	if (!empty($pfb_localsub)) {
		foreach ($pfb_localsub as $line) {
			if (ip_in_subnet($subnet, $line)) {
				return true;
			}
		}
	}
	return false;
}


// Parse Filter log for pfBlockerNG Alerts
function conv_log_filter_lite($logfile, $nentries, $tail, $pfbdenycnt, $pfbpermitcnt, $pfbmatchcnt) {
	global $pfb, $rule_list, $filterfieldsarray;
	$fields_array	= array();
	$logarr		= "";
	$denycnt	= 0;
	$permitcnt	= 0;
	$matchcnt	= 0;

	if (file_exists($logfile)) {
		exec("/usr/local/sbin/clog " . escapeshellarg($logfile) . " | grep -v \"CLOG\" | grep -v \"\033\" | /usr/bin/grep 'filterlog:' | /usr/bin/tail -r -n {$tail}", $logarr);
	}
	else return;

	if (!empty($logarr) && !empty($rule_list['id'])) {
		foreach ($logarr as $logent) {
			$pfbalert  = array();
			$log_split = "";

			if (!preg_match("/(.*)\s(.*)\sfilterlog:\s(.*)$/", $logent, $log_split)) {
				continue;
			}

			list($all, $pfbalert[99], $host, $rule) = $log_split;
			$rule_data	= explode(",", $rule);
			$pfbalert[0]	= $rule_data[0];		// Rulenum

			// Skip Alert if Rule is not a pfBNG Alert
			if (!in_array($pfbalert[0], $rule_list['id'])) {
				continue;
			}

			$pfbalert[1] = $rule_data[4];			// Realint
			$pfbalert[3] = $rule_data[6];			// Act
			$pfbalert[4] = $rule_data[8];			// Version

			if ($pfbalert[4] == "4") {
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

			if ($pfbalert[5] == "6" || $pfbalert[5] == "17") {
				// skip
			} else {
				$pfbalert[9]  = "";
				$pfbalert[10] = "";
				$pfbalert[11] = "";
			}

			// Skip Repeated Alerts 
			if (($pfbalert[1] . $pfbalert[3] . $pfbalert[7] . $pfbalert[8] . $pfbalert[10]) == $previous_alert) {
				continue;
			}

			$pfbalert[2] = convert_real_interface_to_friendly_descr($rule_data[4]);					// Friendly Interface Name
			$pfbalert[6] = str_replace("TCP", "TCP-", strtoupper($pfbalert[6]), $pfbalert[6]) . $pfbalert[11];	// Protocol Flags

			// If Alerts Filtering is selected, process Filters as required.
			if ($pfb['filterlogentries'] && !pfb_match_filter_field($pfbalert, $filterfieldsarray)) {
				continue;
			}

			if ($pfbalert[3] == "block") {
				if ($denycnt < $pfbdenycnt) {
					$fields_array['Deny'][] = $pfbalert;
					$denycnt++;
				}
			}
			elseif ($pfbalert[3] == "pass") {
				if ($permitcnt < $pfbpermitcnt) {
					$fields_array['Permit'][] = $pfbalert;
					$permitcnt++;
				}
			}
			elseif ($pfbalert[3] == "unkn(%u)" || $pfbalert[3] == "unkn(11)") {
				if ($matchcnt < $pfbmatchcnt) {
					$fields_array['Match'][] = $pfbalert;
					$matchcnt++;
				}
			}

			// Exit function if Sufficinet Matches found.
			if ($denycnt >= $pfbdenycnt && $permitcnt >= $pfbpermitcnt && $matchcnt >= $pfbmatchcnt) {
				unset ($pfbalert, $logarr);
				return $fields_array;
			}

			// Collect Details for Repeated Alert Comparison
			$previous_alert = $pfbalert[1] . $pfbalert[3] . $pfbalert[7] . $pfbalert[8] . $pfbalert[10];
		}
		unset ($pfbalert, $logarr);
		return $fields_array;
	}
}

$pgtitle = gettext("pfBlockerNG: Alerts");
include_once("head.inc");
?>
<body link="#000000" vlink="#0000CC" alink="#000000">
<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
<input type="hidden" name="ip" id="ip" value=""/>
<input type="hidden" name="table" id="table" value=""/>
<input type="hidden" name="descr" id="descr" value=""/>
<input type="hidden" name="cidr" id="cidr" value=""/>
<?php

include_once("fbegin.inc");

/* refresh every 60 secs */
if ($alertrefresh == 'on') {
	echo "<meta http-equiv=\"refresh\" content=\"60;url={$_SERVER['PHP_SELF']}\" />\n";
}
if ($savemsg) {
	print_info_box($savemsg);
}

$skipcount = 0; $counter = 0;
?>
	<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr>
		<td>
			<?php
				$tab_array = array();
				$tab_array[] = array(gettext("General"), false, "/pkg_edit.php?xml=pfblockerng.xml&amp;id=0");
				$tab_array[] = array(gettext("Update"), false, "/pfblockerng/pfblockerng_update.php");
				$tab_array[] = array(gettext("Alerts"), true, "/pfblockerng/pfblockerng_alerts.php");
				$tab_array[] = array(gettext("Reputation"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_reputation.xml&id=0");
				$tab_array[] = array(gettext("IPv4"), false, "/pkg.php?xml=/pfblockerng/pfblockerng_v4lists.xml");
				$tab_array[] = array(gettext("IPv6"), false, "/pkg.php?xml=/pfblockerng/pfblockerng_v6lists.xml");
				$tab_array[] = array(gettext("Top 20"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_top20.xml&id=0");
				$tab_array[] = array(gettext("Africa"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_Africa.xml&id=0");
				$tab_array[] = array(gettext("Asia"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_Asia.xml&id=0");
				$tab_array[] = array(gettext("Europe"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_Europe.xml&id=0");
				$tab_array[] = array(gettext("N.A."), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_NorthAmerica.xml&id=0");
				$tab_array[] = array(gettext("Oceania"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_Oceania.xml&id=0");
				$tab_array[] = array(gettext("S.A."), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_SouthAmerica.xml&id=0");
				$tab_array[] = array(gettext("P.S."), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_ProxyandSatellite.xml&id=0");
				$tab_array[] = array(gettext("Logs"), false, "/pfblockerng/pfblockerng_log.php");
				$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=/pfblockerng/pfblockerng_sync.xml&id=0");
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
				<?php printf(gettext('%sDeny%s.&nbsp;&nbsp;') , '<strong>', '</strong>'); ?>
				<input name="pfbpermitcnt" type="text" class="formfld unknown" id="pdbpermitcnt" size="1"
					title="Enter the number of 'Permit' Alerts to Show" value="<?=htmlspecialchars($pfbpermitcnt);?>"/>
				<?php printf(gettext('%sPermit%s.&nbsp;&nbsp;'), '<strong>', '</strong>'); ?>
				<input name="pfbmatchcnt" type="text" class="formfld unknown" id="pdbmatchcnt" size="1"
					title="Enter the number of 'Match' Alerts to Show" value="<?=htmlspecialchars($pfbmatchcnt); ?>"/>
				<?php printf(gettext('%sMatch%s.'), '<strong>', '</strong>'); ?>

				<?php echo gettext('&nbsp;&nbsp;&nbsp;&nbsp;Click to Auto-Refresh');?>&nbsp;&nbsp;<input name="alertrefresh" type="checkbox" value="on"
					title="Click to enable Auto-Refresh of this Tab once per minute"
				<?php if ($config['installedpackages']['pfblockerngglobal']['alertrefresh']=="on") echo "checked"; ?>/>&nbsp;

				<?php echo gettext('&nbsp;Click to Auto-Resolve');?>&nbsp;&nbsp;<input name="hostlookup" type="checkbox" value="on"
					title="Click to enable Auto-Resolve of Hostnames. Country Blocks/Permit/Match Lists will not auto-resolve"
				<?php if ($config['installedpackages']['pfblockerngglobal']['hostlookup']=="on") echo "checked"; ?>/>&nbsp;&nbsp;&nbsp;
				<input name="save" type="submit" class="formbtns" value="Save" title="<?=gettext('Save settings');?>"/><br />

				<?php printf(gettext('Enter number of log entries to view.')); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
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
					&nbsp;&nbsp;<?=gettext("Click to display advanced filtering options dialog");?>
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
							<div align="center"><?=gettext("Destination IP Address");?></div>
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
					<tr>
						<td colspan="3" style="vertical-align:bottom">
							<br /><?printf(gettext('Regex Style Matching Only! %1$s Regular Expression Help link%2$s.'), '
								<a target="_blank" href="http://www.php.net/manual/en/book.pcre.php">', '</a>');?>&nbsp;&nbsp;
								<?=gettext("Precede with exclamation (!) as first character to exclude match.) ");?>
							<br /><?printf(gettext("Example: ( ^80$ - Match Port 80, ^80$|^8080$ - Match both port 80 & 8080 ) "));?><br />
						</td>
					</tr>
					<tr>
						<td colspan="3" style="vertical-align:bottom">
							<div align="left"><input id="filterlogentries_submit" name="filterlogentries_submit" type="submit"
								class="formbtns" value="<?=gettext("Apply Filter");?>" title="<?=gettext("Apply filter"); ?>" />
								&nbsp;&nbsp;&nbsp;<input id="filterlogentries_clear" name="filterlogentries_clear" type="submit"
								class="formbtns" value="<?=gettext("Clear");?>" title="<?=gettext("Remove filter");?>" />
								&nbsp;&nbsp;&nbsp;<input id="filterlogentries_hide" name="filterlogentries_hide" type="button"
								class="formbtns" value="<?=gettext("Hide");?>" onclick="enable_hideFilter();"
								title="<?=gettext("Hide filter options");?>" /></div>
						</td>
					</tr>
					</table>
				</td>
			</tr>

<!--Create Three Output Windows 'Deny', 'Permit' and 'Match'-->
<?php foreach (array ( "Deny" => $pfb['denydir'] . " " . $pfb['nativedir'], "Permit" => $pfb['permitdir'], "Match" => $pfb['matchdir']) as $type => $pfbfolder ):
	switch($type) {
		case "Deny":
			$rtype = "block";
			$pfbentries = "{$pfbdenycnt}";
			break;
		case "Permit":
			$rtype = "pass";
			$pfbentries = "{$pfbpermitcnt}";
			break;
		case "Match":
			$rtype = "unkn(%u)";
			$pfbentries = "{$pfbmatchcnt}";
			break;
	}

	// Skip Table output if $pfbentries is zero.
	if ($pfbentries == 0 && $skipcount != 2) {
		$skipcount++;
		continue;
	}
?>
			<table id="maintable" class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="6">
			<tr>
				<!--Print Table Info-->
				<td colspan="2" class="listtopic"><?php printf(gettext("&nbsp;{$type}&nbsp;&nbsp; - &nbsp; Last %s Alert Entries."),"{$pfbentries}"); ?>
					<?php if ($type == "Deny"): ?>
						&nbsp;&nbsp;&nbsp;&nbsp;<?php echo gettext("Firewall Rule changes can unsync these Alerts."); ?>
					<?php endif; ?>
				</td>
			</tr>

<td width="100%" colspan="2">
<table id="pfbAlertsTable" style="table-layout: fixed;" width="100%" class="sortable" border="0" cellpadding="0" cellspacing="0">
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

$pfb['runonce'] = TRUE;
if (isset($pfb['load'])) {
	$pfb['runonce'] = FALSE;
}

// Execute the following once per refresh
if ($pfb['runonce']) {
	$pfb['load'] = TRUE;
	$resolvecounter = 0;
	$fields_array = array();

	$pfblines = exec("/usr/local/sbin/clog {$filter_logfile} | /usr/bin/grep -c ^");
	$fields_array = conv_log_filter_lite($filter_logfile, $pfblines, $pfblines, $pfbdenycnt, $pfbpermitcnt, $pfbmatchcnt);
	$continents   = array('pfB_Africa','pfB_Antartica','pfB_Asia','pfB_Europe','pfB_NAmerica','pfB_Oceania','pfB_SAmerica','pfB_Top');

	$supp_ip_txt  = "Clicking this Suppression Icon, will immediately remove the Block.\n\nSuppressing a /32 CIDR is better than Suppressing the full /24";
	$supp_ip_txt .= " CIDR.\nThe Host will be added to the pfBlockerNG Suppress Alias Table.\n\nOnly 32 or 24 CIDR IPs can be Suppressed with the '+' Icon.";
	$supp_ip_txt .= "\nTo manually add Host(s), edit the 'pfBlockerNGSuppress' Alias in the Alias Tab.\nManual entries will not remove existing Blocked Hosts";

	// Array of all Local IPs for Alert Analysis
	$pfb_local = array();
	$pfb_localsub = array();

	// Collect Gateway IP Addresses for Inbound/Outbound List matching
	$int_gateway = get_interfaces_with_gateway();
	if (is_array($int_gateway)) {
		foreach ($int_gateway as $gateway) {
			$convert = get_interface_ip($gateway);
			$pfb_local[] = $convert;
		}
	}

	// Collect Virtual IP Aliases for Inbound/Outbound List Matching
	if (is_array($config['virtualip']['vip'])) {
		foreach ($config['virtualip']['vip'] as $list) {
			if ($list['subnet'] != "" && $list['subnet_bits'] != "") {
				if ($list['subnet_bits'] >= 24) {
					$pfb_local = array_merge(subnetv4_expand("{$list['subnet']}/{$list['subnet_bits']}"), $pfb_local);
				} else {
					$pfb_localsub[] = "{$list['subnet']}/{$list['subnet_bits']}";
				}
			}
		}
	}

	// Collect NAT IP Addresses for Inbound/Outbound List Matching
	if (is_array($config['nat']['rule'])) {
		foreach ($config['nat']['rule'] as $natent) {
			$pfb_local[] = $natent['target'];
		}
	}

	// Collect 1:1 NAT IP Addresses for Inbound/Outbound List Matching
	if (is_array($config['nat']['onetoone'])) {
		foreach ($config['nat']['onetoone'] as $onetoone) {
			$pfb_local[] = $onetoone['source']['address'];
		}
	}

	// Convert any 'Firewall Aliases' to IP Address Format
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

	// Collect all Interface Addresses for Inbound/Outbound List Matching
	if (is_array($config['interfaces'])) {
		foreach ($config['interfaces'] as $int) {
			if ($int['ipaddr'] != "dhcp") {
				if ($int['ipaddr'] != "" && $int['subnet'] != "") {
					if ($int['subnet'] >= 24) {
						$pfb_local = array_merge(subnetv4_expand("{$int['ipaddr']}/{$int['subnet']}"), $pfb_local);
					} else {
						$pfb_localsub[] = "{$int['ipaddr']}/{$int['subnet']}";
					}
				}
			}
		}
	}

	// Remove any Duplicate IPs
	$pfb_local = array_unique($pfb_local);
	$pfb_localsub = array_unique($pfb_localsub);
}

// Process Fields_array and generate Output
if (!empty($fields_array[$type]) && !empty($rule_list)) {
	$key = 0;
	foreach ($fields_array[$type] as $fields) {
		$rulenum	= "";
		$alert_ip	= "";
		$supp_ip	= "";
		$pfb_query	= "";

		/* Fields_array Reference	[0]	= Rulenum			[6]	= Protocol
						[1]	= Real Interface		[7]	= SRC IP
						[2]	= Friendly Interface Name	[8]	= DST IP
						[3]	= Action			[9]	= SRC Port
						[4]	= Version			[10]	= DST Port
						[5]	= Protocol ID			[11]	= Flags
						[99]	= Timestamp	*/

		$rulenum = $fields[0];
		if ($counter < $pfbentries) {
			// Cleanup Port Output
			if ($fields[6] == "ICMP" || $fields[6] == "ICMPV6") {
				$srcport = "";
				$dstport = "";
			} else {
				$srcport = ":" . $fields[9];
				$dstport = ":" . $fields[10];
			}

			// Don't add Suppress Icon to Country Block Lines
			if (in_array(substr($rule_list[$rulenum]['name'], 0, -3), $continents)) {
				$pfb_query = "Country";
			}

			// Add DNS Resolve and Suppression Icons to External IPs only. GeoIP Code to External IPs only.
			if (in_array($fields[8], $pfb_local) || ip_in_pfb_localsub($fields[8])) {
				// Destination is Gateway/NAT/VIP
				$rule = $rule_list[$rulenum]['name'] . "<br />(" . $rulenum .")";
				$host = $fields[7];

				$alert_ip  = "<a href='/pfblockerng/pfblockerng_diag_dns.php?host={$host}' title=\" " . gettext("Resolve host via Rev. DNS lookup");
				$alert_ip .= "\"> <img src=\"/themes/{$g['theme']}/images/icons/icon_log.gif\" width='11' height='11' border='0' ";
				$alert_ip .= "alt=\"Icon Reverse Resolve with DNS\" style=\"cursor: pointer;\" /></a>";

				if ($pfb_query != "Country" && $rtype == "block" && $pfb['supp'] == "on") {
					$supp_ip  = "<input type='image' name='addsuppress[]' onclick=\"hostruleid('{$host}','{$rule_list[$rulenum]['name']}');\" ";
					$supp_ip .= "src=\"../themes/{$g['theme']}/images/icons/icon_pass_add.gif\" title=\"";
					$supp_ip .= gettext($supp_ip_txt) . "\" border='0' width='11' height='11' />";
				}

				if ($pfb_query != "Country" && $rtype == "block" && $hostlookup == "on") {
					$hostname = getpfbhostname('src', $fields[7], $counter);
				} else {
					$hostname = "";
				}
		
				$src_icons = $alert_ip . "&nbsp;" . $supp_ip . "&nbsp;";
				$dst_icons = "";
			} else {
				// Outbound
				$rule = $rule_list[$rulenum]['name'] . "<br />(" . $rulenum .")";
				$host = $fields[8];

				$alert_ip  = "<a href='/pfblockerng/pfblockerng_diag_dns.php?host={$host}' title=\"" . gettext("Resolve host via Rev. DNS lookup");
				$alert_ip .= "\"> <img src=\"/themes/{$g['theme']}/images/icons/icon_log.gif\" width='11' height='11' border='0' ";
				$alert_ip .= "alt=\"Icon Reverse Resolve with DNS\" style=\"cursor: pointer;\" /></a>";

				if ($pfb_query != "Country" && $rtype == "block" && $pfb['supp'] == "on") {
					$supp_ip  = "<input type='image' name='addsuppress[]' onclick=\"hostruleid('{$host}','{$rule_list[$rulenum]['name']}');\" ";
					$supp_ip .= "src=\"../themes/{$g['theme']}/images/icons/icon_pass_add.gif\" title=\"";
					$supp_ip .= gettext($supp_ip_txt) . "\" border='0' width='11' height='11' />";
				}

				if ($pfb_query != "Country" && $rtype == "block" && $hostlookup == "on") {
					$hostname = getpfbhostname('dst', $fields[8], $counter);
				} else {
					$hostname = "";
				}

				$src_icons = "";
				$dst_icons = $alert_ip . "&nbsp;" . $supp_ip . "&nbsp;";
			}

			// Determine Country Code of Host
			if (is_ipaddrv4($host)) {
				$country = substr(exec("$pathgeoip -f $pathgeoipdat $host"),23,2);
			} else {
				$country = substr(exec("$pathgeoip6 -f $pathgeoipdat6 $host"),26,2);
			}

			// IP Query Grep Exclusion
			$pfb_ex1 = "grep -v 'pfB\_\|\_v6\.txt'";
			$pfb_ex2 = "grep -v 'pfB\_\|/32\|/24\|\_v6\.txt' | grep -m1 '/'";

			// Find List which contains Blocked IP Host
			if (is_ipaddrv4($host) && $pfb_query != "Country") {
				// Search for exact IP Match
				$host1 = preg_replace("/(\d{1,3})\.(\d{1,3}).(\d{1,3}).(\d{1,3})/", '\'$1\.$2\.$3\.$4\'', $host);
				$pfb_query = exec("/usr/bin/grep -rHm1 {$host1} {$pfbfolder} | sed -e 's/^.*[a-zA-Z]\///' -e 's/:.*//' -e 's/\..*/ /' | {$pfb_ex1}");
				// Search for IP in /24 CIDR
				if (empty($pfb_query)) {
					$host1 = preg_replace("/(\d{1,3})\.(\d{1,3}).(\d{1,3}).(\d{1,3})/", '\'$1\.$2\.$3\.0/24\'', $host);
					$pfb_query = exec("/usr/bin/grep -rHm1 {$host1} {$pfbfolder} | sed -e 's/^.*[a-zA-Z]\///' -e 's/\.txt:/ /' | {$pfb_ex1}");
				}
				// Search for First Two IP Octets in CIDR Matches Only. Skip any pfB (Country Lists) or /32,/24 Addresses.
				if (empty($pfb_query)) {
					$host1 = preg_replace("/(\d{1,3})\.(\d{1,3}).(\d{1,3}).(\d{1,3})/", '\'^$1\.$2\.\'', $host);
					$pfb_query = exec("/usr/bin/grep -rH {$host1} {$pfbfolder} | sed -e 's/^.*[a-zA-Z]\///' -e 's/\.txt:/ /' | {$pfb_ex2}");
				}
				// Search for First Two IP Octets in CIDR Matches Only (Subtract 1 from second Octet on each loop).
				// Skip (Country Lists) or /32,/24 Addresses.
				if (empty($pfb_query)) {
					$host1 = preg_replace("/(\d{1,3})\.(\d{1,3}).(\d{1,3}).(\d{1,3})/", '\'^$1\.', $host);
					$host2 = preg_replace("/(\d{1,3})\.(\d{1,3}).(\d{1,3}).(\d{1,3})/", '$2', $host);
					for ($cnt = 1; $cnt <= 5; $cnt++) {
						$host3 = $host2 - $cnt . '\'';
						$pfb_query = exec("/usr/bin/grep -rH {$host1}{$host3} {$pfbfolder} | sed -e 's/^.*[a-zA-Z]\///' -e 's/\.txt:/ /' | {$pfb_ex2}");
						// Break out of loop if found.
						if (!empty($pfb_query)) {
							$cnt = 6;
						}
					}
				}
				// Search for First Three Octets
				if (empty($pfb_query)) {
					$host1 = preg_replace("/(\d{1,3})\.(\d{1,3}).(\d{1,3}).(\d{1,3})/", '\'^$1\.$2\.$3\.\'', $host);
					$pfb_query = exec("/usr/bin/grep -rH {$host1} {$pfbfolder} | sed -e 's/^.*[a-zA-Z]\///' -e 's/\.txt:/ /' | {$pfb_ex2}");
				}
				// Search for First Two Octets
				if (empty($pfb_query)) {
					$host1 = preg_replace("/(\d{1,3})\.(\d{1,3}).(\d{1,3}).(\d{1,3})/", '\'^$1\.$2\.\'', $host);
					$pfb_query = exec("/usr/bin/grep -rH {$host1} {$pfbfolder} | sed -e 's/^.*[a-zA-Z]\///' -e 's/\.txt:/ /' | {$pfb_ex2}");
				}
				// Report Specific ET IQRisk Details
				if ($pfb['et_header'] && preg_match("/{$et_header}/", $pfb_query)) {
					$host1 = preg_replace("/(\d{1,3})\.(\d{1,3}).(\d{1,3}).(\d{1,3})/", '\'$1\.$2\.$3\.$4\'', $host);
					$pfb_query = exec("/usr/bin/grep -Hm1 {$host1} {$pfb['etdir']}/* | sed -e 's/^.*[a-zA-Z]\///' -e 's/:.*//' -e 's/\..*/ /' -e 's/ET_/ET IPrep /' ");
					if (empty($pfb_query)) {
						$host1 = preg_replace("/(\d{1,3})\.(\d{1,3}).(\d{1,3}).(\d{1,3})/", '\'$1.$2.$3.0/24\'', $host);
						$pfb_query = exec("/usr/bin/grep -rHm1 {$host1} {$pfbfolder} | sed -e 's/^.*[a-zA-Z]\///' -e 's/\.txt:/ /' | {$pfb_ex1}");
					}
				}
			}
			elseif (is_ipaddrv6($host) && $pfb_query != "Country") {
				$pfb_query = exec("/usr/bin/grep -Hm1 {$host} {$pfbfolder} | sed -e 's/^.*[a-zA-Z]\///' -e 's/\.txt:/ /' | grep -v 'pfB\_'");
			}

			// Default to "No Match" if not found.
			if (empty($pfb_query)) {
				$pfb_query = "No Match";
			}

			// Split List Column into Two lines.
			unset ($pfb_match);
			if ($pfb_query == "No Match") {
				$pfb_match[1] = "{$pfb_query}";
				$pfb_match[2] = "";
			} else {
				preg_match ("/(.*)\s(.*)/", $pfb_query, $pfb_match);
				if ($pfb_match[1] == "") {
					$pfb_match[1] = "{$pfb_query}";
					$pfb_match[2] = "";
				}
			}

			// Add []'s to IPv6 Addresses and add a zero-width space as soft-break opportunity after each colon if we have an IPv6 address (from Snort)
			if ($fields[4] == "6") {
				$fields[97] = "[" . str_replace(":", ":&#8203;", $fields[7]) . "]";
				$fields[98] = "[" . str_replace(":", ":&#8203;", $fields[8]) . "]";
			}
			else {
				$fields[97] = $fields[7];
				$fields[98] = $fields[8];
			}

			// Truncate Long List Names
			$pfb_matchtitle = "Country Block Rules cannot be suppressed.\n\nTo allow a particular Country IP, either remove the particular Country or add the Host\nto a Permit Alias in the Firewall Tab.\n\nIf the IP is not listed beside the List, this means that the Block is a /32 entry.\nOnly /32 or /24 CIDR Hosts can be suppressed.\n\nIf (Duplication) Checking is not enabled. You may see /24 and /32 CIDR Blocks for a given blocked Host";

			if (strlen($pfb_match[1]) >= 17) {
				$pfb_matchtitle = $pfb_match[1];
				$pfb_match[1]	= substr($pfb_match[1], 0, 16) . '...';
			}

			// Print Alternating Line Shading 
			$alertRowEvenClass	= "style='background-color: #D8D8D8;'";
			$alertRowOddClass	= "style='background-color: #E8E8E8;'";

			$alertRowClass = $counter % 2 ? $alertRowEvenClass : $alertRowOddClass;
			echo "<tr {$alertRowClass}>
				<td class='listMRr' align='center'>{$fields[99]}</td>
				<td class='listMRr' align='center'>{$fields[2]}</td>
				<td class='listMRr' align='center' title='The pfBlockerNG Rule that Blocked this Host.'>{$rule}</td>
				<td class='listMRr' align='center'>{$fields[6]}</td>
				<td class='listMRr' align='center' sorttable_customkey='{$fields[97]}'>{$src_icons}{$fields[97]}{$srcport}<br /><small>{$hostname['src']}</small></td>
				<td class='listMRr' align='center' sorttable_customkey='{$fields[98]}'>{$dst_icons}{$fields[98]}{$dstport}<br /><small>{$hostname['dst']}</small></td>
				<td class='listMRr' align='center'>{$country}</td>
				<td class='listbg' align='center' title='{$pfb_matchtitle}' style=\"font-size: 10px word-wrap:break-word;\">{$pfb_match[1]}<br />{$pfb_match[2]}</td></tr>";
			$counter++;
			if ($rtype == "block") {
				$resolvecounter = $counter;
			}
		}
	}
}
?>
	</tbody>
	<tr>
		<!--Print Final Table Info-->
		<?php
			if ($pfbentries != $counter) {
				$msg = " - Insufficient Firewall Alerts found.";
			}
			echo (" <td colspan='8' style='font-size:10px; background-color: #F0F0F0;' >Found {$counter} Alert Entries {$msg}</td>");
			$counter = 0; $msg = '';
		?>
	</tr>
	</table>
	</table>
<?php endforeach; ?>	<!--End - Create Three Output Windows 'Deny', 'Permit' and 'Match'-->
<?php unset ($fields_array); ?>
</td></tr>
</table>
</td>

<script type="text/javascript">
//<![CDATA[

// This function stuffs the passed HOST, Table values into hidden Form Fields for postback.
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

// Auto-Resolve of Alerted Hostnames
function findhostnames(counter) {
	getip = jQuery('#gethostname_' + counter).attr('name');
	geturl = "<?php echo $_SERVER['PHP_SELF']; ?>";
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
if ( autoresolve == "on" ) {
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
<?php include("fend.inc"); ?>
</form>
</body>
</html>