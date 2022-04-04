<?php
/*
 * nmap_scan.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2022-2022 Rubicon Communications, LLC (Netgate)
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

function nmap_get_running_process($f){
	$processcheck = trim(shell_exec("/bin/ps axw -O pid= | /usr/bin/grep '/usr/local/bin/[n]map.*{$f}'"));
	return $processcheck;
}

// Return a ports array from a nmap -p arg value format
// we strip ranges (-) and types (T:, U:, P:, S:)
// port names with '-' like 'netbios-ssn' are supported
function get_ports_array($port_post) {
	$ports_array = array();

	// get ports from the comma separated form input
	if (strpos($port_post, ',') === false) {
		$ports_array = array($port_post);
	} else {
		$ports_array = explode(',', $port_post);
	}

	$port_max_ranges = array();
	foreach ($ports_array as &$p) {
		if (preg_match('/^(T:|U:|P:|S:)/', $p) === 1) {
			$p = substr($p, 2);//if empty, we keep it as it is an illegal nmap syntax. On function return, empty ports will evaluate as invalid
		}

		// keep only inner and outer ports from a ports range
		// if the inner/outer port range limit is omitted, set corresponding port value to '*'
		if ($p === '-') {
			$p = '*';
		} elseif (strpos($p, '-') !== false && preg_match('/^[0-9\-]+$/', $p) === 1) {
			// port ranges must be in the format of 'numeric-numeric'
			$port_ranges = explode('-', $p, 2);
			$p = strlen($port_ranges[0]) ? $port_ranges[0] : '*';
			$port_max_ranges[] = strlen($port_ranges[1]) ? $port_ranges[1] : '*';
		}
	}
	unset($p);
	$ports_array = array_merge($ports_array, $port_max_ranges);

	return $ports_array;
}

if ($_POST['downloadbtn'] == gettext("Download Results")) {
	$nocsrf = true;
}

$pgtitle = array("Package", "Diagnostics: Nmap");
require_once("guiconfig.inc");
/* require_once("pfsense-utils.inc"); */
require_once("ipsec.inc");

$fp = "/root/";
$fn = "nmap.result";
$fe = "nmap.error"; // stderr
$max_display_size = 50*1024*1024; // 50MB limit on GUI results display. See https://redmine.pfsense.org/issues/9239

$interfaces = get_configured_interface_with_descr();
if (ipsec_enabled()) {
	$interfaces['enc0'] = "IPsec";
}
$interfaces['lo0'] = "Localhost";

foreach (array('server' => gettext('OpenVPN Server'), 'client' => gettext('OpenVPN Client')) as $mode => $mode_descr) {
	if (is_array($config['openvpn']["openvpn-{$mode}"])) {
		foreach ($config['openvpn']["openvpn-{$mode}"] as $id => $setting) {
			if (!isset($setting['disable'])) {
				$interfaces['ovpn' . substr($mode, 0, 1) . $setting['vpnid']] = $mode_descr . ": ".htmlspecialchars($setting['description']);
			}
		}
	}
}

$interfaces = array_merge($interfaces, interface_ipsec_vti_list_all());
$interfaces = array_merge(array('' => 'Auto detect (default)'), $interfaces);

$scan_types = array(
	'syn' => gettext('TCP SYN'),
	'connect' => gettext('TCP Connect()'),
	'ack' => gettext('TCP ACK'),
	'window' => gettext('TCP Window'),
	'udp' => gettext('UDP'),
	'icmp' => gettext('No Port Scan'),
	'arp' => gettext('ARP Ping'),
	'ipscan' => gettext('IP Protocol'),
	'sctpinit' => gettext('SCTP INIT'),
	'sctpecho' => gettext('SCTP COOKIE ECHO'),
	'listscan' => gettext('List Only')
);

