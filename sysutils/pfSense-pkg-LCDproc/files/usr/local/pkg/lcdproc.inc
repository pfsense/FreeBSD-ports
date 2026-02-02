<?php
/*
 * lcdproc.inc
 *
 * part of pfSense (http://www.pfsense.org/)
 * Copyright (c) 2015-2025 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2012 Michele Di Maria
 * Copyright (c) 2007-2009 Seth Mos <seth.mos@dds.nl>
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
require_once("config.inc");
require_once("functions.inc");
require_once("service-utils.inc");

/* LCDproc */
define('LCDPROC_RCFILE', '/usr/local/etc/rc.d/lcdproc.sh');
define('LCDPROC_CONFIG', '/usr/local/etc/LCDd.conf');
define('LCDPROC_HOST', '127.0.0.1');
define('LCDPROC_PORT', '13666');
define('LCDPROC_SERVICE_NAME', 'lcdproc');
define('LOG_PREFIX_PKG_LCDPROC', 'lcdproc');

/* Common Lists */

/* Log levels */
global $lcdproc_log_levels;
$lcdproc_log_levels = [
	-1 => 'Disabled',
	0 => 'Critical Errors',
	1 => 'Errors',
	2 => 'Warnings (Default)',
	3 => 'Major Events',
	4 => 'Minor Events',
	5 => 'All messages plus debugging information',
];

/* Background colors for Matrix Orbital compatible displays supporting the
 * extended command set and associated RGB hardware, such as the Adafruit
 * LCD backpack and certain add-on displays.
 */
global $mtxorb_backlight_color_list;
/* BG color command plus octal LED power values of various shades for R, G, B.
 *   - For lowest power setting, use 1, not 0.
 * Some displays have a weak Red LED backlight so also list alternate mixes
 */
$mtxorb_backlight_color_list = [
	'Default'     => '',
	'White'       => '\0376\0320\0377\0376\0375', /* Full R+G+B */
	'Gray'        => '\0376\0320\0177\0177\0177', /* Med R+G+B */
	'Gray Alt'    => '\0376\0320\0377\0040\0040', /* Full R, Low G+B */
	'Red'         => '\0376\0320\0377\0001\0001', /* Full R */
	'Green'       => '\0376\0320\0001\0377\0001', /* Full G */
	'Blue'        => '\0376\0320\0001\0001\0377', /* Full B */
	'Cyan'        => '\0376\0320\0001\0377\0377', /* Full G+B */
	'Yellow'      => '\0376\0320\0377\0377\0001', /* Full R+G */
	'Yellow Alt'  => '\0376\0320\0377\0050\0001', /* Full R, Low G */
	'Magenta'     => '\0376\0320\0377\0001\0377', /* Full R+B */
	'Magenta Alt' => '\0376\0320\0377\0001\0040', /* Full R, Low B */
	'Purple'      => '\0376\0320\0377\0001\0177', /* Full R + Med B */
];

global $comport_list;
$comport_list = [
	'none'    => 'none',
	'com1'    => 'Serial COM port 1 (/dev/cua0)',
	'com2'    => 'Serial COM port 2 (/dev/cua1)',
	'com1a'   => 'Serial COM port 1 alternate (/dev/cuau0)',
	'com2a'   => 'Serial COM port 2 alternate (/dev/cuau1)',
	'ucom1'   => 'USB COM port 1 (/dev/cuaU0)',
	'ucom2'   => 'USB COM port 2 (/dev/cuaU1)',
	'lpt1'    => 'Parallel port 1 (/dev/lpt0)',
	'ttyU0'   => 'USB COM port 1 tty (/dev/ttyU0)',
	'ttyU1'   => 'USB COM port 2 tty (/dev/ttyU1)',
	'ttyU2'   => 'USB COM port 3 tty (/dev/ttyU2)',
	'ttyU3'   => 'USB COM port 4 tty (/dev/ttyU3)',
	'ugen0.2' => 'USB COM port 1 alternate (/dev/ugen0.2)',
	'ugen1.2' => 'USB COM port 2 alternate (/dev/ugen1.2)',
	'ugen1.3' => 'USB COM port 3 alternate (/dev/ugen1.3)',
	'ugen2.2' => 'USB COM port 4 alternate (/dev/ugen2.2)'
];

