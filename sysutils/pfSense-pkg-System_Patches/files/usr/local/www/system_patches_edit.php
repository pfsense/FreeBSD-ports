<?php
/*
	system_patches_edit.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2012 Jim Pingle
	Copyright (C) 2015 ESF, LLC
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
/*
	pfSense_MODULE:	system
*/

##|+PRIV
##|*IDENT=page-system-patches-edit
##|*NAME=System: Edit Patches
##|*DESCR=Allow access to the 'System: Edit Patches' page.
##|*MATCH=system_patches_edit.php*
##|-PRIV

require("guiconfig.inc");
require_once("itemid.inc");
require_once("patches.inc");
require_once("pkg-utils.inc");
require_once('classes/Form.class.php');

if (!is_array($config['installedpackages']['patches']['item'])) {
	$config['installedpackages']['patches']['item'] = array();
}
$a_patches = &$config['installedpackages']['patches']['item'];

$id = $_GET['id'];
if (isset($_POST['id'])) {
	$id = $_POST['id'];
}

if (isset($_GET['dup'])) {
	$id = $_GET['dup'];
	$after = $_GET['dup'];
}

if (isset($id) && $a_patches[$id]) {
	$pconfig['descr'] = $a_patches[$id]['descr'];
	$pconfig['location'] = $a_patches[$id]['location'];
	$pconfig['patch'] = $a_patches[$id]['patch'];
	$pconfig['pathstrip'] = $a_patches[$id]['pathstrip'];
	$pconfig['basedir'] = $a_patches[$id]['basedir'];
	$pconfig['ignorewhitespace'] = isset($a_patches[$id]['ignorewhitespace']);
	$pconfig['autoapply'] = isset($a_patches[$id]['autoapply']);
	$pconfig['uniqid'] = $a_patches[$id]['uniqid'];
} else {
	$pconfig['pathstrip'] = 1;
	$pconfig['basedir'] = "/";
	$pconfig['ignorewhitespace'] = true;
}

if (isset($_GET['dup'])) {
	unset($id);
}

unset($input_errors);

if ($_POST) {
	$pconfig = $_POST;

	/* input validation */
	if (empty($_POST['location'])) {
		$reqdfields = explode(" ", "patch");
		$reqdfieldsn = array(gettext("Patch Contents"));
	} else {
		$reqdfields = explode(" ", "descr location");
		$reqdfieldsn = array(gettext("Description"),gettext("URL/Commit ID"));
	}

	$pf_version=substr(trim(file_get_contents("/etc/version")),0,3);
	if ($pf_version < 2.1) {
		$input_errors = eval('do_input_validation($_POST, $reqdfields, $reqdfieldsn, &$input_errors); return $input_errors;');
	} else {
		do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);
	}

	if (!empty($_POST['location']) && !is_commit_id($_POST['location']) && !is_URL($_POST['location'])) {
		$input_errors[] = gettext("The supplied commit ID/URL appears to be invalid.");
	}
	if (!is_numeric($_POST['pathstrip'])) {
		$input_errors[] = gettext("Path Strip Count must be numeric!");
	}
	if (!empty($_POST['basedir']) && (!file_exists($_POST['basedir']) || !is_dir($_POST['basedir']))) {
		$input_errors[] = gettext("Base Directory must exist and be a directory!");
	}

	if (!$input_errors) {
		$thispatch = array();

		$thispatch['descr'] = $_POST['descr'];
		$thispatch['location'] = patch_fixup_url($_POST['location']);
		if (!empty($_POST['patch'])) {
			/* Strip DOS style carriage returns from textarea input */
			$thispatch['patch'] = base64_encode(str_replace("\r", "", $_POST['patch']));
		}
		if (is_github_url($thispatch['location']) && ($_POST['pathstrip'] == 0)) {
			$thispatch['pathstrip'] = 1;
		} else {
			$thispatch['pathstrip'] = $_POST['pathstrip'];
		}
		$thispatch['basedir'] = empty($_POST['basedir']) ? "/" : $_POST['basedir'];
		$thispatch['ignorewhitespace'] = isset($_POST['ignorewhitespace']);
		$thispatch['autoapply'] = isset($_POST['autoapply']);
		if (empty($_POST['uniqid'])) {
			$thispatch['uniqid'] = uniqid();
		} else {
			$thispatch['uniqid'] = $_POST['uniqid'];
		}

		// Update the patch entry now
		if (isset($id) && $a_patches[$id]) {
			$a_patches[$id] = $thispatch;
		} else {
			if (is_numeric($after)) {
				array_splice($a_patches, $after+1, 0, array($thispatch));
			} else {
				$a_patches[] = $thispatch;
			}
		}

		write_config();
		if ($thispatch['autoapply']) {
			patch_add_shellcmd();
		}
		header("Location: system_patches.php");
		return;
	}
}

$closehead = false;
$pgtitle = array(gettext("System"),gettext("Patches"), gettext("Edit"));
$pglinks = array("", "system_patches.php", "@self");
include("head.inc");

if ($input_errors) {
	print_input_errors($input_errors);
}
if ($savemsg) {
	print_info_box($savemsg, 'success');
}

$form = new Form();

$section = new Form_Section('Patch Details');

$section->addInput(new Form_Input(
	'descr',
	'Description',
	'text',
	$pconfig['descr']
))->setHelp('Enter a description here for reference.');

$section->addInput(new Form_Input(
	'location',
	'URL/Commit ID',
	'text',
	$pconfig['location']
))->setHelp('Enter a URL to a patch, or a commit ID from the main github repository (NOT the tools or packages repos!)');

$patchtext = new Form_Textarea(
	'patch',
	'Patch Contents',
	$input_errors ? $pconfig['patch'] : base64_decode($pconfig['patch'])
);

$patchtext->setWidth(7);
$patchtext->setAttribute("rows", "15");
$patchtext->setAttribute("wrap", "off");
$patchtext->setHelp('The contents of the patch. Paste a patch here, or enter a URL/commit ID above.');

$section->addInput($patchtext);

$form->add($section);

$section = new Form_Section('Patch Application Behavior');

$section->addInput(new Form_Select(
	'pathstrip',
	'Path Strip Count',
	$pconfig['pathstrip'],
	array_combine(range(0, 20, 1), range(0, 20, 1))
))->setHelp('The number of levels to strip from the front of the path in the patch header.');

$section->addInput(new Form_Input(
	'basedir',
	'Base Directory',
	'text',
	htmlspecialchars($pconfig['basedir'])
))->setHelp('Enter the base directory for the patch, default is /. Patches from github are all based in /. <br/>Custom patches may need a full path here such as /usr/local/www/.');

$section->addInput(new Form_Checkbox(
	'ignorewhitespace',
	'Ignore Whitespace',
	'Ignore whitespace in the patch.',
	$pconfig['ignorewhitespace']
));

$section->addInput(new Form_Checkbox(
	'autoapply',
	'Auto Apply',
	'Apply the patch automatically when possible, useful for patches to survive after updates.',
	$pconfig['autoapply']
));

$form->add($section);

$section = new Form_Section('Patch Information');

$section->addInput(new Form_StaticText(
	'Patch ID',
	$pconfig['uniqid']
));

$form->add($section);

$form->addGlobal(new Form_Input(
	'id',
	null,
	'hidden',
	$id
));

$form->addGlobal(new Form_Input(
	'uniqid',
	null,
	'hidden',
	$pconfig['uniqid']
));


print($form);

?>
<?php include("foot.inc"); ?>