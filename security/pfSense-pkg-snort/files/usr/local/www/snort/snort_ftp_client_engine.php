<?php
/*
 * snort_ftp_client_engine.php
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

if (!is_array($config['installedpackages']['snortglobal']['rule']))
	$config['installedpackages']['snortglobal']['rule'] = array();
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['ftp_client_engine']['item']))
	$config['installedpackages']['snortglobal']['rule'][$id]['ftp_client_engine']['item'] = array();
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

if ($_POST['Cancel']) {
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

		// We have saved a preproc config change, so set "dirty" flag
		mark_subsystem_dirty('snort_preprocessors');

		header("Location: /snort/snort_preprocessors.php?id={$id}#ftp_telnet_row_ftp_proto_opts");
		exit;
	}
}

$if_friendly = convert_friendly_interface_to_friendly_descr($config['installedpackages']['snortglobal']['rule'][$id]['interface']);
$pgtitle = gettext("Snort: Interface {$if_friendly} - FTP Preprocessor Client Engine");
include_once("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" >

<?php
include("fbegin.inc");
if ($input_errors) print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);
?>

<form action="snort_ftp_client_engine.php" method="post" name="iform" id="iform">
<input name="id" type="hidden" value="<?=$id?>">
<input name="eng_id" type="hidden" value="<?=$eng_id?>">
<div id="boxarea">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
<td class="tabcont">
<table width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr>
		<td colspan="2" valign="middle" class="listtopic"><?php echo gettext("Snort Target-Based FTP Client Engine Configuration"); ?></td>
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
					value="<?=htmlspecialchars($pconfig['bind_to']);?>" title="<?=trim(filter_expand_alias($pconfig['bind_to']));?>" autocomplete="off" >&nbsp;
					<?php echo gettext("IP List to bind this engine to. (Cannot be blank)"); ?></td>
					<td align="right"><input type="button" class="formbtns" value="Aliases" onclick="parent.location='snort_select_alias.php?id=<?=$id;?>&eng_id=<?=$eng_id;?>&type=host|network&varname=bind_to&act=import&returl=<?=urlencode($_SERVER['PHP_SELF']);?>'" 
					title="<?php echo gettext("Select an existing IP alias");?>"/></td>
				</tr>
				<tr>
					<td class="vexpl" colspan="2"><?php echo gettext("This engine will only run for packets with destination addresses contained within the IP List.");?>.<br/><br/>
					<span class="red"><strong><?php echo gettext("Note: ") . "</strong></span>" . gettext("Supplied value must be a pre-configured Alias or the keyword 'all'.");?></td>
				</tr>
			</table>
		<?php else : ?>
			<input name="ftp_bind_to" type="text" class="formfldalias" id="ftp_bind_to" size="32" 
			value="<?=htmlspecialchars($pconfig['bind_to']);?>" autocomplete="off" readonly>&nbsp;
			<?php echo "<span class=\"red\">" . gettext("IP address for the default engine is read-only and must be 'all'.") . "</span>";?><br/>
			<?php echo gettext("The default engine is required and only runs for packets with destination addresses not matching other engine IP addresses.");?><br/>
		<?php endif ?>
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
			<?php echo gettext("Ignore Telnet escape sequences for erase character and erase line when normalizing FTP command channel.") . "<br/>" . 
			gettext("Default is ") . "<strong>" . gettext("Checked") . "</strong>"; ?>.<br/>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Maximum Response Length"); ?></td>
		<td class="vtable">
			<input name="ftp_max_resp_len" type="text" class="formfld unknown" id="ftp_max_resp_len" size="6" 
			value="<?=htmlspecialchars($pconfig['max_resp_len']);?>">
			<?php echo gettext("Max FTP command response length accepted by client.  Enter ") . "<strong>" . gettext("0") . "</strong>" . 
			gettext(" to disable.  Default is ") . "<strong>" . gettext("256.") . "</strong>";?><br/>
			<?php echo gettext("Specifies the maximum allowed response length to an FTP command accepted by the client.  It can be used as ") . 
			gettext("a basic buffer overflow detection.");?><br/>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Bounce Detection"); ?></td>
		<td width="78%" class="vtable"><input name="ftp_client_bounce_detect" type="checkbox" value="on" 
			<?php if ($pconfig['bounce']=="yes") echo "checked"; ?> onclick="ftp_client_bounce_enable_change();">
		<?php echo gettext("Enable detection and alerting of FTP bounce attacks.  Default is ") . 
		"<strong>" . gettext("Checked") . "</strong>"; ?>.</td>
	</tr>
	<tr id="ftp_client_row_bounce_to">
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Bounce-To Configuration"); ?></td>
		<td width="78%" class="vtable">
			<table border="0" cellpadding="2" cellspacing="0">
			   <tr>
				<td class="vexpl"><strong><?php echo gettext("Bounce-To Address:"); ?></strong></td>
				<td class="vexpl"><input name="ftp_client_bounce_to_net" type="text" class="formfldalias" id="ftp_client_bounce_to_net" size="20" 
					value="<?=htmlspecialchars($pconfig['bounce_to_net']);?>" title="<?=trim(filter_expand_alias($pconfig['bounce_to_net']));?>" autocomplete="off"><span class="vexpl">&nbsp;
					<?php echo gettext("Default is ") . "<strong>" . gettext("blank") . "</strong>.";?></span>
				</td>
				<td class="vexpl">&nbsp;&nbsp;<input type="button" class="formbtns" value="Aliases" onclick="parent.location='snort_select_alias.php?id=<?=$id;?>&eng_id=<?=$eng_id;?>&type=host|network&varname=bounce_to_net&act=import&returl=<?=urlencode($_SERVER['PHP_SELF']);?>'"  
					title="<?php echo gettext("Select an existing IP alias");?>"/>
				</td>
			   </tr>
			   <tr>
				<td class="vexpl"><strong><?php echo gettext("Bounce-To Port:"); ?></strong></td>
				<td class="vexpl"><input name="ftp_client_bounce_to_port" type="text" class="formfldalias" id="ftp_client_bounce_to_port" size="20" 
					value="<?=htmlspecialchars($pconfig['bounce_to_port']);?>" title="<?=trim(filter_expand_alias($pconfig['bounce_to_port']));?>" autocomplete="off"><span class="vexpl">&nbsp;
					<?php echo gettext("Default is ") . "<strong>" . gettext("blank") . "</strong>.";?></span>
				</td>
				<td class="vexpl">&nbsp;&nbsp;<input type="button" class="formbtns" value="Aliases" onclick="parent.location='snort_select_alias.php?id=<?=$id;?>&eng_id=<?=$eng_id;?>&type=port&varname=bounce_to_port&act=import&returl=<?=urlencode($_SERVER['PHP_SELF']);?>'"  
					title="<?php echo gettext("Select an existing port alias");?>"/>
				</td>
			   </tr>
			</table>
			<?php echo gettext("When the Bounce option is enabled, this allows the PORT command to use the address and port (or inclusive port range) ") . 
			gettext("specified without generating an alert.  It can be used with proxied FTP connections where the FTP data channel is different from the client.");?><br/><br/>
			<span class="red"><strong><?php echo gettext("Note: ") . "</strong></span>" . 
			gettext("Supplied value must be a pre-configured Alias or left blank.");?><br/>
			<span class="red"><?php echo gettext("Hint: ") . "</span>" . gettext("Leave these settings at their defaults unless you are proxying FTP connections.");?>
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
	echo "objAliasBindTo = new AutoSuggestControl(document.getElementById('ftp_bind_to'), new StateSuggestions(addressarray));\n";
	echo "objAliasBounceNet = new AutoSuggestControl(document.getElementById('ftp_client_bounce_to_net'), new StateSuggestions(addressarray));\n";
	echo "objAliasBouncePort = new AutoSuggestControl(document.getElementById('ftp_client_bounce_to_port'), new StateSuggestions(portarray));\n";


?>
}

setTimeout("createAutoSuggest();", 500);

function ftp_client_bounce_enable_change() {
	var endis = !(document.iform.ftp_client_bounce_detect.checked);
	if (endis)
		document.getElementById("ftp_client_row_bounce_to").style.display="none";
	else
		document.getElementById("ftp_client_row_bounce_to").style.display="table-row";
}

// Set initial state of form controls
ftp_client_bounce_enable_change();

</script>

</html>
