<?php
/*
 * status_andwatch.php
 *
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (c) 2025, Denny Page
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
require_once("andwatch.inc");

$active_interfaces = array_filter(explode(',', config_get_path('installedpackages/andwatch/active_interfaces', '')));

if ($_REQUEST['if']) {
	$if = htmlspecialchars($_REQUEST['if']);
} else {
	$if = $active_interfaces[0];
}
if ($_REQUEST['all']) {
	$all = htmlspecialchars($_REQUEST['all']);
}

// Build the tab array
$tab_array = array();
foreach ($active_interfaces as $ifname) {
	$friendly_ifname = convert_friendly_interface_to_friendly_descr($ifname);
	$active = ($ifname == $if);
	$tab_array[] = array($friendly_ifname, $active, "status_andwatch.php?if={$ifname}");
	if ($active) {
		$active_name = $friendly_ifname;
	}
}

$pgtitle = array(gettext('Status'), gettext('ANDwatch Database'), $active_name);
$pglinks = array("", "status_andwatch.php", "@self");
include("head.inc");
display_top_tabs($tab_array, false, 'pills');

if ($all) {
	$panel_title = gettext('Historical Address Records');
} else {
	$panel_title = gettext('Current Address Records');
}

// Get the entries
$entries = andwatch_query_interface($if, $all);
?>


<div class="panel panel-default" id="search-panel">
	<div class="panel-heading">
		<h2 class="panel-title">
			<?=gettext('Search')?>
			<span class="widget-heading-icon pull-right">
				<a data-toggle="collapse" href="#search-panel_panel-body">
					<i class="fa-solid fa-plus-circle"></i>
				</a>
			</span>
		</h2>
	</div>
	<div id="search-panel_panel-body" class="panel-body collapse in">
		<div class="form-group">
			<label class="col-sm-2 control-label">
				<?=gettext('Search Term')?>
			</label>
			<div class="col-sm-5"><input class="form-control" name="searchstr" id="searchstr" type="text"></div>
			<div class="col-sm-2">
				<select id="where" class="form-control">
					<option value="1" selected><?=gettext('Any Field')?></option>
					<option value="2"><?=gettext('Hostname')?></option>
					<option value="3"><?=gettext('IP Address')?></option>
					<option value="4"><?=gettext('MAC Address')?></option>
					<option value="5"><?=gettext('MAC Organization')?></option>
				</select>
			</div>
			<div class="col-sm-3">
				<a id="btnsearch" title="<?=gettext("Search")?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-search icon-embed-btn"></i><?=gettext("Search")?></a>
				<a id="btnclear" title="<?=gettext("Clear")?>" class="btn btn-info btn-sm"><i class="fa-solid fa-undo icon-embed-btn"></i><?=gettext("Clear")?></a>
			</div>
			<div class="col-sm-5 col-sm-offset-2">
				<span class="help-block"><?=gettext('Enter a search string or regular expression to filter entries.')?></span>
			</div>
		</div>
	</div>
</div>


<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=$panel_title?></h2></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-hover table-condensed sortable-theme-bootstrap" data-sortable>
			<thead>
			<tr class="text-nowrap">
				<th><?=gettext("DateTime")?></th>
				<th><?=gettext("Age")?></th>
				<th><?=gettext("Hostname")?></th>
				<th><?=gettext("IP Address")?></th>
				<th><?=gettext("MAC Address")?></th>
				<th><?=gettext("MAC Organization")?></th>
			</tr>
			</thead>
			<tbody id="addrlist">
			<?php if (count($entries)) : ?>
			<?php foreach ($entries as $entry): ?>
			<tr class="text-nowrap">
				<td><?=htmlspecialchars($entry['datetime'])?></td>
				<td><?=htmlspecialchars($entry['age'])?></td>
				<td><?=htmlspecialchars($entry['hostname'])?></td>
				<td><?=htmlspecialchars($entry['ipaddr'])?></a></td>
				<td><?=htmlspecialchars($entry['hwaddr'])?></a></td>
				<td><?=htmlspecialchars($entry['org'])?></td>
			</tr>
			<?php endforeach; ?>
			<?php else: ?>
			<tr>
				<td colspan="6"><?=gettext("No entries to display")?></td>
			</tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<div>
<?php if ($_REQUEST['all']): ?>
	<a class="btn btn-info btn-sm" href="status_andwatch.php?if=<?=$if?>&all=0"><i class="fa-solid fa-minus-circle icon-embed-btn"></i><?=gettext("Show Current Records Only")?></a>
<?php else: ?>
	<a class="btn btn-info btn-sm" href="status_andwatch.php?if=<?=$if?>&all=1"><i class="fa-solid fa-plus-circle icon-embed-btn"></i><?=gettext("Show Historical Records")?></a>
<?php endif; ?>
</div>



<script type="text/javascript">
//<![CDATA[
events.push(function() {
	// Make these controls plain buttons
	$("#btnsearch").prop('type', 'button');
	$("#btnclear").prop('type', 'button');

	// Search for a term in the entry name and/or dn
	$("#btnsearch").click(function() {
		var searchstr = $('#searchstr').val().toLowerCase();
		var table = $("#addrlist");
		var where = $('#where').val();

		// Trim on values where a space doesn't make sense
		if ((where >= 2) && (where <= 5)) {
			searchstr = searchstr.trim();
		}

		table.find('tr').each(function (i) {
			var $tds     = $(this).find('td');
			var $popover = $($.parseHTML($tds.eq(2).attr('data-content')));

			var hostname = $tds.eq(2).text().trim().toLowerCase();
			var ipaddr   = $tds.eq(3).text().trim().toLowerCase();
			var macaddr  = $tds.eq(4).text().trim().toLowerCase();
			var macorg   = $tds.eq(5).text().trim().toLowerCase();

			regexp = new RegExp(searchstr);
			if (searchstr.length > 0) {
				if (!(regexp.test(hostname) && ((where == 2) || (where == 1))) &&
				    !(regexp.test(ipaddr)   && ((where == 3) || (where == 1))) &&
				    !(regexp.test(macaddr)  && ((where == 4) || (where == 1))) &&
				    !(regexp.test(macorg)   && ((where == 5) || (where == 1)))
				    ) {
					$(this).hide();
				} else {
					$(this).show();
				}
			} else {
				$(this).show(); // A blank search string shows all
			}
		});
	});

	// Clear the search term and unhide all rows (that were hidden during a previous search)
	$("#btnclear").click(function() {
		var table = $("#addrlist");

		$('#searchstr').val("");
		$('#where option[value="1"]').prop('selected', true);

		table.find('tr').each(function (i) {
			$(this).show();
		});
	});

	// Hitting the enter key will do the same as clicking the search button
	$("#searchstr").on("keyup", function (event) {
		if (event.keyCode == 13) {
			$("#btnsearch").get(0).click();
		}
	});
});
//]]>
</script>


<?php include("foot.inc"); ?>
