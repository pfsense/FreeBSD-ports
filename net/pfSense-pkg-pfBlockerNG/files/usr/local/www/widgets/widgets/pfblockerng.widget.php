<?php
/*
	pfBlockerNG.widget.php

	pfBlockerNG
	Copyright (C) 2015 BBcan177@gmail.com
	All rights reserved.

	Based Upon pfblocker :
	Copyright 2011 Thomas Schaefer - Tomschaefer.org
	Copyright 2011 Marcello Coutinho
	Part of pfSense widgets (www.pfsense.org)

	Adapted From:
	snort_alerts.widget.php
	Copyright (C) 2009 Jim Pingle
	mod 24-07-2012
	mod 28-02-2015 by Bill Meeks

	Javascript and Integration modifications by J. Nieuwenhuizen

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
$nocsrf = true;
@require_once("/usr/local/www/widgets/include/widget-pfblockerng.inc");
@require_once("/usr/local/pkg/pfblockerng/pfblockerng.inc");
@require_once("guiconfig.inc");

pfb_global();

// Image source definition
$pfb['down']	= "<img src ='/themes/{$g['theme']}/images/icons/icon_interface_down.gif' title='No Rules are Defined using this Alias' alt='' />";
$pfb['up']	= "<img src ='/themes/{$g['theme']}/images/icons/icon_interface_up.gif' title='Rules are Defined using this Alias (# of fw rules defined)' alt='' />";
$pfb['err']	= "<img src ='/themes/{$g['theme']}/images/icons/icon_wzd_nsaved.png' title='pf Errors found.' alt='' />";

// Alternating line shading
$pfb['RowOddClass']	= "style='background-color: #FFFFFF;'";
$pfb['RowEvenClass']	= "style='background-color: #F0F0F0;'";
$pfb['RowEvenClass2']	= "style='background-color: #D0D0D0;'";
$pfb['ColClass']	= "listMRr";

$pfb['global'] = &$config['installedpackages']['pfblockerngglobal'];

// Define default widget customizations
if (!isset($pfb['global']['widget-maxfails'])) {
	$pfb['global']['widget-maxfails']	= '3';
}
if (!isset($pfb['global']['widget-maxpivot'])) {
	$pfb['global']['widget-maxpivot']	= '200';
}
if (!isset($pfb['global']['widget-sortcolumn'])) {
	$pfb['global']['widget-sortcolumn']	= 'none'; 
}
if (!isset($pfb['global']['widget-sortdir'])) {
	$pfb['global']['widget-sortdir']	= 'asc';
}
if (!isset($pfb['global']['widget-popup'])) {
	$pfb['global']['widget-popup']		= 'on';
}

// Collect variables
if (is_array($pfb['global'])) {
	$pfb['maxfails']	= $pfb['global']['widget-maxfails'];
	$pfb['maxpivot']	= $pfb['global']['widget-maxpivot'];
	$pfb['sortcolumn']	= $pfb['global']['widget-sortcolumn'];
	$pfb['sortdir']		= $pfb['global']['widget-sortdir'];
	$pfb['popup']		= $pfb['global']['widget-popup'];
}

// Save widget customizations
if ($_POST) {
	if (is_numeric($_POST['pfb_maxfails'])) {
		$pfb['global']['widget-maxfails']	= $_POST['pfb_maxfails'];
	}
	if (is_numeric($_POST['pfb_maxpivot'])) {
		$pfb['global']['widget-maxpivot']	= $_POST['pfb_maxpivot'];
	}
	if (!empty($_POST['pfb_popup'])) {
		$pfb['global']['widget-popup']		= $_POST['pfb_popup'];
	}
	if (!empty($_POST['pfb_sortcolumn'])) {
		$pfb['global']['widget-sortcolumn']	= $_POST['pfb_sortcolumn'];
	}
	if (!empty($_POST['pfb_sortdir'])) {
		$pfb['global']['widget-sortdir']	= $_POST['pfb_sortdir'];
	}
	write_config("pfBlockerNG: Saved Widget customizations via Dashboard");
	header("Location: ../../index.php");
}

// Ackwnowlege failed downloads
if (isset($_POST['pfblockerngack'])) {
	exec("/usr/bin/sed -i '' 's/FAIL/Fail/g' {$pfb['errlog']}");
	header("Location: ../../index.php");
}

// Called by Ajax to update table contents
if (isset($_GET['getNewCounts'])) {
	pfBlockerNG_get_table("js");
	return;
}

// Sort widget table according to user configuration
function pfbsort(&$array, $subkey="id", $sort_ascending=FALSE) {
	if (empty($array)) {
		return;
	}
	if (count($array)) {
		$temp_array[key($array)] = array_shift($array);
	}

	foreach ($array as $key => $val) {
		$offset = 0;
		$found = FALSE;
		foreach ($temp_array as $tmp_key => $tmp_val) {
			if (!$found and strtolower($val[$subkey]) > strtolower($tmp_val[$subkey])) {
				$temp_array = array_merge((array)array_slice($temp_array,0,$offset), array($key => $val), array_slice($temp_array,$offset));
				$found = TRUE;
			}
			$offset++;
		}
		if (!$found) {
			$temp_array = array_merge($temp_array, array($key => $val));
		}
	}

	if ($sort_ascending) {
		$array = array_reverse($temp_array);
	} else {
		$array = $temp_array;
	}
	return;
}

// Collect all pfBlockerNG statistics
function pfBlockerNG_get_counts() {
	global $config, $pfb;
	$pfb_table = array();

	/* Alias Table Definitions -	'update'	- Last Updated Timestamp
					'rule'		- Total number of Firewall rules per alias
					'count'		- Total Line Count per alias
					'packets'	- Total number of pf packets per alias */

	exec("/sbin/pfctl -vvsTables | grep -A4 'pfB_'", $pfb_pfctl);
	if (!empty($pfb_pfctl)) {
		foreach($pfb_pfctl as $line) {
			$line = trim(str_replace(array( '[', ']' ), '', $line));
			if (substr($line, 0, 1) == '-') {
				$pfb_alias = trim(strstr($line, 'pfB', FALSE));
				if (empty($pfb_alias)) {
					unset($pfb_alias);
					continue;
				}
				exec("/usr/bin/grep -cv '^1\.1\.1\.1' {$pfb['aliasdir']}/{$pfb_alias}.txt", $match);
				$pfb_table[$pfb_alias] = array('count' => $match[1], 'img' => $pfb['down']);
				exec("ls -ld {$pfb['aliasdir']}/{$pfb_alias}.txt | awk '{ print $6,$7,$8 }'", $update);
				$pfb_table[$pfb_alias]['update'] = $update[0];
				$pfb_table[$pfb_alias]['rule'] = 0;
				unset($match, $update);
				continue;
			}

			if (isset($pfb_alias)) {
				if (substr($line, 0, 9) == 'Addresses') {
					$addr = trim(substr(strrchr($line, ':'), 1));
					$pfb_table[$pfb_alias]['count'] = $addr;
					continue;
				}
				if (substr($line, 0, 11) == 'Evaluations') {
					$packets = trim(substr(strrchr($line, ':'), 1));
					$pfb_table[$pfb_alias]['packets'] = $packets;
					unset($pfb_alias);
				}
			}
		}
	}
	else {
		// Error. No pf labels found.
		$pfb['pfctl'] = TRUE;
	}

	// Determine if firewall rules are defined
	if (is_array($config['filter']['rule'])) {
		foreach ($config['filter']['rule'] as $rule) {
			// Skip disabled rules
			if (isset($rule['disabled'])) {
				continue;
			}
			if (stripos($rule['source']['address'], "pfb_") !== FALSE) {
				$pfb_table[$rule['source']['address']]['img'] = $pfb['up'];
				$pfb_table[$rule['source']['address']]['rule'] += 1;
			}
			if (stripos($rule['destination']['address'], "pfb_") !== FALSE) {
				$pfb_table[$rule['destination']['address']]['img'] = $pfb['up'];
				$pfb_table[$rule['destination']['address']]['rule'] += 1;
			}
		}
	}

	// Collect packet fence rule numbers
	exec("/sbin/pfctl -vv -sr | grep 'pfB_'", $pfrules);
	if (!empty($pfrules)) {
		foreach ($pfrules as $result) {
			// Sample : @112(0) block return in log quick on em1 from any to <pfB_PRI1:160323> label "USER_RULE: pfB_PRI1"
			if (preg_match("/@(\d+)\(\d+\).*\<(pfB_\w+):\d+\>/", $result, $rule)) {
				$pfb_table[$rule[2]]['rules'] .= $rule[1] . '|';
			}
		}
	}

	// Sort tables per sort customization
	if ($pfb['sortcolumn'] != "none") {
		if ($pfb['sortdir'] == "asc") {
			pfbsort($pfb_table, $pfb['sortcolumn'], TRUE);
		} else {
			pfbsort($pfb_table, $pfb['sortcolumn'], FALSE);
		}
	}
	return $pfb_table;
}

