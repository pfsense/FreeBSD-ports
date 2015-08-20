<?php
/* $Id$ */
/*
	openbgpd_raw.php
	part of pfSense (https://www.pfsense.org/)
    Copyright (C) 2009 Aarno Aukia (aarnoaukia@gmail.com)
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

require("guiconfig.inc");
require("openbgpd.inc");

global $config;

if (isset($_POST['openbgpd_raw'])) {
  openbgpd_put_raw_config($_POST['openbgpd_raw']);
  write_config();
  openbgpd_install_conf();
}

$openbgpd_raw = openbgpd_get_raw_config();

if ($config['version'] >= 6)
	$pgtitle = array("OpenBGPD", "Raw config");
else
	$pgtitle = "OpenBGPD: Raw config";

include("head.inc");

?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>

<?php
	if ($config['version'] < 6)
		echo '<p class="pgtitle">' . $pgtitle . '</font></p>';
?>

<?php if ($savemsg) print_info_box($savemsg); ?>

<div id="mainlevel">
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Settings"), false, "/pkg_edit.php?xml=openbgpd.xml&id=0");
	$tab_array[] = array(gettext("Neighbors"), false, "/pkg.php?xml=openbgpd_neighbors.xml");
	$tab_array[] = array(gettext("Groups"), false, "/pkg.php?xml=openbgpd_groups.xml");
	$tab_array[] = array(gettext("Raw config"), true, "/openbgpd_raw.php");
	$tab_array[] = array(gettext("Status"), false, "/openbgpd_status.php");
	display_top_tabs($tab_array);
?>
</table>

<table width="100%" border="0" cellpadding="0" cellspacing="0">
  <form action="openbgpd_raw.php" method="post" name="iform" id="iform">
   <tr>
    <td class="tabcont" >
      You can edit the raw bgpd.conf here.<br>
      Note: Once you click "Save" below, the assistant (in the "Settings", "Neighbors" and "Groups" tabs above) will be overridden with whatever you type here. To get back the assisted config save this form below once with an empty input field.
     </td>
   </tr>
   <tr>
    <td class="tabcont" >
      <textarea name="openbgpd_raw" rows="40" cols="80"><? echo $openbgpd_raw; ?></textarea>
     </td>
    </tr>
   <tr>
    <td>
      <input name="Submit" type="submit" class="formbtn" value="Save"> <input class="formbtn" type="button" value="Cancel" on
      click="history.back()">
    </td>
   </tr>
  </form>
</table>

</div>

<?php include("fend.inc"); ?>

</body>
</html>
