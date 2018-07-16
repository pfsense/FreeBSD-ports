<?php
/*
 * system_patches.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2012-2017 Rubicon Communications, LLC (Netgate)
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

##|+PRIV
##|*IDENT=page-system-patches
##|*NAME=System: Patches
##|*DESCR=Allow access to the 'System: Patches' page.
##|*MATCH=system_patches.php*
##|-PRIV

require("guiconfig.inc");
require_once("functions.inc");
require_once("itemid.inc");
require_once("patches.inc");
require_once("pkg-utils.inc");
require_once('classes/Form.class.php');

if (!is_array($config['installedpackages'])) {
	$config['installedpackages'] = array();
}
if (!is_array($config['installedpackages']['patches'])) {
	$config['installedpackages']['patches'] = array();
}
if (!is_array($config['installedpackages']['patches']['item'])) {
	$config['installedpackages']['patches']['item'] = array();
}

$a_patches = &$config['installedpackages']['patches']['item'];

/* if a custom message has been passed along, lets process it */
if ($_GET['savemsg']) {
	$savemsg = $_GET['savemsg'];
}

if ($_POST) {
	$pconfig = $_POST;
	if ($_POST['apply']) {
		write_config(gettext("System: Patches: applied a patch."));
	}
}

if (($_GET['act'] == "fetch") && ($a_patches[$_GET['id']])) {
	$savemsg = patch_fetch($a_patches[$_GET['id']]) ? gettext("Patch Fetched Successfully") : gettext("Patch Fetch Failed");
}
if (($_GET['act'] == "test") && ($a_patches[$_GET['id']])) {
	$savemsg = patch_test_apply($a_patches[$_GET['id']]) ? gettext("Patch can be applied cleanly") : gettext("Patch can NOT be applied cleanly");
	$savemsg .= " (<a href=\"system_patches.php?id={$_GET['id']}&amp;fulltest=apply\">" . gettext("detail") . "</a>)";
	$savemsg .= empty($savemsg) ? "" : "<br/>";
	$savemsg .= patch_test_revert($a_patches[$_GET['id']]) ? gettext("Patch can be reverted cleanly") : gettext("Patch can NOT be reverted cleanly");
	$savemsg .= " (<a href=\"system_patches.php?id={$_GET['id']}&amp;fulltest=revert\">" . gettext("detail") . "</a>)";
}
if (($_GET['fulltest']) && ($a_patches[$_GET['id']])) {
	if ($_GET['fulltest'] == "apply") {
		$fulldetail = patch_test_apply($a_patches[$_GET['id']], true);
	} elseif ($_GET['fulltest'] == "revert") {
		$fulldetail = patch_test_revert($a_patches[$_GET['id']], true);
	}
}
if (($_GET['act'] == "apply") && ($a_patches[$_GET['id']])) {
	$savemsg = patch_apply($a_patches[$_GET['id']]) ? gettext("Patch applied successfully") : gettext("Patch could NOT be applied!");
}
if (($_GET['act'] == "revert") && ($a_patches[$_GET['id']])) {
	$savemsg = patch_revert($a_patches[$_GET['id']]) ? gettext("Patch reverted successfully") : gettext("Patch could NOT be reverted!");
}

