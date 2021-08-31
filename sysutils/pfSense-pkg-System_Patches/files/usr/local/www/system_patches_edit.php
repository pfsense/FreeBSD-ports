<?php
/*
 * system_patches_edit.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2012-2021 Rubicon Communications, LLC (Netgate)
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

init_config_arr(array('installedpackages', 'patches', 'item'));
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
	$pconfig['pathstrip'] = 2;
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
	if (empty($_POST['location']) && !is_uploaded_file($_FILES['ulfile']['tmp_name'])) {
		$reqdfields = explode(" ", "patch");
		$reqdfieldsn = array(gettext("Patch Contents"));
	} elseif (!is_uploaded_file($_FILES['ulfile']['tmp_name'])) {
		$reqdfields = explode(" ", "descr location");
		$reqdfieldsn = array(gettext("Description"),gettext("URL/Commit ID"));
	} else {
		$reqdfields = explode(" ", "descr");
		$reqdfieldsn = array(gettext("Description"));
	}

	do_input_validation($_POST, $reqdfields, $reqdfieldsn, $input_errors);

	if (!empty($_POST['location']) && !is_commit_id($_POST['location']) && !is_URL($_POST['location'])) {
		$input_errors[] = gettext("The supplied commit ID/URL appears to be invalid.");
	}
	if (!is_numeric($_POST['pathstrip'])) {
		$input_errors[] = gettext("Path Strip Count must be numeric!");
	}
	if (!empty($_POST['basedir']) && (!file_exists($_POST['basedir']) || !is_dir($_POST['basedir']))) {
		$input_errors[] = gettext("Base Directory must exist and be a directory!");
	}
	if (!empty($_FILES['ulfile']) && is_uploaded_file($_FILES['ulfile']['tmp_name'])) {
		if ($_FILES['ulfile']['type'] != "text/x-patch") {
			$input_errors[] = gettext("The uploaded file must be in unified diff format.");
		}
		if ($_FILES['ulfile']['size'] > 1048576) {
			$input_errors[] = gettext("The uploaded file is too large.");
		}
	}

	if (!$input_errors) {
		$thispatch = array();

		$thispatch['descr'] = $_POST['descr'];
		$thispatch['location'] = patch_fixup_url($_POST['location']);
		if (!empty($_POST['patch'])) {
			/* Strip DOS style carriage returns from textarea input */
			$thispatch['patch'] = base64_encode(str_replace("\r", "", $_POST['patch']));
		} elseif (!empty($_FILES['ulfile']) && is_uploaded_file($_FILES['ulfile']['tmp_name'])) {
			$thispatch['patch'] = base64_encode(file_get_contents($_FILES['ulfile']['tmp_name']));
		}

		if (is_github_url($thispatch['location']) && ($_POST['pathstrip'] == 0)) {
			$thispatch['pathstrip'] = 2;
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

		write_config(gettext("System: Patches: edited a patch."));
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
$form->setMultipartEncoding();

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

$section->addInput(new Form_Input(
	'ulfile',
	'Patch file upload',
	'file',
))->setHelp('Upload patch file');

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
