<?php
/*
 * snort_ftp_client_engine.php
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
	unset($_SESSION['ftp_client_import']);
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
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['ftp_client_engine'])) {
	$config['installedpackages']['snortglobal']['rule'][$id]['ftp_client_engine'] = array();
}
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['ftp_client_engine']['item'])) {
	$config['installedpackages']['snortglobal']['rule'][$id]['ftp_client_engine']['item'] = array();
}
$a_nat = &$config['installedpackages']['snortglobal']['rule'][$id]['ftp_client_engine']['item'];

$pconfig = array();
if (empty($a_nat[$eng_id])) {
	$def = array( "name" => "engine_{$eng_id}", "bind_to" => "", "max_resp_len" => 256, 
		      "telnet_cmds" => "no", "ignore_telnet_erase_cmds" => "yes", 
		      "bounce" => "yes", "bounce_to_net" => "", "bounce_to_port" => "" );
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
	unset($_SESSION['ftp_client_import']);
	session_write_close();
	header("Location: /snort/snort_preprocessors.php?id={$id}#ftp_telnet_row_ftp_proto_opts");
	exit;
}

// Check for returned "selected alias" if action is import
if ($_GET['act'] == "import") {
	session_start();
	if (($_GET['varname'] == "bind_to" || $_GET['varname'] == "bounce_to_net" || $_GET['varname'] == "bounce_to_port") 
	     && !empty($_GET['varvalue'])) {
		$pconfig[$_GET['varname']] = htmlspecialchars($_GET['varvalue']);
		if(!isset($_SESSION['ftp_client_import']))
			$_SESSION['ftp_client_import'] = array();

		$_SESSION['ftp_client_import'][$_GET['varname']] = $_GET['varvalue'];
		if (isset($_SESSION['ftp_client_import']['bind_to']))
			$pconfig['bind_to'] = $_SESSION['ftp_client_import']['bind_to'];
		if (isset($_SESSION['ftp_client_import']['bounce_to_net']))
			$pconfig['bounce_to_net'] = $_SESSION['ftp_client_import']['bounce_to_net'];
		if (isset($_SESSION['ftp_client_import']['bounce_to_port']))
			$pconfig['bounce_to_port'] = $_SESSION['ftp_client_import']['bounce_to_port'];
	}
	// If "varvalue" is empty, user likely hit CANCEL in Select Dialog,
	// so restore any saved values.
	elseif (empty($_GET['varvalue'])) {
		if (isset($_SESSION['ftp_client_import']['bind_to']))
			$pconfig['bind_to'] = $_SESSION['ftp_client_import']['bind_to'];
		if (isset($_SESSION['ftp_client_import']['bounce_to_net']))
			$pconfig['bounce_to_net'] = $_SESSION['ftp_client_import']['bounce_to_net'];
		if (isset($_SESSION['ftp_client_import']['bounce_to_port']))
			$pconfig['bounce_to_port'] = $_SESSION['ftp_client_import']['bounce_to_port'];
	}
	else {
		unset($_SESSION['ftp_client_import']);
		session_write_close();
	}
}

if ($_POST['save']) {

	// Clear and close out any session variable we created
	session_start();
	unset($_SESSION['ftp_client_import']);
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

	// Validate BOUNCE-TO Alias entries to be sure if one is set, then both are set; since 
	// if you define a BOUNCE-TO address, you must also define the BOUNCE-TO port.
	if ($_POST['ftp_client_bounce_to_net'] && !is_alias($_POST['ftp_client_bounce_to_net']))
		$input_errors[] = gettext("Only aliases are allowed for the FTP Protocol BOUNCE-TO ADDRESS option.");

	if ($_POST['ftp_client_bounce_to_port'] && !is_alias($_POST['ftp_client_bounce_to_port']))
		$input_errors[] = gettext("Only aliases are allowed for the FTP Protocol BOUNCE-TO PORT option.");

	if ($_POST['ftp_client_bounce_to_net'] && empty($_POST['ftp_client_bounce_to_port']))
		$input_errors[] = gettext("FTP Protocol BOUNCE-TO PORT cannot be empty when BOUNCE-TO ADDRESS is set.");

	if ($_POST['ftp_client_bounce_to_port'] && empty($_POST['ftp_client_bounce_to_net']))
		$input_errors[] = gettext("FTP Protocol BOUNCE-TO ADDRESS cannot be empty when BOUNCE-TO PORT is set.");

	// Validate the BOUNCE-TO Alias entries for correct format of their defined values.  BOUNCE-TO ADDRESS must be
	// a valid single IP, and BOUNCE-TO PORT must be either a single port value or a port range value.  Provide 
	// detailed error messages for the user that explain any problems.
	if ($_POST['ftp_client_bounce_to_net'] && $_POST['ftp_client_bounce_to_port']) {
		if (!snort_is_single_addr_alias($_POST['ftp_client_bounce_to_net'])){
			$net = trim(filter_expand_alias($_POST['ftp_client_bounce_to_net']));
			$net = preg_replace('/\s+/', ',', $net);
			$msg = gettext("The FTP Protocol BOUNCE-TO ADDRESS parameter must be a single IP network or address, ");
			$msg .= gettext("so the supplied Alias must be defined as a single address or network in CIDR form.  ");
			$msg .= gettext("The Alias [ {$_POST['ftp_client_bounce_to_net']} ] is currently defined as [ {$net} ].");
			$input_errors[] = $msg;
		}
		$port = trim(filter_expand_alias($_POST['ftp_client_bounce_to_port']));
		$port = preg_replace('/\s+/', ',', $port);
		if (!is_port($port) && !is_portrange($port)) {
			$msg = gettext("The FTP Protocol BOUNCE-TO PORT parameter must be a single port or port-range, ");
			$msg .= gettext("so the supplied Alias must be defined as a single port or port-range value.  ");
			$msg .= gettext("The Alias [ {$_POST['ftp_client_bounce_to_port']} ] is currently defined as [ {$port} ].");
			$input_errors[] = $msg;
		}
	}

	$engine['bounce_to_net'] = $_POST['ftp_client_bounce_to_net'];
	$engine['bounce_to_port'] = $_POST['ftp_client_bounce_to_port'];
	$engine['telnet_cmds'] = $_POST['ftp_telnet_cmds'] ? 'yes' : 'no';
	$engine['ignore_telnet_erase_cmds'] = $_POST['ftp_ignore_telnet_erase_cmds'] ? 'yes' : 'no';
	$engine['bounce'] = $_POST['ftp_client_bounce_detect'] ? 'yes' : 'no';
	$engine['max_resp_len'] = $_POST['ftp_max_resp_len'];

	/* Can only have one "all" Bind_To address */
	if ($engine['bind_to'] == "all" && $engine['name'] <> "default") {
		$input_errors[] = gettext("Only one default FTP Engine can be bound to all addresses.");
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
		write_config("Snort pkg: modified ftp_telnet_client engine settings.");

		header("Location: /snort/snort_preprocessors.php?id={$id}#ftp_telnet_row_ftp_proto_opts");
		exit;
	}
}

