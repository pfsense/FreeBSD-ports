<?php
/*
 * suricata_passlist_edit.php
 *
 * Significant portions of this code are based on original work done
 * for the Snort package for pfSense from the following contributors:
 * 
 * Copyright (C) 2005 Bill Marquette <bill.marquette@gmail.com>.
 * Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
 * Copyright (C) 2006 Scott Ullrich
 * Copyright (C) 2009 Robert Zelaya Sr. Developer
 * Copyright (C) 2012 Ermal Luci
 * All rights reserved.
 *
 * Adapted for Suricata by:
 * Copyright (C) 2014 Bill Meeks
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:

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
require_once("/usr/local/pkg/suricata/suricata.inc");

if ($_POST['cancel']) {
	header("Location: /suricata/suricata_passlist.php");
	exit;
}

if (!is_array($config['installedpackages']['suricata']['passlist']))
	$config['installedpackages']['suricata']['passlist'] = array();
if (!is_array($config['installedpackages']['suricata']['passlist']['item']))
	$config['installedpackages']['suricata']['passlist']['item'] = array();
$a_passlist = &$config['installedpackages']['suricata']['passlist']['item'];

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

/* Should never be called without identifying list index, so bail */
if (is_null($id)) {
	header("Location: /suricata/suricata_interfaces_passlist.php");
	exit;
}

if (isset($id) && isset($a_passlist[$id])) {
	/* Retrieve saved settings */
	$pconfig['name'] = $a_passlist[$id]['name'];
	$pconfig['uuid'] = $a_passlist[$id]['uuid'];
	$pconfig['address'] = $a_passlist[$id]['address'];
	$pconfig['descr'] = html_entity_decode($a_passlist[$id]['descr']);
	$pconfig['localnets'] = $a_passlist[$id]['localnets'];
	$pconfig['wanips'] = $a_passlist[$id]['wanips'];
	$pconfig['wangateips'] = $a_passlist[$id]['wangateips'];
	$pconfig['wandnsips'] = $a_passlist[$id]['wandnsips'];
	$pconfig['vips'] = $a_passlist[$id]['vips'];
	$pconfig['vpnips'] = $a_passlist[$id]['vpnips'];
}

// Check for returned "selected alias" if action is import
if ($_GET['act'] == "import") {

	// Retrieve previously typed values we passed to SELECT ALIAS page
	$pconfig['name'] = htmlspecialchars($_GET['name']);
	$pconfig['uuid'] = htmlspecialchars($_GET['uuid']);
	$pconfig['address'] = htmlspecialchars($_GET['address']);
	$pconfig['descr'] = htmlspecialchars($_GET['descr']);
	$pconfig['localnets'] = htmlspecialchars($_GET['localnets'])? 'yes' : 'no';
	$pconfig['wanips'] = htmlspecialchars($_GET['wanips'])? 'yes' : 'no';
	$pconfig['wangateips'] = htmlspecialchars($_GET['wangateips'])? 'yes' : 'no';
	$pconfig['wandnsips'] = htmlspecialchars($_GET['wandnsips'])? 'yes' : 'no';
	$pconfig['vips'] = htmlspecialchars($_GET['vips'])? 'yes' : 'no';
	$pconfig['vpnips'] = htmlspecialchars($_GET['vpnips'])? 'yes' : 'no';

	// Now retrieve the "selected alias" returned from SELECT ALIAS page
	if ($_GET['varname'] == "address" && isset($_GET['varvalue']))
		$pconfig[$_GET['varname']] = htmlspecialchars($_GET['varvalue']);
}

/* If no entry for this passlist, then create a UUID and treat it like a new list */
if (!isset($a_passlist[$id]['uuid']) && empty($pconfig['uuid'])) {
	$passlist_uuid = 0;
	while ($passlist_uuid > 65535 || $passlist_uuid == 0) {
		$passlist_uuid = mt_rand(1, 65535);
		$pconfig['uuid'] = $passlist_uuid;
		$pconfig['name'] = "passlist_{$passlist_uuid}";
	}
}
elseif (!empty($pconfig['uuid'])) {
	$passlist_uuid = $pconfig['uuid'];	
}
else
	$passlist_uuid = $a_passlist[$id]['uuid'];

/* returns true if $name is a valid name for a pass list file name or ip */
function is_validpasslistname($name) {
	if (!is_string($name))
		return false;

	if (!preg_match("/[^a-zA-Z0-9\_\.\/]/", $name))
		return true;

	return false;
}

