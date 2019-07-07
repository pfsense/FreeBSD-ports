<?php
/*
 * snort_frag3_engine.php
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

// Grab the incoming QUERY STRING or POST variables
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

if (isset($_POST['eng_id']) && isset($_POST['eng_id']))
	$eng_id = $_POST['eng_id'];
elseif (isset($_GET['eng_id']) && is_numericint($_GET['eng_id']))
	$eng_id = htmlspecialchars($_GET['eng_id']);

if (is_null($id)) {
 	header("Location: /snort/snort_interfaces.php");
	exit;
}

if (!is_array($config['installedpackages']['snortglobal']['rule'])) {
	$config['installedpackages']['snortglobal']['rule'] = array();
}
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id])) {
	$config['installedpackages']['snortglobal']['rule'][$id] = array();
}
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['frag3_engine'])) {
	$config['installedpackages']['snortglobal']['rule'][$id]['frag3_engine'] = array();
}
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['frag3_engine']['item'])) {
	$config['installedpackages']['snortglobal']['rule'][$id]['frag3_engine']['item'] = array();
}
$a_nat = &$config['installedpackages']['snortglobal']['rule'][$id]['frag3_engine']['item'];

$pconfig = array();
if (empty($a_nat[$eng_id])) {
	$def = array( "name" => "engine_{$eng_id}", "bind_to" => "", "policy" => "bsd", 
		      "timeout" => 60, "min_ttl" => 1, "detect_anomalies" => "on", 
		      "overlap_limit" => 0, "min_frag_len" => 0 );
	// See if this is initial entry and set to "default" if true
	if ($eng_id < 1) {
		$def['name'] = "default";
		$def['bind_to'] = "all";
	}
	$pconfig = $def;
}
else {
	$pconfig = $a_nat[$eng_id];

	// Check for any empty values and set sensible defaults
	if (empty($pconfig['policy']))
		$pconfig['policy'] = "bsd";
	if (empty($pconfig['timeout']))
		$pconfig['timeout'] = 60;
	if (empty($pconfig['min_ttl']))
		$pconfig['min_ttl'] = 1;
	if (empty($pconfig['detect_anomalies']))
		$pconfig['detect_anomalies'] = "on";
	if (empty($pconfig['overlap_limit']))
		$pconfig['overlap_limit'] = 0;
	if (empty($pconfig['min_frag_len']))
		$pconfig['min_frag_len'] = 0;
}

if ($_POST['cancel']) {
	header("Location: /snort/snort_preprocessors.php?id={$id}#frag3_row");
	exit;
}

// Check for returned "selected alias" if action is import
if ($_GET['act'] == "import") {
	if ($_GET['varname'] == "bind_to" && !empty($_GET['varvalue']))
		$pconfig[$_GET['varname']] = htmlspecialchars($_GET['varvalue']);
}

if ($_POST['save']) {

	/* Grab all the POST values and save in new temp array */
	$engine = array();
	if ($_POST['frag3_name']) { $engine['name'] = trim($_POST['frag3_name']); } else { $engine['name'] = "default"; }
	if ($_POST['frag3_bind_to']) {
		if (is_alias($_POST['frag3_bind_to']))
			$engine['bind_to'] = $_POST['frag3_bind_to'];
		elseif (strtolower(trim($_POST['frag3_bind_to'])) == "all")
			$engine['bind_to'] = "all";
		else
			$input_errors[] = gettext("You must provide a valid Alias or the reserved keyword 'all' for the 'Bind-To IP Address' value.");
	}
	else {
		$input_errors[] = gettext("The 'Bind-To IP Address' value cannot be blank.  Provide a valid Alias or the reserved keyword 'all'.");
	}

	/* Validate the text input fields before saving */
	if (!empty($_POST['frag3_timeout']) || $_POST['frag3_timeout'] == 0) {
		$engine['timeout'] = $_POST['frag3_timeout'];
		if (!is_numeric($_POST['frag3_timeout']) || $_POST['frag3_timeout'] < 1)
			$input_errors[] = gettext("The value for Timeout must be numeric and greater than zero.");
	}
	else
		$engine['timeout'] = 60;

	if (!empty($_POST['frag3_min_ttl']) || $_POST['frag3_min_ttl'] == 0) {
		$engine['min_ttl'] = $_POST['frag3_min_ttl'];
		if ($_POST['frag3_min_ttl'] < 1 || $_POST['frag3_min_ttl'] > 255)
			$input_errors[] = gettext("The value for Minimum_Time-To-Live must be between 1 and 255.");
	}
	else
		$engine['min_ttl'] = 1;

	if (!empty($_POST['frag3_overlap_limit']) || $_POST['frag3_overlap_limit'] == 0) {
		$engine['overlap_limit'] = $_POST['frag3_overlap_limit'];
		if (!is_numeric($_POST['frag3_overlap_limit']) || $_POST['frag3_overlap_limit'] < 0)
			$input_errors[] = gettext("The value for Overlap_Limit must be a number greater than or equal to zero.");
	}
	else
		$engine['overlap_limit'] = 0;

	if (!empty($_POST['frag3_min_frag_len']) || $_POST['frag3_min_frag_len'] == 0) {
		$engine['min_frag_len'] = $_POST['frag3_min_frag_len'];
		if (!is_numeric($_POST['frag3_min_frag_len']) || $_POST['frag3_min_frag_len'] < 0)
			$input_errors[] = gettext("The value for Min_Fragment_Length must be a number greater than or equal to zero.");
	}
	else
		$engine['min_frag_len'] = 0;

	if ($_POST['frag3_policy']) { $engine['policy'] = $_POST['frag3_policy']; } else { $engine['policy'] = "bsd"; }
	$engine['detect_anomalies'] = $_POST['frag3_detect_anomalies'] ? 'on' : 'off';

	/* Can only have one "all" Bind_To address */
	if ($engine['bind_to'] == "all" && $engine['name'] <> "default") {
		$input_errors[] = gettext("Only one default Frag3 Engine can be bound to all addresses.");
		$pconfig = $engine;
	}

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
		write_config("Snort pkg: modified frag3 engine settings.");

		header("Location: /snort/snort_preprocessors.php?id={$id}#frag3_row");
		exit;
	}
}