$input_errors = array();
$do_nmapscan = false;
if ($_POST) {
	$hostnames = $_POST['hostnames'];
	$interface = $_POST['interface'];
	$scantype = $_POST['scantype'];
	$udpscan = isset($_POST['udpscan']);
	$noping = isset($_POST['noping']);
	$servicever = isset($_POST['servicever']);
	$osdetect = isset($_POST['osdetect']);
	$excludehosts = $_POST['excludehosts'];
	$ports = $_POST['ports'];
	$topports = $_POST['topports'];
	$nodns = isset($_POST['nodns']);
	$traceroute = isset($_POST['traceroute']);

	if ($_POST['startbtn'] != "") {
		$action = gettext("Start");

		// check for input errors
		if (strlen($hostnames) === 0) {
			$input_errors[] = gettext("You must enter an IP address or host name to scan.");
		} else {
			$hostnames_array = explode(" ", $hostnames);
			foreach ($hostnames_array as $host_entry) {
				if (!(is_ipaddr($host_entry) || is_subnet($host_entry) || is_hostname($host_entry))) {
					$input_errors[] = gettext("Host: '") . escapeshellarg($host_entry) . gettext("' is not a valid IP address or host name.");
				} elseif (is_ipaddrv6($host_entry) || is_subnetv6($host_entry)) {
					$enable_ipv6 = true;
				}
			}
		}

		if(!empty($interface)) {
			if (!array_key_exists($interface, $interfaces)) {
				$input_errors[] = gettext("Invalid interface.");
			}
		}

		if ($udpscan) {
			if ($scantype !== 'syn' && $scantype !== 'connect' && $scantype !== 'ack' && $scantype !== 'window') {
				$input_errors[] = gettext("UDP Scan (-sU): This option cannot be used with '") . $scan_types[$scantype] . gettext("' scan type. UDP scan can only be combined with a TCP scan method");
			}
		}

		// Check advanced options
		if(strlen($excludehosts) > 0) {
			$excludehosts_array = explode(",", $excludehosts);
			foreach ($excludehosts_array as $host_entry) {
				if (strlen($host_entry) === 0) {
					$input_errors[] = gettext("Exclude Hosts: you cannot specify empty hosts in the list. Remove any extra or trailing commas !");
				} elseif (!(is_ipaddr($host_entry) || is_subnet($host_entry) || is_hostname($host_entry))) {
					$input_errors[] = gettext("Exclude Hosts: '") . escapeshellarg($host_entry) . gettext("' is not a valid IP address or host name.");
				}
			}
		}

		if(strlen($ports) > 0) {
			if (strpos($ports, '*') !== false) {
				$input_errors[] = gettext("Ports cannot contain an asterix '*'.");
			} else {
				$ports_arr = get_ports_array($ports);
				foreach ($ports_arr as $p) {
					if ($p === '*') {
						continue;
					} elseif (strlen($p) === 0) {
						$input_errors[] = gettext("Port: emtpy ports in list. Remove any extra or trailing commas. Also ensure that you type a port number/name after port specifiers (T:|U:|P:|S:)");
					} elseif (is_numericint($p) && intval($p) === 0) {
						continue;//nmap allows scanning of port 0 if explicitely specified
					} elseif (!is_port($p)) {
						$input_errors[] = gettext("Port: '") . escapeshellarg($p) . gettext("' is not a valid port.");
					} elseif ($scantype === 'ipscan' && is_numericint($p) && intval($p) > 255) {
						$input_errors[] = gettext("Port number must be an integer between 0 and 255 when using IP Protocol Scan method");
					}
				}
			}
		}

		if(strlen($topports) > 0) {
			if (!is_numericint($topports) || intval($topports) < 1 || intval($topports) > 65535) {
				$input_errors[] = gettext("--top-ports value must be an integer in the range 1-65535.");
			}
		}

		// process scan options only if no input errors
		if (!count($input_errors)) {
			$do_nmapscan = true;

			$nmap_options = "";

			// prevent use of any deprecated option
			//$nmap_options .= " -d";

			if ($enable_ipv6) {
				$nmap_options .= " -6";
			}

			// scan type
			switch($scantype) {
				case 'syn':
					$nmap_options .= " -sS";
					break;
				case 'connect':
					$nmap_options .= " -sT";
					break;
				case 'ack':
					$nmap_options .= " -sA";
					break;
				case 'window':
					$nmap_options .= " -sW";
					break;
				case 'udp':
					$nmap_options .= " -sU";
					break;
				case 'icmp':
					$nmap_options .= " -sn"; // previously -sP
					break;
				case 'arp':
					$nmap_options .= " -sn -PR";
					break;
				case 'ipscan':
					$nmap_options .= " -sO";
					break;
				case 'sctpinit':
					$nmap_options .= " -sY";
					break;
				case 'sctpecho':
					$nmap_options .= " -sZ";
					break;
				case 'listscan':
					$nmap_options .= " -sL";
					break;
			}

			// allow TCP + UDP combined scans
			if ($udpscan) {
				$nmap_options .= " -sU";
			}

			// scan options
			if ($noping) {
				$nmap_options .= " -Pn"; // previously -P0
			}
			if ($servicever) {
				$nmap_options .= " -sV";
			}
			if ($osdetect) {
				$nmap_options .= " -O";
			}

			if(strlen($ports) > 0) {
				$nmap_options .= " -p " . escapeshellarg($ports);
			}

			if(strlen($topports) > 0) {
				$nmap_options .= " --top-ports " . escapeshellarg($topports);
			}

			if ($nodns) {
				$nmap_options .= " -n";
			}

			if ($traceroute) {
				$nmap_options .= " --traceroute";
			}

			// append summary output to results file (doesn't contain stderr)
			$nmap_options .= " -oN {$fp}{$fn} --append-output";

			if (!empty($interface)) {
				$nmap_options .= " -e " . get_real_interface($interface);
			}

			if(strlen($excludehosts) > 0) {
				$nmap_options .= " --exclude " . escapeshellarg($excludehosts);
			}

			foreach ($hostnames_array as $host_entry) {
				$nmap_options .= " " . escapeshellarg($host_entry);
			}
		}
	} elseif ($_POST['stopbtn'] != "") {
		$action = gettext("Stop");

		/* check if nmap scan is already running */
		$processes_running = nmap_get_running_process($fn);
		$processisrunning = ($processes_running != "");

		//explode processes into an array, (delimiter is new line)
		$processes_running_array = explode("\n", $processes_running);

		if ($processisrunning != true) {
			$input_errors[] = gettext("Process nmap already completed. Check results below.");
		} else {
			//kill each of the nmap processes
			foreach ($processes_running_array as $process) {
				$process_id_pos = strpos($process, ' ');
				$process_id = substr($process, 0, $process_id_pos);
				exec("kill $process_id");
			}				
		}
	} elseif ($_POST['viewbtn'] != "" || $_POST['refreshbtn'] != "") {
		$action = gettext("View");
	} elseif ($_POST['downloadbtn'] != "") {
		$action = gettext("Download");

		//download file
		send_user_download('file', $fp.$fn);
	} elseif ($_POST['clearbtn'] != "") {
		$action = gettext("Delete");

		//delete previous nmap results file if it exists
		unlink_if_exists($fp.$fn);
		unlink_if_exists($fp.$fe);
	}
}

