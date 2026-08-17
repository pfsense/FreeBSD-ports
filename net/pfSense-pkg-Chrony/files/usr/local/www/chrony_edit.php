<?php
/*
 * chrony_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2026 Denny Page
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
require_once("chrony.inc");

$shortcut_section = 'chrony';

$source_path = CHRONY_CONF_PATH_SOURCE . '/';

// If editing an existing source
if (is_numericint($_REQUEST['id'])) {
	$id = $_REQUEST['id'];
	$init_path = CHRONY_CONF_PATH_SOURCE . '/' . $id;
	$source_path .= $id;
	if (!config_get_path($source_path . '/' . CHRONY_CONF_TOK_SOURCE_DISABLED)) {
		$dirty = 1;
	}
}

// If duplicating an existing source
elseif (is_numericint($_REQUEST['dup'])) {
	$init_path = CHRONY_CONF_PATH_SOURCE . '/' . $_REQUEST['dup'];
}

// If the source exists, load its configuration
if (isset($init_path)) {
	$init_array = config_get_path($init_path, null);
	unset($init_path);

	$pconfig = array();
	$pconfig[CHRONY_CONF_TOK_SOURCE_DISABLED] = $init_array[CHRONY_CONF_TOK_SOURCE_DISABLED];
	$pconfig[CHRONY_CONF_TOK_SOURCE_NAME] = $init_array[CHRONY_CONF_TOK_SOURCE_NAME];
	$pconfig[CHRONY_CONF_TOK_SOURCE_IPPROTO] = $init_array[CHRONY_CONF_TOK_SOURCE_IPPROTO];
	$pconfig[CHRONY_CONF_TOK_SOURCE_TYPE] = $init_array[CHRONY_CONF_TOK_SOURCE_TYPE];
	$pconfig[CHRONY_CONF_TOK_SOURCE_MAXSOURCES] = $init_array[CHRONY_CONF_TOK_SOURCE_MAXSOURCES];
	$pconfig[CHRONY_CONF_TOK_SOURCE_POLLMIN] = $init_array[CHRONY_CONF_TOK_SOURCE_POLLMIN];
	$pconfig[CHRONY_CONF_TOK_SOURCE_POLLMAX] = $init_array[CHRONY_CONF_TOK_SOURCE_POLLMAX];
	$pconfig[CHRONY_CONF_TOK_SOURCE_FILTER] = $init_array[CHRONY_CONF_TOK_SOURCE_FILTER];
	$pconfig[CHRONY_CONF_TOK_SOURCE_XLEAVE] = $init_array[CHRONY_CONF_TOK_SOURCE_XLEAVE];
	$pconfig[CHRONY_CONF_TOK_SOURCE_PRIORITY] = $init_array[CHRONY_CONF_TOK_SOURCE_PRIORITY];
	$pconfig[CHRONY_CONF_TOK_SOURCE_REQUIRE] = $init_array[CHRONY_CONF_TOK_SOURCE_REQUIRE];
	$pconfig[CHRONY_CONF_TOK_SOURCE_NTS] = $init_array[CHRONY_CONF_TOK_SOURCE_NTS];
	$pconfig[CHRONY_CONF_TOK_SOURCE_DESC] = $init_array[CHRONY_CONF_TOK_SOURCE_DESC];
}

// Handle a save
if ($_POST) {
	$pconfig = $_POST;

	// Validate source name
	$source_name = trim($pconfig[CHRONY_CONF_TOK_SOURCE_NAME]);
	$pconfig[CHRONY_CONF_TOK_SOURCE_NAME] = $source_name;
	if ($source_name == '') {
		$input_errors[] = gettext('Source Name is required');
	}
	else {
		if (!is_fqdn($source_name)) {
			if ($pconfig[CHRONY_CONF_TOK_SOURCE_TYPE] == 'pool') {
				$input_errors[] = gettext('Source Name is not a valid FQDN');
			}
			else {
				$ip_protocols = chrony_ip_protocols();
				if ($ip_protocols == 'ipv4' || $pconfig[CHRONY_CONF_TOK_SOURCE_IPPROTO] == 'ipv4') {
					if (!is_ipaddrv4($source_name)) {
							$input_errors[] = gettext('Source Name is not a valid FQDN or IPv4 address');
					}
				}
				elseif ($ip_protocols == 'ipv6' || $pconfig[CHRONY_CONF_TOK_SOURCE_IPPROTO] == 'ipv6') {
					if (!is_ipaddrv6($source_name)) {
							$input_errors[] = gettext('Source Name is not a valid FQDN or IPv6 address');
					}
				}
				elseif (!is_ipaddr($source_name)) {
					$input_errors[] = gettext('Source Name is not a valid FQDN or IP address');
				}
			}
		}
	}

	// Validate the polling intervals
	if ($pconfig[CHRONY_CONF_TOK_SOURCE_POLLMIN] > $pconfig[CHRONY_CONF_TOK_SOURCE_POLLMAX]) {
		$input_errors[] = gettext('The minimum Polling Interval must be less than or equal to the maximum Polling Interval');
	}

	// Update the config
	if (!$input_errors) {
		$write_array = array();
		if ($pconfig[CHRONY_CONF_TOK_SOURCE_DISABLED]) {
			$write_array[CHRONY_CONF_TOK_SOURCE_DISABLED] = $pconfig[CHRONY_CONF_TOK_SOURCE_DISABLED];
		}
		else {
			$dirty = 1;
		}

		$write_array[CHRONY_CONF_TOK_SOURCE_NAME] = $pconfig[CHRONY_CONF_TOK_SOURCE_NAME];
		if (chrony_ip_protocols() == 'any') {
			$write_array[CHRONY_CONF_TOK_SOURCE_IPPROTO] = $pconfig[CHRONY_CONF_TOK_SOURCE_IPPROTO];
		}
		$write_array[CHRONY_CONF_TOK_SOURCE_TYPE] = $pconfig[CHRONY_CONF_TOK_SOURCE_TYPE];
		if ($pconfig[CHRONY_CONF_TOK_SOURCE_TYPE] == 'pool') {
			$write_array[CHRONY_CONF_TOK_SOURCE_MAXSOURCES] = $pconfig[CHRONY_CONF_TOK_SOURCE_MAXSOURCES];
		}
		$write_array[CHRONY_CONF_TOK_SOURCE_POLLMIN] = $pconfig[CHRONY_CONF_TOK_SOURCE_POLLMIN];
		$write_array[CHRONY_CONF_TOK_SOURCE_POLLMAX] = $pconfig[CHRONY_CONF_TOK_SOURCE_POLLMAX];
		if ($source[CHRONY_CONF_TOK_SOURCE_POLLMAX] <= 0) {
			$write_array[CHRONY_CONF_TOK_SOURCE_FILTER] = $pconfig[CHRONY_CONF_TOK_SOURCE_FILTER];
		}
		$write_array[CHRONY_CONF_TOK_SOURCE_XLEAVE] = $pconfig[CHRONY_CONF_TOK_SOURCE_XLEAVE];
		$write_array[CHRONY_CONF_TOK_SOURCE_PRIORITY] = $pconfig[CHRONY_CONF_TOK_SOURCE_PRIORITY];
		if ($pconfig[CHRONY_CONF_TOK_SOURCE_PRIORITY] != 'noselect') {
			$write_array[CHRONY_CONF_TOK_SOURCE_REQUIRE] = $pconfig[CHRONY_CONF_TOK_SOURCE_REQUIRE];
		}
		$write_array[CHRONY_CONF_TOK_SOURCE_NTS] = $pconfig[CHRONY_CONF_TOK_SOURCE_NTS];
		$write_array[CHRONY_CONF_TOK_SOURCE_DESC] = $pconfig[CHRONY_CONF_TOK_SOURCE_DESC];

		// Write the config
		config_set_path($source_path, $write_array);
		write_config(sprintf(gettext("Chrony daemon: %s source %s"),
			isset($id) ? gettext('edited') : gettext('added'),
			$write_array[CHRONY_CONF_TOK_SOURCE_NAME]));

		// Mark the subsystem as dirty if appropriate
		if ($dirty) {
			mark_subsystem_dirty('chrony');
		}

		// Return to the main page
		header("Location: chrony.php");
		exit;
	}
}

// Available options for source type
$source_type_options = array(
	'pool' => gettext('Pool - a pool of NTP servers'),
	'server' => gettext('Server - an individual NTP server'),
	'peer' => gettext('Peer - an individual NTP server in peer mode'));

// Available options for IP protocol
$ip_proto_options = array(
	'any' => gettext('Any'),
	'ipv4' => gettext('IPv4 only'),
	'ipv6' => gettext('IPv6 only'));

// Available options for maximum number of pool sources
$pool_maxsources_options = array(
	'1' => gettext('1'),
	'2' => gettext('2'),
	'3' => gettext('3'),
	'4' => gettext('4'),
	'5' => gettext('5'),
	'6' => gettext('6'),
	'7' => gettext('7'),
	'8' => gettext('8'),
	'9' => gettext('9'),
	'10' => gettext('10'),
	'11' => gettext('11'),
	'12' => gettext('12'),
	'13' => gettext('13'),
	'14' => gettext('14'),
	'15' => gettext('15'),
	'16' => gettext('16'));

// Available options for source priority
$source_priority_options = array(
	'normal' => gettext('Normal'),
	'prefer' => gettext('Preferred - mark this server as a preferred time source'),
	'noselect' => gettext('Noselect - never select this server as a time source'),
	'trust' => gettext('Trust - mark this server as a trusted time source'));

// Available options for poll intervals
$poll_interval_options = array(
	'-4' => gettext('-4 (1/16 second)'),
	'-3' => gettext('-3 (1/8 second)'),
	'-2' => gettext('-2 (1/4 second)'),
	'-1' => gettext('-1 (1/2 second)'),
	'0' => gettext('0 (1 second)'),
	'1' => gettext('1 (2 seconds)'),
	'2' => gettext('2 (4 seconds)'),
	'3' => gettext('3 (8 seconds)'),
	'4' => gettext('4 (16 seconds)'),
	'5' => gettext('5 (32 seconds)'),
	'6' => gettext('6 (64 seconds)'),
	'7' => gettext('7 (128 seconds)'),
	'8' => gettext('8 (256 seconds)'),
	'9' => gettext('9 (512 seconds)'),
	'10' => gettext('10 (1,024 seconds)'),
	'11' => gettext('11 (2,048 seconds)'),
	'12' => gettext('12 (4,096 seconds)'),
	'13' => gettext('13 (8,192 seconds)'),
	'14' => gettext('14 (16,384 seconds)'),
	'15' => gettext('15 (32,768 seconds)'),
	'16' => gettext('16 (65,536 seconds)'),
	'17' => gettext('17 (131,072 seconds)'),
	'18' => gettext('18 (262,144 seconds)'));

// Available options for median filter
$filter_options = array(
	'0' => gettext('none'),
	'2' => gettext('2'),
	'3' => gettext('3'),
	'4' => gettext('4'),
	'5' => gettext('5'),
	'6' => gettext('6'),
	'7' => gettext('7'),
	'8' => gettext('8'));

$pgtitle = array(gettext("Services"), gettext("Chrony"), gettext("Edit Source"));
$pglinks = array("", "chrony.php", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

$form = new Form;
$section = new Form_Section('Edit Source');

// Disable source
$section->addInput(new Form_Checkbox(
	'disabled',
	'Disable',
	'Disable this source',
	$pconfig[CHRONY_CONF_TOK_SOURCE_DISABLED]
));

// Source type
$section->addInput(new Form_Select(
	CHRONY_CONF_TOK_SOURCE_TYPE,
	'Source Type',
	$pconfig[CHRONY_CONF_TOK_SOURCE_TYPE],
	$source_type_options
))->setHelp(gettext('A Pool is a collection of NTP servers that Chrony will choose ' .
	'from, and must be a Fully Qualified Domain Name. Servers and Peers are ' .
	'individual NTP servers, and may be specified as either a Fully Qualified ' .
	'Domain Name or an IP address.'));

// Source name or IP address
$group = new Form_Group('Source Name');
$group->add(new Form_Input(
	'name',
	null,
	'text',
	$pconfig[CHRONY_CONF_TOK_SOURCE_NAME]
))->setHelp(gettext('Fully Qualified Domain Name or IP address of the source.'))->setAttribute('placeholder', 'e.g. pool.ntp.org');

// IP protocol
if (chrony_ip_protocols() == 'any') {
	$group->add(new Form_Select(
		CHRONY_CONF_TOK_SOURCE_IPPROTO,
		null,
		$pconfig[CHRONY_CONF_TOK_SOURCE_IPPROTO],
		$ip_proto_options
	))->setHelp(gettext('The IP protocol to use for the source. The default is Any.'));
}
$section->add($group);

// Maximum number of sources for a pool
$group = new Form_Group('Max Pool Sources');
$group->addClass('maxsources');
$group->add(new Form_Select(
	CHRONY_CONF_TOK_SOURCE_MAXSOURCES,
	null,
	(isset($pconfig[CHRONY_CONF_TOK_SOURCE_MAXSOURCES]) ? $pconfig[CHRONY_CONF_TOK_SOURCE_MAXSOURCES] : '4'),
	$pool_maxsources_options
))->setHelp(gettext('The maximum number of sources to use from this pool. The default is 4.'));
$section->add($group);

// Polling interval
$group = new Form_Group('Polling Interval');
$group->add(new Form_Select(
	CHRONY_CONF_TOK_SOURCE_POLLMIN,
	null,
	(isset($pconfig[CHRONY_CONF_TOK_SOURCE_POLLMIN]) ? $pconfig[CHRONY_CONF_TOK_SOURCE_POLLMIN] : '6'),
	$poll_interval_options
))->setHelp(gettext('The minimum polling interval for this source. Must be less than or equal to the maximum polling interval. The default is 6 (64 seconds).'));
$group->add(new Form_Select(
	CHRONY_CONF_TOK_SOURCE_POLLMAX,
	null,
	(isset($pconfig[CHRONY_CONF_TOK_SOURCE_POLLMAX]) ? $pconfig[CHRONY_CONF_TOK_SOURCE_POLLMAX] : '10'),
	$poll_interval_options
))->setHelp(gettext('The maximum polling interval for this source. Must be greater than or equal to the minimum polling interval. The default is 10 (1,024 seconds).'));
$group->setHelp(gettext('Intervals shorter than 6 (64 seconds) should not be ' .
	'used with public servers on the Internet because it might be considered ' .
	'abuse. Sub-second intervals should only be used in the local network when ' .
	'the round-trip time to the source is below 10 milliseconds.'));
$section->add($group);

// Median filter
$group = new Form_Group('Median Filter');
$group->addClass('filter');
$group->add(new Form_Select(
	CHRONY_CONF_TOK_SOURCE_FILTER,
	null,
	$pconfig[CHRONY_CONF_TOK_SOURCE_FILTER],
	$filter_options
))->setHelp(gettext('A median filter may be used to reduce measurement noise. The ' .
	'median filter works by reducing the sepcified number of raw samples to a ' .
	'single sample which is then used for calculations. This is intended for ' .
	'use with high speed polling in a local network.'));
$section->add($group);

// Interleaved mode
$section->addInput(new Form_Checkbox(
	'xleave',
	'Interleaved Mode',
	'Enable interleaved mode (xleave) for this source',
	$pconfig[CHRONY_CONF_TOK_SOURCE_XLEAVE]
))->setHelp(gettext('Note that if interleaved mode is enabled for a peer, it must ' .
	'be enabled on the remote system as well.'));

// Source Priority
$section->addInput(new Form_Select(
	CHRONY_CONF_TOK_SOURCE_PRIORITY,
	'Source Priority',
	$pconfig[CHRONY_CONF_TOK_SOURCE_PRIORITY],
	$source_priority_options
))->setHelp(gettext('The relative priority of this source. The default is Normal.'));

// Require source
$group = new Form_Group('Require Source');
$group->addClass('require');
$group->add(new Form_Checkbox(
	'require',
	null,
	'Mark this source as required',
	$pconfig[CHRONY_CONF_TOK_SOURCE_REQUIRE]
))->setHelp(gettext('If any sources are marked as required, at least one of them must be available before Chrony will adjust the clock.'));
$section->add($group);

// Network Time Security (NTS)
$group = new Form_Group('Network Time Security');
$group->addClass('nts');
$group->add(new Form_Checkbox(
	'nts',
	null,
	'Enable NTS for this source',
	$pconfig[CHRONY_CONF_TOK_SOURCE_NTS]
))->setHelp(gettext('Enable Network Time Security for this source.'));
$section->add($group);

// Description
$section->addInput(new Form_Input(
	'desc',
	'Description',
	'text',
	$pconfig[CHRONY_CONF_TOK_SOURCE_DESC]
))->setHelp('Description of this source for reference (not parsed).');

$form->add($section);

print($form);
?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Show/hide on source type
	function hideOnType() {
		hideClass('maxsources', $('#type').prop('value') != 'pool');
		hideClass('nts', $('#type').prop('value') == 'peer');
	}

	// Show/hide on source priority
	function hideOnPriority() {
		hideClass('require', $('#priority').prop('value') == 'noselect');
	}

	// Show/hide on max polling speed
	function hideOnPollMax() {
		hideClass('filter', parseInt($('#pollmax').prop('value')) > 0);
	}

	// On changing source type
	$('#type').change(function() {
		hideOnType();
	});

	// On changing priority
	$('#priority').change(function() {
		hideOnPriority();
	});

	// On changing poll min
	$('#pollmin').change(function() {
		var min = parseInt($('#pollmin').prop('value'));
		var max = parseInt($('#pollmax').prop('value'));
		if (min > max) {
			$('#pollmax').val(min);
			hideOnPollMax();
		}
	});

	// On changing poll max
	$('#pollmax').change(function() {
		var min = parseInt($('#pollmin').prop('value'));
		var max = parseInt($('#pollmax').prop('value'));
		if (max < min) {
			$('#pollmin').val(max);
		}
		hideOnPollMax();
	});

	// Initial page load
	hideOnType();
	hideOnPriority();
	hideOnPollMax();
});
//]]>
</script>

<?php include("foot.inc");
