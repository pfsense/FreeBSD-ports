<?php
/*
 * lcdproc.php
 *
 * part of pfSense (https://www.pfsense.org/)
 * Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2011 Michele Di Maria
 * Copyright (c) 2009 Scott Ullrich
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

$lcdproc_config         = &$config['installedpackages']['lcdproc']['config'][0];
$lcdproc_screens_config = &$config['installedpackages']['lcdprocscreens']['config'][0];

// Set default values for anything not in the $config
$pconfig = $lcdproc_config;
if (!isset($pconfig['enable']))            $pconfig['enable']            = '';
if (!isset($pconfig['comport']))           $pconfig['enabled']           = 'ucom1';
if (!isset($pconfig['size']))              $pconfig['size']              = '16x2';
if (!isset($pconfig['driver']))            $pconfig['driver']            = 'pyramid';
if (!isset($pconfig['connection_type']))   $pconfig['connection_type']   = 'lcd2usb';
if (!isset($pconfig['refresh_frequency'])) $pconfig['refresh_frequency'] = '5';
if (!isset($pconfig['port_speed']))        $pconfig['port_speed']        = '0';
if (!isset($pconfig['brightness']))        $pconfig['brightness']        = '-1';
if (!isset($pconfig['offbrightness']))     $pconfig['offbrightness']     = '-1';
if (!isset($pconfig['contrast']))          $pconfig['contrast']          = '-1';
if (!isset($pconfig['backlight']))         $pconfig['backlight']         = 'default';
if (!isset($pconfig['outputleds']))        $pconfig['outputleds']        = 'no';


if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	if (!$input_errors) {
		$lcdproc_config['enable']            = $pconfig['enable'];
		$lcdproc_config['comport']           = $pconfig['comport'];
		$lcdproc_config['size']              = $pconfig['size'];   
		$lcdproc_config['driver']            = $pconfig['driver'];
		$lcdproc_config['connection_type']   = $pconfig['connection_type'];
		$lcdproc_config['refresh_frequency'] = $pconfig['refresh_frequency'];
		$lcdproc_config['port_speed']        = $pconfig['port_speed'];
		$lcdproc_config['brightness']        = $pconfig['brightness'];
		$lcdproc_config['offbrightness']     = $pconfig['offbrightness'];
		$lcdproc_config['contrast']          = $pconfig['contrast'];
		$lcdproc_config['backlight']         = $pconfig['backlight'];
		$lcdproc_config['outputleds']        = $pconfig['outputleds'];
				
		write_config();
		sync_package_lcdproc();
	}
}


$pgtitle = array(gettext("Services"), gettext("LCDproc"), gettext("Server"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("Server"),  true,  "/packages/lcdproc/lcdproc.php");
$tab_array[] = array(gettext("Screens"), false, "/packages/lcdproc/lcdproc_screens.php");
display_top_tabs($tab_array);

// The constructor for Form automatically creates a submit button. If you want to suppress that
// use Form(false), of specify a different button using Form($mybutton)
$form = new Form();
$section = new Form_Section('LCD connection and hardware');

// Add the Enable checkbox
$section->addInput(
	new Form_Checkbox(
		'enable', // checkbox name (id)
		'Enable', // checkbox label
		'Enable LCDproc at startup', // checkbox text
		$pconfig['enable'] // checkbox initial value
	)
);

// Add the com port selector
$section->addInput(
	new Form_Select(
		'comport',
		'Com port',
		$pconfig['comport'], // Initial value.
		[
			'none'    => 'none',
			'com1'    => 'Serial COM port 1 (/dev/cua0)',
			'com2'    => 'Serial COM port 2 (/dev/cua1)',
			'com1a'   => 'Serial COM port 1 alternate (/dev/cuau0)',
			'com2a'   => 'Serial COM port 2 alternate (/dev/cuau1)',
			'ucom1'   => 'USB COM port 1 (/dev/cuaU0)',
			'ucom2'   => 'USB COM port 2 (/dev/cuaU1)',
			'lpt1'    => 'Parallel port 1 (/dev/lpt0)',
			'ugen0.2' => 'USB COM port 1 alternate (/dev/ugen0.2)',
			'ugen1.2' => 'USB COM port 2 alternate (/dev/ugen1.2)',
			'ugen1.3' => 'USB COM port 3 alternate (/dev/ugen1.3)',
			'ugen2.2' => 'USB COM port 4 alternate (/dev/ugen2.2)'
		]
	)
)->setHelp('Set the com port LCDproc should use.');

$section->addInput(
	new Form_Select(
		'size',
		'Display size',
		$pconfig['size'], // Initial value.
		[
			'12x1' => '1 rows 12 colums',
			'12x2' => '2 rows 12 colums',
			'12x4' => '4 rows 12 colums',
			'16x1' => '1 row 16 colums',
			'16x2' => '2 rows 16 colums',
			'16x4' => '4 rows 16 colums',
			'20x1' => '1 row 20 colums',
			'20x2' => '2 rows 20 colums',
			'20x4' => '4 rows 20 colums'
		]
	)
)->setHelp('Set the display size lcdproc should use.');

$driverGroup = new Form_Group('Interface Traffic');
$driverGroup->add(
	new Form_Select(
		'driver',
		'Driver',
		$pconfig['driver'], // Initial value.
		[
			'bayrad'       => 'bayrad',
			'CFontz'       => 'CrystalFontz',
			'CFontz633'    => 'CrystalFontz 633',
			'CFontzPacket' => 'CrystalFontz Packet',
			'curses'       => 'curses',
			'CwLnx'        => 'CwLnx',
			'ea65'         => 'ea65',
			'EyeboxOne'    => 'EyeboxOne',
			'glk'          => 'glk',
			'hd44780'      => 'HD44780 and compatible',
			'icp_a106'     => 'icp_a106',
			'IOWarrior'    => 'IOWarrior',
			'lb216'        => 'lb216',
			'lcdm001'      => 'lcdm001',
			'lcterm'       => 'lcterm',
			'MD8800'       => 'MD8800',
			'ms6931'       => 'ms6931',
			'mtc_s16209x'  => 'mtc_s16209x',
			'MtxOrb'       => 'MtxOrb',
			'nexcom'       => 'nexcom (x86 only)',
			'NoritakeVFD'  => 'NoritakeVFD',
			'picolcd'      => 'picolcd',
			'pyramid'      => 'pyramid',
			'sdeclcd'      => 'Watchguard Firebox with SDEC',
			'sed1330'      => 'sed1330',
			'sed1520'      => 'sed1520',
			'serialPOS'    => 'serialPOS',
			'serialVFD'    => 'serialVFD',
			'shuttleVFD'   => 'shuttleVFD',
			'sli'          => 'sli',
			'stv5730'      => 'stv5730',
			'SureElec'     => 'Sure Electronics',
			't6963'        => 't6963',
			'text'         => 'text',
			'tyan'         => 'tyan'
		]
	)
);

// The connection type is HD44780-specific, so is hidden by javascript (below) 
// if the HD44780 driver is not being used.
$driverGroup->add(
	new Form_Select(
		'connection_type',
		'Connection type',
		$pconfig['connection_type'], // Initial value.
		[
			'4bit'          => '4bit wiring to parallel port',
			'8bit'          => '8bit wiring to parallel port (lcdtime)',
			'winamp'        => '8bit wiring winamp style to parallel port',
			'serialLpt'     => 'Serial LPT wiring',
			'picanlcd'      => 'PIC-an-LCD serial device',
			'lcdserializer' => 'LCD serializer',
			'los-panel'     => 'LCD on serial panel device',
			'vdr-lcd'       => 'VDR LCD serial device',
			'vdr-wakeup'    => 'VDR-Wakeup module',
			'pertelian'     => 'Pertelian X2040 LCD',
			'bwctusb'       => 'BWCT USB device',
			'lcd2usb'       => 'Till Harbaum\'s LCD2USB',
			'usbtiny'       => 'Dick Streefland\'s USBtiny',
			'lis2'          => 'LIS2 from VLSystem',
			'mplay'         => 'MPlay Blast from VLSystem',
			'ftdi'          => 'LCD connected to FTDI 2232D USB chip',
			'usblcd'        => 'USBLCD adapter from Adams IT Services',
			'i2c'           => 'LCD driven by PCF8574/PCA9554 connected via i2c'
		]
	)
);
$driverGroup->setHelp('Set the LCD driver LCDproc should use.<br />If using a HD44780 driver, set the connection type using the second selection box, which will appear.');
$section->add($driverGroup);
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
		hideGroupInput('connection_type', !using_HD44780_driver);
		
		// Hide the Output-LEDs checkbox when not using the CFontz633 or CFontzPacket driver
		var driverSupportsLEDs  = driverName_lowercase.indexOf("cfontz633") >= 0;
		driverSupportsLEDs     |= driverName_lowercase.indexOf("cfontzpacket") >= 0;
		hideCheckbox('outputleds', !driverSupportsLEDs);		
	}
//]]>
</script>

<?php

$section->addInput(
	new Form_Select(
		'port_speed',
		'Port speed',
		$pconfig['port_speed'], // Initial value.
		[
			'0'      => 'Default',
			'1200'   => '1200 bps',
			'2400'   => '2400 bps',
			'9600'   => '9600 bps',
			'19200'  => '19200 bps',
			'57600'  => '57600 bps',
			'115200' => '115200 bps'
		]
	)
)->setHelp('Set the port speed.<br />Caution: not all the driver or panels support all the speeds, leave "default" if unsure.');

/********* New section *********/
$form->add($section); 
$section = new Form_Section('Display preferences');
/********* New section *********/

