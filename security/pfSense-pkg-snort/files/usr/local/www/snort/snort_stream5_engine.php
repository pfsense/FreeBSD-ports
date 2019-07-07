<?php
/*
 * snort_stream5_engine.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2018 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013-2018 Bill Meeks
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
require_once("/usr/local/pkg/snort/snort.inc");

global $g;

$snortdir = SNORTDIR;

/* Retrieve required array index values from QUERY string if available. */
/* 'id' is the [rule] array index, and 'eng_id' is the index for the    */
/* stream5_tcp_engine's [item] array.                                   */
/* See if values are in our form's POST content */
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

if (isset($_POST['eng_id']) && isset($_POST['eng_id']))
	$eng_id = $_POST['eng_id'];
elseif (isset($_GET['eng_id']) && is_numericint($_GET['eng_id']))
	$eng_id = htmlspecialchars($_GET['eng_id']);

/* If we don't have a [rule] index specified, exit */
if (is_null($id)) {
	// Clear and close out any session variable we created
	session_start();
	unset($_SESSION['stream5_client_import']);
	session_write_close();
 	header("Location: /snort/snort_interfaces.php");
	exit;
}

/* Initialize pointer into requisite section of [config] array */
if (!is_array($config['installedpackages']['snortglobal']['rule'])) {
	$config['installedpackages']['snortglobal']['rule'] = array();
}
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id])) {
	$config['installedpackages']['snortglobal']['rule'][$id] = array();
}
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['stream5_tcp_engine'])) {
	$config['installedpackages']['snortglobal']['rule'][$id]['stream5_tcp_engine'] = array();
}
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['stream5_tcp_engine']['item'])) {
	$config['installedpackages']['snortglobal']['rule'][$id]['stream5_tcp_engine']['item'] = array();
}
$a_nat = &$config['installedpackages']['snortglobal']['rule'][$id]['stream5_tcp_engine']['item'];

$pconfig = array();

// If this is a new entry, intialize it with default values
if (empty($a_nat[$eng_id])) {
	$def = array(	"name" => "engine_{$eng_id}", "bind_to" => "", "policy" => "bsd", "timeout" => 30, 
			"max_queued_bytes" => 1048576, "detect_anomalies" => "off", "overlap_limit" => 0, 
			"max_queued_segs" => 2621, "require_3whs" => "off", "startup_3whs_timeout" => 0, 
			"no_reassemble_async" => "off", "dont_store_lg_pkts" => "off", "max_window" => 0, 
			"use_static_footprint_sizes" => "off", "check_session_hijacking" => "off", "ports_client" => "default", 
			"ports_both" => "default", "ports_server" => "none" );
	// See if this is initial entry and set to "default" if true
	if ($eng_id < 1) {
		$def['name'] = "default";
		$def['bind_to'] = "all";
	}
	$pconfig = $def;
}
else {
	$pconfig = $a_nat[$eng_id];

	// Check for empty values and set sensible defaults
	if (empty($pconfig['policy']))
		$pconfig['policy'] = "bsd";
	if (empty($pconfig['timeout']))
		$pconfig['timeout'] = 30;
	if (empty($pconfig['max_queued_bytes']) && $pconfig['max_queued_bytes'] <> 0)
		$pconfig['max_queued_bytes'] = 1048576;
	if (empty($pconfig['detect_anomalies']))
		$pconfig['detect_anomalies'] = "off";
	if (empty($pconfig['overlap_limit']))
		$pconfig['overlap_limit'] = 0;
	if (empty($pconfig['max_queued_segs']) && $pconfig['max_queued_segs'] <> 0)
		$pconfig['max_queued_segs'] = 2621;
	if (empty($pconfig['require_3whs']))
		$pconfig['require_3whs'] = "off";
	if (empty($pconfig['startup_3whs_timeout']))
		$pconfig['startup_3whs_timeout'] = 0;
	if (empty($pconfig['no_reassemble_async']))
		$pconfig['no_reassemble_async'] = "off";
	if (empty($pconfig['dont_store_lg_pkts']))
		$pconfig['dont_store_lg_pkts'] = "off";
	if (empty($pconfig['max_window']))
		$pconfig['max_window'] = 0;
	if (empty($pconfig['use_static_footprint_sizes']))
		$pconfig['use_static_footprint_sizes'] = "off";
	if (empty($pconfig['check_session_hijacking']))
		$pconfig['check_session_hijacking'] = "off";
	if (empty($pconfig['ports_client']))
		$pconfig['ports_client'] = "default";
	if (empty($pconfig['ports_both']))
		$pconfig['ports_both'] = "default";
	if (empty($pconfig['ports_server']))
		$pconfig['ports_server'] = "none";
}

