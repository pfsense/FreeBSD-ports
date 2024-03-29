<?php
/*
 * snort_iprep_list_browser.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2019-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2022 Bill Meeks
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

$path = SNORT_IPREP_PATH;
$container = htmlspecialchars($_POST['container']);
$target = htmlspecialchars($_POST['target']);

// ----- header -----
?>
<div class="table-responsive">
	<table class="table table-striped table-hover table-condensed">
		<thead>
			<tr>
				<th><img src="/vendor/filebrowser/images/icon_home.gif" alt="Home" title="Home" /></th>
				<th><b><?=$path;?></b></th>
				<th><b><?=gettext('File Size'); ?></th>
				<th class="fbClose pull-right">
					<img onClick="$('<?=$container;?>').hide();" border="0" src="/vendor/filebrowser/images/icon_cancel.gif" alt="Close" title="Close" />
				</th>
			</tr>
		</thead>
		<tbody>
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
				<td class="fbFile" id="<?=$fqpn;?>">
					<?php $filename = str_replace("//","/", "{$path}/{$file}"); ?>
					<div onClick="$('<?=$target;?>').value='<?=$filename?>'; $('<?=$container;?>').hide();">
						<img src="/vendor/filebrowser/images/file_<?=$type;?>.gif" alt="" title="">&nbsp;<?=$file;?>
					</div>
				</td>
				<td><?=$size;?></td>
				<td></td>
			</tr>
	<?php
	endforeach;
	?>
	</tbody>
	</table>
</div>

