<?php
/*
 * snort_ftp_server_engine.php
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

// Grab any QUERY STRING or POST variables
if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

if (isset($_POST['eng_id']) && isset($_POST['eng_id']))
	$eng_id = $_POST['eng_id'];
elseif (isset($_GET['eng_id']) && is_numericint($_GET['eng_id']))
	$eng_id = htmlspecialchars($_GET['eng_id']);

if (is_null($id)) {
	// Clear and close out any session variable we created
	session_start();
	unset($_SESSION['ftp_server_import']);
	session_write_close();
 	header("Location: /snort/snort_interfaces.php");
	exit;
}

if (!is_array($config['installedpackages']['snortglobal']['rule'])) {
	$config['installedpackages']['snortglobal']['rule'] = array();
}
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id])) {
	$config['installedpackages']['snortglobal']['rule'][$id] = array();
}
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['ftp_server_engine'])) {
	$config['installedpackages']['snortglobal']['rule'][$id]['ftp_server_engine'] = array();
}
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['ftp_server_engine']['item'])) {
	$config['installedpackages']['snortglobal']['rule'][$id]['ftp_server_engine']['item'] = array();
}
$a_nat = &$config['installedpackages']['snortglobal']['rule'][$id]['ftp_server_engine']['item'];

$pconfig = array();
if (empty($a_nat[$eng_id])) {
	$def = array( "name" => "engine_{$eng_id}", "bind_to" => "", "ports" => "default", 
		      "telnet_cmds" => "no", "ignore_telnet_erase_cmds" => "yes", 
		      "ignore_data_chan" => "no", "def_max_param_len" => 100 );
	// See if this is initial entry and set to "default" if true
	if ($eng_id < 1) {
		$def['name'] = "default";
		$def['bind_to'] = "all";
	}
	$pconfig = $def;
}
else
	$pconfig = $a_nat[$eng_id];

if ($_POST['cancel']) {
	// Clear and close out any session variable we created
	session_start();
	unset($_SESSION['ftp_server_import']);
	session_write_close();
	header("Location: /snort/snort_preprocessors.php?id={$id}#ftp_telnet_row_ftp_proto_opts");
	exit;
}

// Check for returned "selected alias" if action is import
if ($_GET['act'] == "import") {
	session_start();
	if (($_GET['varname'] == "bind_to" || $_GET['varname'] == "ports") 
	     && !empty($_GET['varvalue'])) {
		$pconfig[$_GET['varname']] = htmlspecialchars($_GET['varvalue']);
		if(!isset($_SESSION['ftp_server_import']))
			$_SESSION['ftp_server_import'] = array();

		$_SESSION['ftp_server_import'][$_GET['varname']] = $_GET['varvalue'];
		if (isset($_SESSION['ftp_server_import']['bind_to']))
			$pconfig['bind_to'] = $_SESSION['ftp_server_import']['bind_to'];
		if (isset($_SESSION['ftp_server_import']['ports']))
			$pconfig['ports'] = $_SESSION['ftp_server_import']['ports'];
	}
	// If "varvalue" is empty, user likely hit CANCEL in Select Dialog,
	// so restore any saved values.
	elseif (empty($_GET['varvalue'])) {
		if (isset($_SESSION['ftp_server_import']['bind_to']))
			$pconfig['bind_to'] = $_SESSION['ftp_server_import']['bind_to'];
		if (isset($_SESSION['ftp_server_import']['ports']))
			$pconfig['ports'] = $_SESSION['ftp_server_import']['ports'];
	}
	else {
		unset($_SESSION['ftp_server_import']);
		session_write_close();
	}
}

if ($_POST['save']) {

	// Clear and close out any session variable we created
	session_start();
	unset($_SESSION['ftp_server_import']);
	session_write_close();

	/* Grab all the POST values and save in new temp array */
	$engine = array();
	if ($_POST['ftp_name']) { $engine['name'] = trim($_POST['ftp_name']); } else { $engine['name'] = "default"; }
	if ($_POST['ftp_bind_to']) {
		if (is_alias($_POST['ftp_bind_to']))
			$engine['bind_to'] = $_POST['ftp_bind_to'];
		elseif (strtolower(trim($_POST['ftp_bind_to'])) == "all")
			$engine['bind_to'] = "all";
		else
			$input_errors[] = gettext("You must provide a valid Alias or the reserved keyword 'all' for the 'Bind-To IP Address' value.");
	}
	else {
		$input_errors[] = gettext("The 'Bind-To IP Address' value cannot be blank.  Provide a valid Alias or the reserved keyword 'all'.");
	}

	if ($_POST['ftp_ports']) {
		if ($_POST['ftp_ports'] == "default")
			$engine['ports'] = $_POST['ftp_ports'];
		elseif (is_alias($_POST['ftp_ports']))
			$engine['ports'] = $_POST['ftp_ports'];
		else
			$input_errors[] = gettext("The value for Ports must be a valid Alias name or the keyword 'default'.");
	}
	else
		$engine['ports'] = 21;

	$engine['telnet_cmds'] = $_POST['ftp_telnet_cmds'] ? 'yes' : 'no';
	$engine['ignore_telnet_erase_cmds'] = $_POST['ftp_ignore_telnet_erase_cmds'] ? 'yes' : 'no';
	$engine['ignore_data_chan'] = $_POST['ftp_ignore_data_chan'] ? 'yes' : 'no';
	$engine['def_max_param_len'] = $_POST['ftp_def_max_param_len'];


	/* Can only have one "all" Bind_To address */
	if ($engine['bind_to'] == "all" && $engine['name'] <> "default") {
		$input_errors[] = gettext("Only one default ftp Engine can be bound to all addresses.");
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
		write_config("Snort pkg: modified ftp_telnet_server engine settings.");

		header("Location: /snort/snort_preprocessors.php?id={$id}#ftp_telnet_row_ftp_proto_opts");
		exit;
	}
}

