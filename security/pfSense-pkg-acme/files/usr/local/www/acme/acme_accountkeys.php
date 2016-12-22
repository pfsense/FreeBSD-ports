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

if (!is_array($config['installedpackages']['acme']['accountkeys']['item'])) {
	$config['installedpackages']['acme']['accountkeys']['item'] = array();
}
$a_accountkeys = &$config['installedpackages']['acme']['accountkeys']['item'];

function array_moveitemsbefore(&$items, $before, $selected) {
	// generic function to move array items before the set item by their numeric indexes.
	
	$a_new = array();
	/* copy all entries < $before and not selected */
	for ($i = 0; $i < $before; $i++) {
		if (!in_array($i, $selected)) {
			$a_new[] = $items[$i];
		}
	}
	/* copy all selected entries */
	for ($i = 0; $i < count($items); $i++) {
		if ($i == $before) {
			continue;
		}
		if (in_array($i, $selected)) {
			$a_new[] = $items[$i];
		}
	}
	/* copy $before entry */
	if ($before < count($items)) {
		$a_new[] = $items[$before];
	}
	/* copy all entries > $before and not selected */
	for ($i = $before+1; $i < count($items); $i++) {
		if (!in_array($i, $selected)) {
			$a_new[] = $items[$i];
		}
	}
	if (count($a_new) > 0) {
		$items = $a_new;
	}
}

if($_POST['action'] == "toggle") {
	$id = $_POST['id'];
	echo "$id|";
	if (isset($a_certifcates[get_certificate_id($id)])) {
		$item = &$a_certifcates[get_certificate_id($id)];
		if ($item['status'] != "disabled"){
			$item['status'] = 'disabled';
			echo "0|";
		}else{
			$item['status'] = 'active';
			echo "1|";
		}
		$changedesc .= " set item '$id' status to: {$item['status']}";
		
		touch($d_acmeconfdirty_path);
		write_config($changedesc);
	}
	echo "ok|";
	exit;
}

if ($_POST) {
	$pconfig = $_POST;

	if ($_POST['apply']) {
		$result = haproxy_check_and_run($savemsg, true);
		if ($result) {
			unlink_if_exists($d_acmeconfdirty_path);
		}
	} elseif ($_POST['del_x']) {
		/* delete selected rules */
		$deleted = false;
		if (is_array($_POST['rule']) && count($_POST['rule'])) {
			$selected = array();
			foreach($_POST['rule'] as $selection) {
				$selected[] = get_accountkey_id($selection);
			}
			foreach ($selected as $itemnr) {
				unset($a_accountkeys[$itemnr]);
				$deleted = true;
			}
			if ($deleted) {
				if (write_config("Acme, deleting accountkey(s)")) {
					//mark_subsystem_dirty('filter');
					touch($d_acmeconfdirty_path);
				}
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
			array_moveitemsbefore($a_accountkeys, $moveto, $selected);
		
			touch($d_acmeconfdirty_path);
			write_config($changedesc);			
		}
	}
} else {
	$result = null;//haproxy_check_config($retval);
	if ($result) {
		$savemsg = gettext($result);
	}
}

if ($_GET['act'] == "del") {
	$id = $_GET['id'];
	$id = get_accountkey_id($id);
	if (isset($a_accountkeys[$id])) {
		if (!$input_errors) {
			unset($a_accountkeys[$id]);
			$changedesc .= " Accountkey delete";
			write_config($changedesc);
			touch($d_acmeconfdirty_path);
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

/*$display_apply = file_exists($d_acmeconfdirty_path) ? "" : "none";
echo "<div id='showapplysettings' style='display: {$display_apply};'>";
print_apply_box(sprintf(gettext("The configuration has been changed.%sYou must apply the changes in order for them to take effect."), "<br/>"));
echo "</div>";
*/
?>
<div id="renewoutputbox" class="alert alert-success clearfix hidden" role="alert">
	<button type="button" class="close" data-dismiss="alert" aria-label="Close">
		<span aria-hidden="true">×</span>
	</button>
	<div id="renewoutput" class="pull-left">
	</div>
</div>

<?php
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
		foreach ($a_accountkeys as $accountkey) {
			$accountname = $accountkey['name'];
			?>
			<tr id="fr<?=$accountname;?>" <?=$display?> onClick="fr_toggle('<?=$accountname;?>')" ondblclick="document.location='acme_accountkeys_edit.php?id=<?=$accountname;?>';">
				<td>
					<input type="checkbox" id="frc<?=$accountname;?>" onClick="fr_toggle('<?=$accountname;?>')" name="rule[]" value="<?=$accountname;?>"/>
					<a class="fa fa-anchor" id="Xmove_<?=$accountname?>" title="<?=gettext("Move checked entries to here")?>"></a>
				</td>
			  <td>
				<?=$accountkey['name'];?>
			  </td>
			  <td>
				<?=$accountkey['desc'];?>
			  </td>
			  <td>
				<?=$accountkey['acmeserver'];?>
			  </td>
			  <td class="action-icons">
				<button style="display: none;" class="btn btn-default btn-xs" type="submit" id="move_<?=$accountname?>" name="move_<?=$accountname?>" value="move_<?=$accountname?>"></button>
				<a href="acme_accountkeys_edit.php?id=<?=$accountname;?>">
					<?=acmeicon("edit", gettext("edit"))?>
				</a>
				<a href="acme_accountkeys.php?act=del&amp;id=<?=$accountname;?>" onclick="return confirm('Do you really want to delete this entry?')">
					<?=acmeicon("delete", gettext("delete"))?>
				</a>
				<a href="acme_accountkeys_edit.php?dup=<?=$accountname;?>">
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
			<i class="fa fa-level-down icon-embed-btn"></i>
			<?=gettext("Add");?>
		</a>
		<button name="del_x" type="submit" class="btn btn-danger btn-sm" value="<?=gettext("Delete selected backends"); ?>" title="<?=gettext('Delete selected backends')?>">
			<i class="fa fa-trash icon-embed-btn no-confirm"></i>
			<?=gettext("Delete"); ?>
		</button>
		<button type="submit" id="order-store" name="order-store" class="btn btn-sm btn-primary" value="store changes" disabled title="<?=gettext('Save backend order')?>">
			<i class="fa fa-save icon-embed-btn no-confirm"></i>
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

function js_callbackrenew(data) {
	$('#renewoutputbox').removeClass("hidden");
	$('#renewoutput').html(data);
}

function js_callback(req_content) {
	
	showapplysettings.style.display = 'block';
	if(req_content !== '') {
		var itemsplit = req_content.split("|");
		buttonid = itemsplit[0];
		enabled = parseInt(itemsplit[1]);
		if (enabled === 1){
			img = "<?=acmeicon("enabled", gettext("click to toggle enable/disable this certificate renewal"))?>";
		} else {
			img = "<?=acmeicon("disabled", gettext("click to toggle enable/disable this certificate renewal"))?>";
		}
		set_content('btn_'+buttonid, img);
	}
}

function togglerow($id) {
	ajaxRequest = $.ajax({
		url: "",
		type: "post",
		data: { id: $id, action: "toggle"},
		success: function(data) {
			js_callback(data);
		}
	});
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