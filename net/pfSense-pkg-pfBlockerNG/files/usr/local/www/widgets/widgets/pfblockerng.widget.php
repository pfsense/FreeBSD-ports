<?php
/*
	pfBlockerNG.widget.php

	pfBlockerNG
	Copyright (c) 2015-2016 BBcan177@gmail.com
	All rights reserved.

	Based Upon pfblocker :
	Copyright (c) 2011 Thomas Schaefer
	Copyright (c) 2011 Marcello Coutinho

	Adapted From:
	snort_alerts.widget.php
	Copyright (c) 2016 Electric Sheep Fencing, LLC. All rights reserved.
	Copyright (c) 2016 Bill Meeks

	Javascript and Integration modifications by J. Nieuwenhuizen and J. Van Breedam

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
$pfb['down']	= '<i class="fa fa-level-down" title="No Rules are Defined using this Alias"></i>';
$pfb['up']	= '<i class="fa fa-level-up text-success" title="Rules are Defined using this Alias (# of fw rules defined)"></i>';
$pfb['err']	= '<i class="fa fa-minus-circle text-danger" title="pf Errors found."></i>';

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
	header("Location: /");
	exit(0);
}

// Ackwnowlege failed downloads
if ($_POST['pfblockerngack']) {
	exec("{$pfb['sed']} -i '' 's/FAIL/Fail/g' {$pfb['errlog']}");
	header("Location: /");
	exit(0);
}

// Called by Ajax to update table contents
if (isset($_GET['getNewCounts'])) {
	pfBlockerNG_get_table('js');
	return;
}

// Reset DNSBL Alias packet counters
if ($_POST['pfblockerngdnsblclear']) {
	$dnsbl_info = array_map('str_getcsv', @file("{$pfb['dnsbl_info']}"));
	if (!empty ($dnsbl_info)) {
		$handle = @fopen("{$pfb['dnsbl_info']}", 'w');
		foreach ($dnsbl_info as $line) {
			if (substr($line[0], 0, 1) != '#') {
				$line[3] = '0';
			}
			fputcsv($handle, $line);
		}
		@fclose ($handle);
	}
	header("Location: /");
	exit(0);
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
					'packets'	- Total number of pf packets per alias
					'id'		- Alias key value				*/

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

	// Collect pfB Alias ID for popup
	if (isset($config['aliases']['alias'])) {
		foreach ($config['aliases']['alias'] as $key => $alias) {
			if (isset($pfb_table[$alias['name']])) {
				$pfb_table[$alias['name']]['id'] = $key;
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
				$packets = $values['packets'];
				$dnsbl = TRUE;
			} else {
				// Add firewall rules count associated with alias
				$values['img'] = $values['img'] . '<span title="Alias Firewall Rule count"></span>';
				if ($values['rule'] > 0) {
					$values['img'] .= "&nbsp;&nbsp;<small>({$values['rule']})</small>";
				}

				// If packet fence errors found, display error.
				if ($pfb['pfctlerr']) {
					$values['img'] = $pfb['err'];
				}

				// Alias table popup
				if ($values['count'] > 0 && $pfb['popup'] == 'on') {
					$pfb_alias = "<a href=\"/firewall_aliases_edit.php?id={$values['id']}\" data-popover=\"true\" "
						. " data-trigger=\"hover focus\" title=\"pfBlockerNG Alias details\" data-content=\""
						. alias_info_popup($values['id']) . "\" data-html=\"true\">{$pfb_alias}</a>";
				}

				// Packet column pivot to Alerts Tab
				if ($values['packets'] > 0) {
					$rules = rtrim($values['rules'], '|');
					if ($values['packets'] > $pfb['maxpivot']) {
						$aentries = $pfb['maxpivot'];
					} else {
						$aentries = $values['packets'];
					}

					$packets  = "<a target=\"_blank\" href=\"/pfblockerng/pfblockerng_alerts.php?rule={$rules}&entries={$aentries}\" ";
					$packets .= "title=\"Click to view these packets in Alerts tab\" >{$values['packets']}</a>";
				}
				else {
					$packets = $values['packets'];
				}
			}

			if ($mode == 'js') {
				print $response = "{$pfb_alias}||{$values['count']}||{$packets}||{$values['update']}||{$values['img']}\n";
			}
			else {
				print ("<tr>
					<td><small>{$pfb_alias}</small></td>
					<td><small>{$values['count']}</small></td>
					<td><small>{$packets}</small></td>
					<td><small>{$values['update']}</small></td>
					<td>{$values['img']}</td>
					</tr>");
			}
		}
	}
}

