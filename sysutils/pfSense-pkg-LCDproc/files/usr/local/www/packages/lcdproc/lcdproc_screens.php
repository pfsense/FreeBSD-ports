<?php
/*
	cron_edit.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2008 Mark J Crane
	Copyright (C) 2015 ESF, LLC
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
require_once("guiconfig.inc");
require_once("/usr/local/pkg/lcdproc.inc");

$lcdproc_config         = &$config['installedpackages']['lcdproc']['config'][0];
$lcdproc_screens_config = &$config['installedpackages']['lcdprocscreens']['config'][0];

// Set default values for anything not in the $config
$pconfig = $lcdproc_screens_config;
if (!isset($pconfig['scr_version']))           $pconfig['scr_version']           = false;
if (!isset($pconfig['scr_time']))              $pconfig['scr_time']              = false;
if (!isset($pconfig['scr_uptime']))            $pconfig['scr_uptime']            = false;
if (!isset($pconfig['scr_hostname']))          $pconfig['scr_hostname']          = false;
if (!isset($pconfig['scr_system']))            $pconfig['scr_system']            = false;
if (!isset($pconfig['scr_disk']))              $pconfig['scr_disk']              = false;
if (!isset($pconfig['scr_load']))              $pconfig['scr_load']              = false;
if (!isset($pconfig['scr_states']))            $pconfig['scr_states']            = false;
if (!isset($pconfig['scr_carp']))              $pconfig['scr_carp']              = false;
if (!isset($pconfig['scr_ipsec']))             $pconfig['scr_ipsec']             = false;
if (!isset($pconfig['scr_slbd']))              $pconfig['scr_slbd']              = false;
if (!isset($pconfig['scr_interfaces']))        $pconfig['scr_interfaces']        = false;
if (!isset($pconfig['scr_mbuf']))              $pconfig['scr_mbuf']              = false;
if (!isset($pconfig['scr_cpufrequency']))      $pconfig['scr_cpufrequency']      = false;
if (!isset($pconfig['scr_traffic']))           $pconfig['scr_traffic']           = false;
if (!isset($pconfig['scr_traffic_interface'])) $pconfig['scr_traffic_interface'] = '';

if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	if (!$input_errors) {
		$lcdproc_screens_config['scr_version']           = $pconfig['scr_version'];
		$lcdproc_screens_config['scr_time']              = $pconfig['scr_time'];
		$lcdproc_screens_config['scr_uptime']            = $pconfig['scr_uptime'];
		$lcdproc_screens_config['scr_hostname']          = $pconfig['scr_hostname'];
		$lcdproc_screens_config['scr_system']            = $pconfig['scr_system'];
		$lcdproc_screens_config['scr_disk']              = $pconfig['scr_disk'];
		$lcdproc_screens_config['scr_load']              = $pconfig['scr_load'];
		$lcdproc_screens_config['scr_states']            = $pconfig['scr_states'];
		$lcdproc_screens_config['scr_carp']              = $pconfig['scr_carp'];
		$lcdproc_screens_config['scr_ipsec']             = $pconfig['scr_ipsec'];
		$lcdproc_screens_config['scr_slbd']              = $pconfig['scr_slbd'];
		$lcdproc_screens_config['scr_interfaces']        = $pconfig['scr_interfaces'];
		$lcdproc_screens_config['scr_mbuf']              = $pconfig['scr_mbuf'];
		$lcdproc_screens_config['scr_cpufrequency']      = $pconfig['scr_cpufrequency'];
		$lcdproc_screens_config['scr_traffic']           = $pconfig['scr_traffic'];
		$lcdproc_screens_config['scr_traffic_interface'] = $pconfig['scr_traffic_interface'];
				
		write_config();
		sync_package_lcdproc();
	}
}


$pgtitle = array(gettext("Services"), gettext("LCDproc"), gettext("Screens"));
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$tab_array = array();
$tab_array[] = array(gettext("Server"),  false, "/packages/lcdproc/lcdproc.php");
$tab_array[] = array(gettext("Screens"), true,  "/packages/lcdproc/lcdproc_screens.php");
display_top_tabs($tab_array);

// The constructor for Form automatically creates a submit button. If you want to suppress that
// use Form(false), of specify a different button using Form($mybutton)
$form = new Form();
$section = new Form_Section('LCD info screens');

// Add the Version checkbox
$section->addInput(
	new Form_Checkbox(
		'scr_version', // checkbox name (id)
		'Version', // checkbox label
		'Display the version', // checkbox text
		$pconfig['scr_version'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_time', // checkbox name (id)
		'Time', // checkbox label
		'Display the time', // checkbox text
		$pconfig['scr_time'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_uptime', // checkbox name (id)
		'Up-time', // checkbox label
		'Display the up-time', // checkbox text
		$pconfig['scr_uptime'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_hostname', // checkbox name (id)
		'Hostname', // checkbox label
		'Display the Hostname', // checkbox text
		$pconfig['scr_hostname'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_system', // checkbox name (id)
		'System', // checkbox label
		'Display system info', // checkbox text
		$pconfig['scr_system'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_disk', // checkbox name (id)
		'Disk', // checkbox label
		'Display disk info', // checkbox text
		$pconfig['scr_disk'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_load', // checkbox name (id)
		'Load', // checkbox label
		'Display the load', // checkbox text
		$pconfig['scr_load'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_states', // checkbox name (id)
		'States', // checkbox label
		'Display the states', // checkbox text
		$pconfig['scr_states'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_carp', // checkbox name (id)
		'Carp', // checkbox label
		'Show CARP state', // checkbox text
		$pconfig['scr_carp'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_ipsec', // checkbox name (id)
		'IPsec', // checkbox label
		'Show IPsec Tunnels state', // checkbox text
		$pconfig['scr_ipsec'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_slbd', // checkbox name (id)
		'Load Balancer', // checkbox label
		'', // checkbox text
		$pconfig['scr_slbd'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_interfaces', // checkbox name (id)
		'Interfaces', // checkbox label
		'Show whether interfaces are up', // checkbox text
		$pconfig['scr_interfaces'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_mbuf', // checkbox name (id)
		'Mbuf', // checkbox label
		'', // checkbox text
		$pconfig['scr_mbuf'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_cpufrequency', // checkbox name (id)
		'CPU Frequency', // checkbox label
		'Display how much the CPU has clocked down to reduce power consumption', // checkbox text
		$pconfig['scr_cpufrequency'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_traffic', // checkbox name (id)
		'Interface Traffic', // checkbox label
		'Display the traffic of an interface', // checkbox text
		$pconfig['scr_traffic'] // checkbox initial value
	)
);


/*
$interfaceList = array();
foreach($config['interfaces'] as $interface) {
	$interfaceList[$interface['if']] = '*' . $interface['descr'];
}*/
$section->addInput(
	new Form_Select(
		'scr_traffic_interface',
		' > interface selected',
		$pconfig['scr_traffic_interface'], // Initial value.
		[ 'wan' => 'WAN', 'lan' => 'LAN' ]
	)
)->setHelp('If Interface Traffic is enabled, here you specify which interface to monitor');

		
$form->add($section); // Add the section to our form
print($form); // Finally . . We can display our new form

?>

<div class="infoblock">
	<?=print_info_box('For more information see: <a href="http://lcdproc.org/docs.php3">LCDproc documentation</a>.', info)?>
</div>

<?php include("foot.inc"); ?>