$if_friendly = convert_friendly_interface_to_friendly_descr($config['installedpackages']['snortglobal']['rule'][$id]['interface']);
$pgtitle = array(gettext("Services"), gettext("Snort"), gettext("FTP Preprocessor Server Engine"), gettext("{$if_friendly}"));
include("head.inc");

if ($input_errors) print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);

$form = new Form(FALSE);
$section = new Form_Section('Snort Target-Based FTP Server Engine Configuration');

$engine_name = new Form_Input(
	'ftp_name',
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
		'ftp_bind_to',
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
		'ftp_bind_to',
		'Bind-To IP Address Alias',
		'text',
		$pconfig['bind_to']
	))->setReadonly()->setHelp('The default engine is required and only runs for packets with destination addresses not matching other engine IP Lists.');
}

$bind_to = new Form_Input(
	'ftp_ports',
	'',
	'text',
	$pconfig['ports']
);
$bind_to->setAttribute('title', trim(filter_expand_alias($pconfig['ports'])));
$bind_to->setHelp('Specify which ports to check for FTP data.  Default value is <em>default</em>');
$btnaliases = new Form_Button(
	'btnSelectAlias',
	' ' . 'Aliases',
	'snort_select_alias.php?id=' . $id . '&eng_id=<?=' . $eng_id . '&type=port&varname=ports&act=import&returl=' . urlencode($_SERVER['PHP_SELF']),
	'fa-search-plus'
);
$btnaliases->removeClass('btn-primary')->addClass('btn-default')->addClass('btn-success')->addClass('btn-sm');
$btnaliases->setAttribute('title', gettext("Select an existing port alias"));
$group = new Form_Group('Ports');
$group->add($bind_to);
$group->add($btnaliases);
$msg = gettext("Using <em>default</em> will include the FTP Ports defined on the ") . '<a href="snort_define_servers.php?id={$id}" title="';
$msg .= gettext("Go to {$if_friendly} Variables tab to define custom port variables") . '">' . gettext("VARIABLES") . '</a>';
$msg .= gettext(" tab.  Specific ports for this server can be specified here using a pre-defined Alias.") . '<br/>';
$msg .= gettext("NOTE:  Supplied value must be a pre-configured Alias or the keyword 'default'.");
$group->setHelp($msg);
$section->add($group);

$section->addInput(new Form_Checkbox(
	'ftp_telnet_cmds',
	'Detect Telnet Commands',
	'Alert when Telnet commands are seen on the FTP command channel.  Default is Not Checked.',
	$pconfig['telnet_cmds'] == 'yes' ? true:false,
	'yes'
));

$section->addInput(new Form_Checkbox(
	'ftp_ignore_telnet_erase_cmds',
	'Ignore Telnet Erase Commands',
	'Ignore Telnet escape sequences for erase character and erase line when normalizing FTP command channel.  Default is Checked.',
	$pconfig['ignore_telnet_erase_cmds'] == 'yes' ? true:false,
	'yes'
));

$group = new Form_Group('Ignore Data Channel');
$group->add(new Form_Checkbox(
	'ftp_ignore_data_chan',
	'',
	'Force Snort to ignore the FTP data channel connections.  Default is Not Checked.',
	$pconfig['ignore_data_chan'] == 'yes' ? true:false,
	'yes'
))->setHelp('When checked, NO INSPECTION other than state will be performed on the data channel.  Enabling this option can improve performance for large FTP transfers from trusted servers.');
$section->add($group);

$section->addInput( new Form_Input(
	'ftp_def_max_param_len',
	'Default Max Allowed Parameter Length',
	'number',
	$pconfig['def_max_param_len']
))->setAttribute('min', '0')->setHelp('Default allowed maximum parameter length for an FTP command.  Enter 0 to disable.  Default is 100.<br/>This specifies the maximum allowed parameter length for and FTP command.  It can be used as a basic buffer overflow detection.');

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
$btnsave->addClass('btn-primary')->addClass('btn-default')->setAttribute('title', 'Save FTP Server engine settings and return to Preprocessors tab');
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

	var addressarray=new Array(<?php echo $aliasesaddr; ?>);
	var portarray=new Array(<?php echo $aliasesport; ?>);

events.push(function() {

	// ---------- Autocomplete --------------------------------------------------------------------

	var addressarray = <?= json_encode(get_alias_list(array("host", "network", "openvpn"))) ?>;
	var portarray = <?= json_encode(get_alias_list("port")) ?>;

	$('#ftp_bind_to').autocomplete({
		source: addressarray
	});
	$('#ftp_ports').autocomplete({
		source: portarray
	});

});

//]]>
</script>

<?php include("foot.inc"); ?>

