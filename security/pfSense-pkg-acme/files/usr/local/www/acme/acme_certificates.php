<?php
/*
 * acme_certificates.php
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

$changedesc = "Services: Acme: Certificates";

if (!is_array($config['installedpackages']['acme']['certificates']['item'])) {
	$config['installedpackages']['acme']['certificates']['item'] = array();
}
$a_certifcates = &$config['installedpackages']['acme']['certificates']['item'];

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
if($_POST['action'] == "renew") {
	$id = $_POST['id'];
	echo $id . "\n";
	if (isset($a_certifcates[get_certificate_id($id)])) {
		renew_certificate($id, true);
	}
	exit;
}

if ($_POST) {
	$pconfig = $_POST;

	if ($_POST['del_x']) {
		/* delete selected rules */
		$deleted = false;
		if (is_array($_POST['rule']) && count($_POST['rule'])) {
			$selected = array();
			foreach($_POST['rule'] as $selection) {
				$selected[] = get_certificate_id($selection);
			}
			foreach ($selected as $itemnr) {
				unset($a_certifcates[$itemnr]);
				$deleted = true;
			}
			if ($deleted) {
				if (write_config("Acme, deleting certificate(s)")) {
					//mark_subsystem_dirty('filter');
					touch($d_acmeconfdirty_path);
				}
			}
			header("Location: acme_certificates.php");
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
			$moveto = get_certificate_id($movebtn);
			$selected = array();
			foreach($_POST['rule'] as $selection) {
				$selected[] = get_certificate_id($selection);
			}
			array_moveitemsbefore($a_certifcates, $moveto, $selected);
		
			touch($d_acmeconfdirty_path);
			write_config($changedesc);			
		}
	}
}

if ($_GET['act'] == "del") {
	$id = $_GET['id'];
	$id = get_certificate_id($id);
	if (isset($a_certifcates[$id])) {
		if (!$input_errors) {
			unset($a_certifcates[$id]);
			$changedesc .= " Item delete";
			write_config($changedesc);
			touch($d_acmeconfdirty_path);
		}
		header("Location: acme_certificates.php");
		exit;
	}
}

$pgtitle = array("Services", "Acme", "Certificates");
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
display_top_tabs_active($acme_tab_array['acme'], "certificates");
?>
<form action="acme_certificates.php" method="post">
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title">Certificates</h2>
		</div>
		<div id="mainarea" class="table-responsive panel-body">
			<table class="table table-hover table-striped table-condensed">
				<thead>
					<tr>
						<th></th>
						<th>On</th>
						<th width="30%">Name</th>
						<th width="20%">Description</th>
						<th>Account</th>
						<th>Last renewed</th>
						<th>Renew</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody class="user-entries">
<?php
		foreach ($a_certifcates as $certificate) {
			$certificatename = $certificate['name'];
			$disabled = $certificate['status'] != 'active';
			?>
			<tr id="fr<?=$certificatename;?>" <?=$display?> onClick="fr_toggle('<?=$certificatename;?>')" ondblclick="document.location='acme_certificates_edit.php?id=<?=$certificatename;?>';" <?=($disabled ? ' class="disabled"' : '')?>>
				<td>
					<input type="checkbox" id="frc<?=$certificatename;?>" onClick="fr_toggle('<?=$certificatename;?>')" name="rule[]" value="<?=$certificatename;?>"/>
					<a class="fa fa-anchor" id="Xmove_<?=$certificatename?>" title="<?=gettext("Move checked entries to here")?>"></a>
				</td>
			  <td>
				<?php
					if ($certificate['status']=='disabled'){
						$iconfn = "disabled";
					} else {
						$iconfn = "enabled";
					}?>
				<a id="btn_<?=$certificatename;?>" href='javascript:togglerow("<?=$certificatename;?>");'>
					<?=acmeicon($iconfn, gettext("click to toggle enable/disable this certificate renewal"))?>
				</a>
			  </td>
			  <td>
				<?=$certificate['name'];?>
			  </td>
			  <td>
				<?=$certificate['desc'];?>
			  </td>
			  <td>
				<?=$certificate['acmeaccount'];?>
			  </td>
			  <td>
				<?=date('d-m-Y H:i:s', $certificate['lastrenewal']);?>
			  </td>
			  <td>
				  <a href='javascript:renewcertificate("<?=$certificatename;?>");' class="btn btn-sm btn-primary">
					  <i id="btnrenewicon_<?=$certificatename;?>" class="fa fa-check"></i> Renew
				  </a>
			  </td>
			  <td class="action-icons">
				<button style="display: none;" class="btn btn-default btn-xs" type="submit" id="move_<?=$certificatename?>" name="move_<?=$certificatename?>" value="move_<?=$certificatename?>"></button>
				<a href="acme_certificates_edit.php?id=<?=$certificatename;?>">
					<?=acmeicon("edit", gettext("edit"))?>
				</a>
				<a href="acme_certificates.php?act=del&amp;id=<?=$certificatename;?>" onclick="return confirm('Do you really want to delete this entry?')">
					<?=acmeicon("delete", gettext("delete"))?>
				</a>
				<a href="acme_certificates_edit.php?dup=<?=$certificatename;?>">
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
		<a href="acme_certificates_edit.php" role="button" class="btn btn-sm btn-success" title="<?=gettext('Add backend to the end of the list')?>">
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
	$('#renewoutput').html(data.replace(/(?:\r\n|\r|\n)/g, '<br />'));
}

function js_callback(req_content) {
	
	//showapplysettings.style.display = 'block';
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

function renewcertificate($id) {
	$('#'+"btnrenewicon_"+$id).removeClass("fa-check").addClass("fa-cog fa-spin");
	
	ajaxRequest = $.ajax({
		url: "",
		type: "post",
		data: { id: $id, action: "renew"},
		success: function(data) {
			js_callbackrenew(data);
			$("#btnrenewicon_"+$id).removeClass("fa-cog fa-spin").addClass("fa-check");
		}
	});
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