$if_friendly = convert_friendly_interface_to_friendly_descr($config['installedpackages']['snortglobal']['rule'][$id]['interface']);
$pgtitle = array(gettext("Services"), gettext("Snort"), gettext("FTP Preprocessor Client Engine"), gettext("{$if_friendly}"));
include("head.inc");

if ($input_errors) print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);

$form = new Form(FALSE);
$section = new Form_Section('Snort Target-Based FTP Client Engine Configuration');

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

$frag3_in = new Form_Input(
	'ftp_max_resp_len',
	'Maximum Response Length',
	'number',
	$pconfig['max_resp_len']
);
$frag3_in->setAttribute('min', '0');
$frag3_in->setHelp(sprintf(gettext('Max FTP command response length accepted by client.  Enter 0 to disable.  Default is 256%sThis specifies the maximum allowed response length to an FTP command accepted by the client.  It can be used as a basic buffer overflow detection.'),'<br/>'));
$section->addInput($frag3_in);

$section->addInput(new Form_Checkbox(
	'ftp_client_bounce_detect',
	'Bounce Detection',
	'Enable detection and alerting of FTP bounce attacks.  Default is Checked.',
	$pconfig['bounce'] == 'yes' ? true:false,
	'yes'
));

$bind_to = new Form_Input(
	'ftp_client_bounce_to_net',
	'',
	'text',
	$pconfig['bounce_to_net']
);
$bind_to->setAttribute('title', trim(filter_expand_alias($pconfig['bounce_to_net'])));
$bind_to->setHelp('Default is blank.  Supplied value must be a pre-configured IP Alias.');
$btnaliases = new Form_Button(
	'btnSelectAlias',
	' ' . 'Aliases',
	'snort_select_alias.php?id=' . $id . '&eng_id=<?=' . $eng_id . '&type=host|network&varname=bounce_to_net&act=import&returl=' . urlencode($_SERVER['PHP_SELF']),
	'fa-search-plus'
);
$btnaliases->removeClass('btn-primary')->addClass('btn-default')->addClass('btn-success')->addClass('btn-sm');
$btnaliases->setAttribute('title', gettext("Select an existing IP alias"));
$group = new Form_Group('Bounce-To Address');
$group->add($bind_to);
$group->add($btnaliases);
$section->add($group);

