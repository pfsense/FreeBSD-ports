<?php
/*
 * acme_generalsettings.php
 * 
 * part of pfSense (https://www.pfsense.org/)
 * Copyright (c) 2016 PiBa-NL
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

namespace pfsense_pkg\acme;

$shortcut_section = "acme";
include_once("guiconfig.inc");
include_once("globals.inc");
require_once("acme/acme.inc");
require_once("acme/acme_utils.inc");
require_once("acme/acme_htmllist.inc");
require_once("acme/pkg_acme_tabs.inc");

$simplefields = array('enable');

if (!is_array($config['installedpackages']['acme'])) {
	$config['installedpackages']['acme'] = array();
}
if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;
	
	if (!$input_errors) {
		foreach($simplefields as $stat) {
			$config['installedpackages']['acme'][$stat] = $_POST[$stat];
		}
		
		set_cronjob();
		write_config();
	}
}

foreach($simplefields as $stat) {
	$pconfig[$stat] = $config['installedpackages']['acme'][$stat];
}

$pgtitle = array(gettext("Services"), gettext("Acme"), gettext("Settings"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg);
}
display_top_tabs_active($acme_tab_array['acme'], "settings");

$counter = 0; // used by htmllist Draw() function.

$form = new \Form;

$section = new \Form_Section("General settings");

$section->addInput(new \Form_Checkbox(
	'enable',
	'',
	'Enable Acme client renewal job',
	$pconfig['enable']
));

$form->add($section);

print $form;

function group_input_with_text($name, $title, $type = 'text', $value = null, array $attributes = array(), $righttext = "")
{
	$group = new \Form_Group($title);
	$group->add(new \Form_Input(
		$name,
		'',
		$type,
		$value,
		$attributes
	))->setWidth(2);

	$group->add(new \Form_StaticText(
		'',
		$righttext
	));
	return $group;
}

include("foot.inc");