include("head.inc");

/*
$tab_array = array();
$tab_array[] = array(gettext("Nmap Scan"), true, "/nmap_scan.php");
display_top_tabs($tab_array);
*/

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form(false); // No button yet. We add those later depending on the required action

$section = new Form_Section('General Options');

$section->addInput(new Form_Input(
	'hostnames',
	'*IP or Hostname',
	'text',
	$hostnames
))->setHelp('Enter the IP addresses or hostnames that you would like to scan.%1$s' .
			'%2$sCan pass space separated hostnames, IP addresses, ranges, networks, etc.%3$s' .
			'Ex: scanme.nmap.org; microsoft.com/24 192.168.0.1 10.10.1.1; 10.0.0-255.1-254%4$s',

			'<span class="infoblock" style="font-size:90%"><br />',
			'<p style="margin:0px;padding:0px">',
			'<br />',
			'</p></span>');

$section->addInput(new Form_Select(
	'interface',
	'Interface',
	$interface,
	$interfaces
))->setHelp('Select the source interface here.');

$section->addInput(new Form_Select(
	'scantype',
	'*Scan Type',
	$scantype,
	$scan_types
))->setHelp('Select the scan type.%1$s' .
			'%2$s%3$s%4$sTCP SYN (-sS):%5$s The default and most popular scan option.%6$s' .

			'%4$sTCP connect (-sT):%5$s The default TCP scan type when SYN scan is not an option. This is the case when a user does not have raw packet privileges. ' .
			'This is the same high-level system call that web browsers, P2P clients, and most other network-enabled applications use to establish a connection.%6$s' .

			'%4$sTCP ACK (-sA):%5$s This scan is different than the others discussed so far in that it never determines open (or even open|filtered) ports. ' .
			'It is used to map out firewall rulesets, determining whether they are stateful or not and which ports are filtered.%6$s' .

			'%4$sTCP Window (-sW):%5$s Window scan is exactly the same as ACK scan except that it exploits an implementation detail of certain systems to differentiate open ports from closed ones, ' .
			'rather than always printing unfiltered when a RST is returned.%6$s' .

			'%4$sUDP scan (-sU):%5$s UDP scan works by sending an UDP packet to every targeted port.%6$s' .

			'%4$sNo port scan (-sn):%5$s This option tells Nmap not to do a port scan after host discovery, and only print out the available hosts that responded to the host discovery probes. This is often known as a "ping scan". ' .
			'However, to skip host discovery and port scan, while still allowing NSE to run, you can use the two options -Pn -sn together.%6$s' .

			'%4$sARP Ping (-sn -PR):%5$s ARP is only for directly connected ethernet LAN. On local networks, ARP scan takes just over a tenth of the time taken by its IP equivalent. ' .
			'It also avoids filling source host ARP table space with invalid entries.%6$s' .

			'%4$sIP protocol scan (-sO):%5$s IP protocol scan allows you to determine which IP protocols (TCP, ICMP, IGMP, etc.) are supported by target machines. ' .
			'This is not technically a port scan, since it cycles through IP protocol numbers rather than TCP or UDP port numbers. Yet it still uses the -p option to select scanned protocol numbers, ' .
			'reports its results within the normal port table format, and even uses the same underlying scan engine as the true port scanning methods.%6$s' .

			'%4$sSCTP INIT scan (-sY):%5$s Scan for services implementing SCTP. SCTP INIT scan is the SCTP equivalent of a TCP SYN scan.%6$s' .

			'%4$sSCTP COOKIE ECHO (-sZ):%5$s A more advanced SCTP scan. It takes advantage of the fact that SCTP implementations should silently drop packets containing COOKIE ECHO chunks on open ports, ' .
			'but send an ABORT if the port is closed.%6$s' .

			'%4$sList scan (-sL):%5$s List scan simply lists each target host on the network(s) specified, without sending any packets to the target hosts. ' .
			'By default, Nmap still performs reverse-DNS resolution on the hosts to learn their names. Nmap also reports the total number of IP addresses at the end. ' .
			'List scan is a good sanity check to ensure that you have proper IP addresses for your targets. A preliminary list scan helps confirm exactly what targets are being scanned.%6$s%7$s',

			'<span class="infoblock" style="font-size:90%"><br />',
			'<p style="margin:0px;padding:0px">',
			'<ul>',
			'<li><b>',
			'</b>',
			'</li>',
			'</ul></p></span>');

