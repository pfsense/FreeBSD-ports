<?php
/*
 * lcdproc.php
 *
 * part of pfSense (https://www.pfsense.org/)
 * Copyright (c) 2016-2023 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2011 Michele Di Maria
 * Copyright (c) 2007-2009 Seth Mos <seth.mos@dds.nl>
 * Copyright (c) 2008 Mark J Crane
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
require_once("/usr/local/pkg/lcdproc.inc");
global $mtxorb_backlight_color_list, $comport_list, $size_list, $driver_list;
global $connection_type_list, $mtxorb_type_list, $port_speed_list;
global $refresh_frequency_list, $percent_list, $backlight_list;

$lcdproc_config = config_get_path('installedpackages/lcdproc/config/0', []);

// Set default values for anything not in the $config
$pconfig = $lcdproc_config;
if (!isset($pconfig['enable']))                      $pconfig['enable']                      = '';
if (!isset($pconfig['comport']))                     $pconfig['enabled']                     = 'ucom1';
if (!isset($pconfig['size']))                        $pconfig['size']                        = '16x2';
if (!isset($pconfig['driver']))                      $pconfig['driver']                      = 'pyramid';
if (!isset($pconfig['connection_type']))             $pconfig['connection_type']             = 'lcd2usb'; // specific to hd44780 driver
if (!isset($pconfig['refresh_frequency']))           $pconfig['refresh_frequency']           = '5';
if (!isset($pconfig['port_speed']))                  $pconfig['port_speed']                  = '0';
if (!isset($pconfig['brightness']))                  $pconfig['brightness']                  = '-1';
if (!isset($pconfig['offbrightness']))               $pconfig['offbrightness']               = '-1';
if (!isset($pconfig['contrast']))                    $pconfig['contrast']                    = '-1';
if (!isset($pconfig['backlight']))                   $pconfig['backlight']                   = 'default';
if (!isset($pconfig['outputleds']))                  $pconfig['outputleds']                  = 'no';
if (!isset($pconfig['controlmenu']))                 $pconfig['controlmenu']                 = 'no';
if (!isset($pconfig['mtxorb_type']))                 $pconfig['mtxorb_type']                 = 'lcd'; // specific to Matrix Orbital driver
if (!isset($pconfig['mtxorb_adjustable_backlight'])) $pconfig['mtxorb_adjustable_backlight'] = true;  // specific to Matrix Orbital driver
if (!isset($pconfig['mtxorb_backlight_color']))      $pconfig['mtxorb_backlight_color']      = '';  // specific to Matrix Orbital driver


if ($_POST) {
	$input_errors = [];
	$pconfig = $_POST;

	/* Input validation */
	lcdproc_validate_list($input_errors, 'comport',                $comport_list,                'COM Port');
	lcdproc_validate_list($input_errors, 'size',                   $size_list,                   'Display Size');
	lcdproc_validate_list($input_errors, 'driver',                 $driver_list,                 'Driver');
	lcdproc_validate_list($input_errors, 'connection_type',        $connection_type_list,        'Connection Type');
	lcdproc_validate_list($input_errors, 'mtxorb_type',            $mtxorb_type_list,            'Display Type');
	lcdproc_validate_list($input_errors, 'mtxorb_backlight_color', $mtxorb_backlight_color_list, 'Matrix Orbital Background Color');
	lcdproc_validate_list($input_errors, 'port_speed',             $port_speed_list,             'Port Speed');
	lcdproc_validate_list($input_errors, 'refresh_frequency',      $refresh_frequency_list,      'Refresh Frequency');
	lcdproc_validate_list($input_errors, 'brightness',             $percent_list,                'Brightness');
	lcdproc_validate_list($input_errors, 'contrast',               $percent_list,                'Contrast');
	lcdproc_validate_list($input_errors, 'backlight',              $backlight_list,              'Backlight');
	lcdproc_validate_list($input_errors, 'offbrightness',          $percent_list,                'Off Brightness');

	if (empty($input_errors)) {
		$lcdproc_config['enable']                      = $pconfig['enable'];
		$lcdproc_config['comport']                     = $pconfig['comport'];
		$lcdproc_config['size']                        = $pconfig['size'];
		$lcdproc_config['driver']                      = $pconfig['driver'];
		$lcdproc_config['connection_type']             = $pconfig['connection_type'];
		$lcdproc_config['refresh_frequency']           = $pconfig['refresh_frequency'];
		$lcdproc_config['port_speed']                  = $pconfig['port_speed'];
		$lcdproc_config['brightness']                  = $pconfig['brightness'];
		$lcdproc_config['offbrightness']               = $pconfig['offbrightness'];
		$lcdproc_config['contrast']                    = $pconfig['contrast'];
		$lcdproc_config['backlight']                   = $pconfig['backlight'];
		$lcdproc_config['outputleds']                  = $pconfig['outputleds'];
		$lcdproc_config['controlmenu']                 = $pconfig['controlmenu'];
		$lcdproc_config['mtxorb_type']                 = $pconfig['mtxorb_type'];
		$lcdproc_config['mtxorb_adjustable_backlight'] = $pconfig['mtxorb_adjustable_backlight'];
		$lcdproc_config['mtxorb_backlight_color']      = $pconfig['mtxorb_backlight_color'];

		config_set_path('installedpackages/lcdproc/config/0', $lcdproc_config);
		write_config("lcdproc: Settings saved");
		sync_package_lcdproc();
	}
}