if ($_POST['cancel']) {
	// Clear and close out any session variable we created
	session_start();
	unset($_SESSION['stream5_client_import']);
	session_write_close();
	header("Location: /snort/snort_preprocessors.php?id={$id}#stream5_row");
	exit;
}

// Check for returned "selected alias" if action is import
if ($_GET['act'] == "import") {
	session_start();
	if (($_GET['varname'] == "bind_to" || $_GET['varname'] == "ports_client" || $_GET['varname'] == "ports_both" || $_GET['varname'] == "ports_server") 
	     && !empty($_GET['varvalue'])) {
		$pconfig[$_GET['varname']] = htmlspecialchars($_GET['varvalue']);
		if(!isset($_SESSION['stream5_client_import']))
			$_SESSION['stream5_client_import'] = array();

		$_SESSION['stream5_client_import'][$_GET['varname']] = $_GET['varvalue'];
		if (isset($_SESSION['stream5_client_import']['bind_to']))
			$pconfig['bind_to'] = $_SESSION['stream5_client_import']['bind_to'];
		if (isset($_SESSION['stream5_client_import']['ports_client']))
			$pconfig['ports_client'] = $_SESSION['stream5_client_import']['ports_client'];
		if (isset($_SESSION['stream5_client_import']['ports_both']))
			$pconfig['ports_both'] = $_SESSION['stream5_client_import']['ports_both'];
		if (isset($_SESSION['stream5_client_import']['ports_server']))
			$pconfig['ports_server'] = $_SESSION['stream5_client_import']['ports_server'];
	}
	// If "varvalue" is empty, user likely hit CANCEL in Select Dialog,
	// so restore any saved values.
	elseif (empty($_GET['varvalue'])) {
		if (isset($_SESSION['stream5_client_import']['bind_to']))
			$pconfig['bind_to'] = $_SESSION['stream5_client_import']['bind_to'];
		if (isset($_SESSION['stream5_client_import']['ports_client']))
			$pconfig['ports_client'] = $_SESSION['stream5_client_import']['ports_client'];
		if (isset($_SESSION['stream5_client_import']['ports_both']))
			$pconfig['ports_both'] = $_SESSION['stream5_client_import']['ports_both'];
		if (isset($_SESSION['stream5_client_import']['ports_server']))
			$pconfig['ports_server'] = $_SESSION['stream5_client_import']['ports_server'];
	}
	else {
		unset($_SESSION['stream5_client_import']);
		unset($_SESSION['org_referer']);
		unset($_SESSION['org_querystr']);
		session_write_close();
	}
}

