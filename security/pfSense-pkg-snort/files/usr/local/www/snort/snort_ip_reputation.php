<?php
/*
 * snort_ip_reputation.php
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
require_once("/usr/local/pkg/snort/snort.inc");

global $g, $rebuild_rules;

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

if (is_null($id)) {
	header("Location: /snort/snort_interfaces.php");
	exit;
}

if (!is_array($config['installedpackages']['snortglobal']['rule'])) {
	$config['installedpackages']['snortglobal']['rule'] = array();
}
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['wlist_files']['item'])) {
	$config['installedpackages']['snortglobal']['rule'][$id]['wlist_files']['item'] = array();
}
if (!is_array($config['installedpackages']['snortglobal']['rule'][$id]['blist_files']['item'])) {
	$config['installedpackages']['snortglobal']['rule'][$id]['blist_files']['item'] = array();
}

$a_nat = &$config['installedpackages']['snortglobal']['rule'];

$pconfig = $a_nat[$id];
$iprep_path = SNORT_IPREP_PATH;
$if_real = get_real_interface($a_nat[$id]['interface']);
$snort_uuid = $config['installedpackages']['snortglobal']['rule'][$id]['uuid'];

// Set sensible defaults for any empty parameters
if (empty($pconfig['iprep_memcap']))
	$pconfig['iprep_memcap'] = '500';
if (empty($pconfig['iprep_priority']))
	$pconfig['iprep_priority'] = 'whitelist';
if (empty($pconfig['iprep_nested_ip']))
	$pconfig['iprep_nested_ip'] = 'inner';
if (empty($pconfig['iprep_white']))
	$pconfig['iprep_white'] = 'unblack';

if ($_POST['mode'] == 'blist_add' && isset($_POST['iplist'])) {
	$pconfig = $_POST;

	// Test the supplied IP List file to see if it exists
	if (file_exists($_POST['iplist'])) {
		// See if the file is already assigned to the interface
		foreach ($a_nat[$id]['blist_files']['item'] as $f) {
			if ($f == basename($_POST['iplist'])) {
				$input_errors[] = sprintf(gettext("The file %s is already assigned as a blacklist file."), htmlspecialchars($f));
				break;
			}
		}
		if (!$input_errors) {
			$a_nat[$id]['blist_files']['item'][] = basename($_POST['iplist']);
			write_config("Snort pkg: added new blacklist file for IP REPUTATION preprocessor.");
			mark_subsystem_dirty('snort_iprep');
		}
	}
	else
		$input_errors[] = sprintf(gettext("The file '%s' could not be found."), htmlspecialchars($_POST['iplist']));

	$pconfig['blist_files'] = $a_nat[$id]['blist_files'];
	$pconfig['wlist_files'] = $a_nat[$id]['wlist_files'];
}

if ($_POST['mode'] == 'wlist_add' && isset($_POST['iplist'])) {
	$pconfig = $_POST;

	// Test the supplied IP List file to see if it exists
	if (file_exists($_POST['iplist'])) {
		// See if the file is already assigned to the interface
		foreach ($a_nat[$id]['wlist_files']['item'] as $f) {
			if ($f == basename($_POST['iplist'])) {
				$input_errors[] = sprintf(gettext("The file %s is already assigned as a whitelist file."), htmlspecialchars($f));
				break;
			}
		}
		if (!$input_errors) {
			$a_nat[$id]['wlist_files']['item'][] = basename($_POST['iplist']);
			write_config("Snort pkg: added new whitelist file for IP REPUTATION preprocessor.");
			mark_subsystem_dirty('snort_iprep');
		}
	}
	else
		$input_errors[] = sprintf(gettext("The file '%s' could not be found."), htmlspecialchars($_POST['iplist']));

	$pconfig['blist_files'] = $a_nat[$id]['blist_files'];
	$pconfig['wlist_files'] = $a_nat[$id]['wlist_files'];
}

if ($_POST['blist_del'] && is_numericint($_POST['list_id'])) {
	$pconfig = $_POST;
	unset($a_nat[$id]['blist_files']['item'][$_POST['list_id']]);
	write_config("Snort pkg: deleted blacklist file for IP REPUTATION preprocessor.");
	mark_subsystem_dirty('snort_iprep');
	$pconfig['blist_files'] = $a_nat[$id]['blist_files'];
	$pconfig['wlist_files'] = $a_nat[$id]['wlist_files'];
}

if ($_POST['wlist_del'] && is_numericint($_POST['list_id'])) {
	$pconfig = $_POST;
	unset($a_nat[$id]['wlist_files']['item'][$_POST['list_id']]);
	write_config("Snort pkg: deleted whitelist file for IP REPUTATION preprocessor.");
	mark_subsystem_dirty('snort_iprep');
	$pconfig['wlist_files'] = $a_nat[$id]['wlist_files'];
	$pconfig['blist_files'] = $a_nat[$id]['blist_files'];
}

if ($_POST['save'] || $_POST['apply']) {

	$natent = array();
	$natent = $pconfig;

	if (!is_numericint($_POST['iprep_memcap']) || strval($_POST['iprep_memcap']) < 1 || strval($_POST['iprep_memcap']) > 4095)
		$input_errors[] = gettext("The value for Memory Cap must be an integer between 1 and 4095.");

	// if no errors write to conf
	if (!$input_errors) {

		$natent['reputation_preproc'] = $_POST['reputation_preproc'] ? 'on' : 'off';
		$natent['iprep_scan_local'] = $_POST['iprep_scan_local'] ? 'on' : 'off';
		$natent['iprep_memcap'] = $_POST['iprep_memcap'];
		$natent['iprep_priority'] = $_POST['iprep_priority'];
		$natent['iprep_nested_ip'] = $_POST['iprep_nested_ip'];
		$natent['iprep_white'] = $_POST['iprep_white'];

		$a_nat[$id] = $natent;

		write_config("Snort pkg: modified IP REPUTATION preprocessor settings for {$a_nat[$id]['interface']}.");

		// Update the snort conf file for this interface
		$rebuild_rules = false;
		conf_mount_rw();
		snort_generate_conf($a_nat[$id]);
		conf_mount_ro();

		// Soft-restart Snort to live-load new variables
		snort_reload_config($a_nat[$id]);
		$pconfig = $natent;

		// Sync to configured CARP slaves if any are enabled
		snort_sync_on_changes();

		// We have saved changes and done a soft restart, so clear "dirty" flag
		clear_subsystem_dirty('snort_iprep');
	}
	else
		$pconfig = $_POST;
}

$if_friendly = convert_friendly_interface_to_friendly_descr($a_nat[$id]['interface']);
$pgtitle = gettext("Snort: Interface {$if_friendly} IP Reputation Preprocessor");
include_once("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php 
include("fbegin.inc"); 
/* Display Alert message */
if ($input_errors)
	print_input_errors($input_errors);