// Status indicator if pfBlockerNG is enabled/disabled
if ($pfb['enable'] == 'on') {
	$pfb_status = 'fa fa-check-circle text-success';
	$pfb_msg = 'pfBlockerNG is Active.';

	if ($pfb['config']['enable_dup'] == 'on') {
		// Check Masterfile Database Sanity
		$db_sanity = exec("{$pfb['grep']} 'Sanity check' {$pfb['logdir']}/pfblockerng.log | {$pfb['grep']} -o 'PASSED' | tail -1");
		if ($db_sanity != 'PASSED') {
			$pfb_status = 'fa fa-exclamation-circle text-warning';
			$pfb_msg = 'pfBlockerNG deDuplication is out of sync. Perform a Force Reload to correct.';
		}
	}
} else {
	$pfb_status = 'fa fa-times-circle text-danger';
	$pfb_msg = 'pfBlockerNG is Disabled.';
}

// Status indicator if DNSBL is actively running
if ($pfb['dnsbl'] == 'on' && $pfb['unbound_state'] == 'on' && $pfb['enable'] == 'on' &&
    strpos(file_get_contents("{$pfb['dnsbldir']}/unbound.conf"), 'pfb_dnsbl') !== FALSE) {
	$dnsbl_status = 'fa fa-check-circle text-success';
	$dnsbl_msg = 'DNSBL is Active.';
} else {
	$dnsbl_status = 'fa fa-times-circle text-danger';
	$dnsbl_msg = 'DNSBL is Disabled.';
}

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
$entries = count($results);
?>

<form id="formicons" action="/widgets/widgets/pfblockerng.widget.php" method="post" class="form-horizontal">
<input type="hidden" name="pfblockerngack" id="pfblockerngack" value="">
<input type="hidden" name="pfblockerngdnsblclear" id="pfblockerngdnsblclear" value="">

	<!-- Print failed downloads (if any) -->
	<?php if (!empty($results)): ?>
		<ol><small>
<?
		$counter = 1;
		foreach ($results as $result) {
			if ($counter > $pfb['maxfails'] && $entries > $pfb['maxfails']) {
				// To many errors stop displaying
				print (($entries - $pfb['maxfails']) . gettext(' more error(s)...'));
				break;
			}
			if ($counter == 1) {
				print ("<li>{$result}&emsp;<i class=\"fa fa-trash icon-pointer\" id=\"pfblockerngackicon\"
						title=\"" . gettext("Clear Failed Downloads") . "\" ></i></li>");
			} else {
				print ("<li>{$result}</li>");
			}
			$counter++;
		}