// Called on initial load and Ajax to update table contents
function pfBlockerNG_get_table($mode="") {
	global $pfb;
	$counter = 0; $dcounter = 1; $response = '';

	$pfb_table = pfBlockerNG_get_counts();
	if (!empty($pfb_table)) {
		foreach ($pfb_table as $pfb_alias => $values) {
			// Add firewall rules count associated with alias
			$values['img'] = $values['img'] . "<span title='Alias Firewall Rule count' ><small>({$values['rule']})</small></span>";

			// If packet fence errors found, display error.
			if ($pfb['pfctl']) {
				$values['img'] = $pfb['err'];
			}

			// Alias table popup
			if ($values['count'] > 0 && $pfb['popup'] == "on") {
				$alias_popup = rule_popup($pfb_alias, '', '', '');
				$alias_span = $alias_popup['src'];
				$alias_span_end = $alias_popup['src_end'];
			}
			else {
				$alias_span = '';
				$alias_span_end = '';
			}

			// Packet column pivot to Alerts Tab
			if ($values['packets'] > 0) {
				$rules = rtrim($values['rules'], '|');
				if ($values['packets'] > $pfb['maxpivot']) {
					$aentries = $pfb['maxpivot'];
				} else {
					$aentries = $values['packets'];
				}

				$packets  = "<a target='_new' href='/pfblockerng/pfblockerng_alerts.php?rule={$rules}&entries={$aentries}' ";
				$packets .= "style='text-decoration: underline;' title='Click to view these packets in Alerts tab' >{$values['packets']}</a>";
			}
			else {
				$packets = $values['packets'];
			}

			if ($mode == "js") {
				echo $response = $alias_span . $pfb_alias . $alias_span_end . "||" . $values['count'] . "||" . $packets . "||" . $values['update']
					. "||" . $values['img'] . "\n";
			}
			else {
				$RowClass = $counter % 2 ? $pfb['RowEvenClass'] : $pfb['RowOddClass'];
				$counter++;
				echo (" <tr {$RowClass}>
					<td class='listMRr ellipsis'>" . $alias_span . $pfb_alias . $alias_span_end . "</td>
					<td class='listMRr' align='center'>{$values['count']}</td>
					<td class='listMRr' sorttable_customkey='{$values['packets']}' align='center'>{$packets}</td>
					<td class='listMRr' align='center'>{$values['update']}</td>
					<td class='listMRr' align='center'>{$values['img']}</td>
					</tr>");
			}
		}
	}
}

// Status indicator if pfBlockerNG is enabled/disabled
if ("{$pfb['enable']}" == "on") {
	$pfb_status = "/themes/{$g['theme']}/images/icons/icon_pass.gif";
	$pfb_msg = "pfBlockerNG is Active.";
} else {
	$pfb_status = "/themes/{$g['theme']}/images/icons/icon_block.gif";
	$pfb_msg = "pfBlockerNG is Disabled.";
}

// Collect total IP/Cidr counts
$dcount = exec("cat {$pfb['denydir']}/*.txt | grep -cv '^#\|^$\|^1\.1\.1\.1'");
$pcount = exec("cat {$pfb['permitdir']}/*.txt | grep -cv '^#\|^$\|^1\.1\.1\.1'");
$mcount = exec("cat {$pfb['matchdir']}/*.txt | grep -cv '^#\|^$\|^1\.1\.1\.1'");
$ncount = exec("cat {$pfb['nativedir']}/*.txt | grep -cv '^#\|^$\|^1\.1\.1\.1'");

// Collect number of suppressed hosts
if (file_exists("{$pfb['supptxt']}")) {
	$pfbsupp_cnt = exec ("/usr/bin/grep -c ^ {$pfb['supptxt']}");
} else {
	$pfbsupp_cnt = 0;
}

// Collect any failed downloads
exec("grep $(date +%m/%d/%y) {$pfb['errlog']} | grep 'FAIL'", $results);
$results = array_reverse($results);

?>
	<!-- Widget customization settings icon -->
	<input type="hidden" id="pfblockerng-config" name="pfblockerng-config" value="" />
	<div id="pfblockerng-settings" class="widgetconfigdiv" style="display:none;outline: none;">
	<form action="/widgets/widgets/pfblockerng.widget.php" method="post" name="pfb_iform">
		<table id="widgettable" class="none" width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr>
			<td width="22%" class="vncellt" valign="top" align="right" ><input type="checkbox" name="pfb_popup" class="formfld unknown" id="pfb_popup"
				title="Enabling this option, will Popup a Table showing all of the Alias Table IPs"
				value="on" <?php if ($pfb['popup'] == "on") echo 'checked'; ?> /></td>
			<td width="78%" class="listr" ><?=gettext("Enable Alias Table Popup");?></td>
		</tr>
		<tr>
			<td width="22%" class="vncellt" valign="top" ><input type="text" size="3" name="pfb_maxfails" class="formfld unknown" id="pfb_maxfails"
				title="Tha maximum number of Failed Download Alerts to be shown. Refer to the error.log for add'l details"
				value="<?= $pfb['maxfails'] ?>" /></td>
			<td width="78%" class="listr" ><?=gettext("Enter number of download fails to display (default:3)");?></td>
		</tr>
		<tr>
			<td width="22%" class="vncellt" valign="top" ><input type="text" size="3" name="pfb_maxpivot" class="formfld unknown" id="pfb_maxpivot"
				title="The maximum number of Packets to pivot to the Alerts Tab"
				value="<?= $pfb['maxpivot'] ?>" /></td>
			<td width="78%" class="listr" ><?=gettext("Enter 'max' Packets for Alerts Tab pivot (default:200)");?></td>
		</tr>
		<tr>
			<td width="22" class="vncellt" valign="top" >
				<select name="pfb_sortcolumn" id="pfb_sortcolumn" class="formselect" title="The Column to be sorted" >
				<?php
				$pfbsort = array(	'none' => 'None', 'alias' => 'Alias', 'count' => 'Count',
							'packets' => 'Packets', 'updated' => 'Updated'
						);
				foreach ($pfbsort as $sort => $sorttype): ?>
					<option value="<?=$sort; ?>" <?php if ($sort == $pfb['sortcolumn']) echo 'selected'; ?> ><?=$sorttype; ?></option>
				<?php endforeach; ?>
				</select></td>
			<td width="78%" class="listr" ><?=gettext("Enter Sort Column");?></td>
		</tr>
		</table>

		<table id="widgettablesummary" width="100%" border="0" cellpadding="0" cellspacing="0">
		<tr>
			<td width="92%" class="vncellt" >&nbsp;<?=gettext("Sort");?>
				<input name="pfb_sortdir" type="radio" value="asc" <?php if ($pfb['sortdir'] == "asc") echo 'checked'; ?> />
					<?=gettext("Ascending");?>
				<input name="pfb_sortdir" type="radio" value="des" <?php if ($pfb['sortdir'] == "des") echo 'checked'; ?> />
					<?=gettext("Descending");?></td>
			<td width="8%" class="vncellt" valign="top" ><input id="pfb_submit" name="pfb_submit" type="submit" class="formbtns" value="Save" /></td>
		</tr>
		</table>
	</form>
	</div>

	<!-- Print widget status bar items -->
	<div class="marinarea">
	<table id="pfb_table" border="0" cellspacing="0" cellpadding="0">
		<thead>
		<tr>
			<td valign="middle">&nbsp;<img src="<?= $pfb_status ?>" width="13" height="13" border="0" title="<?=gettext($pfb_msg) ?>" alt="" /></td>
			<td valign="middle">&nbsp;&nbsp;</td>
			<td valign="middle" p style="font-size:10px">
									<?php if ($dcount != 0): ?>
										<?=gettext("Deny:"); echo("&nbsp;<strong>" . $dcount . "</strong>") ?>
									<?php endif; ?>
									<?php if ($pcount != 0): ?>
										<?=gettext("&nbsp;Permit:"); echo("&nbsp;<strong>" . $pcount . "</strong>") ?>
									<?php endif; ?>
									<?php if ($mcount != 0): ?>
										<?=gettext("&nbsp;Match:"); echo("&nbsp;<strong>" . $mcount . "</strong>"); ?>
									<?php endif; ?>
									<?php if ($ncount != 0): ?>
										<?=gettext("&nbsp;Native:"); echo("&nbsp;<strong>" . $ncount . "</strong>"); ?>
									<?php endif; ?>
									<?php if ($pfbsupp_cnt != 0): ?>
										<?=gettext("&nbsp;Supp:"); echo("&nbsp;<strong>" . $pfbsupp_cnt . "</strong>"); ?>
									<?php endif; ?></td>
			<td valign="middle">&nbsp;&nbsp;</td>
			<td valign="top"><a href="pfblockerng/pfblockerng_log.php"><img src="/themes/<?=$g['theme']; ?>/images/icons/icon_logs.gif"
				width="13" height="13" border="0" title="<?=gettext("View pfBlockerNG Logs TAB") ?>" alt="" /></a>&nbsp;
			<td valign="top">
				<?php if (!empty($results)): ?>		<!--Hide "Ack" Button when Failed Downloads are Empty-->
					<form action="/widgets/widgets/pfblockerng.widget.php" method="post" name="widget_pfblockerng_ack">
						<input type="hidden" value="clearack" name="pfblockerngack" />
						<input class="vexpl" type="image" name="pfblockerng_ackbutton" src="/themes/<?=$g['theme']; ?>/images/icons/icon_x.gif"
							width="14" height="14" border="0" title="<?=gettext("Clear Failed Downloads") ?>"/>
					</form>
				<?php endif; ?>
			</td>
		</tr>
		</thead>
	</table>
	</div>

	<table id="pfb-tblfails" width="100%" border="0" cellspacing="0" cellpadding="0">
	<tbody id="pfb-fails">
<?php

// Report any failed downloads
$counter = 0;
if (!empty($results)) {
	foreach ($results as $result) {
		$RowClass = $counter % 2 ? $pfb['RowEvenClass'] : $pfb['RowOddClass'];
		echo(" <tr " . $RowClass . "><td class='" . $pfb['ColClass'] . "'>" . $result . "</td><tr>");
		$counter++;
		if ($counter > $pfb['maxfails']) {
			// To many errors stop displaying
			echo(" <tr " . $RowClass . "><td class='" . $pfb['ColClass'] . "'>" . (count($results) - $pfb['maxfails']) . " more error(s)...</td><tr>");
			break;
		}
	}
}

?>
	<!-- Print main table header -->
	</tbody>
	</table>
	<table id="pfb-tbl" width="100%" class="sortable" border="0" cellspacing="0" cellpadding="0">
	<thead>
		<tr class="sortableHeaderRowIdentifier">
			<th class="widgetsubheader" axis="string" align="center"><?=gettext("Alias");?></th>
			<th title="The count can be a mixture of Single IPs or CIDR values" class="widgetsubheader" axis="string"
				align="center"><?=gettext("Count");?></th>
			<th title="Packet Counts can be cleared by the pfSense filter_configure() function. Make sure Rule Descriptions start with 'pfB_'"
				class="widgetsubheader" axis="string" align="center"><?=gettext("Packets");?></th>
			<th title="Last Update (Date/Time) of the Alias " class="widgetsubheader" axis="string" align="center"><?=gettext("Updated");?></th>
			<th class="widgetsubheader" axis="string" align="center"><?php echo $pfb['down']; ?><?php echo $pfb['up']; ?></th>
		</tr>
	</thead>
	<tbody id="pfbNG-entries">

<!-- Print main table body, subsequent refresh by javascript function -->
<?php pfBlockerNG_get_table(); ?>

</tbody>
</table>

<script type="text/javascript">
//<![CDATA[
<!-- update every 10000 ms -->
	var pfBlockerNGupdateDelay = 10000;

<!-- needed to display the widget settings menu -->
	selectIntLink = "pfblockerng-configure";
	textlink = document.getElementById(selectIntLink);
	textlink.style.display = "inline";
//]]>
</script>