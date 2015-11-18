<?php
/*
 * snort_frag3_engine.php
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

if (!is_array($config['installedpackages']['snortglobal']['rule']))
	$config['installedpackages']['snortglobal']['rule'] = array();
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['frag3_engine']['item']))
	$config['installedpackages']['snortglobal']['rule'][$id]['frag3_engine']['item'] = array();
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

if ($_POST['Cancel']) {
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

		// We have saved a preproc config change, so set "dirty" flag
		mark_subsystem_dirty('snort_preprocessors');

		header("Location: /snort/snort_preprocessors.php?id={$id}#frag3_row");
		exit;
	}
}

$if_friendly = convert_friendly_interface_to_friendly_descr($config['installedpackages']['snortglobal']['rule'][$id]['interface']);
$pgtitle = gettext("Snort: Interface {$if_friendly} Frag3 Preprocessor Engine");
include_once("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" >

<?php
include("fbegin.inc");
if ($input_errors) print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);
?>

<form action="snort_frag3_engine.php" method="post" name="iform" id="iform">
<input name="id" type="hidden" value="<?=$id?>">
<input name="eng_id" type="hidden" value="<?=$eng_id?>">
<div id="boxarea">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
<td class="tabcont">
<table width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr>
		<td colspan="2" valign="middle" class="listtopic"><?php echo gettext("Snort Target-Based IP Defragmentation Engine Configuration"); ?></td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Engine Name"); ?></td>
		<td class="vtable">
			<input name="frag3_name" type="text" class="formfld unknown" id="frag3_name" size="25" maxlength="25" 
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
					<td class="vexpl"><input name="frag3_bind_to" type="text" class="formfldalias" id="frag3_bind_to" size="32" 
					value="<?=htmlspecialchars($pconfig['bind_to']);?>" title="<?=trim(filter_expand_alias($pconfig['bind_to']));?>" autocomplete="off">&nbsp;
					<?php echo gettext("IP List to bind this engine to. (Cannot be blank)"); ?></td>
					<td class="vexpl" align="right"><input type="button" class="formbtns" value="Aliases" onclick="parent.location='snort_select_alias.php?id=<?=$id;?>&eng_id=<?=$eng_id;?>&type=host|network&varname=bind_to&act=import&multi_ip=yes&returl=<?=urlencode($_SERVER['PHP_SELF']);?>'" 
					title="<?php echo gettext("Select an existing IP alias");?>"/></td>
				</tr>
				<tr>
					<td class="vexpl" colspan="2"><?php echo gettext("This engine will only run for packets with destination addresses contained within this IP List.");?></td>
				</tr>
			</table>
			<span class="red"><strong><?php echo gettext("Note: ") . "</strong></span>" . gettext("Supplied value must be a pre-configured Alias or the keyword 'all'.");?>
			&nbsp;&nbsp;&nbsp;&nbsp;
		<?php else : ?>
			<input name="frag3_bind_to" type="text" class="formfldalias" id="frag3_bind_to" size="32" 
			value="<?=htmlspecialchars($pconfig['bind_to']);?>" autocomplete="off" readonly>&nbsp;
			<?php echo "<span class=\"red\">" . gettext("IP List for the default engine is read-only and must be 'all'.") . "</span>";?><br/>
			<?php echo gettext("The default engine is required and only runs for packets with destination addresses not matching other engine IP Lists.");?><br/>
		<?php endif ?>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Target Policy"); ?> </td>
		<td width="78%" class="vtable">
			<select name="frag3_policy" class="formselect" id="policy">
			<?php
			$profile = array( 'BSD', 'BSD-Right', 'First', 'Last', 'Linux', 'Solaris', 'Windows' );
			foreach ($profile as $val): ?>
			<option value="<?=strtolower($val);?>" 
			<?php if (strtolower($val) == $pconfig['policy']) echo "selected"; ?>>
				<?=gettext($val);?></option>
				<?php endforeach; ?>
			</select>&nbsp;&nbsp;<?php echo gettext("Choose the IP fragmentation target policy appropriate for the protected hosts.  The default is ") . 
			"<strong>" . gettext("BSD") . "</strong>"; ?>.<br/><br/>
			<?php echo gettext("Available OS targets are BSD, BSD-Right, First, Last, Linux, Solaris and Windows."); ?><br/>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Timeout"); ?></td>
		<td class="vtable">
			<input name="frag3_timeout" type="text" class="formfld unknown" id="frag3_timeout" size="6" 
			value="<?=htmlspecialchars($pconfig['timeout']);?>">
			<?php echo gettext("Timeout period in seconds for fragments in the engine."); ?><br/><br/>
			<?php echo gettext("Fragments in the engine for longer than this period will be automatically dropped.  Default value is ") . 
			"<strong>" . gettext("60 ") . "</strong>" . gettext("seconds."); ?><br/>
		</td>
	</tr>
	<tr>
		<td valign="top" class="vncell"><?php echo gettext("Minimum Time-to-Live"); ?></td>
		<td class="vtable">
			<input name="frag3_min_ttl" type="text" class="formfld unknown" id="frag3_min_ttl" size="6" 
			value="<?=htmlspecialchars($pconfig['min_ttl']);?>">
			<?php echo gettext("Minimum acceptable TTL for a fragment in the engine."); ?><br/><br/>
			<?php echo gettext("The accepted range for this option is 1 - 255.  Default value is ") . 
			"<strong>" . gettext("1") . "</strong>"; ?>.<br/>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Detect Anomalies"); ?></td>
		<td width="78%" class="vtable"><input name="frag3_detect_anomalies" id="frag3_detect_anomalies" type="checkbox" value="on"  
			<?php if ($pconfig['detect_anomalies']=="on") echo "checked "; ?> onclick="frag3_enable_change();">
			<?php echo gettext("Use Frag3 Engine to detect fragment anomalies.  Default is ") . 
			"<strong>" . gettext("Checked") . "</strong>"; ?>.<br/><br/>
			<span class="red"><strong><?php echo gettext("Note: ") . "</strong></span>" . 
			gettext("In order to customize the Overlap Limit and Minimum Fragment Length parameters for this engine, Anomaly Detection must be enabled."); ?>
		</td>
	</tr>
	<tr id="frag3_overlaplimit_row">
		<td valign="top" class="vncell"><?php echo gettext("Overlap Limit"); ?></td>
		<td class="vtable">
			<input name="frag3_overlap_limit" type="text" class="formfld unknown" id="frag3_overlap_limit" size="6" 
			value="<?=htmlspecialchars($pconfig['overlap_limit']);?>">
			<?php echo gettext("Minimum is ") . "<strong>0</strong>" . gettext(" (unlimited).  Values greater than zero set the overlapped limit."); ?><br/><br/>
			<?php echo gettext("Sets the limit for the number of overlapping fragments allowed per packet.  Default value is ") .
			"<strong>0</strong>" . gettext(" (unlimited)."); ?><br/>
		</td>
	</tr>
	<tr id="frag3_minfraglen_row">
		<td valign="top" class="vncell"><?php echo gettext("Minimum Fragment Length"); ?></td>
		<td class="vtable">
			<input name="frag3_min_frag_len" type="text" class="formfld unknown" id="frag3_min_frag_len" size="6" 
			value="<?=htmlspecialchars($pconfig['min_frag_len']);?>">
			<?php echo gettext("Minimum is ") . "<strong>0</strong>" . gettext(" (check is disabled).  Values greater than zero enable the check."); ?><br/><br/>
			<?php echo gettext("Defines smallest fragment size (payload size) that should be considered valid.  " . 
			"Fragments smaller than or equal to this limit are considered malicious.  Default value is ") .
			"<strong>0</strong>" . gettext(" (check is disabled)."); ?><br/>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="bottom">&nbsp;</td>
		<td width="78%" valign="bottom">
			<input name="save" id="save" type="submit" class="formbtn" value=" Save " title="<?php echo 
			gettext("Save Frag3 engine settings and return to Preprocessors tab"); ?>">
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

function frag3_enable_change() {
	var endis = !(document.iform.frag3_detect_anomalies.checked);

	// Hide the "frag3_overlap_limit and frag3_min_frag_len" rows if frag3_detect_anomablies disabled
	if (endis) {
		document.getElementById("frag3_overlaplimit_row").style.display="none";
		document.getElementById("frag3_minfraglen_row").style.display="none";
	}
	else {
		document.getElementById("frag3_overlaplimit_row").style.display="table-row";
		document.getElementById("frag3_minfraglen_row").style.display="table-row";
	}
}

// Set initial state of form controls
frag3_enable_change();

<?php
	$isfirst = 0;
	$aliases = "";
	$addrisfirst = 0;
	$aliasesaddr = "";
	if(isset($config['aliases']['alias']) && is_array($config['aliases']['alias']))
		foreach($config['aliases']['alias'] as $alias_name) {
			if ($alias_name['type'] != "host" && $alias_name['type'] != "network")
				continue;
			// Skip any Aliases that resolve to an empty string
			if (trim(filter_expand_alias($alias_name['name'])) == "")
				continue;
			if($addrisfirst == 1) $aliasesaddr .= ",";
			$aliasesaddr .= "'" . $alias_name['name'] . "'";
			$addrisfirst = 1;
		}
?>
	var addressarray=new Array(<?php echo $aliasesaddr; ?>);

function createAutoSuggest() {
<?php
	echo "objAlias = new AutoSuggestControl(document.getElementById('frag3_bind_to'), new StateSuggestions(addressarray));\n";
?>
}

setTimeout("createAutoSuggest();", 500);

</script>

</html>