if ($savemsg)
	print_info_box($savemsg);
?>

<form action="snort_ip_reputation.php" method="post" name="iform" id="iform" >
<input name="id" type="hidden" value="<?=$id;?>" />
<input type="hidden" id="mode" name="mode" value="" />
<input name="iplist" id="iplist" type="hidden" value="" />
<input name="list_id" id="list_id" type="hidden" value="" />

<?php if (is_subsystem_dirty('snort_iprep')): ?><p>
<?php print_info_box_np(gettext("A change has been made to blacklist or whitelist file assignments.") . "<br/>" . gettext("You must apply the changes in order for them to take effect."));?>
<?php endif; ?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tbody>
	<tr>
		<td>
		<?php
		$tab_array = array();
		$tab_array[0] = array(gettext("Snort Interfaces"), true, "/snort/snort_interfaces.php");
		$tab_array[1] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
		$tab_array[2] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
		$tab_array[3] = array(gettext("Alerts"), false, "/snort/snort_alerts.php?instance={$id}");
		$tab_array[4] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
		$tab_array[5] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
		$tab_array[6] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
		$tab_array[7] = array(gettext("IP Lists"), false, "/snort/snort_ip_list_mgmt.php");
		$tab_array[8] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
		$tab_array[9] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
		$tab_array[10] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
		display_top_tabs($tab_array, true);
		echo '</td></tr>';
		echo '<tr><td class="tabnavtbl">';
		$menu_iface=($if_friendly?substr($if_friendly,0,5)." ":"Iface ");
		$tab_array = array();
        	$tab_array[] = array($menu_iface . gettext(" Settings"), false, "/snort/snort_interfaces_edit.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Categories"), false, "/snort/snort_rulesets.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Rules"), false, "/snort/snort_rules.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Variables"), false, "/snort/snort_define_servers.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Preprocs"), false, "/snort/snort_preprocessors.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Barnyard2"), false, "/snort/snort_barnyard.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("IP Rep"), true, "/snort/snort_ip_reputation.php?id={$id}");
		$tab_array[] = array($menu_iface . gettext("Logs"), false, "/snort/snort_interface_logs.php?id={$id}");
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
					<td colspan="2" valign="top" class="listtopic"><?php echo gettext("IP Reputation Preprocessor Configuration"); ?></td>
				</tr>
				<tr>
					<td width="22%" valign='top' class='vncell'><?php echo gettext("Enable"); ?>
					</td>
					<td width="78%" class="vtable"><input name="reputation_preproc" type="checkbox" value="on" <?php if ($pconfig['reputation_preproc'] == "on") echo "checked"; ?>/>
					<?php echo gettext("Use IP Reputation Lists on this interface.  Default is ") . "<strong>" . gettext("Not Checked.") . "</strong>"; ?>
					</td>
				</tr>
				<tr>
					<td valign="top" class="vncell"><?php echo gettext("Memory Cap"); ?></td>
					<td class="vtable"><input name="iprep_memcap" type="text" class="formfld unknown"
					id="http_inspect_memcap" size="9"
					value="<?=htmlspecialchars($pconfig['iprep_memcap']);?>">&nbsp;
					<?php echo gettext("Maximum memory in megabytes (MB) supported for IP Reputation Lists. Default is ") . "<strong>" . 
					gettext("500.") . "</strong><br/>" . gettext("The Minimum value is ") . 
					"<strong>" . gettext("1 MB") . "</strong>" . gettext(" and the Maximum is ") . "<strong>" . 
					gettext("4095 MB.") . "</strong>&nbsp;" . gettext("Enter an integer value between 1 and 4095."); ?><br/>
					</td>
				</tr>
				<tr>
					<td width="22%" valign='top' class='vncell'><?php echo gettext("Scan Local"); ?>
					</td>
					<td width="78%" class="vtable"><input name="iprep_scan_local" type="checkbox" value="on" <?php if ($pconfig['iprep_scan_local'] == "on") echo "checked"; ?>/>
					<?php echo gettext("Scan RFC 1918 addresses on this interface.  Default is ") . "<strong>" . gettext("Not Checked.") . "</strong>"; ?><br/>
					<?php echo gettext("When checked, Snort will inspect addresses in the 10/8, 172.16/12 and 192.168/16 ranges defined in RFC 1918.");?><br/><br/>
					<span class="red"><strong><?=gettext("Hint: ");?></strong></span><?=gettext("if these address ranges are used in your internal network, and this instance ") . 
					gettext("is on an internal interface, this option should usually be enabled (checked).");?>
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell"><?php echo gettext("Nested IP"); ?></td>
					<td width="78%" class="vtable">
					<input name="iprep_nested_ip" type="radio" id="iprep_nested_ip_inner"  
					value="inner" <?php if ($pconfig['iprep_nested_ip'] == 'inner') echo "checked";?>/>
					<?php echo gettext("Inner"); ?>&nbsp;<input name="iprep_nested_ip" type="radio" id="iprep_nested_ip_outer" 
					value="outer" <?php if ($pconfig['iprep_nested_ip'] == 'outer') echo "checked";?>/>
					<?php echo gettext("Outer"); ?>&nbsp;<input name="iprep_nested_ip" type="radio" id="iprep_nested_ip_both" 
					value="both" <?php if ($pconfig['iprep_nested_ip'] == 'both') echo "checked";?>/>
					<?php echo gettext("Both"); ?><br/>
					<?php echo gettext("Specify which IP address to use for whitelist/blacklist matching when there is IP encapsulation. Default is ") . "<strong>" . gettext("Inner") . "</strong>."; ?>
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell"><?php echo gettext("Priority"); ?></td>
					<td width="78%" class="vtable">
					<input name="iprep_priority" type="radio" id="iprep_priority_blacklist"  
					value="blacklist" <?php if ($pconfig['iprep_priority'] == 'blacklist') echo "checked";?>/>
					<?php echo gettext("Blacklist"); ?>&nbsp;<input name="iprep_priority" type="radio" id="iprep_priority" 
					value="whitelist" <?php if ($pconfig['iprep_priority'] == 'whitelist') echo "checked";?>/>
					<?php echo gettext("Whitelist"); ?><br/>
					<?php echo gettext("Specify which list has priority when source/destination is on blacklist while destination/source is on whitelist.") . 
					"<br/>" . gettext("Default is ") . "<strong>" . gettext("Whitelist") . "</strong>."; ?>
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell"><?php echo gettext("Whitelist Meaning"); ?></td>
					<td width="78%" class="vtable">
					<input name="iprep_white" type="radio" id="iprep_white_unblack"  
					value="unblack" <?php if ($pconfig['iprep_white'] == 'unblack') echo "checked";?>/>
					<?php echo gettext("Unblack"); ?>&nbsp;<input name="iprep_white" type="radio" id="iprep_white_trust" 
					value="trust" <?php if ($pconfig['iprep_white'] == 'trust') echo "checked";?>/>
					<?php echo gettext("Trust"); ?><br/>
					<?php echo gettext("Specify the meaning of whitelist. \"Unblack\" unblacks blacklisted IP addresses and routes them for further inspection.  \"Trust\" means the packet bypasses all further Snort detection. ") . 
					gettext("Default is ") . "<strong>" . gettext("Unblack") . "</strong>."; ?>
					</td>
				</tr>
				<tr>
					<td width="22%" valign="top" class="vncell">&nbsp;</td>
					<td width="78%" class="vtable">
					<input name="save" type="submit" class="formbtn" value="Save" title="<?=gettext("Save IP Reputation configuration");?>" />
					&nbsp;&nbsp;<?=gettext("Click to save configuration settings and live-reload the running Snort configuration.");?>
					</td>
				</tr>
				<tr>
					<td colspan="2" valign="top" class="listtopic"><?php echo gettext("Assign Blacklists/Whitelists to IP Reputation Preprocessor"); ?></td>
				</tr>
				<tr>
					<td width="22%" valign='top' class='vncell'><?php echo gettext("Blacklist Files"); ?>
					</td>
					<td width="78%" class="vtable">
					<!-- blist_chooser -->
					<div id="blistChooser" name="blistChooser" style="display:none; border:1px dashed gray; width:98%;"></div>
						<table width="95%" border="0" cellpadding="2" cellspacing="0">
							<colgroup>
								<col style="text-align:left;">
								<col style="width: 30%; text-align:left;">
								<col style="width: 17px;">
							</colgroup>
							<thead>
								<tr>
									<th class="listhdrr"><?php echo gettext("Blacklist Filename"); ?></th>
									<th class="listhdrr"><?php echo gettext("Modification Time"); ?></th>
									<th class="list" align="left" valign="middle"><img style="cursor:pointer;" name="blist_add" id="blist_add" 
									src="../themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" 
									height="17" border="0" title="<?php echo gettext('Assign a blacklist file');?>"/></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach($pconfig['blist_files']['item'] as $k => $f):
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
									<td class="list"><input type="image" name="blist_del[]" id="blist_del[]" onClick="document.getElementById('list_id').value='<?=$k;?>';" 
									src="../themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" 
									border="0" title="<?php echo gettext('Remove this blacklist file');?>"/></td>
								</tr>
							<?php endforeach; ?>
								<tr>
									<td colspan="2" class="vexpl"><span class="red"><strong><?=gettext("Note: ");?></strong></span>
									<?=gettext("changes to blacklist assignments are immediately saved.");?></td>
								</tr>
							</tbody>
						</table>
					</td>
				</tr>
				<tr>
					<td width="22%" valign='top' class='vncell'><?php echo gettext("Whitelist Files"); ?>
					</td>
					<td width="78%" class="vtable">
					<!-- wlist_chooser -->
					<div id="wlistChooser" name="wlistChooser" style="display:none; border:1px dashed gray; width:98%;"></div>
						<table width="95%" border="0" cellpadding="2" cellspacing="0">
							<colgroup>
								<col style="text-align:left;">
								<col style="width: 30%; text-align:left;">
								<col style="width: 17px;">
							</colgroup>
							<thead>
								<tr>
									<th class="listhdrr"><?php echo gettext("Whitelist Filename"); ?></th>
									<th class="listhdrr"><?php echo gettext("Modification Time"); ?></th>
									<th class="list" align="left" valign="middle"><img style="cursor:pointer;" name="wlist_add" id="wlist_add" 
									src="../themes/<?= $g['theme']; ?>/images/icons/icon_plus.gif" width="17" height="17" 
									border="0" title="<?php echo gettext('Assign a whitelist file');?>"/></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach($pconfig['wlist_files']['item'] as $k => $f):
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
									<td class="list"><input type="image" name="wlist_del[]" id="wlist_del[]" onClick="document.getElementById('list_id').value='<?=$k;?>';" 
									src="../themes/<?= $g['theme']; ?>/images/icons/icon_x.gif" width="17" height="17" 
									border="0" title="<?php echo gettext('Remove this whitelist file');?>"/></td>
								</tr>
							<?php endforeach; ?>
								<tr>
									<td colspan="2" class="vexpl"><span class="red"><strong><?=gettext("Note: ");?></strong></span>
									<?=gettext("changes to whitelist assignments are immediately saved.");?></td>
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
			"blist_add", "click",
			function() {
				Effect.Appear("blistChooser", { duration: 0.25 });
				blistChoose();
			}
		);

		Event.observe(
			"wlist_add", "click",
			function() {
				Effect.Appear("wlistChooser", { duration: 0.25 });
				wlistChoose();
			}
		);
	}
);

