<?php
/*
*  suricata_list_view.php
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

global $g, $config;

$contents = '';

if ($_REQUEST['ajax'] == 'ajax') {
	if (isset($_REQUEST['id']) && is_numericint($_REQUEST['id'])) {
		$id = htmlspecialchars($_REQUEST['id']);
	}

	$wlist = htmlspecialchars($_REQUEST['wlist']);
	$type = htmlspecialchars($_REQUEST['type']);
} else {
	if (isset($_GET['id']) && is_numericint($_GET['id'])) {
		$id = htmlspecialchars($_GET['id']);
	}

	$wlist = htmlspecialchars($_GET['wlist']);
	$type = htmlspecialchars($_GET['type']);
}

$title = "List";

if (isset($id) && isset($wlist)) {
	$a_rule = $config['installedpackages']['suricata']['rule'][$id];
	if ($type == "homenet") {
		$list = suricata_build_list($a_rule, $wlist);
		$contents = implode("\n", $list);
		$title = "HOME_NET";
	}
	elseif ($type == "passlist") {
		$list = suricata_build_list($a_rule, $wlist, true);
		$contents = implode("\n", $list);
		$title = "Pass List";
	}
	elseif ($type == "suppress") {
		$list = suricata_find_list($wlist, $type);
		$contents = str_replace("\r", "", base64_decode($list['suppresspassthru']));
		$title = "Suppress List";
	}
	elseif ($type == "externalnet") {
		if ($wlist == "default") {
			$list = suricata_build_list($a_rule, $a_rule['homelistname']);
			$contents = "";
			foreach ($list as $ip)
				$contents .= "!{$ip}\n";
			$contents = trim($contents, "\n");
		}
		else {
			$list = suricata_build_list($a_rule, $wlist, false, true);
			$contents = implode("\n", $list);
		}
		$title = "EXTERNAL_NET";
	}
	else
		$contents = gettext("\n\nERROR -- Requested List Type entity is not valid!");
}
else
	$contents = gettext("\n\nERROR -- Supplied interface or List entity is not valid!");

if ($_REQUEST['ajax'] == 'ajax') {
	print($contents);
} else {

// Probably won't need this after the whole package is converted to Bootstrap
$pgtitle = array(gettext("Suricata"), gettext($title . " Viewer"));
?>

<?php include("head.inc");?>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
	<td class="tabcont">
		<table width="100%" cellpadding="0" cellspacing="6" bgcolor="#eeeeee">
		<tr>
			<td class="pgtitle" colspan="2">Suricata: <?php echo gettext($title . " Viewer"); ?></td>
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
<?php }

