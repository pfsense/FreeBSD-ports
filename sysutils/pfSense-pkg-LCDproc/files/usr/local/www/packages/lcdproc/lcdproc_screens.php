<?php
/*
	lcdproc_screens.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2008 Mark J Crane
	Copyright (C) 2016 Treer
	Copyright (C) 2016 ESF, LLC
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
if (!isset($pconfig['scr_version']))                         $pconfig['scr_version']                         = false;
if (!isset($pconfig['scr_time']))                            $pconfig['scr_time']                            = false;
if (!isset($pconfig['scr_uptime']))                          $pconfig['scr_uptime']                          = false;
if (!isset($pconfig['scr_hostname']))                        $pconfig['scr_hostname']                        = false;
if (!isset($pconfig['scr_system']))                          $pconfig['scr_system']                          = false;
if (!isset($pconfig['scr_disk']))                            $pconfig['scr_disk']                            = false;
if (!isset($pconfig['scr_load']))                            $pconfig['scr_load']                            = false;
if (!isset($pconfig['scr_states']))                          $pconfig['scr_states']                          = false;
if (!isset($pconfig['scr_carp']))                            $pconfig['scr_carp']                            = false;
if (!isset($pconfig['scr_ipsec']))                           $pconfig['scr_ipsec']                           = false;
if (!isset($pconfig['scr_slbd']))                            $pconfig['scr_slbd']                            = false;
if (!isset($pconfig['scr_interfaces']))                      $pconfig['scr_interfaces']                      = false;
if (!isset($pconfig['scr_mbuf']))                            $pconfig['scr_mbuf']                            = false;
if (!isset($pconfig['scr_cpufrequency']))                    $pconfig['scr_cpufrequency']                    = false;
if (!isset($pconfig['scr_traffic']))                         $pconfig['scr_traffic']                         = false;
if (!isset($pconfig['scr_traffic_interface']))               $pconfig['scr_traffic_interface']               = '';
if (!isset($pconfig['scr_top_interfaces_by_bps']))           $pconfig['scr_top_interfaces_by_bps']           = false;
if (!isset($pconfig['scr_top_interfaces_by_bytes_today']))   $pconfig['scr_top_interfaces_by_bytes_today']   = false;
if (!isset($pconfig['scr_traffic_by_address']))              $pconfig['scr_traffic_by_address']              = false;
if (!isset($pconfig['scr_traffic_by_address_if']))           $pconfig['scr_traffic_by_address_if']           = '';
if (!isset($pconfig['scr_traffic_by_address_sort']))         $pconfig['scr_traffic_by_address_sort']         = 'in';
if (!isset($pconfig['scr_traffic_by_address_filter']))       $pconfig['scr_traffic_by_address_filter']       = 'local';
if (!isset($pconfig['scr_traffic_by_address_hostipformat'])) $pconfig['scr_traffic_by_address_hostipformat'] = 'descr';


if ($_POST) {
	unset($input_errors);
	$pconfig = $_POST;

	if (!$input_errors) {
		$lcdproc_screens_config['scr_version']                         = $pconfig['scr_version'];
		$lcdproc_screens_config['scr_time']                            = $pconfig['scr_time'];
		$lcdproc_screens_config['scr_uptime']                          = $pconfig['scr_uptime'];
		$lcdproc_screens_config['scr_hostname']                        = $pconfig['scr_hostname'];
		$lcdproc_screens_config['scr_system']                          = $pconfig['scr_system'];
		$lcdproc_screens_config['scr_disk']                            = $pconfig['scr_disk'];
		$lcdproc_screens_config['scr_load']                            = $pconfig['scr_load'];
		$lcdproc_screens_config['scr_states']                          = $pconfig['scr_states'];
		$lcdproc_screens_config['scr_carp']                            = $pconfig['scr_carp'];
		$lcdproc_screens_config['scr_ipsec']                           = $pconfig['scr_ipsec'];
		$lcdproc_screens_config['scr_slbd']                            = $pconfig['scr_slbd'];
		$lcdproc_screens_config['scr_interfaces']                      = $pconfig['scr_interfaces'];
		$lcdproc_screens_config['scr_mbuf']                            = $pconfig['scr_mbuf'];
		$lcdproc_screens_config['scr_cpufrequency']                    = $pconfig['scr_cpufrequency'];
		$lcdproc_screens_config['scr_traffic']                         = $pconfig['scr_traffic'];
		$lcdproc_screens_config['scr_traffic_interface']               = $pconfig['scr_traffic_interface'];
		$lcdproc_screens_config['scr_top_interfaces_by_bps']           = $pconfig['scr_top_interfaces_by_bps'];
		$lcdproc_screens_config['scr_top_interfaces_by_bytes_today']   = $pconfig['scr_top_interfaces_by_bytes_today'];
		$lcdproc_screens_config['scr_traffic_by_address']              = $pconfig['scr_traffic_by_address'];
		$lcdproc_screens_config['scr_traffic_by_address_if']           = $pconfig['scr_traffic_by_address_if'];
		$lcdproc_screens_config['scr_traffic_by_address_sort']         = $pconfig['scr_traffic_by_address_sort'];
		$lcdproc_screens_config['scr_traffic_by_address_filter']       = $pconfig['scr_traffic_by_address_filter'];
		$lcdproc_screens_config['scr_traffic_by_address_hostipformat'] = $pconfig['scr_traffic_by_address_hostipformat'];
				
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
		'Display the percentage of disk-space used', // checkbox text
		$pconfig['scr_disk'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_load', // checkbox name (id)
		'Load', // checkbox label
		'Display the load averages', // checkbox text
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
		'Show IPsec tunnels', // checkbox text
		$pconfig['scr_ipsec'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_slbd', // checkbox name (id)
		'Load Balancer', // checkbox label
		'Show the load balance state', // checkbox text
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
		'Show the MBuf usage', // checkbox text
		$pconfig['scr_mbuf'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_cpufrequency', // checkbox name (id)
		'CPU Frequency', // checkbox label
		'Show when CPU speed is lowered to save power', // checkbox text
		$pconfig['scr_cpufrequency'] // checkbox initial value
	)
);


$group = new Form_Group('Traffic of interface');
$group->add(
	new Form_Checkbox(
		'scr_traffic', // checkbox name (id)
		'', // checkbox label
		'Display interface traffic for:', // checkbox text
		$pconfig['scr_traffic'] // checkbox initial value
	)
);
$group->add(
	new Form_Select(
		'scr_traffic_interface',
		'',
		$pconfig['scr_traffic_interface'], // Initial value.
		get_configured_interface_with_descr()
	)
);
$section->add($group);

$section->addInput(
	new Form_Checkbox(
		'scr_top_interfaces_by_bps', // checkbox name (id)
		'Interfaces by traffic', // checkbox label
		'List interfaces, ordered by bits-per-second (in & out)', // checkbox text
		$pconfig['scr_top_interfaces_by_bps'] // checkbox initial value
	)
)->setHelp('A 4&hyphen;row 20&hyphen;column display size, or higher, is recommended for this screen.');

$section->addInput(
	new Form_Checkbox(
		'scr_top_interfaces_by_bytes_today', // checkbox name (id)
		'Interfaces by volume', // checkbox label
		'List interfaces, ordered by total bytes today (in & out)', // checkbox text
		$pconfig['scr_top_interfaces_by_bytes_today'] // checkbox initial value
	)
)->setHelp('A 4&hyphen;row 20&hyphen;column display size, or higher, is recommended for this screen.');


$group = new Form_Group('Traffic by address');
$group->add(new Form_Checkbox(
		'scr_traffic_by_address',
		'',
		'Show IP traffic:',
		$pconfig['scr_traffic_by_address']
));
$group->add(new Form_Select(
	'scr_traffic_by_address_if',
	null,
	$pconfig['scr_traffic_by_address_if'],
	get_configured_interface_with_descr()
))->setHelp('Interface');
$group->add(new Form_Select(
	'scr_traffic_by_address_sort',
	null,
	$pconfig['scr_traffic_by_address_sort'],
	array (
		'in'	=> gettext('Bandwidth In'),
		'out'	=> gettext('Bandwidth Out')
	)
))->setHelp('Sort by');
$group->add(new Form_Select(
	'scr_traffic_by_address_filter',
	null,
	$pconfig['scr_traffic_by_address_filter'],
	array (
		'local'	=> gettext('Local'),
		'remote'=> gettext('Remote'),
		'all'	=> gettext('All')
	)
))->setHelp('Filter');
$group->add(new Form_Select(
	'scr_traffic_by_address_hostipformat',
	null,
	$pconfig['scr_traffic_by_address_hostipformat'],
	array (
		''			=> gettext('IP Address'),
		'hostname'	=> gettext('Host Name'),
		'descr'		=> gettext('Description'),
		'fqdn'		=> gettext('FQDN')
	)
))->setHelp('Display');
$group->setHelp('A 4&hyphen;row 20&hyphen;column display size, or higher, is recommended for this screen.');
$section->add($group);

		
$form->add($section); // Add the section to our form
print($form); // Finally . . We can display our new form

?>

<div class="infoblock">
	<?=print_info_box('For more information see: <a href="http://lcdproc.org/docs.php3">LCDproc documentation</a>.', info)?>
</div>

<?php include("foot.inc"); ?>
