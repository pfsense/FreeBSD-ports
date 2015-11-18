<?php
/*
 * snort_ftp_server_engine.php
 * Copyright (C) 2013-2014 Bill Meeks
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

if (!is_array($config['installedpackages']['snortglobal']['rule']))
	$config['installedpackages']['snortglobal']['rule'] = array();
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['ftp_server_engine']['item']))
	$config['installedpackages']['snortglobal']['rule'][$id]['ftp_server_engine']['item'] = array();
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

if ($_POST['Cancel']) {
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

		// We have saved a preproc config change, so set "dirty" flag
		mark_subsystem_dirty('snort_preprocessors');

		header("Location: /snort/snort_preprocessors.php?id={$id}#ftp_telnet_row_ftp_proto_opts");
		exit;
	}
}

$if_friendly = convert_friendly_interface_to_friendly_descr($config['installedpackages']['snortglobal']['rule'][$id]['interface']);
$pgtitle = gettext("Snort: Interface {$if_friendly} - FTP Preprocessor Server Engine");
include_once("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" >

<?php
include("fbegin.inc");
if ($input_errors) print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);
?>

<form action="snort_ftp_server_engine.php" method="post" name="iform" id="iform">
<input name="id" type="hidden" value="<?=$id?>">
<input name="eng_id" type="hidden" value="<?=$eng_id?>">
<div id="boxarea">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
<td class="tabcont">
<table width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr>
		<td colspan="2" valign="middle" class="listtopic"><?php echo gettext("Snort Target-Based FTP Server Engine Configuration"); ?></td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Engine Name"); ?></td>
		<td class="vtable">
			<input name="ftp_name" type="text" class="formfld unknown" id="ftp_name" size="25" maxlength="25" 
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
		<td valign="top" class="vncell"><?php echo gettext("Bind-To IP Address Alias"); ?></td>
		<td class="vtable">
		<?php if ($pconfig['name'] <> "default") : ?>
			<table width="95%" border="0" cellpadding="2" cellspacing="0">
				<tr>
					<td class="vexpl"><input name="ftp_bind_to" type="text" class="formfldalias" id="ftp_bind_to" size="32" 
					value="<?=htmlspecialchars($pconfig['bind_to']);?>" title="<?=trim(filter_expand_alias($pconfig['bind_to']));?>" autocomplete="off">&nbsp;
					<?php echo gettext("IP List to bind this engine to. (Cannot be blank)"); ?></td>
					<td align="right"><input type="button" class="formbtns" value="Aliases" onclick="parent.location='snort_select_alias.php?id=<?=$id;?>&eng_id=<?=$eng_id;?>&type=host|network&varname=bind_to&act=import&returl=<?=urlencode($_SERVER['PHP_SELF']);?>'" 
					title="<?php echo gettext("Select an existing IP alias");?>"/></td>
				</tr>
				<tr>
					<td class="vexpl" colspan="2"><?php echo gettext("This engine will only run for packets with destination addresses contained within the IP List.");?>.</td>
				</tr>
			</table><br/>
			<span class="red"><strong><?php echo gettext("Note: ") . "</strong></span>" . gettext("Supplied value must be a pre-configured Alias or the keyword 'all'.");?>
		<?php else : ?>
			<input name="ftp_bind_to" type="text" class="formfldalias" id="ftp_bind_to" size="32" 
			value="<?=htmlspecialchars($pconfig['bind_to']);?>" autocomplete="off" readonly>&nbsp;
			<?php echo "<span class=\"red\">" . gettext("IP address for the default engine is read-only and must be 'all'.") . "</span>";?><br/>
			<?php echo gettext("The default engine is required and only runs for packets with destination addresses not matching other engine IP addresses.");?><br/>
		<?php endif ?>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Ports"); ?></td>
		<td class="vtable">
			<table width="95%" border="0" cellpadding="2" cellspacing="0">
				<tr>
					<td class="vexpl"><input name="ftp_ports" type="text" class="formfldalias" id="ftp_ports" size="25" 
					value="<?=htmlspecialchars($pconfig['ports']);?>" title="<?=trim(filter_expand_alias($pconfig['ports']));?>">
					<?php echo gettext("Specifiy which ports to check for FTP data.");?></td>
					<td align="right"><input type="button" class="formbtns" value="Aliases" onclick="parent.location='snort_select_alias.php?id=<?=$id;?>&eng_id=<?=$eng_id;?>&type=port&varname=ports&act=import'" 
					title="<?php echo gettext("Select an existing port alias");?>"/></td>
				</tr>
				<tr>
					<td class="vexpl" colspan="2"><?php echo gettext("Default value is '") . "<strong>" . gettext("'default'") . "</strong>" . 
					gettext("  Using 'default' will include the FTP Ports defined on the ") . "<a href='snort_define_servers.php?id={$id}' title=\"" . 
					gettext("Go to {$if_friendly} Variables tab to define custom port variables") . "\">" . gettext("VARIABLES") . "</a>" . 
					gettext(" tab.  Specific ports for this server can be specified here using a pre-defined Alias.");?></td>
				</tr>
			</table><br/>
			<span class="red"><strong><?php echo gettext("Note: ") . "</strong></span>" . gettext("Supplied value must be a pre-configured Alias or the keyword 'default'.");?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Detect Telnet Commands"); ?></td>
		<td width="78%" class="vtable"><input name="ftp_telnet_cmds" id="ftp_telnet_cmds" type="checkbox" value="on"  
			<?php if ($pconfig['telnet_cmds']=="yes") echo "checked "; ?>>
			<?php echo gettext("Alert when Telnet commands are seen on the FTP command channel.  Default is ") . 
			"<strong>" . gettext("Not Checked") . "</strong>"; ?>.<br/>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Ignore Telnet Erase Commands"); ?></td>
		<td width="78%" class="vtable"><input name="ftp_ignore_telnet_erase_cmds" id="ftp_ignore_telnet_erase_cmds" type="checkbox" value="on"  
			<?php if ($pconfig['ignore_telnet_erase_cmds']=="yes") echo "checked "; ?>>
			<?php echo gettext("Ignore Telnet escape sequences for erase character and erase line when normalizing FTP command channel.  Default is ") . 
			"<strong>" . gettext("Checked") . "</strong>"; ?>.<br/>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Ignore Data Channel"); ?></td>
		<td width="78%" class="vtable"><input name="ftp_ignore_data_chan" id="ftp_ignore_data_chan" type="checkbox" value="on"  
			<?php if ($pconfig['ignore_data_chan']=="yes") echo "checked "; ?>>
			<?php echo gettext("Force Snort to ignore the FTP data channel connections.  Default is ") . 
			"<strong>" . gettext("Not Checked") . "</strong>"; ?>.<br/><br/>
			<span class="red"><strong><?php echo gettext("Warning: ") . "</strong></span>" . gettext("When checked, NO INSPECTION other than state will be ") . 
			gettext("performed on the data channel.  Enabling this option can improve performance for large FTP transfers from trusted servers.");?>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Default Max Allowed Parameter Length"); ?></td>
		<td class="vtable">
			<input name="ftp_def_max_param_len" type="text" class="formfld unknown" id="ftp_def_max_param_len" size="6" 
			value="<?=htmlspecialchars($pconfig['def_max_param_len']);?>">
			<?php echo gettext("Default allowed maximum parameter length for command.  Enter ") . "<strong>" . gettext("0") . "</strong>" . 
			gettext(" to disable.  Default is ") . "<strong>" . gettext("100.") . "</strong>";?><br/>
			<?php echo gettext("Specifies the maximum allowed parameter length for and FTP command.  It can be used as a ") . 
			gettext("basic buffer overflow detection.");?><br/>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="bottom">&nbsp;</td>
		<td width="78%" valign="bottom">
			<input name="save" id="save" type="submit" class="formbtn" value=" Save " title="<?php echo 
			gettext("Save ftp engine settings and return to Preprocessors tab"); ?>">
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
	echo "objAlias = new AutoSuggestControl(document.getElementById('ftp_bind_to'), new StateSuggestions(addressarray));\n";
	echo "objAliasPort = new AutoSuggestControl(document.getElementById('ftp_ports'), new StateSuggestions(portarray));\n";
?>
}

setTimeout("createAutoSuggest();", 500);

</script>

</html>
