<?php
/*
 * system_patches.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2012-2024 Rubicon Communications, LLC (Netgate)
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
require_once("system.inc");
require_once("functions.inc");
require_once("itemid.inc");
require_once("patches.inc");
require_once("pkg-utils.inc");
require_once('classes/Form.class.php');

init_config_arr(array('installedpackages', 'patches', 'item'));
$a_patches = &$config['installedpackages']['patches']['item'];
$savemsgtype = 'success';

list($thisversion, $thisversiontype) = explode('-', $g['product_version'], 2);
$platform = system_identify_specific_platform();

/* if a custom message has been passed along, lets process it */
if ($_POST['savemsg']) {
	$savemsg = $_POST['savemsg'];
}

if ($_POST && $_POST['apply']) {
	write_config(gettext("System: Patches: applied a patch."));
}

if (in_array($_POST['all'], ['apply', 'revert']) &&
    in_array($_POST['type'], ['custom', 'recommended'])) {
	$typestr = "";
	if ($_POST['type'] == 'custom') {
		$patchlist = $a_patches;
		$typestr = gettext('custom');
	} elseif ($_POST['type'] == 'recommended') {
		$patchlist = $recommended_patches;
		$typestr = gettext('recommended');
	}

	/* Revert in reverse order since patch order is significant! */
	if ($_POST['all'] == 'revert') {
		$patchlist = array_reverse($patchlist);
	}

	foreach ($patchlist as $thispatch) {
		if (($_POST['type'] == 'recommended') &&
		    (!in_array($thisversion, $thispatch['versions'])) ||
		    (array_key_exists('models', $thispatch) &&
		     !in_array($platform['name'], $thispatch['models']))) {
			/* This patch is not relevant to the running version, skip it */
			continue;
		}

		if ($_POST['all'] == 'apply') {
			if (patch_test_apply($thispatch)) {
				patch_apply($thispatch);
			}
		} elseif ($_POST['all'] == 'revert') {
			if (patch_test_revert($thispatch)) {
				patch_revert($thispatch);
			}
		}
	}

	if ($_POST['all'] == 'apply') {
		$savemsg = sprintf(gettext('Applied all %s patches'), $typestr);
	} elseif ($_POST['all'] == 'revert') {
		$savemsg = sprintf(gettext('Reverted all %s patches'), $typestr);
	}
	patchlog($savemsg);
	$savemsg .= '<br/><br/>' . gettext('Changes may not fully activate until the next reboot or restart of patched functions.');
}

