<?php
/*
* select_box_file.php
* part of pfSense (https://www.pfSense.org/)
* Copyright (c) 2018-2020 Prosper Doko
* Copyright (c) 2020 Mark Overholser
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

/*
 * Returns an array containing an alphabetic list of files in the specified
 * directory ($path) with a file extension that matches $extension
 */
function list_by_ext($extension, $path) {
	$list = array();
	$dir_handle = @opendir($path) or die("Unable to open {$path}");

	while ($file = readdir($dir_handle)) {
		if (($file == ".") || ($file == "..")) {
			continue;
		}
		$filename = explode(".", $file);
		$cnt = count($filename); 
		$cnt--; 
		$ext = $filename[$cnt];
		if (strtolower($ext) == strtolower($extension)) {
			array_push($list, $file);
		}
	}

	if ($list[0]) { 
		return $list; 
	} else {
		return false;
	}
}

if ($_POST['x']) {
	$x = $_POST['x'];
	$current = list_by_ext("log", "/usr/local/logs/current");
	if (($x==1) || (count($current)>$x)) {
		foreach ($current as $value) {
			echo "<option value={$value}>{$value}</option>";
		}
	}
}
?>