$need_save = false;
if (isset($_POST['del'])) {
	/* delete selected patches */
	if (is_array($_POST['patch']) && count($_POST['patch'])) {
		foreach ($_POST['patch'] as $patchi) {
			unset($a_patches[$patchi]);
		}
		$need_save = true;
	}
} else {
	/* yuck - IE won't send value attributes for image buttons, while Mozilla does - so we use .x/.y to find move button clicks instead... */
	unset($delbtn, $movebtn);
	foreach ($_POST as $pn => $pd) {
		if (preg_match("/del_(\d+)/", $pn, $matches)) {
			$delbtn = $matches[1];
		} elseif (preg_match("/move_(\d+)/", $pn, $matches)) {
			$movebtn = $matches[1];
		}
	}

	/* move selected patches before this patch */
	if (isset($movebtn) && is_array($_POST['patch']) && count($_POST['patch'])) {
		$a_patches_new = array();

		/* copy all patches < $movebtn and not selected */
		for ($i = 0; $i < $movebtn; $i++) {
			if (!in_array($i, $_POST['patch'])) {
				$a_patches_new[] = $a_patches[$i];
			}
		}

		/* copy all selected patches */
		for ($i = 0; $i < count($a_patches); $i++) {
			if ($i == $movebtn) {
				continue;
			}
			if (in_array($i, $_POST['patch'])) {
				$a_patches_new[] = $a_patches[$i];
			}
		}

		/* copy $movebtn patch */
		if ($movebtn < count($a_patches)) {
			$a_patches_new[] = $a_patches[$movebtn];
		}

		/* copy all patches > $movebtn and not selected */
		for ($i = $movebtn+1; $i < count($a_patches); $i++) {
			if (!in_array($i, $_POST['patch'])) {
				$a_patches_new[] = $a_patches[$i];
			}
		}
		$a_patches = $a_patches_new;
		$need_save = true;
	} else if (isset($delbtn)) {
		unset($a_patches[$delbtn]);
		$need_save = true;
	}
}

if ($need_save) {
	write_config(gettext("System: Patches: saved configuration."));
	header("Location: system_patches.php");
	return;
}

$closehead = false;
$pgtitle = array(gettext("System"), gettext("Patches"));
include("head.inc");

if ($savemsg) {
	print_info_box($savemsg, 'success');
}
?>
<? print_info_box(gettext("This page allows adding patches, either from the official code repository or pasted in from e-mail or other sources. <br />Use with caution!"), 'warning'); ?>

<form name="mainform" method="post">
	<?php if (!empty($fulldetail)): ?>
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Patch Test Output')?> <?= htmlspecialchars($_GET['fulltest']) ?></h2></div>
		<div class="panel-body table-responsive">
			<pre><?=$fulldetail; ?></pre>
			<a href="system_patches.php">Close</a><br/><br/>
		</div>
	</div>
	<?php endif; ?>
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('System Patches')?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th width="5%">&nbsp;</th>
						<th width="65%"><?=gettext("Description")?></th>
						<th width="5%"><?=gettext("Fetch")?></th>
						<th width="5%"><?=gettext("Test")?></th>
						<th width="5%"><?=gettext("Apply")?></th>
						<th width="5%"><?=gettext("Revert")?></th>
						<th width="5%"><?=gettext("Auto Apply")?></th>
						<th width="5%"><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody class="patchentries">


<?php
$npatches = $i = 0;
foreach ($a_patches as $thispatch):
	$can_apply = patch_test_apply($thispatch);
	$can_revert = patch_test_revert($thispatch);