if ($_POST['save']) {
	// Clear and close out any session variable we created
	session_start();
	unset($_SESSION['org_referer']);
	unset($_SESSION['org_querystr']);
	unset($_SESSION['stream5_client_import']);
	session_write_close();

	/* Grab all the POST values and save in new temp array */
	$engine = array();
	if ($_POST['stream5_name']) { $engine['name'] = trim($_POST['stream5_name']); } else { $engine['name'] = "default"; }

	/* Validate input values before saving */
	if ($_POST['stream5_bind_to']) {
		if (is_alias($_POST['stream5_bind_to'])) {
			$engine['bind_to'] = $_POST['stream5_bind_to'];
			if (!snort_is_single_addr_alias($_POST['stream5_bind_to']))
				$input_errors[] = gettext("An Alias that evaluates to a single IP address or CIDR network is required for the 'Bind-To IP Address' value.");
		}
		elseif (strtolower(trim($_POST['stream5_bind_to'])) == "all")
			$engine['bind_to'] = "all";
		else
			$input_errors[] = gettext("You must provide a valid Alias or the reserved keyword 'all' for the 'Bind-To IP Address' value.");
	}
	else {
		$input_errors[] = gettext("The 'Bind-To IP Address' value cannot be blank.  Provide a valid Alias or the reserved keyword 'all'.");
	}
	if ($_POST['stream5_ports_client']) {
		if (is_alias($_POST['stream5_ports_client']))
			$engine['ports_client'] = $_POST['stream5_ports_client'];
		elseif (strtolower(trim($_POST['stream5_ports_client'])) == "default")
			$engine['ports_client'] = "default";
		elseif (strtolower(trim($_POST['stream5_ports_client'])) == "all")
			$engine['ports_client'] = "all";
		elseif (strtolower(trim($_POST['stream5_ports_client'])) == "none")
			$engine['ports_client'] = "none";
		else
			$input_errors[] = gettext("You must provide a valid Alias or one of the reserved keywords 'default', 'all' or 'none' for the TCP Target Ports 'ports_client' value.");
	}
	if ($_POST['stream5_ports_both']) {
		if (is_alias($_POST['stream5_ports_both']))
			$engine['ports_both'] = $_POST['stream5_ports_both'];
		elseif (strtolower(trim($_POST['stream5_ports_both'])) == "default")
			$engine['ports_both'] = "default";
		elseif (strtolower(trim($_POST['stream5_ports_both'])) == "all")
			$engine['ports_both'] = "all";
		elseif (strtolower(trim($_POST['stream5_ports_both'])) == "none")
			$engine['ports_both'] = "none";
		else
			$input_errors[] = gettext("You must provide a valid Alias or one of the reserved keywords 'default', 'all' or 'none' for the TCP Target Ports 'ports_both' value.");
	}
	if ($_POST['stream5_ports_server']) {
		if (is_alias($_POST['stream5_ports_server']))
			$engine['ports_server'] = $_POST['stream5_ports_server'];
		elseif (strtolower(trim($_POST['stream5_ports_server'])) == "default")
			$engine['ports_server'] = "default";
		elseif (strtolower(trim($_POST['stream5_ports_server'])) == "all")
			$engine['ports_server'] = "all";
		elseif (strtolower(trim($_POST['stream5_ports_server'])) == "none")
			$engine['ports_server'] = "none";
		else
			$input_errors[] = gettext("You must provide a valid Alias or one of the reserved keywords 'default', 'all' or 'none' for the TCP Target Ports 'ports_server' value.");
	}

	if (!empty($_POST['stream5_timeout']) || $_POST['stream5_timeout'] == 0) {
		$engine['timeout'] = $_POST['stream5_timeout'];
		if ($engine['timeout'] < 1 || $engine['timeout'] > 86400)
			$input_errors[] = gettext("The value for Timeout must be between 1 and 86400.");
	}
	else
		$engine['timeout'] = 60;

	if (!empty($_POST['stream5_max_queued_bytes']) || $_POST['stream5_max_queued_bytes'] == 0) {
		$engine['max_queued_bytes'] = $_POST['stream5_max_queued_bytes'];
		if ($engine['max_queued_bytes'] <> 0) {
			if ($engine['max_queued_bytes'] < 1024 || $engine['max_queued_bytes'] > 1073741824)
				$input_errors[] = gettext("The value for Max_Queued_Bytes must either be 0, or between 1024 and 1073741824.");
		}
	}
	else
		$engine['max_queued_bytes'] = 1048576;

	if (!empty($_POST['stream5_max_queued_segs']) || $_POST['stream5_max_queued_segs'] == 0) {
		$engine['max_queued_segs'] = $_POST['stream5_max_queued_segs'];
		if ($engine['max_queued_segs'] <> 0) {
			if ($engine['max_queued_segs'] < 2 || $engine['max_queued_segs'] > 1073741824)
				$input_errors[] = gettext("The value for Max_Queued_Segs must either be 0, or between 2 and 1073741824.");
		}
	}
	else
		$engine['max_queued_segs'] = 2621;

	if (!empty($_POST['stream5_overlap_limit']) || $_POST['stream5_overlap_limit'] == 0) {
		$engine['overlap_limit'] = $_POST['stream5_overlap_limit'];
		if ($engine['overlap_limit'] < 0 || $engine['overlap_limit'] > 255)
			$input_errors[] = gettext("The value for Overlap_Limit must be between 0 and 255.");
	}
	else
		$engine['overlap_limit'] = 0;

	if (!empty($_POST['stream5_max_window']) || $_POST['stream5_max_window'] == 0) {
		$engine['max_window'] = $_POST['stream5_max_window'];
		if ($engine['max_window'] < 0 || $engine['max_window'] > 1073725440)
			$input_errors[] = gettext("The value for Max_Window must be between 0 and 1073725440.");
	}
	else
		$engine['max_window'] = 0;

	if (!empty($_POST['stream5_3whs_startup_timeout']) || $_POST['stream5_3whs_startup_timeout'] == 0) {
		$engine['startup_3whs_timeout'] = $_POST['stream5_3whs_startup_timeout'];
		if ($engine['startup_3whs_timeout'] < 0 || $engine['startup_3whs_timeout'] > 86400)
			$input_errors[] = gettext("The value for 3whs_Startup_Timeout must be between 0 and 86400.");
	}
	else
		$engine['startup_3whs_timeout'] = 0;

	if ($_POST['stream5_policy']) { $engine['policy'] = $_POST['stream5_policy']; } else { $engine['policy'] = "bsd"; }
	if ($_POST['stream5_ports']) { $engine['ports'] = $_POST['stream5_ports']; } else { $engine['ports'] = "both"; }

	$engine['detect_anomalies'] = $_POST['stream5_detect_anomalies'] ? 'on' : 'off';
	$engine['require_3whs'] = $_POST['stream5_require_3whs'] ? 'on' : 'off';
	$engine['no_reassemble_async'] = $_POST['stream5_no_reassemble_async'] ? 'on' : 'off';
	$engine['dont_store_lg_pkts'] = $_POST['stream5_dont_store_lg_pkts'] ? 'on' : 'off';
	$engine['use_static_footprint_sizes'] = $_POST['stream5_use_static_footprint_sizes'] ? 'on' : 'off';
	$engine['check_session_hijacking'] = $_POST['stream5_check_session_hijacking'] ? 'on' : 'off';

	/* Can only have one "all" Bind_To address */
	if ($engine['bind_to'] == "all" && $engine['name'] <> "default")
		$input_errors[] = gettext("Only one default Stream5 Engine can be bound to all addresses.");
	$pconfig = $engine;

	/* if no errors, write new entry to conf */
	if (!$input_errors) {
		if (isset($eng_id) && $a_nat[$eng_id]) {
			$a_nat[$eng_id] = $engine;
		}
		else
			$a_nat[] = $engine;

		/* Reorder the engine array to ensure the */
		/* 'bind_to=all' entry is at the bottom   */
		/* if it contains more than one entry.    */
		if (count($a_nat) > 1) {
			$i = -1;
			foreach ($a_nat as $f => $v) {
				if ($v['bind_to'] == "all") {
					$i = $f;
					break;
				}
			}
			/* Only relocate the entry if we  */
			/* found it, and it's not already */
			/* at the end.                    */
			if ($i > -1 && ($i < (count($a_nat) - 1))) {
				$tmp = $a_nat[$i];
				unset($a_nat[$i]);
				$a_nat[] = $tmp;
			}
		}

		/* Now write the new engine array to conf */
		write_config("Snort pkg: save modified stream5 engine.");

		header("Location: /snort/snort_preprocessors.php?id={$id}#stream5_row");
		exit;
	}
}