?>
		</small></ol>
	<?php else: ?>
		<!-- Print MaxMind version when failed downloads is null -->
		<p><?="&emsp;<small>MaxMind: {$maxver}</small>"?></p>
	<?php endif; ?>

	<!-- Print Status header -->
	<table class="table table-condensed">
		<thead>
			<th width=" 5%"><!-- Status icon    --></th>
			<th width="17%"><!-- IP/DNSBL count --></th>
			<th width="17%"><!-- Permit count   --></th>
			<th width="17%"><!-- Match count    --></th>
			<th width="17%"><!-- Native count   --></th>
			<th width="17%"><!-- Supp count     --></th>
			<th width="10%"><!-- Icons          --></th>
		</thead>
		<tbody>
		<tr>
			<td>
				<i class="<?=$pfb_status?>" title="<?=gettext($pfb_msg)?>"></i>
			</td>
			<td>
				<?php if ($dcount != 0): ?>
					<?=("<small>Deny:<strong>{$dcount}</strong></small>")?>
				<?php endif; ?>
			</td>
			<td>
				<?php if ($pcount != 0): ?>
					<?=("<small>Permit:<strong>{$pcount}</strong></small>")?>
				<?php endif; ?>
			</td>
			<td>
				<?php if ($mcount != 0): ?>
					<?=("<small>Match:<strong>{$mcount}</strong></small>")?>
				<?php endif; ?>
			<td>
				<?php if ($ncount != 0): ?>
					<?=("<small>Native:<strong>{$ncount}</strong></small>")?>
				<?php endif; ?>
			</td>
			<td>
				<?php if ($pfbsupp_cnt != 0): ?>
					<?=("<small>Supp:<strong>{$pfbsupp_cnt}</strong></small>")?>
				<?php endif; ?>
			</td>
			<td>
				<a target="_blank" href="pfblockerng/pfblockerng_log.php" title="<?=gettext("Click to open Logs tab")?>">
					<i class="fa fa-info-circle"></i></a>&nbsp;
			</td>
		</tr>

		<?php if ($pfb['dnsbl'] == 'on'): ?>	<!--Enable DNSBL widget statistics if enabled-->
		<tr>
			<td>
				<i class="<?=$dnsbl_status?>" title="<?=gettext($dnsbl_msg)?>"></i>
			</td>
			<td>
				<?=("<small>DNSBL:<strong>{$scount}</strong></small>")?>
			</td>
			<td></td><td></td><td></td><td></td>
			<td>
				<i class="fa fa-trash icon-pointer" id="pfblockerngdnsblclearicon" title="<?=gettext("Clear DNSBL Packets")?>"></i>
			</td>
		</tr>
		<?php endif; ?>
		</tbody>
	</table>

	<!-- Print main table header -->
	<table id="pfb-tbl" class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
		<thead>
			<tr>
				<th><?=gettext("Alias");?></th>
				<th title="The count can be a mixture of Single IPs or CIDR values"><?=gettext("Count");?></th>
				<th title="Packet Counts can be cleared by the pfSense filter_configure() function.
					Make sure Rule Descriptions start with 'pfB_'"><?=gettext("Packets");?></th>
				<th title="Last Update (Date/Time) of the Alias"><?=gettext("Updated");?></th>
				<th><?=$pfb['down']?>&nbsp;<?=$pfb['up']?></th>
			</tr>
		</thead>
		<tbody id="pfbNG-entries">
			<!-- Print main table body, subsequent refresh by javascript function -->
			<?=pfBlockerNG_get_table()?>
		</tbody>
	</table>
</form>

<!-- Widget customization settings wrench -->
</div>
<div id="widget-<?=$widgetname?>_panel-footer" class="panel-footer collapse">

