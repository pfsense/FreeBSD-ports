<?php
/*
 * haproxy_listeners.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2008 Remco Hoef <remcoverhoef@pfsense.com>
 * Copyright (c) 2009 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2013-2016 PiBa-NL
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
require_once("certs.inc");
require_once("haproxy/haproxy.inc");
require_once("haproxy/haproxy_gui.inc");
require_once("haproxy/haproxy_utils.inc");
require_once("haproxy/pkg_haproxy_tabs.inc");

$changedesc = "Services: HAProxy: Frontends";

if (!is_array($config['installedpackages']['haproxy']['ha_backends']['item'])) {
	$config['installedpackages']['haproxy']['ha_backends']['item'] = array();
}
$a_frontend = &$config['installedpackages']['haproxy']['ha_backends']['item'];

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

if($_GET['action'] == "toggle") {
	$id = $_GET['id'];
	echo "$id|";
	if (isset($a_frontend[get_frontend_id($id)])) {
		$frontent = &$a_frontend[get_frontend_id($id)];
		if ($frontent['status'] != "disabled"){
			$frontent['status'] = 'disabled';
			echo "0|";
		}else{
			$frontent['status'] = 'active';
			echo "1|";
		}
		$changedesc .= " set frontend '$id' status to: {$frontent['status']}";
		
		touch($d_haproxyconfdirty_path);
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
			unlink_if_exists($d_haproxyconfdirty_path);
		}
	} elseif ($_POST['del_x']) {
		/* delete selected rules */
		$deleted = false;
		if (is_array($_POST['rule']) && count($_POST['rule'])) {
			$selected = array();
			foreach($_POST['rule'] as $selection) {
				$selected[] = get_frontend_id($selection);
			}
			foreach ($selected as $itemnr) {
				unset($a_frontend[$itemnr]);
				$deleted = true;
			}
			if ($deleted) {
				if (write_config("HAProxy, deleting frontend(s)")) {
					//mark_subsystem_dirty('filter');
					touch($d_haproxyconfdirty_path);
				}
			}
			header("Location: haproxy_listeners.php");
			exit;
		}
	} else {	

		// from '\src\usr\local\www\vpn_ipsec.php'
		/* yuck - IE won't send value attributes for image buttons, while Mozilla does - so we use .x/.y to find move button clicks instead... */
		// TODO: this. is. nasty.
		unset($delbtn, $delbtnp2, $movebtn, $movebtnp2, $togglebtn, $togglebtnp2);
		foreach ($_POST as $pn => $pd) {
			// if name contains a dot its replaced in $POST key value by a underscore while $pd can be an array for some values..
			if (preg_match("/move_(.+)/", $pn, $matches)) {
				$movebtn = substr($pd, 5);
			}
		}
		//
		
		/* move selected p1 entries before this */
		if (isset($movebtn) && is_array($_POST['rule']) && count($_POST['rule'])) {
			$moveto = get_frontend_id($movebtn);
			$selected = array();
			foreach($_POST['rule'] as $selection) {
				$selected[] = get_frontend_id($selection);
			}
			array_moveitemsbefore($a_frontend, $moveto, $selected);
		
			touch($d_haproxyconfdirty_path);
			write_config($changedesc);			
		}
	}
} else {
	$result = haproxy_check_config($retval);
	if ($result) {
		$savemsg = gettext($result);
	}
}

if ($_GET['act'] == "del") {
	$id = $_GET['id'];
	$id = get_frontend_id($id);
	if (isset($a_frontend[$id])) {
		if (!$input_errors) {
			unset($a_frontend[$id]);
			$changedesc .= " Frontend delete";
			write_config($changedesc);
			touch($d_haproxyconfdirty_path);
		}
		header("Location: haproxy_listeners.php");
		exit;
	}
}

function haproxy_userlist_backend_servers($backendname) {
	//used for hint title text when hovering mouse over a backend name
	global $a_servermodes;
	$backend_servers = "";
	$backend = get_backend($backendname);
	if ($backend && is_array($backend['ha_servers']) && is_array($backend['ha_servers']['item'])){
		$servers = $backend['ha_servers']['item'];
		$backend_servers = sprintf(gettext("Servers in \"%s\" pool:"), $backendname);
		if (is_array($servers)){
			foreach($servers as $server){
				$srvstatus = $server['status'];
				$status = $a_servermodes[$srvstatus]['sign'];
				if (isset($server['forwardto']) && $server['forwardto'] != "") {
					$backend_servers .= "\n{$status}[{$server['forwardto']}]";
				} else {
					$backend_servers .= "\n{$status}{$server['address']}:{$server['port']}";
				}
			}
		}
	}
	return $backend_servers;
}

$pgtitle = array("Services", "HAProxy", "Frontends");
include("head.inc");
if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg);
}

