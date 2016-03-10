<?php
/*
*  suricata_libhtp_policy_engine.php
*
*  Copyright (c)  2004-2016  Electric Sheep Fencing, LLC. All rights reserved.
*
*  Redistribution and use in source and binary forms, with or without modification,
*  are permitted provided that the following conditions are met:
*
*  1. Redistributions of source code must retain the above copyright notice,
*      this list of conditions and the following disclaimer.
*
*  2. Redistributions in binary form must reproduce the above copyright
*      notice, this list of conditions and the following disclaimer in
*      the documentation and/or other materials provided with the
*      distribution.
*
*  3. All advertising materials mentioning features or use of this software
*      must display the following acknowledgment:
*      "This product includes software developed by the pfSense Project
*       for use in the pfSense software distribution. (http://www.pfsense.org/).
*
*  4. The names "pfSense" and "pfSense Project" must not be used to
*       endorse or promote products derived from this software without
*       prior written permission. For written permission, please contact
*       coreteam@pfsense.org.
*
*  5. Products derived from this software may not be called "pfSense"
*      nor may "pfSense" appear in their names without prior written
*      permission of the Electric Sheep Fencing, LLC.
*
*  6. Redistributions of any form whatsoever must retain the following
*      acknowledgment:
*
*  "This product includes software developed by the pfSense Project
*  for use in the pfSense software distribution (http://www.pfsense.org/).
*
*  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
*  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
*  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
*  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
*  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
*  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
*  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
*  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
*  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
*  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
*  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
*  OF THE POSSIBILITY OF SUCH DAMAGE.
*
*
* Portions of this code are based on original work done for the Snort package for pfSense by the following contributors:
*
* Copyright (C) 2003-2004 Manuel Kasper
* Copyright (C) 2005 Bill Marquette
* Copyright (C) 2006 Scott Ullrich (copyright assigned to ESF)
* Copyright (C) 2009 Robert Zelaya Sr. Developer
* Copyright (C) 2012 Ermal Luci  (copyright assigned to ESF)
* Copyright (C) 2016 Bill Meeks
*
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
	enable_double_decode_path --> double-decode path part of URI
	enable_double_decode_query --> double-decode query string part of URI
	enable_uri_include_all --> inspect all of URI
	save_libhtp_policy --> Submit button for save operation and exit
	cancel_libhtp_policy --> Submit button to cancel operation and exit
 **************************************************************************************/


$form = new Form(false);

$section = new Form_Section('Suricata Target-Based HTTP Server Policy Configuration');
$section->addInput(new Form_Input(
	'policy_name',
	'Engine Name',
	'text',
	$pengcfg['name']
))->setHelp('Name or description for this engine. (Max 25 characters). Unique name or description for this engine configuration. Default value is default.');
$section->addInput(new Form_Input(
	'policy_bind_to',
	'Bind-To IP Address Alias',
	'text',
	$pengcfg['bind_to']
))->setHelp('IP List to bind this engine to. (Cannot be blank). This policy will apply for packets with destination addresses contained within this IP List. Supplied value must be a pre-configured Alias or the keyword "all".');
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
$form->add($section);

$section = new Form_Section('Decode Settings');
$section->addInput(new Form_Checkbox(
	'enable_double_decode_path',
	'Double-Decode Path',
	'Suricata will double-decode path section of the URI. Default is Not Checked.',
	$pengcfg['double-decode-path'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'enable_double_decode_query',
	'Double-Decode Query',
	'Suricata will double-decode query string section of the URI. Default is Not Checked.',
	$pengcfg['double-decode-query'] == 'on' ? true:false,
	'on'
));
$section->addInput(new Form_Checkbox(
	'enable_uri_include_all',
	'URI Include-All',
	'Include all parts of the URI. Default is Not Checked. By default the "scheme", username/password, hostname and port are excluded from inspection. Enabling this option adds all of them to the normalized uri. This was the default in Suricata versions prior to 2.0.',
	$pengcfg['uri-include-all'] == 'on' ? true:false,
	'on'
));
$form->add($section);

$form->addGlobal(new Form_Button(
	'save_libhtp_policy',
	'Save',
	null,
	'fa-save'
))->addClass("btn-primary");

$form->addGlobal(new Form_Button(
	'cancel_libhtp_policy',
	'Cancel'
))->removeClass('btn-primary')->addClass('btn-warning');

print($form);

?>

<script type="text/javascript" src="/javascript/autosuggest.js"></script>
<script type="text/javascript" src="/javascript/suggestions.js"></script>
<script type="text/javascript">
//<![CDATA[
var addressarray = <?= json_encode(get_alias_list(array("host", "network"))) ?>;

function createAutoSuggest() {
<?php
	echo "\tvar objAlias = new AutoSuggestControl(document.getElementById('policy_bind_to'), new StateSuggestions(addressarray));\n";
?>
}

setTimeout("createAutoSuggest();", 500);

</script>

