<?php
/*
 * snort_httpinspect_engine.php
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
	unset($_SESSION['http_inspect_import']);
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
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['http_inspect_engine'])) {
	$config['installedpackages']['snortglobal']['rule'][$id]['http_inspect_engine'] = array();
}
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['http_inspect_engine']['item'])) {
	$config['installedpackages']['snortglobal']['rule'][$id]['http_inspect_engine']['item'] = array();
}
$a_nat = &$config['installedpackages']['snortglobal']['rule'][$id]['http_inspect_engine']['item'];

$pconfig = array();
if (empty($a_nat[$eng_id])) {
	$def = array( "name" => "engine_{$eng_id}", "bind_to" => "", "server_profile" => "all", "enable_xff" => "off", 
		      "log_uri" => "off", "log_hostname" => "off", "server_flow_depth" => 65535, "enable_cookie" => "on", 
		      "client_flow_depth" => 1460, "extended_response_inspection" => "on", "no_alerts" => "off", 
		      "unlimited_decompress" => "on", "inspect_gzip" => "on", "normalize_cookies" =>"on", "normalize_headers" => "on", 
		      "normalize_utf" => "on", "normalize_javascript" => "on", "allow_proxy_use" => "off", "inspect_uri_only" => "off", 
		      "max_javascript_whitespaces" => 200, "post_depth" => -1, "max_headers" => 0, "max_spaces" => 0, 
		      "max_header_length" => 0, "ports" => "default" );
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
	if (empty($pconfig['ports']))
		$pconfig['ports'] = "default";
	if (empty($pconfig['server_profile']))
		$pconfig['server_profile'] = "all";
	if (empty($pconfig['enable_xff']))
		$pconfig['enable_xff'] = "off";
	if (empty($pconfig['log_uri']))
		$pconfig['log_uri'] = "off";
	if (empty($pconfig['log_hostname']))
		$pconfig['log_hostname'] = "off";
	if (empty($pconfig['server_flow_depth']) && $pconfig['server_flow_depth'] <> 0)
		$pconfig['server_flow_depth'] = 65535;
	if (empty($pconfig['enable_cookie']))
		$pconfig['enable_cookie'] = "on";
	if (empty($pconfig['client_flow_depth']) && $pconfig['client_flow_depth'] <> 0)
		$pconfig['client_flow_depth'] = 1460;
	if (empty($pconfig['extended_response_inspection']))
		$pconfig['extended_response_inspection'] = "on";
	if (empty($pconfig['no_alerts']))
		$pconfig['no_alerts'] = "off";
	if (empty($pconfig['unlimited_decompress']))
		$pconfig['unlimited_decompress'] = "on";
	if (empty($pconfig['inspect_gzip']))
		$pconfig['inspect_gzip'] = "on";
	if (empty($pconfig['normalize_cookies']))
		$pconfig['normalize_cookies'] = "on";
	if (empty($pconfig['normalize_headers']))
		$pconfig['normalize_headers'] = "on";
	if (empty($pconfig['normalize_utf']))
		$pconfig['normalize_utf'] = "on";
	if (empty($pconfig['normalize_javascript']))
		$pconfig['normalize_javascript'] = "on";
	if (empty($pconfig['allow_proxy_use']))
		$pconfig['allow_proxy_use'] = "off";
	if (empty($pconfig['inspect_uri_only']))
		$pconfig['inspect_uri_only'] = "off";
	if (empty($pconfig['max_javascript_whitespaces']) && $pconfig['max_javascript_whitespaces'] <> 0)
		$pconfig['max_javascript_whitespaces'] = 200;
	if (empty($pconfig['post_depth']) && $pconfig['post_depth'] <> 0)
		$pconfig['post_depth'] = -1;
	if (empty($pconfig['max_headers']))
		$pconfig['max_headers'] = 0;
	if (empty($pconfig['max_spaces']))
		$pconfig['max_spaces'] = 0;
	if (empty($pconfig['max_header_length']))
		$pconfig['max_header_length'] = 0;
}

if ($_POST['cancel']) {
	// Clear and close out any session variable we created
	session_start();
	unset($_SESSION['http_inspect_import']);
	session_write_close();
	header("Location: /snort/snort_preprocessors.php?id={$id}#httpinspect_row");
	exit;
}

// Check for returned "selected alias" if action is import
if ($_GET['act'] == "import") {
	session_start();
	if (($_GET['varname'] == "bind_to" || $_GET['varname'] == "ports") 
	     && !empty($_GET['varvalue'])) {
		$pconfig[$_GET['varname']] = htmlspecialchars($_GET['varvalue']);
			$_SESSION['http_inspect_import'] = array();

		$_SESSION['http_inspect_import'][$_GET['varname']] = $_GET['varvalue'];
		if (isset($_SESSION['http_inspect_import']['bind_to']))
			$pconfig['bind_to'] = $_SESSION['http_inspect_import']['bind_to'];
		if (isset($_SESSION['http_inspect_import']['ports']))
			$pconfig['ports'] = $_SESSION['http_inspect_import']['ports'];
	}
	// If "varvalue" is empty, user likely hit CANCEL in Select Dialog,
	// so restore any saved values.
	elseif (empty($_GET['varvalue'])) {
		if (isset($_SESSION['http_inspect_import']['bind_to']))
			$pconfig['bind_to'] = $_SESSION['http_inspect_import']['bind_to'];
		if (isset($_SESSION['http_inspect_import']['ports']))
			$pconfig['ports'] = $_SESSION['http_inspect_import']['ports'];
	}
	else {
		unset($_SESSION['http_inspect_import']);
		session_write_close();
	}
}

if ($_POST['save']) {

	// Clear and close out any session variable we created
	session_start();
	unset($_SESSION['http_inspect_import']);
	session_write_close();

	// Grab all the POST values and save in new temp array
	$engine = array();
	if ($_POST['httpinspect_name']) { $engine['name'] = trim($_POST['httpinspect_name']); } else { $engine['name'] = "default"; }
	if ($_POST['httpinspect_bind_to']) {
		if (is_alias($_POST['httpinspect_bind_to']))
			$engine['bind_to'] = $_POST['httpinspect_bind_to'];
		elseif (strtolower(trim($_POST['httpinspect_bind_to'])) == "all")
			$engine['bind_to'] = "all";
		else
			$input_errors[] = gettext("You must provide a valid Alias or the reserved keyword 'all' for the 'Bind-To IP Address' value.");
	}
	else {
		$input_errors[] = gettext("The 'Bind-To IP Address' value cannot be blank.  Provide a valid Alias or the reserved keyword 'all'.");
	}
	if ($_POST['httpinspect_ports']) { $engine['ports'] = trim($_POST['httpinspect_ports']); } else { $engine['ports'] = "default"; }

	// Validate the text input fields before saving
	if (!empty($_POST['httpinspect_server_flow_depth']) || $_POST['httpinspect_server_flow_depth'] == 0) {
		$engine['server_flow_depth'] = $_POST['httpinspect_server_flow_depth'];
		if (!is_numeric($_POST['httpinspect_server_flow_depth']) || $_POST['httpinspect_server_flow_depth'] < -1 || $_POST['httpinspect_server_flow_depth'] > 65535)
			$input_errors[] = gettext("The value for Server_Flow_Depth must be numeric and between -1 and 65535.");
	}
	else
		$engine['server_flow_depth'] = 65535;

	if (!empty($_POST['httpinspect_client_flow_depth']) || $_POST['httpinspect_client_flow_depth'] == 0) {
		$engine['client_flow_depth'] = $_POST['httpinspect_client_flow_depth'];
		if (!is_numeric($_POST['httpinspect_client_flow_depth']) || $_POST['httpinspect_client_flow_depth'] < -1 || $_POST['httpinspect_client_flow_depth'] > 1460)
			$input_errors[] = gettext("The value for Client_Flow_Depth must be between -1 and 1460.");
	}
	else
		$engine['client_flow_depth'] = 1460;

	if (!empty($_POST['httpinspect_max_javascript_whitespaces']) || $_POST['httpinspect_max_javascript_whitespaces'] == 0) {
		$engine['max_javascript_whitespaces'] = $_POST['httpinspect_max_javascript_whitespaces'];
		if (!is_numeric($_POST['httpinspect_max_javascript_whitespaces']) || $_POST['httpinspect_max_javascript_whitespaces'] < 0 || $_POST['httpinspect_max_javascript_whitespaces'] > 65535)
			$input_errors[] = gettext("The value for Max_Javascript_Whitespaces must be between 0 and 65535.");
	}
	else
		$engine['max_javascript_whitespaces'] = 200;

	if (!empty($_POST['httpinspect_post_depth']) || $_POST['httpinspect_post_depth'] == 0) {
		$engine['post_depth'] = $_POST['httpinspect_post_depth'];
		if (!is_numeric($_POST['httpinspect_post_depth']) || $_POST['httpinspect_post_depth'] < -1 || $_POST['httpinspect_post_depth'] > 65495)
			$input_errors[] = gettext("The value for Post_Depth must be between -1 and 65495.");
	}
	else
		$engine['post_depth'] = -1;

	if (!empty($_POST['httpinspect_max_headers']) || $_POST['httpinspect_max_headers'] == 0) {
		$engine['max_headers'] = $_POST['httpinspect_max_headers'];
		if (!is_numeric($_POST['httpinspect_max_headers']) || $_POST['httpinspect_max_headers'] < 0 || $_POST['httpinspect_max_headers'] > 65535)
			$input_errors[] = gettext("The value for Max_Headers must be between 0 and 65535.");
	}
	else
		$engine['max_headers'] = 0;

	if (!empty($_POST['httpinspect_max_spaces']) || $_POST['httpinspect_max_spaces'] == 0) {
		$engine['max_spaces'] = $_POST['httpinspect_max_spaces'];
		if (!is_numeric($_POST['httpinspect_max_spaces']) || $_POST['httpinspect_max_spaces'] < 0 || $_POST['httpinspect_max_spaces'] > 65535)
			$input_errors[] = gettext("The value for Max_Spaces must be between 0 and 65535.");
	}
	else
		$engine['max_spaces'] = 0;

	if (!empty($_POST['httpinspect_max_header_length']) || $_POST['httpinspect_max_header_length'] == 0) {
		$engine['max_header_length'] = $_POST['httpinspect_max_header_length'];
		if (!is_numeric($_POST['httpinspect_max_header_length']) || $_POST['httpinspect_max_header_length'] < 0 || $_POST['httpinspect_max_header_length'] > 65535)
			$input_errors[] = gettext("The value for Max_Header_Length must be between 0 and 65535.");
	}
	else
		$engine['max_header_length'] = 0;

	if ($_POST['httpinspect_server_profile']) { $engine['server_profile'] = $_POST['httpinspect_server_profile']; } else { $engine['server_profile'] = "all"; }

	$engine['no_alerts'] = $_POST['httpinspect_no_alerts'] ? 'on' : 'off';
	$engine['enable_xff'] = $_POST['httpinspect_enable_xff'] ? 'on' : 'off';
	$engine['log_uri'] = $_POST['httpinspect_log_uri'] ? 'on' : 'off';
	$engine['log_hostname'] = $_POST['httpinspect_log_hostname'] ? 'on' : 'off';
	$engine['extended_response_inspection'] = $_POST['httpinspect_extended_response_inspection'] ? 'on' : 'off';
	$engine['enable_cookie'] = $_POST['httpinspect_enable_cookie'] ? 'on' : 'off';
	$engine['unlimited_decompress'] = $_POST['httpinspect_unlimited_decompress'] ? 'on' : 'off';
	$engine['inspect_gzip'] = $_POST['httpinspect_inspect_gzip'] ? 'on' : 'off';
	$engine['normalize_cookies'] = $_POST['httpinspect_normalize_cookies'] ? 'on' : 'off';
	$engine['normalize_headers'] = $_POST['httpinspect_normalize_headers'] ? 'on' : 'off';
	$engine['normalize_utf'] = $_POST['httpinspect_normalize_utf'] ? 'on' : 'off';
	$engine['normalize_javascript'] = $_POST['httpinspect_normalize_javascript'] ? 'on' : 'off';
	$engine['allow_proxy_use'] = $_POST['httpinspect_allow_proxy_use'] ? 'on' : 'off';
	$engine['inspect_uri_only'] = $_POST['httpinspect_inspect_uri_only'] ? 'on' : 'off';

	// Can only have one "all" Bind_To address
	if ($engine['bind_to'] == "all" && $engine['name'] <> "default") {
		$input_errors[] = gettext("Only one default http_inspect Engine can be bound to all addresses.");
		$pconfig = $engine;
	}

	// if no errors, write new entry to conf
	if (!$input_errors) {
		if (isset($eng_id) && $a_nat[$eng_id]) {
			$a_nat[$eng_id] = $engine;
		}
		else
			$a_nat[] = $engine;

		// Reorder the engine array to ensure the
		// 'bind_to=all' entry is at the bottom
		// if it contains more than one entry.
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

		// Now write the new engine array to conf
		write_config("Snort pkg: modified http_inspect engine settings.");

		header("Location: /snort/snort_preprocessors.php?id={$id}#httpinspect_row");
		exit;
	}
}

$if_friendly = convert_friendly_interface_to_friendly_descr($config['installedpackages']['snortglobal']['rule'][$id]['interface']);
$pgtitle = array(gettext("Services"), gettext("Snort"), gettext("HTTP_Inspect Preprocessor Engine"), gettext("{$if_friendly}"));
include("head.inc");

if ($input_errors) print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);

$form = new Form(FALSE);
$section = new Form_Section('Snort HTTP Inspection Server Configuration');

$engine_name = new Form_Input(
	'httpinspect_name',
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
		'httpinspect_bind_to',
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
		'httpinspect_bind_to',
		'Bind-To IP Address Alias',
		'text',
		$pconfig['bind_to']
	))->setReadonly()->setHelp('The default engine is required and only runs for packets with destination addresses not matching other engine IP Lists.');
}

$bind_to = new Form_Input(
	'httpinspect_ports',
	'',
	'text',
	$pconfig['ports']
);
$bind_to->setAttribute('title', trim(filter_expand_alias($pconfig['ports'])));
$bind_to->setHelp('Specify which ports to check for HTTP data.  Default value is <em>default</em>');
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
$msg = gettext("Using <em>default</em> will include the HTTP Ports defined on the ") . '<a href="snort_define_servers.php?id={$id}" title="';
$msg .= gettext("Go to {$if_friendly} Variables tab to define custom port variables") . '">' . gettext("VARIABLES") . '</a>';
$msg .= gettext(" tab.  Specific ports for this server can be specified here using a pre-defined Alias.") . '<br/>';
$msg .= gettext("NOTE:  Supplied value must be a pre-configured Alias or the keyword 'default'.");
$group->setHelp($msg);
$section->add($group);

$section->addInput(new Form_Select(
	'httpinspect_server_profile',
	'Server Profile',
	$pconfig['server_profile'],
	array( 'all' => 'All', 'apache' => 'Apache', 'iis' => 'IIS', 'iis4_0' => 'IIS4_0', 'iis5_0' => 'IIS5_0' )
))->setHelp('Choose the profile type of the protected web server.  The default is All.  IIS_4.0 and IIS_5.0 are identical to IIS except they alert on the double decoding vulnerability present in those versions.');

$section->addInput(new Form_Checkbox(
	'httpinspect_no_alerts',
	'No Alerts',
	'Disable Alerts from this engine configuration. Default is Not Checked.',
	$pconfig['no_alerts'] == 'on' ? true:false,
	'on'
));

$group = new Form_Group('Allow Proxy Use');
$group->add(new Form_Checkbox(
	'httpinspect_allow_proxy_use',
	'',
	'Allow proxy use on this server.  Default is Not Checked.',
	$pconfig['allow_proxy_use'] == 'on' ? true:false,
	'on'
))->setHelp('This prevents proxy alerts for this server.  The global option Proxy_Alert must also be enabled, otherwise this setting does nothing.');
$section->add($group);

$section->addInput(new Form_Checkbox(
	'httpinspect_enable_xff',
	'XFF/True-Client-IP',
	'Log original client IP present in X-Forwarded-For or True-Client-IP HTTP headers. Default is Not Checked.',
	$pconfig['enable_xff'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'httpinspect_log_uri',
	'URI Logging',
	'Parse URI data from the HTTP request and log it with other session data. Default is Not Checked.',
	$pconfig['log_uri'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'httpinspect_log_hostname',
	'Hostname Logging',
	'Parse Hostname data from the HTTP request and log it with other session data. Default is Not Checked.',
	$pconfig['log_hostname'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'httpinspect_enable_cookie',
	'Cookie Extraction/Inspection',
	'Enable HTTP cookie extraction and inspection. Default is Checked.',
	$pconfig['enable_cookie'] == 'on' ? true:false,
	'on'
));

$group = new Form_Group('Inspect URI Only');
$group->add(new Form_Checkbox(
	'httpinspect_inspect_uri_only',
	'',
	'Inspect only URI portion of HTTP requests. This is a performance enhancement.  Default is Not Checked.',
	$pconfig['inspect_uri_only'] == 'on' ? true:false,
	'on'
))->setHelp('If this option is used without any uricontent rules, then no inspection will take place. The URI is only inspected with uricontent rules, and if there are none available, then there is nothing to inspect.');
$section->add($group);

$section->addInput(new Form_Checkbox(
	'httpinspect_extended_response_inspection',
	'Extended Response Inspection',
	'Enable extended response inspection to thoroughly inspect the HTTP response. Default is Checked.',
	$pconfig['extended_response_inspection'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'httpinspect_normalize_javascript',
	'Normalize Javascript',
	'Enable Javascript normalization in HTTP response body. Default is Checked.',
	$pconfig['normalize_javascript'] == 'on' ? true:false,
	'on'
));

$section->addInput( new Form_Input(
	'httpinspect_max_javascript_whitespaces',
	'Maximum Javascript Whitespaces',
	'number',
	$pconfig['max_javascript_whitespaces']
))->setAttribute('min', '0')->setAttribute('max', '65535')->setHelp('Maximum consecutive whitespaces allowed in Javascript obfuscated data.  Minimum is 1 and maximum is 65535.  Zero disables this alert.  Default is 200.<br/>This specifies the maximum allowed parameter length for and FTP command.  It can be used as a basic buffer overflow detection.');

$section->addInput(new Form_Checkbox(
	'httpinspect_inspect_gzip',
	'Inspect gzip',
	'Uncompress and inspect compressed data in HTTP response. Default is Checked.',
	$pconfig['inspect_gzip'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'httpinspect_unlimited_decompress',
	'Unlimited Decompress',
	'Decompress unlimited gzip data (across multiple packets). Default is Checked.',
	$pconfig['unlimited_decompress'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'httpinspect_normalize_cookies',
	'Normalize Cookies',
	'Normalize HTTP cookie fields. Default is Checked.',
	$pconfig['normalize_cookies'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'httpinspect_normalize_utf',
	'Normalize UTF',
	'Normalize HTTP response body character sets to 8-bit encoding. Default is Checked.',
	$pconfig['normalize_utf'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Checkbox(
	'httpinspect_normalize_headers',
	'Normalize Headers',
	'Normalize HTTP Header fields. Default is Checked.',
	$pconfig['normalize_headers'] == 'on' ? true:false,
	'on'
));

$group = new Form_Group('Server Flow Depth');
$group->add( new Form_Input(
	'httpinspect_server_flow_depth',
	'',
	'number',
	$pconfig['server_flow_depth']
))->setAttribute('min', '-1')->setAttribute('max', '65535')->setHelp('Amount of HTTP server response payload to inspect.  Minimum is -1 and maximum is 65535.  -1 disables HTTP inspect and 0 enables all HTTP inspect.  Default is 1460.');
$group->setHelp('Snort\'s performance may increase by adjusting this value. Setting this value too low may cause false negatives. Values above 0 are specified in bytes.  Recommended setting is maximum (1460).');
$section->add($group);

$group = new Form_Group('Client Flow Depth');
$group->add( new Form_Input(
	'httpinspect_client_flow_depth',
	'',
	'number',
	$pconfig['client_flow_depth']
))->setAttribute('min', '-1')->setAttribute('max', '1460')->setHelp('Amount of raw HTTP client request payload to inspect.  Minimum is -1 and maximum is 1460.  -1 disables HTTP inspect and 0 enables all HTTP inspect.  Default is 65535.');
$group->setHelp('Snort\'s performance may increase by adjusting this value. Setting this value too low may cause false negatives. Values above 0 are specified in bytes.  Recommended setting is maximum (65535).');
$section->add($group);

$group = new Form_Group('Post Depth');
$group->add( new Form_Input(
	'httpinspect_post_depth',
	'',
	'number',
	$pconfig['post_depth']
))->setAttribute('min', '-1')->setAttribute('max', '65495')->setHelp('Amount of data to inspect in client post message.  Minimum is -1 and maximum is 65495.  -1 ignores all post data and 0 inspects all post data.  Default is -1.');
$group->setHelp('Snort\'s performance may increase by adjusting this value. Values above 0 are specified in bytes.  Recommended setting is -1 (ignore all post data.');
$section->add($group);

$group = new Form_Group('Max Headers');
$group->add( new Form_Input(
	'httpinspect_max_headers',
	'',
	'number',
	$pconfig['max_headers']
))->setAttribute('min', '0')->setAttribute('max', '1024')->setHelp('Sets the maximum number of HTTP client request header fields allowed.  Minimum is 0 and maximum is 65535.  Zero disables this alert.  Default is 0.');
$group->setHelp('Requests that contain more HTTP headers than this value will cause a <em>Max Header</em> alert.');
$section->add($group);

$group = new Form_Group('Max Header Length');
$group->add( new Form_Input(
	'httpinspect_max_header_length',
	'',
	'number',
	$pconfig['max_header_length']
))->setAttribute('min', '0')->setAttribute('max', '65535')->setHelp('Sets the maximum length allowed for an HTTP client request header field.  Minimum is 0 and maximum is 1024.  Zero disables this alert.  Default is 0.');
$group->setHelp('Requests that exceed this limit will cause a <em>Long Header</em> alert.');
$section->add($group);

$group = new Form_Group('Max Spaces');
$group->add( new Form_Input(
	'httpinspect_max_spaces',
	'',
	'number',
	$pconfig['max_spaces']
))->setAttribute('min', '0')->setAttribute('max', '65535')->setHelp('Sets the maximum number of whitespaces allowed with HTTP client request line folding.  Minimum is 0 and maximum is 1024.  Zero disables this alert.  Default is 0.');
$group->setHelp('Request headers folded with whitespaces equal to or greater than this value will cause a <em>Whitespace Saturation</em> alert.');
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
$btnsave->addClass('btn-primary')->addClass('btn-default')->setAttribute('title', 'Save HTTP Inspect engine settings and return to Preprocessors tab');
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

	function extended_response_enable_change() {
		var hide = ! $('#httpinspect_extended_response_inspection').prop('checked');

		// Hide the "httpinspect_inspectgzip, httpinspect_normalize_javascript, 
		// httpinspect_unlimited_decompress httpinspect_max_javascript_whitespaces" 
		// if httpinspect_extended_response_inspection disabled.
		disableInput('httpinspect_inspect_gzip', hide);
		disableInput('httpinspect_normalize_javascript', hide);
		disableInput('httpinspect_unlimited_decompress', hide);
		disableInput('httpinspect_max_javascript_whitespaces', hide);
	}

	function httpinspect_inspectgzip_enable_change() {
		var hide = ! $('#httpinspect_inspect_gzip').prop('checked');

		// Hide the "httpinspect_unlimited_decompress" if httpinspect_inspect_gzip disabled
		disableInput('httpinspect_unlimited_decompress', hide);
	}

	function normalize_javascript_enable_change() {
		var hide = ! $('#httpinspect_normalize_javascript').prop('checked');

		// Hide the "httpinspect_max_javascript_whitespaces" if httpinspect_normalize_javascript disabled
		disableInput('httpinspect_max_javascript_whitespaces', hide);
	}

events.push(function() {

	// ---------- Autocomplete --------------------------------------------------------------------

	var addressarray = <?= json_encode(get_alias_list(array("host", "network", "openvpn"))) ?>;
	var portarray = <?= json_encode(get_alias_list(array("port"))) ?>;

	$('#httpinspect_bind_to').autocomplete({
		source: addressarray
	});

	$('#httpinspect_ports').autocomplete({
		source: portarray
	});

	//-- click handlers ---------------------------------------------------
	$('#httpinspect_extended_response_inspection').click(function() {
		extended_response_enable_change();
	});

	$('#httpinspect_inspect_gzip').click(function() {
		httpinspect_inspectgzip_enable_change();
	});

	$('#httpinspect_normalize_javascript').click(function() {
		normalize_javascript_enable_change();
	});

	// Set initial state of form controls
	extended_response_enable_change();
	normalize_javascript_enable_change();
	httpinspect_inspectgzip_enable_change();

});
//]]>
</script>
<?php include("foot.inc");?>