if ($_POST['save']) {
	unset($input_errors);
	$pconfig = $_POST;

	/* input validation */
	$reqdfields = explode(" ", "name");
	$reqdfieldsn = explode(",", "Name");

	$pf_version=substr(trim(file_get_contents("/etc/version")),0,3);
	if ($pf_version < 2.1)
		$input_errors = eval('do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors); return $input_errors;');
	else
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if(strtolower($_POST['name']) == "defaultpasslist")
		$input_errors[] = gettext("Pass List file names may not be named defaultpasslist.");

	if (is_validpasslistname($_POST['name']) == false)
		$input_errors[] = gettext("Pass List file name may only consist of the characters \"a-z, A-Z, 0-9 and _\". Note: No Spaces or dashes. Press Cancel to reset.");

	/* check for name conflicts */
	foreach ($a_passlist as $p_list) {
		if (isset($id) && ($a_passlist[$id]) && ($a_passlist[$id] === $p_list))
			continue;

		if ($p_list['name'] == $_POST['name']) {
			$input_errors[] = gettext("A Pass List file name with this name already exists.");
			break;
		}
	}

	if ($_POST['address']) {
		if (!is_alias($_POST['address']))
			$input_errors[] = gettext("A valid alias must be provided");
		if (is_alias($_POST['address']) && trim(filter_expand_alias($_POST['address'])) == "")
			$input_errors[] = gettext("FQDN aliases are not supported in Suricata.");
	}
	if (!$input_errors) {
		$p_list = array();
		/* post user input */
		$p_list['name'] = $_POST['name'];
		$p_list['uuid'] = $passlist_uuid;
		$p_list['localnets'] = $_POST['localnets']? 'yes' : 'no';
		$p_list['wanips'] = $_POST['wanips']? 'yes' : 'no';
		$p_list['wangateips'] = $_POST['wangateips']? 'yes' : 'no';
		$p_list['wandnsips'] = $_POST['wandnsips']? 'yes' : 'no';
		$p_list['vips'] = $_POST['vips']? 'yes' : 'no';
		$p_list['vpnips'] = $_POST['vpnips']? 'yes' : 'no';

		$p_list['address'] = $_POST['address'];
		$p_list['descr']  =  mb_convert_encoding(str_replace("\r\n", "\n", $_POST['descr']),"HTML-ENTITIES","auto");
		$p_list['detail'] = $final_address_details;

		if (isset($id) && $a_passlist[$id])
			$a_passlist[$id] = $p_list;
		else
			$a_passlist[] = $p_list;

		write_config("Suricata pkg: modified PASS LIST {$p_list['name']}.");

		/* create pass list and homenet file, then sync files */
		conf_mount_rw();
		sync_suricata_package_config();
		conf_mount_ro();

		header("Location: /suricata/suricata_passlist.php");
		exit;
	}
}

$pgtitle = gettext("Suricata: Pass List Edit - {$pconfig['name']}");
include_once("head.inc");
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC" >

<?php
include("fbegin.inc");
?>
<script type="text/javascript" src="/javascript/autosuggest.js">
</script>
<script type="text/javascript" src="/javascript/suggestions.js">
</script>
<form action="suricata_passlist_edit.php" method="post" name="iform" id="iform">
<input name="id" type="hidden" value="<?=$id;?>" />

<?php
if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);
?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tbody>
<tr><td>
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Interfaces"), false, "/suricata/suricata_interfaces.php");
	$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
	$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
	$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php");
	$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
	$tab_array[] = array(gettext("Pass Lists"), true, "/suricata/suricata_passlist.php");
	$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
	$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php?instance={$instanceid}");
	$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
	$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
	$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
	$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
	display_top_tabs($tab_array, true);
?>
	</td>