$display_apply = file_exists($d_haproxyconfdirty_path) ? "" : "none";
echo "<div id='showapplysettings' style='display: {$display_apply};'>";
print_apply_box(sprintf(gettext("The haproxy configuration has been changed.%sYou must apply the changes in order for them to take effect."), "<br/>"));
echo "</div>";

haproxy_display_top_tabs_active($haproxy_tab_array['haproxy'], "frontend");

?>
<form action="haproxy_listeners.php" method="post">
<script type="text/javascript" src="/haproxy/haproxy_geturl.js"></script>
<script type="text/javascript">
function set_content(elementid, image) {
	var item = document.getElementById(elementid);
	item.innerHTML = image;
}

function js_callback(req) {
	showapplysettings.style.display = 'block';
	
	if(req.content !== '') {
		var itemsplit = req.content.split("|");
		buttonid = itemsplit[0];
		enabled = itemsplit[1];
		if (enabled === "1"){
			img = "<?=haproxyicon("enabled", gettext("click to toggle enable/disable this frontend"))?>";
		} else {
			img = "<?=haproxyicon("disabled", gettext("click to toggle enable/disable this frontend"))?>";
		}
		set_content('btn_'+buttonid, img);
	}
}
</script>
<?php
	
	function sort_sharedfrontends(&$a, &$b) {
		// make sure the 'primary frontend' is the first in the array, after that sort by name.
		if ($a['secondary'] != $b['secondary']) {
			return $a['secondary'] > $b['secondary'] ? 1 : -1;
		}
		if ($a['name'] != $b['name']) {
			return $a['name'] > $b['name'] ? 1 : -1;
		}
		return 0;
	}
	
	$a_frontend_grouped = array();
	foreach($a_frontend as &$frontend2) {
		$mainfrontend = get_primaryfrontend($frontend2);
		$mainname = $mainfrontend['name'];
		$ipport = get_frontend_ipport($frontend2, true);
		$frontend2['ipport'] = $ipport;
		$frontend2['type'] = $mainfrontend['type'];
		$a_frontend_grouped[$mainname][] = $frontend2;
	}
?>

	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title">Frontends</h2>
		</div>
		<div id="mainarea" class="table-responsive panel-body">
			<table class="table table-hover table-striped table-condensed">
				<thead>
					<tr>
						<th>Primary</th>
						<th>Shared</th>
						<th>On</th>
						<th>Advanced</th>
						<th>Name</th>
						<th>Description</th>
						<th>Address</th>
						<th>Type</th>
						<th>Backend</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody class="user-entries">
