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
require_once("pfsense-utils.inc");
require_once("certs.inc");
require_once("acme/acme.inc");
require_once("acme/acme_gui.inc");
require_once("acme/acme_utils.inc");
require_once("acme/pkg_acme_tabs.inc");

$changedesc = "Services: Acme: Certificates";

if($_POST['action'] == "toggle") {
	$id = $_POST['id'];
	echo "$id|";
	if (config_get_path('installedpackages/acme/certificates/item/' . get_certificate_id($id)) !== null) {
		if (config_get_path('installedpackages/acme/certificates/item/' . get_certificate_id($id) . '/status') != "disabled"){
			config_set_path('installedpackages/acme/certificates/item/' . get_certificate_id($id) . '/status', 'disabled');
			echo "0|";
		}else{
			config_set_path('installedpackages/acme/certificates/item/' . get_certificate_id($id) . '/status', 'active');
			echo "1|";
		}
		$changedesc .= " set item '$id' status to: " . config_get_path('installedpackages/acme/certificates/item/' . get_certificate_id($id) . '/status');
		
		write_config($changedesc);
	}
	echo "ok|";
	exit;
}
if($_POST['action'] == "issuecert") {
	$id = $_POST['id'];
	echo $id . "\n";
	if (config_get_path('installedpackages/acme/certificates/item/' . get_certificate_id($id)) !== null) {
		issue_certificate($id, true);
	}
	exit;
}
if($_POST['action'] == "renewcert") {
	$id = $_POST['id'];
	echo $id . "\n";
	if (config_get_path('installedpackages/acme/certificates/item/' . get_certificate_id($id)) !== null) {
		issue_certificate($id, true, true);
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
				config_del_path("installedpackages/acme/certificates/item/{$itemnr}");
				$deleted = true;
			}
			if ($deleted) {
				write_config("Acme, deleting certificate(s)");
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
				$movebtn = substr($pd, 5);
			}
		}
		
		/* move selected p1 entries before this */
		if (isset($movebtn) && is_array($_POST['rule']) && count($_POST['rule'])) {
			$moveto = get_certificate_id($movebtn);
			$selected = array();
			foreach($_POST['rule'] as $selection) {
				$selected[] = get_certificate_id($selection);
			}
			$a_certificates = config_get_path('installedpackages/acme/certificates/item', []);
			array_moveitemsbefore($a_certificates, $moveto, $selected);
			config_set_path('installedpackages/acme/certificates/item', $a_certificates);
		
			write_config($changedesc);			
		}
	}
}

