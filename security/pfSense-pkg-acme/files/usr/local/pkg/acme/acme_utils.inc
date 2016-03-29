<?php
/*
	acme_utils.php
	part of pfSense (https://www.pfsense.org/)
	Copyright (C) 2016 PiBa-NL
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
	AUTHOR BE LIABLE FOR ANY DIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
/*
  This file contains functions which are NOT specific to the package and may/could/should 
  be moved to the general pfSense php library for possible easy use by other parts of pfSense
*/

require_once("config.inc");

if(!function_exists('ifset')){
	function ifset(&$var, $default = ''){
		return isset($var) ? $var : $default;
	};
}

if(!function_exists('is_arrayset')){
	function is_arrayset(&$array, $items){
		if (!isset($array))
			return false;
		$item = $array;
		$arg = func_get_args();
		for($i = 1; $i < count($arg); $i++) {

			$itemindex = $arg[$i];
			if (!isset($item[$itemindex]) || !is_array($item[$itemindex]))
				return false;
			$item = $item[$itemindex];

		}
		return true;
	}
}

function phparray_to_javascriptarray_recursive($nestID, $path, $items, $nodeName, $includeitems) {
	$offset = str_repeat('  ',$nestID);
	$itemName = "item$nestID";
	//echo "{$offset}$nodeName = {};\n";
	echo "{$offset}$nodeName = Object.create(null);\n";
	if (is_array($items))
		foreach ($items as $key => $item)
		{
			if (in_array($path.'/'.$key, $includeitems))
				$subpath = $path.'/'.$key;
			else
				$subpath = $path.'/*';
			if (in_array($subpath, $includeitems) || in_array($path.'/*', $includeitems)) {
				if (is_array($item)) {
					$subNodeName = "item$nestID";
					phparray_to_javascriptarray_recursive($nestID+1, $subpath, $items[$key], $subNodeName, $includeitems);
					echo "{$offset}{$nodeName}['{$key}'] = $itemName;\n";
				} else {
					$item = json_encode($item);
					echo "{$offset}{$nodeName}['$key'] = $item;\n";
				}
			}
		}
}
function phparray_to_javascriptarray($items, $javaMapName, $includeitems) {
	phparray_to_javascriptarray_recursive(1,'',$items, $javaMapName, $includeitems);
}

function html_select_options($keyvaluelist, $selected="") {
	$result = "";
	foreach($keyvaluelist as $key => $desc){
		$selectedhtml = $key == $selected ? "selected" : "";
		if ($desc['deprecated'] && $key != $selected){
			continue;
		}
		$name = htmlspecialchars($desc['name']);
		$result .= "<option value='{$key}' {$selectedhtml}>{$name}</option>";
	}
	return $result;
}

function echo_html_select($name, $keyvaluelist, $selected, $listEmptyMessage="", $onchangeEvent="", $style="") {
	$result = "";
	if (count($keyvaluelist)>0){
		if ($onchangeEvent != "")
			$onchangeEvent = " onchange='$onchangeEvent'";
		if ($style != "")
			$style = " style='$style'";
		$result .= "<select name=\"$name\" id=\"$name\" class=\"formselect\"$onchangeEvent$style>";
		$result .= html_select_options($keyvaluelist, $selected);
		$result .= "</select>";
	} else {
		$result .= $listEmptyMessage;
	}
	return $result;
}

function form_keyvalue_array($hap_array) {
	$result = array();
	foreach($hap_array as $key => $item) {
		$result[$key] = $item['name'];
	}
	return $result;
}

function form_name_array($hap_array) {
	$result = array();
	foreach($hap_array as $key => $item) {
		$name = $item['name'];
		$result[$name] = $name;
	}
	return $result;
}
