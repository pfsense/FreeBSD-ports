<?php
/*
* suricata_ip_reputation.php
*
*  Copyright (c)  2004-2016  Electric Sheep Fencing, LLC. All rights reserved.
*
*  Redistribution and use in source and binary forms, with or without modification,
*  are permitted provided that the following conditions are met:
*
*  1. Redistributions of source code must retain the above copyright notice,
*      this list of conditions and the following disclaimer.
*
*  2. Redistributions in binary form must reproduce the above copyright
*      notice, this list of conditions and the following disclaimer in
*      the documentation and/or other materials provided with the
*      distribution.
*
*  3. All advertising materials mentioning features or use of this software
*      must display the following acknowledgment:
*      "This product includes software developed by the pfSense Project
*       for use in the pfSense software distribution. (http://www.pfsense.org/).
*
*  4. The names "pfSense" and "pfSense Project" must not be used to
*       endorse or promote products derived from this software without
*       prior written permission. For written permission, please contact
*       coreteam@pfsense.org.
*
*  5. Products derived from this software may not be called "pfSense"
*      nor may "pfSense" appear in their names without prior written
*      permission of the Electric Sheep Fencing, LLC.
*
*  6. Redistributions of any form whatsoever must retain the following
*      acknowledgment:
*
*  "This product includes software developed by the pfSense Project
*  for use in the pfSense software distribution (http://www.pfsense.org/).
*
*  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
*  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
*  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
*  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
*  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
*  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
*  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
*  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
*  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
*  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
*  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
*  OF THE POSSIBILITY OF SUCH DAMAGE.
*
*
* Portions of this code are based on original work done for the Snort package for pfSense by the following contributors:
*
* Copyright (C) 2003-2004 Manuel Kasper
* Copyright (C) 2005 Bill Marquette
* Copyright (C) 2006 Scott Ullrich (copyright assigned to ESF)
* Copyright (C) 2009 Robert Zelaya Sr. Developer
* Copyright (C) 2012 Ermal Luci  (copyright assigned to ESF)
* Copyright (C) 2014 Bill Meeks
*
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

if ($_POST['save']) {

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

		$savemsg = gettext("IP reputation system changes have been saved");
	}
}

$if_friendly = convert_friendly_interface_to_friendly_descr($a_nat[$id]['interface']);
$pgtitle = array(gettext("Suricata"), $if_friendly, gettext("IP Reputation Preprocessor"));
include_once("head.inc");

if ($g['platform'] == "nanobsd") {
	$input_errors[] = gettext("IP Reputation is not supported on NanoBSD installs");
}

/* Display Alert message */
if ($input_errors)
	print_input_errors($input_errors);

if ($savemsg)
	print_info_box($savemsg, 'success');

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

$form = new Form();

$section = new Form_Section('IP Reputation Configuration');

$section->addInput(new Form_Checkbox(
	'enable_iprep',
	'Enable',
	'Use IP Reputation Lists on this interface. Default is NOT Checked.',
	$pconfig['enable_iprep'],
	'on'
));

$section->addInput(new Form_Input(
	'host_memcap',
	'Host Memcap',
	'number',
	$pconfig['host_memcap'],
	['min' => '1048576']
))->setHelp('Host table memory cap in bytes. Default is 16777216 (16 MB). Min value is 1048576 (1 MB)');

$section->addInput(new Form_Input(
	'host_hash_size',
	'Host Hash Size',
	'number',
	$pconfig['host_hash_size'],
	['min' => '1024']
))->setHelp('	Host Hash Size in bytes. Default is 4096. Min value is 1024');

$section->addInput(new Form_Input(
	'host_prealloc',
	'Host Preallocations',
	'number',
	$pconfig['host_prealloc'],
	['min' => '10']
))->setHelp('Number of Host Table entries to preallocate. Default is 1000. Min value is 10<br /> ' .
			'Increasing this value may slightly improve performance when using large IP Reputation Lists');

$form->add($section);

$form->addGlobal(new Form_Input('id', null, 'hidden', $id));
$form->addGlobal(new Form_Input('mode', null, 'hidden'));
$form->addGlobal(new Form_Input('iplist', null, 'hidden'));
$form->addGlobal(new Form_Input('list_id', null, 'hidden'));

print($form);
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Assign Categories File")?></h2></div>
	<div class="panel-body">
		<!-- iprep_catlist_chooser -->
		<div id="iprep_catlistChooser" name="iprep_catlistChooser" style="display:none; border:1px dashed gray; width:98%;" class="table-responsive"></div>
		<table class="table table-hover table-condensed">
			<thead>
				<tr>
					<th class="col-sm-6"><?=gettext("Categories Filename")?></th>
					<th class="col-sm-4"><?=gettext("Modification Time")?></th>
					<th><?=gettext("Action")?></th>
				</tr>
			</thead>
			<tbody>
			<?php if (!empty($pconfig['iprep_catlist'])) :
					if (!file_exists("{$iprep_path}{$pconfig['iprep_catlist']}")) {
						$filedate = gettext("Unknown -- file missing");
					}
					else
						$filedate = date('M-d Y   g:i a', filemtime("{$iprep_path}{$pconfig['iprep_catlist']}"));
			 ?>
				<tr>
					<td><?=htmlspecialchars($pconfig['iprep_catlist']);?></td>
					<td> <?=$filedate;?></td>
					<td><button class="btn btn-sm btn-danger" name="iprep_catlist_delX" id="iprep_catlist_delX" title="<?=gettext('Remove this Categories file');?>">
					<i class="fa fa-times icon-embed-btn"></i><?=gettext("Delete")?></button>
					</td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