$section->addInput(
	new Form_Select(
		'refresh_frequency',
		'Refresh frequency',
		$pconfig['refresh_frequency'], // Initial value.
		[
			'1'  => '1 second',
			'2'  => '2 seconds',
			'3'  => '3 seconds',
			'5'  => '5 seconds',
			'10' => '10 seconds',
			'15' => '15 seconds'
		]
	)
)->setHelp('Set the duration for which each info screen will be displayed.');

// The connection type is CFontz633/CFontzPacket-specific, so is hidden by javascript (above) 
// if a CFontz633/CFontzPacket driver is not being used.
$section->addInput(
	new Form_Checkbox(
		'outputleds', // checkbox name (id)
		'Enable output LEDs', // checkbox label
		'Enable the output LEDs present on some LCD panels.', // checkbox text
		$pconfig['outputleds'] // checkbox initial value
	)
)->setHelp(
	'This feature is currently supported by the CFontz633 driver only.<br />' .
	'Each LED can be off or show two colors: RED (alarm) or GREEN (everything ok) and shows:<br />' .
	'LED1: NICs status (green: ok, red: at least one nic down)<br />' .
	'LED2: CARP status (green: master, red: backup, off: CARP not implemented)<br />' .
	'LED3: CPU status (green &lt; 50%, red &gt; 50%)<br />' .
	'LED4: Gateway status (green: ok, red: at least one gateway not responding, off: no gateway configured).'
);


