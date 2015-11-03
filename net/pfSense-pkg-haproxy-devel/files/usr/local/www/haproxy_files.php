<?php
/* $Id: load_balancer_virtual_server.php,v 1.6.2.1 2006/01/02 23:46:24 sullrich Exp $ */
/*
	haproxy_pools.php
	part of pfSense (https://www.pfsense.org/)
	Copyright (C) 2014 PiBa-NL
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
require_once("haproxy_htmllist.inc");

$a_files = &$config['installedpackages']['haproxy']['files']['item'];
if (!is_array($a_files)) $a_files = array();
$a_pools = &$config['installedpackages']['haproxy']['ha_pools']['item'];
if (!is_array($a_pools)) $a_pools = array();


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

$pgtitle = "Services: HAProxy: Files";
include("head.inc");
haproxy_css();

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<form action="haproxy_files.php" method="post">
<?php if ($input_errors) print_input_errors($input_errors); ?>
<?php if ($savemsg) print_info_box($savemsg); ?>
<?php if (file_exists($d_haproxyconfdirty_path)): ?>
<?php print_info_box_np("The haproxy configuration has been changed.<br/>You must apply the changes in order for them to take effect.");?><br/>
<?php endif; ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr><td class="tabnavtbl">
  <?php
	haproxy_display_top_tabs_active($haproxy_tab_array['haproxy'], "files");
  ?>
  </td></tr>
  <tr>
    <td>
	<div id="mainarea">
		<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr>
			<td>
				Files can be used for errorfiles, that can return custom error pages in 
				case haproxy reports a error (like no available backend). The content needs 
				to be less than the buffer size which is typically 8kb.
				There are 2 possible variables to use inside the template:
				Put these variables in the content of the errorfile templates and they will be replaced by the actual errorcode / message. (include the curly braces around the text)<br/>
				<b>{errorcode}</b> this represents the errorcode<br/>
				<b>{errormsg}</b> this represents the human readable error<br/>
			</td>
		</tr>
		<tr>
			<td>
				&nbsp;
			</td>
		</tr>
		<tr>
			<td>
			<?
			$counter=0;
			$fileslist->Draw($a_files);
			?>
			</td>
		</tr>
			<tr>
				<td>
					&nbsp;
				</td>
			</tr>
			<tr>
				<td width="78%">
					<input name="Submit" type="submit" class="formbtn" value="Save" />
				</td>
			</tr>
		</table>
	</div>
	</table>
	</form>
<script type="text/javascript">
	totalrows =  <?php echo $counter; ?>;
<?
	$fileslist->outputjavascript();
?>
</script>

<?php
haproxy_htmllist_js();
include("fend.inc"); ?>
</body>
</html>