if ($_GET['act'] == "del") {
	$id = $_GET['id'];
	$id = get_certificate_id($id);
	if (config_get_path("installedpackages/acme/certificates/item/{$id}") !== null) {
		if (!$input_errors) {
			config_del_path("installedpackages/acme/certificates/item/{$id}");
			$changedesc .= " Item delete";
			write_config($changedesc);
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

?>
<div id="renewoutputbox" class="alert alert-success clearfix hidden" role="alert">
	<button type="button" class="close" data-dismiss="alert" aria-label="Close">
		<span aria-hidden="true">×</span>
	</button>
	<div id="renewoutput" class="pull-left" style="white-space: pre-wrap">
	</div>
</div>
	
<?php
display_top_tabs_active($acme_tab_array['acme'], "certificates");
?>
<div class="panel panel-default" id="search-panel">
	<div class="panel-heading">
		<h2 class="panel-title">
			<?=gettext('Search')?>
			<span class="widget-heading-icon pull-right">
				<a data-toggle="collapse" href="#search-panel_panel-body">
					<i class="fa-solid fa-plus-circle"></i>
				</a>
			</span>
		</h2>
	</div>
	<div id="search-panel_panel-body" class="panel-body collapse in">
		<div class="form-group">
			<label class="col-sm-2 control-label">
				<?=gettext("Search term")?>
			</label>
			<div class="col-sm-5"><input class="form-control" name="searchstr" id="searchstr" type="text"/></div>
			<div class="col-sm-2">
				<select id="where" class="form-control">
					<option value="0"><?=gettext("Name")?></option>
					<option value="1"><?=gettext("Description")?></option>
					<option value="2" selected><?=gettext("Both")?></option>
				</select>
			</div>
			<div class="col-sm-3">
				<a id="btnsearch" title="<?=gettext("Search")?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-search icon-embed-btn"></i><?=gettext("Search")?></a>
				<a id="btnclear" title="<?=gettext("Clear")?>" class="btn btn-info btn-sm"><i class="fa-solid fa-undo icon-embed-btn"></i><?=gettext("Clear")?></a>
			</div>
			<div class="col-sm-10 col-sm-offset-2">
				<span class="help-block"><?=gettext('Enter a search string or *nix regular expression to search certificate names and distinguished names.')?></span>
			</div>
		</div>
	</div>
</div>
<form action="acme_certificates.php" method="post">
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title">Certificates</h2>
		</div>
		<div id="mainarea" class="table-responsive panel-body">
			<table class="table table-hover table-striped table-condensed sortable-theme-bootstrap" data-sortable>
				<thead>
					<tr>
						<th data-sortable="false"></th>
						<th>On</th>
						<th>Name</th>
						<th>Description</th>
						<th>Account</th>
						<th data-sortable-type="date">Last renewed</th>
						<th data-sortable="false">Renew</th>
						<th data-sortable="false">Actions</th>
					</tr>
				</thead>
				<tbody class="user-entries">
<?php
		foreach (config_get_path('installedpackages/acme/certificates/item', []) as $certificate) {
			$certificatename = $certificate['name'];
			$disabled = $certificate['status'] != 'active';
			$issuedcert = lookup_cert_by_name($certificate['name']);
			$issuedcert = $issuedcert['item'];
			?>
			<tr id="fr<?=$certificatename;?>" <?=$display?> onClick="fr_toggle('<?=$certificatename;?>')" ondblclick="document.location='acme_certificates_edit.php?id=<?=$certificatename;?>';" <?=($disabled ? ' class="disabled"' : '')?>>
				<td>
					<input type="checkbox" id="frc<?=$certificatename;?>" onClick="fr_toggle('<?=$certificatename;?>')" name="rule[]" value="<?=$certificatename;?>"/>
					<a class="fa-solid fa-anchor" id="Xmove_<?=$certificatename?>" title="<?=gettext("Move checked entries to here")?>"></a>
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
				<?=htmlspecialchars($certificate['descr']);?>
			  </td>
			  <td>
				<?=htmlspecialchars($certificate['acmeaccount']);?>
			  </td>
			  <td style="white-space: nowrap">
				<?=cert_format_date('', $certificate['lastrenewal'], true);?>
				<?php if ($issuedcert): ?>
				<br/><?=gettext("Issued Certificate Dates:")?>
				<?=cert_print_dates($issuedcert);?>
				<?php endif; ?>
			  </td>
			  <td>
				  <?php
					$method = "";
					if (is_array($certificate) &&
					    is_array($certificate['a_domainlist']) &&
					    is_array($certificate['a_domainlist']['item'])) {
						foreach($certificate['a_domainlist']['item'] as $domain) {
							if ($domain['status'] == 'disable') {
								continue;
							}
							$method = $domain['method'];
						}
					}
			
				  if ($method == "dns_manual"): ?>
				  <a href='javascript:renewcertificate("<?=$certificatename;?>");' class="btn btn-sm btn-primary">
					  <i id="btnrenewicon_<?=$certificatename;?>" class="fa-solid fa-check"></i> Renew
				  </a>
				  <a href='javascript:issuecertificate("<?=$certificatename;?>");' class="btn btn-sm btn-primary">
					  <i id="btnissueicon_<?=$certificatename;?>" class="fa-solid fa-check"></i> Issue
				  </a>
				  <?php else: ?>
				  <a href='javascript:issuecertificate("<?=$certificatename;?>");' class="btn btn-sm btn-primary">
					  <i id="btnissueicon_<?=$certificatename;?>" class="fa-solid fa-check"></i> Issue/Renew
				  </a>
				  <?php endif; ?>
			  </td>
			  <td class="action-icons">
				<button style="display: none;" class="btn btn-default btn-xs" type="submit" id="move_<?=$certificatename?>" name="move_<?=$certificatename?>" value="move_<?=$certificatename?>"></button>
				<a href="acme_certificates_edit.php?id=<?=$certificatename;?>">
					<?=acmeicon("edit", gettext("edit"))?>
				</a>
				<a href="acme_certificates.php?act=del&amp;id=<?=$certificatename;?>">
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
		<a href="acme_certificates_edit.php" role="button" class="btn btn-sm btn-success" title="<?=gettext('Add certificate to the end of the list')?>">
			<i class="fa-solid fa-plus icon-embed-btn"></i>
			<?=gettext("Add");?>
		</a>
		<button name="del_x" type="submit" class="btn btn-danger btn-sm" value="<?=gettext("Delete selected certificates"); ?>" title="<?=gettext('Delete selected certificates')?>">
			<i class="fa-solid fa-trash-can icon-embed-btn"></i>
			<?=gettext("Delete"); ?>
		</button>
		<button type="submit" id="order-store" name="order-store" class="btn btn-sm btn-primary" value="store changes" disabled title="<?=gettext('Save certificate order')?>">
			<i class="fa-solid fa-save icon-embed-btn no-confirm"></i>
			<?=gettext("Save")?>
		</button>
	</nav>

<div class="infoblock blockopen">
	<?php print_info_box(sprintf(gettext('Use the search box to filter the list and show only matching entries. <br />' .
						   'Click table column headers to sort table entries. ' .
						   'Do not use the movement/reordering controls after sorting the table.'), '<br />'), 'info', false); ?>
</div>
</form>

<script type="text/javascript">
//<![CDATA[

function set_content(elementid, image) {
	var item = document.getElementById(elementid);
	item.innerHTML = image;
}

function js_callbackrenew(data) {
	$('#renewoutputbox').removeClass("hidden");
	$('#renewoutput').text(data);
}

function js_callback(req_content) {
	
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

function issuecertificate($id) {
	$("i[id='btnissueicon_"+$id+"']").removeClass("fa-check").addClass("fa-cog fa-solid fa-spin");
	
	ajaxRequest = $.ajax({
		url: "",
		type: "post",
		data: { id: $id, action: "issuecert"},
		success: function(data) {
			js_callbackrenew(data);
			$("i[id='btnissueicon_"+$id+"']").removeClass("fa-cog fa-spin").addClass("fa-solid fa-check");
		},
		error: function(data) {
			$("i[id='btnissueicon_"+$id+"']").removeClass("fa-cog fa-spin").addClass("fa-solid fa-link-slash");
		}
	});
}

function renewcertificate($id) {
	$("i[id='btnrenewicon_"+$id+"']").removeClass("fa-check").addClass("fa-cog fa-solid fa-spin");
	
	ajaxRequest = $.ajax({
		url: "",
		type: "post",
		data: { id: $id, action: "renewcert"},
		success: function(data) {
			js_callbackrenew(data);
			$("i[id='btnrenewicon_"+$id+"']").removeClass("fa-cog fa-spin").addClass("fa-solid fa-check");
		},
		error: function(data) {
			$("i[id='btnrenewicon_"+$id+"']").removeClass("fa-cog fa-spin").addClass("fa-solid fa-link-slash");
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
		buttonid = event.target.id.slice(1);
		$("[id='" + buttonid + "']").click();
		return false;
	});
	$('[id^=Xmove_]').css('cursor', 'pointer');

	// Check all of the rule checkboxes so that their values are posted
	$('#order-store').click(function () {
	   $('[id^=frc]').prop('checked', true);
	});

	// Make these controls plain buttons
	$("#btnsearch").prop('type', 'button');
	$("#btnclear").prop('type', 'button');

	// Search for a term in the entry name and/or dn
	$("#btnsearch").click(function() {
		var searchstr = $('#searchstr').val().toLowerCase();
		var table = $("table tbody");
		var where = $('#where').val();

		table.find('tr').each(function (i) {
			var $tds = $(this).find('td'),
				shortname = $tds.eq(2).text().trim().toLowerCase(),
				descr = $tds.eq(3).text().trim().toLowerCase();

			regexp = new RegExp(searchstr);
			if (searchstr.length > 0) {
				if (!(regexp.test(shortname) && (where != 1)) && !(regexp.test(descr) && (where != 0))) {
					$(this).hide();
				} else {
					$(this).show();
				}
			} else {
				$(this).show();	// A blank search string shows all
			}
		});
	});

	// Clear the search term and unhide all rows (that were hidden during a previous search)
	$("#btnclear").click(function() {
		var table = $("table tbody");

		$('#searchstr').val("");

		table.find('tr').each(function (i) {
			$(this).show();
		});
	});

	// Hitting the enter key will do the same as clicking the search button
	$("#searchstr").on("keyup", function (event) {
		if (event.keyCode == 13) {
			$("#btnsearch").get(0).click();
		}
	});
});
//]]>
</script>
<?php include("foot.inc");