$section->addInput(
	new Form_Select(
		'brightness',
		'Brightness',
		$pconfig['brightness'], // Initial value.
		[
			'-1' => 'Default',
			'0'  => '0%',
			'10' => '10%',
			'20' => '20%',
			'30' => '30%',
			'40' => '40%',
			'50' => '50%',
			'60' => '60%',
			'70' => '70%',
			'80' => '80%',
			'90' => '90%',
			'100' => '100%'
		]
	)
)->setHelp('Set the brightness of the LCD panel.<br />This option is not supported by all the LCD panels, leave "default" if unsure.');

$section->addInput(
	new Form_Select(
		'contrast',
		'Contrast',
		$pconfig['contrast'], // Initial value.
		[
			'-1' => 'Default',
			'0'  => '0%',
			'10' => '10%',
			'20' => '20%',
			'30' => '30%',
			'40' => '40%',
			'50' => '50%',
			'60' => '60%',
			'70' => '70%',
			'80' => '80%',
			'90' => '90%',
			'100' => '100%'
		]
	)
)->setHelp(
	'Set the contrast of the LCD panel.<br />' .
	'This option is not supported by all the LCD panels, leave "default" if unsure.'
);

$section->addInput(
	new Form_Select(
		'backlight',
		'Backlight',
		$pconfig['backlight'], // Initial value.
		[
			'default' => 'Default',
			'on'      => 'On',
			'off'     => 'Off'
		]
	)
)->setHelp(
	'Set the backlight setting. If set to the default value, then the backlight setting of the display can be influenced by the clients.<br />' .
	'This option is not supported by all the LCD panels, leave "default" if unsure.'
);

$section->addInput(
	new Form_Select(
		'offbrightness',
		'Off brightness',
		$pconfig['offbrightness'], // Initial value.
		[
			'-1' => 'Default',
			'0'  => '0%',
			'10' => '10%',
			'20' => '20%',
			'30' => '30%',
			'40' => '40%',
			'50' => '50%',
			'60' => '60%',
			'70' => '70%',
			'80' => '80%',
			'90' => '90%',
			'100' => '100%'
		]
	)
)->setHelp(
	'Set the off-brightness of the LCD panel. This value is used when the display is normally switched off in case LCDd is inactive.<br />' .
	'This option is not supported by all the LCD panels, leave "default" if unsure.'
);

$form->add($section); // Add the section to our form
print($form); // Finally . . We can display our new form

?>

<div class="infoblock">
	<?=print_info_box('For more information see: <a href="http://lcdproc.org/docs.php3">LCDproc documentation</a>.', info)?>
</div>

<?php include("foot.inc"); ?>