?>

	<tr valign="top" id="fr<?=$npatches?>">

		<tr id="fr<?=$i?>" id="frd<?=$i?>" ondblclick="document.location='system_patches_edit.php?id=<?= $i ?>'">
		<td>
			<input type="checkbox" id="frc<?=$i?>" name="patch[]" value="<?=$i?>" onclick="fr_bgcolor('<?=$i?>')" />
			<a class="fa fa-anchor" id="Xmove_<?=$i?>" title="<?=gettext("Move checked entries to here")?>"></a>
		</td>

		<td id="frd<?=$i?>" onclick="fr_toggle(<?=$i?>)">
			<?=$thispatch['descr']?>
		</td>
		<td id="frd<?=$i?>" onclick="fr_toggle(<?=$i?>)">
		<?php if (empty($thispatch['patch'])): ?>
			<a href="system_patches.php?id=<?=$i?>&amp;act=fetch" class="btn btn-sm btn-primary"><i class="fa fa-download"></i> <?=gettext("Fetch"); ?></a>
		<?php elseif (!empty($thispatch['location'])): ?>
			<a href="system_patches.php?id=<?=$i?>&amp;act=fetch" class="btn btn-sm btn-primary"><i class="fa fa-refresh"></i> <?=gettext("Re-Fetch"); ?></a>
		<?php endif; ?>
		</td>
		<td id="frd<?=$i?>" onclick="fr_toggle(<?=$i?>)">
		<?php if (!empty($thispatch['patch'])): ?>
			<a href="system_patches.php?id=<?=$i?>&amp;act=test" class="btn btn-sm btn-primary"><i class="fa fa-check"></i> <?=gettext("Test"); ?></a>
		<?php endif; ?>
		</td>
		<td id="frd<?=$i?>" onclick="fr_toggle(<?=$i?>)">
		<?php if ($can_apply): ?>
			<a href="system_patches.php?id=<?=$i?>&amp;act=apply" class="btn btn-sm btn-primary"><i class="fa fa-plus-circle"></i> <?=gettext("Apply"); ?></a>
		<?php endif; ?>
		</td>
		<td id="frd<?=$i?>" onclick="fr_toggle(<?=$i?>)">
		<?php if ($can_revert): ?>
			<a href="system_patches.php?id=<?=$i?>&amp;act=revert" class="btn btn-sm btn-primary"><i class="fa fa-minus-circle"></i> <?=gettext("Revert"); ?></a>
		<?php endif; ?>
		</td>
		<td id="frd<?=$i?>" onclick="fr_toggle(<?=$i?>)">
			<?= isset($thispatch['autoapply']) ? "Yes" : "No" ?>
		</td>

		<td style="cursor: pointer;">
			<button style="display: none;" class="btn btn-default btn-xs" type="submit" id="move_<?=$i?>" name="move_<?=$i?>" value="move_<?=$i?>"><?=gettext("Move checked entries to here")?></button>
			<a class="fa fa-pencil" href="system_patches_edit.php?id=<?=$i?>" title="<?=gettext("Edit Patch"); ?>"></a>
			<a class="fa fa-trash no-confirm" id="Xdel_<?=$i?>" title="<?=gettext('Delete Patch'); ?>"></a>
			<button style="display: none;" class="btn btn-xs btn-warning" type="submit" id="del_<?=$i?>" name="del_<?=$i?>" value="del_<?=$i?>" title="<?=gettext('Delete Patch'); ?>">delete</button>
		</td>
	</tr>
<?php
	$i++;
	$npatches++;
endforeach;
?>
				</tbody>
			</table>
		</div>
	</div>
	<nav class="action-buttons">
		<a href="system_patches_edit.php" class="btn btn-success btn-sm">
			<i class="fa fa-plus icon-embed-btn"></i>
			<?=gettext("Add New Patch")?>
		</a>
<?php if ($i !== 0): ?>
		<button type="submit" name="del" class="btn btn-danger btn-sm" value="<?=gettext("Delete selected P1s")?>">
			<i class="fa fa-trash icon-embed-btn"></i>
			<?=gettext("Delete Patches")?>
		</button>
<?php endif; ?>
	</nav>
</form>

<div id="infoblock">
	<?=print_info_box('<strong>' . gettext("Note:") . '</strong><br />' .
	gettext("Each patch is tested and the appropriate action is shown. If neither 'Apply' or 'Revert' shows up, the patch cannot be used (check the pathstrip and whitespace options).") .
	"<br/><br/>" .
	gettext("Use the 'Test' link to see if a patch can be applied or reverted. Patches may be reordered so that higher patches apply later than lower patches."), 'info'); ?>
</div>

<script type="text/javascript">
//<![CDATA[
function show_phase2(id, buttonid) {
	document.getElementById(buttonid).innerHTML='';
	document.getElementById(id).style.display = "block";
	var visible = id + '-visible';
	document.getElementById(visible).value = "1";
}

events.push(function() {
	$('[id^=Xmove_]').click(function (event) {
		$('#' + event.target.id.slice(1)).click();
	});

	$('[id^=Xdel_]').click(function (event) {
		if(confirm("<?=gettext('Delete this patch entry?')?>")) {
			$('#' + event.target.id.slice(1)).click();
		}
	});
});
//]]>
</script>

<?php include("foot.inc"); ?>
