<?php
/*
 * lcdproc_screens.php
 *
 * part of pfSense (https://www.pfsense.org/)
 * Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2016 Treer
 * Copyright (c) 2008 Mark J Crane
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
if (!isset($pconfig['scr_top_interfaces_by_total_bytes']))   $pconfig['scr_top_interfaces_by_total_bytes']   = false;
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
		$lcdproc_screens_config['scr_top_interfaces_by_total_bytes']   = $pconfig['scr_top_interfaces_by_total_bytes'];
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
		'Display CARP state', // checkbox text
		$pconfig['scr_carp'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_ipsec', // checkbox name (id)
		'IPsec', // checkbox label
		'Display IPsec tunnels', // checkbox text
		$pconfig['scr_ipsec'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_slbd', // checkbox name (id)
		'Load Balancer', // checkbox label
		'Display the load balance state', // checkbox text
		$pconfig['scr_slbd'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_interfaces', // checkbox name (id)
		'Interfaces', // checkbox label
		'Display status of interfaces', // checkbox text
		$pconfig['scr_interfaces'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_mbuf', // checkbox name (id)
		'Mbuf', // checkbox label
		'Display the MBuf usage', // checkbox text
		$pconfig['scr_mbuf'] // checkbox initial value
	)
);
$section->addInput(
	new Form_Checkbox(
		'scr_cpufrequency', // checkbox name (id)
		'CPU Frequency', // checkbox label
		'Display CPU power saving rate', // checkbox text
		$pconfig['scr_cpufrequency'] // checkbox initial value
	)
);


$group = new Form_Group('Traffic of interface');
$group->add(
	new Form_Checkbox(
		'scr_traffic', // checkbox name (id)
		'', // checkbox label
		'Display total bytes since last boot (in & out), for interface:', // checkbox text
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
		'Interfaces listed with current bits-per-second (in & out)', // checkbox text
		$pconfig['scr_top_interfaces_by_bps'] // checkbox initial value
	)
)->setHelp('A 4&hyphen;row 20&hyphen;column display size, or higher, is recommended for this screen.');

$section->addInput(
	new Form_Checkbox(
		'scr_top_interfaces_by_total_bytes', // checkbox name (id)
		'Interfaces by volume', // checkbox label
		'Interfaces listed with total bytes since last boot (in & out)', // checkbox text
		$pconfig['scr_top_interfaces_by_total_bytes'] // checkbox initial value
	)
)->setHelp('A 4&hyphen;row 20&hyphen;column display size, or higher, is recommended for this screen.');

$section->addInput(
	new Form_Checkbox(
		'scr_top_interfaces_by_bytes_today', // checkbox name (id)
		'Interfaces by volume today', // checkbox label
		'Interfaces listed with total bytes since the start of the day, or since LCDproc reset (in & out)', // checkbox text
		$pconfig['scr_top_interfaces_by_bytes_today'] // checkbox initial value
	)
)->setHelp('A 4&hyphen;row 20&hyphen;column display size, or higher, is recommended for this screen.');


$group = new Form_Group('Addresses by traffic');
$group->add(new Form_Checkbox(
		'scr_traffic_by_address',
		'',
		'Display IP traffic:',
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
