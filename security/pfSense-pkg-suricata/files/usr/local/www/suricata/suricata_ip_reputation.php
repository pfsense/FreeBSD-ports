<?php
/*
 * suricata_ip_reputation.php
 * part of pfSense
 *
 * Copyright (C) 2014 Bill Meeks
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

require_once("guiconfig.inc");
require_once("/usr/local/pkg/suricata/suricata.inc");

global $g, $rebuild_rules;

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

if (is_null($id)) {
	header("Location: /suricata/suricata_interfaces.php");
	exit;
}

if (!is_array($config['installedpackages']['suricata']['rule'])) {
	$config['installedpackages']['suricata']['rule'] = array();
}
if (!is_array($config['installedpackages']['suricata']['rule'][$id]['iplist_files']['item'])) {
	$config['installedpackages']['suricata']['rule'][$id]['iplist_files']['item'] = array();
}

$a_nat = &$config['installedpackages']['suricata']['rule'];

// If doing a postback, used typed values, else load from stored config
if (!empty($_POST)) {
	$pconfig = $_POST;
}
else {
	$pconfig = $a_nat[$id];
}

$iprep_path = SURICATA_IPREP_PATH;
$if_real = get_real_interface($a_nat[$id]['interface']);
$suricata_uuid = $config['installedpackages']['suricata']['rule'][$id]['uuid'];

if ($_POST['mode'] == 'iprep_catlist_add' && isset($_POST['iplist'])) {
	$pconfig = $_POST;

	// Test the supplied IP List file to see if it exists
	if (file_exists($_POST['iplist'])) {
		if (!$input_errors) {
			$a_nat[$id]['iprep_catlist'] = basename($_POST['iplist']);
			write_config("Suricata pkg: added new IP Rep Categories file for IP REPUTATION preprocessor.");
			mark_subsystem_dirty('suricata_iprep');
		}
	}
	else
		$input_errors[] = gettext("The file '{$_POST['iplist']}' could not be found.");

	$pconfig['iprep_catlist'] = $a_nat[$id]['iprep_catlist'];
	$pconfig['iplist_files'] = $a_nat[$id]['iplist_files'];
}

if ($_POST['mode'] == 'iplist_add' && isset($_POST['iplist'])) {
	$pconfig = $_POST;

	// Test the supplied IP List file to see if it exists
	if (file_exists($_POST['iplist'])) {
		// See if the file is already assigned to the interface
		foreach ($a_nat[$id]['iplist_files']['item'] as $f) {
			if ($f == basename($_POST['iplist'])) {
				$input_errors[] = gettext("The file {$f} is already assigned as a whitelist file.");
				break;
			}
		}
		if (!$input_errors) {
			$a_nat[$id]['iplist_files']['item'][] = basename($_POST['iplist']);
			write_config("Suricata pkg: added new whitelist file for IP REPUTATION preprocessor.");
			mark_subsystem_dirty('suricata_iprep');
		}
	}
	else
		$input_errors[] = gettext("The file '{$_POST['iplist']}' could not be found.");

	$pconfig['iprep_catlist'] = $a_nat[$id]['iprep_catlist'];
	$pconfig['iplist_files'] = $a_nat[$id]['iplist_files'];
}

if ($_POST['iprep_catlist_del']) {
	$pconfig = $_POST;
	unset($a_nat[$id]['iprep_catlist']);
	write_config("Suricata pkg: deleted blacklist file for IP REPUTATION preprocessor.");
	mark_subsystem_dirty('suricata_iprep');
	$pconfig['iprep_catlist'] = $a_nat[$id]['iprep_catlist'];
	$pconfig['iplist_files'] = $a_nat[$id]['iplist_files'];
}

if ($_POST['iplist_del'] && is_numericint($_POST['list_id'])) {
	$pconfig = $_POST;
	unset($a_nat[$id]['iplist_files']['item'][$_POST['list_id']]);
	write_config("Suricata pkg: deleted whitelist file for IP REPUTATION preprocessor.");
	mark_subsystem_dirty('suricata_iprep');
	$pconfig['iplist_files'] = $a_nat[$id]['iplist_files'];
	$pconfig['iprep_catlist'] = $a_nat[$id]['iprep_catlist'];
}

if ($_POST['save'] || $_POST['apply']) {

	$pconfig['iprep_catlist'] = $a_nat[$id]['iprep_catlist'];
	$pconfig['iplist_files'] = $a_nat[$id]['iplist_files'];

	// Validate HOST TABLE values
	if ($_POST['host_memcap'] < 1000000 || !is_numericint($_POST['host_memcap']))
		$input_errors[] = gettext("The value for 'Host Memcap' must be a numeric integer greater than 1MB (1,048,576!");
	if ($_POST['host_hash_size'] < 1024 || !is_numericint($_POST['host_hash_size']))
		$input_errors[] = gettext("The value for 'Host Hash Size' must be a numeric integer greater than 1024!");
	if ($_POST['host_prealloc'] < 10 || !is_numericint($_POST['host_prealloc']))
		$input_errors[] = gettext("The value for 'Host Preallocations' must be a numeric integer greater than 10!");

	// Validate CATEGORIES FILE
	if ($_POST['enable_iprep'] == 'on') {
		if (empty($a_nat[$id]['iprep_catlist']))
			$input_errors[] = gettext("Assignment of a 'Categories File' is required when IP Reputation is enabled!");
	}

	// If no errors write to conf
	if (!$input_errors) {

		$a_nat[$id]['enable_iprep'] = $_POST['enable_iprep'] ? 'on' : 'off';
		$a_nat[$id]['host_memcap'] = str_replace(",", "", $_POST['host_memcap']);
		$a_nat[$id]['host_hash_size'] = str_replace(",", "", $_POST['host_hash_size']);
		$a_nat[$id]['host_prealloc'] = str_replace(",", "", $_POST['host_prealloc']);

		write_config("Suricata pkg: modified IP REPUTATION preprocessor settings for {$a_nat[$id]['interface']}.");

		// Update the suricata conf file for this interface
		$rebuild_rules = false;
		conf_mount_rw();
		suricata_generate_yaml($a_nat[$id]);
		conf_mount_ro();

		// Soft-restart Suricata to live-load new variables
		suricata_reload_config($a_nat[$id]);

		// Sync to configured CARP slaves if any are enabled
		suricata_sync_on_changes();

		// We have saved changes and done a soft restart, so clear "dirty" flag
		clear_subsystem_dirty('suricata_iprep');
	}
}

$if_friendly = convert_friendly_interface_to_friendly_descr($a_nat[$id]['interface']);
$pgtitle = gettext("Suricata: Interface {$if_friendly} IP Reputation Preprocessor");
include_once("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php 
include("fbegin.inc"); 
?>

<form action="suricata_ip_reputation.php" method="post" name="iform" id="iform" >
<input name="id" type="hidden" value="<?=$id;?>" />
<input type="hidden" id="mode" name="mode" value="" />
<input name="iplist" id="iplist" type="hidden" value="" />
<input name="list_id" id="list_id" type="hidden" value="" />

<?php if (is_subsystem_dirty('suricata_iprep') && !$input_errors): ?><p>
<?php print_info_box_np(gettext("A change has been made to IP List file assignments.") . "<br/>" . gettext("You must apply the change in order for it to take effect."));?>
<?php endif; ?>
<?php
/* Display Alert message */
if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);
?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tbody>
	<tr>
		<td>
		<?php
		$tab_array = array();
		$tab_array[] = array(gettext("Interfaces"), true, "/suricata/suricata_interfaces.php");
		$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
		$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
		$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php?instance={$id}");
		$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
		$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
		$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
		$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php?instance={$id}");
		$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
		$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
		$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
		$tab_array[] = array(gettext("IP Lists"), false, "/suricata/suricata_ip_list_mgmt.php");
		display_top_tabs($tab_array, true);
		echo '</td></tr>';
		echo '<tr><td class="tabnavtbl">';
		$tab_array = array();
		$menu_iface=($if_friendly?substr($if_friendly,0,5)." ":"Iface ");
		$tab_array[] = array($menu_iface . gettext("Settings"), false, "/suricata/suricata_interfaces_edit.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Categories"), false, "/suricata/suricata_rulesets.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Rules"), false, "/suricata/suricata_rules.php?id={$id}");
	        $tab_array[] = array($menu_iface . gettext("Flow/Stream"), false, "/suricata/suricata_flow_stream.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("App Parsers"), false, "/suricata/suricata_app_parsers.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Variables"), false, "/suricata/suricata_define_vars.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Barnyard2"), false, "/suricata/suricata_barnyard.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("IP Rep"), true, "/suricata/suricata_ip_reputation.php?id={$id}");
		display_top_tabs($tab_array, true);
		?>
		</td>
	</tr>
	<tr>
		<td><div id="mainarea">
			<table id="maintable" class="tabcont" width="100%" border="0" cellpadding="6" cellspacing="0">
				<tbody>
			<?php if ($g['platform'] == "nanobsd") : ?>
				<tr>
					<td colspan="2" class="listtopic"><?php echo gettext("IP Reputation is not supported on NanoBSD installs"); ?></td>
				</tr>
			<?php else: ?>
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?php echo gettext("IP Reputation Configuration"); ?></td>
				</tr>
				<tr>
					<td width="22%" valign='top' class='vncell'><?php echo gettext("Enable"); ?>
					</td>
					<td width="78%" class="vtable"><input name="enable_iprep" type="checkbox" value="on" <?php if ($pconfig['enable_iprep'] == "on") echo "checked"; ?>/>
					<?php echo gettext("Use IP Reputation Lists on this interface.  Default is ") . "<strong>" . gettext("Not Checked.") . "</strong>"; ?>
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell"><?php echo gettext("Host Memcap"); ?></td>
					<td width="78%" class="vtable"><input name="host_memcap" type="text" 
					class="formfld unknown" id="host_memcap" size="8" value="<?=htmlspecialchars($pconfig['host_memcap']); ?>"/>&nbsp;
					<?php echo gettext("Host table memory cap in bytes.  Default is ") . "<strong>" . 
					gettext("16777216") . "</strong>" . gettext(" (16 MB).  Min value is 1048576 (1 MB)."); ?><br/><br/><?php echo gettext("When using large IP Reputation Lists, this value may need to be increased " . 
					"to avoid exhausting Host Table memory.") ?></td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell"><?php echo gettext("Host Hash Size"); ?></td>
					<td width="78%" class="vtable"><input name="host_hash_size" type="text" 
					class="formfld unknown" id="host_hash_size" size="8" value="<?=htmlspecialchars($pconfig['host_hash_size']); ?>"/>&nbsp;
					<?php echo gettext("Host Hash Size in bytes.  Default is ") . "<strong>" . 
					gettext("4096") . "</strong>" . gettext(".  Min value is 1024."); ?><br/><br/><?php echo gettext("When using large IP Reputation Lists, this value may need to be increased."); ?></td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell"><?php echo gettext("Host Preallocations"); ?></td>
					<td width="78%" class="vtable"><input name="host_prealloc" type="text" 
					class="formfld unknown" id="host_prealloc" size="8" value="<?=htmlspecialchars($pconfig['host_prealloc']); ?>"/>&nbsp;
					<?php echo gettext("Number of Host Table entries to preallocate.  Default is ") . "<strong>" . 
					gettext("1000") . "</strong>" . gettext(".  Min value is 10."); ?><br/><br/><?php echo gettext("Increasing this value may slightly improve performance when using large IP Reputation Lists."); ?></td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell">&nbsp;</td>
					<td width="78%" class="vtable">
					<input name="save" type="submit" class="formbtn" value="Save" title="<?=gettext("Save IP Reputation configuration");?>" />
					&nbsp;&nbsp;<?=gettext("Click to save configuration settings and live-reload the running Suricata configuration.");?>
					</td>
				</tr>
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Assign Categories File"); ?></td>
				</tr>
				<tr>
					<td width="22%" valign='top' class='vncell'><?php echo gettext("Categories File"); ?>
					</td>
					<td width="78%" class="vtable">
					<!-- iprep_catlist_chooser -->
					<div id="iprep_catlistChooser" name="iprep_catlistChooser" style="display:none; border:1px dashed gray; width:98%;"></div>
						<table width="95%" border="0" cellpadding="2" cellspacing="0">
							<colgroup>
								<col style="text-align:left;">
								<col style="width: 30%; text-align:left;">
								<col style="width: 17px;">
							</colgroup>
							<thead>
								<tr>
									<th class="listhdrr"><?php echo gettext("Categories Filename"); ?></th>
									<th class="listhdrr"><?php echo gettext("Modification Time"); ?></th>
									<th class="list" align="left" valign="middle"><img style="cursor:pointer;" name="iprep_catlist_add" id="iprep_catlist_add" 
									src="../themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" 
									height="17" border="0" title="<?php echo gettext('Assign a Categories file');?>"/></th>
								</tr>
							</thead>
							<tbody>
							<?php if (!empty($pconfig['iprep_catlist'])) :
									$class = "listr";
									if (!file_exists("{$iprep_path}{$pconfig['iprep_catlist']}")) {
										$filedate = gettext("Unknown -- file missing");
										$class .= " red";
									}
									else
										$filedate = date('M-d Y   g:i a', filemtime("{$iprep_path}{$pconfig['iprep_catlist']}"));
							 ?>
								<tr>
									<td class="<?=$class;?>"><?=htmlspecialchars($pconfig['iprep_catlist']);?></td>
									<td class="<?=$class;?>" align="center"><?=$filedate;?></td>
									<td class="list"><input type="image" name="iprep_catlist_del[]" id="iprep_catlist_del[]" onClick="document.getElementById('list_id').value='0';" 
									src="../themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" 
									border="0" title="<?php echo gettext('Remove this Categories file');?>"/></td>
								</tr>
							<?php endif; ?>
								<tr>
									<td colspan="2" class="vexpl"><span class="red"><strong><?=gettext("Note: ");?></strong></span>
									<?=gettext("change to Categories File assignment is immediately saved.");?></td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Assign IP Reputation Lists"); ?></td>
				</tr>
				<tr>
					<td width="22%" valign='top' class='vncell'><?php echo gettext("IP Reputation Files"); ?>
					</td>
					<td width="78%" class="vtable">
						<table width="95%" border="0" cellpadding="2" cellspacing="0">
					<!-- iplist_chooser -->
					<div id="iplistChooser" name="iplistChooser" style="display:none; border:1px dashed gray; width:98%;"></div>
							<colgroup>
								<col style="text-align:left;">
								<col style="width: 30%; text-align:left;">
								<col style="width: 17px;">
							</colgroup>
							<thead>
								<tr>
									<th class="listhdrr"><?php echo gettext("IP Reputation List Filename"); ?></th>
									<th class="listhdrr"><?php echo gettext("Modification Time"); ?></th>
									<th class="list" align="left" valign="middle"><img style="cursor:pointer;" name="iplist_add" id="iplist_add" 
									src="../themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" 
									border="0" title="<?php echo gettext('Assign a whitelist file');?>"/></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach($pconfig['iplist_files']['item'] as $k => $f):
									$class = "listr";
									if (!file_exists("{$iprep_path}{$f}")) {
										$filedate = gettext("Unknown -- file missing");
										$class .= " red";
									}
									else
										$filedate = date('M-d Y   g:i a', filemtime("{$iprep_path}{$f}"));
							 ?>
								<tr>
									<td class="<?=$class;?>"><?=htmlspecialchars($f);?></td>
									<td class="<?=$class;?>" align="center"><?=$filedate;?></td>
									<td class="list"><input type="image" name="iplist_del[]" id="iplist_del[]" onClick="document.getElementById('list_id').value='<?=$k;?>';" 
									src="../themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" 
									border="0" title="<?php echo gettext('Remove this whitelist file');?>"/></td>
								</tr>
							<?php endforeach; ?>
								<tr>
									<td colspan="2" class="vexpl"><span class="red"><strong><?=gettext("Note: ");?></strong></span>
									<?=gettext("changes to IP Reputation List assignments are immediately saved.");?></td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
			<?php endif; ?>
				</tbody>
			</table>
		</div>
		</td>
	</tr>
	</tbody>
