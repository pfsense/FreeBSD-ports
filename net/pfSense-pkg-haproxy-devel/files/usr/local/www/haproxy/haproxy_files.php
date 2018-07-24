<?php
/*
 * haproxy_files.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2014 PiBa-NL
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
require_once("haproxy/haproxy_htmllist.inc");
require_once("haproxy/pkg_haproxy_tabs.inc");

haproxy_config_init();

$fields_files = array();
$fields_files[0]['name']="name";
$fields_files[0]['columnheader']="Name";
$fields_files[0]['colwidth']="20%";
$fields_files[0]['type']="textbox";
$fields_files[0]['size']="20";
$fields_files[1]['name']="type";
$fields_files[1]['columnheader']="Type";
$fields_files[1]['colwidth']="10%";
$fields_files[1]['type']="select";
$fields_files[1]['size']="10";
$fields_files[1]['items']=$a_filestype;
$fields_files[2]['name']="content";
$fields_files[2]['columnheader']="content";
$fields_files[2]['colwidth']="70%";
$fields_files[2]['type']="textarea";
$fields_files[2]['size']="70";

$fileslist = new HaproxyHtmlList("table_files", $fields_files);
$fileslist->keyfield = "name";

if ($_POST) {
	$pconfig = $_POST;

	if ($_POST['apply']) {
		$result = haproxy_check_and_run($savemsg, true);
		if ($result)
			unlink_if_exists($d_haproxyconfdirty_path);
	} else {
		$a_files = $fileslist->haproxy_htmllist_get_values();
		$filedupcheck = array();

		foreach($a_files as $key => $file) {
			$name = $file['name'];
			if (!preg_match("/^[a-zA-Z][a-zA-Z0-9\.\-_]*$/", $file['name']))
				$input_errors[] = "The field 'Name' (".htmlspecialchars($file['name']).") contains invalid characters. Use only: a-zA-Z0-9.-_ and start with a letter";
			if (isset($filedupcheck[$name]))
				$input_errors[] = "Duplicate names are not allowed: " . htmlspecialchars($name);
			$filedupcheck[$name] = true;
		}

		// replace references in backends to renamed 'files'
		foreach($a_pools as &$backend) {
			if (is_arrayset($backend,'errorfiles','item')) {
				foreach($backend['errorfiles']['item'] as &$errorfile) {
					$found = false;
					foreach($a_files as $key => $file) {
						if ($errorfile['errorfile'] == $key) {
							$errorfile['errorfile'] = $file['name'];
							$found = true;
						}
					}
					if (!$found) {
						$input_errors[] = "Errorfile marked for deletion: " . $errorfile['errorfile'] . " which is used in backend " . $backend['name'];
					}
				}
			}
		}
		if (!$input_errors) {
			// save config when no errors found
			touch($d_haproxyconfdirty_path);
			write_config($changedesc);
			header("Location: haproxy_files.php");
			exit;
		}
	}
}

$pgtitle = array("Services", "HAProxy", "Files");
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
haproxy_display_top_tabs_active($haproxy_tab_array['haproxy'], "files");
?>
<form action="haproxy_files.php" method="post">
	<div class="panel panel-default">
		<div class="panel-heading">
			<h2 class="panel-title"><?=gettext("Files")?></h2>
		</div>
	<div class="content">
		<div class="table-responsive panel-body content">
			Files can be used for errorfiles and lua scripts.<br/>
			- Errorfiles can return custom error pages in
			case haproxy reports a error (like no available backend). The content needs
			to be less than the buffer size which is typically 8kb.
			There are 2 possible variables to use inside the template:
			Put these variables in the content of the errorfile templates and they will be replaced by the actual errorcode / message. (include the curly braces around the text)<br/>
			<b>{errorcode}</b> this represents the errorcode<br/>
			<b>{errormsg}</b> this represents the human readable error<br/>
			- Lua files, can be used to implement custom fetches, service implementations and has several other options.<br/>
			<a href="http://www.arpalert.org/src/haproxy-lua-api/1.6/">See the api for more information.</a>
		</div>
		<div class="table-responsive panel-body">
			<?php
			$counter=0;
			echo $fileslist->Draw($a_files);
			?>
		</div>
	</div>
		<div class="col-sm-2">
		</div>
		<div class="col-sm-8">
			<br/><?=new Form_Button('save','Save');?>
		</div>
	</div>

</form>
<?php
haproxy_htmllist_js();
?>
<script type="text/javascript">
	totalrows =  <?php echo $counter; ?>;
<?php
	$fileslist->outputjavascript();
?>
</script>

<?php
include("foot.inc");
