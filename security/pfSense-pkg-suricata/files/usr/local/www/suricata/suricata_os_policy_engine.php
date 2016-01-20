<?php
/*
 * suricata_os_policy_engine.php
 *
 * Portions of this code are based on original work done for the
 * Snort package for pfSense from the following contributors:
 * 
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2006 Scott Ullrich
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 * Copyright (C) 2012 Ermal Luci
 * All rights reserved.
 *
 * Adapted for Suricata by:
 * Copyright (C) 2014 Bill Meeks
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:

 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
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
	'Aliases'
))->removeClass('btn-primary')->addClass('btn-info');
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
//]]>
</script>

