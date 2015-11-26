<?php
/*
	pfBlockerNG.widget.php

	pfBlockerNG
	Copyright (c) 2015 BBcan177@gmail.com
	All rights reserved.

	Based Upon pfblocker :
	Copyright (c) 2011 Thomas Schaefer
	Copyright (c) 2011 Marcello Coutinho

	Adapted From:
	snort_alerts.widget.php
	Copyright (c) 2015 Electric Sheep Fencing, LLC. All rights reserved.
	Copyright (c) 2015 Bill Meeks

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
@require_once('/usr/local/www/widgets/include/widget-pfblockerng.inc');
@require_once('/usr/local/pkg/pfblockerng/pfblockerng.inc');
@require_once('guiconfig.inc');

pfb_global();

// Image source definition
$pfb['down']	= "<img src ='/themes/{$g['theme']}/images/icons/icon_interface_down.gif' title='No Rules are Defined using this Alias' alt='' />";
$pfb['up']	= "<img src ='/themes/{$g['theme']}/images/icons/icon_interface_up.gif' title='Rules are Defined using this Alias (# of fw rules defined)' alt='' />";
$pfb['err']	= "<img src ='/themes/{$g['theme']}/images/icons/icon_wzd_nsaved.png' title='pf Errors found.' alt='' />";

// Alternating line shading
$pfb['RowOddClass']	= "style='background-color: #FFFFFF;'";
$pfb['RowEvenClass']	= "style='background-color: #F0F0F0;'";
$pfb['RowEvenClass2']	= "style='background-color: #D0D0D0;'";
$pfb['ColClass']	= 'listMRr';

// Widget customizations
$wglobal_array = array('popup' => 'off', 'sortcolumn' => 'none', 'sortdir' => 'asc', 'maxfails' => 3, 'maxpivot' => 200);
$pfb['wglobal'] = &$config['installedpackages']['pfblockerngglobal'];
foreach ($wglobal_array as $type => $value) {
	$pfb[$type] = $pfb['wglobal']['widget-' . "{$type}"] ?: $value;
}

// Save widget customizations
if (isset($_POST['pfb_submit'])) {
	$pfb['wglobal']['widget-popup']			= htmlspecialchars($_POST['pfb_popup']) ?: 'off';
	$pfb['wglobal']['widget-sortcolumn']		= htmlspecialchars($_POST['pfb_sortcolumn']) ?: 'none';
	$pfb['wglobal']['widget-sortdir']		= htmlspecialchars($_POST['pfb_sortdir']) ?: 'asc';

	if (ctype_digit(htmlspecialchars($_POST['pfb_maxfails']))) {
		$pfb['wglobal']['widget-maxfails']	= htmlspecialchars($_POST['pfb_maxfails']);
	}
	if (ctype_digit(htmlspecialchars($_POST['pfb_maxpivot']))) {
		$pfb['wglobal']['widget-maxpivot']	= htmlspecialchars($_POST['pfb_maxpivot']);
	}

	write_config('pfBlockerNG: Saved Widget customizations via Dashboard');
	header('Location: ../../index.php');
}

// Ackwnowlege failed downloads
if (isset($_POST['pfblockerngack'])) {
	exec("{$pfb['sed']} -i '' 's/FAIL/Fail/g' {$pfb['errlog']}");
	header('Location: ../../index.php');
}

// Called by Ajax to update table contents
if (isset($_GET['getNewCounts'])) {
	pfBlockerNG_get_table('js');
	return;
}

// Reset DNSBL Alias packet counters
if (isset($_POST['pfblockerngdnsblclear'])) {
	$dnsbl_info = array_map('str_getcsv', @file("{$pfb['dnsbl_info']}"));
	if (!empty ($dnsbl_info)) {
		$handle = fopen("{$pfb['dnsbl_info']}", 'w');
		foreach ($dnsbl_info as $line) {
			if (substr($line[0], 0, 1) != '#') {
				$line[3] = '0';
			}
			fputcsv($handle, $line);
		}
		fclose ($handle);
	}
	header('Location: ../../index.php');
}

// Sort widget table according to user configuration
function pfbsort(&$array, $subkey='id', $sort_ascending=FALSE) {
	if (empty($array)) {
		return;
	}
	if (count($array)) {
		$temp_array[key($array)] = array_shift($array);
	}

	if ($subkey == 'alias') {
		$subkey = 0;
	} 

	foreach ($array as $key => $val) {
		$offset = 0;
		$found = FALSE;
		foreach ($temp_array as $tmp_key => $tmp_val) {
			if (!$found && strtolower($val[$subkey]) > strtolower($tmp_val[$subkey])) {
				$temp_array = array_merge((array)array_slice($temp_array, 0, $offset), array($key => $val), array_slice($temp_array, $offset));
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
	$pfb_table = $pfb_dtable = array();

	/* Alias Table Definitions -	'update'	- Last Updated Timestamp
					'rule'		- Total number of Firewall rules per alias
					'count'		- Total Line Count per alias
					'packets'	- Total number of pf packets per alias */

	exec("{$pfb['pfctl']} -vvsTables | {$pfb['grep']} -A4 'pfB_'", $pfb_pfctl);
	if (!empty($pfb_pfctl)) {
		foreach($pfb_pfctl as $line) {
			$line = trim(str_replace(array( '[', ']' ), '', $line));
			if (substr($line, 0, 1) == '-') {
				$pfb_alias = trim(strstr($line, 'pfB', FALSE));
				if (empty($pfb_alias)) {
					unset($pfb_alias);
					continue;
				}
				exec("{$pfb['grep']} -cv '^1\.1\.1\.1$' {$pfb['aliasdir']}/{$pfb_alias}.txt", $match);
				$pfb_table[$pfb_alias] = array('count' => $match[1], 'img' => $pfb['down']);
				exec("{$pfb['ls']} -ld {$pfb['aliasdir']}/{$pfb_alias}.txt | {$pfb['awk']} '{ print $6,$7,$8 }'", $update);
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
		$pfb['pfctlerr'] = TRUE;
	}

	// Determine if firewall rules are defined
	if (isset($config['filter']['rule'])) {
		foreach ($config['filter']['rule'] as $rule) {
			// Skip disabled rules
			if (isset($rule['disabled'])) {
				continue;
			}
			if (stripos($rule['source']['address'], 'pfb_') !== FALSE) {
				$pfb_table[$rule['source']['address']]['img'] = $pfb['up'];
				$pfb_table[$rule['source']['address']]['rule'] += 1;
			}
			if (stripos($rule['destination']['address'], 'pfb_') !== FALSE) {
				$pfb_table[$rule['destination']['address']]['img'] = $pfb['up'];
				$pfb_table[$rule['destination']['address']]['rule'] += 1;
			}
		}
	}

	// Collect packet fence rule numbers
	exec("{$pfb['pfctl']} -vv -sr | {$pfb['grep']} 'pfB_'", $pfrules);
	if (!empty($pfrules)) {
		foreach ($pfrules as $result) {
			// Sample : @112(0) block return in log quick on em1 from any to <pfB_PRI1:160323> label "USER_RULE: pfB_PRI1"
			$id = strstr($result, '(', FALSE);
			$id = ltrim(strstr($id, ')', TRUE), '(');
			$descr = ltrim(stristr($result, '<pfb_', FALSE), '<');
			$descr = strstr($descr, ':', TRUE);

			if (!empty($id) && !empty($descr) && strpos($pfb_table[$descr]['rules'], $id) === FALSE) {
				$pfb_table[$descr]['rules'] .= $id . '|';
			}
		}
	}

	// DNSBL collect statistics
	if ($pfb['enable'] == 'on' && $pfb['dnsbl'] == 'on' && file_exists ("{$pfb['dnsbl_info']}")) {
		$dnsbl_info = array_map('str_getcsv', @file("{$pfb['dnsbl_info']}"));
		if (!empty($dnsbl_info)) {
			foreach ($dnsbl_info as $line) {
				if (substr($line[0], 0, 1) != '#') {
					if ($line[2] == 'disabled') {
						$pfb_dtable[$line[0]] = array ('count' => 'disabled', 'img' => $pfb['down']);
					} else {
						$pfb_dtable[$line[0]] = array ('count' => $line[2], 'img' => $pfb['up']);
					}
					$pfb_dtable[$line[0]]['update'] = "{$line[1]}";
					$pfb_dtable[$line[0]]['packets'] = "{$line[3]}";
				}
			}
		}
	}

	// Sort tables per sort customization
	if ($pfb['sortcolumn'] != 'none') {
		if ($pfb['sortdir'] == 'asc') {
			pfbsort($pfb_table, $pfb['sortcolumn'], FALSE);
			pfbsort($pfb_dtable, $pfb['sortcolumn'], FALSE);
		} else {
			pfbsort($pfb_table, $pfb['sortcolumn'], TRUE);
			pfbsort($pfb_dtable, $pfb['sortcolumn'], TRUE);
		}
	}
	$pfb_table = array_merge($pfb_table, $pfb_dtable);
	return $pfb_table;
}

// Called on initial load and Ajax to update table contents
function pfBlockerNG_get_table($mode='') {
	global $pfb;
	$counter = 0; $dcounter = 1; $response = '';

	$pfb_table = pfBlockerNG_get_counts();
	if (!empty($pfb_table)) {
		foreach ($pfb_table as $pfb_alias => $values) {
			if (strpos($pfb_alias, 'DNSBL_') !== FALSE) {
				$alias_span = $alias_span_end = '';
				$packets = $values['packets'];
				$dnsbl = TRUE;
			} else {
				// Add firewall rules count associated with alias
				$values['img'] = $values['img'] . "<span title='Alias Firewall Rule count' ><small>({$values['rule']})</small></span>";

				// If packet fence errors found, display error.
				if ($pfb['pfctlerr']) {
					$values['img'] = $pfb['err'];
				}

				// Alias table popup
				if ($values['count'] > 0 && $pfb['popup'] == 'on') {
					$alias_popup = rule_popup($pfb_alias, '', '', '');
					$alias_span = $alias_popup['src'];
					$alias_span_end = $alias_popup['src_end'];
				}
				else {
					$alias_span = $alias_span_end = '';
				}

				// Packet column pivot to Alerts Tab
				if ($values['packets'] > 0) {
					$rules = rtrim($values['rules'], '|');
					if ($values['packets'] > $pfb['maxpivot']) {
						$aentries = $pfb['maxpivot'];
					} else {
						$aentries = $values['packets'];
					}

					$packets  = "<a target='_blank' href='/pfblockerng/pfblockerng_alerts.php?rule={$rules}&entries={$aentries}' ";
					$packets .= "title='Click to view these packets in Alerts tab' >{$values['packets']}</a>";
				}
				else {
					$packets = $values['packets'];
				}
			}

			if ($mode == 'js') {
				echo $response = "{$alias_span}{$pfb_alias}{$alias_span_end}||{$values['count']}||{$packets}||{$values['update']}||{$values['img']}\n";
			}
			else {
				// Print darker shading for DNSBL
				if ($dnsbl) {
					$RowClass = $dcounter % 2 ? $pfb['RowEvenClass2'] : $pfb['RowOddClass'];
					$dcounter++;
				} else {
					$RowClass = $counter % 2 ? $pfb['RowEvenClass'] : $pfb['RowOddClass'];
					$counter++;
				}
				echo (" <tr {$RowClass}>
					<td class='listMRr ellipsis'>{$alias_span}{$pfb_alias}{$alias_span_end}</td>
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
if ($pfb['enable'] == 'on') {
	$mode = 'pass';
	$pfb_msg = 'pfBlockerNG is Active.';

	if ($pfb['config']['enable_dup'] == 'on') {
		// Check Masterfile Database Sanity
		$db_sanity = exec("{$pfb['grep']} 'Sanity check' {$pfb['logdir']}/pfblockerng.log | {$pfb['grep']} -o 'PASSED' | tail -1");
		if ($db_sanity != 'PASSED') {
			$mode = 'reject';
			$pfb_msg = 'pfBlockerNG deDuplication is out of sync. Perform a Force Reload to correct.';
		}
	}
} else {
	$mode = 'block';
	$pfb_msg = 'pfBlockerNG is Disabled.';
}
$pfb_status = "/themes/{$g['theme']}/images/icons/icon_{$mode}.gif";

// Status indicator if DNSBL is actively running
if ($pfb['dnsbl'] == 'on' && $pfb['unbound_state'] == 'on' && $pfb['enable'] == 'on' &&
    strpos(file_get_contents("{$pfb['dnsbldir']}/unbound.conf"), 'pfb_dnsbl') !== FALSE) {
	$mode = 'pass';
	$dnsbl_msg = 'DNSBL is Active.';
} else {
	$mode = 'block';
	$dnsbl_msg = 'DNSBL is Disabled.';
}
$dnsbl_status = "/themes/{$g['theme']}/images/icons/icon_{$mode}.gif";

// Collect total IP/Cidr counts
$dcount = exec("{$pfb['cat']} {$pfb['denydir']}/*.txt | {$pfb['grep']} -cv '^#\|^$\|^1\.1\.1\.1$'");
$pcount = exec("{$pfb['cat']} {$pfb['permitdir']}/*.txt | {$pfb['grep']} -cv '^#\|^$\|^1\.1\.1\.1$'");
$mcount = exec("{$pfb['cat']} {$pfb['matchdir']}/*.txt | {$pfb['grep']} -cv '^#\|^$\|^1\.1\.1\.1$'");
$ncount = exec("{$pfb['cat']} {$pfb['nativedir']}/*.txt | {$pfb['grep']} -cv '^#\|^$\|^1\.1\.1\.1$'");
$scount = exec("{$pfb['grep']} -c ^ {$pfb['dnsbl_file']}.conf");
$maxver = exec("grep -o 'Last-.*' /var/log/pfblockerng/maxmind_ver");

// Collect number of suppressed hosts
$pfbsupp_cnt = 0;
if (file_exists("{$pfb['supptxt']}")) {
	$pfbsupp_cnt = exec("{$pfb['grep']} -c ^ {$pfb['supptxt']}");
}

// Collect any failed downloads
exec("{$pfb['grep']} 'FAIL' {$pfb['errlog']} | {$pfb['grep']} $(date +%m/%d/%y)", $results);
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
				title="The maximum number of Failed Download Alerts to be shown. Refer to the error.log for add'l details"
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
				$pfbsort = array( 'none' => 'None', 'alias' => 'Alias', 'count' => 'Count', 'packets' => 'Packets', 'updated' => 'Updated' );
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
	<table id="pfb_table" width="100%" border="0" cellspacing="0" cellpadding="0">
		<thead>
		<tr>
			<td style="font-size:10px;white-space: nowrap;">&nbsp;<img src="<?= $pfb_status ?>"
				width="13" height="13" border="0" title="<?=gettext($pfb_msg) ?>" alt="" />

				<?=gettext("&nbsp;") ?>
				<?php if ($dcount != 0): ?>
					<?php echo("IP- Deny: <strong>{$dcount}</strong>"); ?>
				<?php endif; ?>
				<?php if ($pcount != 0): ?>
					<?php echo("Permit: <strong>{$pcount}</strong>"); ?>
				<?php endif; ?>
				<?php if ($mcount != 0): ?>
					<?php echo("Match: <strong>{$mcount}</strong>"); ?>
				<?php endif; ?>
					<?php if ($ncount != 0): ?>
				<?php echo("Native: <strong>{$ncount}</strong>"); ?>
					<?php endif; ?>
				<?php if ($pfbsupp_cnt != 0): ?>
					<?php echo("Supp: <strong>{$pfbsupp_cnt}</strong>"); ?>
				<?php endif; ?>
				<?=gettext("&nbsp;") ?>

				<a target='_blank' href="pfblockerng/pfblockerng_log.php"><img src="/themes/<?=$g['theme']; ?>/images/icons/icon_logs.gif"
					width="13" height="13" border="0" title="<?=gettext("View pfBlockerNG Logs TAB") ?>" alt="" /></a>&nbsp;

				<?php if (!empty($results)): ?>		<!--Hide "Ack" Button when Failed Downloads are Empty-->
					<form  style="display:inline;" action="/widgets/widgets/pfblockerng.widget.php" method="post" name="widget_pfblockerng_ack">
						<input type="hidden" value="clearack" name="pfblockerngack" />
						<input class="vexpl" type="image" name="pfblockerng_ackbutton" src="/themes/<?=$g['theme']; ?>
							/images/icons/icon_x.gif" width="14" height="14" border="0" title="<?=gettext("Clear Failed Downloads") ?>"/>
					</form>
				<?php endif; ?>
			</td>
		</tr>

		<?php if ($pfb['dnsbl'] == 'on'): ?>	<!--Enable DNSBL widget statistics if enabled-->
		<tr>
			<td style="font-size:10px">&nbsp;<img src="<?= $dnsbl_status ?>" width="13" height="13" border="0"
				title="<?=gettext($dnsbl_msg); ?>" alt="" />
				<?php if ($scount != 0): ?>
					<?php echo("&nbsp;&nbsp;DNSBL- <strong>{$scount}</strong>&nbsp;&nbsp;"); ?>
				<?php endif; ?>
				<form style="display:inline"; action="/widgets/widgets/pfblockerng.widget.php" method="post" name="widget_pfblockerng_dnsblclear">
					<input type="hidden" value="dnsblclear" name="pfblockerngdnsblclear" />
					<input class="vexpl" type="image" name="dnsblclearbutton" src="/themes/<?=$g['theme']; ?>/images/icons/icon_x.gif"
						width="14" height="14" border="0" title="<?=gettext("Clear DNSBL Packets") ?>"/>
				</form>
			</td>
		</tr>
		<?php endif; ?>

		<tr>
			<td >
				<?php echo "<br />&nbsp;MaxMind: {$maxver}"; ?>
			</td>
		</tr>
		</thead>
	</table>
	</div>

	<table id="pfb-tblfails" width="100%" border="0" cellspacing="0" cellpadding="0">
	<tbody id="pfb-fails">
<?php

// Report any failed downloads
if (!empty($results)) {
	$counter = 1;
	$entries = count($results);
	foreach ($results as $result) {
		$RowClass = $counter % 2 ? $pfb['RowEvenClass'] : $pfb['RowOddClass'];
		if ($counter > $pfb['maxfails'] && $entries > $pfb['maxfails']) {
			// To many errors stop displaying
			echo("<tr {$RowClass}><td class='{$pfb['ColClass']}'>" . ($entries - $pfb['maxfails']) . ' more error(s)...</td><tr>');
			break;
		}
		echo("<tr {$RowClass}><td class='{$pfb['ColClass']}'>{$result}</td><tr>");
		$counter++;
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