$shortcut_section = 'lcdproc';

$pgtitle = array(gettext("Services"), gettext("LCDproc"), gettext("Server"));
include("head.inc");

if (!empty($input_errors)) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("Server"),  true,  "/packages/lcdproc/lcdproc.php");
$tab_array[] = array(gettext("Screens"), false, "/packages/lcdproc/lcdproc_screens.php");
display_top_tabs($tab_array);

$form = new Form();
$section = new Form_Section('LCD connection and hardware');

// Add the Enable checkbox
$section->addInput(
	new Form_Checkbox(
		'enable', // checkbox name (id)
		'Enable', // checkbox label
		'Enable LCDproc service', // checkbox text
		$pconfig['enable'] // checkbox initial value
	)
);

// Add the com port selector
$section->addInput(
	new Form_Select(
		'comport',
		'COM port',
		$pconfig['comport'], // Initial value.
		$comport_list
	)
)->setHelp('Set the com port LCDproc should use.');

$section->addInput(
	new Form_Select(
		'size',
		'Display Size',
		$pconfig['size'], // Initial value.
		$size_list
	)
)->setHelp('Set the display size lcdproc should use.');

$section->addInput(
	new Form_Select(
		'driver',
		'Driver',
		$pconfig['driver'], // Initial value.
		$driver_list
	)
)->setHelp('Select the LCD driver LCDproc should use. Some drivers will show additional settings.');

// The connection type is HD44780-specific, so is hidden by javascript (below)
// if the HD44780 driver is not being used.
$section->addInput(
	new Form_Select(
		'connection_type',
		'Connection Type',
		$pconfig['connection_type'], // Initial value.
		$connection_type_list
	)
)->setHelp('Select the HD44780 connection type');

/* The mtxorb_type, mtxorb_adjustable_backlight, and mtxorb_backlight_color are
 * Matrix-Orbital-specific, so are hidden by javascript (below) if the MtxOrb
 * driver is not being used.
 */
$subsection = new Form_Group('Display type');
$subsection->add(
	new Form_Select(
		'mtxorb_type',
		'Display Type',
		$pconfig['mtxorb_type'], // Initial value.
		$mtxorb_type_list
	)
);
$subsection->add(
	new Form_Checkbox(
		'mtxorb_adjustable_backlight',          // checkbox name (id)
		'Has adjustable backlight',             // label
		'Has adjustable backlight',             // text
		$pconfig['mtxorb_adjustable_backlight'] // initial value
	)
);
$subsection->add(
	new Form_Select(
		'mtxorb_backlight_color',
		'Background Color',
		$pconfig['mtxorb_backlight_color'], // Initial value.
		array_combine(array_keys($mtxorb_backlight_color_list), array_keys($mtxorb_backlight_color_list))
	)
)->setHelp('LCD Background Color');

$subsection->setHelp(
	'Select the Matrix Orbital display type.%1$s%1$s' .
	'Some firmware versions of Matrix Orbital and compatible modules do not support an adjustable backlight ' .
	'and only can switch the backlight on/off. If the LCD experiences randomly appearing block characters ' .
	'and the backlight cannot be switched on or off, uncheck the adjustable backlight option.%1$s%1$s' .
	'Some Matrix Orbital compatible Adafruit controller boards have extended commands to set ' .
	'the background color. This works independently of the adjustable backlight checkbox. ' .
	'Leave at default if this feature is not supported.',
	'<br/>'
);

$section->add($subsection);
?>