<?php
		$textgray = "";
		$first = true;		
		$last_frontend_shared = false;
		$i = 0;
		foreach ($a_frontend_grouped as $a_frontend) {
			//usort($a_frontend, 'sort_sharedfrontends');
			if ((count($a_frontend) > 1 || $last_frontend_shared) && !$first) {
				?> <tr class="<?=$textgray?>"><td colspan="10">&nbsp;</td></tr> <?	
			}
			$first = false;
			$last_frontend_shared = count($a_frontend) > 1;
			foreach ($a_frontend as $frontend) {
				$frontendname = $frontend['name'];
				$disabled = $frontend['status'] != 'active';
				?>
					<tr id="fr<?=$frontendname;?>" <?=$display?> onClick="fr_toggle('<?=$frontendname;?>')" ondblclick="document.location='haproxy_listeners_edit.php?id=<?=$frontendname;?>';" <?=($disabled ? ' class="disabled"' : '')?>>
						<td>
						<?if($frontend['secondary'] != 'yes'):?>
							<input type="checkbox" id="frc<?=$frontendname;?>" onClick="fr_toggle('<?=$frontendname;?>')" name="rule[]" value="<?=$frontendname;?>"/>
							<a class="fa fa-anchor" id="Xmove_<?=$frontendname?>" title="<?=gettext("Move checked entries to here")?>"></a>
						<?endif?>
						</td>
				  <td>
				  <?if($frontend['secondary'] == 'yes'):?>
					<input type="checkbox" id="frc<?=$frontendname;?>" onClick="fr_toggle('<?=$frontendname;?>')" name="rule[]" value="<?=$frontendname;?>"/>
					<a class="fa fa-anchor" id="Xmove_<?=$frontendname?>" title="<?=gettext("Move checked entries to here")?>"></a>
				  <?endif?>
				  </td>
				  <td>
					<?php
						if ($frontend['status']=='disabled'){
							$iconfn = "disabled";
						} else {
							$iconfn = "enabled";
						}?>
					<a id="btn_<?=$frontendname;?>" href='javascript:getURL("?id=<?=$frontendname;?>&amp;action=toggle&amp;", js_callback);'>
						<?=haproxyicon($iconfn, gettext("click to toggle enable/disable this frontend"))?>
					</a>
				  </td>
				  <td>
					<?php
					$acls = get_frontend_acls($frontend);
					$isaclset = "";
					foreach ($acls as $acl) {
						$isaclset .= "\n" . htmlspecialchars($acl['descr']);
					}
					if ($isaclset) {
						echo haproxyicon("acl", gettext("acl's used") . ": {$isaclset}");
					}
					
					if (get_frontend_uses_ssl($frontend)) {
						$cert = lookup_cert($frontend['ssloffloadcert']);
						$descr = htmlspecialchars($cert['descr']);
						if (is_array($frontend['ha_certificates']) && is_array($frontend['ha_certificates']['item'])) {
							$certs = $frontend['ha_certificates']['item'];
							if (count($certs) > 0){
								foreach($certs as $certitem){
									$cert = lookup_cert($certitem['ssl_certificate']);
									$descr .= "\n".htmlspecialchars($cert['descr']);
								}
							}
						}
						echo haproxyicon("cert", "SSL offloading cert: {$descr}");
					}
					
					$isadvset = "";
					if ($frontend['advanced_bind']) {
						$isadvset .= "Advanced bind: ".htmlspecialchars($frontend['advanced_bind'])."\r\n";
					}
					if ($frontend['advanced']) {
						$isadvset .= "Advanced pass thru setting used\r\n";
					}
					if ($isadvset) {
						echo haproxyicon("advanced", gettext("Advanced settings set") . ": {$isadvset}");
					}
					?>
				  </td>
				  <td>
					<?=$frontend['name'];?>
				  </td>
				  <td>
					<?=$frontend['desc'];?>
				  </td>
				  <td>
				    <?php
						$first = true;
						foreach($frontend['ipport'] as $addr) {
							//if (!$first)
							//	print "<br/>";
							print "<div style='white-space:nowrap;'>";
							print "{$addr['addr']}:{$addr['port']}";
							if ($addr['ssl'] == 'yes') {
								echo haproxyicon("cert", "SSL offloading");
							}
							print "</div>";
							$first = false;
						}
					?>
				  </td>
				  <td>
				  <?php
					if ($frontend['type'] == 'http') {
						$mainfrontend = get_primaryfrontend($frontend);
						$sslused = get_frontend_uses_ssl($mainfrontend);
						$httpused = !get_frontend_uses_ssl_only($frontend);
						if ($httpused) {
							echo "http";
						}
						if ($sslused) {
							echo ($httpused ? "/" : "") . "https";
						}
					} else {
						echo $a_frontendmode[$frontend['type']]['shortname'];
					}
				  ?>
				  </td>
				  <td>
					<?php
					if (is_array($frontend['a_actionitems']['item'])) {
						foreach ($frontend['a_actionitems']['item'] as $actionitem) {
							if ($actionitem['action'] == "use_backend") {
								$backend = $actionitem['use_backendbackend'];
								$hint = haproxy_userlist_backend_servers($backend);
								echo "<div title='{$hint}'>";
								echo "<a href='haproxy_pool_edit.php?id={$backend}'>{$backend}</a>";
								if (!empty($actionitem['acl'])) {
									echo "&nbsp;if({$actionitem['acl']})";
								}
								echo "<br/></div>";
							}
						}
					}
					$hint = haproxy_userlist_backend_servers($frontend['backend_serverpool']);
					$backend = $frontend['backend_serverpool'];
					if (!empty($backend)) {
						echo "<div title='{$hint}'>";
						echo "<a href='haproxy_pool_edit.php?id={$backend}'>{$backend}</a> (default)";
						echo "<br/></div>";
					}
					?>
				  </td>
				  <td class="action-icons">
					<button style="display: none;" class="btn btn-default btn-xs" type="submit" id="move_<?=$frontendname?>" name="move_<?=$frontendname?>" value="move_<?=$frontendname?>"></button>
					<a href="haproxy_listeners_edit.php?id=<?=$frontendname;?>">
						<?=haproxyicon("edit", gettext("edit frontend"))?>
					</a>
					<a href="haproxy_listeners.php?act=del&amp;id=<?=$frontendname;?>" onclick="return confirm('Do you really want to delete this entry?')">
						<?=haproxyicon("delete", gettext("delete frontend"))?>
					</a>
					<a href="haproxy_listeners_edit.php?dup=<?=$frontendname;?>">
						<?=haproxyicon("clone", gettext("clone frontend"))?>
					</a>
				  </td>
				</tr><?php
			}
		}
?>				
				</tbody>
			</table>
		</div>
	</div>
	<nav class="action-buttons">
		<a href="haproxy_listeners_edit.php" role="button" class="btn btn-sm btn-success" title="<?=gettext('Add backend to the end of the list')?>">
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
events.push(function() {
	$('[id^=Xmove_]').click(function (event) {
		$('[id="'+event.target.id.slice(1)+'"]').click();
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