if ((($_POST['type'] == 'custom') && ($a_patches[$_POST['id']])) ||
    (($_POST['type'] == 'recommended') && !empty(get_recommended_patch($_POST['id'])))) {
	$savemsg = "";

	if ($_POST['type'] == 'recommended') {
		$thispatch = get_recommended_patch($_POST['id']);
	} else {
		$thispatch = & $a_patches[$_POST['id']];
	}
	$descr = patch_descr($thispatch);

	switch ($_POST['act']) {
		case 'fetch':
			if ($_POST['type'] == 'recommended') {
				break;
			}
			if (patch_fetch($thispatch)) {
				$savemsg .= gettext("Patch fetched successfully");
			} else {
				$savemsgtype = 'danger';
				$savemsg .= gettext("Patch fetch failed");
			}
			patchlog($savemsg . $descr);
			break;
		case 'debug':
			$can_apply = patch_test_apply($thispatch);
			$can_revert = patch_test_revert($thispatch);
			if ($can_apply) {
				$savemsg .= gettext("Patch can apply cleanly");
				$resulticon = ' <i class="fa-solid fa-check"></i>';
			} else {
				$savemsg .= gettext("Patch does not apply cleanly");
				$resulticon = ' <i class="fa-solid fa-times"></i>';
			}
			$savemsg .= " (<a href=\"system_patches.php?id={$_POST['id']}&amp;type={$_POST['type']}&amp;act=debug&amp;fulldebug=apply\" usepost>" . gettext("detail") . "</a>)";
			$savemsg .= $resulticon;
			$savemsg .= "<br/>";
			if ($can_revert) {
				$savemsg .= gettext("Patch can revert cleanly");
				$resulticon = ' <i class="fa-solid fa-check"></i>';
			} else {
				$savemsg .= gettext("Patch does not revert cleanly");
				$resulticon = ' <i class="fa-solid fa-times"></i>';
			}
			$savemsg .= " (<a href=\"system_patches.php?id={$_POST['id']}&amp;type={$_POST['type']}&amp;act=debug&amp;fulldebug=revert\" usepost>" . gettext("detail") . "</a>)";
			$savemsg .= $resulticon;
			$savemsg .= "<br/><br/>";
			$savemsg .= gettext("Debug Result: ");
			if (!$can_apply && !$can_revert) {
				$savemsgtype = 'danger';
				$savemsg .= gettext("Fail") . "<br/><br/>";
				$savemsg .= gettext("This patch does not apply or revert cleanly.");
				$savemsg .= "<br/>";
				$savemsg .= gettext("The patch settings may be incorrect, the patch content may not be relevant to this version, " .
						"or the patch may depend upon another separate patch which must be applied first.");
			} elseif ($can_apply && $can_revert) {
				$savemsgtype = 'warning';
				$savemsg .= gettext("Warning") . "<br/><br/>";
				$savemsg .= gettext("This patch can both apply and revert cleanly. ");
				$savemsg .= "<br/>";
				$savemsg .= gettext("Typically this indicates that a simple patch has either already been applied once (applying it again is unnecessary) or that it only removes code (Applying is OK, reverting has no effect).");
			} elseif ($can_apply && !$can_revert) {
				$savemsg .= gettext("OK") . "<br/><br/>";
				$savemsg .= gettext("This patch is normal and has not yet been applied.");
			} elseif (!$can_apply && $can_revert) {
				$savemsg .= gettext("OK") . "<br/><br/>";
				$savemsg .= gettext("This patch is normal and has already been applied. The patch can be reverted if its changes are no longer required.");
			}

			if ($_POST['fulldebug']) {
				if ($_POST['fulldebug'] == "apply") {
					$fulldetail = patch_test_apply($thispatch, true);
				} elseif ($_POST['fulldebug'] == "revert") {
					$fulldetail = patch_test_revert($thispatch, true);
				}
			}
			break;
		case 'apply':
			if (patch_apply($thispatch)) {
				$savemsg .= gettext("Patch applied successfully");
			} else {
				$savemsgtype = 'danger';
				$savemsg .= gettext("Patch could NOT be applied");
			}
			patchlog($savemsg . $descr);
			break;
		case 'revert':
			if (patch_revert($thispatch)) {
				$savemsg .= gettext("Patch reverted successfully");
			} else {
				$savemsgtype = 'danger';
				$savemsg .= gettext("Patch could NOT be reverted!");
			}
			patchlog($savemsg . $descr);
			break;
		case 'view':
			if ($_POST['type'] == 'recommended') {
				$patchfile = $rec_patch_dir . basename($thispatch['uniqid']) . $patch_suffix;
				if (file_exists($patchfile)) {
					$fulldetail = file_get_contents($patchfile);
				}
			} else {
				if (!empty($thispatch['patch'])) {
					$fulldetail = base64_decode($thispatch['patch']);
				}
			}
			break;
		default:
	}
}
unset($thispatch);

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
	print_info_box($savemsg, $savemsgtype);
}
?>
<? print_info_box(gettext("This page allows adding patches, either from the official code repository or pasted in from e-mail or other sources. <br />Use with caution!"), 'warning'); ?>

