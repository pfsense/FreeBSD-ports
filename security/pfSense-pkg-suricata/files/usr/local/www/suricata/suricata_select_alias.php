<?php
/* $Id$ */
/*
	suricata_select_alias.php
	Copyright (C) 2014 Bill Meeks
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/

require("guiconfig.inc");
require_once("functions.inc");
require_once("/usr/local/pkg/suricata/suricata.inc");

// Need to keep track of who called us so we can return to the correct page
// when the SAVE button is clicked.  On initial entry, a GET variable is 
// passed with the referrer's URL encoded within.  That value is saved and
// used when SAVE or CANCEL is clicked to return to the referring page.
// 

// Retrieve the QUERY STRING of the original referrer so we can return it.
// On the initial pass, we will save it in a hidden POST field so we won't
// overwrite it on subsequent POST-BACKs to this page.
if (!isset($_POST['org_querystr']))
	$querystr = $_SERVER['QUERY_STRING'];
else
	$querystr = $_POST['org_querystr'];

// Retrieve any passed QUERY STRING or POST variables
if (isset($_POST['type']))
	$type = htmlspecialchars($_POST['type']);
elseif (isset($_GET['type']))
	$type = htmlspecialchars($_GET['type']);

if (isset($_POST['varname']))
	$varname = htmlspecialchars($_POST['varname']);
elseif (isset($_GET['varname']))
	$varname = htmlspecialchars($_GET['varname']);

if (isset($_POST['multi_ip']))
	$multi_ip = htmlspecialchars($_POST['multi_ip']);
elseif (isset($_GET['multi_ip']))
	$multi_ip = htmlspecialchars($_GET['multi_ip']);

if (isset($_POST['returl']) && substr($_POST['returl'], 0, 1) == '/')
	$referrer = urldecode($_POST['returl']);
elseif (isset($_GET['returl']) && substr($_GET['returl'], 0, 1) == '/')
	$referrer = urldecode($_GET['returl']);
else
	$referrer = $_SERVER['HTTP_REFERER'];

// Make sure we have a valid VARIABLE name
// and ALIAS TYPE, or else bail out.
if (is_null($type) || is_null($varname)) {
	header("Location: {$referrer}?{$querystr}");
	exit;
}

// Used to track if any selectable Aliases are found
$selectablealias = false;

// Initialize required array variables as necessary
if (!is_array($config['aliases']['alias']))
	$config['aliases']['alias'] = array();
$a_aliases = $config['aliases']['alias'];

// Create an array consisting of the Alias types the
// caller wants to select from.
$a_types = array();
$a_types = explode('|', strtolower($type));

// Create a proper title based on the Alias types
$title = "a";
switch (count($a_types)) {
	case 1:
		$title .= " " . ucfirst($a_types[0]);
		break;

	case 2:
		$title .= " " . ucfirst($a_types[0]) . " or " . ucfirst($a_types[1]);
		break;

	case 3:
		$title .= " " . ucfirst($a_types[0]) . ", " . ucfirst($a_types[1]) . " or " . ucfirst($a_types[2]);

	default:
		$title = "n";
}

if ($_POST['cancel']) {
	header("Location: {$referrer}?{$querystr}");
	exit;
}

if ($_POST['save']) {
	if(empty($_POST['alias']))
		$input_errors[] = gettext("No alias is selected.  Please select an alias before saving.");

	// if no errors, write new entry to conf
	if (!$input_errors) {
		$selection = $_POST['alias'];
		header("Location: {$referrer}?{$querystr}&varvalue={$selection}");
		exit;
	}
}

$pgtitle = gettext("Suricata: Select {$title} Alias");
include("head.inc");

?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="suricata_select_alias.php" method="post">
<input type="hidden" name="varname" value="<?=$varname;?>"/>
<input type="hidden" name="type" value="<?=$type;?>"/>
<input type="hidden" name="multi_ip" value="<?=$multi_ip;?>"/>
<input type="hidden" name="returl" value="<?=htmlspecialchars($referrer);?>"/>
<input type="hidden" name="org_querystr" value="<?=htmlspecialchars($querystr);?>"/>
<?php if ($input_errors) print_input_errors($input_errors); ?>
<div id="boxarea">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
	<td class="tabcont"><strong><?=gettext("Select an Alias to use from the list below.");?></strong><br/>
	</td>
</tr>
<tr>
	<td class="tabcont">
		<table id="sortabletable1" style="table-layout: fixed;" class="sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
			<colgroup>
				<col width="5%" align="center">
				<col width="25%" align="left" axis="string">
				<col width="35%" align="left" axis="string">
				<col width="35%" align="left" axis="string">
			</colgroup>
			<thead>
			   <tr class="sortableHeaderRowIdentifier">
				<th class="listhdrr sorttable_nosort"></th>
				<th class="listhdrr" axis="string"><?=gettext("Alias Name"); ?></th>
				<th class="listhdrr" axis="string"><?=gettext("Values"); ?></th>
				<th class="listhdrr" axis="string"><?=gettext("Description"); ?></th>
			   </tr>
			</thead>
		<tbody>
		  <?php $i = 0; foreach ($a_aliases as $alias): ?>
			<?php if (!in_array($alias['type'], $a_types))
				continue;
			      if ( ($alias['type'] == "network" || $alias['type'] == "host") && 
				    $multi_ip != "yes" && 
				    !suricata_is_single_addr_alias($alias['name'])) {
				$textss = "<span class=\"gray\">";
				$textse = "</span>";
				$disable = true;
			        $tooltip = gettext("Aliases resolving to multiple address entries cannot be used with the destination target.");
			      }
			      elseif (($alias['type'] == "network" || $alias['type'] == "host") && 
				       trim(filter_expand_alias($alias['name'])) == "") {
				$textss = "<span class=\"gray\">";
				$textse = "</span>";
				$disable = true;
			        $tooltip = gettext("Aliases representing a FQDN host cannot be used in Suricata configurations.");
			      }
			      else {
				$textss = "";
				$textse = "";
				$disable = "";
				$selectablealias = true;
			        $tooltip = gettext("Selected entry will be imported. Click to toggle selection.");
			      }
			?>
			<?php if ($disable): ?>
			<tr title="<?=$tooltip;?>">
			  <td class="listlr" align="center"><img src="../themes/<?=$g['theme'];?>/images/icons/icon_block_d.gif" width="11" height"11" border="0"/>
			<?php else: ?>
			<tr>
			  <td class="listlr" align="center"><input type="radio" name="alias" value="<?=htmlspecialchars($alias['name']);?>" title="<?=$tooltip;?>"/></td>
			<?php endif; ?>
			  <td class="listr" align="left"><?=$textss . htmlspecialchars($alias['name']) . $textse;?></td>
			  <td class="listr" align="left">
			      <?php
				$tmpaddr = explode(" ", $alias['address']);
				$addresses = implode(", ", array_slice($tmpaddr, 0, 10));
				echo "{$textss}{$addresses}{$textse}";
				if(count($tmpaddr) > 10) {
					echo "...";
				}
			    ?>
			  </td>
			  <td class="listbg" align="left">
			    <?=$textss . htmlspecialchars($alias['descr']) . $textse;?>&nbsp;
			  </td>
			</tr>
		  <?php $i++; endforeach; ?>
		</table>
	</td>
</tr>
<?php if (!$selectablealias): ?>
<tr>
	<td class="tabcont" align="center"><b><?php echo gettext("There are currently no defined Aliases eligible for selection.");?></b></td>
</tr>
<tr>
	<td class="tabcont" align="center">
	<input type="Submit" name="cancel" value="Cancel" id="cancel" class="formbtn" title="<?=gettext("Cancel import operation and return");?>"/>
	</td>
</tr>
<?php else: ?>
<tr>
	<td class="tabcont" align="center">
	<input type="Submit" name="save" value="Save" id="save" class="formbtn" title="<?=gettext("Import selected item and return");?>"/>&nbsp;&nbsp;&nbsp;
	<input type="Submit" name="cancel" value="Cancel" id="cancel" class="formbtn" title="<?=gettext("Cancel import operation and return");?>"/>
	</td>
</tr>
<?php endif; ?>
<tr>
	<td class="tabcont">
	<span class="vexpl"><span class="red"><strong><?=gettext("Note:"); ?><br></strong></span><?=gettext("Fully-Qualified Domain Name (FQDN) host Aliases cannot be used as Suricata configuration parameters.  Aliases resolving to a single FQDN value are disabled in the list above.  In the case of nested Aliases where one or more of the nested values is a FQDN host, the FQDN host will not be included in the {$title} configuration.");?></span>
	</td>
</tr>
</table>
</div>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
