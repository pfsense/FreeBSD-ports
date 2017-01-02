<?php
/*
    haproxy_gui.inc
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
    AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
    OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
    SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
    INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
    CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
    ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
    POSSIBILITY OF SUCH DAMAGE.
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
