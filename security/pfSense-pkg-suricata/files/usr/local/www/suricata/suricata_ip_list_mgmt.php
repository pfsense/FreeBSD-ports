<?php
/*
 * suricata_ip_list_mgmt.php
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

global $g;

// Hard-code the path where IP Lists are stored
// and disregard any user-supplied path element.
$iprep_path = SURICATA_IPREP_PATH;

// Set default to not show IP List editor controls
$iplist_edit_style = "display: none;";

function suricata_is_iplist_active($iplist) {

	/***************************************************
	 * This function checks all configured Suricata	   *
	 * interfaces to see if the passed IP List is used *
	 * as a whitelist or blacklist by an interface.	   *
	 *                                                 *
	 * Returns: TRUE  if IP List is in use             *
	 *          FALSE if IP List is not in use         *
	 ***************************************************/

	foreach (config_get_path('installedpackages/suricata/rule', []) as $rule) {
		foreach (array_get_path($rule, 'iplist_files/item', []) as $file) {
			if ($file == $iplist)
				return TRUE;
		}
	}
	return FALSE;
}

// If doing a postback, used typed values, else load from stored config
if (!empty($_POST)) {
	$pconfig = $_POST;
}
else {
	$pconfig['et_iqrisk_enable'] = config_get_path('installedpackages/suricata/config/0/et_iqrisk_enable');
	$pconfig['iqrisk_code'] = htmlentities(config_get_path('installedpackages/suricata/config/0/iqrisk_code'));
}

// Validate IQRisk settings if enabled and saving them
if ($_POST['save']) {
	if ($pconfig['et_iqrisk_enable'] == 'on' && empty($pconfig['iqrisk_code']))
		$input_errors[] = gettext("You must provide a valid IQRisk subscription code when IQRisk downloads are enabled!");

	if (!$input_errors) {
		config_set_path('installedpackages/suricata/config/0/et_iqrisk_enable', $_POST['et_iqrisk_enable'] ? 'on' : 'off');
		config_set_path('installedpackages/suricata/config/0/iqrisk_code', trim(html_entity_decode($_POST['iqrisk_code'])));
		write_config("Suricata pkg: modified IP Lists settings.");

		/* Toggle cron task for ET IQRisk updates if setting was changed */
		if (config_get_path('installedpackages/suricata/config/0/et_iqrisk_enable') == 'on' && !suricata_cron_job_exists("/usr/local/pkg/suricata/suricata_etiqrisk_update.php")) {
			install_cron_job("/usr/bin/nice -n20 /usr/local/bin/php-cgi -f /usr/local/pkg/suricata/suricata_etiqrisk_update.php", TRUE, 0, "*/6", "*", "*", "*", "root");
		}
		elseif (config_get_path('installedpackages/suricata/config/0/et_iqrisk_enable') == 'off' && suricata_cron_job_exists("/usr/local/pkg/suricata/suricata_etiqrisk_update.php"))
			install_cron_job("/usr/local/pkg/suricata/suricata_etiqrisk_update.php", FALSE);

		/* Peform a manual ET IQRisk file check/download */
		if (config_get_path('installedpackages/suricata/config/0/et_iqrisk_enable') == 'on')
			include("/usr/local/pkg/suricata/suricata_etiqrisk_update.php");
	}
}

if (isset($_POST['upload'])) {
	if ($_FILES["iprep_fileup"]["error"] == UPLOAD_ERR_OK) {
		$tmp_name = $_FILES["iprep_fileup"]["tmp_name"];
		$name = $_FILES["iprep_fileup"]["name"];
		move_uploaded_file($tmp_name, "{$iprep_path}{$name}");
	}
	else
		$input_errors[] = gettext("Failed to upload file {$_FILES["iprep_fileup"]["name"]}");
}

if (isset($_POST['iplist_action']) && isset($_POST['iplist_fname'])) {
	switch ($_POST['iplist_action']) {
		case 'delete':
			if (!suricata_is_iplist_active($_POST['iplist_fname']))
				unlink_if_exists("{$iprep_path}{$_POST['iplist_fname']}");
			else
				$input_errors[] = gettext("This IP List is currently assigned as a Whitelist or Blackist for an interface and cannot be deleted.");
			break;

		case 'edit':
			$file = $iprep_path . basename($_POST['iplist_fname']);
			$data = file_get_contents($file);
			if ($data !== FALSE) {
				$iplist_data = htmlspecialchars($data);
				$iplist_edit_style = "display: table-row-group;";
				$iplist_name = basename($_POST['iplist_fname']);
				unset($data);
			}
			else {
				$input_errors[] = gettext("An error occurred reading the file.");
			}
			break;

		default:
	}
}