function blistChoose() {
	Effect.Appear("blistChooser", { duration: 0.25 });
	if($("fbCurrentDir"))
		$("fbCurrentDir").innerHTML = "Loading ...";

	new Ajax.Request(
		"/snort/snort_iprep_list_browser.php?container=blistChooser&target=iplist&val=" + new Date().getTime(),
		{ method: "get", onComplete: blistComplete }
	);
}

function wlistChoose() {
	Effect.Appear("wlistChooser", { duration: 0.25 });
	if($("fbCurrentDir"))
		$("fbCurrentDir").innerHTML = "Loading ...";

	new Ajax.Request(
		"/snort/snort_iprep_list_browser.php?container=wlistChooser&target=iplist&val=" + new Date().getTime(),
		{ method: "get", onComplete: wlistComplete }
	);
}

function blistComplete(req) {
	$("blistChooser").innerHTML = req.responseText;

	var actions = {
		fbClose: function() { $("blistChooser").hide();                    },
		fbFile:  function() { $("iplist").value = this.id;
				      $("mode").value = 'blist_add';
				      document.getElementById('iform').submit();
				    }
	}

	for(var type in actions) {
		var elem = $("blistChooser");
		var list = elem.getElementsByClassName(type);
		for (var i=0; i<list.length; i++) {
			Event.observe(list[i], "click", actions[type]);
			list[i].style.cursor = "pointer";
		}
	}
}

function wlistComplete(req) {
	$("wlistChooser").innerHTML = req.responseText;

	var actions = {
		fbClose: function() { $("wlistChooser").hide();                    },
		fbFile:  function() { $("iplist").value = this.id;
				      $("mode").value = 'wlist_add';
				      document.getElementById('iform').submit();
				    }
	}

	for(var type in actions) {
		var elem = $("wlistChooser");
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
