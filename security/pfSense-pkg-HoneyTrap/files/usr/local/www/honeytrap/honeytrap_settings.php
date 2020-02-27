<?php
/*
 * honeytrap_settings.php
 *
 * part of pfSense (http://www.pfsense.org)
 * Copyright (c) 2019 DutchSec (https://dutchsec.com/)
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

require_once('guiconfig.inc');
require_once('globals.inc');

require_once('honeytrap/honeytrap-plugin.inc');

init_config_arr(array('installedpackages', 'honeytrap', 'config', 0));
$gconfig = &$config['installedpackages']['honeytrap']['config'][0];

if ($_POST && isset($_POST['save'])) {
	if (isset($_POST['enable']) && $_POST['enable'] == 'on') {
		$gconfig['enable'] = $_POST['enable'];
	} else {
		unset($gconfig['enable']);
	}

	if (isset($_POST['keep']) && $_POST['keep'] == 'on') {
		$gconfig['keep'] = $_POST['keep'];
	} else {
		unset($gconfig['keep']);
	}

	if (isset($_POST['truncate']) && $_POST['truncate'] == 'on') {
		$gconfig['truncate'] = $_POST['truncate'];
	} else {
		unset($gconfig['truncate']);
	}

	if (isset($_POST['config_file'])) {
		$part_parts = pathinfo($_POST['config_file']);
		if ($part_parts['extension'] != 'toml') {
			$input_errors[] = "Config file must be a TOML file.\nCurrent extension is {$part_parts['extension']}";
		} elseif (!realpath($_POST['config_file'])) {
			$input_errors[] = 'File can\'t be found.';
		} else {
			$gconfig['config_file'] = $_POST['config_file'];
		}
	}

	if (!$input_errors) {
		$savemsg = 'Successfully modified settings.';
		write_config($savemsg);
		honeytrap_sync_config();
	}
}

$pgtitle = array(gettext("Service"), gettext("HoneyTrap"));
$shortcut_section = 'honeytrap';
include_once('head.inc');

if (isset($input_errors)) {
	print_input_errors($input_errors);
}

if (isset($savemsg)) {
	print_info_box($savemsg, 'success');
}

$form = new Form();

$section = new Form_Section('HoneyTrap General Settings');
$section->addInput(new Form_Checkbox(
	'enable',
	'Enable',
	'Enable HoneyTrap',
	$gconfig['enable'] === 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'keep',
	'Keep',
	'Keep settings on install, upgrade or deinstall',
	$gconfig['keep'] === 'on' ? true:false,
	'on'
));
$form->add($section);

$section = new Form_Section('Service Settings');
$section->addInput(new Form_Checkbox(
	'truncate',
	'Truncate',
	'Truncate service output logs on service start',
	$gconfig['truncate'] === 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Input(
	'config_file',
	'Config file path',
	'text',
	$gconfig['config_file']
));

$form->add($section);
print($form);
include('foot.inc');
?>
