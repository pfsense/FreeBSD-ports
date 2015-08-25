<?php
/*
	siproxd_registered_phones.php
	part of pfSense (https://www.pfSense.org/)
	Copyright (C) 2010 Jim Pingle
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
	pfSense_MODULE:	shell
*/

##|+PRIV
##|*IDENT=page-status-siproxd
##|*NAME=Status: siproxd registered phones
##|*DESCR=Allow access to the 'Status: siproxd registered phones' page.
##|*MATCH=siproxd_registered_phones.php*
##|-PRIV

require_once("guiconfig.inc");

$phonetext = file_get_contents("/var/siproxd/siproxd_registrations");
$phonedata = explode("\n", $phonetext);

if (!is_array($phonedata)) {
	$phonedata = array();
}

$activephones = array();
for ($i = 0; $i < count($phonedata); $i++) {
	list($stars, $active, $expires) = explode(":", $phonedata[$i]);
	if ($active == "1") {
		$phone = array();
		$phone["expires"] = $expires;
		$phone["real"]["type"] = $phonedata[++$i];
		$phone["real"]["user"] = $phonedata[++$i];
		$phone["real"]["host"] = $phonedata[++$i];
		$phone["real"]["port"] = $phonedata[++$i];
		$phone["nat"]["type"] = $phonedata[++$i];
		$phone["nat"]["user"] = $phonedata[++$i];
		$phone["nat"]["host"] = $phonedata[++$i];
		$phone["nat"]["port"] = $phonedata[++$i];
		$phone["registered"]["type"] = $phonedata[++$i];
		$phone["registered"]["user"] = $phonedata[++$i];
		$phone["registered"]["host"] = $phonedata[++$i];
		$phone["registered"]["port"] = $phonedata[++$i];
		$activephones[] = $phone;
	}
}

$pgtitle = array(gettext("Status"), gettext("siproxd Registered Phones"));
require("head.inc");
?>

<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>

<br />

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr><td>
<?php
	$tab_array = array();
	$tab_array[] = array(gettext("Settings"), false, "pkg_edit.php?xml=siproxd.xml&amp;id=0");
	$tab_array[] = array(gettext("Users"), false, "pkg.php?xml=siproxdusers.xml");
	$tab_array[] = array(gettext("Registered Phones"), true, "siproxd_registered_phones.php");
	display_top_tabs($tab_array);
?>
</td></tr>

<tr><td>
	<div id="mainarea">
		<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
		<thead>
			<tr>
				<td colspan="16" class="listtopic"><?php echo gettext("Currently Registered Phones") . " (" . count($activephones) . ")"; ?></td>
			</tr>
			<tr>
				<th colspan="5">Real Phone</th>
				<th colspan="5">NAT Address</th>
				<th colspan="4">Registered With</th>
				<th colspan="2">&nbsp;</th>
			</tr>
			<tr>
				<th>Type</th>
				<th>User</th>
				<th>Host</th>
				<th>Port</th>
				<th>&nbsp;</th>
				<th>Type</th>
				<th>User</th>
				<th>Host</th>
				<th>Port</th>
				<th>&nbsp;</th>
				<th>Type</th>
				<th>User</th>
				<th>Host</th>
				<th>Port</th>
				<th>&nbsp;</th>
				<th>Expires</th>
			</tr>
		</thead>

			<?php if (count($phonedata) == 0): ?>
			<tr><td colspan="16" align="center">No Phone Data Found</td></tr>
			<? elseif (count($activephones) == 0): ?>
			<tr><td colspan="16" align="center">No Active Phones</td></tr>
			<? else: ?>
			<? foreach ($activephones as $phone): ?>
			<tr>
				<td align="center" class="listlr"><? echo ($phone['real']['type']) ? $phone['real']['type'] : "sip"; ?></td>
				<td align="center" class="listr"><? echo ($phone['real']['user']) ? $phone['real']['user'] : "&nbsp;"; ?></td>
				<td align="center" class="listr"><? echo ($phone['real']['host']) ? $phone['real']['host'] : "&nbsp;"; ?></td>
				<td align="center" class="listr"><? echo ($phone['real']['port']) ? $phone['real']['port'] : "5060"; ?></td>

				<td align="center" class="list">&nbsp;</td>
				<td align="center" class="listlr"><? echo ($phone['nat']['type']) ? $phone['nat']['type'] : "sip"; ?></td>
				<td align="center" class="listr"><? echo ($phone['nat']['user']) ? $phone['nat']['user'] : "&nbsp;"; ?></td>
				<td align="center" class="listr"><? echo ($phone['nat']['host']) ? $phone['nat']['host'] : "&nbsp;"; ?></td>
				<td align="center" class="listr"><? echo ($phone['nat']['port']) ? $phone['nat']['port'] : "5060"; ?></td>

				<td align="center" class="list">&nbsp;</td>
				<td align="center" class="listlr"><? echo ($phone['registered']['type']) ? $phone['registered']['type'] : "sip"; ?></td>
				<td align="center" class="listr"><? echo ($phone['registered']['user']) ? $phone['registered']['user'] : "&nbsp;"; ?></td>
				<td align="center" class="listr"><? echo ($phone['registered']['host']) ? $phone['registered']['host'] : "&nbsp;"; ?></td>
				<td align="center" class="listr"><? echo ($phone['registered']['port']) ? $phone['registered']['port'] : "5060"; ?></td>

				<td align="center" class="list">&nbsp;</td>
				<td align="center" class="listlr"><? echo date("m/d/Y h:i:sa", $phone['expires']); ?></td>
			</tr>
			<? endforeach; ?>
			<? endif; ?>
		</table>
	</div>
</td></tr>
</table>

<?php include("fend.inc"); ?>
</body>
</html>