<script type="text/javascript">
//<![CDATA[
	events.push(
		function() {
			$('#driver').on('change', updateInputVisibility);
			updateInputVisibility();
		}
	);

    function updateInputVisibility() {
		var driverName_lowercase = $('#driver').val().toLowerCase();

		// Hide the connection type selection field when not using the HD44780 driver
		var using_HD44780_driver  = driverName_lowercase.indexOf("hd44780") >= 0;
		using_HD44780_driver     |= jQuery("#driver option:selected").text().toLowerCase().indexOf("hd44780") >= 0;
		hideInput('connection_type', !using_HD44780_driver); // Hides the entire section

		// Hide the Matrix Orbital specific fields when not using the MtxOrb driver
		var using_MtxOrb_driver  = driverName_lowercase.indexOf("mtxorb") >= 0;
		hideInput('mtxorb_type', !using_MtxOrb_driver); // Hides the entire section, including the mtxorb_adjustable_backlight checkbox

		// Hide the Output-LEDs checkbox when not using the CFontzPacket driver
		var driverSupportsLEDs  = driverName_lowercase.indexOf("cfontzpacket") >= 0;
		hideCheckbox('outputleds', !driverSupportsLEDs);
	}
//]]>
</script>

<?php

$section->addInput(
	new Form_Select(
		'port_speed',
		'Port Speed',
		$pconfig['port_speed'], // Initial value.
		$port_speed_list
	)
)->setHelp(
	'Set the port speed.%1$s' .
	'Caution: not all the driver or panels support all the speeds, leave "default" if unsure.',
	'<br />'
);

/********* New section *********/
$form->add($section);
$section = new Form_Section('Display preferences');
/********* New section *********/

$section->addInput(
	new Form_Select(
		'refresh_frequency',
		'Refresh Frequency',
		$pconfig['refresh_frequency'], // Initial value.
		$refresh_frequency_list
	)
)->setHelp('Set the duration for which each info screen will be displayed.');

// The connection type is CFontzPacket-specific, so is hidden by javascript (above)
// if a CFontzPacket driver is not being used.
$section->addInput(
	new Form_Checkbox(
		'outputleds', // checkbox name (id)
		'Enable Output LEDs', // checkbox label
		'Enable the output LEDs present on some LCD panels.', // checkbox text
		$pconfig['outputleds'] // checkbox initial value
	)
)->setHelp(
	'This feature is currently supported by the CFontzPacket driver only.%1$s' .
	'Each LED can be off or show two colors: RED (alarm) or GREEN (everything ok) and shows:%1$s' .
	'LED1: NICs status (green: ok, red: at least one nic down)%1$s' .
	'LED2: CARP status (green: master, red: backup, off: CARP not implemented)%1$s' .
	'LED3: CPU status (green %2$s 50%%, red %3$s 50%%)%1$s' .
	'LED4: Gateway status (green: ok, red: at least one gateway not responding, off: no gateway configured).',
	'<br />', '&lt;', '&gt;'
);

$section->addInput(
	new Form_Checkbox(
		'controlmenu', // checkbox name (id)
		'pfSense control menu', // checkbox label
		'Enable the pfSense control menu next to LCDproc\'s Options menu.', // checkbox text
		$pconfig['controlmenu'] // checkbox initial value
	)
)->setHelp(
	'Requires a display with buttons (e.g. Crystalfontz 635/735/835).%1$s' .
	'Currently supports several basic functions including reboot and halt.',
	'<br />'
);

$section->addInput(
	new Form_Select(
		'brightness',
		'Brightness',
		$pconfig['brightness'], // Initial value.
		$percent_list
	)
)->setHelp(
	'Set the brightness of the LCD panel.%1$s' . '
	This option is not supported by all the LCD panels, leave "default" if unsure.',
	'<br />'
);

$section->addInput(
	new Form_Select(
		'contrast',
		'Contrast',
		$pconfig['contrast'], // Initial value.
		$percent_list
	)
)->setHelp(
	'Set the contrast of the LCD panel.%1$s' .
	'This option is not supported by all the LCD panels, leave "default" if unsure.',
	'<br />'
);

$section->addInput(
	new Form_Select(
		'backlight',
		'Backlight',
		$pconfig['backlight'], // Initial value.
		$backlight_list
	)
)->setHelp(
	'Set the backlight setting. If set to the default value, then the backlight setting of the display can be influenced by the clients.%1$s' .
	'This option is not supported by all the LCD panels, leave "default" if unsure.',
	'<br />'
);

$section->addInput(
	new Form_Select(
		'offbrightness',
		'Off Brightness',
		$pconfig['offbrightness'], // Initial value.
		$percent_list
	)
)->setHelp(
	'Set the off-brightness of the LCD panel. This value is used when the display is normally switched off in case LCDd is inactive.%1$s' .
	'This option is not supported by all the LCD panels, leave "default" if unsure.',
	'<br />'
);

$form->add($section); // Add the section to our form
print($form); // Finally . . We can display our new form

?>

<div class="infoblock">
	<?=print_info_box('For more information see: <a href="http://lcdproc.org/docs.php3">LCDproc documentation</a>.', 'info')?>
</div>

<?php include("foot.inc"); ?>
