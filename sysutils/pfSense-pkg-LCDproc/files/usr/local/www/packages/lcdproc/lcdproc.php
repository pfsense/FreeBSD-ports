<?php
/*
	lcdproc.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2008 Mark J Crane
	Copyright (C) 2007-2009 Seth Mos <seth.mos@dds.nl>
	Copyright (C) 2009 Scott Ullrich
	Copyright (C) 2011 Michele Di Maria
	Copyright (C) 2016 ESF, LLC
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
require_once("guiconfig.inc");
require_once("/usr/local/pkg/lcdproc.inc");

$a_lcdproc = &$config['lcdproc']['item'];

/*
if ($_GET['act'] == "del") {
	if ($_GET['type'] == 'php') {
		if ($a_lcdproc[$_GET['id']]) {
			unset($a_lcdproc[$_GET['id']]);
			write_config();
			header("Location: lcdproc.php");
			exit;
		}
	}
}*/

$pgtitle = array(gettext("Services"), gettext("LCDproc"), gettext("Server"));
include("head.inc");

$tab_array = array();
$tab_array[] = array(gettext("Server"), true, "/packages/lcdproc/lcdproc.php");
$tab_array[] = array(gettext("Screens"), false, "/packages/lcdproc/lcdproc_screens.php");
display_top_tabs($tab_array);

// The constructor for Form automatically creates a submit button. If you want to suppress that
// use Form(false), of specify a different button using Form($mybutton)
$form = new Form();

// Create a new form section and give it a title
$section = new Form_Section('General Options');

// Add a checkbox to our new form section
$section->addInput(
	new Form_Checkbox(
		'enable', // checkbox name (id)
		'Enable', // checkbox label
		'Enable LCDproc at startup', // checkbox text
		$pconfig['enable'] // checkbox initial value
	)
);

// <default_value>ucom1</default_value>
// Add a selector (option) box passing the selector values in an associative array
$section->addInput(
	new Form_Select(
		'comport',
		'Com port',
		$pconfig['comport'], // Initial value.
		[
			'none'    => 'none',
			'com1'    => 'Serial COM port 1 (/dev/cua0)',
			'com2'    => 'Serial COM port 2 (/dev/cua1)',
			'com1a'   => 'Serial COM port 1 alternate (/dev/cuau0)',
			'com2a'   => 'Serial COM port 2 alternate (/dev/cuau1)',
			'ucom1'   => 'USB COM port 1 (/dev/cuaU0)',
			'ucom2'   => 'USB COM port 2 (/dev/cuaU1)',
			'lpt1'    => 'Parallel port 1 (/dev/lpt0)',
			'ugen0.2' => 'USB COM port 1 alternate (/dev/ugen0.2)',
			'ugen1.2' => 'USB COM port 2 alternate (/dev/ugen1.2)',
			'ugen1.3' => 'USB COM port 3 alternate (/dev/ugen1.3)',
			'ugen2.2' => 'USB COM port 4 alternate (/dev/ugen2.2)'
		]
	)
)->setHelp('Set the com port LCDproc should use.');


$form->add($section); // Add the section to our form
?>
<div class="infoblock">
	<?=print_info_box('For more information see: <a href="http://lcdproc.org/docs.php3">LCDproc documentation</a>.', info)?>
</div>

<?php include("foot.inc"); ?>
