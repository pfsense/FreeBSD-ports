<?php
/* $Id$ */
/*
	status_mail_report_add_graph.php
	Part of pfSense
	Copyright (C) 2011-2014 Jim Pingle <jimp@pfsense.org>
	Portions Copyright (C) 2007-2011 Seth Mos <seth.mos@dds.nl>
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
##|*IDENT=page-status-mailreportsaddgraph
##|*NAME=Status: Email Reports: Add Graph page
##|*DESCR=Allow access to the 'Status: Email Reports: Add Graph' page.
##|*MATCH=status_mail_report_add_graph.php*
##|-PRIV

require("guiconfig.inc");
require_once("filter.inc");
require("shaper.inc");
require_once("rrd.inc");
require_once("mail_reports.inc");

/* if the rrd graphs are not enabled redirect to settings page */
if(! isset($config['rrd']['enable'])) {
	header("Location: status_rrd_graph_settings.php");
}

$reportid = $_REQUEST['reportid'];
$id = $_REQUEST['id'];

if (!is_array($config['mailreports']['schedule']))
	$config['mailreports']['schedule'] = array();

$a_mailreports = &$config['mailreports']['schedule'];

if (!isset($reportid) || !isset($a_mailreports[$reportid])) {
	header("Location: status_mail_report.php");
	return;
}

if (!is_array($a_mailreports[$reportid]['row']))
	$a_mailreports[$reportid]['row'] = array();
$a_graphs = $a_mailreports[$reportid]['row'];

if (isset($id) && $a_graphs[$id]) {
	$pconfig = $a_graphs[$id];
} else {
	$pconfig = array();
}

if (isset($id) && !($a_graphs[$id])) {
	header("Location: status_mail_report_edit.php?id={$reportid}");
	return;
}




$rrddbpath = "/var/db/rrd/";
chdir($rrddbpath);
$databases = glob("*.rrd");

$now = time();

/* sort names reverse so WAN comes first */
rsort($databases);

/* these boilerplate databases are required for the other menu choices */
$dbheader = array("allgraphs-traffic.rrd",
		"allgraphs-quality.rrd",
		"allgraphs-wireless.rrd",
		"allgraphs-cellular.rrd",
		"allgraphs-vpnusers.rrd",
		"captiveportal-allgraphs.rrd",
		"allgraphs-packets.rrd",
		"system-allgraphs.rrd",
		"system-throughput.rrd",
		"outbound-quality.rrd",
		"outbound-packets.rrd",
		"outbound-traffic.rrd");

/* additional menu choices for the custom tab */
$dbheader_custom = array("system-throughput.rrd");

foreach($databases as $database) {
	if(stristr($database, "-wireless")) {
		$wireless = true;
	}
	if(stristr($database, "-queues")) {
		$queues = true;
	}
	if(stristr($database, "-cellular") && !empty($config['ppps'])) {
		$cellular = true;
	}
	if(stristr($database, "-vpnusers")) {
		$vpnusers = true;
	}
	if(stristr($database, "captiveportal-") && isset($config['captiveportal']['enable'])) {
		$captiveportal = true;
	}
}
/* append the existing array to the header */
$ui_databases = array_merge($dbheader, $databases);
$custom_databases = array_merge($dbheader_custom, $databases);

$styles = array('inverse' => gettext('Inverse'),
		'absolute' => gettext('Absolute'));
$graphs = array("eighthour", "day", "week", "month", "quarter", "year", "fouryear");
$periods = array("absolute" => gettext("Absolute Timespans"), "current" => gettext("Current Period"), "previous" => gettext("Previous Period"));
$graph_length = array(
	"eighthour" => 28800,
	"day" => 86400,
	"week" => 604800,
	"month" => 2764800,
	"quarter" => 8035200,
	"year" => 31622400,
	"fouryear" => 126489600);