<form name="mainform" method="post">
	<?php if (!empty($fulldetail)): ?>
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title">
		<?php if ($_POST['act'] == "view"): ?>
			<?= gettext('View') ?> <?= ucwords(htmlspecialchars($_POST['type'])) ?> <?= gettext('Patch') ?>
		<?php else: ?>
			<?= gettext('Patch Debug Output')?>: <?= ucwords(htmlspecialchars($_POST['fulldebug'])) ?>
		<?php endif; ?>
		</h2></div>
		<div class="panel-body table-responsive">
			<pre><?= htmlentities($fulldetail); ?></pre>
			<a href="system_patches.php">Close</a><br/><br/>
		</div>
	</div>
	<?php endif; ?>
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext('Custom System Patches')?></h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th width="5%">&nbsp;</th>
						<th width="60%"><?=gettext("Description")?></th>
						<th width="5%"><?=gettext("Fetch")?></th>
						<th width="5%"><?=gettext("Apply")?></th>
						<th width="5%"><?=gettext("Revert")?></th>
						<th width="5%"><?=gettext("View")?></th>
						<th width="5%"><?=gettext("Debug")?></th>
						<th width="5%"><?=gettext("Auto Apply")?></th>
						<th width="5%"><?=gettext("Actions")?></th>
					</tr>
				</thead>
				<tbody class="patchentries">
<?php
$i = 0;
$cus_can_apply=0;
$cus_can_revert=0;
foreach ($a_patches as $thispatch):
	$can_apply = patch_test_apply($thispatch);
	$can_revert = patch_test_revert($thispatch);
?>

	<tr id="fr<?=$i?>" id="frd<?=$i?>" ondblclick="document.location='system_patches_edit.php?id=<?= $i ?>'">
		<td>
			<input type="checkbox" id="frc<?=$i?>" name="patch[]" value="<?=$i?>" onclick="fr_bgcolor('<?=$i?>')" />
			<a class="fa-solid fa-anchor" id="Xmove_<?=$i?>" title="<?=gettext("Move checked entries to here")?>"></a>
		</td>

		<td id="frd<?=$i?>" onclick="fr_toggle(<?=$i?>)">
			<?=$thispatch['descr']?>
		</td>

		<td id="frd<?=$i?>" onclick="fr_toggle(<?=$i?>)">
		<?php if (empty($thispatch['patch'])): ?>
			<a href="system_patches.php?id=<?=$i?>&amp;type=custom&amp;act=fetch" class="btn btn-sm btn-primary" usepost><i class="fa-solid fa-download"></i> <?=gettext("Fetch"); ?></a>
		<?php elseif (!empty($thispatch['location'])): ?>
			<a href="system_patches.php?id=<?=$i?>&amp;type=custom&amp;act=fetch" class="btn btn-sm btn-primary" usepost><i class="fa-solid fa-arrows-rotate"></i> <?=gettext("Re-Fetch"); ?></a>
		<?php endif; ?>
		</td>

		<td id="frd<?=$i?>" onclick="fr_toggle(<?=$i?>)">
		<?php if ($can_apply):
			$cus_can_apply += 1; ?>
			<a href="system_patches.php?id=<?=$i?>&amp;type=custom&amp;act=apply" class="btn btn-sm btn-primary" usepost><i class="fa-solid fa-plus-circle"></i> <?=gettext("Apply"); ?></a>
		<?php endif; ?>
		</td>

		<td id="frd<?=$i?>" onclick="fr_toggle(<?=$i?>)">
		<?php if ($can_revert):
			$cus_can_revert += 1; ?>
			<a href="system_patches.php?id=<?=$i?>&amp;type=custom&amp;act=revert" class="btn btn-sm btn-primary" usepost><i class="fa-solid fa-minus-circle"></i> <?=gettext("Revert"); ?></a>
		<?php endif; ?>
		</td>

		<td id="frd<?=$i?>" onclick="fr_toggle(<?=$i?>)">
		<?php if (!empty($thispatch['patch'])): ?>
			<a href="system_patches.php?id=<?=$i?>&amp;type=custom&amp;act=view" class="btn btn-sm btn-primary" usepost><i class="fa-regular fa-rectangle-list"></i> <?=gettext("View"); ?></a>
		<?php endif; ?>
		</td>

		<td id="frd<?=$i?>" onclick="fr_toggle(<?=$i?>)">
		<?php if (!empty($thispatch['patch'])): ?>
			<a href="system_patches.php?id=<?=$i?>&amp;type=custom&amp;act=debug" class="btn btn-sm btn-primary" usepost><i class="fa-solid fa-bug"></i> <?=gettext("Debug"); ?></a>
		<?php endif; ?>
		</td>

		<td id="frd<?=$i?>" onclick="fr_toggle(<?=$i?>)">
			<i class="<?= isset($thispatch['autoapply']) ? "fa-solid fa-check" : "fa-solid fa-times" ?>" title="<?= isset($thispatch['autoapply']) ? "Yes" : "No" ?>"></i>
		</td>

		<td style="cursor: pointer;">
			<button style="display: none;" class="btn btn-default btn-xs" type="submit" id="move_<?=$i?>" name="move_<?=$i?>" value="move_<?=$i?>"><?=gettext("Move checked entries to here")?></button>
			<a class="fa-solid fa-pencil" href="system_patches_edit.php?id=<?=$i?>" title="<?=gettext("Edit Patch"); ?>"></a>
			<a class="fa-solid fa-trash-can no-confirm" id="Xdel_<?=$i?>" title="<?=gettext('Delete Patch'); ?>"></a>
			<button style="display: none;" class="btn btn-xs btn-warning" type="submit" id="del_<?=$i?>" name="del_<?=$i?>" value="del_<?=$i?>" title="<?=gettext('Delete Patch'); ?>">delete</button>
		</td>
	</tr>