$if_friendly = convert_friendly_interface_to_friendly_descr($config['installedpackages']['snortglobal']['rule'][$id]['interface']);
$pgtitle = array(gettext("Services"), gettext("Snort"), gettext("Stream5 Preprocessor TCP Engine"), gettext("{$if_friendly}"));
include("head.inc");

if ($input_errors) print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);

$form = new Form(FALSE);
$section = new Form_Section('Snort Stream5 Target-Based TCP Stream Reassembly Engine Configuration');

$engine_name = new Form_Input(
	'stream5_name',
	'Engine Name',
	'text',
	$pconfig['name']
);
if ($pconfig['name'] <> "default") {
	$engine_name->setHelp('Enter a unique name or description for this engine.  (Max 25 characters)');
}
else {
	$engine_name->setReadonly()->setHelp('The name for the default engine is read-only.');
}
$section->addInput($engine_name);

if ($pconfig['name'] <> "default") {
	$bind_to = new Form_Input(
		'stream5_bind_to',
		'',
		'text',
		$pconfig['bind_to']
	);
	$bind_to->setAttribute('title', trim(filter_expand_alias($pconfig['bind_to'])));
	$bind_to->setHelp('IP List to bind this engine to. (Cannot be blank)');
	$btnaliases = new Form_Button(
		'btnSuppressList',
		' ' . 'Aliases',
		'snort_select_alias.php?id=' . $id . '&eng_id=<?=' . $eng_id . '&type=host|network&varname=bind_to&act=import&multi_ip=yes&returl=' . urlencode($_SERVER['PHP_SELF']),
		'fa-search-plus'
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
		'stream5_bind_to',
		'Bind-To IP Address Alias',
		'text',
		$pconfig['bind_to']
	))->setReadonly()->setHelp('The default engine is required and only runs for packets with destination addresses not matching other engine IP Lists.');
}

