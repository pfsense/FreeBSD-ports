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
		"faicon" => "fa-arrow-up"),
	'movedown' => array(
		"faicon" => "fa-arrow-down"),
	'add' => array(
		"faicon" => "fa-level-down"),
	'delete' => array(
		"faicon" => "fa-trash"),
	'edit' => array(
		"faicon" => "fa-pencil"),
	'clone' => array(
		"faicon" => "fa-clone"),
	'acl' => array(
		"faicon" => "fa-random"),
	'cert' => array(
		"faicon" => "fa-lock"),
	'advanced' => array(
		"faicon" => "fa-cog"),
	'enabled' => array(
		"faicon" => "fa-check"),
	'disabled' => array(
		"faicon" => "fa-ban"),
	'stats' => array(
		"faicon" => "fa-tasks"),
	'stop' => array(
		"faicon" => "fa-stop-circle-o"),
	'start' => array(
		"faicon" => "fa-play-circle"),
	'up' => array(
		"faicon" => "fa-check-circle"),
	'down' => array(
		"faicon" => "fa-times-circle"),
	'resolvedns' => array(
		"faicon" => "fa-info"),
	'help' => array(
		"faicon" => "fa-info-circle"),
	'expand' => array(
		"faicon" => "fa-plus-square-o")
);

function haproxyicon($iconname, $title) {
	global $haproxy_icons;
	$title = htmlspecialchars($title);
	$title = str_replace("'", "&#39;", $title);
	
	$faicon = $haproxy_icons[$iconname]["faicon"];
	return "<i class='fa $faicon' title='$title'></i>";
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