$section->addInput(new Form_Checkbox(
	'udpscan',
	'UDP Scan',
	'Combines an UDP scan (-sU) with a TCP scan method',
	$udpscan
))->setHelp('Only possible if a TCP scan method was selected.%1$s' .
			'%2$sUDP scan can be combined with a TCP scan type such as SYN scan (-sS) to check both protocols during the same run.%3$s' .
			'This option is valid only in combination with a TCP scan method (SYN, Connect(), ACK, Window).%4$s',

			'<span class="infoblock" style="font-size:90%"><br />',
			'<p style="margin:0px;padding:0px">',
			'<br />',
			'</p></span>');

$section->addInput(new Form_Checkbox(
	'noping',
	'-Pn',
	'Treat all hosts as online (No ping).',
	$noping
))->setHelp('Allow scanning of networks that do not answer echo requests.%1$s' .
			'%2$sThis option skips the Nmap discovery stage altogether. So if a class B target address space (/16) is specified on the command line, all 65,536 IP addresses are scanned. ' .
			'Proper host discovery is skipped as with the list scan, but instead of stopping and printing the target list, Nmap continues to perform requested functions as if each target IP is active.%3$s' .
			'microsoft.com is an example of such a network, and thus you should always use -P0 or -PT80 when port scanning microsoft.com.%3$s' .
			'Note the "ping" in this context may involve more than the traditional ICMP echo request packet. Nmap supports many such probes, including arbitrary combinations of TCP, UDP, and ICMP probes.%3$s' .
			'By default, Nmap sends an ICMP echo request and a TCP ACK packet to port 80.%4$s',

			'<span class="infoblock" style="font-size:90%"><br />',
			'<p style="margin:0px;padding:0px">',
			'<br />',
			'</p></span>');

