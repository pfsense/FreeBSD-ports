<?php
/*
 * snort_select_alias.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2018 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2018 Bill Meeks
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

require("guiconfig.inc");
require_once("functions.inc");
require_once("/usr/local/pkg/snort/snort.inc");

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
if (!is_array($config['aliases'])) {
	$config['aliases'] = array();
}
if (!is_array($config['aliases']['alias'])) {
	$config['aliases']['alias'] = array();
}
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

$pgtitle = array(gettext("Snort"), gettext("Select {$title} Alias"));
include("head.inc");
?>
<form action="snort_select_alias.php" method="post">
<input type="hidden" name="varname" value="<?=$varname;?>"/>
<input type="hidden" name="type" value="<?=$type;?>"/>
<input type="hidden" name="multi_ip" value="<?=$multi_ip;?>"/>
<input type="hidden" name="returl" value="<?=htmlspecialchars($referrer);?>"/>
<input type="hidden" name="org_querystr" value="<?=htmlspecialchars($querystr);?>"/>

<?php
if ($input_errors) {
		print_input_errors($input_errors);
}
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Select an Alias to use from the list below")?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
				<tr>
				<th></th>
				<th><?=gettext("Alias Name"); ?></th>
				<th><?=gettext("Values"); ?></th>
				<th><?=gettext("Description"); ?></th>
			</tr>
			</thead>
			<tbody>
<?php $i = 0; foreach ($a_aliases as $alias):
if (!in_array($alias['type'], $a_types))
	continue;

	if ( ($alias['type'] == "network" || $alias['type'] == "host") &&
	    $multi_ip != "yes" &&
	    !snort_is_single_addr_alias($alias['name'])) {
		$textss = "<span class=\"gray\">";
		$textse = "</span>";
		$disable = true;
		$tooltip = gettext("Aliases resolving to multiple address entries cannot be used with the destination target.");
	} elseif (($alias['type'] == "network" || $alias['type'] == "host") && trim(filter_expand_alias($alias['name'])) == "") {
		$textss = "<span class=\"gray\">";
		$textse = "</span>";
		$disable = true;
		$tooltip = gettext("Aliases representing a FQDN host cannot be used in Snort configurations.");
	} else {
		$textss = "";
		$textse = "";
		$disable = "";
		$selectablealias = true;
		$tooltip = gettext("Selected entry will be imported. Click to toggle selection.");
	}

 if ($disable):
 ?>
				<tr title="<?=$tooltip;?>">
					<td><i class="fa fa-times text-danger"></i></td>
<?php else: ?>
				<tr>
					<td align="center"><input type="radio" name="alias" value="<?=htmlspecialchars($alias['name']);?>" title="<?=$tooltip;?>"/></td>
<?php endif; ?>
					<td align="left"><?=$textss . htmlspecialchars($alias['name']) . $textse;?></td>
					<td>
<?php
	$tmpaddr = explode(" ", $alias['address']);
	$addresses = implode(", ", array_slice($tmpaddr, 0, 10));
	echo "{$textss}{$addresses}{$textse}";
	if(count($tmpaddr) > 10) {
		echo "...";
	}
?>
					</td>
					<td><?=$textss . htmlspecialchars($alias['descr']) . $textse;?>&nbsp;</td>
				</tr>
<?php $i++; endforeach; ?>
			</tbody>
		</table>

<?php if (!$selectablealias) {
	print_info_box(gettext("There are currently no defined Aliases eligible for selection.") . '&nbsp;&nbsp;&nbsp;&nbsp;', 'alert-warning', 'cancel', 'Cancel');

} else {
?>

		</div>
	</div>

<nav class="action-buttons">
	<input type="Submit" name="save" value="Save" id="save" class="btn btn-sm btn-primary" title="<?=gettext("Import selected item and return");?>"/>&nbsp;&nbsp;&nbsp;
	<input type="Submit" name="cancel" value="Cancel" id="cancel" class="btn btn-sm btn-warning" title="<?=gettext("Cancel import operation and return");?>"/>
</nav>
<div class="infoblock">
<?php

	print_info_box('<strong>' . gettext('Note:') . '<br></strong>' . gettext('Fully-Qualified Domain Name (FQDN) host Aliases cannot be used as Snort configuration parameters. ' .
		' Aliases resolving to a single FQDN value are disabled in the list above. ' .
		'In the case of nested Aliases where one or more of the nested values is a FQDN host, the FQDN host will not be included in ' . $title . ' configuration.'), info, false);
} ?>
</div>
</form>

<?php include("foot.inc"); ?>