</table>

<?php if ($g['platform'] != "nanobsd") : ?>
<script type="text/javascript">
Event.observe(
	window, "load",
	function() {
		Event.observe(
			"iprep_catlist_add", "click",
			function() {
				Effect.Appear("iprep_catlistChooser", { duration: 0.25 });
				iprep_catlistChoose();
			}
		);

		Event.observe(
			"iplist_add", "click",
			function() {
				Effect.Appear("iplistChooser", { duration: 0.25 });
				iplistChoose();
			}
		);
	}
);

function iprep_catlistChoose() {
	Effect.Appear("iprep_catlistChooser", { duration: 0.25 });
	if($("fbCurrentDir"))
		$("fbCurrentDir").innerHTML = "Loading ...";

	new Ajax.Request(
		"/suricata/suricata_iprep_list_browser.php?container=iprep_catlistChooser&target=iplist&val=" + new Date().getTime(),
		{ method: "get", onComplete: iprep_catlistComplete }
	);
}

function iplistChoose() {
	Effect.Appear("iplistChooser", { duration: 0.25 });
	if($("fbCurrentDir"))
		$("fbCurrentDir").innerHTML = "Loading ...";

	new Ajax.Request(
		"/suricata/suricata_iprep_list_browser.php?container=iplistChooser&target=iplist&val=" + new Date().getTime(),
		{ method: "get", onComplete: iplistComplete }
	);
}