</tr>
<tr><td><div id="mainarea">
<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
	<tbody>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Add the name and " .
		"description of the file."); ?></td>
	</tr>
	<tr>
		<td valign="top" class="vncellreq"><?php echo gettext("Name"); ?></td>
		<td class="vtable"><input name="name" type="text" id="name" class="formfld unknown" 
			size="40" value="<?=htmlspecialchars($pconfig['name']);?>" /> <br />
		<span class="vexpl"> <?php echo gettext("The list name may only consist of the " .
		"characters \"a-z, A-Z, 0-9 and _\"."); ?>&nbsp;&nbsp;<span class="red"><?php echo gettext("Note:"); ?> </span>
		<?php echo gettext("No Spaces or dashes."); ?> </span></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Description"); ?></td>
		<td width="78%" class="vtable"><input name="descr" type="text" class="formfld unknown" 
			id="descr" size="40" value="<?=$pconfig['descr'];?>" /> <br />
		<span class="vexpl"> <?php echo gettext("You may enter a description here for your " .
		"reference (not parsed)."); ?> </span></td>
	</tr>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Add auto-generated IP Addresses."); ?></td>
	</tr>

	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Local Networks"); ?></td>
		<td width="78%" class="vtable"><input name="localnets" type="checkbox"
			id="localnets" size="40" value="yes"
			<?php if($pconfig['localnets'] == 'yes'){ echo "checked";} if($pconfig['localnets'] == ''){ echo "checked";} ?> />
		<span class="vexpl"> <?php echo gettext("Add firewall Local Networks to the list (excluding WAN)."); ?> </span></td>
	</tr>

	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("WAN IPs"); ?></td>
		<td width="78%" class="vtable"><input name="wanips" type="checkbox"
			id="wanips" size="40" value="yes"
			<?php if($pconfig['wanips'] == 'yes'){ echo "checked";} if($pconfig['wanips'] == ''){ echo "checked";} ?> />
		<span class="vexpl"> <?php echo gettext("Add WAN interface IPs to the list."); ?> </span></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("WAN Gateways"); ?></td>
		<td width="78%" class="vtable"><input name="wangateips"
			type="checkbox" id="wangateips" size="40" value="yes"
			<?php if($pconfig['wangateips'] == 'yes'){ echo "checked";} if($pconfig['wangateips'] == ''){ echo "checked";} ?> />
		<span class="vexpl"> <?php echo gettext("Add WAN Gateways to the list."); ?> </span></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("WAN DNS servers"); ?></td>
		<td width="78%" class="vtable"><input name="wandnsips"
			type="checkbox" id="wandnsips" size="40" value="yes"
			<?php if($pconfig['wandnsips'] == 'yes'){ echo "checked";} if($pconfig['wandnsips'] == ''){ echo "checked";} ?> />
		<span class="vexpl"> <?php echo gettext("Add WAN DNS servers to the list."); ?> </span></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("Virtual IP Addresses"); ?></td>
		<td width="78%" class="vtable"><input name="vips" type="checkbox"
			id="vips" size="40" value="yes"
			<?php if($pconfig['vips'] == 'yes'){ echo "checked";} if($pconfig['vips'] == ''){ echo "checked";} ?> />
		<span class="vexpl"> <?php echo gettext("Add Virtual IP Addresses to the list."); ?> </span></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell"><?php echo gettext("VPNs"); ?></td>
		<td width="78%" class="vtable"><input name="vpnips" type="checkbox"
			id="vpnips" size="40" value="yes"
			<?php if($pconfig['vpnips'] == 'yes'){ echo "checked";} if($pconfig['vpnips'] == ''){ echo "checked";} ?> />
		<span class="vexpl"> <?php echo gettext("Add VPN Addresses to the list."); ?> </span></td>
	</tr>
	<tr>
		<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Add custom IP Addresses from configured Aliases."); ?></td>
	</tr>
	<tr>
		<td width="22%" valign="top" class="vncell">
		<?php echo gettext("Assigned Aliases:"); ?>
		</td>
		<td width="78%" class="vtable">
		<input autocomplete="off" name="address" type="text" class="formfldalias" id="address" size="30" value="<?=htmlspecialchars($pconfig['address']);?>"
		title="<?=trim(filter_expand_alias($pconfig['address']));?>"/>&nbsp;&nbsp;&nbsp;&nbsp;
		<input type="button" class="formbtns" value="Aliases" onclick="selectAlias();" 
		title="<?php echo gettext("Select an existing IP alias");?>"/>
		</td>
	</tr>
	<tr>
		<td width="22%" valign="top">&nbsp;</td>
		<td width="78%">
			<input id="save" name="save" type="submit" class="formbtn" value="Save" />
			<input id="cancel" name="cancel" type="submit" class="formbtn" value="Cancel" />
		</td>
	</tr>
	</tbody>
</table>
</div>
</td></tr></tbody>
</table>
</form>
<script type="text/javascript">
<?php
        $isfirst = 0;
        $aliases = "";
        $addrisfirst = 0;
        $aliasesaddr = "";
        if(isset($config['aliases']['alias']) && is_array($config['aliases']['alias']))
                foreach($config['aliases']['alias'] as $alias_name) {
			if ($alias_name['type'] != "host" && $alias_name['type'] != "network")
				continue;
                        if($addrisfirst == 1) $aliasesaddr .= ",";
                        $aliasesaddr .= "'" . $alias_name['name'] . "'";
                        $addrisfirst = 1;
                }
?>
        var addressarray=new Array(<?php echo $aliasesaddr; ?>);

function createAutoSuggest() {
<?php
	echo "objAlias = new AutoSuggestControl(document.getElementById('address'), new StateSuggestions(addressarray));\n";
?>
}

function selectAlias() {

	var loc;
	var fields = [ "name", "descr", "localnets", "wanips", "wangateips", "wandnsips", "vips", "vpnips", "address" ];

	// Scrape current form field values and add to
	// the select alias URL as a query string.
	var loc = '/suricata/suricata_select_alias.php?id=<?=$id;?>&act=import&type=host|network';
	loc = loc + '&varname=address&multi_ip=yes';
	loc = loc + '&returl=<?=urlencode($_SERVER['PHP_SELF']);?>';
	loc = loc + '&uuid=<?=$passlist_uuid;?>';

	// Iterate over just the specific form fields we want to pass to
	// the select alias URL.
	fields.forEach(function(entry) {
		var tmp = $(entry).serialize();
		if (tmp.length > 0)
			loc = loc + '&' + tmp;
	});
	
	window.parent.location = loc; 
}

setTimeout("createAutoSuggest();", 500);

</script>
<?php include("fend.inc"); ?>
</body>
</html>