$section->addInput(new Form_Select(
	'stream5_policy',
	'TCP Target Policy',
	$pconfig['policy'],
	array( 'bsd' => 'BSD', 'first' => 'First', 'hpux' => 'HPUX', 'hpux10' => 'HPUX10', 'irix' => 'Irix', 'last' => 'Last', 
		'linux' => 'Linux', 'macos' => 'MacOS', 'old-linux' => 'Old-Linux', 'solaris' => 'Solaris', 'vista' => 'Vista', 
		'windows' => 'Windows', 'win2003' => 'Win2003' )
))->setHelp('Choose the TCP target policy appropriate for the protected hosts.  The default is BSD.');

$bind_to = new Form_Input(
	'stream5_ports_client',
	'',
	'text',
	$pconfig['ports_client']
);
$bind_to->setAttribute('title', trim(filter_expand_alias($pconfig['ports'])));
$bind_to->setHelp('Specify which ports to check for data.  Default value is <em>default</em> which includes ports 21, 22, 23, 25, 42, 53, 70, 79, 109, 110, 111, 113, 119, 135, 136, 137, 139, 143, ' . 
		  '161, 445, 513, 514, 587, 593, 691, 1433, 1521, 1741, 2100, 3306, 6070, 6665, 6666, 6667, 6668, 6669, 7000, 8181, 32770, 32771, 32772, 32773, 32774, 32775, 32776, 32777, 32778 and 32779.');
$btnaliases = new Form_Button(
	'btnSelectAlias',
	' ' . 'Aliases',
	'snort_select_alias.php?id=' . $id . '&eng_id=<?=' . $eng_id . '&type=port&varname=ports_client&act=import&returl=' . urlencode($_SERVER['PHP_SELF']),
	'fa-search-plus'
);
$btnaliases->removeClass('btn-primary')->addClass('btn-default')->addClass('btn-success')->addClass('btn-sm');
$btnaliases->setAttribute('title', gettext("Select an existing port alias"));
$group = new Form_Group('TCP Target Client Ports');
$group->add($bind_to);
$group->add($btnaliases);
$msg = gettext("Configures which side of the connection packets should be reassembled for based on the configured destination ports. "); 
$msg .= gettext("Supplied value must be a pre-configured Alias or the keyword <em>default</em>, <em>all</em> or <em>none</em>.  Specific ports can be specified here using a pre-defined Alias.") . '<br/>';
$msg .= gettext("Most users should leave these settings at their default values.");
$group->setHelp($msg);
$section->add($group);

$bind_to = new Form_Input(
	'stream5_ports_server',
	'',
	'text',
	$pconfig['ports_server']
);
$bind_to->setAttribute('title', trim(filter_expand_alias($pconfig['ports'])));
$bind_to->setHelp('Specify which ports to check for data.  Default value is <em>none</em>.');
$btnaliases = new Form_Button(
	'btnSelectAlias',
	' ' . 'Aliases',
	'snort_select_alias.php?id=' . $id . '&eng_id=<?=' . $eng_id . '&type=port&varname=ports_server&act=import&returl=' . urlencode($_SERVER['PHP_SELF']),
	'fa-search-plus'
);
$btnaliases->removeClass('btn-primary')->addClass('btn-default')->addClass('btn-success')->addClass('btn-sm');
$btnaliases->setAttribute('title', gettext("Select an existing port alias"));
$group = new Form_Group('TCP Target Server Ports');
$group->add($bind_to);
$group->add($btnaliases);
$msg = gettext("Configures which side of the connection packets should be reassembled for based on the configured destination ports. "); 
$msg .= gettext("Supplied value must be a pre-configured Alias or the keyword <em>default</em>, <em>all</em> or <em>none</em>.  Specific ports can be specified here using a pre-defined Alias.") . '<br/>';
$msg .= gettext("Most users should leave these settings at their default values.");
$group->setHelp($msg);
$section->add($group);

