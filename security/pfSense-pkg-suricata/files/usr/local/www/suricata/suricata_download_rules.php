<?php
/*
* suricata_download_rules.php
*
*  Copyright (c)  2004-2016  Electric Sheep Fencing, LLC. All rights reserved.
*
*  Redistribution and use in source and binary forms, with or without modification,
*  are permitted provided that the following conditions are met:
*
*  1. Redistributions of source code must retain the above copyright notice,
*      this list of conditions and the following disclaimer.
*
*  2. Redistributions in binary form must reproduce the above copyright
*      notice, this list of conditions and the following disclaimer in
*      the documentation and/or other materials provided with the
*      distribution.
*
*  3. All advertising materials mentioning features or use of this software
*      must display the following acknowledgment:
*      "This product includes software developed by the pfSense Project
*       for use in the pfSense software distribution. (http://www.pfsense.org/).
*
*  4. The names "pfSense" and "pfSense Project" must not be used to
*       endorse or promote products derived from this software without
*       prior written permission. For written permission, please contact
*       coreteam@pfsense.org.
*
*  5. Products derived from this software may not be called "pfSense"
*      nor may "pfSense" appear in their names without prior written
*      permission of the Electric Sheep Fencing, LLC.
*
*  6. Redistributions of any form whatsoever must retain the following
*      acknowledgment:
*
*  "This product includes software developed by the pfSense Project
*  for use in the pfSense software distribution (http://www.pfsense.org/).
*
*  THIS SOFTWARE IS PROVIDED BY THE pfSense PROJECT ``AS IS'' AND ANY
*  EXPRESSED OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
*  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR
*  PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE pfSense PROJECT OR
*  ITS CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
*  SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT
*  NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
*  LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
*  HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
*  STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
*  ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
*  OF THE POSSIBILITY OF SUCH DAMAGE.
*
*
* Portions of this code are based on original work done for the Snort package for pfSense by the following contributors:
*
* Copyright (C) 2003-2004 Manuel Kasper
* Copyright (C) 2005 Bill Marquette
* Copyright (C) 2006 Scott Ullrich (copyright assigned to ESF)
* Copyright (C) 2009 Robert Zelaya Sr. Developer
* Copyright (C) 2012 Ermal Luci  (copyright assigned to ESF)
* Copyright (C) 2014 Bill Meeks
*
*/

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("service-utils.inc");
require_once("/usr/local/pkg/suricata/suricata.inc");

global $g;

$pgtitle = "Services: Suricata - Update Rules";
require_once("head.inc");
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?if($pfsense_stable == 'yes'){echo '<p class="pgtitle">' . $pgtitle . '</p>';}?>

<form action="/suricata/suricata_download_updates.php" method="GET">

<table width="100%" border="0" cellpadding="6" cellspacing="0">
	<tr>
		<td align="center"><div id="boxarea">
		<table id="maintable" class="tabcont"  width="100%" border="0" cellpadding="6" cellspacing="0">
			<tr>
				<td class="tabcont" align="center">
				<table width="420" border="0" cellpadding="0" cellspacing="0">
					<tr>
						<td style="background:url('../themes/<?= $g['theme']; ?>/images/misc/bar_left.gif')" height="15" width="5"></td>
						<td style="background:url('../themes/<?= $g['theme']; ?>/images/misc/bar_gray.gif')" height="15" width="410">
						<table id="progholder" width='410' cellpadding='0' cellspacing='0'>
							<tr>
								<td align="left"><img border='0' src='../themes/<?= $g['theme']; ?>/images/misc/bar_blue.gif'
								width='0' height='15' name='progressbar' id='progressbar' alt='' /></td
							</tr>
						</table></td>
						<td style="background:url('../themes/<?= $g['theme']; ?>/images/misc/bar_right.gif')" height="15" width="5"></td>
					</tr>
				</table>
				</td>
			</tr>
			<tr>
				<td class="tabcont" align="center">
				<!-- status box -->
				<textarea cols="85" rows="1" name="status" id="status" wrap="soft"><?=gettext("Initializing..."); ?>.</textarea>
				<!-- command output box -->
				<textarea cols="85" rows="12" name="output" id="output" wrap="soft"></textarea>
				</td>
			</tr>
			<tr>
				<td class="tabcont" align="center" valign="middle"><input type="submit" name="return" id="return" Value="Return"></td>
			</tr>
		</table>
		</div>
		</td>
	</tr>
</table>
</form>
<?php require_once("foot.inc"); ?>
	
<?php

$suricata_gui_include = true;
include("/usr/local/pkg/suricata/suricata_check_for_rule_updates.php");

/* hide progress bar and lets end this party */
echo "\n<script type=\"text/javascript\">document.progressbar.style.visibility='hidden';\n</script>";

?>
