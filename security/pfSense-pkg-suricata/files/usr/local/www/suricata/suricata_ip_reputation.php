<?php
/*
 * suricata_ip_reputation.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2023 Bill Meeks
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

require_once("guiconfig.inc");
require_once("/usr/local/pkg/suricata/suricata.inc");

global $g, $rebuild_rules;
$iprep_path = SURICATA_IPREP_PATH;

if (isset($_POST['id']) && is_numericint($_POST['id']))
	$id = $_POST['id'];
elseif (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

if (is_null($id)) {
	header("Location: /suricata/suricata_interfaces.php");
	exit;
}

$a_nat = config_get_path("installedpackages/suricata/rule/{$id}", []);

// If doing a postback, used typed values, else load from stored config
if (!empty($_POST)) {
	$pconfig = $_POST;
}
else {
	$pconfig = $a_nat;
}

if ($_POST['mode'] == 'iprep_catlist_add' && isset($_POST['iplist'])) {
	$pconfig = $_POST;

	// Test the supplied IP List file to see if it exists
	if (file_exists($_POST['iplist'])) {
		if (!$input_errors) {
			$a_nat['iprep_catlist'] = basename($_POST['iplist']);
			config_set_path("installedpackages/suricata/rule/{$id}", $a_nat);
			write_config("Suricata pkg: added new IP Rep Categories file for IP REPUTATION preprocessor.");
			mark_subsystem_dirty('suricata_iprep');
		}
	}
	else
		$input_errors[] = gettext("The file '{$_POST['iplist']}' could not be found.");

	$pconfig['iprep_catlist'] = $a_nat['iprep_catlist'];
	$pconfig['iplist_files'] = $a_nat['iplist_files'];
}

if ($_POST['mode'] == 'iplist_add' && isset($_POST['iplist'])) {
	$pconfig = $_POST;

	// Test the supplied IP List file to see if it exists
	if (file_exists($_POST['iplist'])) {
		// See if the file is already assigned to the interface
		foreach (array_get_path($a_nat, 'iplist_files/item', []) as $f) {
			if ($f == basename($_POST['iplist'])) {
				$input_errors[] = gettext("The file {$f} is already assigned as a whitelist file.");
				break;
			}
		}
		if (!$input_errors) {
			if (!is_array($a_nat['iplist_files'])){
				$a_nat['iplist_files'] = array( "item" => array() );
			}

			$a_nat['iplist_files']['item'][] = basename($_POST['iplist']);
			config_set_path("installedpackages/suricata/rule/{$id}", $a_nat);
			write_config("Suricata pkg: added new whitelist file for IP REPUTATION preprocessor.");
			mark_subsystem_dirty('suricata_iprep');
		}
	}
	else
		$input_errors[] = gettext("The file '{$_POST['iplist']}' could not be found.");

	$pconfig['iprep_catlist'] = $a_nat['iprep_catlist'];
	$pconfig['iplist_files'] = $a_nat['iplist_files'];
}

if ($_POST['iprep_catlist_del']) {
	$pconfig = $_POST;
	unset($a_nat['iprep_catlist']);
	config_set_path("installedpackages/suricata/rule/{$id}", $a_nat);
	write_config("Suricata pkg: deleted blacklist file for IP REPUTATION preprocessor.");
	mark_subsystem_dirty('suricata_iprep');
	$pconfig['iprep_catlist'] = $a_nat['iprep_catlist'];
	$pconfig['iplist_files'] = $a_nat['iplist_files'];
}

if ($_POST['iplist_del'] && is_numericint($_POST['list_id'])) {
	$pconfig = $_POST;
	unset($a_nat['iplist_files']['item'][$_POST['list_id']]);
	config_set_path("installedpackages/suricata/rule/{$id}", $a_nat);
	write_config("Suricata pkg: deleted whitelist file for IP REPUTATION preprocessor.");
	mark_subsystem_dirty('suricata_iprep');
	$pconfig['iplist_files'] = $a_nat['iplist_files'];
	$pconfig['iprep_catlist'] = $a_nat['iprep_catlist'];
}

if ($_POST['save']) {

	$pconfig['iprep_catlist'] = $a_nat['iprep_catlist'];
	$pconfig['iplist_files'] = $a_nat['iplist_files'];

	// Validate HOST TABLE values
	if ($_POST['host_memcap'] < 1000000 || !is_numericint($_POST['host_memcap']))
		$input_errors[] = gettext("The value for 'Host Memcap' must be a numeric integer greater than 1MB (1,048,576!");
	if ($_POST['host_hash_size'] < 1024 || !is_numericint($_POST['host_hash_size']))
		$input_errors[] = gettext("The value for 'Host Hash Size' must be a numeric integer greater than 1024!");
	if ($_POST['host_prealloc'] < 10 || !is_numericint($_POST['host_prealloc']))
		$input_errors[] = gettext("The value for 'Host Preallocations' must be a numeric integer greater than 10!");

	// Validate CATEGORIES FILE
	if ($_POST['enable_iprep'] == 'on') {
		if (empty($a_nat['iprep_catlist']))
			$input_errors[] = gettext("Assignment of a 'Categories File' is required when IP Reputation is enabled!");
	}

	// If no errors write to conf
	if (!$input_errors) {
		$a_nat['enable_iprep'] = $_POST['enable_iprep'] ? 'on' : 'off';
		$a_nat['host_memcap'] = str_replace(",", "", $_POST['host_memcap']);
		$a_nat['host_hash_size'] = str_replace(",", "", $_POST['host_hash_size']);
		$a_nat['host_prealloc'] = str_replace(",", "", $_POST['host_prealloc']);

		config_set_path("installedpackages/suricata/rule/{$id}", $a_nat);
		write_config("Suricata pkg: modified IP REPUTATION preprocessor settings for {$a_nat['interface']}.");

		// Update the suricata conf file for this interface
		$rebuild_rules = false;
		suricata_generate_yaml($a_nat);

		// Soft-restart Suricata to live-load new variables
		suricata_reload_config($a_nat);

		// Sync to configured CARP slaves if any are enabled
		suricata_sync_on_changes();

		$savemsg = gettext("IP reputation system changes have been saved");
	}
}

$if_friendly = convert_friendly_interface_to_friendly_descr($a_nat['interface']);
$pglinks = array("", "/suricata/suricata_interfaces.php", "/suricata/suricata_interfaces_edit.php?id={$id}", "@self");
$pgtitle = array("Services", "Suricata", "Interface Settings", "{$if_friendly} - IP Reputation");
include_once("head.inc");

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
$tab_array[] = array(gettext("Files"), false, "/suricata/suricata_files.php?instance={$id}");
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
$tab_array[] = array($menu_iface . gettext("IP Rep"), true, "/suricata/suricata_ip_reputation.php?id={$id}");
display_top_tabs($tab_array, true);

$form = new Form();

$section = new Form_Section('IP Reputation Configuration');

$section->addInput(new Form_Checkbox(
	'enable_iprep',
	'Enable',
	'Use IP Reputation Lists on this interface. Default is NOT Checked.',
	$pconfig['enable_iprep'] == 'on' ? true:false,
	'on'
));

$section->addInput(new Form_Input(
	'host_memcap',
	'Host Memcap',
	'number',
	$pconfig['host_memcap'],
	['min' => '1048576']
))->setHelp('Host table memory cap in bytes. Default is 33554432 (32 MB). Min value is 1048576 (1 MB)');

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

print $form;
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
					<i class="fa-solid fa-times icon-embed-btn"></i><?=gettext("Delete")?></button>
					</td>
				</tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
<nav class="action-buttons">
	<button class="btn btn-sm btn-success" name="iprep_catlist_add" id="iprep_catlist_add"  title="<?=gettext('Assign a Categories file');?>">
		<i class="fa-solid fa-plus icon-embed-btn"></i>
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
				foreach(array_get_path($pconfig, 'iplist_files/item', []) as $k => $f) :
					if (!file_exists("{$iprep_path}{$f}")) {
						$filedate = gettext("Unknown -- file missing");
					} else {
						$filedate = date('M-d Y   g:i a', filemtime("{$iprep_path}{$f}"));
					}
?>
					<tr>
						<td><?=htmlspecialchars($f);?></td>
						<td><?=$filedate;?></td>
						<td>
							<button class="btn btn-sm btn-danger" name="iplist_delX[]" id="iplist_delX[]" value="<?=$k;?>" title="<?php echo gettext('Remove this IP reputation file');?>">
							<i class="fa-solid fa-times icon-embed-btn"></i><?=gettext("Delete")?></button>
						</td>
					</tr>
<?php 			endforeach;
?>
				</tbody>
		</table>
	</div>
</div>

<nav class="action-buttons">
	<button class="btn btn-sm btn-success" name="iplist_add" id="iplist_add" title="<?php echo gettext('Assign a whitelist file');?>">
		<i class="fa-solid fa-plus icon-embed-btn"></i>
		<?=gettext("Add")?>
	</button>
</nav>

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

<?php include("foot.inc");
?>