<?php
	$i++;
endforeach;
?>
				</tbody>
			</table>
		</div>
	</div>
	<nav class="action-buttons">
<?php if ($cus_can_apply > 0): ?>
		<a href="system_patches.php?all=apply&type=custom" class="btn btn-primary btn-sm do-confirm" title="<?=gettext("Apply all custom patches")?>" usepost>
			<i class="fa-solid fa-plus-circle icon-embed-btn"></i>
			<?=gettext("Apply All Custom")?>
		</a>
<?php endif; ?>
<?php if ($cus_can_revert > 0): ?>
		<a href="system_patches.php?all=revert&type=custom" class="btn btn-primary btn-sm do-confirm" title="<?=gettext("Revert all custom patches")?>" usepost>
			<i class="fa-solid fa-minus-circle icon-embed-btn"></i>
			<?=gettext("Revert All Custom")?>
		</a>
<?php endif; ?>
		<a href="system_patches_edit.php" class="btn btn-success btn-sm">
			<i class="fa-solid fa-plus icon-embed-btn"></i>
			<?=gettext("Add New Patch")?>
		</a>
<?php if ($i !== 0): ?>
		<button type="submit" name="del" class="btn btn-danger btn-sm" value="<?=gettext("Delete selected patches")?>">
			<i class="fa-solid fa-trash-can icon-embed-btn"></i>
			<?=gettext("Delete Patches")?>
		</button>
<?php endif; ?>
	</nav>

	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title">
			<?= gettext('Recommended System Patches for') ?>
			<?= $g['product_label_html'] ?>
			<?= gettext('software version') ?>
			<?= $thisversion ?>
		</h2></div>
		<div class="panel-body table-responsive">
			<table class="table table-striped table-hover">
				<thead>
					<tr>
						<th width="60%"><?=gettext("Description")?></th>
						<th width="10%"><?=gettext("Apply")?></th>
						<th width="10%"><?=gettext("Revert")?></th>
						<th width="10%"><?=gettext("View")?></th>
						<th width="10%"><?=gettext("Debug")?></th>
					</tr>
				</thead>
				<tbody class="rpatchentries">
<?php
$num_rpatches=0;
$rec_can_apply=0;
$rec_can_revert=0;
foreach ($recommended_patches as $rpatch):
	if ((!in_array($thisversion, $rpatch['versions'])) ||
	    (array_key_exists('models', $rpatch) &&
	     !in_array($platform['name'], $rpatch['models']))) {
		/* This patch is not relevant to the running version, skip it */
		continue;
	} else {
		/* Patch is relevant, increase the count of relevant patches. */
		$num_rpatches++;
	}
	$can_apply = patch_test_apply($rpatch);
	$can_revert = patch_test_revert($rpatch);
	$linklist = array();
	if (!empty($rpatch['links'])) {
		foreach($rpatch['links'] as $link) {
			$linktext = !empty($link['text']) ? $link['text'] : gettext("More Info");
			if (!empty($link['url'])) {
				$linktext = "<a href=\"{$link['url']}\">{$linktext}</a>";
			}
			$linklist[] = $linktext;
		}
	}
	$linkhtml = implode(', ', $linklist);