if (isset($_POST['iplist_edit_save']) && isset($_POST['iplist_data'])) {
	if (strlen(basename($_POST['iplist_name'])) > 0) {
		$file = $iprep_path . basename($_POST['iplist_name']);
		$data = str_replace("\r\n", "\n", $_POST['iplist_data']);
		file_put_contents($file, $data);
		unset($data);
	}
	else {
		$input_errors[] = gettext("You must provide a valid filename for the IP List.");
		$iplist_edit_style = "display: table-row-group;";
	}
}

// Get all files in the IP Lists sub-directory as an array
// Leave this as the last thing before spewing the page HTML
// so we can pick up any changes made to files in code above.
$ipfiles = return_dir_as_array($iprep_path);

$pglinks = array("", "/suricata/suricata_interfaces.php", "@self");
$pgtitle = array("Services", "Suricata", "IP Lists Management");

include_once("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg) {
	print_info_box($savemsg);
}

$tab_array = array();
$tab_array[] = array(gettext("Interfaces"), false, "/suricata/suricata_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/suricata/suricata_global.php");
$tab_array[] = array(gettext("Updates"), false, "/suricata/suricata_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/suricata/suricata_alerts.php?instance={$id}");
$tab_array[] = array(gettext("Blocks"), false, "/suricata/suricata_blocked.php");
$tab_array[] = array(gettext("Files"), false, "/suricata/suricata_files.php");
$tab_array[] = array(gettext("Pass Lists"), false, "/suricata/suricata_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/suricata/suricata_suppress.php");
$tab_array[] = array(gettext("Logs View"), false, "/suricata/suricata_logs_browser.php?instance={$id}");
$tab_array[] = array(gettext("Logs Mgmt"), false, "/suricata/suricata_logs_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/suricata/suricata_sid_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=suricata/suricata_sync.xml");
$tab_array[] = array(gettext("IP Lists"), true, "/suricata/suricata_ip_list_mgmt.php");
display_top_tabs($tab_array, true);
?>

<div id="container">

<?php

$form = new Form;
$section = new Form_Section('IP Reputation List Management');
$section->addInput(new Form_Checkbox(
	'et_iqrisk_enable',
	'Emerging Threats IQRisk Settings Enable',
	'Checking this box enables auto-download of IQRisk List updates with a valid subscription code.',
	$pconfig['et_iqrisk_enable'] == 'on' ? true:false,
	'on'
))->setHelp('IQRisk IP lists will auto-update nightly at midnight. Visit <a href="https://www.proofpoint.com/us/products/et-intelligence" target="_blank">https://www.proofpoint.com/us/products/et-intelligence</a> for more information or to purchase a subscription.');
$section->addInput(new Form_Input(
	'iqrisk_code',
	'IQRisk Subscription Configuration Code',
	'text',
	$pconfig['iqrisk_code']
))->setHelp('Obtain an Emerging Threats IQRisk List subscription code and paste it here.');
$form->add($section);
print $form;
?>

	<form action="/suricata/suricata_ip_list_mgmt.php" enctype="multipart/form-data" method="post" name="iform" id="iform">
	<input type="hidden" name="MAX_FILE_SIZE" value="100000000" />
	<input type="hidden" name="iplist_fname" id="iplist_fname" value=""/>
	<input type="hidden" name="iplist_action" id="iplist_action" value=""/>
	<div class="panel panel-default">
		<div class="panel-heading"><h2 class="panel-title"><?=gettext("IP Reputation List Management")?></h2></div>
		<div class="panel-body">
			<div class="table-responsive">
				<table class="table table-striped table-hover table-condensed">
					<thead>
						<tr>
							<th><?=gettext("IP List File Name"); ?></th>
							<th><?=gettext("Last Modified Time"); ?></th>
							<th><?=gettext("File Size"); ?></th>
							<th><?=gettext("Actions"); ?></th>
						</tr>
					</thead>
				<?php foreach ($ipfiles as $file):
					if (substr(strrchr($file, "."), 1) == "md5")
						continue; ?>
					<tr>
						<td><?=gettext($file); ?></td>
						<td><?=date('M-d Y g:i a', filemtime("{$iprep_path}{$file}")); ?></td>
						<td><?=format_bytes(filesize("{$iprep_path}{$file}")); ?></td>
						<td>
							<a href="#" class="fa-solid fa-pencil icon-primary" onClick="suricata_iplist_action('edit', '<?=addslashes($file);?>');" title="<?=gettext('Edit this IP List');?>"></a>
							<a href="#" class="fa-solid fa-trash-can icon-primary no-confirm" onClick="suricata_iplist_action('delete', '<?=addslashes($file);?>');" title="<?=gettext('Delete this IP List');?>"></a>
						</td>
					</tr>
				<?php endforeach; ?>
				</table>
			</div>
			<div class="table-responsive">
				<table class="table table-condensed">
					<tbody id="iplist_editor" style="<?=$iplist_edit_style?>">
					<tr>
						<td colspan="4">&nbsp;</td>
					</tr>
					<tr>
						<td colspan="4">
							<strong><?=gettext("File Name: ")?></strong>
							<input type="text" size="45" class="formfld file" id="iplist_name" name="iplist_name" value="<?=$iplist_name?>" />
							<input type="submit" class="btn btn-success btn-sm" id="iplist_edit_save" name="iplist_edit_save" value="<?=gettext(" Save ")?>" title="<?=gettext("Save changes and close editor")?>" />
							<input type="button" class="btn btn-danger btn-sm" id="cancel" name="cancel" value="<?=gettext("Cancel")?>" onClick="document.getElementById('iplist_editor').style.display='none';" title="<?=gettext("Abandon changes and quit editor")?>" />
						</td>
					</tr>
					<tr>
						<td colspan="4">&nbsp;</td>
					</tr>
					<tr>
						<td colspan="4">
							<textarea wrap="off" cols="80" rows="20" name="iplist_data" id="iplist_data" style="width:95%; height:100%;"><?=$iplist_data?></textarea>
						</td>
					</tr>
					</tbody>
					<tbody id="uploader" style="display: none;">
						<tr>
							<td colspan="4">&nbsp;</td>
						</tr>
						<tr>
							<td colspan="4">
								<p><?=gettext("Select a file to import, and then click 'Upload' or click 'Close' to quit."); ?></p>
								<input type="file" name="iprep_fileup" id="iprep_fileup" class="formfld file" size="50" /><br />
								<input type="submit" class="btn btn-success btn-sm" name="upload" id="upload" value="<?=gettext("Upload")?>" title="<?=gettext("Upload selected IP list to firewall")?>"/>
								<input type="button" class="btn btn-danger btn-sm" value="<?=gettext("Close")?>" onClick="document.getElementById('uploader').style.display='none';"/>
							</td>
						</tr>
					</tbody>
					<tr>
						<td colspan="4" class="text-right">
							<button type="button" class="btn btn-success btn-sm" title="<?=gettext('Create a new IP List');?>" onclick="document.getElementById('iplist_data').value=''; document.getElementById('iplist_name').value=''; document.getElementById('iplist_editor').style.display='table-row-group'; document.getElementById('iplist_name').focus();">
								<i class="fa-solid fa-plus icon-embed-btn"></i>
								<?=gettext(' Add');?>
							</button>
							<button type="button" class="btn btn-info btn-sm" title="<?=gettext('Upload IP List file');?>" onclick="document.getElementById('uploader').style.display='table-row-group';">
								<i class="fa-solid fa-upload icon-embed-btn"></i>
								<?=gettext(' Upload');?>
							</button>
						</td>
					</tr>
				</table>
			</div>
		</div>
	</div>
