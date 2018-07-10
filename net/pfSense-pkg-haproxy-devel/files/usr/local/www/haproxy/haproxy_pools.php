<?php
/*
 * haproxy_pools.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2009 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013 PiBa-NL
 * Copyright (c) 2008 Remco Hoef <remcoverhoef@pfsense.com>
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

$shortcut_section = "haproxy";
require_once("guiconfig.inc");
require_once("haproxy/haproxy.inc");
require_once("haproxy/haproxy_gui.inc");
require_once("haproxy/pkg_haproxy_tabs.inc");

haproxy_config_init();

$a_pools = &$config['installedpackages']['haproxy']['ha_pools']['item'];
$a_backends = &$config['installedpackages']['haproxy']['ha_backends']['item'];

if ($_POST['apply']) {
	$result = haproxy_check_and_run($savemsg, true);
	if ($result) {
		unlink_if_exists($d_haproxyconfdirty_path);
	}
} elseif ($_POST['del_x']) {
	/* delete selected rules */
	$deleted = false;
	if (is_array($_POST['rule']) && count($_POST['rule'])) {
		foreach ($_POST['rule'] as $rulei) {
			unset($a_pools[$rulei]);
			$deleted = true;
		}

		if ($deleted) {
			if (write_config("HAProxy, deleting backend(s)")) {
				//mark_subsystem_dirty('filter');
				touch($d_haproxyconfdirty_path);
			}
		}

		header("Location: haproxy_pools.php");
		exit;
	}
} elseif ($_POST['order-store']) {
	/* update rule order, POST[rule] is an array of ordered IDs */
	if (is_array($_POST['rule']) && !empty($_POST['rule'])) {
		$a_filter_new = array();

		// if a rule is not in POST[rule], it has been deleted by the user
		foreach ($_POST['rule'] as $id) {
			$a_filter_new[] = $a_pools[$id];
		}

		$a_pools = $a_filter_new;
		if (write_config()) {
			mark_subsystem_dirty('filter');
		}

		header("Location: haproxy_pools.php");
		exit;
	}
} elseif ($_GET['act'] == "del") {
	if (isset($a_pools[$_GET['id']])) {
		unset($a_pools[$_GET['id']]);
		write_config();
		touch($d_haproxyconfdirty_path);
	}
	header("Location: haproxy_pools.php");
	exit;
}

$pgtitle = array("Services", "HAProxy", "Backend");
include("head.inc");
if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg);
}
if (file_exists($d_haproxyconfdirty_path)) {
	print_apply_box(sprintf(gettext("The haproxy configuration has been changed.%sYou must apply the changes in order for them to take effect."), "<br/>"));
}
haproxy_display_top_tabs_active($haproxy_tab_array['haproxy'], "backend");

?>

<form method="post">
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext("Backends")?></h2>
		</div>
		<div id="mainarea" class="table-responsive panel-body">
			<table id="backendstbl" class="table table-hover table-striped table-condensed">
				<thead>
					<tr>
						<th><!-- checkbox --></th>
						<th>Advanced</th>
						<th>Name</th>
						<th>Servers</th>
						<th>Check</th>
						<th>Frontend</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody class="user-entries">

<?php
		$i = 0;
		foreach ($a_pools as $backend){
			$fes = find_frontends_using_backend($backend['name']);
			$fe_list = implode(", ", $fes);
			$disabled = $fe_list == "";

			if (is_array($backend['ha_servers'])) {
				$count = count($backend['ha_servers']['item']);
			} else {
				$count = 0;
			}
?>
					<tr id="fr<?=$i;?>" <?=$display?> onClick="fr_toggle(<?=$i;?>)" ondblclick="document.location='haproxy_pool_edit.php?id=<?=$i;?>';" <?=($disabled ? ' class="disabled"' : '')?>>
						<td >
							<input type="checkbox" id="frc<?=$i;?>" onClick="fr_toggle(<?=$i;?>)" name="rule[]" value="<?=$i;?>"/>
							<a class="fa fa-anchor" id="Xmove_<?=$i?>" title="<?=gettext("Move checked entries to here")?>"></a>
						</td>
			<!--tr class="<?=$textgray?>"-->
			  <td>
			  <?php
				if ($backend['stats_enabled']=='yes') {
					echo haproxyicon("stats", gettext("stats enabled"));
				}
				$isadvset = "";
				if ($backend['advanced']) {
					$isadvset .= "Per server pass thru\r\n";
				}
				if ($backend['advanced_backend']) {
					$isadvset .= "Backend pass thru\r\n";
				}
				if ($isadvset) {
					echo haproxyicon("advanced", gettext("advanced settings set") . ": {$isadvset}");
				}
			  ?>
			  </td>
			  <td>
				<?=$backend['name'];?>
			  </td>
			  <td>
				<?=$count;?>
			  </td>
			  <td>
				<?=$a_checktypes[$backend['check_type']]['name'];?>
			  </td>
			  <td>
				<?=$fe_list;?>
			  </td>
				<td class="action-buttons">
					<a href="haproxy_pool_edit.php?id=<?=$i;?>">
						<?=haproxyicon("edit", gettext("edit backend"))?>
					</a>
					<a href="haproxy_pools.php?act=del&amp;id=<?=$i;?>">
						<?=haproxyicon("delete", gettext("delete backend"))?>
					</a>
					<a href="haproxy_pool_edit.php?dup=<?=$i;?>">
						<?=haproxyicon("clone", gettext("clone backend"))?>
					</a>
			  	</td>
			</tr>
<?php
			$i++;
		}
?>
				</tbody>
			</table>
		</div>
	</div>
	<nav class="action-buttons">
		<a href="haproxy_pool_edit.php" role="button" class="btn btn-sm btn-success" title="<?=gettext('Add backend to the end of the list')?>">
			<i class="fa fa-level-down icon-embed-btn"></i>
			<?=gettext("Add");?>
		</a>
		<button name="del_x" type="submit" class="btn btn-danger btn-sm" value="<?=gettext("Delete selected backends"); ?>" title="<?=gettext('Delete selected backends')?>">
			<i class="fa fa-trash icon-embed-btn"></i>
			<?=gettext("Delete"); ?>
		</button>
		<button type="submit" id="order-store" name="order-store" class="btn btn-sm btn-primary" value="store changes" disabled title="<?=gettext('Save backend order')?>">
			<i class="fa fa-save icon-embed-btn"></i>
			<?=gettext("Save")?>
		</button>
	</nav>
</form>

<script type="text/javascript">
//<![CDATA[

	function moveRowUpAboveAnchor(rowId, tableId) {
		var table = $('#'+tableId);
		var viewcheckboxes = $('[id^=frc]input:checked', table);
		var rowview = $("#fr" + rowId, table);
		var moveabove = rowview;
		//var parent = moveabove[0].parentNode;
		
		viewcheckboxes.each(function( index ) {
			var moveid = this.value;
			console.log( index + ": " + this.id );

			var prevrowview = $("#fr" + moveid, table);
			prevrowview.insertBefore(moveabove);
			$('#order-store').removeAttr('disabled');
		});
	}

events.push(function() {
	$('[id^=Xmove_]').click(function (event) {
		/*$('[id="'+event.target.id.slice(1)+'"]').click();*/
		moveRowUpAboveAnchor(event.target.id.slice(6),"backendstbl");
		
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

<?php include("foot.inc"); ?>