$bind_to = new Form_Input(
	'stream5_ports_both',
	'',
	'text',
	$pconfig['ports_both']
);
$bind_to->setAttribute('title', trim(filter_expand_alias($pconfig['ports'])));
$bind_to->setHelp('Specify which ports to check for data.  Default value is <em>default</em> which includes ports 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 110, 311, 383, 443, 465, 563, ' . 
		  '591, 593, 631, 636, 901, 989, 992, 993, 994, 995, 1220, 1414, 1533, 1830, 2301, 2381, 2809, 3037, 3057, 3128, 3443, 3702, 4343, 4848, 5250, 6080, 6988, 7907, 7000, 7001, 7144, 7145, 7510, 7802, 7777, ' . 
		  '7779, 7801, 7900-7920, 8000, 8008, 8014, 8028, 8080, 8081, 8082, 8085, 8088, 8090, 8118, 8123, 8180, 8222, 8243, 8280, 8300, 8500, 8800, 8888, 8899, 9000, 9060, 9080, 9090, 9091, 9443, 9999, 10000, ' .
		  '11371, 15489, 29991, 33300, 34412, 34443, 34444, 41080, 44440, 50000, 50002, 51423, 55555 and 56712.');
$btnaliases = new Form_Button(
	'btnSelectAlias',
	' ' . 'Aliases',
	'snort_select_alias.php?id=' . $id . '&eng_id=<?=' . $eng_id . '&type=port&varname=ports_both&act=import&returl=' . urlencode($_SERVER['PHP_SELF']),
	'fa-search-plus'
);
$btnaliases->removeClass('btn-primary')->addClass('btn-default')->addClass('btn-success')->addClass('btn-sm');
$btnaliases->setAttribute('title', gettext("Select an existing port alias"));
$group = new Form_Group('TCP Target Ports (Both)');
$group->add($bind_to);
$group->add($btnaliases);
$msg = gettext("Configures which side of the connection packets should be reassembled for based on the configured destination ports. "); 
$msg .= gettext("Supplied value must be a pre-configured Alias or the keyword <em>default</em>, <em>all</em> or <em>none</em>.  Specific ports can be specified here using a pre-defined Alias.") . '<br/>';
$msg .= gettext("Most users should leave these settings at their default values.");
$group->setHelp($msg);
$section->add($group);

$section->addInput( new Form_Input(
	'stream5_max_window',
	'TCP Max Window',
	'number',
	$pconfig['max_window']
))->setAttribute('min', '0')->setAttribute('max', '1073725440')->setHelp('TCP max window size.  Minimum is 1 and maximum is 1073725440.  Default is 0 (unlimited).<br/>This option is intended to prevent a DoS against Stream5 by an attacker using an abnormally large window, so using a value near the maximum is discouraged.');

$section->addInput( new Form_Input(
	'stream5_timeout',
	'TCP Timeout',
	'number',
	$pconfig['timeout']
))->setAttribute('min', '1')->setAttribute('max', '86400')->setHelp('TCP Session timeout in seconds.  Minimum is 1 and maximum is 86400 (approximately 1 day).  Default is 30.<br/>Sets the session reassembly timeout period (in seconds) for TCP packets.');

$section->addInput( new Form_Input(
	'stream5_max_queued_bytes',
	'TCP Max Queued Bytes',
	'number',
	$pconfig['max_queued_bytes']
))->setAttribute('min', '0')->setAttribute('max', '1073741824')->setHelp('TCP Session timeout in seconds.  Minimum is 1024 and maximum is 1073741824 (0 means Maximum).  Default is 1048576.<br/>This sets the number of bytes to be queued for reassembly of TCP sessions in memory.');

$section->addInput( new Form_Input(
	'stream5_max_queued_segs',
	'TCP Max Queued Segs',
	'number',
	$pconfig['max_queued_segs']
))->setAttribute('min', '0')->setAttribute('max', '1073741824')->setHelp('Number of segments to be queued for reassembly of TCP sessions.  Minimum is 2 and maximum is 1073741824 (0 means Maximum).  Default is 2621.<br/>This sets the number of segments to be queued for reassembly of TCP sessions in memory.');

$section->addInput( new Form_Input(
	'stream5_overlap_limit',
	'TCP Overlap Limit',
	'number',
	$pconfig['overlap_limit']
))->setAttribute('min', '0')->setAttribute('max', '255')->setHelp('Number of overlapping packets.  Minimum is 0 (unlimited) and maximum is 255.  Default is 0.<br/>This sets the limit for the number of overlapping packets.');

