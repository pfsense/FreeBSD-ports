<?php
/*
	lcdproc.inc
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2007-2009 Seth Mos <seth.mos@dds.nl>
	Copyright (C) 2009 Scott Ullrich
	Copyright (C) 2012 Michele Di Maria
	Copyright (C) 2015 ESF, LLC
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
require_once("config.inc");
require_once("functions.inc");
require_once("service-utils.inc");

/* LCDproc */
define('LCDPROC_RCFILE', '/usr/local/etc/rc.d/lcdproc.sh');
$pfs_version = substr(trim(file_get_contents("/etc/version")), 0, 3);
if ($pfs_version == "2.1" || $pfs_version == "2.2") {
	define('LCDPROC_CONFIG', '/usr/pbi/lcdproc-' . php_uname('m') . '/local/etc/LCDd.conf');
} else {
	define('LCDPROC_CONFIG', '/usr/local/etc/LCDd.conf');
}
define('LCDPROC_HOST', '127.0.0.1');
define('LCDPROC_PORT', '13666');
define('LCDPROC_SERVICE_NAME', 'lcdproc');

/* Functions */
function lcdproc_notice($msg) {
	syslog(LOG_NOTICE, "lcdproc: {$msg}");
}

function lcdproc_warn($msg) {
	syslog(LOG_WARNING, "lcdproc: {$msg}");
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

function lcdproc_write_script($file, $text) {
	$handle = fopen($file, 'wx');
	if (!$handle) {
		lcdproc_warn("Could not open {$file} for writing.");
		exit;
	}
	fwrite($handle, $text);
	fclose($handle);
	chmod($file, 0755);
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
			case "ugen0.2":
			case "ugen1.2":
			case "ugen1.3";
			case "ugen2.2":
				continue;
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
				continue;
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
				continue;
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
	global $g, $config, $input_errors;

	# Detect boot process
	if (function_exists("platform_booting")) {
		if (platform_booting()) {
			return;
		}
	} elseif ($g['booting']) {
		return;
	}

	# Continue sync package
	lcdproc_notice("Sync: Begin package sync");
	$lcdproc_config = $config['installedpackages']['lcdproc']['config'][0];
	$lcdproc_screens_config = $config['installedpackages']['lcdprocscreens']['config'][0];

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
		$config_text .= "Driver={$lcdproc_config[driver]}\n";
		$config_text .= "Bind=127.0.0.1\n";
		$config_text .= "Port=13666\n";
		$config_text .= "ReportLevel=3\n";
		$config_text .= "ReportToSyslog=yes\n";
		$config_text .= "User=nobody\n";
		$config_text .= "Foreground=no\n";
		$config_text .= "ServerScreen=no\n";
		$config_text .= "GoodBye=\"Thanks for using\"\n";
		$config_text .= "GoodBye=\"    {$g['product_name']}     \"\n";
		$config_text .= "WaitTime={$lcdproc_config[refresh_frequency]}\n";
		if ($lcdproc_config[backlight] != "" && $lcdproc_config[backlight] != "default") {
			/* Backlight setting */
			$config_text .= "Backlight={$lcdproc_config[backlight]}\n";
		}
		if ($lcdproc_config[driver] == "sdeclcd") {
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
		switch($lcdproc_config[driver]) {
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
			case "CFontz633":
				$config_text .= "[{$lcdproc_config['driver']}]\n";
				$config_text .= "Device={$realport}\n";
				$config_text .= "Size={$lcdproc_config['size']}\n";
				$config_text .= set_lcd_value("contrast", 1000, 350);
				$config_text .= set_lcd_value("brightness", 1000, 1000);
				$config_text .= set_lcd_value("offbrightness", 1000, 50);
				$config_text .= "NewFirmware=yes\n";
				$config_text .= "Reboot=yes\n";
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
				if ($lcdproc_config['comport'] != "none") {
					$config_text .= "Device={$realport}\n";
					$config_text .= "Port=0x378\n";
					$config_text .= "Speed=0\n";
					$config_text .= "Keypad=yes\n";
					$config_text .= set_lcd_value("contrast", 1000, 850);
					$config_text .= set_lcd_value("brightness", 1000, 800);
					$config_text .= set_lcd_value("offbrightness", 1000, 0);
					$config_text .= "Backlight=yes\n";
					$config_text .= "OutputPort=no\n";
					$config_text .= "Charmap=hd44780_default\n";
					$config_text .= "DelayMult=1\n";
					$config_text .= "DelayBus=true\n";
					$config_text .= "Size={$lcdproc_config['size']}\n";
				}
				if ($lcdproc_config[connection_type] == "winamp") {
					$config_text .= "KeyDirect_1=Enter\n";
					$config_text .= "KeyDirect_2=Up\n";
					$config_text .= "KeyDirect_3=Down\n";
					$config_text .= "KeyDirect_4=Escape\n";
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
				$config_text .= "Type=lcd\n";
				$config_text .= "hasAdjustableBacklight=yes\n";
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
				unset($lcdproc_config[driver]);
		}

		/* Port speed */
		if ($lcdproc_config[port_speed] <> '' && $lcdproc_config[port_speed] <> '0') {
			$port_speed = $lcdproc_config[port_speed];
			$config_text .= "Speed={$port_speed}\n";
		}

		/* Generate rc file start and stop */
		$stop = <<<EOD
if [ `pgrep -f lcdproc_client.ph` ];then
		pkill -f lcdproc_client.ph
		sleep 1
fi
if  [ `pgrep -anx LCDd` ]; then
	pkill -anx LCDd 
	sleep 1
fi

EOD;
		$start = $stop ."\n";
		$start .= "\t/usr/bin/nice -20 /usr/local/sbin/LCDd -c ". LCDPROC_CONFIG ." -u nobody\n";
		$start .= "\t/usr/bin/nice -20 /usr/local/bin/php -f /usr/local/pkg/lcdproc_client.php &\n";

		/* Write out the configuration */
		conf_mount_rw();
		lcdproc_write_config(LCDPROC_CONFIG, $config_text);
		write_rcfile(array(
				'file' => 'lcdproc.sh',
				'start' => $start,
				'stop' => $stop
				));
		conf_mount_ro();
		/* Or restart lcdproc if settings were changed*/
		if (is_service_running(LCDPROC_SERVICE_NAME)) {
			lcdproc_notice("Sync: Restarting the service");
			restart_service(LCDPROC_SERVICE_NAME);
		}
	}

	if ((!$lcdproc_config['driver']) || (!$lcdproc_config['comport'])) {
		/* No parameters - user does not want lcdproc running */
		/* Let's stop the service and remove the rc file */
		if (file_exists(LCDPROC_RCFILE)) {
			if (!$lcdproc_config['enable']) {
				lcdproc_notice('Stopping service: lcdproc disabled');
			} else {
				lcdproc_notice('Stopping service: no COM port selected');
			}
			stop_service(LCDPROC_SERVICE_NAME);
			conf_mount_rw();
			unlink(LCDPROC_RCFILE);
			unlink(LCDPROC_CONFIG);
			conf_mount_ro();
		}
	}
	lcdproc_notice("Sync: End package sync");
}

function set_lcd_value($fieldname, $max, $default_value) {
	global $config;
	$lcdproc_config = $config['installedpackages']['lcdproc']['config'][0];
	$value = $lcdproc_config[$fieldname];
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

?>
