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
require_once("guiconfig.inc");
/* require_once("pfsense-utils.inc"); */
require_once("ipsec.inc");

if ($_POST['downloadbtn'] == gettext("Download Results")) {
	$nocsrf = true;
}

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
$interfaces = array_merge(array('' => 'Any'), $interfaces);

$scan_types = array(
	'syn' => gettext('SYN'),
	'connect' => gettext('TCP connect()'),
	'icmp' => gettext('Ping'),
	'udp' => gettext('UDP'),
	'arp' => gettext('ARP (directly connected networks only!)')
);

function nmap_get_running_process(){
	$processcheck = (trim(shell_exec("/bin/ps axw -O pid= | /usr/bin/grep '/usr/local/bin/nmap' | /usr/bin/grep '{$fn}' | /usr/bin/egrep -v '(pflog|grep)'")));
	return $processcheck;
}

// only accept alphanumeric, spaces (040) and '.,-_' chars, and do not allow '-o? ' for output files
function valid_input($str) {
	$valid_str = (preg_match('/^[a-z0-9\040\.,\-_]+$/i', $str) === 1) && (preg_match('/-o.\040/', $str) === 0);
	//$valid_str = (preg_match('/^[a-z0-9\040\.,\-_]+$/i', $str) === 1) && (strpos($str, '-o') === false);
	return $valid_str;
}

$input_errors = array();
$do_nmapscan = false;
if ($_POST) {
	$hostname = $_POST['hostname'];
	$interface = $_POST['interface'];
	$scantype = $_POST['scantype'];
	$noping = isset($_POST['noping']);
	$servicever = isset($_POST['servicever']);
	$osdetect = isset($_POST['osdetect']);
	$custom_scantype = $_POST['custom_scantype'];
	$custom_options = $_POST['custom_options'];
	$custom_targetspecs = $_POST['custom_targetspecs'];

	if ($_POST['startbtn'] != "") {
		$action = gettext("Start");

		// check for input errors
		if (empty($hostname)) {
			$input_errors[] = gettext("You must enter an IP address to scan.");
		} elseif (!(is_ipaddr($hostname) || is_subnet($hostname) || is_hostname($hostname))) {
			$input_errors[] = gettext("You must enter a valid IP address to scan.");
		}

		if(!empty($interface)) {
			if (!array_key_exists($interface, $interfaces)) {
				$input_errors[] = gettext("Invalid interface.");
			}
		}

		// process scan options
		if (!count($input_errors)) {

			// prevent use of any deprecated option
			$nmap_options = " -d";

			if (is_ipaddrv6($hostname) || is_subnetv6($hostname)) {
				$nmap_options .= " -6";
			}

			// scan type
			if (empty($custom_scantype)) {
				switch($scantype) {
					case 'syn':
						$nmap_options .= " -sS";
						break;
					case 'connect':
						$nmap_options .= " -sT";
						break;
					case 'icmp':
						$nmap_options .= " -sn"; // previously -sP
						break;
					case 'udp':
						$nmap_options .= " -sU";
						break;
					case 'arp':
						$nmap_options .= " -sn -PR";
						break;
				}
			} elseif (valid_input($custom_scantype)) {
					$nmap_options .= " " . $custom_scantype;
			} else {
					$input_errors[] = gettext("Invalid characters in Custom Scan Type. Only alphanumeric, spaces and '.,-_' chars are allowed. Custom output options -o are not allowed");
			}

			// scan options
			if ($noping) {
				$nmap_options .= " -P0";
			}
			if ($servicever) {
				$nmap_options .= " -sV";
			}
			if ($osdetect) {
				$nmap_options .= " -O";
			}

			if (!empty($custom_options)) {
				if (valid_input($custom_options)) {
					$nmap_options .= " " . $custom_options;
				} else {
					$input_errors[] = gettext("Invalid characters in custom options. Only alphanumeric, spaces and '.,-_' chars are allowed. Custom output options -o are not allowed");
				}
			}

			// append summary output to results file (doesn't contain stderr)
			// prevent using source stylesheets for xls output (not really needed)
			$nmap_options .= " -oN {$fp}{$fn} --append-output --no-stylesheet";

			if (!empty($interface)) {
				$nmap_options .= " -e " . get_real_interface($interface);
			}

			if (!empty($custom_targetspecs)) {
				if (valid_input($custom_targetspecs)) {
					$nmap_options .= " " . $custom_targetspecs;
				} else {
					$input_errors[] = gettext("Invalid characters in custom target options. Only alphanumeric, spaces and '.,-_' chars are allowed. Custom output options -o are not allowed");
				}
			}

			$nmap_options .= " " . escapeshellarg($hostname);

			if (!count($input_errors)) {
				$do_nmapscan = true;
			}
		}
	} elseif ($_POST['stopbtn'] != "") {
		$action = gettext("Stop");

		/* check if nmap scan is already running */
		$processes_running = nmap_get_running_process();
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
	} elseif ($_POST['viewbtn'] != "" or $_POST['refreshbtn'] != "") {
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

$pgtitle = array("Package", "Diagnostics: Nmap");
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
	'hostname',
	'*IP or Hostname',
	'text',
	$hostname
))->setHelp('Enter the IP address or hostname that you would like to scan.');

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
))->setHelp('Select the scan type');