global $size_list;
$size_list = [
	'12x1' => '1 rows 12 colums',
	'12x2' => '2 rows 12 colums',
	'12x4' => '4 rows 12 colums',
	'16x1' => '1 row 16 colums',
	'16x2' => '2 rows 16 colums',
	'16x4' => '4 rows 16 colums',
	'20x1' => '1 row 20 colums',
	'20x2' => '2 rows 20 colums',
	'20x4' => '4 rows 20 colums'
];

global $driver_list;
$driver_list = [
	'bayrad'       => 'bayrad',
	'CFontz'       => 'CrystalFontz',
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
	'MtxOrb'       => 'Matrix Orbital and Compatible',
	'NoritakeVFD'  => 'NoritakeVFD',
	'picolcd'      => 'picolcd',
	'pyramid'      => 'pyramid',
	'rawserial'    => 'rawserial',
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
];

global $connection_type_list;
$connection_type_list = [
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
	'i2c'           => 'LCD driven by PCF8574/PCA9554 connected via i2c',
	'ezio'          => 'Portwell EZIO-100 and EZIO-300'
];

global $mtxorb_type_list;
$mtxorb_type_list = [
	'lcd' => 'LCD (default)',
	'lkd' => 'LKD',
	'vfd' => 'VFD',
	'vkd' => 'VKD'
];

global $port_speed_list;
$port_speed_list = [
	'0'      => 'Default',
	'1200'   => '1200 bps',
	'2400'   => '2400 bps',
	'9600'   => '9600 bps',
	'19200'  => '19200 bps',
	'57600'  => '57600 bps',
	'115200' => '115200 bps'
];

global $refresh_frequency_list;
$refresh_frequency_list = [
	'1'  => '1 second',
	'2'  => '2 seconds',
	'3'  => '3 seconds',
	'5'  => '5 seconds',
	'10' => '10 seconds',
	'15' => '15 seconds'
];

global $percent_list;
$percent_list = [
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
];

global $backlight_list;
$backlight_list = [
	'default' => 'Default',
	'on'      => 'On',
	'off'     => 'Off'
];

/* Functions */
function lcdproc_validate_list(&$input_errors, $postvar, $list, $name) {
	if (!empty($_POST[$postvar]) &&
	    !array_key_exists($_POST[$postvar], $list)) {
		$input_errors[] = "Invalid {$name}.";
	}
}

function lcdproc_notice($msg) {
	logger(LOG_NOTICE, $msg, LOG_PREFIX_PKG_LCDPROC);
}

function lcdproc_warn($msg) {
	logger(LOG_WARNING, $msg, LOG_PREFIX_PKG_LCDPROC);
}

function lcdproc_write_config($file, $text) {
	$handle = fopen($file, 'w');
	if (!$handle) {
		lcdproc_warn("Could not open {$file} for writing.");
		exit;
	}
	fwrite($handle, $text);
	fclose($handle);
}

function validate_form_lcdproc($post, &$input_errors) {
	if ($post['comport']) {
		switch($post['comport']) {
			case "none":
			case "com1":
			case "com2":
			case "com1a":
			case "com2a":
			case "ucom1":
			case "ucom2":
			case "lpt1":
			case "ttyU0":
			case "ttyU1":
			case "ttyU2":
			case "ttyU3":
			case "ugen0.2":
			case "ugen1.2":
			case "ugen1.3":
			case "ugen2.2":
				break;
			default:
				$input_errors[] = "The chosen COM port is not valid";
				break;
		}
	}
	if ($post['size']) {
		switch($post['size']) {
			case "12x1":
			case "12x2":
			case "12x4":
			case "16x1":
			case "16x2":
			case "16x4":
			case "20x1":
			case "20x2":
			case "20x4":
			case "40x2":
				break;
			default:
				$input_errors[] = "The chosen display size is not valid";
				break;
		}
	}
	if ($post['port_speed']) {
		switch($post['port_speed']) {
			case "0":
			case "1200":
			case "2400":
			case "9600":
			case "19200":
			case "57600":
			case "115200":
				break;
			default:
				$input_errors[] = "The chosen port speed is not valid";
				break;
		}
	}
}

