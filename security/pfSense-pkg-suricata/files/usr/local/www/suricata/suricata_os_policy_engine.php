<?php
/*
*  suricata_os_policy_engine.php
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

$section = new Form_Section('Suricata Target-Based Host OS Policy Engine Configuration');
$section->addInput(new Form_Input(
	'policy_name',
	'Policy Name',
	'text',
	$pengcfg['name']
))->setHelp('Name or description for this engine. (Max 25 characters). Unique name or description for this engine configuration. Default value is default.');

$group = new Form_Group('Bind-To IP Address Alias');
$group->add(new Form_Input(
	'policy_bind_to',
	'Bind-To IP Address Alias',
	'text',
	$pengcfg['bind_to']
))->setHelp('IP List to bind this engine to (Cannot be blank). This policy will apply for packets with destination addresses contained within this IP List. Supplied value must be a pre-configured Alias or the keyword "all".');
$group->add(new Form_Button(
	'select_alias',
	'Aliases',
	null,
	'fa-upload'
))->removeClass('btn-primary')->addClass('btn-sm btn-success');
$section->add($group);

$section->addInput(new Form_Select(
	'policy',
	'Target Policy',
	$pengcfg['policy'],
	array( 'bsd' => 'BSD', 'bsd-right' => 'BSD-Right', 'hpux10' => 'HPUX10', 'hpux11' => 'HPUX11', 'irix' => 'Irix', 'linux' => 'Linux', 'mac-os' => 'Mac-OS', 'old-linux' => 'Old-Linux', 'old-solaris' => 'Old-Solaris', 'solaris' => 'Solaris', 'vista' => 'Vista', 'windows' => 'Windows', 'windows2k3' => 'Windows2k3' )
))->setHelp('Choose the OS target policy appropriate for the protected hosts. The default is BSD.');
$form->add($section);

print($form);
?>

<script type="text/javascript">
//<![CDATA[
	var addressarray = <?= json_encode(get_alias_list(array("host", "network"))) ?>;

function createAutoSuggest() {
	<?php
		echo "\tvar objAlias = new AutoSuggestControl(document.getElementById('policy_bind_to'), new StateSuggestions(addressarray));\n";
	?>
}

setTimeout("createAutoSuggest();", 500);
//]]>
</script>

