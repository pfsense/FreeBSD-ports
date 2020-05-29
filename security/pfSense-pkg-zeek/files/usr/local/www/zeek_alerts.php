<?php
/*
* zeek_alerts.php
* part of pfSense (https://www.pfSense.org/)
* Copyright (c) 2018-2020 Prosper Doko
* Copyright (c) 2020 Mark Overholser
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

require_once("/etc/inc/util.inc");
require_once("/etc/inc/functions.inc");
require_once("/etc/inc/pkg-utils.inc");
require_once("/etc/inc/globals.inc");
require_once("guiconfig.inc");

$pgtitle = array(gettext("Package"), gettext("Zeek"), gettext("Alerts"));
$shortcut_section = "zeek";
include("head.inc");

if ($savemsg) {
	print_info_box($savemsg);
}

$tab_array = array();
$tab_array[] = array(gettext("General"), false, "/pkg_edit.php?xml=zeek.xml&amp;id=0");
$tab_array[] = array(gettext("ZeekControl Config"), false, "/pkg_edit.php?xml=zeek_zeekctl.xml&amp;id=0");
$tab_array[] = array(gettext("Zeek Cluster"), false, "/pkg_edit.php?xml=zeek_cluster.xml&amp;id=0");
$tab_array[] = array(gettext("Zeek Scripts"), false, "/pkg.php?xml=zeek_script.xml");
$tab_array[] = array(gettext("Log Mgmt"), false, "/pkg_edit.php?xml=zeek_log.xml&amp;id=0");
$tab_array[] = array(gettext("Real Time Inspection"), true, "/zeek_alerts.php");
$tab_array[] = array(gettext("XMLRPC Sync"), false, "/pkg_edit.php?xml=zeek_sync.xml");

display_top_tabs($tab_array);
?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Filtering"); ?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<form id="paramsForm" name="paramsForm" method="post" action="">
				<table class="table table-hover table-condensed">
					<tbody>
						<tr>
							<td width="22%" valign="top" class="vncellreq">Log file to view:</td>
							<td width="78%" class="vtable">
								<select id="logfile">
									<option value="0">- Select -</option>
								</select>
								<br/>
								<span class="vexpl">
									<?=gettext("Choose log file to view")?>
								</span>
							</td>
						</tr>
						<tr>
							<td width="22%" valign="top" class="vncellreq">Max lines:</td>
							<td width="78%" class="vtable">
								<select name="maxlines" id="maxlines">
									<option value="10" selected="selected">10 lines</option>
									<option value="15">15 lines</option>
									<option value="20">20 lines</option>
									<option value="25">25 lines</option>
									<option value="100">100 lines</option>
									<option value="200">200 lines</option>
								</select>
								<br/>
								<span class="vexpl">
									<?=gettext("Max. lines to be displayed.")?>
								</span>
							</td>
						</tr>
					</tbody>
				</table>
			</form>
		</div>
	</div>

	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Log File Content"); ?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-hover table-condensed">
				<tbody>
					<tr><td>
						<table width="100%" border="0" cellspacing="20" cellpadding="10">
							<tbody id="zeekView">
								<tr><td></td></tr>
							</tbody>
						</table>
					</td></tr>
				</tbody>
			</table>
		</div>
	</div>
</div>
<?php include("foot.inc"); ?>
<!-- Function to call programs logs -->
<script type="text/javascript">

$( "#logfile" ).change(function() {
	showLog('zeekView', 'zeek_alert_data.php', 'zeek');
});

function updateSelect() {
	var x = 'x='+$('#logfile').length;
	jQuery.ajax({
		type: "POST",
		url: "select_box_file.php",
		data: x,
		success: function(html) {
			if (html) {
				$("#logfile").html(html);
			}
		},
	});
}

function showLog(content, url, program) {
	jQuery.ajax(url,
		{
			type: 'post',
			data: {
				logfile: $('#logfile').val(),
				maxlines: $('#maxlines').val(),
				program: program,
				content: content
			},
			success: function(ret) {
				$('#' + content).html(ret);
			}
		});
}

function updateAllLogs() {
	updateSelect();
	showLog('zeekView', 'zeek_alert_data.php', 'zeek');
	setTimeout(updateAllLogs, 10000);
}

events.push(function() {
	updateAllLogs();
});

</script>