$if_friendly = convert_friendly_interface_to_friendly_descr($config['installedpackages']['snortglobal']['rule'][$id]['interface']);
$pgtitle = array(gettext("Services"), gettext("Snort"), gettext("Frag3 Preprocessor Engine"), gettext("{$if_friendly}"));
include("head.inc");

if ($input_errors) print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);

$form = new Form(FALSE);
$section = new Form_Section('Snort Target-Based IP Defragmentation Engine Configuration');

$engine_name = new Form_Input(
	'frag3_name',
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
		'frag3_bind_to',
		'',
		'text',
		$pconfig['bind_to']
	);
	$bind_to->setAttribute('title', trim(filter_expand_alias($pconfig['bind_to'])));
	$bind_to->setHelp('IP List to bind this engine to. (Cannot be blank)');
	$btnaliases = new Form_Button(
		'btnSelectAlias',
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
		'frag3_bind_to',
		'Bind-To IP Address Alias',
		'text',
		$pconfig['bind_to']
	))->setReadonly()->setHelp('The default engine is required and only runs for packets with destination addresses not matching other engine IP Lists.');
}

$section->addInput(new Form_Select(
	'frag3_policy',
	'Target Policy',
	$pconfig['policy'],
	array( 'bsd' => 'BSD', 'bsd-right' => 'BSD-Right', 'first' => 'First', 'last' => 'Last', 'linux' => 'Linux', 'solaris' => 'Solaris', 'windows' => 'Windows' )
))->setHelp('Choose the IP fragmentation target policy appropriate for the protected hosts.  The default is BSD.');

$section->addInput( new Form_Input(
	'frag3_timeout',
	'Timeout',
	'number',
	$pconfig['timeout']
))->setHelp(sprintf(gettext('Timeout period in seconds for fragments in the engine.%sFragments in the engine for longer than this period will be automatically dropped.  Default value is 60.'),'<br/>'));

$group = new Form_Group('Minimum Time-to-Live');
$group->add(new Form_Input(
	'frag3_min_ttl',
	'',
	'number',
	$pconfig['min_ttl']
))->setHelp('Minimum acceptable TTL for a fragment in the engine.  The accepted range for this option is 1 - 255.  Default value is 1.');
$section->add($group);

$group = new Form_Group('Detect Anomalies');
$group->add(new Form_Checkbox(
	'frag3_detect_anomalies',
	'',
	'Use Frag3 Engine to detect fragment anomalies.  Default is checked.',
	$pconfig['detect_anomalies'] == 'on' ? true:false,
	'on'
))->setHelp('In order to customize the Overlap Limit and Minimum Fragment Length parameters for this engine, Anomaly Detection must be enabled.');
$section->add($group);

$frag3_in = new Form_Input(
	'frag3_overlap_limit',
	'Overlap Limit',
	'number',
	$pconfig['overlap_limit']
);
$frag3_in->setAttribute('min', '0');
$frag3_in->setHelp(sprintf(gettext('Minimum is 0 (unlimited).  Values greater than zero set the overlap limit.%sThis sets the limit for the number of overlapping fragments allowed per packet.'),'<br/>'));
$section->addInput($frag3_in);

$frag3_in = new Form_Input(
	'frag3_min_frag_len',
	'Minimum Fragment Length',
	'number',
	$pconfig['min_frag_len']
);
$frag3_in->setAttribute('min', '0');
$frag3_in->setHelp(sprintf(gettext('Minimum is 0 (check is disabled).  Values greater than zero enable the check.%sThis defines smallest fragment (payload) that should be considered valid.  Fragments smaller than or equal to this limit are considered malicious.'),'<br/>'));
$section->addInput($frag3_in);

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
$btnsave->addClass('btn-primary')->addClass('btn-default')->setAttribute('title', 'Save Frag3 engine settings and return to Preprocessors tab');
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

	function frag3_enable_change() {
		var hide = ! $('#frag3_detect_anomalies').prop('checked');

		// Hide the "frag3_overlap_limit and frag3_min_frag_len" rows if frag3_detect_anomablies disabled
		disableInput('frag3_overlap_limit', hide);
		disableInput('frag3_min_frag_len', hide);
	}

events.push(function() {

	// ---------- Autocomplete --------------------------------------------------------------------

	var addressarray = <?= json_encode(get_alias_list(array("host", "network", "openvpn"))) ?>;

	$('#frag3_bind_to').autocomplete({
		source: addressarray
	});

	//-- click handlers ---------------------------------------------------
	$('#frag3_detect_anomalies').click(function() {
		frag3_enable_change();
	});

	// Set initial state of form controls
	frag3_enable_change();

});
//]]>
</script>

<?php include("foot.inc"); ?>

