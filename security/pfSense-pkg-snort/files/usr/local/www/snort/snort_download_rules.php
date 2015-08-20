<?php
/*
 * snort_download_rules.php
 *
 * Copyright (C) 2006 Scott Ullrich
 * Copyright (C) 2009 Robert Zelaya
 * Copyright (C) 2011-2012 Ermal Luci
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright
 * notice, this list of conditions and the following disclaimer in the
 * documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

require_once("guiconfig.inc");
require_once("functions.inc");
require_once("service-utils.inc");
require_once("/usr/local/pkg/snort/snort.inc");

global $g;

$pgtitle = "Services: Snort: Update Rules";
include("head.inc");
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">

<?php include("fbegin.inc"); ?>

<form action="/snort/snort_download_updates.php" method="GET">

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
<?php include("fend.inc");?>
</body>
</html>
<?php

$snort_gui_include = true;
include("/usr/local/pkg/snort/snort_check_for_rule_updates.php");

/* hide progress bar and lets end this party */
echo "\n<script type=\"text/javascript\">document.progressbar.style.visibility='hidden';\n</script>";

?>
