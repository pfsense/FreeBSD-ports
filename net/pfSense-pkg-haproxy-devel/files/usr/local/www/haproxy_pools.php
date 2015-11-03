<?php
/* $Id: load_balancer_virtual_server.php,v 1.6.2.1 2006/01/02 23:46:24 sullrich Exp $ */
/*
	haproxy_pools.php
	part of pfSense (https://www.pfsense.org/)
	Copyright (C) 2013 PiBa-NL
	Copyright (C) 2009 Scott Ullrich <sullrich@pfsense.com>
	Copyright (C) 2008 Remco Hoef <remcoverhoef@pfsense.com>
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
$shortcut_section = "haproxy";
require_once("guiconfig.inc");
require_once("haproxy.inc");
require_once("pkg_haproxy_tabs.inc");


if (!is_array($config['installedpackages']['haproxy']['ha_pools']['item'])) {
	$config['installedpackages']['haproxy']['ha_pools']['item'] = array();
}
if (!is_array($config['installedpackages']['haproxy']['ha_backends']['item'])) {
	$config['installedpackages']['haproxy']['ha_backends']['item'] = array();
}

$a_pools = &$config['installedpackages']['haproxy']['ha_pools']['item'];
$a_backends = &$config['installedpackages']['haproxy']['ha_backends']['item'];

if ($_POST) {
	$pconfig = $_POST;

	if ($_POST['apply']) {
		$result = haproxy_check_and_run($savemsg, true);
		if ($result)
			unlink_if_exists($d_haproxyconfdirty_path);
	}
}

if ($_GET['act'] == "del") {
	if (isset($a_pools[$_GET['id']])) {
		unset($a_pools[$_GET['id']]);
		write_config();
		touch($d_haproxyconfdirty_path);
	}
	header("Location: haproxy_pools.php");
	exit;
}

$pgtitle = "Services: HAProxy: Backend server pools";
include("head.inc");
haproxy_css();

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="haproxy_pools.php" method="post">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_haproxyconfdirty_path)): ?>
<?php print_info_box_np("The haproxy configuration has been changed.<br/>You must apply the changes in order for them to take effect.");?><br/>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
  <?php
	haproxy_display_top_tabs_active($haproxy_tab_array['haproxy'], "backend");
  ?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
		<table class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr>
			<td width="5%" class="listhdrr">Advanced</td>
			<td width="25%" class="listhdrr">Name</td>
			<td width="10%" class="listhdrr">Servers</td>
			<td width="10%" class="listhdrr">Check</td>
			<td width="30%" class="listhdrr">Frontend</td>
			<td width="10%" class="list"></td>
		</tr>
<?php
		$img_adv = "/themes/{$g['theme']}/images/icons/icon_advanced.gif";
		$i = 0;
		foreach ($a_pools as $pool){
			$fe_list = "";
			$sep = "";
			foreach ($a_backends as $frontend) {
				$used = false;
				if($frontend['backend_serverpool'] == $pool['name']) {
					$used = true;
				}
				$actions = $frontend['a_actionitems']['item'];
				if (is_array($actions)) {
					foreach($actions as $action) {
						if ($action["action"] == "use_backend" && $action['use_backendbackend'] == $pool['name']) {
							$used = true;
						}
					}
				}
				if ($used) {
					$fe_list .= $sep . $frontend['name'];
					$sep = ", ";
				}
			}
			$textgray = $fe_list == "" ? " gray" : "";
			
			if (is_array($pool['ha_servers'])) {
				$count = count($pool['ha_servers']['item']);
			} else {
				$count = 0;
			}
?>
			<tr class="<?=$textgray?>">
			  <td class="listlr" ondblclick="document.location='haproxy_pool_edit.php?id=<?=$i;?>';">
			  <?
				if ($pool['stats_enabled']=='yes'){
					echo "<img src=\"./themes/{$g['theme']}/images/icons/icon_log_s.gif\"" . ' title="stats enabled" width="11" height="15" border="0" />';
				}
				$isadvset = "";
				if ($pool['advanced']) $isadvset .= "Per server pass thru\r\n";
				if ($pool['advanced_backend']) $isadvset .= "Backend pass thru\r\n";
				if ($isadvset)
					echo "<img src=\"$img_adv\" title=\"" . gettext("advanced settings set") . ": {$isadvset}\" border=\"0\" />";
			  ?>
			  </td>
			  <td class="listlr" ondblclick="document.location='haproxy_pool_edit.php?id=<?=$i;?>';">
				<?=$pool['name'];?>
			  </td>
			  <td class="listlr" ondblclick="document.location='haproxy_pool_edit.php?id=<?=$i;?>';">
				<?=$count;?>
			  </td>
			  <td class="listlr" ondblclick="document.location='haproxy_pool_edit.php?id=<?=$i;?>';">
				<?=$a_checktypes[$pool['check_type']]['name'];?>
			  </td>
			  <td class="listlr" ondblclick="document.location='haproxy_pool_edit.php?id=<?=$i;?>';">
				<?=$fe_list;?>
			  </td>
			  <td class="list" nowrap>
				<table border="0" cellspacing="0" cellpadding="1">
				  <tr>
					<td valign="middle"><a href="haproxy_pool_edit.php?id=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_e.gif" title="<?=gettext("edit backend");?>" width="17" height="17" border="0" /></a></td>
					<td valign="middle"><a href="haproxy_pools.php?act=del&amp;id=<?=$i;?>" onclick="return confirm('Do you really want to delete this entry?')"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" title="<?=gettext("delete backend");?>" width="17" height="17" border="0" /></a></td>
					<td valign="middle"><a href="haproxy_pool_edit.php?dup=<?=$i;?>"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("clone backend");?>" width="17" height="17" border="0" /></a></td>
				  </tr>
				</table>
			  </td>
			</tr>
<?php
			$i++; 
		}
?>
			<tfoot>
			<tr>
			  <td class="list" colspan="5"></td>
			  <td class="list">
				<table border="0" cellspacing="0" cellpadding="1">
				  <tr>
					<td valign="middle"><a href="haproxy_pool_edit.php"><img src="/themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" title="<?=gettext("add new backend");?>" width="17" height="17" border="0" /></a></td>
				  </tr>
				</table>
			  </td>
			</tr>
			</tfoot>
		</table>
	</div>
	</table>
	</form>
<?php include("fend.inc"); ?>
</body>
</html>
