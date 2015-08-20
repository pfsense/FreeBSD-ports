<?php
/*
	system_patches.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2012 Jim Pingle
	Copyright (C) 2015 ESF, LLC
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
/*
	pfSense_MODULE:	system
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
		write_config();
	}
}

if ($_GET['act'] == "del") {
	if ($a_patches[$_GET['id']]) {
		unset($a_patches[$_GET['id']]);
		write_config();
		header("Location: system_patches.php");
		exit;
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


if (isset($_POST['del_x'])) {
	/* delete selected patches */
	if (is_array($_POST['patch']) && count($_POST['patch'])) {
		foreach ($_POST['patch'] as $patchi) {
			unset($a_patches[$patchi]);
		}
		write_config();
		header("Location: system_patches.php");
		exit;
	}
} else {
	/* yuck - IE won't send value attributes for image buttons, while Mozilla does - so we use .x/.y to find move button clicks instead... */
	unset($movebtn);
	foreach ($_POST as $pn => $pd) {
		if (preg_match("/move_(\d+)_x/", $pn, $matches)) {
			$movebtn = $matches[1];
			break;
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
		write_config();
		header("Location: system_patches.php");
		return;
	}
}

$closehead = false;
$pgtitle = array(gettext("System"), gettext("Patches"));
include("head.inc");

?>
<script type="text/javascript" src="/javascript/domTT/domLib.js"></script>
<script type="text/javascript" src="/javascript/domTT/domTT.js"></script>
<script type="text/javascript" src="/javascript/domTT/behaviour.js"></script>
<script type="text/javascript" src="/javascript/domTT/fadomatic.js"></script>

<link type="text/css" rel="stylesheet" href="/javascript/chosen/chosen.css" />
</head>
<body link="#000000" vlink="#000000" alink="#000000">
<?php include("fbegin.inc"); ?>
<form action="system_patches.php" method="post" name="iform">
<script type="text/javascript" src="/javascript/row_toggle.js"></script>
<?php if ($savemsg) print_info_box_np($savemsg, "Patches", "Close", false); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0" summary="system patches">
<tr><td><div id="mainarea">
<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0" summary="main area">
	<tr>
		<td colspan="8" align="center">
			<?php echo gettext("This page allows you to add patches, either from the official code repository or ones pasted in from e-mail or other sources."); ?>
			<br/><br/>
			<strong><?php echo gettext("Use with caution!"); ?></strong>
			<br/><br/>
<?php if (!empty($fulldetail)): ?>
		</td>
	</tr>
	<tr>
		<td></td>
		<td colspan="7" align="left">Output of full patch <?php echo $_GET['fulltest']; ?> test:
			<pre><?php echo $fulldetail; ?></pre>
			<a href="system_patches.php">Close</a><br/><br/>
<?php endif; ?>
		</td>
	</tr>
	<tr id="frheader">
		<td width="5%" class="list">&nbsp;</td>
		<td width="5%" class="listhdrr"><?=gettext("Description");?></td>
		<td width="60%" class="listhdrr"><?=gettext("URL/ID");?></td>
		<td width="5%" class="listhdrr"><?=gettext("Fetch");?></td>
		<td width="5%" class="listhdrr"><?=gettext("Test");?></td>
		<td width="5%" class="listhdrr"><?=gettext("Apply");?></td>
		<td width="5%" class="listhdr"><?=gettext("Revert");?></td>
		<td width="5%" class="listhdr"><?=gettext("Auto Apply");?></td>
		<td width="5%" class="list">
			<table border="0" cellspacing="0" cellpadding="1" summary="buttons">
				<tr>
					<td width="17">
					<?php if (count($a_patches) == 0): ?>
						<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif" width="17" height="17" title="<?=gettext("delete selected patches");?>" border="0" alt="delete" />
					<?php else: ?>
						<input name="del" type="image" src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" title="<?=gettext("delete selected patches"); ?>" onclick="return confirm('<?=gettext("Do you really want to delete the selected patches?");?>')" />
					<?php endif; ?>
					</td>
					<td><a href="system_patches_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?=gettext("add new patch"); ?>" alt="add" /></a></td>
				</tr>
			</table>
		</td>
	</tr>

<?php
$npatches = $i = 0;
foreach ($a_patches as $thispatch):
	$can_apply = patch_test_apply($thispatch);
	$can_revert = patch_test_revert($thispatch);

?>
	<tr valign="top" id="fr<?=$npatches;?>">
		<td class="listt"><input type="checkbox" id="frc<?=$npatches;?>" name="patch[]" value="<?=$i;?>" onclick="fr_bgcolor('<?=$npatches;?>')" style="margin: 0; padding: 0; width: 15px; height: 15px;" /></td>
		<td class="listlr" onclick="fr_toggle(<?=$npatches;?>)" id="frd<?=$npatches;?>" ondblclick="document.location='system_patches_edit.php?id=<?=$npatches;?>';">
			<?=$thispatch['descr'];?>
		</td>
		<td class="listr" onclick="fr_toggle(<?=$npatches;?>)" id="frd<?=$npatches;?>" ondblclick="document.location='system_patches_edit.php?id=<?=$npatches;?>';">

			<?php
			if (!empty($thispatch['location'])) {
				echo $thispatch['location'];
			} elseif (!empty($thispatch['patch'])) {
				echo gettext("Saved Patch");
			}
			?>
		</td>
		<td class="listr" onclick="fr_toggle(<?=$npatches;?>)" id="frd<?=$npatches;?>" ondblclick="document.location='system_patches_edit.php?id=<?=$npatches;?>';">
		<?php if (empty($thispatch['patch'])): ?>
			<a href="system_patches.php?id=<?=$i;?>&amp;act=fetch"><?php echo gettext("Fetch"); ?></a>
		<?php elseif (!empty($thispatch['location'])): ?>
			<a href="system_patches.php?id=<?=$i;?>&amp;act=fetch"><?php echo gettext("Re-Fetch"); ?></a>
		<?php endif; ?>
		</td>
		<td class="listr" onclick="fr_toggle(<?=$npatches;?>)" id="frd<?=$npatches;?>" ondblclick="document.location='system_patches_edit.php?id=<?=$npatches;?>';">
		<?php if (!empty($thispatch['patch'])): ?>
			<a href="system_patches.php?id=<?=$i;?>&amp;act=test"><?php echo gettext("Test"); ?></a>
		<?php endif; ?>
		</td>
		<td class="listr" onclick="fr_toggle(<?=$npatches;?>)" id="frd<?=$npatches;?>" ondblclick="document.location='system_patches_edit.php?id=<?=$npatches;?>';">
		<?php if ($can_apply): ?>
			<a href="system_patches.php?id=<?=$i;?>&amp;act=apply"><?php echo gettext("Apply"); ?></a>
		<?php endif; ?>
		</td>
		<td class="listr" onclick="fr_toggle(<?=$npatches;?>)" id="frd<?=$npatches;?>" ondblclick="document.location='system_patches_edit.php?id=<?=$npatches;?>';">
		<?php if ($can_revert): ?>
			<a href="system_patches.php?id=<?=$i;?>&amp;act=revert"><?php echo gettext("Revert"); ?></a>
		<?php endif; ?>
		</td>
		<td class="listr" onclick="fr_toggle(<?=$npatches;?>)" id="frd<?=$npatches;?>" ondblclick="document.location='system_patches_edit.php?id=<?=$npatches;?>';">
			<?= isset($thispatch['autoapply']) ? "Yes" : "No" ?>
		</td>
		<td valign="middle" class="list" nowrap="nowrap">
			<table border="0" cellspacing="0" cellpadding="1" summary="edit">
				<tr>
					<td><input onmouseover="fr_insline(<?=$npatches;?>, true)" onmouseout="fr_insline(<?=$npatches;?>, false)" name="move_<?=$i;?>" src="/themes/<?= $g['theme']; ?>/images/icons/icon_left.gif" title="<?=gettext("move selected patches before this patch");?>" type="image" /></td>
					<td><a href="system_patches_edit.php?id=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" width="17" height="17" border="0" title="<?=gettext("edit patch"); ?>" alt="edit" /></a></td>
				</tr>
				<tr>
					<td align="center" valign="middle"><a href="system_patches.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('<?=gettext("Do you really want to delete this patch?");?>')"><img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" border="0" title="<?=gettext("delete patch");?>" alt="delete" /></a></td>
					<td></td>
				</tr>
			</table>
		</td>
	</tr>
<?php
	$i++;
	$npatches++;
endforeach;
?>
	<tr>
		<td class="list" colspan="8"></td>
		<td class="list" valign="middle" nowrap="nowrap">
			<table border="0" cellspacing="0" cellpadding="1" summary="edit">
				<tr>
					<td><?php if ($npatches == 0): ?><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_left_d.gif" width="17" height="17" title="<?=gettext("move selected patches to end"); ?>" border="0" alt="move" /><?php else: ?><input name="move_<?=$i;?>" type="image" src="/themes/<?= $g['theme']; ?>/images/icons/icon_left.gif" title="<?=gettext("move selected patches to end");?>" alt="move" /><?php endif; ?></td>
				</tr>
				<tr>
					<td width="17">
					<?php if (count($a_patches) == 0): ?>
						<img src="./themes/<?= $g['theme']; ?>/images/icons/icon_x_d.gif" width="17" height="17" title="<?=gettext("delete selected patches");?>" border="0" alt="delete" />
					<?php else: ?>
						<input name="del" type="image" src="./themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" title="<?=gettext("delete selected patches"); ?>" onclick="return confirm('<?=gettext("Do you really want to delete the selected patches?");?>')" />
					<?php endif; ?>
					</td>
					<td><a href="system_patches_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" border="0" title="<?=gettext("add new patch"); ?>" alt="add" /></a></td>
				</tr>
			</table>
		</td>
	</tr>
	<tr>
		<td></td>
		<td colspan="6">
			<?php echo gettext("NOTE: Each patch is tested, and the appropriate action is shown. If neither 'Apply' or 'Revert' shows up, the patch cannot be used (check the pathstrip and whitespace options)."); ?>
			<br/><br/>
			<?php echo gettext("Use the 'Test' link to see if a patch can be applied or reverted. You can reorder patches so that higher patches apply later than lower patches."); ?>
		</td>
		<td></td>
	</tr>
</table>
</div></td></tr>
</table>
</form>
<?php include("fend.inc"); ?>
</body>
</html>
