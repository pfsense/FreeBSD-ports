<?php
/*
 * suricata_os_policy_engine.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2023 Bill Meeks
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

/**************************************************************************************
	This file contains code for adding/editing an existing Host OS Policy Engine.
	It is included and injected inline as needed into the suricata_stream_flow.php
	page to provide the edit functionality for Host OS Policy Engines.

	The following variables are assumed to exist and must be initialized
	as necessary in order to utilize this page.

	$g --> system global variables array
	$config --> global variable pointing to configuration information
	$pengcfg --> array containing current Host OS Policy engine configuration

	Information is returned from this page via the following form fields:

	policy_name --> Unique Name for the Host OS Policy Engine
	policy_bind_to --> Alias name representing "bind_to" IP address for engine
	policy --> Operating system chosen for engine policy
	select_alias --> Submit button for select alias operation
	save_os_policy --> Submit button for save operation and exit
	cancel_os_policy --> Submit button to cancel operation and exit
 **************************************************************************************/

$form->addGlobal(new Form_Input(
	'id',
	'id',
	'hidden',
	$id
));
$form->addGlobal(new Form_Input(
	'eng_id',
	'eng_id',
	'hidden',
	$eng_id
));

$section = new Form_Section('Suricata Target-Based Host OS Policy Engine Configuration');
$section->addInput(new Form_Input(
	'policy_name',
	'Policy Name',
	'text',
	$pengcfg['name']
))->setHelp('Name or description for this engine. (Max 25 characters). Unique name or description for this engine configuration. Default value is default.');

if ($pengcfg['name'] <> "default") {
	$bind_to = new Form_Input(
		'policy_bind_to',
		'',
		'text',
		$pengcfg['bind_to']
	);
	$bind_to->setAttribute('title', trim(filter_expand_alias($pconfig['bind_to'])));
	$bind_to->setHelp('IP List to bind this engine to. (Cannot be blank)');
	$btnaliases = new Form_Button(
		'select_alias',
		'Aliases',
		null,
		'fa-solid fa-search-plus'
	);
	$btnaliases->removeClass('btn-primary')->addClass('btn-default')->addClass('btn-success')->addClass('btn-sm');
	$btnaliases->setAttribute('title', gettext("Select an existing IP alias"));
	$group = new Form_Group('Bind-To IP Address Alias');
	$group->add($bind_to);
	$group->add($btnaliases);
	$group->setHelp(gettext("Supplied value must be a pre-configured Alias or the keyword 'all'."));
	$section->add($group);
}
else {
	$section->addInput( new Form_Input(
		'policy_bind_to',
		'Bind-To IP Address Alias',
		'text',
		$pengcfg['bind_to']
	))->setReadonly()->setHelp('The default engine is required and only runs for packets with destination addresses not matching other engine IP Lists.');
}

$section->addInput(new Form_Select(
	'policy',
	'Target Policy',
	$pengcfg['policy'],
	array( 'bsd' => 'BSD', 'bsd-right' => 'BSD-Right', 'hpux10' => 'HPUX10', 'hpux11' => 'HPUX11', 'irix' => 'Irix', 'linux' => 'Linux', 'mac-os' => 'Mac-OS', 'old-linux' => 'Old-Linux', 'old-solaris' => 'Old-Solaris', 'solaris' => 'Solaris', 'vista' => 'Vista', 'windows' => 'Windows', 'windows2k3' => 'Windows2k3' )
))->setHelp('Choose the OS target policy appropriate for the protected hosts. The default is BSD.');
$form->add($section);

$form->addGlobal(new Form_Button(
	'save_os_policy',
	'Save',
	null,
	'fa-solid fa-save'
))->addClass("btn-primary");

$form->addGlobal(new Form_Button(
	'cancel_os_policy',
	'Cancel',
	null
))->removeClass("btn-primary")->addClass("btn-warning");

print($form);
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	var addressarray = <?= json_encode(get_alias_list(array("host", "network"))) ?>;

	$('#policy_bind_to').autocomplete({
		source: addressarray
	});
});
//]]>
</script>