<nav class="action-buttons">
	<button class="btn btn-sm btn-success" name="iprep_catlist_add" id="iprep_catlist_add"  title="<?=gettext('Assign a Categories file');?>">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</button>
</nav>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Assign IP Reputation Lists")?></h2></div>
	<div class="panel-body ">
		<!-- iprep_catlist_chooser -->
		<div id="iplistChooser" name="iplistChooser" style="display:none; border:1px dashed gray; width:98%;" class="table-responsive"></div>
		<table class="table table-hover table-condensed">
			<!-- iplist_chooser -->

				<thead>
					<tr>
						<th class="col-sm-6"><?php echo gettext("IP Reputation List Filename"); ?></th>
						<th class="col-sm-4"><?php echo gettext("Modification Time"); ?></th>
						<th><?=gettext("Action")?></th>
					</tr>
				</thead>
				<tbody>
<?php
				if (is_array($pconfig['iplist_files']['item'])) :
					foreach($pconfig['iplist_files']['item'] as $k => $f) :
						if (!file_exists("{$iprep_path}{$f}")) {
							$filedate = gettext("Unknown -- file missing");
						}
						else
							$filedate = date('M-d Y   g:i a', filemtime("{$iprep_path}{$f}"));
				 ?>
					<tr>
						<td><?=htmlspecialchars($f);?></td>
						<td><?=$filedate;?></td>
						<td>
							<button class="btn btn-sm btn-danger" name="iplist_delX[]" id="iplist_delX[]" value="<?=$k;?>" title="<?php echo gettext('Remove this IP reputation file');?>">
							<i class="fa fa-times icon-embed-btn"></i><?=gettext("Delete")?></button>
						</td>
					</tr>
<?php 					endforeach;
				endif;
?>
				</tbody>
		</table>
	</div>
</div>

<nav class="action-buttons">
	<button class="btn btn-sm btn-success" name="iplist_add" id="iplist_add" title="<?php echo gettext('Assign a whitelist file');?>">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</button>
</nav>


<?php if ($g['platform'] != "nanobsd") : ?>

<script type="text/javascript">
//<![CDATA[
events.push(function() {

// Adding a new reputation category file
$('#iprep_catlist_add').click(function() {
	iprep_catlistChoose();
});

// Adding a new IP reputation file
$('#iplist_add').click(function() {
	iplistChoose();
});

// Delete a reputation file
$('[id^=iplist_delX]').click(function() {
	$('#list_id').val($(this).val());
	$('<input name="iplist_del[]" id="iplist_del[]" type="hidden" value="0"/>').appendTo($(form));
	$(form).submit();
});

// Delete a reputation category file
$('#iprep_catlist_delX').click(function() {
	$('#list_id').val('0');
	$('<input name="iprep_catlist_del[]" id="iprep_catlist_del[]" type="hidden" value="0"/>').appendTo($(form));
	$(form).submit();
});

// Fetch category list information via AJAX
function iprep_catlistChoose() {
	if($("fbCurrentDir")) {
		$("#iprep_catlistChooser").html("Loading ...");
		$("#iprep_catlistChooser").show();
	}

	$.ajax(
		"/suricata/suricata_iprep_list_browser.php?container=iprep_catlistChooser&target=iplist&val=" + new Date().getTime(),
		{
			type: 'get',
			complete: iprep_catlistComplete
		}
	);

}

// Fetch IP list information via AJAX
function iplistChoose() {
	if($("fbCurrentDir"))
		$("#iplistChooser").html("Loading ...");
		$("#iplistChooser").show();

	$.ajax(
		"/suricata/suricata_iprep_list_browser.php?container=iplistChooser&target=iplist&val=" + new Date().getTime(),
		{
			type: "get",
			complete: iplistComplete
		}
	);
}

// Update the category display, adding the action handlers to each entry
function iprep_catlistComplete(req) {
	$("#iprep_catlistChooser").html(req.responseText);

	var actions = {
		fbClose: function() {
			$("#iprep_catlistChooser").hide();
		},

		fbFile:  function() {
			$("#iprep_catlist").val(this.id);
			$("#mode").val('iprep_catlist_add');
			$(form).submit();
		}
	}

	for(var type in actions) {
		$("#iprep_catlistChooser ." + type).each(
			function() {
				$(this).click(actions[type]);
				$(this).css("cursor","pointer");
			}
		);
	}
}

// Update the IP list display, adding the action handlers to each entry
function iplistComplete(req) {
	$("#iplistChooser").html(req.responseText);

	var actions = {
		fbClose: function() {
			$("#iplistChooser").hide();
		},

		fbFile:  function() {
			$("#iplist").val(this.id);
		    $("#mode").val('iplist_add');
		    $(form).submit();
		 }
	}

	for(var type in actions) {
		$("#iplistChooser ." + type).each(
			function() {
				$(this).click(actions[type]);
				$(this).css("cursor","pointer");
			}
		);
	}
}
});
//]]>
</script>
<?php endif; ?>

<?php include("foot.inc");
?>