</form>

<div class="infoblock">
	<div class="alert alert-info clearfix" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		<div class="pull-left">
			<div class="row">
				<div class="col-md-12">
					<p>
						<ol>
							<li>A Categories file is required and contains CSV fields for Category Number, Short Name and Description per line.M</li>
							<li>IP Lists are CSV format text files with an IP address, category code and reputation score per line.M</li>
							<li>IP Lists are stored as local files on the firewall and their contents are not saved as part of the firewall configuration file.M</li>
							<li>Visit <a href="https://redmine.openinfosecfoundation.org/projects/suricata/wiki/IPReputationFormat" target="_blank">https://redmine.openinfosecfoundation.org/projects/suricata/wiki/IPReputationFormat</a> for IP Reputation file formats.M</li>
						</ol>
					</p>
					<p>
						Click on the <i class="fa-solid fa-lg fa-plus" alt="Add Icon"></i> icon to open the editor window to create a new IP List.<br/>
						Click on the <i class="fa-solid fa-lg fa-upload" alt="Upload Icon"></i> icon to upload a new IP List file from your local machine.<br/>
						Click on the <i class="fa-solid fa-lg fa-pencil" alt="Edit Icon"></i> icon to view or edit an existing IP List.<br/>
						Click on the <i class="fa-solid fa-lg fa-trash-can" alt="Delete Icon"></i> icon to delete an existing IP List.
					</p>
				</div>
			</div>
		</div>
	</div>
</div>

</div>

<script type="text/javascript">
//<![CDATA[

	function suricata_iplist_action(action,list) {
		$('#iplist_action').val(action);
		$('#iplist_fname').val(list);
		if (action == 'delete') {
			if (confirm('Are you sure you want to delete this IP List?'))
				$('#iform').submit();
		}
		else {
			$('#iform').submit();
		}
		return false;
	}

	events.push(function(){

		function et_iqrisk_enable() {
			var hide = ! $('#et_iqrisk_enable').prop('checked');
			hideInput('iqrisk_code', hide);
		}

		// ---------- Click checkbox handlers ---------------------------------------------------------
		// When 'enable_vrt_rules' is clicked, toggle the Oinkmaster text control
		$('#et_iqrisk_enable').click(function() {
			et_iqrisk_enable();
		});

		// ---------- On initial page load ------------------------------------------------------------
		et_iqrisk_enable();

	});

//]]>
</script>

<?php include("foot.inc"); ?>
