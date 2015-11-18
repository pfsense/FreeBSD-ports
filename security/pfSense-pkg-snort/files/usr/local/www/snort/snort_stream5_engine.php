<?php
/*
 * snort_stream5_engine.php
 * Copyright (C) 2013, 2014 Bill Meeks
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
if (!is_array($config['installedpackages']['snortglobal']['rule']))
	$config['installedpackages']['snortglobal']['rule'] = array();
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['stream5_tcp_engine']['item']))
	$config['installedpackages']['snortglobal']['rule'][$id]['stream5_tcp_engine']['item'] = array();
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

if ($_POST['Cancel']) {
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

		// We have saved a preproc config change, so set "dirty" flag
		mark_subsystem_dirty('snort_preprocessors');

		header("Location: /snort/snort_preprocessors.php?id={$id}#stream5_row");
		exit;
	}
}

$if_friendly = convert_friendly_interface_to_friendly_descr($config['installedpackages']['snortglobal']['rule'][$id]['interface']);
$pgtitle = gettext("Snort: Interface {$if_friendly} - Stream5 Preprocessor TCP Engine");
include_once("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" >

<?php
include("fbegin.inc");
if ($input_errors) print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);
?>

<form action="snort_stream5_engine.php" method="post" name="iform" id="iform">
<input name="id" type="hidden" value="<?=$id?>">
<input name="eng_id" type="hidden" value="<?=$eng_id?>">
<div id="boxarea">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
<td class="tabcont">
<table width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr>
		<td colspan="2" valign="middle" class="listtopic"><?php echo gettext("Stream5 Target-Based TCP Stream Reassembly Engine Configuration"); ?></td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("TCP Engine Name"); ?></td>
		<td class="vtable">
			<input name="stream5_name" type="text" class="formfld unknown" id="stream5_name" size="25" maxlength="25" 
			value="<?=htmlspecialchars($pconfig['name']);?>"<?php if (htmlspecialchars($pconfig['name']) == "default") echo "readonly";?>>&nbsp;
			<?php if (htmlspecialchars($pconfig['name']) <> "default") 
					echo gettext("Name or description for this engine.  (Max 25 characters)");
				else
					echo "<span class=\"red\">" . gettext("The name for the 'default' engine is read-only.") . "</span>";?><br/>
			<?php echo gettext("Unique name or description for this engine configuration.  Default value is ") . 
			"<strong>" . gettext("default") . "</strong>"; ?>.<br/>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Bind-To IP Address"); ?></td>
		<td class="vtable">
		<?php if ($pconfig['name'] <> "default") : ?>
			<table width="95%" border="0" cellpadding="2" cellspacing="0">
				<tr>
					<td class="vexpl"><input name="stream5_bind_to" type="text" class="formfldalias" id="stream5_bind_to" size="32" 
					value="<?=htmlspecialchars($pconfig['bind_to']);?>" title="<?=trim(filter_expand_alias($pconfig['bind_to']));?>" autocomplete="off">&nbsp;
					<?php echo gettext("IP address or network to bind this engine to."); ?></td>
					<td align="right"><input type="button" class="formbtns" value="Aliases" onclick="parent.location='snort_select_alias.php?id=<?=$id;?>&eng_id=<?=$eng_id;?>&type=host|network&varname=bind_to&act=import&multi_ip=no&returl=<?=urlencode($_SERVER['PHP_SELF']);?>'" 
					title="<?php echo gettext("Select an existing IP alias");?>"/></td>
				</tr>
				<tr>
					<td class="vexpl" colspan="2"><?php echo gettext("This engine will only run for packets with the destination IP address specified.  Default value is ") . 
					"<strong>" . gettext("all") . "</strong>" . gettext(".  Only a single IP address or single network in CIDR form may be specified.  ") . 
					gettext("IP Lists are not allowed.");?></td>
				</tr>
			</table><br/>
			<span class="red"><strong><?php echo gettext("Note: ") . "</strong></span>" . gettext("Supplied value must be a pre-configured Alias or the keyword 'all'.  ");?>
		<?php else : ?>
			<input name="stream5_bind_to" type="text" class="formfldalias" id="stream5_bind_to" size="32" 
			value="<?=htmlspecialchars($pconfig['bind_to']);?>" autocomplete="off" readonly>&nbsp;
			<?php echo "<span class=\"red\">" . gettext("IP List for the default engine is read-only and must be 'all'.") . "</span>";?><br/>
			<?php echo gettext("The default engine is required and only runs for packets with destination addresses not matching other engine IP Lists.");?><br/>
		<?php endif ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("TCP Target Policy"); ?></td>
		<td width="78%" class="vtable">
			<select name="stream5_policy" class="formselect" id="stream5_policy"> 
			<?php
			$profile = array( 'BSD', 'First', 'HPUX', 'HPUX10', 'Irix', 'Last', 'Linux', 'MacOS', 'Old-Linux', 
					 'Solaris', 'Vista', 'Windows', 'Win2003' );
			foreach ($profile as $val): ?>
			<option value="<?=strtolower($val);?>" 
			<?php if (strtolower($val) == $pconfig['policy']) echo "selected"; ?>>
				<?=gettext($val);?></option>
				<?php endforeach; ?>
			</select>&nbsp;&nbsp;<?php echo gettext("Choose the TCP target policy appropriate for the protected hosts.  The default is ") . 
			"<strong>" . gettext("BSD") . "</strong>"; ?>.<br/><br/>
			<?php echo gettext("Available OS targets are BSD, First, HPUX, HPUX10, Irix, Last, Linux, MacOS, Old Linux, Solaris, Vista, Windows, and Win2003 Server."); ?><br/>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("TCP Target Ports"); ?></td>
		<td width="78%" class="vtable">
			<table width="95%" border="0" cellpadding="2" cellspacing="0">
			   <tr>
				<td class="vexpl"><strong><?php echo gettext("Client:"); ?></strong></td>
				<td class="vexpl"><input name="stream5_ports_client" type="text" class="formfldalias" id="stream5_ports_client" size="32" 
					value="<?=htmlspecialchars($pconfig['ports_client']);?>" title="<?=trim(filter_expand_alias($pconfig['ports_client']));?>" autocomplete="off"><span class="vexpl">&nbsp;
					<?php echo gettext("Default value is the keyword ") . "<strong>" . gettext("default") . "</strong>.";?></span>
				</td>
				<td align="right"><input type="button" class="formbtns" value="Aliases" onclick="parent.location='snort_select_alias.php?id=<?=$id;?>&eng_id=<?=$eng_id;?>&type=port&varname=ports_client&act=import&returl=<?=urlencode($_SERVER['PHP_SELF']);?>'"  
					title="<?php echo gettext("Select an existing port alias");?>"/>
				</td>
			   </tr>
			   <tr>
				<td class="vexpl"><strong><?php echo gettext("Server:"); ?></strong></td>
				<td class="vexpl"><input name="stream5_ports_server" type="text" class="formfldalias" id="stream5_ports_server" size="32" 
					value="<?=htmlspecialchars($pconfig['ports_server']);?>" title="<?=trim(filter_expand_alias($pconfig['ports_server']));?>" autocomplete="off"><span class="vexpl">&nbsp;
					<?php echo gettext("Default value is the keyword ") . "<strong>" . gettext("none") . "</strong>.";?></span>
				</td>
				<td align="right"><input type="button" class="formbtns" value="Aliases" onclick="parent.location='snort_select_alias.php?id=<?=$id;?>&eng_id=<?=$eng_id;?>&type=port&varname=ports_server&act=import&returl=<?=urlencode($_SERVER['PHP_SELF']);?>'"  
					title="<?php echo gettext("Select an existing port alias");?>"/>
				</td>
			   </tr>
			   <tr>
				<td class="vexpl"><strong><?php echo gettext("Both:"); ?></strong></td>
				<td class="vexpl"><input name="stream5_ports_both" type="text" class="formfldalias" id="stream5_ports_both" size="32" 
					value="<?=htmlspecialchars($pconfig['ports_both']);?>" title="<?=trim(filter_expand_alias($pconfig['ports_both']));?>" autocomplete="off"><span class="vexpl">&nbsp;
					<?php echo gettext("Default value is the keyword ") . "<strong>" . gettext("default") . "</strong>.";?></span>
				</td>
				<td align="right"><input type="button" class="formbtns" value="Aliases" onclick="parent.location='snort_select_alias.php?id=<?=$id;?>&eng_id=<?=$eng_id;?>&type=port&varname=ports_both&act=import&returl=<?=urlencode($_SERVER['PHP_SELF']);?>'"  
					title="<?php echo gettext("Select an existing port alias");?>"/>
				</td>
			   </tr>
			</table>
			<br/><?php echo gettext("Configures which side of the connection packets should be reassembled for based on the configured destination ports.  See ");?>
			<a href="http://www.snort.org/vrt/snort-conf-configurations/" target="_blank"><?php echo gettext("www.snort.org/vrt/snort-conf-configurations");?></a>
			<?php echo gettext(" for the default configuration port values.");?><br/><br/>
			<span class="red"><strong><?php echo gettext("Note: ") . "</strong></span>" . 
			gettext("Supplied value must be a pre-configured Alias or the keyword 'default', 'all' or 'none'.");?><br/>
			<span class="red"><?php echo gettext("Hint: ") . "</span>" . gettext("Most users should leave these settings at their default values.");?>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("TCP Max Window"); ?></td>
		<td class="vtable">
			<input name="stream5_max_window" type="text" class="formfld unknown" id="stream5_max_window" size="9" 
			value="<?=htmlspecialchars($pconfig['max_window']);?>" maxlength="10">
			<?php echo gettext("Maximum allowed TCP window.  Min is ") . "<strong>0</strong>" . gettext(" and max is ") . 
			"<strong>1073725440</strong>" . gettext(" (65535 left shift 14)"); ?>.<br/><br/>
			<?php echo gettext("Sets the TCP max window size.  Default value is ") .
			"<strong>0</strong>" . gettext(" (unlimited).  This option is intended to prevent a DoS against Stream5 by " . 
			"attacker using an abnormally large window, so using a value near the maximum is discouraged."); ?><br/>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("TCP Timeout"); ?></td>
		<td class="vtable">
			<input name="stream5_timeout" type="text" class="formfld unknown" id="stream5_timeout" size="9" 
			value="<?=htmlspecialchars($pconfig['timeout']);?>" maxlength="5">
			<?php echo gettext("TCP Session timeout in seconds.  Min is ") . "<strong>1</strong>" . gettext(" and max is ") . 
			"<strong>86400</strong>" . gettext(" (approximately 1 day)"); ?>.<br/><br/>
			<?php echo gettext("Sets the session reassembly timeout period for TCP packets.  Default value is ") .
			"<strong>30</strong>" . gettext(" seconds."); ?><br/>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("TCP Max Queued Bytes"); ?></td>
		<td class="vtable">
			<input name="stream5_max_queued_bytes" type="text" class="formfld unknown" id="stream5_max_queued_bytes" size="9" 
			value="<?=htmlspecialchars($pconfig['max_queued_bytes']);?>" maxlength="10">
			<?php echo gettext("Minimum is ") . "<strong>" . gettext("1024") . "</strong>" . gettext(" and Maximum is ") . 
			"<strong>" . gettext("1073741824") . "</strong>" . gettext("  (") . 
			"<strong>" . gettext("0") . "</strong>" . gettext(" means Maximum)."); ?><br/><br/>
			
			<?php echo gettext("The number of bytes to be queued for reassembly of TCP sessions in " .
			"memory. Default value is <strong>1048576</strong>"); ?>.<br/>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("TCP Max Queued Segs"); ?></td>
		<td class="vtable">
			<input name="stream5_max_queued_segs" type="text" class="formfld unknown" id="stream5_max_queued_segs" size="9" 
			value="<?=htmlspecialchars($pconfig['max_queued_segs']);?>" maxlength="10">
			<?php echo gettext("Minimum is ") . "<strong>" . gettext("2") . "</strong>" . gettext(" and Maximum is ") . 
			"<strong>" . gettext("1073741824") . "</strong>" . gettext("  (") . 
			"<strong>" . gettext("0") . "</strong>" . gettext(" means Maximum)");?>.<br/><br/>
			<?php echo gettext("The number of segments to be queued for reassembly of TCP sessions " .
			"in memory. Default value is <strong>2621</strong>"); ?>.<br/>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("TCP Overlap Limit"); ?></td>
		<td class="vtable">
			<input name="stream5_overlap_limit" type="text" class="formfld unknown" id="stream5_overlap_limit" size="9" 
			value="<?=htmlspecialchars($pconfig['overlap_limit']);?>" maxlength="3">
			<?php echo gettext("Minimum is ") . "<strong>0</strong>" . gettext(" (unlimited) and Maximum is ") . "<strong>" . 
			gettext("255") . "</strong>"; ?>.<br/><br/>
			<?php echo gettext("Sets the limit for the number of overlapping packets.  Default value is ") .
			"<strong>0</strong>" . gettext(" (unlimited)."); ?><br/>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Detect TCP Anomalies"); ?></td>
		<td width="78%" class="vtable"><input name="stream5_detect_anomalies" id="stream5_detect_anomalies" type="checkbox" value="on"  
			<?php if ($pconfig['detect_anomalies']=="on") echo "checked"; ?>>
			<?php echo gettext("Detect TCP protocol anomalies.  Default is ") . 
			"<strong>" . gettext("Not Checked") . "</strong>"; ?>.<br/>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Check Session Hijacking"); ?></td>
		<td width="78%" class="vtable"><input name="stream5_check_session_hijacking" id="stream5_check_session_hijacking" type="checkbox" value="on"  
			<?php if ($pconfig['check_session_hijacking']=="on") echo "checked"; ?>>
			<?php echo gettext("Check for TCP session hijacking.  Default is ") . 
			"<strong>" . gettext("Not Checked") . "</strong>"; ?>.<br/><br/>
			<?php echo gettext("This check validates the hardware (MAC) address from both sides of the connection -- " . 
			"as established on the 3-way handshake -- against subsequent packets received on the session.");?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Require 3-Way Handshake"); ?></td>
		<td width="78%" class="vtable"><input name="stream5_require_3whs" type="checkbox" value="on" 
			<?php if ($pconfig['require_3whs']=="on") echo "checked"; ?> onclick="stream5_3whs_enable_change();">
			<?php echo gettext("Establish sessions only on completion of SYN/SYN-ACK/ACK handshake.  Default is ") . 
			"<strong>" . gettext("Not Checked") . "</strong>"; ?>.<br/>
		</td>
	</tr>
	<tr id="stream5_3whs_startuptimeout_row">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("3-Way Handshake Startup Timeout"); ?></td>
		<td width="78%" class="vtable">
			<input name="stream5_3whs_startup_timeout" type="text" class="formfld unknown" id="stream5_3whs_startup_timeout" size="9" 
			value="<?=htmlspecialchars($pconfig['startup_3whs_timeout']);?>" maxlength="5">
			<?php echo gettext("3-Way Handshake Startup Timeout in seconds.  Min is ") . "<strong>" . gettext("0") . "</strong>" . 
			gettext(" and Max is ") . "<strong>" . gettext("86400") . "</strong>" . gettext(" (1 day).");?><br/><br/>
			<?php echo gettext("This allows a grace period for existing sessions to be considered established during that " . 
			"interval immediately after Snort is started.  The default is ") . "<strong>" . gettext("0") . 
			"</strong>" . gettext(", (don't consider existing sessions established).");?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Do Not Reassemble Async"); ?></td>
		<td width="78%" class="vtable"><input name="stream5_no_reassemble_async" type="checkbox" value="on" 
			<?php if ($pconfig['no_reassemble_async']=="on") echo "checked "; ?>>
			<?php echo gettext("Do not queue packets for reassembly if traffic has not been seen in both directions.  Default is ") . 
			"<strong>" . gettext("Not Checked") . "</strong>"; ?>.
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Use Static Footprint Sizes"); ?></td>
		<td width="78%" class="vtable"><input name="stream5_use_static_footprint_sizes" id="stream5_use_static_footprint_sizes" type="checkbox" value="on"  
			<?php if ($pconfig['use_static_footprint_sizes']=="on") echo "checked "; ?>>
			<?php echo gettext("Emulate Stream4 behavior for flushing reassembled packets.  Default is ") . 
			"<strong>" . gettext("Not Checked") . "</strong>"; ?>.<br/>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Do Not Store Large TCP Packets"); ?></td>
		<td width="78%" class="vtable">
			<input name="stream5_dont_store_lg_pkts" type="checkbox" value="on" 
			<?php if ($pconfig['dont_store_lg_pkts']=="on") echo "checked"; ?>>
			<?php echo gettext("Do not queue large packets in reassembly buffer to increase performance.  Default is ") . 
			"<strong>" . gettext("Not Checked") . "</strong>"; ?>.<br/><br/>
			<?php echo "<span class=\"red\"><strong>" . gettext("Warning:  ") . "</strong></span>" . 
			gettext("Enabling this option could result in missed packets.  Recommended setting is not checked."); ?>
		</td>  
	</tr>
	<tr>
		<td width="22%" valign="bottom">&nbsp;</td>
		<td width="78%" valign="bottom">
			<input name="save" id="save" type="submit" class="formbtn" value=" Save " title="<?php echo 
			gettext("Save Stream5 engine settings and return to Preprocessors tab"); ?>">
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
<?php include("fend.inc"); ?>
</body>
<script type="text/javascript" src="/javascript/autosuggest.js">
</script>
<script type="text/javascript" src="/javascript/suggestions.js">
</script>
<script type="text/javascript">

function stream5_3whs_enable_change() {
	var endis = !(document.iform.stream5_require_3whs.checked);

	// Hide the "3whs_startup_timeout" row if stream5_require_3whs disabled
	if (endis)
		document.getElementById("stream5_3whs_startuptimeout_row").style.display="none";
	else
		document.getElementById("stream5_3whs_startuptimeout_row").style.display="table-row";
}

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
	echo "objAlias = new AutoSuggestControl(document.getElementById('stream5_bind_to'), new StateSuggestions(addressarray));\n";
	echo "objAliasPortsClient = new AutoSuggestControl(document.getElementById('stream5_ports_client'), new StateSuggestions(portarray));\n";
	echo "objAliasPortsServer = new AutoSuggestControl(document.getElementById('stream5_ports_server'), new StateSuggestions(portarray));\n";
	echo "objAliasPortsBoth = new AutoSuggestControl(document.getElementById('stream5_ports_both'), new StateSuggestions(portarray));\n";
?>
}

setTimeout("createAutoSuggest();", 500);
stream5_3whs_enable_change();

</script>

</html>
