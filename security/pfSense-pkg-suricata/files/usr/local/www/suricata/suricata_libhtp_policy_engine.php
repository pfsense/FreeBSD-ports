<?php
/*
 * suricata_libhtp_policy_engine.php
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
	This file contains code for adding/editing an existing Libhtp Policy Engine.
	It is included and injected inline as needed into the suricata_app_parsers.php
	page to provide the edit functionality for Host OS Policy Engines.

	The following variables are assumed to exist and must be initialized
	as necessary in order to utilize this page.

	$g --> system global variables array
	$config --> global variable pointing to configuration information
	$pengcfg --> array containing current Libhtp Policy engine configuration

	Information is returned from this page via the following form fields:

	policy_name --> Unique Name for the Libhtp Policy Engine
	policy_bind_to --> Alias name representing "bind_to" IP address for engine
	personality --> Operating system chosen for engine policy
	select_alias --> Submit button for select alias operation
	req_body_limit --> Request Body Limit size
	resp_body_limit --> Response Body Limit size
	meta_field_limit --> Meta-Field Limit size
	enable_double_decode_path --> double-decode path part of URI
	enable_double_decode_query --> double-decode query string part of URI
	enable_uri_include_all --> inspect all of URI
	save_libhtp_policy --> Submit button for save operation and exit
	cancel_libhtp_policy --> Submit button to cancel operation and exit
 **************************************************************************************/


$form = new Form(false);
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

$section = new Form_Section('Suricata Target-Based HTTP Server Policy Configuration');
$section->addInput(new Form_Input(
	'policy_name',
	'Engine Name',
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
	'personality',
	'Target Web Server Personality',
	$pengcfg['personality'],
	array( 'Apache_2' => 'Apache_2', 'Generic' => 'Generic', 'IDS' => 'IDS', 'IIS_4_0' => 'IIS_4_0', 'IIS_5_0' => 'IIS_5_0', 'IIS_5_1' => 'IIS_5_1', 'IIS_6_0' => 'IIS_6_0', 'IIS_7_0' => 'IIS_7_0', 'IIS_7_5' => 'IIS_7_5', 'Minimal' => 'Minimal')
))->setHelp('Choose the web server personality appropriate for the protected hosts. The default is IDS.');
$form->add($section);

$section = new Form_Section('Inspection Limits');
$section->addInput(new Form_Input(
	'req_body_limit',
	'Request Body Limit',
	'text',
	$pengcfg['request-body-limit']
))->setHelp('Maximum number of HTTP request body bytes to inspect. Default is 4,096 bytes. HTTP request bodies are often big, so they take a lot of time to process which has a significant impact on performance. This sets the limit (in bytes) of the client-body that will be inspected. Setting this parameter to 0 will inspect all of the client-body.');
$section->addInput(new Form_Input(
	'resp_body_limit',
	'Response Body Limit',
	'text',
	$pengcfg['response-body-limit']
))->setHelp('Maximum number of HTTP response body bytes to inspect. Default is 4,096 bytes. HTTP response bodies are often big, so they take a lot of time to process which has a significant impact on performance. This sets the limit (in bytes) of the server-body that will be inspected. Setting this parameter to 0 will inspect all of the server-body.');
$section->addInput(new Form_Input(
	'meta_field_limit',
	'Meta-Field Limit',
	'text',
	$pengcfg['meta-field-limit']
))->setHelp('Hard size limit for request and response size limits. Applies to request line and headers, response line and headers. Does not apply to request or response bodies. Default is 18k (18432) bytes. If this limit is reached an event is raised.');
$form->add($section);

$section = new Form_Section('Decode Settings');
$section->addInput(new Form_Checkbox(
	'enable_double_decode_path',
	'Double-Decode Path',
	'Suricata will double-decode path section of the URI. Default is Not Checked.',
	$pengcfg['double-decode-path'] == 'yes' ? true:false,
	'yes'
));
$section->addInput(new Form_Checkbox(
	'enable_double_decode_query',
	'Double-Decode Query',
	'Suricata will double-decode query string section of the URI. Default is Not Checked.',
	$pengcfg['double-decode-query'] == 'yes' ? true:false,
	'yes'
));
$section->addInput(new Form_Checkbox(
	'enable_uri_include_all',
	'URI Include-All',
	'Include all parts of the URI. Default is Not Checked. By default the "scheme", username/password, hostname and port are excluded from inspection. Enabling this option adds all of them to the normalized uri. This was the default in Suricata versions prior to 2.0.',
	$pengcfg['uri-include-all'] == 'yes' ? true:false,
	'yes'
));
$form->add($section);

$form->addGlobal(new Form_Button(
	'save_libhtp_policy',
	'Save',
	null,
	'fa-solid fa-save'
))->addClass("btn-primary");

$form->addGlobal(new Form_Button(
	'cancel_libhtp_policy',
	'Cancel',
	null
))->removeClass('btn-primary')->addClass('btn-warning');

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