?>
	<tr>
		<td>
			<?=$rpatch['descr']?>
		<?php if (!empty($linkhtml)) : ?>
			(<?= $linkhtml ?>)
		<?php endif; ?>
		</td>

		<td>
		<?php if ($can_apply):
			$rec_can_apply += 1; ?>
			<a href="system_patches.php?id=<?=$rpatch['uniqid']?>&amp;type=recommended&amp;act=apply" class="btn btn-sm btn-primary" usepost><i class="fa-solid fa-plus-circle"></i> <?=gettext("Apply"); ?></a>
		<?php endif; ?>
		</td>

		<td>
		<?php if ($can_revert):
			$rec_can_revert += 1; ?>
			<a href="system_patches.php?id=<?=$rpatch['uniqid']?>&amp;type=recommended&amp;act=revert" class="btn btn-sm btn-primary" usepost><i class="fa-solid fa-minus-circle"></i> <?=gettext("Revert"); ?></a>
		<?php endif; ?>
		</td>

		<td>
			<a href="system_patches.php?id=<?=$rpatch['uniqid']?>&amp;type=recommended&amp;act=view" class="btn btn-sm btn-primary" usepost><i class="fa-regular fa-rectangle-list"></i> <?=gettext("View"); ?></a>
		</td>

		<td>
			<a href="system_patches.php?id=<?=$rpatch['uniqid']?>&amp;type=recommended&amp;act=debug" class="btn btn-sm btn-primary" usepost><i class="fa-solid fa-bug"></i> <?=gettext("Debug"); ?></a>
		</td>
	</tr>
<?php
endforeach;
?>
<?php if ($num_rpatches == 0): ?>
	<tr>
		<td colspan="5">
			<?= gettext("No recommended patches for this version.") ?>
		</td>
	</tr>
<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
	<nav class="action-buttons">
<?php if ($rec_can_apply > 0): ?>
		<a href="system_patches.php?all=apply&type=recommended" class="btn btn-primary btn-sm do-confirm" title="<?=gettext("Apply all recommended patches")?>" usepost>
			<i class="fa-solid fa-plus-circle icon-embed-btn"></i>
			<?=gettext("Apply All Recommended")?>
		</a>
<?php endif; ?>
<?php if ($rec_can_revert > 0): ?>
		<a href="system_patches.php?all=revert&type=recommended" class="btn btn-primary btn-sm do-confirm" title="<?=gettext("Revert all recommended patches")?>" usepost>
			<i class="fa-solid fa-minus-circle icon-embed-btn"></i>
			<?=gettext("Revert All Recommended")?>
		</a>
<?php endif; ?>
	</nav>
</form>

<div id="infoblock">
	<?=print_info_box('<strong>' . gettext("Note:") . '</strong><br />' .
	gettext("The package tests each patch and displays the appropriate action. If a patch does not show either 'Apply' or 'Revert', the package cannot use the patch. Check the pathstrip and whitespace options.") .
	"<br/><br/>" .
	gettext("It is normal for a patch to only work one way. At a given state of the system a patch will normally either apply cleanly or revert cleanly, but not both.") .
	"<br/><br/>" .
	gettext("Use the 'Debug' option for details on whether or not the package can apply or revert a given patch.") .
	"<br/><br/>" .
	gettext("Auto-Apply applies patches in the order shown in the custom patches table. Reorder patches as needed so the package can apply patches in the intended order.") .
	"<br/><br/>" .
	gettext("After upgrading, do not revert a patch if the changes from the patch were included in the upgrade. This will remove the changes, which is unlikely to be helpful."), 'info');
	?>
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
