<?php
/*
 * snort_edit_hat_data.php
 * Copyright (C) 2004 Scott Ullrich
 * Copyright (C) 2011-2012 Ermal Luci
 * Copyright (C) 2013-2014 Bill Meeks
 * All rights reserved.
 *
 * originially part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * All rights reserved.
 *
 * modified for the pfsense snort package
 * Copyright (C) 2009-2010 Robert Zelaya.
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

global $g, $rebuild_rules;

$snortdir = SNORTDIR;

if (!is_array($config['installedpackages']['snortglobal']['rule'])) {
	$config['installedpackages']['snortglobal']['rule'] = array();
}
$a_nat = &$config['installedpackages']['snortglobal']['rule'];

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

if (is_null($id)) {
	header("Location: /snort/snort_interfaces.php");
	exit;
}

if (!empty($a_nat[$id]['host_attribute_data']))
	$pconfig['host_attribute_data'] = base64_decode($a_nat[$id]['host_attribute_data']);
else
	$pconfig['host_attribute_data'] = "";

if ($_POST['clear']) {
	unset($a_nat[$id]['host_attribute_data']);
	$a_nat[$id]['host_attribute_table'] = 'off';
	write_config("Snort pkg: cleared Host Attribute Table data for {$a_nat[$id]['interface']}.");
	$rebuild_rules = false;
	conf_mount_rw();
	snort_generate_conf($a_nat[$id]);
	conf_mount_ro();
	$pconfig['host_attribute_data'] = "";
}

if ($_POST['save']) {
	$a_nat[$id]['host_attribute_data'] = base64_encode($_POST['host_attribute_data']);
	if (strlen($_POST['host_attribute_data']) > 0)
		$a_nat[$id]['host_attribute_table'] = 'on';
	else
		$a_nat[$id]['host_attribute_table'] = 'off';
	write_config("Snort pkg: modified Host Attribute Table data for {$a_nat[$id]['interface']}.");
	$rebuild_rules = false;
	conf_mount_rw();
	snort_generate_conf($a_nat[$id]);
	conf_mount_ro();
	$pconfig['host_attribute_data'] = $_POST['host_attribute_data'];
}


$if_friendly = convert_friendly_interface_to_friendly_descr($a_nat[$id]['interface']);
$pgtitle = gettext("Snort: Interface {$if_friendly} - Host Attribute Table Data");
include_once("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" >

<?php
include("fbegin.inc");
if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);
?>
<form action="snort_edit_hat_data.php" method="post" name="iform" id="iform">
<div id="boxarea">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
<td class="tabcont">
<table width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr>
		<td valign="middle" class="listtopic"><?php echo gettext("Edit Host Attribute Table Data"); ?></td>
	</tr>
	<tr>
		<td>
		<input type='hidden' name='id' value='<?=$id;?>'>
		<textarea wrap="off" cols="80" rows="35" name="host_attribute_data" id="host_attribute_data" style="width:99%; height:100%;"><?=htmlspecialchars($pconfig['host_attribute_data']);?></textarea></td>
	</tr>
	<tr>
		<td>
			<input name="save" type="submit" class="formbtn" value="<?php echo gettext(" Save "); ?>" title=" <?php echo gettext("Save Host Attribute data"); ?>"/>&nbsp;&nbsp;
			<input type="button" class="formbtn" value=" <?php echo gettext("Return"); ?>" onclick="parent.location='snort_preprocessors.php?id=<?=$id;?>'" title="<?php echo gettext("Return to Preprocessors tab"); ?>"/>&nbsp;&nbsp;
			<input name="clear" type="submit" class="formbtn" id="clear" value="<?php echo gettext("Clear"); ?>" onclick="return confirm('<?php echo gettext("This will erase all Host Attribute data for the interface.  Are you sure?"); ?>')" title="<?php echo gettext("Deletes all Host Attribute data"); ?>"/>
		</td>
	</tr>
</table>
</td>
</tr>
</table>
</div>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