$section->addInput(new Form_Checkbox(
	'noping',
	'-P0',
	'Do not attempt to ping hosts before scanning',
	$_POST['noping']
))->setHelp('Allow scanning of networks that do not answer echo requests.%1$s' .
			'%2$smicrosoft.com is an example of such a network, and thus you should always use -P0 or -PT80 when port scanning microsoft.com.%3$s' .
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
	$_POST['servicever']
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
	$_POST['osdetect']
))->setHelp('Try to identify remote host via TCP/IP fingerprinting.%1$s' .
			'%2$sIn other words, it uses techniques to detect subtleties in the underlying operating system network stack of the computers being scanned.%3$s' .
			'It uses this information to create a "fingerprint" which it compares with its database of known OS fingerprints' .
			'(the nmap-os-fingerprints file) to determine the operating system of the target host.%4$s',

			'<span class="infoblock" style="font-size:90%"><br />',
			'<p style="margin:0px;padding:0px">',
			'<br />',
			'</p></span>');

$section->addInput(new Form_Input(
	'custom_scantype',
	'Custom Scan Type',
	'text',
	$custom_scantype
))->setHelp('Enter a custom Scan Type. It will override above selected Scan Type.%1$s' .
			'%2$sExp: -sA -sW -sFs%3$s',

			'<br />',
			'<p style="margin:0px;padding:0px;font-size:90%">',
			'</p>');

$section->addInput(new Form_Input(
	'custom_options',
	'Custom Options',
	'text',
	$custom_options
))->setHelp('Enter extra scan options.%1$s' .
			'%2$sExp: --traceroute --dns-servers server1 -p <port ranges>%3$s',

			'<br />',
			'<p style="margin:0px;padding:0px;font-size:90%">',
			'</p>');

$section->addInput(new Form_Input(
	'custom_targetspecs',
	'Custom Target Specs',
	'text',
	$custom_targetspecs
))->setHelp('Enter additional hosts or extra target options.%1$s' .
			'%2$sExp: ip hostname --exclude host1,host2 -iR <num hosts>%3$s',

			'<br />',
			'<p style="margin:0px;padding:0px;font-size:90%">',
			'</p>');

$form->add($section);

/* check if nmap scan is already running */
$processes_running = nmap_get_running_process();
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

if (file_exists($fp.$fe) and filesize($fp.$fe) > 0) {
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

} elseif ($action == gettext("View") or $action == gettext("Stop")) {
		if (file_exists($fp.$fe) and filesize($fp.$fe) > 0) {
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
		if (file_exists($fp.$fn) and (filesize($fp.$fn) > $max_display_size)) {
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