$section->addInput(new Form_Checkbox(
	'servicever',
	'-sV',
	'Attempt to identify service versions',
	$servicever
))->setHelp('Try to detect services running on discoverd ports.%1$s' .
			'%2$sAfter TCP and/or UDP ports are discovered using one of the other scan types, version detection communicates with those ports to try and determine more about what is actually running.%3$s' .
			'A file called nmap-service-probes is used to determine the best probes for detecting various services and the match strings to expect.%3$s' .
			'Nmap tries to determine the service protocol (e.g. ftp, ssh, telnet, http), the application name (e.g. ISC Bind, Apache httpd, Solaris telnetd), ' .
			'the version number, and sometimes miscellaneous details like whether an X server is open to connections or the SSH protocol version).%4$s',

			'<span class="infoblock" style="font-size:90%"><br />',
			'<p style="margin:0px;padding:0px">',
			'<br />',
			'</p></span>');

$section->addInput(new Form_Checkbox(
	'osdetect',
	'-O',
	'Enable Operating System detection',
	$osdetect
))->setHelp('Try to identify remote host via TCP/IP fingerprinting.%1$s' .
			'%2$sIn other words, it uses techniques to detect subtleties in the underlying operating system network stack of the computers being scanned.%3$s' .
			'It uses this information to create a "fingerprint" which it compares with its database of known OS fingerprints ' .
			'(the nmap-os-fingerprints file) to determine the operating system of the target host.%4$s',

			'<span class="infoblock" style="font-size:90%"><br />',
			'<p style="margin:0px;padding:0px">',
			'<br />',
			'</p></span>');

$form->add($section);

$section = new Form_Section('Advanced Options');

$section->addInput(new Form_Input(
	'excludehosts',
	'Exclude Hosts',
	'text',
	$excludehosts
))->setHelp('Enter the IP addresses or hostnames that you would like to exclude from scan.%1$s' .
			'%2$sCan pass comma separated hostnames, IP addresses, ranges, networks, etc.%3$s' .
			'Ex: scanme.nmap.org,microsoft.com/24,192.168.0.1,10.10.1.1,10.0.0-255.1-254%4$s',

			'<span class="infoblock" style="font-size:90%"><br />',
			'<p style="margin:0px;padding:0px">',
			'<br />',
			'</p></span>');

$section->addInput(new Form_Input(
	'ports',
	'Port',
	'text',
	$ports
))->setHelp('Only scan specified ports.%1$s' .
			'%2$sEx: 22; 1-65535; ssh; U:53,111,137,T:21-25,80,139,8080,P:9.%3$s' .
			'When scanning a combination of protocols (e.g. TCP and UDP), you can specify a particular protocol by preceding the port numbers by T: for TCP, U: for UDP, or P: for IP Protocol.%3$s' .
			'Individual port numbers are OK, as are ranges separated by a hyphen (e.g. 1-1023). The beginning and/or end values of a range may be omitted, causing Nmap to use 1 and 65535, respectively. ' .
			'So you can specify -p- to scan ports from 1 through 65535. Scanning port zero is allowed if you specify it explicitly.%3$s' .
			'For IP protocol scanning (-sO), this option specifies the protocol numbers you wish to scan for (0–255).%3$s' .
			'Note: SCTP (S:) specifier is not supported in GUI.%4$s',

			'<span class="infoblock" style="font-size:90%"><br />',
			'<p style="margin:0px;padding:0px">',
			'<br />',
			'</p></span>');

$section->addInput(new Form_Input(
	'topports',
	'--top-ports',
	'text',
	$topports
))->setHelp('Only scan specified most common ports (1-65535).%1$s' .
			'%2$sNormally Nmap scans the most common 1,000 ports for each scanned protocol.%3$s' .
			'With this option, nmap will only scan the specified most common ports number.%3$s' .
			'Value of 100 would be equivalent to the option "-F (Fast (limited port) scan)" which scans the first 100 most common ports.%4$s',

			'<span class="infoblock" style="font-size:90%"><br />',
			'<p style="margin:0px;padding:0px">',
			'<br />',
			'</p></span>');

