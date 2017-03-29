<?php
/*
 * netgate_coreboot_upgrade.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2012-2015 Rubicon Communications, LLC (Netgate)
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
##|*IDENT=page-system-netgate-coreboot-upgrade
##|*NAME=System: Netgate Coreboot Upgrade
##|*DESCR=Allow access to the 'System: Netgate Coreboot Upgrade' page.
##|*MATCH=netgate_coreboot_upgrade.php*
##|-PRIV

require_once("guiconfig.inc");
require_once("system.inc");
require_once("netgate_coreboot_upgrade.inc");

$input_errors = array();
if (is_netgate_hw()) {
	$current = get_current_coreboot_details();

	if (empty($current['version'])) {
		$current['version'] = gettext('Unknown');
	}

	$new = get_new_coreboot_details();

	if (empty($new['version'])) {
		$input_errors[] = gettext(
		    "Unable to determine latest coreboot version");
	}

	if (empty($new['rom_path']) || !file_exists($new['rom_path'])) {
		$input_errors[] = sprintf(gettext(
		    "Unable to find new coreboot rom: %s"), $new['rom_path']);
	}
} else {
	$input_errors[] = gettext(
	    "This function is only available for Netgate Inc. hardware");
}

$show_log = false;
if (empty($input_errors) && isset($_POST['upgrade'])) {
	if (upgrade_coreboot($new, $adi_flash_util_output)) {
		touch("/tmp/coreupdatecomplete");
	} else {
		$input_errors[] = gettext("Coreboot update failed.");
	}

	$show_log = true;
}

$pgtitle = array(gettext("System"), gettext("Netgate Coreboot Upgrade"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

/*
 * Print success message if the update succeeded (at any time since the last
 * boot)
 */
if (file_exists("/tmp/coreupdatecomplete")) {
	$savemsg = sprintf(gettext('Coreboot was successfully upgraded! The ' .
	    'new version will take effect after %1$sreboot%2$s'),
	    '<a href="/diag_reboot.php">', '</a>');

	print_info_box($savemsg, 'success');
	if (empty($adi_flash_util_output) &&
	    file_exists("{$g['conf_path']}/netgate_coreboot_upgrade.log")) {
		$adi_flash_util_output = file_get_contents(
		    "{$g['conf_path']}/netgate_coreboot_upgrade.log");
		$show_log = true;
	}
}

/* Add warnings for SG-4860 and SG-8860 */
$platform = system_identify_specific_platform();
$model_msg = '';
if ($platform['model'] == 'SG-4860') {
	$model_msg = gettext(
	    "WARNING: This device will need to be physically rebooted " .
	    "after the firmware upgrade. Do not do this remotely if you " .
	    "can't power cycle!");

} elseif ($platform['model'] == 'SG-8860') {
	$model_msg = gettext("WARNING: This device will need to be powered " .
	    "on with the red button in the back after coreboot is upgraded. " .
	    "Do not do this remotely if you can not press this button after " .
	    "upgrade!");
}

if (!empty($model_msg)) {
	print_info_box($model_msg, 'danger', false);
}

?>
<form action="netgate_coreboot_upgrade.php" method="post" class="form-horizontal">
<?php
	if (empty($input_errors) && !file_exists("/tmp/coreupdatecomplete")):

		$section = new Form_Section("Netgate Coreboot details");

		$section->addInput(new Form_StaticText(
			'Current Coreboot Version',
			$current['version']
		));

		$section->addInput(new Form_StaticText(
			'Latest Coreboot Version',
			$new['version']
		));

		if ($new['version'] != $current['version']) {
			$section->addInput(new Form_Button(
				'upgrade',
				'Upgrade',
				null,
				'fa-check'
			))->addClass('btn-success btn-sm');
		}

		print($section);
	elseif ($show_log):
?>
		<div class="panel-heading">
			<h2 class="panel-title">
				<?=gettext("Netgate Coreboot update output")?>
			</h2>
		</div>
		<div class="panel-body">
			<pre><?=$adi_flash_util_output;?></pre>
		</div>
<?php
	endif;
?>

</form>
<?php

include("foot.inc");

?>
