<?php
/*
 * snort_list_view.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2014 Bill Meeks
 * Copyright (c) 2006-2009 Volker Theile
 * Copyright (c) 2008-2009 Robert Zelaya
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

global $g, $config;

$contents = '';

if (isset($_GET['id']) && is_numericint($_GET['id']))
	$id = htmlspecialchars($_GET['id']);

$wlist = htmlspecialchars($_GET['wlist']);
$type = htmlspecialchars($_GET['type']);
$title = "List";

if (isset($id) && isset($wlist)) {
	$a_rule = $config['installedpackages']['snortglobal']['rule'][$id];
	if ($type == "homenet") {
		$list = snort_build_list($a_rule, $wlist);
		$contents = implode("\n", $list);
		$title = "HOME_NET";
	}
	elseif ($type == "passlist") {
		$list = snort_build_list($a_rule, $wlist, true);
		$contents = implode("\n", $list);
		$title = "Pass List";
	}
	elseif ($type == "suppress") {
		$list = snort_find_list($wlist, $type);
		$contents = str_replace("\r", "", base64_decode($list['suppresspassthru']));
		$title = "Suppress List";
	}
	elseif ($type == "externalnet") {
		if ($wlist == "default") {
			$list = snort_build_list($a_rule, $a_rule['homelistname']);
			$contents = "";
			foreach ($list as $ip)
				$contents .= "!{$ip}\n";
			$contents = trim($contents, "\n");
		}
		else {
			$list = snort_build_list($a_rule, $wlist, false, true);
			$contents = implode("\n", $list);
		}
		$title = "EXTERNAL_NET";
	}
	else
		$contents = gettext("\n\nERROR -- Requested List Type entity is not valid!");
}
else
	$contents = gettext("\n\nERROR -- Supplied interface or List entity is not valid!");

$pgtitle = array(gettext("Snort"), gettext($title . " Viewer"));
?>

<?php include("head.inc");?>

<body link="#000000" vlink="#000000" alink="#000000">

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
	<td class="tabcont">
		<table width="100%" cellpadding="0" cellspacing="6" bgcolor="#eeeeee">
		<tr>
			<td class="pgtitle" colspan="2">Snort: <?php echo gettext($title . " Viewer"); ?></td>
		</tr>
		<tr>
			<td align="left" width="20%">
				<input type="button" class="formbtn" value="Return" onclick="window.close()">
			</td>
			<td align="right">
				<b><?php echo gettext($title . ": ") . '</b>&nbsp;' . htmlspecialchars($_GET['wlist']); ?>&nbsp;&nbsp;&nbsp;&nbsp;
			</td>
		</tr>
		<tr>
			<td colspan="2" valign="top" class="label">
			<div style="background: #eeeeee; width:100%; height:100%;" id="textareaitem"><!-- NOTE: The opening *and* the closing textarea tag must be on the same line. -->
			<textarea style="width:100%; height:100%;" readonly wrap="off" rows="25" cols="80" name="code2"><?=htmlspecialchars($contents);?></textarea>
			</div>
			</td>
		</tr>
		</table>
	</td>
</tr>
</table>
</body>
</html>
