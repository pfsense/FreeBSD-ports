<?php
/*
 * snort_httpinspect_engine.php
 * Copyright (C) 2013-2015 Bill Meeks
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
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

if (!is_array($config['installedpackages']['snortglobal']['rule']))
	$config['installedpackages']['snortglobal']['rule'] = array();
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['http_inspect_engine']['item']))
	$config['installedpackages']['snortglobal']['rule'][$id]['http_inspect_engine']['item'] = array();
$a_nat = &$config['installedpackages']['snortglobal']['rule'][$id]['http_inspect_engine']['item'];

$pconfig = array();
if (empty($a_nat[$eng_id])) {
	$def = array( "name" => "engine_{$eng_id}", "bind_to" => "", "server_profile" => "all", "enable_xff" => "off", 
		      "log_uri" => "off", "log_hostname" => "off", "server_flow_depth" => 65535, "enable_cookie" => "on", 
		      "client_flow_depth" => 1460, "extended_response_inspection" => "on", "no_alerts" => "off", 
		      "unlimited_decompress" => "on", "inspect_gzip" => "on", "normalize_cookies" =>"on", "normalize_headers" => "on", 
		      "normalize_utf" => "on", "normalize_javascript" => "on", "allow_proxy_use" => "off", "inspect_uri_only" => "off", 
		      "max_javascript_whitespaces" => 200, "post_depth" => -1, "max_headers" => 0, "max_spaces" => 0, 
		      "max_header_length" => 0, "ports" => "default", "decompress_swf" => "off", "decompress_pdf" => "off" );
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
	if (empty($pconfig['decompress_swf']))
		$pconfig['decompress_swf'] = "off";
	if (empty($pconfig['decompress_pdf']))
		$pconfig['decompress_pdf'] = "off";
}

if ($_POST['Cancel']) {
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
	$engine['decompress_swf'] = $_POST['httpinspect_decompress_swf'] ? 'on' : 'off';
	$engine['decompress_pdf'] = $_POST['httpinspect_decompress_pdf'] ? 'on' : 'off';

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

		// We have saved a preproc config change, so set "dirty" flag
		mark_subsystem_dirty('snort_preprocessors');

		header("Location: /snort/snort_preprocessors.php?id={$id}#httpinspect_row");
		exit;
	}
}

$if_friendly = convert_friendly_interface_to_friendly_descr($config['installedpackages']['snortglobal']['rule'][$id]['interface']);
$pgtitle = gettext("Snort: {$if_friendly} - HTTP_Inspect Preprocessor Engine");
include_once("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" >

<?php
include("fbegin.inc");
if ($input_errors) print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);
?>

<form action="snort_httpinspect_engine.php" method="post" name="iform" id="iform">
<input name="id" type="hidden" value="<?=$id?>">
<input name="eng_id" type="hidden" value="<?=$eng_id?>">
<div id="boxarea">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
<td class="tabcont">
<table width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr>
		<td colspan="2" valign="middle" class="listtopic"><?php echo gettext("HTTP Inspection Server Configuration"); ?></td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Engine Name"); ?></td>
		<td class="vtable">
			<input name="httpinspect_name" type="text" class="formfld unknown" id="httpinspect_name" size="25" maxlength="25" 
			value="<?=htmlspecialchars($pconfig['name']);?>"<?php if (htmlspecialchars($pconfig['name']) == "default") echo " readonly";?>>&nbsp;
			<?php if (htmlspecialchars($pconfig['name']) <> "default") 
					echo gettext("Name or description for this engine.  (Max 25 characters)");
				else
					echo "<span class=\"red\">" . gettext("The name for the 'default' engine is read-only.") . "</span>";?><br/>
			<?php echo gettext("Unique name or description for this engine configuration.  Default value is ") . 
			"<strong>" . gettext("default") . "</strong>"; ?>.<br/>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Bind-To IP Address Alias"); ?></td>
		<td class="vtable">
		<?php if ($pconfig['name'] <> "default") : ?>
			<table width="95%" border="0" cellpadding="2" cellspacing="0">
				<tr>
					<td class="vexpl"><input name="httpinspect_bind_to" type="text" class="formfldalias" id="httpinspect_bind_to" size="32" 
					value="<?=htmlspecialchars($pconfig['bind_to']);?>" title="<?=trim(filter_expand_alias($pconfig['bind_to']));?>" autocomplete="off">&nbsp;
					<?php echo gettext("IP List to bind this engine to. (Cannot be blank)"); ?></td>
					<td align="right"><input type="button" class="formbtns" value="Aliases" onclick="parent.location='snort_select_alias.php?id=<?=$id;?>&eng_id=<?=$eng_id;?>&type=host|network&varname=bind_to&act=import&multi_ip=yes&returl=<?=urlencode($_SERVER['PHP_SELF']);?>'" 
					title="<?php echo gettext("Select an existing IP alias");?>"/></td>
				</tr>
				<tr>
					<td class="vexpl" colspan="2"><?php echo gettext("This engine will only run for packets with destination addresses contained within this IP List.");?></td>
				</tr>
			</table><br/>
			<span class="red"><strong><?php echo gettext("Note: ") . "</strong></span>" . gettext("Supplied value must be a pre-configured Alias or the keyword 'all'.");?>
		<?php else : ?>
			<input name="httpinspect_bind_to" type="text" class="formfldalias" id="httpinspect_bind_to" size="32" 
			value="<?=htmlspecialchars($pconfig['bind_to']);?>" autocomplete="off" readonly>&nbsp;
			<?php echo "<span class=\"red\">" . gettext("IP List for the default engine is read-only and must be 'all'.") . "</span>";?><br/>
			<?php echo gettext("The default engine is required and only runs for packets with destination addresses not matching other engine IP Lists.");?><br/>
		<?php endif ?>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Ports"); ?></td>
		<td class="vtable">
			<table width="95%" border="0" cellpadding="2" cellspacing="0">
				<tr>
					<td class="vexpl"><input name="httpinspect_ports" type="text" class="formfldalias" id="httpinspect_ports" size="25" 
					value="<?=htmlspecialchars($pconfig['ports']);?>" title="<?=trim(filter_expand_alias($pconfig['ports']));?>">
					<?php echo gettext("Specifiy which ports to check for HTTP data.");?></td>
					<td align="right"><input type="button" class="formbtns" value="Aliases" onclick="parent.location='snort_select_alias.php?id=<?=$id;?>&eng_id=<?=$eng_id;?>&type=port&varname=ports&act=import&returl=<?=urlencode($_SERVER['PHP_SELF']);?>'" 
					title="<?php echo gettext("Select an existing port alias");?>"/></td>
				</tr>
				<tr>
					<td class="vexpl" colspan="2"><?php echo gettext("Default value is '") . "<strong>" . gettext("'default'.  ") . "</strong>";?>
					<?php echo gettext("Using 'default' will include the HTTP Ports defined on the ") . "<a href='snort_define_servers.php?id={$id}' title=\"" . 
					gettext("Go to {$if_friendly} Variables tab to define custom port variables") . "\">" . gettext("VARIABLES") . "</a>" . 
					gettext(" tab.  Specific ports for this server can be specified here using a pre-defined Alias.");?></td>
				</tr>
			</table><br/>
			<span class="red"><strong><?php echo gettext("Note: ") . "</strong></span>" . gettext("Supplied value must be a pre-configured Alias or the keyword 'default'.");?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Server Profile");?> </td>
		<td width="78%" class="vtable">
			<select name="httpinspect_server_profile" class="formselect" id="httpinspect_server_profile">
			<?php
			$profile = array('All', 'Apache', 'IIS', 'IIS4_0', 'IIS5_0');
			foreach ($profile as $val): ?>
			<option value="<?=strtolower($val);?>"
			<?php if (strtolower($val) == $pconfig['server_profile']) echo "selected"; ?>>
				<?=gettext($val);?></option>
				<?php endforeach;?>
			</select>&nbsp;&nbsp;<?php echo gettext("Choose the profile type of the protected web server.  The default is ") . 
			"<strong>" . gettext("All") . "</strong>";?><br/>
			<?php echo gettext("IIS_4.0 and IIS_5.0 are identical to IIS except they alert on the ") . 
			gettext("double decoding vulnerability present in those versions.");?><br/>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("No Alerts");?></td>
		<td width="78%" class="vtable"><input name="httpinspect_no_alerts" 
			type="checkbox" value="on" id="httpinspect_no_alerts"  
			<?php if ($pconfig['no_alerts']=="on") echo "checked";?>>
			<?php echo gettext("Disable Alerts from this engine configuration. Default is ");?>
 			<strong><?php echo gettext("Not Checked");?></strong>.</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Allow Proxy Use");?></td>
		<td width="78%" class="vtable"><input name="httpinspect_allow_proxy_use" 
			type="checkbox" value="on" id="httpinspect_allow_proxy_use" 
			<?php if ($pconfig['allow_proxy_use']=="on") echo "checked";?>>
			<?php echo gettext("Allow proxy use on this server. " .
				"Default is ");?>
			<strong><?php echo gettext("Not Checked");?></strong>.<br/><br/>
			<span class="red"><strong><?php echo gettext("Note: ") . "</strong></span>" . 
			gettext("This prevents proxy alerts for this server.  The global option Proxy_Alert must also be " . 
			"enabled, otherwise this setting does nothing.");?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("XFF/True-Client-IP");?></td>
		<td width="78%" class="vtable"><input name="httpinspect_enable_xff" 
			type="checkbox" value="on" id="httpinspect_enable_xff"  
			<?php if ($pconfig['enable_xff']=="on") echo "checked";?>>
			<?php echo gettext("Log original client IP present in X-Forwarded-For or True-Client-IP " .
				"HTTP headers.  Default is ");?>
			<strong><?php echo gettext("Not Checked");?></strong>.</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("URI Logging"); ?></td>
		<td width="78%" class="vtable"><input name="httpinspect_log_uri" 
			type="checkbox" value="on" id="hhttpinspect_log_uri"  
			<?php if ($pconfig['log_uri']=="on") echo "checked"; ?>>
			<?php echo gettext("Parse URI data from the HTTP request and log it with other session data." .
				"  Default is "); ?>
			<strong><?php echo gettext("Not Checked");?></strong>.</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Hostname Logging");?></td>
		<td width="78%" class="vtable"><input name="httpinspect_log_hostname" 
			type="checkbox" value="on" id="httpinspect_log_hostname"  
			<?php if ($pconfig['log_hostname']=="on") echo "checked";?>>
			<?php echo gettext("Parse Hostname data from the HTTP request and log it with other session data." .
				"  Default is ");?>
			<strong><?php echo gettext("Not Checked");?></strong>.</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Cookie Extraction/Inspection");?></td>
		<td width="78%" class="vtable"><input name="httpinspect_enable_cookie" 
			type="checkbox" value="on" id="httpinspect_enable_cookie" 
			<?php if ($pconfig['enable_cookie']=="on") echo "checked";?>>
			<?php echo gettext("Enable HTTP cookie extraction and inspection. " .
				"Default is ");?>
			<strong><?php echo gettext("Checked");?></strong>.</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Inspect URI Only");?></td>
		<td width="78%" class="vtable"><input name="httpinspect_inspect_uri_only" 
			type="checkbox" value="on" id="httpinspect_inspect_uri_only" 
			<?php if ($pconfig['inspect_uri_only']=="on") echo "checked";?>>
			<?php echo gettext("Inspect only URI portion of HTTP requests. This is a performance enhancement. " .
				"Default is ");?>
			<strong><?php echo gettext("Not Checked");?></strong>.<br/><br/>
			<span class="red"><strong><?php echo gettext("Note: ") . "</strong></span>" . 
			gettext("If this option is used without any uricontent rules, then no inspection will take place. " . 
			"The URI is only inspected with uricontent rules, and if there are none available, then there is nothing to inspect.");?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Extended Response Inspection");?></td>
		<td width="78%" class="vtable"><input name="httpinspect_extended_response_inspection" 
			type="checkbox" value="on" id="httpinspect_extended_response_inspection" onclick="extended_response_enable_change();" 
			<?php if ($pconfig['extended_response_inspection']=="on") echo "checked";?>>
			<?php echo gettext("Enable extended response inspection to thoroughly inspect the HTTP response. " .
				"Default is ");?>
			<strong><?php echo gettext("Checked");?></strong>.</td>
	</tr>
	<tr id="httpinspect_normalizejavascript_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Normalize Javascript");?></td>
		<td width="78%" class="vtable"><input name="httpinspect_normalize_javascript" 
			type="checkbox" value="on" id="httpinspect_normalize_javascript" onclick="normalize_javascript_enable_change();" 
			<?php if ($pconfig['normalize_javascript']=="on") echo "checked";?>>
			<?php echo gettext("Enable Javascript normalization in HTTP response body. " .
				"Default is ");?>
			<strong><?php echo gettext("Checked");?></strong>.</td>
	</tr>
	<tr id="httpinspect_maxjavascriptwhitespaces_row">
		<td valign="top" class="vncell"><?php echo gettext("Maximum Javascript Whitespaces"); ?></td>
		<td class="vtable">
			<table width="95%" border="0" cellpadding="2" cellspacing="0">
				<tr>
					<td valign="top"><input name="httpinspect_max_javascript_whitespaces" type="text" class="formfld unknown"
					id="httpinspect_max_javascript_whitespaces" size="6" 
					value="<?=htmlspecialchars($pconfig['max_javascript_whitespaces']);?>"></td>
					<td class="vexpl" valign="top"><?php echo gettext("Maximum consecutive whitespaces allowed in Javascript obfuscated data.  ");?>
					<?php echo gettext("Minimum is ") . "<strong>" . gettext("1") . "</strong>" . gettext(" and maximum is ") . 
					"<strong>" . gettext("65535") . "</strong>" . gettext("  (") . "<strong>" . gettext("0") . 
					"</strong>" . gettext(" disables this alert). "). gettext("The default value is ") . 
					"<strong>" . gettext("200") . "</strong>."?></td>
				</tr>
			</table>
		</td>
	</tr>
	<tr id="httpinspect_inspectgzip_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Inspect gzip");?></td>
		<td width="78%" class="vtable"><input name="httpinspect_inspect_gzip" 
			type="checkbox" value="on" id="httpinspect_inspect_gzip" onclick="httpinspect_inspectgzip_enable_change();" 
			<?php if ($pconfig['inspect_gzip']=="on") echo "checked";?>>
			<?php echo gettext("Uncompress and inspect compressed data in HTTP response. " .
				"Default is ");?>
			<strong><?php echo gettext("Checked");?></strong>.</td>
	</tr>
	<tr id="httpinspect_unlimiteddecompress_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Unlimited Decompress");?></td>
		<td width="78%" class="vtable"><input name="httpinspect_unlimited_decompress" 
			type="checkbox" value="on" id="httpinspect_unlimited_decompress" 
			<?php if ($pconfig['unlimited_decompress']=="on") echo "checked";?>>
			<?php echo gettext("Decompress unlimited gzip data (across multiple packets). Default is ");?> 
			<strong><?php echo gettext("Checked");?></strong>.</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Decompress SWF");?></td>
		<td width="78%" class="vtable"><input name="httpinspect_decompress_swf" 
			type="checkbox" value="on" id="httpinspect_decompress_swf" 
			<?php if ($pconfig['decompress_swf']=="on") echo "checked";?>>
			<?php echo gettext("Uncompress and inspect Shockwave Flash data in HTTP response. " .
				"Default is ");?>
			<strong><?php echo gettext("Not Checked");?></strong>.</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Decompress PDF");?></td>
		<td width="78%" class="vtable"><input name="httpinspect_decompress_pdf" 
			type="checkbox" value="on" id="httpinspect_decompress_pdf" 
			<?php if ($pconfig['decompress_pdf']=="on") echo "checked";?>>
			<?php echo gettext("Uncompress and inspect PDF data in HTTP response. " .
				"Default is ");?>
			<strong><?php echo gettext("Not Checked");?></strong>.</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Normalize Cookies");?></td>
		<td width="78%" class="vtable"><input name="httpinspect_normalize_cookies" 
			type="checkbox" value="on" id="httpinspect_normalize_cookies" 
			<?php if ($pconfig['normalize_cookies']=="on") echo "checked";?>>
			<?php echo gettext("Normalize HTTP cookie fields. " .
				"Default is ");?>
			<strong><?php echo gettext("Checked");?></strong>.</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Normalize UTF");?></td>
		<td width="78%" class="vtable"><input name="httpinspect_normalize_utf" 
			type="checkbox" value="on" id="httpinspect_normalize_utf" 
			<?php if ($pconfig['normalize_utf']=="on") echo "checked";?>>
			<?php echo gettext("Normalize HTTP response body character sets to 8-bit encoding. " .
				"Default is ");?>
			<strong><?php echo gettext("Checked");?></strong>.</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Normalize Headers");?></td>
		<td width="78%" class="vtable"><input name="httpinspect_normalize_headers" 
			type="checkbox" value="on" id="httpinspect_normalize_headers" 
			<?php if ($pconfig['normalize_headers']=="on") echo "checked";?>>
			<?php echo gettext("Normalize HTTP Header fields. " .
				"Default is ");?>
			<strong><?php echo gettext("Checked");?></strong>.</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Server Flow Depth"); ?></td>
		<td class="vtable">
			<input name="httpinspect_server_flow_depth" type="text" class="formfld unknown" 
			id="httpinspect_server_flow_depth" size="6" 
			value="<?=htmlspecialchars($pconfig['server_flow_depth']);?>">&nbsp;<strong><?php echo gettext("-1") . 
			"</strong>" . gettext(" to ") . "<strong>" . gettext("65535") . "</strong> " . gettext("(") . "<strong>" . 
			gettext("-1") . "</strong>" . gettext(" disables HTTP inspect, ") . "<strong>" . gettext("0") . "</strong>" . 
			gettext(" enables all HTTP inspect).");?><br/><br/>
			<?php echo gettext("Amount of HTTP server response payload to inspect. Snort's performance " .
			"may increase by adjusting this value. Setting this value too low may cause false negatives. ") . 
			gettext("Values above 0 are specified in bytes.  Recommended setting is maximum (65535). " . 
			"Default value is ") . "<strong>" . gettext("65535") . "</strong>.";?>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Client Flow Depth"); ?></td>
		<td class="vtable">
			<input name="httpinspect_client_flow_depth" type="text" class="formfld unknown" 
			id="httpinspect_client_flow_depth" size="6" 
			value="<?=htmlspecialchars($pconfig['client_flow_depth']);?>">&nbsp;<strong><?php echo gettext("-1") . "</strong>" . 
			gettext(" to ") . "<strong>" . gettext("1460") . "</strong>" . gettext(" (") . "<strong>" . gettext("-1") . 
			"</strong>" . gettext(" disables HTTP inspect, ") . "<strong>" . gettext("0") . "</strong>" . 
			gettext(" enables all HTTP inspect).");?><br/><br/>
			<?php echo gettext("Amount of raw HTTP client request payload to inspect. Snort's " .
			"performance may increase by adjusting this value. Setting this value too low may cause false negatives. ");?>
			<?php echo gettext("Values above 0 are specified in bytes.  Recommended setting is maximum (1460). " . 
			"Default value is ") . "<strong>" . gettext("1460") . "</strong>.";?>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Post Depth"); ?></td>
		<td class="vtable">
			<input name="httpinspect_post_depth" type="text" class="formfld unknown" 
			id="httpinspect_post_depth" size="6" 
			value="<?=htmlspecialchars($pconfig['post_depth']);?>">&nbsp;<strong><?php echo gettext("-1") . "</strong>" . 
			gettext(" to ") . "<strong>" . gettext("65495") . "</strong>" . gettext(" (") . "<strong>" . gettext("-1") . 
			"</strong>" . gettext(" ignores all post data, ") . "<strong>" . gettext("0") . "</strong>" . 
			gettext(" inspects all post data).");?><br/><br/>
			<?php echo gettext("Amount of data to inspect in client post message. Snort's performance may " .
			"increase by adjusting this value. Values above 0 are specified in bytes.  ") . 
			gettext("Default value is ") . "<strong>" . gettext("-1") . "</strong>.";?>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Max Headers"); ?></td>
		<td class="vtable">
			<input name="httpinspect_max_headers" type="text" class="formfld unknown" 
			id="httpinspect_max_headers" size="6" 
			value="<?=htmlspecialchars($pconfig['max_headers']);?>">&nbsp;<strong><?php echo gettext("1") . "</strong>" . 
			gettext(" to ") . "<strong>" . gettext("1024") . "</strong>" . gettext(" (") . "<strong>" . gettext("0") . 
			"</strong>" . gettext(" disables the alert).");?><br/><br/>
			<?php echo gettext("Sets the maximum number of HTTP client request header fields allowed.  Requests that " .
			"contain more HTTP headers than this value will cause a \"Max Header\" alert. ") . 
			gettext("Default value is ") . "<strong>" . gettext("0") . "</strong>.";?>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Max Header Length"); ?></td>
		<td class="vtable">
			<input name="httpinspect_max_header_length" type="text" class="formfld unknown" 
			id="httpinspect_max_header_length" size="6" 
			value="<?=htmlspecialchars($pconfig['max_header_length']);?>">&nbsp;<strong><?php echo gettext("1") . "</strong>" . 
			gettext(" to ") . "<strong>" . gettext("65535") . "</strong>" . gettext(" (") . "<strong>" . gettext("0") . 
			"</strong>" . gettext(" disables the alert).");?><br/><br/>
			<?php echo gettext("This sets the maximum length allowed for an HTTP client request header field. " .
			"Requests that exceed this limit well cause a \"Long Header\" alert. ") .  
			gettext("Default value is ") . "<strong>" . gettext("0") . "</strong>.";?>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Max Spaces"); ?></td>
		<td class="vtable">
			<input name="httpinspect_max_spaces" type="text" class="formfld unknown" 
			id="httpinspect_max_spaces" size="6" 
			value="<?=htmlspecialchars($pconfig['max_spaces']);?>">&nbsp;<strong><?php echo gettext("1") . "</strong>" . 
			gettext(" to ") . "<strong>" . gettext("65535") . "</strong>" . gettext(" (") . "<strong>" . gettext("0") . 
			"</strong>" . gettext(" disables the alert).");?><br/><br/>
			<?php echo gettext("This sets the maximum number of whitespaces allowed with HTTP client request line folding. " .
			"Request headers folded with whitespaces equal to or greater than this value will cause a \"Whitespace Saturation\" alert. ") . 
			gettext("Default value is ") . "<strong>" . gettext("0") . "</strong>.";?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="bottom">&nbsp;</td>
		<td width="78%" valign="bottom">
			<input name="save" id="save" type="submit" class="formbtn" value=" Save " title="<?php echo 
			gettext("Save httpinspect engine settings and return to Preprocessors tab"); ?>">
			&nbsp;&nbsp;&nbsp;&nbsp;
			<input name="Cancel" id="cancel" type="submit" class="formbtn" value="Cancel" title="<?php echo 
			gettext("Cancel changes and return to Preprocessors tab"); ?>"></td>
	</tr>
</table>
</td>
</tr>
</table>
</div>
</form>

<script type="text/javascript" src="/javascript/autosuggest.js">
</script>

<script type="text/javascript" src="/javascript/suggestions.js">
</script>

<script type="text/javascript">

function extended_response_enable_change() {
	var endis = !(document.iform.httpinspect_extended_response_inspection.checked);

	// Hide the "httpinspect_inspectgzip and httpinspect_normalizejavascript" rows if httpinspect_extended_response_inspection disabled
	if (endis) {
		document.getElementById("httpinspect_inspectgzip_row").style.display="none";
		document.getElementById("httpinspect_unlimiteddecompress_row").style.display="none";
		document.getElementById("httpinspect_normalizejavascript_row").style.display="none";
		document.getElementById("httpinspect_maxjavascriptwhitespaces_row").style.display="none";
	}
	else {
		document.getElementById("httpinspect_inspectgzip_row").style.display="table-row";
		document.getElementById("httpinspect_unlimiteddecompress_row").style.display="table-row";
		document.getElementById("httpinspect_normalizejavascript_row").style.display="table-row";
		document.getElementById("httpinspect_maxjavascriptwhitespaces_row").style.display="table-row";
	}
}

function httpinspect_inspectgzip_enable_change() {
	var endis = !(document.iform.httpinspect_inspect_gzip.checked);
	// Hide the "httpinspect_unlimited_decompress" row if httpinspect_inspect_gzip disabled
	if (endis)
		document.getElementById("httpinspect_unlimiteddecompress_row").style.display="none";
	else
		document.getElementById("httpinspect_unlimiteddecompress_row").style.display="table-row";
}

function normalize_javascript_enable_change() {
	var endis = !(document.iform.httpinspect_normalize_javascript.checked);

	// Hide the "httpinspect_maxjavascriptwhitespaces" row if httpinspect_normalize_javascript disabled
	if (endis)
		document.getElementById("httpinspect_maxjavascriptwhitespaces_row").style.display="none";
	else
		document.getElementById("httpinspect_maxjavascriptwhitespaces_row").style.display="table-row";
}

// Set initial state of form controls
extended_response_enable_change();
normalize_javascript_enable_change();
httpinspect_inspectgzip_enable_change();

<?php
	$isfirst = 0;
	$aliases = "";
	$addrisfirst = 0;
	$portisfirst = 0;
	$aliasesaddr = "";
	$aliasesport = "";
	if(isset($config['aliases']['alias']) && is_array($config['aliases']['alias']))
		foreach($config['aliases']['alias'] as $alias_name) {
			// Skip any Aliases that resolve to an empty string
			if (trim(filter_expand_alias($alias_name['name'])) == "")
				continue;
			if ($alias_name['type'] == "host" || $alias_name['type'] == "network") {
				if($addrisfirst == 1) $aliasesaddr .= ",";
				$aliasesaddr .= "'" . $alias_name['name'] . "'";
				$addrisfirst = 1;
			}
			elseif ($alias_name['type'] == "port") {
				if($portisfirst == 1) $aliasesport .= ",";
				$aliasesport .= "'" . $alias_name['name'] . "'";
				$portisfirst = 1;
			}
		}
?>
	var addressarray=new Array(<?php echo $aliasesaddr; ?>);
	var portarray=new Array(<?php echo $aliasesport; ?>);

function createAutoSuggest() {
<?php
	echo "objAliasAddr = new AutoSuggestControl(document.getElementById('httpinspect_bind_to'), new StateSuggestions(addressarray));\n";
	echo "objAliasPort = new AutoSuggestControl(document.getElementById('httpinspect_ports'), new StateSuggestions(portarray));\n";
?>
}

setTimeout("createAutoSuggest();", 500);

</script>
<?php include("fend.inc");?>
</body>
</html>
