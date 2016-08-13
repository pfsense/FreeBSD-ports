<?php
/*
	lcdproc.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2008 Mark J Crane
	Copyright (C) 2007-2009 Seth Mos <seth.mos@dds.nl>
	Copyright (C) 2009 Scott Ullrich
	Copyright (C) 2011 Michele Di Maria
	Copyright (C) 2016 ESF, LLC
	All rights reserved.

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
$section = new Form_Section('LCD Options');

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
		'Display Size',
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

$section->addInput(
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
			'hd44780'      => 'hd44780',
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
)->setHelp('Set the LCD driver LCDproc should use.');

$section->addInput(
	new Form_Select(
		'connection_type',
		'Connection Type',
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
)->setHelp('Set connection type for the HD44780 driver');

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
)->setHelp('Set the refresh frequency of the information on the LCD Panel');

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
	new Form_Checkbox(
		'outputleds', // checkbox name (id)
		'Enable Output LEDs', // checkbox label
		'Enable the Output LEDs present on some LCD panels.', // checkbox text
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


$form->add($section); // Add the section to our form
print($form); // Finally . . We can display our new form

?>

<div class="infoblock">
	<?=print_info_box('For more information see: <a href="http://lcdproc.org/docs.php3">LCDproc documentation</a>.', info)?>
</div>

<?php include("foot.inc"); ?>