$section->addInput(new Form_Checkbox(
	'nodns',
	'-n',
	'No DNS Resolution',
	$nodns
));

$section->addInput(new Form_Checkbox(
	'traceroute',
	'--traceroute',
	'Trace hop path to each host',
	$traceroute
));

$form->add($section);

/* check if nmap scan is already running */
$processes_running = nmap_get_running_process($fn);
$processisrunning = ($processes_running != "");

if ($processisrunning or $do_nmapscan) {
	$form->addGlobal(new Form_Button(
		'stopbtn',
		'Stop',
		null,
		'fa-stop-circle'
	))->addClass('btn-warning');

	$form->addGlobal(new Form_Button(
		'refreshbtn',
		'Refresh Results',
		null,
		'fa-retweet'
	))->addClass('btn-primary');
} else {
	$form->addGlobal(new Form_Button(
		'startbtn',
		'Start',
		null,
		'fa-play-circle'
	))->addClass('btn-success');

	if (file_exists($fp.$fn) or file_exists($fp.$fe)) {
		$form->addGlobal(new Form_Button(
			'viewbtn',
			'View Results',
			null,
			'fa-file-text-o'
		))->addClass('btn-primary');

		$form->addGlobal(new Form_Button(
			'downloadbtn',
			'Download Results',
			null,
			'fa-download'
		))->addClass('btn-primary');

		$form->addGlobal(new Form_Button(
			'clearbtn',
			'Clear Results',
			null,
			'fa-trash'
		))->addClass('btn-danger');
	}
}

if (file_exists($fp.$fn)) {
	$section->addInput(new Form_StaticText(
		'Last scan results',
		date("F jS, Y g:i:s a.", filemtime($fp.$fn))
	));
}

if (file_exists($fp.$fe) && filesize($fp.$fe) > 0) {
	$section->addInput(new Form_StaticText(
		'Last scan error',
		date("F jS, Y g:i:s a.", filemtime($fp.$fe))
	));
}

print($form);

if ($do_nmapscan) {
	$cmd = "/usr/local/bin/nmap {$nmap_options} >/dev/null 2>{$fp}{$fe} &";
	exec($cmd);
	print_info_box(gettext('Nmap scan is running' . '<br />' . 'Press info button to show command'), 'info');
	?>
	<div class="infoblock">
	<? print_info_box(gettext('Command line') . ': ' . htmlspecialchars($cmd), 'info', false); ?>
	</div>
	<?php

} elseif ($action == gettext("View") || $action == gettext("Stop")) {
		if (file_exists($fp.$fe) && filesize($fp.$fe) > 0) {
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Scan Errors')?></h2></div>
	<div class="panel-body">
		<div class="form-group">
<?php

			print('<textarea class="form-control" rows="10" style="font-size: 13px; font-family: consolas,monaco,roboto mono,liberation mono,courier;">');
			if (filesize($fp.$fe) > $max_display_size) {
				print(gettext("Nmap scan error file is too large to display in the GUI.") .
					"\n" .
					gettext("Download the file, or view it in the console or ssh shell.") .
					"\n" .
					gettext("Error file: {$fp}{$fe}"));
			} else {
				print(file_get_contents($fp.$fe));
			}
			print('</textarea>');

?>
		</div>
	</div>
</div>
<?php
		}

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext('Scan Results')?></h2></div>
	<div class="panel-body">
		<div class="form-group">
<?php

		print('<textarea class="form-control" rows="20" style="font-size: 13px; font-family: consolas,monaco,roboto mono,liberation mono,courier;">');
		if (file_exists($fp.$fn) && (filesize($fp.$fn) > $max_display_size)) {
			print(gettext("Nmap scan results file is too large to display in the GUI.") .
				"\n" .
				gettext("Download the file, or view it in the console or ssh shell.") .
				"\n" .
				gettext("Results file: {$fp}{$fn}"));
		} elseif (!file_exists($fp.$fn) || (filesize($fp.$fn) === 0)) {
			print(gettext("No nmap scan results to display."));
		} else {
			print(file_get_contents($fp.$fn));
		}
		print('</textarea>');

?>
		</div>
	</div>
</div>
<?php
}

include("foot.inc");
