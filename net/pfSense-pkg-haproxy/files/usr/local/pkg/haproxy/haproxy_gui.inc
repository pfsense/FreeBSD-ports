<?php
/*
 * haproxy_gui.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2016 PiBa-NL
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

/* Include the autoloader for all the GUI display classes */
require_once("classes/autoload.inc.php");

$haproxy_icons = array(
	'moveup' => array(
		"faicon" => "fa-arrow-up",
		"icon" => "icon_up.gif",
		"iconsize" => 17),
	'movedown' => array(
		"faicon" => "fa-arrow-down",
		"icon" => "icon_down.gif",
		"iconsize" => 17),
	'add' => array(
		"faicon" => "fa-level-down",
		"icon" => "icon_plus.gif",
		"iconsize" => 17),
	'delete' => array(
		"faicon" => "fa-trash",
		"icon" => "icon_x.gif",
		"iconsize" => 17),
	'edit' => array(
		"faicon" => "fa-pencil",
		"icon" => "icon_e.gif",
		"iconsize" => 17),
	'clone' => array(
		"faicon" => "fa-clone",
		"icon" => "icon_plus.gif"),
	'acl' => array(
		"faicon" => "fa-random",
		"icon" => "icon_ts_rule.gif",
		"iconsize" => 11),
	'cert' => array(
		"faicon" => "fa-lock",
		"icon" => "icon_frmfld_cert.png",
		"iconsize" => 11),
	'advanced' => array(
		"faicon" => "fa-cog",
		"icon" => "icon_advanced.gif",
		"iconsize" => 11),
	'enabled' => array(
		"faicon" => "fa-check",
		"icon" => "icon_pass.gif",
		"iconsize" => 11),
	'disabled' => array(
		"faicon" => "fa-ban",
		"icon" => "icon_reject.gif",
		"iconsize" => 11),
	'stats' => array(
		"faicon" => "fa-tasks",
		"icon" => "icon_log_s.gif",
		"iconsize" => 11),
	'stop' => array(
		"faicon" => "fa-stop-circle-o",
		"icon" => "icon_service_stop.gif",
		"iconsize" => 17),
	'start' => array(
		"faicon" => "fa-play-circle",
		"icon" => "icon_service_start.gif",
		"iconsize" => 17),
	'up' => array(
		"faicon" => "fa-check-circle",
		"icon" => "icon_interface_up.gif",
		"iconsize" => 11),
	'down' => array(
		"faicon" => "fa-times-circle",
		"icon" => "icon_interface_down.gif",
		"iconsize" => 11),
	'resolvedns' => array(
		"faicon" => "fa-info",
		"icon" => "icon_log.gif",
		"iconsize" => 11),
	'help' => array(
		"faicon" => "fa-info-circle",
		"icon" => "icon_help.gif",
		"iconsize" => 11),
	'expand' => array(
		"faicon" => "fa-plus-square-o",
		"icon" => "plus.gif",
		"iconsize" => 11)

);

global $pf_version;
$pf_version = substr(trim(file_get_contents("/etc/version")), 0, 3);

if(!function_exists('pf_version')) {
	function pf_version() {
		global $pf_version;
		return $pf_version;
	}
}

function haproxyicon($iconname, $title) {
	global $g, $haproxy_icons;
	$title = htmlspecialchars($title);
	$title = str_replace("'", "&#39;", $title);
	
	$faicon = $haproxy_icons[$iconname]["faicon"];
	if (pf_version() < "2.3") {
		$icon = $haproxy_icons[$iconname]["icon"];
		$iconsize = $haproxy_icons[$iconname]["iconsize"];
		return "<img src='/themes/{$g['theme']}/images/icons/$icon' title='$title' width='$iconsize' height='$iconsize' border='0' />";	
	} else {
		return "<i class='fa $faicon' title='$title'></i>";
	}
}

class Form_Section_class extends Form_Section {
	// Shortcut, adds a group for the specified input
	public function addInput(Form_Input $input, $groupclass = null)
	{
		$group = new Form_Group($input->getTitle());
		$group->add($input);
		if ($groupclass) {
			$group->addClass($groupclass);
		}
		$this->add($group);
		return $input;
	}
}
