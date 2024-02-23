<?php
/*
 * netgate_firmware_upgrade.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2012-2024 Rubicon Communications, LLC (Netgate)
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

##|+PRIV
##|*IDENT=page-system-netgate-firmware-upgrade
##|*NAME=System: Netgate Firmware Upgrade
##|*DESCR=Allow access to the 'System: Netgate Firmware Upgrade' page.
##|*MATCH=netgate_firmware_upgrade.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("system.inc");
require_once("netgate_firmware_upgrade.inc");

$guitimeout = 90;	// Seconds to wait before reloading the page after reboot
$guiretry = 20;		// Seconds to try again if $guitimeout was not long enough

$input_errors = array();
if (is_netgate_hw()) {
	$current = get_current_firmware_details();

	if (empty($current['version'])) {
		$current['version'] = gettext('Unknown');
	}

	$new = get_new_firmware_details();

	if (empty($new['version'])) {
		$input_errors[] = gettext(
		    "Unable to determine latest firmware version");
	}

	if (empty($new['rom_path']) || !file_exists($new['rom_path'])) {
		$input_errors[] = sprintf(gettext(
		    "Unable to find new firmware rom: %s"), $new['rom_path']);
	}
} else {
	$input_errors[] = gettext(
	    "This function is not available for this hardware model");
}

$show_log = false;
$reboot = false;
if (empty($input_errors) && isset($_POST['upgrade'])) {
	if (upgrade_firmware($new, $adi_flash_util_output)) {
		touch("/tmp/coreupdatecomplete");
		$reboot = true;
	} else {
		$input_errors[] = gettext("Firmware update failed.");
	}

	$show_log = true;
}

$pgtitle = array(gettext("System"), gettext("Netgate Firmware Upgrade"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

/*
 * Print success message if the update succeeded (at any time since the last
 * boot)
 */
if (file_exists("/tmp/coreupdatecomplete")) {
	$savemsg = gettext('Firmware was successfully upgraded! The ' .
	    'new version will take effect after reboot.');

	$platform = system_identify_specific_platform();
	if ($platform['name'] == '6100') {
		$savemsg .= '<br/><br/>';
		$savemsg .= gettext('Microcontroller updates on this platform ' .
		    'require a power cycle to activate. Wait for the automatic reboot ' .
		    'to complete then manually halt the device. Once the device ' .
		    'has shut down, unplug the power cord and plug it back in.');
	}

	print_info_box($savemsg, 'success');

	if ($reboot) {
		print('<div><pre>');
		if ($platform['name'] == 'RCC-VE') {
			mwexec('/usr/local/sbin/adi_powercycle');
			system_halt();
		} else {
			system_reboot();
		}
		print('</pre></div>');
	}

	if (empty($adi_flash_util_output) &&
	    file_exists("{$g['conf_path']}/netgate_firmware_upgrade.log")) {
		$adi_flash_util_output = file_get_contents(
		    "{$g['conf_path']}/netgate_firmware_upgrade.log");
		$show_log = true;
	}
}

if (empty($input_errors) && !file_exists("/tmp/coreupdatecomplete") &&
    ($new['version'] != $current['version'])) {
	print_info_box(gettext("WARNING: This operation requires a reboot."),
	    'warning', false);
}

?>
<form action="netgate_firmware_upgrade.php" method="post" class="form-horizontal">
<?php
	if (empty($input_errors) && !file_exists("/tmp/coreupdatecomplete")):

		$section = new Form_Section("Netgate Firmware details");

		$section->addInput(new Form_StaticText(
			'Current Firmware Version',
			$current['version']
		));

		$section->addInput(new Form_StaticText(
			'Latest Firmware Version',
			$new['version']
		));

		if (check_update($current, $new)) {
			$section->addInput(new Form_Button(
				'upgrade',
				'Upgrade and Reboot',
				null,
				'fa-solid fa-check'
			))->setAttribute("title", "Upgrade Firmware and reboot the system")->addClass('btn-danger');
		}

		print($section);
	elseif ($show_log):
?>
		<div class="panel-heading">
			<h2 class="panel-title">
				<?=gettext("Netgate Firmware update output")?>
			</h2>
		</div>
		<div class="panel-body">
			<pre><?=$adi_flash_util_output;?></pre>
		</div>

		<div id="countdown" class="text-center"></div>

		<script type="text/javascript">
		//<![CDATA[
		events.push(function() {

			var time = 0;

			function checkonline() {
				$.ajax({
					url	 : "/index.php", // or other resource
					type : "HEAD"
				})
				.done(function() {
					window.location="/index.php";
				});
			}

			function startCountdown() {
				setInterval(function() {
					if (time == "<?=$guitimeout?>") {
						$('#countdown').html('<h4><?=sprintf(gettext("Rebooting%sPage will automatically reload in %s seconds"), "<br />", "<span id=\"secs\"></span>");?></h4>');
					}

					if (time > 0) {
						$('#secs').html(time);
						time--;
					} else {
						time = "<?=$guiretry?>";
						$('#countdown').html('<h4><?=sprintf(gettext("Not yet ready%s Retrying in another %s seconds"), "<br />", "<span id=\"secs\"></span>");?></h4>');
						$('#secs').html(time);
						checkonline();
					}
				}, 1000);
			}

			time = "<?=$guitimeout?>";
			startCountdown();

		});
		//]]>
		</script>
		</div>
<?php
	endif;
?>

</form>
<?php

include("foot.inc");

?>
