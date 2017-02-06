<?php
/*
 * suricata_download_rules.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2006-2016 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2003-2004 Manuel Kasper
 * Copyright (c) 2005 Bill Marquette
 * Copyright (c) 2009 Robert Zelaya Sr. Developer
 * Copyright (c) 2014 Bill Meeks
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
