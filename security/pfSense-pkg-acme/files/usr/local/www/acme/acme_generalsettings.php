<?php
/*
	acme_generalsettings.php
	part of pfSense (https://www.pfsense.org/)
	Copyright (C) 2016 PiBa-NL
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

$action = $_GET[action];
if ($action == "createaccountkey") {
	createAcmeAccountKey();
}
elseif ($action == "registeraccountkey") {
	registerAcmeAccountKey();
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