$bind_to = new Form_Input(
	'ftp_client_bounce_to_port',
	'',
	'text',
	$pconfig['bounce_to_port']
);
$bind_to->setAttribute('title', trim(filter_expand_alias($pconfig['bounce_to_port'])));
$bind_to->setHelp('Default is blank.  Supplied value must be a pre-configured Port Alias.');
$btnaliases = new Form_Button(
	'btnSelectAlias',
	' ' . 'Aliases',
	'snort_select_alias.php?id=' . $id . '&eng_id=<?=' . $eng_id . '&type=port&varname=bounce_to_port&act=import&returl=' . urlencode($_SERVER['PHP_SELF']),
	'fa-search-plus'
);
$btnaliases->removeClass('btn-primary')->addClass('btn-default')->addClass('btn-success')->addClass('btn-sm');
$btnaliases->setAttribute('title', gettext("Select an existing port alias"));
$group = new Form_Group('Bounce-To Port');
$group->add($bind_to);
$group->add($btnaliases);
$section->add($group);

$msg = '<span class="help-block">' . gettext("When the Bounce Detection option is enabled, this allows the PORT command to use the address and port (or inclusive port range) specified above ");
$msg .= gettext("without generating an alert.  It can be used with proxied FTP connections where the FTP data channel is different from the client.") . '</span>';
$msg .= '<br/><span class="text-info"><b>' . gettext("Note: ") . '</b>' . gettext("Supplied value must be a pre-configured Alias or left blank.  ");
$msg .= gettext("Leave these settings at their defaults unless you are proxying FTP connections.") . '</span>';
$section->addInput( new Form_StaticText (
	null,
	$msg
));

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
$btnsave->addClass('btn-primary')->addClass('btn-default')->setAttribute('title', 'Save FTP Client engine settings and return to Preprocessors tab');
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

	function ftp_client_bounce_enable_change() {
		var hide = ! $('#ftp_client_bounce_detect').prop('checked');

		// Disable the "ftp_client_bounce_to_net and ftp_client_bounce_to_port" controls if 
		// ftp bounce detection is disabled.
		disableInput('ftp_client_bounce_to_net', hide);
		disableInput('ftp_client_bounce_to_port', hide);
	}

events.push(function() {

	// ---------- Autocomplete --------------------------------------------------------------------

	var addressarray = <?= json_encode(get_alias_list(array("host", "network", "openvpn"))) ?>;
	var portarray = <?= json_encode(get_alias_list("port")) ?>;

	$('#ftp_bind_to').autocomplete({
		source: addressarray
	});
	$('#ftp_client_bounce_to_net').autocomplete({
		source: addressarray
	});
	$('#ftp_client_bounce_to_port').autocomplete({
		source: portarray
	});

	//-- click handlers ---------------------------------------------------
	$('#ftp_client_bounce_detect').click(function() {
		ftp_client_bounce_enable_change();
	});

	// Set initial state of form controls
	ftp_client_bounce_enable_change();

});
//]]>
</script>

<?php include("foot.inc"); ?>

