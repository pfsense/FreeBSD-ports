<?php
/*
 * acme_accountkeys.php
 * 
 * part of pfSense (https://www.pfsense.org/)
 * Copyright (c) 2016 PiBa-NL
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

namespace pfsense_pkg\acme;

$shortcut_section = "acme";
require_once("guiconfig.inc");
require_once("certs.inc");
require_once("acme/acme.inc");
require_once("acme/acme_gui.inc");
require_once("acme/acme_utils.inc");
require_once("acme/pkg_acme_tabs.inc");

$changedesc = "Services: Acme: Accountkeys";

if ($_POST) {
	$pconfig = $_POST;

	if ($_POST['del_x']) {
		/* delete selected rules */
		$deleted = false;
		if (is_array($_POST['rule']) && count($_POST['rule'])) {
			$selected = array();
			foreach($_POST['rule'] as $selection) {
				$selected[] = get_accountkey_id($selection);
			}
			foreach ($selected as $itemnr) {
				config_del_path("installedpackages/acme/accountkeys/item/{$itemnr}");
				$deleted = true;
			}
			if ($deleted) {
				write_config("Acme, deleting accountkey(s)");
			}
			header("Location: acme_accountkeys.php");
			exit;
		}
	} else {	

		// from '\src\usr\local\www\vpn_ipsec.php'
		/* yuck - IE won't send value attributes for image buttons, while Mozilla does - so we use .x/.y to find move button clicks instead... */
		// TODO: this. is. nasty.
		unset($delbtn, $delbtnp2, $movebtn, $movebtnp2, $togglebtn, $togglebtnp2);
		foreach ($_POST as $pn => $pd) {
			if (preg_match("/move_(.+)/", $pn, $matches)) {
				$movebtn = $matches[1];
			}
		}
		//
		
		/* move selected p1 entries before this */
		if (isset($movebtn) && is_array($_POST['rule']) && count($_POST['rule'])) {
			$moveto = get_accountkey_id($movebtn);
			$selected = array();
			foreach($_POST['rule'] as $selection) {
				$selected[] = get_accountkey_id($selection);
			}
			$a_accountkeys = config_get_path('installedpackages/acme/accountkeys/item', []);
			array_moveitemsbefore($a_accountkeys, $moveto, $selected);
			config_set_path('installedpackages/acme/accountkeys/item', $a_accountkeys);
		
			write_config($changedesc);			
		}
	}
} else {
	$result = null;//haproxy_check_config($retval);
	if ($result) {
		$savemsg = gettext($result);
	}
}

if ($_POST['act'] == "del") {
	$id = $_POST['id'];
	$id = get_accountkey_id($id);
	if (config_get_path("installedpackages/acme/accountkeys/item/{$id}") !== null) {
		if (!$input_errors) {
			config_del_path("installedpackages/acme/accountkeys/item/{$id}");
			$changedesc .= " Accountkey delete";
			write_config($changedesc);
		}
		header("Location: acme_accountkeys.php");
		exit;
	}
}

$pgtitle = array("Services", "Acme", "Accountkeys");
include("head.inc");
if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg);
}

display_top_tabs_active($acme_tab_array['acme'], "accountkeys");
?>
<form action="acme_accountkeys.php" method="post">
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title">Account keys</h2>
		</div>
		<div id="mainarea" class="table-responsive panel-body">
			<table class="table table-hover table-striped table-condensed">
				<thead>
					<tr>
						<th></th>
						<th width="30%">Name</th>
						<th width="20%">Description</th>
						<th>CA</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody class="user-entries">
<?php
		foreach (config_get_path('installedpackages/acme/accountkeys/item', []) as $accountkey) {
			if (empty($accountkey) || !is_array($accountkey)) {
				continue;
			}
			$accountname = htmlspecialchars($accountkey['name']);
			?>
			<tr id="fr<?=$accountname;?>" <?=$display?> onClick="fr_toggle('<?=$accountname;?>')" ondblclick="document.location='acme_accountkeys_edit.php?id=<?=$accountname;?>';">
				<td>
					<input type="checkbox" id="frc<?=$accountname;?>" onClick="fr_toggle('<?=$accountname;?>')" name="rule[]" value="<?=$accountname;?>"/>
					<a class="fa-solid fa-anchor" id="Xmove_<?=$accountname?>" title="<?=gettext("Move checked entries to here")?>"></a>
				</td>
			  <td>
				<?=$accountname;?>
			  </td>
			  <td>
				<?=htmlspecialchars($accountkey['descr']);?>
			  </td>
			  <td>
				<?=htmlspecialchars($accountkey['acmeserver']);?>
			  </td>
			  <td class="action-icons">
				<button style="display: none;" class="btn btn-default btn-xs" type="submit" id="move_<?=urlencode($accountname)?>" name="move_<?=urlencode($accountname)?>" value="move_<?=urlencode($accountname)?>"></button>
				<a href="acme_accountkeys_edit.php?id=<?=urlencode($accountname);?>">
					<?=acmeicon("edit", gettext("edit"))?>
				</a>
				<a href="acme_accountkeys.php?act=del&amp;id=<?=$accountname;?>" usepost>
					<?=acmeicon("delete", gettext("delete"))?>
				</a>
				<a href="acme_accountkeys_edit.php?dup=<?=urlencode($accountname);?>">
					<?=acmeicon("clone", gettext("clone"))?>
				</a>
			  </td>
			</tr><?php
		}
?>				
				</tbody>
			</table>
		</div>
	</div>
	<nav class="action-buttons">
		<a href="acme_accountkeys_edit.php" role="button" class="btn btn-sm btn-success" title="<?=gettext('Add backend to the end of the list')?>">
			<i class="fa-solid fa-plus icon-embed-btn"></i>
			<?=gettext("Add");?>
		</a>
		<button name="del_x" type="submit" class="btn btn-danger btn-sm" value="<?=gettext("Delete selected backends"); ?>" title="<?=gettext('Delete selected backends')?>">
			<i class="fa-solid fa-trash-can icon-embed-btn no-confirm"></i>
			<?=gettext("Delete"); ?>
		</button>
		<button type="submit" id="order-store" name="order-store" class="btn btn-sm btn-primary" value="store changes" disabled title="<?=gettext('Save backend order')?>">
			<i class="fa-solid fa-save icon-embed-btn no-confirm"></i>
			<?=gettext("Save")?>
		</button>
	</nav>
</form>

<script type="text/javascript">
//<![CDATA[

function set_content(elementid, image) {
	var item = document.getElementById(elementid);
	item.innerHTML = image;
}

events.push(function() {
	
	$('#clearallnotices').click(function() {
		ajaxRequest = $.ajax({
			url: "/index.php",
			type: "post",
			data: { closenotice: "all"},
			success: function() {
				window.location = window.location.href;
			},
			failure: function() {
				alert("Error clearing notices!");
			}
		});
	});
	
	$('[id^=Xmove_]').click(function (event) {
		$('#' + event.target.id.slice(1)).click();
		return false;
	});
	$('[id^=Xmove_]').css('cursor', 'pointer');

	// Check all of the rule checkboxes so that their values are posted
	$('#order-store').click(function () {
	   $('[id^=frc]').prop('checked', true);
	});
});
//]]>
</script>
<?php include("foot.inc");