function iprep_catlistComplete(req) {
	$("iprep_catlistChooser").innerHTML = req.responseText;

	var actions = {
		fbClose: function() { $("iprep_catlistChooser").hide();                    },
		fbFile:  function() { $("iplist").value = this.id;
				      $("mode").value = 'iprep_catlist_add';
				      document.getElementById('iform').submit();
				    }
	}

	for(var type in actions) {
		var elem = $("iprep_catlistChooser");
		var list = elem.getElementsByClassName(type);
		for (var i=0; i<list.length; i++) {
			Event.observe(list[i], "click", actions[type]);
			list[i].style.cursor = "pointer";
		}
	}
}

function iplistComplete(req) {
	$("iplistChooser").innerHTML = req.responseText;

	var actions = {
		fbClose: function() { $("iplistChooser").hide();                    },
		fbFile:  function() { $("iplist").value = this.id;
				      $("mode").value = 'iplist_add';
				      document.getElementById('iform').submit();
				    }
	}

	for(var type in actions) {
		var elem = $("iplistChooser");
		var list = elem.getElementsByClassName(type);
		for (var i=0; i<list.length; i++) {
			Event.observe(list[i], "click", actions[type]);
			list[i].style.cursor = "pointer";
		}
	}
}

</script>
<?php endif; ?>

</form>
<?php include("fend.inc"); ?>
</body>
</html>