function sync_package_lcdproc_screens() {
	sync_package_lcdproc();
}

function sync_package_lcdproc() {
	global $g, $input_errors, $lcdproc_log_levels, $mtxorb_backlight_color_list;

	# Detect boot process
	if (platform_booting()) {
		return;
	}

	# Continue sync package
	lcdproc_notice("Sync: Begin package sync");
	$lcdproc_config         = config_get_path('installedpackages/lcdproc/config/0', []);
	$lcdproc_screens_config = config_get_path('installedpackages/lcdprocscreens/config/0', []);

	/* Since config is written before this file invoked, we don't need to read post data */
	if ($lcdproc_config['enable'] && ($lcdproc_config['comport'] != "none")) {
		switch ($lcdproc_config['comport']) {
			case "com1":
				$realport = "/dev/cua0";
				break;
			case "com2":
				$realport = "/dev/cua1";
				break;
			case "com1a":
				$realport = "/dev/cuau0";
				break;
			case "com2a":
				$realport = "/dev/cuau1";
				break;
			case "ucom1":
				$realport = "/dev/cuaU0";
				break;
			case "ucom2":
				$realport = "/dev/cuaU1";
				break;
			case "lpt1":
				$realport = "/dev/lpt0";
				break;
			case "ttyU0":
				$realport = "/dev/ttyU0";
				break;
			case "ttyU1":
				$realport = "/dev/ttyU1";
				break;
			case "ttyU2":
				$realport = "/dev/ttyU2";
				break;
			case "ttyU3":
				$realport = "/dev/ttyU3";
				break;
			case "ugen0.2":
				$realport = "/dev/ugen0.2";
				break;
			case "ugen1.2":
				$realport = "/dev/ugen1.2";
				break;
			case "ugen1.3":
				$realport = "/dev/ugen1.3";
				break;
			case "ugen2.2":
				$realport = "/dev/ugen2.2";
				break;
			default:
				lcdproc_warn("The selected COM port is not valid!");
				return;
		}

		$config_text = "[server]\n";
		$config_text .= "DriverPath=/usr/local/lib/lcdproc/\n";
		$config_text .= "Driver={$lcdproc_config['driver']}\n";
		$config_text .= "Bind=127.0.0.1\n";
		$config_text .= "Port=13666\n";

		if (is_null($lcdproc_config['log_level']) ||
		    !array_key_exists($lcdproc_config['log_level'], $lcdproc_log_levels)) {
			$lcdproc_config['log_level'] = 2;
		}
		if ((int) $lcdproc_config['log_level'] < 0) {
			$config_text .= "ReportToSyslog=no\n";
		} else {
			$config_text .= "ReportLevel={$lcdproc_config['log_level']}\n";
			$config_text .= "ReportToSyslog=yes\n";
		}

		$config_text .= "User=nobody\n";
		$config_text .= "Foreground=no\n";
		$config_text .= "ServerScreen=no\n";
		$config_text .= "GoodBye=\"Thanks for using\"\n";
		$config_text .= "GoodBye=\"    {$g['product_name']}     \"\n";
		$config_text .= "WaitTime={$lcdproc_config['refresh_frequency']}\n";
		if ($lcdproc_config['backlight'] != "" && $lcdproc_config['backlight'] != "default") {
			/* Backlight setting */
			$config_text .= "Backlight={$lcdproc_config['backlight']}\n";
		}
		if ($lcdproc_config['driver'] == "sdeclcd") {
			/* Sdeclcd Keys settings */
			$config_text .= "PrevScreenKey=Down\n";
			$config_text .= "NextScreenKey=Up\n";
			/* Sdeclcd Menu settings */
			$config_text .= "[menu]\n";
			$config_text .= "MenuKey=Left\n";
			$config_text .= "EnterKey=Right\n";
			$config_text .= "UpKey=Up\n";
			$config_text .= "DownKey=Down\n";
		} else {
			/* Generic Keys settings */
			$config_text .= "ToggleRotateKey=Enter\n";
			$config_text .= "PrevScreenKey=Left\n";
			$config_text .= "NextScreenKey=Right\n";
			$config_text .= "ScrollUpKey=Up\n";
			$config_text .= "ScrollDownKey=Down\n";
			/* Generic Menu settings */
			$config_text .= "[menu]\n";
			$config_text .= "MenuKey=Escape\n";
			$config_text .= "EnterKey=Enter\n";
			$config_text .= "UpKey=Up\n";
			$config_text .= "DownKey=Down\n";
		}

		/* lcdproc default driver definitions */
		switch($lcdproc_config['driver']) {
			case "bayrad":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				break;
			case "CFontz":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				$config_text .= set_lcd_value("contrast", 1000, 350);
				$config_text .= set_lcd_value("brightness", 1000, 1000);
				$config_text .= set_lcd_value("offbrightness", 1000, 50);
				$config_text .= "NewFirmware=no\n";
				$config_text .= "Reboot=no\n";
				break;
			case "CFontzPacket":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Model=635\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				$config_text .= set_lcd_value("contrast", 1000, 350);
				$config_text .= set_lcd_value("brightness", 1000, 1000);
				$config_text .= set_lcd_value("offbrightness", 1000, 50);
				$config_text .= "NewFirmware=yes\n";
				$config_text .= "Reboot=yes\n";
				break;
			case "curses":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Foreground=blue\n";
				$config_text .= "Background=cyan\n";
				$config_text .= "Backlight=red\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				$config_text .= "TopLeftX=7\n";
				$config_text .= "TopLeftY=7\n";
				$config_text .= "UseACS=no\n";
				break;
			case "CwLnx":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Model=12232\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				$config_text .= "Reboot=no\n";
				break;
			case "ea65":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "OffBrightness=0\n";
				$config_text .= set_lcd_value("brightness", 1000, 500);
				break;
			case "EyeboxOne":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Backlight=yes\n";
				break;
			case "glk":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= set_lcd_value("contrast", 1000, 350);
				break;
			case "hd44780":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "driverpath=/usr/local/lib/lcdproc/\n";
				$config_text .= "ConnectionType={$lcdproc_config['connection_type']}\n";
				if ($lcdproc_config['comport'] != "none" && $lcdproc_config['connection_type'] != "ezio") {
					$config_text .= "Device={$realport}\n";
					$config_text .= "Port=0x378\n";
					$config_text .= "Speed=0\n";
					$config_text .= set_lcd_value("contrast", 1000, 850);
					$config_text .= set_lcd_value("brightness", 1000, 800);
					$config_text .= set_lcd_value("offbrightness", 1000, 0);
					$config_text .= "Backlight=yes\n";
					$config_text .= "OutputPort=no\n";
					$config_text .= "Charmap=hd44780_default\n";
					$config_text .= "DelayBus=true\n";
					$config_text .= "Size={$lcdproc_config['size']}\n";
					if ($lcdproc_config['connection_type'] = "pertelian") {
						$config_text .= "DelayMult=32\n";
						$config_text .= "Keypad=no\n";
					} else {
						$config_text .= "DelayMult=1\n";
						$config_text .= "Keypad=yes\n";
					}
				}
				if ($lcdproc_config['connection_type'] == "winamp") {
					$config_text .= "KeyDirect_1=Enter\n";
					$config_text .= "KeyDirect_2=Up\n";
					$config_text .= "KeyDirect_3=Down\n";
					$config_text .= "KeyDirect_4=Escape\n";
				}
				if ($lcdproc_config['connection_type'] == "ezio") {
					$config_text .= "Device={$realport}\n";
					$config_text .= "Charmap=hd44780_default\n";
					$config_text .= "Size=16x2\n";
					$config_text .= "Backlight=no\n";
					$config_text .= "Keypad=yes\n";
					$config_text .= "KeyMatrix_4_1=Enter\n";
					$config_text .= "KeyMatrix_4_2=Up\n";
					$config_text .= "KeyMatrix_4_3=Down\n";
					$config_text .= "KeyMatrix_4_4=Escape\n";
				}
				break;
			case "icp_a106":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				break;
			case "IOWarrior":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				break;
			case "lb216":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= set_lcd_value("brightness", 255, 255);
				$config_text .= "Reboot=no\n";
				break;
			case "lcdm001":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				break;
			case "lcterm":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				break;
			case "MD8800":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				$config_text .= set_lcd_value("brightness", 1000, 1000);
				$config_text .= set_lcd_value("offbrightness", 1000, 0);
				break;
			case "ms6931":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Reboot=no\n";
				break;
			case "mtc_s16209x":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= set_lcd_value("brightness", 255, 255);
				$config_text .= "Reboot=no\n";
				break;
			case "MtxOrb":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				$config_text .= "Type={$lcdproc_config['mtxorb_type']}\n";
				$config_text .= "hasAdjustableBacklight=" . ($lcdproc_config['mtxorb_adjustable_backlight'] ? "yes" : "no") . "\n";
				$config_text .= "Reboot=no\n";
				$config_text .= set_lcd_value("contrast", 1000, 480);
				$config_text .= set_lcd_value("brightness", 1000, 1000);
				$config_text .= set_lcd_value("offbrightness", 1000, 0);
				break;
			case "nexcom":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "driverpath =/usr/local/lib/lcdproc/\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				break;
			case "NoritakeVFD":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				$config_text .= set_lcd_value("brightness", 1000, 1000);
				$config_text .= set_lcd_value("offbrightness", 1000, 0);
				$config_text .= "Parity=0\n";
				$config_text .= "Reboot=no\n";
				break;
			case "picolcd":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "driverpath=/usr/local/lib/lcdproc/\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				$config_text .= "KeyTimeout=500\n";
				$config_text .= set_lcd_value("contrast", 1000, 1000);
				$config_text .= set_lcd_value("brightness", 1000, 1000);
				$config_text .= set_lcd_value("offbrightness", 1000, 0);
				$config_text .= "Blacklight_Timer=60\n";
				$config_text .= "Keylights=on\n";
				$config_text .= "Key0Light=on\n";
				$config_text .= "Key1Light=off\n";
				$config_text .= "Key2Light=off\n";
				$config_text .= "Key3Light=off\n";
				$config_text .= "Key4Light=off\n";
				$config_text .= "Key5Light=off\n";
				break;
			case "pyramid":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				break;
			case "rawserial":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				$config_text .= "UpdateRate=1\n";
				break;
			case "sdeclcd":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				break;
			case "sed1330":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				break;
			case "sed1520":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				break;
			case "serialPOS":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				$config_text .= "Type=AEDEX\n";
				break;
			case "serialVFD":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "use_parallel=no\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				$config_text .= "Type=0\n"; // Just the first
				$config_text .= set_lcd_value("brightness", 1000, 1000);
				$config_text .= set_lcd_value("offbrightness", 1000, 0);
				$config_text .= "ISO_8859_1=yes\n";
				break;
			case "shuttleVFD":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				break;
			case "SureElec":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "driverpath =/usr/local/lib/lcdproc/\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				$config_text .= "Edition=2\n";
				$config_text .= set_lcd_value("contrast", 1000, 200);
				$config_text .= set_lcd_value("brightness", 1000, 480);
				break;
			case "sli":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				break;
			default:
				lcdproc_warn("The chosen lcdproc driver is not a valid choice");
				unset($lcdproc_config['driver']);
		}

		/* Port speed */
		if ($lcdproc_config['port_speed'] <> '' && $lcdproc_config['port_speed'] <> '0') {
			$port_speed = $lcdproc_config['port_speed'];
			$config_text .= "Speed={$port_speed}\n";
		}

		/* Generate rc file start and stop */
		$stop = <<<EOD

	if [ `pgrep -fo lcdproc_client.php` ];then
			pkill -f lcdproc_client.php
			sleep 1
	fi
	if  [ `pgrep -anx LCDd` ]; then
		pkill -anx LCDd
		sleep 1
	fi

EOD;
		$start = $stop ."\n";

		if (!empty($lcdproc_config['mtxorb_backlight_color']) &&
		    array_key_exists($lcdproc_config['mtxorb_backlight_color'], $mtxorb_backlight_color_list) &&
		    !empty($mtxorb_backlight_color_list[$lcdproc_config['mtxorb_backlight_color']])) {
			$start .= "\t# Initialize display\n";
			$start .= "\t/usr/bin/printf \"%b\" ";
			$start .= escapeshellarg($mtxorb_backlight_color_list[$lcdproc_config['mtxorb_backlight_color']]);
			$start .= " > " . escapeshellarg($realport) . "\n";
		}

		$start .= "\t# Start LCDProc daemon and client\n";
		$start .= "\t/usr/bin/nice -20 /usr/local/sbin/LCDd -c ". LCDPROC_CONFIG ." -u nobody\n";
		$start .= "\t/usr/bin/nice -20 /usr/local/bin/php -f /usr/local/pkg/lcdproc_client.php &\n";

		/* Write out the configuration */
		lcdproc_write_config(LCDPROC_CONFIG, $config_text);
		write_rcfile(array(
				'file' => 'lcdproc.sh',
				'start' => $start,
				'stop' => $stop
				));
		/* Or restart lcdproc if settings were changed*/
		if (is_service_running(LCDPROC_SERVICE_NAME)) {
			lcdproc_notice("Sync: Restarting the service");
			restart_service(LCDPROC_SERVICE_NAME);
		} else {
			start_service(LCDPROC_SERVICE_NAME);
		}
	}

	if (!$lcdproc_config['enable'] || !$lcdproc_config['driver'] || !$lcdproc_config['comport']) {
		/* No parameters - user does not want lcdproc running */
		/* Let's stop the service and remove the rc file */
		if (file_exists(LCDPROC_RCFILE)) {
			if (!$lcdproc_config['enable']) {
				lcdproc_notice('Stopping service: lcdproc disabled');
			} else {
				lcdproc_notice('Stopping service: no COM port selected');
			}
			stop_service(LCDPROC_SERVICE_NAME);
			unlink(LCDPROC_RCFILE);
			unlink(LCDPROC_CONFIG);
		}
	}
	lcdproc_notice("Sync: End package sync");
}

function set_lcd_value($fieldname, $max, $default_value) {
	$value = config_get_path("installedpackages/lcdproc/config/0/{$fieldname}");
	$returnvalue = "";
	if ($value != '' && $value != '-1') {
		$realvalue = (int)($max * $value / 100);
	} else {
		$realvalue = $default_value;
	}
	switch ($fieldname) {
		case "brightness":
			$returnvalue = "Brightness={$realvalue}\n";
			break;
		case "offbrightness":
			$returnvalue = "OffBrightness={$realvalue}\n";
			break;
		case "contrast":
			$returnvalue = "Contrast={$realvalue}\n";
			break;
	}
	return $returnvalue;
}
