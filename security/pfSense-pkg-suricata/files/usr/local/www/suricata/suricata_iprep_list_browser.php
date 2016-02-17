<?php
/*
*  suricata_iprep_list_browser.php
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

// Fetch a list of files inside a given directory
function get_content($dir) {
	$files = array();

	clearstatcache();
	$fd = @opendir($dir);
	while($entry = @readdir($fd)) {
		if($entry == ".")  continue;
		if($entry == "..") continue;

		if(is_dir("{$dir}/{$entry}"))
			continue;
		else
			array_push($files, $entry);
	}
	@closedir($fd);
	natsort($files);
	return $files;
}

$path = SURICATA_IPREP_PATH;
$container = htmlspecialchars($_GET['container']);
$target = htmlspecialchars($_GET['target']);

// ----- header -----
?>
<table width="100%">
	<tr>
		<td width="25px" align="left">
			<img src="/filebrowser/images/icon_home.gif" alt="Home" title="Home" />
		</td>
		<td><b><?=$path;?></b></td>
		<td class="fbClose" align="right">
			<img onClick="$('#<?=$container;?>').hide();" border="0" src="/filebrowser/images/icon_cancel.gif" alt="Close" title="Close" />
		</td>
	</tr>
	<tr>
		<td id="fbCurrentDir" colspan="3" class="vexpl" align="left">
		</td>
	</tr>
<?php
$files = get_content($path);

// ----- files -----
foreach($files as $file):
	$ext = strrchr($file, ".");

	if($ext == ".css" ) $type = "code";
	elseif($ext == ".html") $type = "code";
	elseif($ext == ".xml" ) $type = "code";
	elseif($ext == ".rrd" ) $type = "database";
	elseif($ext == ".gif" ) $type = "image";
	elseif($ext == ".jpg" ) $type = "image";
	elseif($ext == ".png" ) $type = "image";
	elseif($ext == ".js"  ) $type = "js";
	elseif($ext == ".pdf" ) $type = "pdf";
	elseif($ext == ".inc" ) $type = "php";
	elseif($ext == ".php" ) $type = "php";
	elseif($ext == ".conf") $type = "system";
	elseif($ext == ".pid" ) $type = "system";
	elseif($ext == ".sh"  ) $type = "system";
	elseif($ext == ".bz2" ) $type = "zip";
	elseif($ext == ".gz"  ) $type = "zip";
	elseif($ext == ".tgz" ) $type = "zip";
	elseif($ext == ".zip" ) $type = "zip";
	else                    $type = "generic";

	$fqpn = "{$path}/{$file}";

	if(is_file($fqpn)) {
		$fqpn = realpath($fqpn);
		$size = sprintf("%.2f KiB", filesize($fqpn) / 1024);
	}
	else
		$size = "";
?>
	<tr>
		<td></td>
		<td class="fbFile vexpl" id="<?=$fqpn;?>" align="left">
			<?php $filename = str_replace("//","/", "{$path}/{$file}"); ?>
			<div onClick="$('#<?=$target;?>').val('<?=$filename?>'); $('#<?=$container;?>').hide();">
				<img src="/filebrowser/images/file_<?=$type;?>.gif" alt="" title="">
				&nbsp;<?=$file;?>
			</div>
		</td>
		<td align="right" class="vexpl">
			<?=$size;?>
		</td>
	</tr>
<?php
endforeach;
?>
</table>