if ($_POST) {
	unset($_POST['__csrf_magic']);
	$pconfig = $_POST;

	if (isset($id) && $a_graphs[$id])
		$a_graphs[$id] = $pconfig;
	else
		$a_graphs[] = $pconfig;

	$a_mailreports[$reportid]['row'] = $a_graphs;

	write_config();
	header("Location: status_mail_report_edit.php?id={$reportid}");
	return;
}


$pgtitle = array(gettext("Status"),gettext("Add Email Report Graph"));
include("head.inc");
?>
<body link="#0000CC" vlink="#0000CC" alink="#0000CC">
<?php include("fbegin.inc"); ?>
<table width="100%" border="0" cellpadding="0" cellspacing="0">
	<tr><td><div id="mainarea">
	<form action="status_mail_report_add_graph.php" method="post" name="iform" id="iform">
	<table class="tabcont" width="100%" border="0" cellpadding="1" cellspacing="1">
		<tr>
			<td class="listtopic" colspan="2">Graph Settings</td>
		</tr>
		<tr>
			<td width="20%" class="listhdr">
				<?=gettext("Graphs:");?>
			</td>
			<td width="80%" class="listhdr">
				<select name="graph" class="formselect" style="z-index: -10;">
				<?php
				foreach ($custom_databases as $db => $database) {
					$optionc = explode("-", $database);
					$optionc[1] = str_replace(".rrd", "", $optionc[1]);
					$friendly = convert_friendly_interface_to_friendly_descr(strtolower($optionc[0]));
					if(!empty($friendly)) {
						$optionc[0] = $friendly;
					}
					$prettyprint = ucwords(implode(" :: ", $optionc));
					echo "<option value=\"{$database}\"";
					if ($pconfig['graph'] == $database) {
						echo " selected";
					}
					echo ">" . htmlspecialchars($prettyprint) . "</option>\n";
				}
				?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="listhdr">
				<?=gettext("Style:");?>
			</td>
			<td class="listhdr">
				<select name="style" class="formselect" style="z-index: -10;">
				<?php
				foreach ($styles as $style => $styled) {
					echo "<option value=\"$style\"";
					if ($style == $pconfig['style']) echo " selected";
					echo ">" . htmlspecialchars($styled) . "</option>\n";
				}
				?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="listhdr">
				<?=gettext("Time Span:");?>
			</td>
			<td class="listhdr">
				<select name="timespan" class="formselect" style="z-index: -10;">
				<?php
				foreach (array_keys($graph_length) as $timespan) {
					$pconfig['timespan'] = fixup_graph_timespan($pconfig['timespan']);
					echo "<option value=\"$timespan\"";
					if ($timespan == $pconfig['timespan']) echo " selected";
					echo ">" . htmlspecialchars(ucwords($timespan)) . "</option>\n";
				}
				?>
				</select>
			</td>
		</tr>
		<tr>
			<td class="listhdr">
				<?=gettext("Period:");?>
			</td>
			<td class="listhdr">
				<select name="period" class="formselect" style="z-index: -10;">
				<?php
				foreach ($periods as $period => $value) {
					echo "<option value=\"$period\"";
					if ($period == $pconfig['period']) echo " selected";
					echo ">" . htmlspecialchars($value) . "</option>\n";
				}
				?>
				</select>
			</td>
		</tr>
		<tr>
			<td colspan="2" align="center">
			<input name="Submit" type="submit" class="formbtn" value="<?=gettext("Save");?>">
			<a href="status_mail_report_edit.php?id=<?php echo $reportid;?>"><input name="cancel" type="button" class="formbtn" value="<?=gettext("Cancel");?>"></a>
			<input name="reportid" type="hidden" value="<?=htmlspecialchars($reportid);?>">
			<?php if (isset($id) && $a_graphs[$id]): ?>
			<input name="id" type="hidden" value="<?=htmlspecialchars($id);?>">
			<?php endif; ?>
			</td>
			<td></td>
		</tr>
	</table>
	</form>
	</div></td></tr>
</table>

<?php include("fend.inc"); ?>
</body>
</html>