$section->addInput(new Form_Checkbox(
	'stream5_detect_anomalies',
	'Detect TCP Anomalies',
	'Detect TCP protocol anomalies. Default is Not Checked.',
	$pconfig['detect_anomalies'] == 'on' ? true:false,
	'on'
));

$group = new Form_Group('Check Session Hijacking');
$group->add(new Form_Checkbox(
	'stream5_check_session_hijacking',
	'',
	'Check for TCP session hijacking.  Default is Not Checked.',
	$pconfig['check_session_hijacking'] == 'on' ? true:false,
	'on'
))->setHelp('This check validates the hardware (MAC) address from both sides of the connection - as established on the 3-way handshake - against subsequent packets received on the session.');
$section->add($group);

$section->addInput(new Form_Checkbox(
	'stream5_require_3whs',
	'Require 3-Way Handshake',
	'Establish sessions only on completion of SYN/SYN-ACK/ACK handshake. Default is Not Checked.',
	$pconfig['require_3whs'] == 'on' ? true:false,
	'on'
));

$section->addInput( new Form_Input(
	'stream5_3whs_startup_timeout',
	'3-Way Handshake Startup Timeout',
	'number',
	$pconfig['startup_3whs_timeout']
))->setAttribute('min', '0')->setAttribute('max', '86400')->setHelp('3-Way Handshake Startup Timeout in seconds.  Minimum is 0 and maximum is 86400 (1 day).  Default is 0.<br/>This allows a grace period for existing sessions to be considered established during that interval immediately after Snort is started.');

$section->addInput(new Form_Checkbox(
	'stream5_no_reassemble_async',
	'Do Not Reassemble Async',
	'Do not queue packets for reassembly if traffic has not been seen in both directions. Default is Not Checked.',
	$pconfig['no_reassemble_async'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'stream5_use_static_footprint_sizes',
	'Use Static Footprint Sizes',
	'Emulate Stream4 behavior for flushing reassembled packets. Default is Not Checked.',
	$pconfig['use_static_footprint_sizes'] == 'on' ? true:false,
	'on'
));

$group = new Form_Group('Do Not Store Large TCP Packets');
$group->add(new Form_Checkbox(
	'stream5_dont_store_lg_pkts',
	'',
	'Do not queue large packets in reassembly buffer to increase performance.  Default is Not Checked.',
	$pconfig['dont_store_lg_pkts'] == 'on' ? true:false,
	'on'
))->setHelp('Enabling this option could result in missed packets.  Recommended setting is not checked.');
$section->add($group);

$btnsave = new Form_Button(
	'save',
	'Save',
	null,
	'fa-save'
);
$btncancel = new Form_Button(
	'cancel',
	'Cancel'
);
$btnsave->addClass('btn-primary')->addClass('btn-default')->setAttribute('title', 'Save Stream5 engine settings and return to Preprocessors tab');
$btncancel->removeClass('btn-primary')->addClass('btn-default')->addClass('btn-warning')->setAttribute('title', 'Cancel changes and return to Preprocessors tab');

$section->addInput(new Form_StaticText(
	null,
	$btnsave . $btncancel
));

$form->add($section);

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

print($form);

?>

<script type="text/javascript">
//<![CDATA[

	function stream5_3whs_enable_change() {
		var hide = ! $('#stream5_require_3whs').prop('checked');

		// Disable the "startup_3whs_timeout" row if stream5_require_3whs disabled
		disableInput('stream5_3whs_startup_timeout', hide);
	}

events.push(function() {

	// ---------- Autocomplete --------------------------------------------------------------------

	var addressarray = <?= json_encode(get_alias_list(array("host", "network", "openvpn"))) ?>;
	var portarray = <?= json_encode(get_alias_list(array("port"))) ?>;

	$('#stream5_bind_to').autocomplete({
		source: addressarray
	});

	$('#stream5_ports_client').autocomplete({
		source: portarray
	});

	$('#stream5_ports_server').autocomplete({
		source: portarray
	});

	$('#stream5_ports_both').autocomplete({
		source: portarray
	});

	//-- click handlers ---------------------------------------------------
	$('#stream5_require_3whs').click(function() {
		stream5_3whs_enable_change();
	});

	// Set initial state of form controls
	stream5_3whs_enable_change();

});
//]]>
</script>
<?php include("foot.inc"); ?>