<form action="/widgets/widgets/pfblockerng.widget.php" method="post" class="form-horizontal">
	<div class="form-group">
		<label class="col-sm-8 control-label">Enable Alias Table Popup</label>
		<div class="col-sm-2 checkbox">
			<label><input type="checkbox" name="pfb_popup" value="on"
				<?=($pfb['popup'] == "on" ? 'checked' : '')?> /></label>
		</div>
	</div>
	<div class="form-group">
		<label for="pfb_maxfails" class="col-sm-8 control-label">Enter number of download fails to display (default:3)</label>
		<div class="col-sm-2">
			<input type="number" name="pfb_maxfails" value="<?=$pfb['maxfails']?>"
				min="1" max="20" class="form-control" />
		</div>
	</div>
	<div class="form-group">
		<label for="pfb_maxpivot" class="col-sm-8 control-label">Enter 'max' Packets for Alerts Tab pivot (default:200)</label>
		<div class="col-sm-2">
			<input type="number" name="pfb_maxpivot" value="<?=$pfb['maxpivot']?>"
				min="1" max="500" class="form-control" />
		</div>
	</div>
	<div class="form-group">
		<label for="pfb_sortcolumn" class="col-sm-8 control-label">Enter Sort Column</label>
		<div class="col-sm-3">
			<select name="pfb_sortcolumn" class="form-control">
			<?php foreach (array('none' => 'None', 'alias' => 'Alias', 'count' => 'Count', 'packets' => 'Packets', 'updated' => 'Updated')
				as $sort => $sorttype):?>
				<option value="<?=$sort?>" <?=($sort == $pfb['sortcolumn'] ? 'selected' : '')?> ><?=$sorttype?></option>
			<?php endforeach;?>
			</select>
		</div>
	</div>
	<div class="form-group">
		<label for="pfb_sortdir" class="col-sm-8 control-label">Select sort direction</label>
		<div class="col-sm-3">
			<label><input type="radio" name="pfb_sortdir" id="pfb_sortdir_asc" value="asc"
				<?=($pfb['sortdir'] == "asc" ? 'checked' : '')?> />Ascending</label>
			<label><input type="radio" name="pfb_sortdir" id="pfb_sortdir_des" value="des"
				<?=($pfb['sortdir'] == "des" ? 'checked' : '')?> />Descending</label>
		</div>
	</div>
	<div class="form-group">
		<div class="col-sm-offset-6 col-sm-6">
			<button type="submit" name="pfb_submit" class="btn btn-primary">Save</button>
		</div>
	</div>
</form>

<script type="text/javascript">
//<![CDATA[
<!-- update every 10000 ms -->
var pfBlockerNGupdateDelay = 10000;

events.push(function() {

	// Keep popover open on mouseover
	// Reference: http://jsfiddle.net/wojtekkruszewski/zf3m7/22/
	var originalLeave = $.fn.popover.Constructor.prototype.leave;
	$.fn.popover.Constructor.prototype.leave = function(obj){
		var self = obj instanceof this.constructor ?
			obj : $(obj.currentTarget)[this.type](this.getDelegateOptions()).data('bs.' + this.type)
		var container, timeout;

		originalLeave.call(this, obj);

		if(obj.currentTarget) {
			container = $(obj.currentTarget).siblings('.popover')
			timeout = self.timeout;
			container.one('mouseenter', function(){
				//We entered the actual popover - call off the dogs
				clearTimeout(timeout);
				var pfBlockerNGupdateDelay = 90000;	// Increase pfBNG refresh interval
				clearInterval(pfBlockerNGtimer);
				pfBlockerNGtimer = setInterval('fetch_new_pfBlockerNGcounts()', pfBlockerNGupdateDelay);
				//Let's monitor popover content instead
				container.one('mouseleave', function(){
					$.fn.popover.Constructor.prototype.leave.call(self, self);
					var pfBlockerNGupdateDelay = 10000;	// Reset pfBNG refresh interval
					clearInterval(pfBlockerNGtimer);
					pfBlockerNGtimer = setInterval('fetch_new_pfBlockerNGcounts()', pfBlockerNGupdateDelay);
				});
			})
		}
	};
	$('body').popover({ selector: '[data-popover]', trigger: 'click hover', placement: 'right', delay: {show: 50, hide: 400}});

	$('[id^=pfblockerngackicon').click(function(event) {
		$('#pfblockerngack').val('true');
		$('#formicons').submit();
	});

	$('[id^=pfblockerngdnsblclearicon').click(function(event) {
		$('#pfblockerngdnsblclear').val('true');
		$('#formicons').submit();
	});
});

//]]>
</script>
