<?php
/*
 * snort_ip_list_mgmt.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2018 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2009-2010 Robert Zelaya.
 * Copyright (c) 2018 Bill Meeks
 * All rights reserved.
 *
 * originially part of m0n0wall (http://m0n0.ch/wall)
 * Copyright (c) 2003-2004 Manuel Kasper <mk@neon1.net>.
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
require_once("/usr/local/pkg/snort/snort.inc");

if (!is_array($config['installedpackages']['snortglobal']['rule'])) {
	$config['installedpackages']['snortglobal']['rule'] = array();
}

// Hard-code the path where IP Lists are stored
// and disregard any user-supplied path element.
$iprep_path = SNORT_IPREP_PATH;

// Set default to not show IP List editor controls
$iplist_edit_style = "display: none;";

function snort_is_iplist_active($iplist) {

	/***************************************************
	 * This function checks all the configured Snort   *
	 * interfaces to see if the passed IP List is used *
	 * as a whitelist or blacklist by an interface.    *
	 *                                                 *
	 * Returns: TRUE  if IP List is in use             *
	 *          FALSE if IP List is not in use         *
	 ***************************************************/

	global $g, $config;

	if (!is_array($config['installedpackages']['snortglobal']['rule']))
		return FALSE;

	foreach ($config['installedpackages']['snortglobal']['rule'] as $rule) {
		if (is_array($rule['wlist_files']['item'])) {
			foreach ($rule['wlist_files']['item'] as $file) {
				if ($file == $iplist)
					return TRUE;
			}
		}
		if (is_array($rule['blist_files']['item'])) {
			foreach ($rule['blist_files']['item'] as $file) {
				if ($file == $iplist)
					return TRUE;
			}
		}
	}
	return FALSE;
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
			if (!snort_is_iplist_active($_POST['iplist_fname']))
				unlink_if_exists("{$iprep_path}{$_POST['iplist_fname']}");
			else
				$input_errors[] = gettext("This IP List is currently assigned as a Whitelist or Blackist for an interface and cannot be deleted.");
			break;

		case 'edit':
			$file = $iprep_path . basename($_POST['iplist_fname']);
			$data = file_get_contents($file);
			if ($data !== FALSE) {
				$iplist_data = htmlspecialchars($data);
				$iplist_edit_style = "display: inline;";
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

if (isset($_POST['save']) && isset($_POST['iplist_data'])) {
	if (strlen(basename($_POST['iplist_name'])) > 0) {
		$file = $iprep_path . basename($_POST['iplist_name']);
		$data = str_replace("\r\n", "\n", $_POST['iplist_data']);
		file_put_contents($file, $data);
		unset($data);
	}
	else {
		$input_errors[] = gettext("You must provide a valid filename for the IP List.");
		$iplist_edit_style = "display: inline;";
	}
}

// Get all files in the IP Lists sub-directory as an array
// Leave this as the last thing before spewing the page HTML
// so we can pick up any changes made to files in code above.
$ipfiles = return_dir_as_array($iprep_path);

$pgtitle = array(gettext('Services'), gettext('Snort'), gettext('IP Reputation Lists'));
include_once("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}

if ($savemsg)
	print_info_box($savemsg);

$tab_array = array();
$tab_array[] = array(gettext("Snort Interfaces"), false, "/snort/snort_interfaces.php");
$tab_array[] = array(gettext("Global Settings"), false, "/snort/snort_interfaces_global.php");
$tab_array[] = array(gettext("Updates"), false, "/snort/snort_download_updates.php");
$tab_array[] = array(gettext("Alerts"), false, "/snort/snort_alerts.php");
$tab_array[] = array(gettext("Blocked"), false, "/snort/snort_blocked.php");
$tab_array[] = array(gettext("Pass Lists"), false, "/snort/snort_passlist.php");
$tab_array[] = array(gettext("Suppress"), false, "/snort/snort_interfaces_suppress.php");
$tab_array[] = array(gettext("IP Lists"), true, "/snort/snort_ip_list_mgmt.php");
$tab_array[] = array(gettext("SID Mgmt"), false, "/snort/snort_sid_mgmt.php");
$tab_array[] = array(gettext("Log Mgmt"), false, "/snort/snort_log_mgmt.php");
$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=snort/snort_sync.xml");
display_top_tabs($tab_array, true);
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("IP Reputation List Management")?></h2></div>
	<div class="panel-body">

		<form action="/snort/snort_ip_list_mgmt.php" id="iform" name="iform" enctype="multipart/form-data" method="post" class="form-horizontal">
		<input type="hidden" name="MAX_FILE_SIZE" value="100000000" />
		<input type="hidden" name="iplist_fname" id="iplist_fname" value=""/>
		<input type="hidden" name="iplist_action" id="iplist_action" value=""/>

		<div class="table-responsive">

	<?php if ($g['platform'] == "nanobsd") : ?>
			<table id="maintable" class="table table-striped table-hover table-condensed">
				<tbody>
				<tr>
					<td><?php echo gettext("IP Reputation is not supported on NanoBSD installs"); ?></td>
				</tr>
				</tbody>
			</table>
	<?php else: ?>
			<table id="maintable" class="table table-striped table-hover table-condensed">
				<thead>
					<tr>
						<th><?=gettext("IP List File Name"); ?></th>
						<th><?=gettext("Last Modified Time"); ?></th>
						<th><?=gettext("File Size"); ?></th>
						<th><?=gettext("Actions"); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($ipfiles as $file): ?>
					<tr>
						<td><?=htmlspecialchars(gettext($file)); ?></td>
						<td><?=date('M-d Y g:i a', filemtime("{$iprep_path}{$file}")); ?></td>
						<td><?=format_bytes(filesize("{$iprep_path}{$file}")); ?> </td>
						<td>
							<a href="#" class="fa fa-pencil icon-primary" onClick="snort_iplist_action('edit', '<?=addslashes($file);?>');" title="<?=gettext('Edit this IP List');?>"></a>
							<a href="#" class="fa fa-trash icon-primary no-confirm" onClick="snort_iplist_action('delete', '<?=addslashes($file);?>');" title="<?=gettext('Delete this IP List');?>"></a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div id="uploader" class="row" style="display: none;">
			<div class="col-md-8">
				<br/><?=gettext('Click BROWSE to select a file to import, and then click UPLOAD.  Click CLOSE to quit.'); ?>
			</div>
			<div class="col-md-8">
				<div class="form-group">
					<input type="file" name="iprep_fileup" id="iprep_fileup" class="form-control" />
					<button type="submit" name="upload" id="upload" class="btn btn-info btn-sm" title="<?=gettext('Upload selected IP list to firewall');?>">
						<i class="fa fa-upload icon-embed-btn"></i>
						<?=gettext('Upload'); ?>
					</button>
					<button type="button" class="btn btn-default btn-sm btn-warning" onClick="document.getElementById('uploader').style.display='none';">
						<?=gettext('Close');?>
					</button>
				</div>
			</div>
		</div>
		<div class="row" id="iplist_editor" style="<?=$iplist_edit_style;?>">
			<div class="col-md-8">
				<div class="form-group">
					<div class="input-group">
						<div class="input-group-addon"><strong><?=gettext("File Name: ");?></strong></div>
						<input type="text" class="form-control" id="iplist_name" name="iplist_name" value="<?=gettext($iplist_name);?>" />
					</div>
					<button type="submit" id="save" name="save" class="btn btn-primary btn-sm" title="<?=gettext('Save changes and close editor');?>">
						<i class="fa fa-save icon-embed-btn"></i>
						<?=gettext('Save'); ?>
					</button>
					<button type="button" class="btn btn-default btn-sm btn-warning" id="cancel" name="cancel" value="<?=gettext('Cancel');?>" onClick="document.getElementById('iplist_editor').style.display='none';"  
						title="<?=gettext('Abandon changes and quit editor');?>">
						<?=gettext('Cancel');?>
					</button>
				</div>
			</div>
			<div class="col-md-8">
				<p>
					<textarea wrap="off" cols="" rows="10" name="iplist_data" id="iplist_data" class="form-control"><?=$iplist_data;?></textarea>
				</p>
			</div>
		</div>
	<?php endif; ?>
		</form>
	</div>
</div>
<nav class="action-buttons">
	<button type="button" class="btn btn-success btn-sm" title="<?=gettext('Create new IP List');?>" onclick="document.getElementById('iplist_data').value=''; document.getElementById('iplist_name').value=''; document.getElementById('iplist_editor').style.display='inline'; document.getElementById('iplist_name').focus();">
		<i class="fa fa-plus icon-embed-btn"></i>
		<?=gettext('Add');?>
	</button>
	<button type="button" class="btn btn-info btn-sm" title="<?=gettext('Upload IP List file');?>" onclick="document.getElementById('uploader').style.display='inline';">
		<i class="fa fa-upload icon-embed-btn"></i>
		<?=gettext('Upload');?>
	</button>
</nav>
<div class="infoblock">
<div class="alert alert-info clearfix" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button><div class="pull-left">
	<div class="row">
		<div class="col-md-12">
			<p>
				IP Lists are used by the IP Reputation Preprocessor and are text files formatted with one IP address or CIDR network per line.<br/>
				IP Lists are stored as local files on the firewall and their contents are not saved as part of the firewall configuration file.
			</p>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<p>
				Click on the <i class="fa fa-lg fa-plus" alt="Add Icon"></i> icon to open the editor window to create a new IP List.<br/>
				Click on the <i class="fa fa-lg fa-upload" alt="Upload Icon"></i> icon to upload a new IP List file from your local machine.<br/>
				Click on the <i class="fa fa-lg fa-pencil" alt="Edit Icon"></i> icon to view or edit an existing IP List.<br/>
				Click on the <i class="fa fa-lg fa-trash" alt="Delete Icon"></i> icon to delete an existing IP List.
			</p>
		</div>
	</div>
</div></div></div>

<script type="text/javascript">
//<![CDATA[

	function snort_iplist_action(action,list) {
		$('#iplist_action').val(action);
		$('#iplist_fname').val(list);
		if (action == 'delete') {
			if (confirm('Are you sure you want to delete this IP List?'))
				$('#iform').submit();
		}
		else {
			$('#iform').submit();
		}
	}

//]]>
</script>

<?php
include("foot.inc");
?>

