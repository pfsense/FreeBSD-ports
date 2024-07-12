<?php
/*
 * nut_settings.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2016-2017 Denny Page
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


require("guiconfig.inc");
require("/usr/local/pkg/nut/nut.inc");

$a_nut = config_get_path('installedpackages/nut/config/0', []);

if (!empty($a_nut)) {
	if (isset($a_nut['type'])) {
		$pconfig = $a_nut;
		$pconfig['extra_args'] = base64_decode($pconfig['extra_args']);
		$pconfig['upsmon_conf'] = base64_decode($pconfig['upsmon_conf']);
		$pconfig['ups_conf'] = base64_decode($pconfig['ups_conf']);
		$pconfig['upsd_conf'] = base64_decode($pconfig['upsd_conf']);
		$pconfig['upsd_users'] = base64_decode($pconfig['upsd_users']);
	} elseif (isset($a_nut['monitor'])) {
		if ($a_nut['monitor'] == 'local') {
			$pconfig['name'] = $a_nut['name'];

			if (preg_match('/^usbhid/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_usb';
				$pconfig['usb_driver'] = 'usbhid-ups';
			} elseif (preg_match('/^blazer_usb/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_usb';
				$pconfig['usb_driver'] = 'blazer_usb';
			} elseif (preg_match('/^tripplite_usb/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_usb';
				$pconfig['usb_driver'] = 'tripplite_usb';
			} elseif (preg_match('/^genericups/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_generic';
				$pconfig['generic_type'] = strstr($a_nut['upstype'], ' ', true);
			} elseif (preg_match('/^apcsmart/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_serial';
				$pconfig['serial_driver'] = 'apcsmart';
				/* cable type 940-0024C does not need converstion because it is the default */
				if (preg_match('/^940-0095B/', $a_nut['cable'])) {
					$pconfig['extra_args'] = "cable=940-0095B\n";
				}
			} elseif (preg_match('/^belkin/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_serial';
				$pconfig['serial_driver'] = 'belkin';
			} elseif (preg_match('/^belkinunv/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_serial';
				$pconfig['serial_driver'] = 'belkinunv';
			} elseif (preg_match('/^bestups/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_serial';
				$pconfig['serial_driver'] = 'bestups';
			} elseif (preg_match('/^bestuferrups/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_serial';
				$pconfig['serial_driver'] = 'bestuferrups';
			} elseif (preg_match('/^bestfcom/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_serial';
				$pconfig['serial_driver'] = 'bestfcom';
			} elseif (preg_match('/^cpsups/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_serial';
				$pconfig['serial_driver'] = 'powerpanel';
			} elseif (preg_match('/^cyberpower/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_serial';
				$pconfig['serial_driver'] = 'cyberpower';
			} elseif (preg_match('/^megatec/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_serial';
				$pconfig['serial_driver'] = 'megatec';
			} elseif (preg_match('/^metasys/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_serial';
				$pconfig['serial_driver'] = 'metasys';
			} elseif (preg_match('/^mge-shut/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_serial';
				$pconfig['serial_driver'] = 'mge-shut';
			} elseif (preg_match('/^powercom/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_serial';
				$pconfig['serial_driver'] = 'powercom';
			} elseif (preg_match('/^rhino/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_serial';
				$pconfig['serial_driver'] = 'rhino';
			} elseif (preg_match('/^solis/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_serial';
				$pconfig['serial_driver'] = 'solis';
			} elseif (preg_match('/^tripplite/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_serial';
				$pconfig['serial_driver'] = 'tripplite';
			} elseif (preg_match('/^tripplitesu/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_serial';
				$pconfig['serial_driver'] = 'tripplitesu';
			} elseif (preg_match('/^upscode2/', $a_nut['driver'])) {
				$pconfig['type'] = 'local_serial';
				$pconfig['serial_driver'] = 'upscode2';
			}
		} elseif ($a_nut['monitor'] == 'snmp') {
			$pconfig['type'] = 'remote_snmp';
			$pconfig['name'] = $a_nut['snmpname'];
			$pconfig['remote_addr'] = $a_nut['snmpaddr'];
			if (!empty($a_nut['snmpcommunity']) && $a_nut['snmpcommunity'] != 'public') {
				$pconfig['extra_args'] = "community=" . $a_nut['snmpcommunity'] . "\n";
			}
			/* The prior version of nut package had v2c incorrectly marked as default. Use
               of 64 bit counters generally isn't desirable, so we explictly ignore that here.
               Add "snmp_version=v2c" to Driver Extra Arguments to restore. */
		} elseif ($a_nut['monitor'] == 'remote') {
			$pconfig['type'] = 'remote_nut';
			$pconfig['name'] = $a_nut['remotename'];
			$pconfig['remote_addr'] = $a_nut['remoteaddr'];
			$pconfig['remote_user'] = $a_nut['remoteuser'];
			$pconfig['remote_pass'] = $a_nut['remotepass'];
		}

		$migration_warning = gettext("WARNING:  An attempt has been made to migrate the prior NUT configuration to the new format. Please review the settings below carefully before saving. The prior NUT configuration will be lost when the new configuration is saved.");
	}
}


if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	if ($pconfig['type'] != 'disabled') {
		if (empty($pconfig['name'])) {
			$input_errors[] = gettext("UPS name cannot be empty");
		}
		elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $pconfig['name'])) {
			$input_errors[] = gettext("Name may contain [a-zA-Z0-9_-] only");
		}
	}

	if ($pconfig['type'] == 'local_generic') {
		if (!is_numericint($pconfig['generic_type']) ||
			intval($pconfig['generic_type']) < 0 ||
			intval($pconfig['generic_type']) > 22) {
			$input_errors[] = gettext("Generic UPS Type must be an integer between 0 and 22");
	   }
	}

	if ($pconfig['type'] == 'remote_nut' ||
		$pconfig['type'] == 'remote_apcupsd' ||
		$pconfig['type'] == 'remote_netxml' ||
		$pconfig['type'] == 'remote_snmp') {
		if (!is_ipaddr($pconfig['remote_addr']) && !is_hostname($pconfig['remote_addr']))
		{
			$input_errors[] = gettext("Remote IP Address / Host must be a valid IP address or hostname");
		}
	 }

	if ($pconfig['type'] == 'remote_nut' ||
		$pconfig['type'] == 'remote_apcupsd' ||
		$pconfig['type'] == 'remote_netxml') {
		if (!empty($pconfig['remote_port']) && !is_port($pconfig['remote_port'])) {
			$input_errors[] = gettext("Remote Port must be a valid port number");
		}
	}

	if ($pconfig['type'] == 'remote_nut') {
		if (empty($pconfig['remote_user'])) {
			$input_errors[] = gettext("Remote username cannot be empty");
		}
		if (empty($pconfig['remote_pass'])) {
			$input_errors[] = gettext("Remote password cannot be empty");
		}
	}

	if ($pconfig['type'] == 'dummy') {
		if (empty($pconfig['dummy_port'])) {
			$input_errors[] = gettext("Dummy port cannot be empty");
		}
	}

	if (!$input_errors) {
		$nut = array();
		$nut['type'] = $pconfig['type'];
		$nut['name'] = $pconfig['name'];
		$nut['email'] = $pconfig['email'];

		switch ($nut['type']) {
			case 'disabled':
				/* Preserve prior settings */
				$nut['usb_driver'] = $pconfig['usb_driver'];
				$nut['serial_driver'] = $pconfig['serial_driver'];
				$nut['serial_port'] = $pconfig['serial_port'];
				$nut['generic_type'] = $pconfig['generic_type'];
				$nut['remote_proto'] = $pconfig['remote_proto'];
				$nut['remote_addr'] = $pconfig['remote_addr'];
				$nut['remote_port'] = $pconfig['remote_port'];
				$nut['remote_user'] = $pconfig['remote_user'];
				$nut['remote_pass'] = $pconfig['remote_pass'];
				$nut['dummy_port'] = $pconfig['dummy_port'];
				break;
			case 'local_usb':
				$nut['usb_driver'] = $pconfig['usb_driver'];
				break;
			case 'local_serial':
				$nut['serial_driver'] = $pconfig['serial_driver'];
				$nut['serial_port'] = $pconfig['serial_port'];
				break;
			case 'local_generic':
				$nut['serial_port'] = $pconfig['serial_port'];
				$nut['generic_type'] = $pconfig['generic_type'];
				break;
			case 'remote_nut':
				$nut['remote_addr'] = $pconfig['remote_addr'];
				$nut['remote_port'] = $pconfig['remote_port'];
				$nut['remote_user'] = $pconfig['remote_user'];
				$nut['remote_pass'] = $pconfig['remote_pass'];
				break;
			case 'remote_apcupsd':
				$nut['remote_addr'] = $pconfig['remote_addr'];
				$nut['remote_port'] = $pconfig['remote_port'];
				break;
			case 'remote_netxml':
				$nut['remote_proto'] = $pconfig['remote_proto'];
				$nut['remote_addr'] = $pconfig['remote_addr'];
				$nut['remote_port'] = $pconfig['remote_port'];
				/* NB: netxml does not actually use username/password currently, but may in the future */
				$nut['remote_user'] = $pconfig['remote_user'];
				$nut['remote_pass'] = $pconfig['remote_pass'];
				break;
			case 'remote_snmp':
				$nut['remote_addr'] = $pconfig['remote_addr'];
				break;
			case 'dummy':
				$nut['dummy_port'] = $pconfig['dummy_port'];
				break;
		}

		$nut['upsmon_conf'] = base64_encode(trim(str_replace("\r\n", "\n", $pconfig['upsmon_conf'])));
		if ($nut['type'] != 'remote_nut') {
			$nut['extra_args'] = base64_encode(trim(str_replace("\r\n", "\n", $pconfig['extra_args'])));
			$nut['ups_conf'] = base64_encode(trim(str_replace("\r\n", "\n", $pconfig['ups_conf'])));
			$nut['upsd_conf'] = base64_encode(trim(str_replace("\r\n", "\n", $pconfig['upsd_conf'])));
			$nut['upsd_users'] = base64_encode(trim(str_replace("\r\n", "\n", $pconfig['upsd_users'])));
		}

		config_set_path('installedpackages/nut/config/0', $nut);
		write_config("Updated UPS settings");

		nut_sync_config();

		header("Location: nut_status.php");
		exit;
	}
}


$nut_types = array(
	'disabled' => gettext('Disabled'),
	'local_usb' => gettext('Local USB'),
	'local_serial' => gettext('Local serial'),
	'local_generic' => gettext('Local serial (genericups)'),
	'remote_nut' => gettext('Remote NUT server'),
	'remote_apcupsd' => gettext('Remote apcupsd'),
	'remote_netxml' => gettext('Remote netxml'),
	'remote_snmp' => gettext('Remote snmp'),
	'dummy' => gettext('Dummy UPS') );

$usb_drivers = array(
	'usbhid-ups' => 'usbhid',
	'bcmxcp_usb' => 'bcmxcp',
	'blazer_usb' => 'blazer',
	'nutdrv_atcl_usb' => 'nutdrv_atcl',
	'nutdrv_qx' => 'nutdrv_qx',
	'richcomm_usb' => 'richcomm',
	'riello_usb' => 'riello',
	'tripplite_usb' => 'tripplite');

$serial_drivers = array(
	'apcsmart' => 'apcsmart',
	'bcmxcp' => 'bcmxcp',
	'belkin' => 'belkin',
	'belkinunv' => 'belkinunv',
	'bestfcom' => 'bestfcom',
	'bestfortress' => 'bestfortress',
	'bestuferrups' => 'bestuferrups',
	'bestups' => 'bestups',
	'blazer_ser' => 'blazer',
	'etapro' => 'etapro',
	'everups' => 'everups',
	'gamatronic' => 'gamatronic',
	'isbmex' => 'isbmex',
	'ivtscd' => 'ivtscd',
	'liebert' => 'liebert',
	'liebert-esp2' => 'liebert-esp2',
	'masterguard' => 'masterguard',
	'metasys' => 'metasys',
	'mge-shut' => 'mge-shut',
	'mge-utalk' => 'mge-utalk',
	'microdowell' => 'microdowell',
	'nutdrv_qx' => 'nutdrv_qx',
	'oneac' => 'oneac',
	'optiups' => 'optiups',
	'powercom' => 'powercom',
	'powerpanel' => 'powerpanel',
	'rhino' => 'rhino',
	'riello_ser' => 'riello',
	'safenet' => 'safenet',
	'solis' => 'solis',
	'tripplite' => 'tripplite',
	'tripplitesu' => 'tripplitesu',
	'upscode2' => 'upscode2',
	'victronups' => 'victronups');


$serial_ports = array();
$dlist = glob("/dev/cua[uU]*");
foreach ($dlist as $d) {
	if (preg_match('/^\/dev\/cua[uU][0-9]+$/', $d)) {
		$serial_ports[$d] = $d; 
	} 
}

$remote_protos = array('http' => 'http', 'https' => 'https');

$pgtitle = array(gettext("Services"), gettext("UPS"), gettext("Settings"));
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("UPS Status"), false, "/nut_status.php");
$tab_array[] = array(gettext("UPS Settings"), true, "/nut_settings.php");
display_top_tabs($tab_array);


if (isset($migration_warning)) {
	print_info_box($migration_warning);
}

if ($input_errors) {
	print_input_errors($input_errors);
}


$form = new Form;

$section = new Form_Section('General Settings');
$section->addInput(new Form_Select(
	'type',
	'UPS Type',
	$pconfig['type'],
	$nut_types
))->sethelp('For information on choosing a type and driver, see the ' . '<a target="_blank" href="http://networkupstools.org/stable-hcl.html">' . 'NUT Hardware Compatibility List' . '</a>.');

$group = new Form_Group('UPS Name');
$group->addClass('basic');
$group->add(new Form_Input(
	'name',
	'UPS Name',
	'text',
	$pconfig['name']
));
$section->add($group);

$group = new Form_Group('Notifcations');
$group->addClass('basic');
$group->add(new Form_Checkbox(
	'email',
	'E-Mail',
	'Enable notifications',
	$pconfig['email']
))->sethelp('E-Mail/Telegram/Pushover delivery settings are configured under System -> Advanced, on the Notifications tab.');
$section->add($group);

$form->add($section);

$section = new Form_Section('Driver Settings');
$section->addClass('basic');

$section->addInput(new Form_Select(
	'usb_driver',
	'Driver',
	$pconfig['usb_driver'],
	$usb_drivers
));

$section->addInput(new Form_Select(
	'serial_driver',
	'Driver',
	$pconfig['serial_driver'],
	$serial_drivers
));

$section->addInput(new Form_Select(
	'serial_port',
	'Serial port',
	$pconfig['serial_port'],
	$serial_ports
));

$section->addInput(new Form_Input(
	'generic_type',
	'Generic Ups Type',
	'text',
	$pconfig['generic_type']
));

$section->addInput(new Form_Select(
	'remote_proto',
	'Remote protocol',
	$pconfig['remote_proto'],
	$remote_protos
));

$section->addInput(new Form_Input(
	'remote_addr',
	'Remote IP address or hostname',
	'text',
	$pconfig['remote_addr']
));

$section->addInput(new Form_Input(
	'remote_port',
	'Remote port (optional)',
	'text',
	$pconfig['remote_port']
));

$section->addInput(new Form_Input(
	'remote_user',
	'Remote username',
	'text',
	$pconfig['remote_user']
));

$section->addInput(new Form_Input(
	'remote_pass',
	'Remote password',
	'text',
	$pconfig['remote_pass']
))->setType('password');

$section->addInput(new Form_Input(
	'dummy_port',
	'Dummy port',
	'text',
	$pconfig['dummy_port']
));


$section->addInput(new Form_Textarea(
	'extra_args',
	'Extra Arguments to driver (optional)',
	$pconfig['extra_args']
))->sethelp('Extra Arguments to the NUT driver, one per line. For information on Extra Arguments see the appropriate ' . '<a target="_blank" href="http://networkupstools.org/docs/man/index.html#Drivers">' . 'NUT driver manual page' . '</a>.');

$button = new Form_Button(
	'advancedbutton',
	'Display Advanced',
	null,
	'fa-solid fa-cog'
);
$button->setAttribute('type', 'button')->addClass('btn-info btn-sm');
$section->addInput(new Form_StaticText(
	null,
	$button
));

$form->add($section);


$section = new Form_Section('Advanced settings');
$section->addClass('advanced');

$section->addInput(new Form_Textarea(
	'upsmon_conf',
	'Additional configuration lines for upsmon.conf',
	$pconfig['upsmon_conf']
))->sethelp('Additional directives for upsmon.conf. For information on upsmon.conf directives, see the ' . '<a target="_blank" href="http://networkupstools.org/docs/man/upsmon.conf.html">' . 'upsmon.conf manual page' . '</a>.');

$section->addInput(new Form_Textarea(
	'ups_conf',
	'Additional configuration lines for ups.conf',
	$pconfig['ups_conf']
))->sethelp('Additional global directives for ups.conf. For information on ups.conf global directives, see the ' . '<a target="_blank" href="http://networkupstools.org/docs/man/ups.conf.html">' . 'ups.conf manual page' . '</a>.');

$section->addInput(new Form_Textarea(
	'upsd_conf',
	'Additional configuration lines for upsd.conf',
	$pconfig['upsd_conf']
))->sethelp('Additional directives for upsd.conf. For information on upsd.conf directives, see the ' . '<a target="_blank" href="http://networkupstools.org/docs/man/upsd.conf.html">' . 'upsd.conf manual page' . '</a>.');

$section->addInput(new Form_Textarea(
	'upsd_users',
	'Additional configuration lines for upsd.users',
	$pconfig['upsd_users']
))->sethelp('Additional entries for upsd.users. For information on upsd.users entries, see the ' . '<a target="_blank" href="http://networkupstools.org/docs/man/upsd.users.html">' . 'upsd.users manual page' . '</a>.');

$form->add($section);


print($form);

?>


<script type="text/javascript">
//<![CDATA[
events.push(function() {
	var showadvanced = false;

	function typeChange(type) {
		if (type == 'disabled') {
			hideClass('basic', true);
			hideClass('advanced', true);
		} else {
			hideClass('basic', false);
			hideClass('advanced', !showadvanced);
		}

		switch (type) {
			case 'local_usb':
				hideInput('usb_driver', false);
				hideInput('serial_driver', true);
				hideInput('serial_port', true);
				hideInput('generic_type', true);
				hideInput('remote_proto', true);
				hideInput('remote_addr', true);
				hideInput('remote_port', true);
				hideInput('remote_user', true);
				hideInput('remote_pass', true);
				hideInput('dummy_port', true);
				hideInput('extra_args', false);
				hideInput('ups_conf', false);
				hideInput('upsd_conf', false);
				hideInput('upsd_users', false);
				break;
			case 'local_serial':
				hideInput('usb_driver', true);
				hideInput('serial_driver', false);
				hideInput('serial_port', false);
				hideInput('generic_type', true);
				hideInput('remote_proto', true);
				hideInput('remote_addr', true);
				hideInput('remote_port', true);
				hideInput('remote_user', true);
				hideInput('remote_pass', true);
				hideInput('dummy_port', true);
				hideInput('extra_args', false);
				hideInput('ups_conf', false);
				hideInput('upsd_conf', false);
				hideInput('upsd_users', false);
				break;
			case 'local_generic':
				hideInput('usb_driver', true);
				hideInput('serial_driver', true);
				hideInput('serial_port', false);
				hideInput('generic_type', false);
				hideInput('remote_proto', true);
				hideInput('remote_addr', true);
				hideInput('remote_port', true);
				hideInput('remote_user', true);
				hideInput('remote_pass', true);
				hideInput('dummy_port', true);
				hideInput('extra_args', false);
				hideInput('ups_conf', false);
				hideInput('upsd_conf', false);
				hideInput('upsd_users', false);
				break;
			case 'dummy':
				hideInput('usb_driver', true);
				hideInput('serial_driver', true);
				hideInput('serial_port', true);
				hideInput('generic_type', true);
				hideInput('remote_proto', true);
				hideInput('remote_addr', true);
				hideInput('remote_port', true);
				hideInput('remote_user', true);
				hideInput('remote_pass', true);
				hideInput('dummy_port', false);
				hideInput('extra_args', false);
				hideInput('ups_conf', false);
				hideInput('upsd_conf', false);
				hideInput('upsd_users', false);
				break;
			case 'remote_nut':
				hideInput('usb_driver', true);
				hideInput('serial_driver', true);
				hideInput('serial_port', true);
				hideInput('generic_type', true);
				hideInput('remote_proto', true);
				hideInput('remote_addr', false);
				hideInput('remote_port', false);
				hideInput('remote_user', false);
				hideInput('remote_pass', false);
				hideInput('dummy_port', true);
				hideInput('extra_args', true);
				hideInput('ups_conf', true);
				hideInput('upsd_conf', true);
				hideInput('upsd_users', true);
				break;
			case 'remote_apcupsd':
				hideInput('usb_driver', true);
				hideInput('serial_driver', true);
				hideInput('serial_port', true);
				hideInput('generic_type', true);
				hideInput('remote_proto', true);
				hideInput('remote_addr', false);
				hideInput('remote_port', false);
				hideInput('remote_user', true);
				hideInput('remote_pass', true);
				hideInput('dummy_port', true);
				hideInput('extra_args', false);
				hideInput('ups_conf', false);
				hideInput('upsd_conf', false);
				hideInput('upsd_users', false);
				break;
			case 'remote_netxml':
				hideInput('usb_driver', true);
				hideInput('serial_driver', true);
				hideInput('serial_port', true);
				hideInput('generic_type', true);
				hideInput('remote_proto', false);
				hideInput('remote_addr', false);
				hideInput('remote_port', false);
				hideInput('remote_user', false);
				hideInput('remote_pass', false);
				hideInput('dummy_port', true);
				hideInput('extra_args', false);
				hideInput('ups_conf', false);
				hideInput('upsd_conf', false);
				hideInput('upsd_users', false);
				break;
			case 'remote_snmp':
				hideInput('usb_driver', true);
				hideInput('serial_driver', true);
				hideInput('serial_port', true);
				hideInput('generic_type', true);
				hideInput('remote_proto', true);
				hideInput('remote_addr', false);
				hideInput('remote_port', true);
				hideInput('remote_user', true);
				hideInput('remote_pass', true);
				hideInput('dummy_port', true);
				hideInput('extra_args', false);
				hideInput('ups_conf', false);
				hideInput('upsd_conf', false);
				hideInput('upsd_users', false);
				break;
		}
	}

	function advancedChange(pageload) {
		var text;

		if (pageload) {
			// Initial page load
			showadvanced = <?php
				if (empty($pconfig['upsmon_conf']) && empty($pconfig['ups_conf']) && empty($pconfig['upsd_conf']) && empty($pconfig['upsd_users'])) {
					echo 'false';
				} else {
					echo 'true';
				}
			?>
		} else {
			showadvanced = !showadvanced;
			hideClass('advanced', !showadvanced);
		}

		if (showadvanced) {
			text = "<?=gettext('Hide Advanced');?>";
		} else {
			text = "<?=gettext('Display Advanced');?>";
		}
		$('#advancedbutton').html('<i class="fa-solid fa-cog"></i> ' + text);
	}

	// Show/Hide settings when the type changes
	$('#type').on('change', function() {
		typeChange(this.value);
	});

	// Show/Hide advanced section
	$('#advancedbutton').click(function(event) {
		advancedChange(false);
	});

	// Initial page load
	advancedChange(true);
	typeChange($('#type').val());
});
//]]>
</script>

<?php include("foot.inc");
 
