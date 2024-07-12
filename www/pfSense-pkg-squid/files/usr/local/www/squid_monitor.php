<?php
/*
 * squid_monitor.php
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015-2024 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2012-2014 Marcello Coutinho
 * Copyright (C) 2012-2014 Carlos Cesario <carloscesario@gmail.com>
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

$pgtitle = array(gettext("Package"), gettext("Squid"), gettext("Monitor"));
$shortcut_section = "squid";
include("head.inc");

if ($savemsg) {
	print_info_box($savemsg);
}

$tab_array = array();
if ($_REQUEST["menu"] == "reverse") {
	$tab_array[] = array(gettext("General"), false, "/pkg_edit.php?xml=squid_reverse_general.xml&amp;id=0");
	$tab_array[] = array(gettext("Web Servers"), false, "/pkg.php?xml=squid_reverse_peer.xml");
	$tab_array[] = array(gettext("Mappings"), false, "/pkg.php?xml=squid_reverse_uri.xml");
	$tab_array[] = array(gettext("Redirects"), false, "/pkg.php?xml=squid_reverse_redir.xml");
	$tab_array[] = array(gettext("Real Time"), true, "/squid_monitor.php?menu=reverse");
	$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=squid_reverse_sync.xml");
} else {
	$tab_array[] = array(gettext("General"), false, "/pkg_edit.php?xml=squid.xml&amp;id=0");
	$tab_array[] = array(gettext("Remote Cache"), false, "/pkg.php?xml=squid_upstream.xml");
	$tab_array[] = array(gettext("Local Cache"), false, "/pkg_edit.php?xml=squid_cache.xml&amp;id=0");
	$tab_array[] = array(gettext("Antivirus"), false, "/pkg_edit.php?xml=squid_antivirus.xml&amp;id=0");
	$tab_array[] = array(gettext("ACLs"), false, "/pkg_edit.php?xml=squid_nac.xml&amp;id=0");
	$tab_array[] = array(gettext("Traffic Mgmt"), false, "/pkg_edit.php?xml=squid_traffic.xml&amp;id=0");
	$tab_array[] = array(gettext("Authentication"), false, "/pkg_edit.php?xml=squid_auth.xml&amp;id=0");
	$tab_array[] = array(gettext("Users"), false, "/pkg.php?xml=squid_users.xml");
	$tab_array[] = array(gettext("Real Time"), true, "/squid_monitor.php");
	$tab_array[] = array(gettext("Status"), false, "/status_squid.php");
	$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=squid_sync.xml");
}
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
					<td width="22%" valign="top" class="vncellreq">Max lines:</td>
					<td width="78%" class="vtable">
						<select name="maxlines" id="maxlines">
							<option value="5">5 lines</option>
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
				<tr>
					<td width="22%" valign="top" class="vncellreq">String filter:</td>
					<td width="78%" class="vtable">
						<input name="strfilter" type="text" class="formfld search" id="strfilter" size="50" value="" />
						<br/>
						<span class="vexpl">
							<?=gettext("Enter a grep-like string/pattern to filter the log entries.")?><br/>
							<?=gettext("E.g.: username, IP address, URL.")?><br/>
							<?=gettext("Use <strong>!</strong> to invert the sense of matching (to select non-matching lines).")?>
						</span>
					</td>
				</tr>
				</tbody>
			</table>
			</form>
		</div>
	</div>

	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Squid Access Table"); ?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-hover table-condensed">
				<tbody>
				<tr><td>
					<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
						<thead><tr>
							<td colspan="6" class="listtopic" align="center"><?=gettext("Squid - Access Logs"); ?></td>
						</tr></thead>
						<tbody id="squidView">
						<tr><td></td></tr>
						</tbody>
					</table>
				</td></tr>
				</tbody>
			</table>
		</div>
	</div>

	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Squid Cache Table"); ?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-hover table-condensed">
				<tbody>
				<tr><td>
					<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
						<thead><tr>
							<td colspan="2" class="listtopic" align="center"><?=gettext("Squid - Cache Logs"); ?></td>
						</tr></thead>
						<tbody id="squidCacheView">
						<tr><td></td></tr>
						</tbody>
					</table>
				</td></tr>
				</tbody>
			</table>
		</div>
	</div>

<?php if ($_REQUEST["menu"] != "reverse") {?>
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("SquidGuard Table"); ?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-hover table-condensed">
				<tbody>
				<tr><td>
					<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
						<thead><tr>
							<td colspan="5" class="listtopic" align="center"><?=gettext("SquidGuard Logs"); ?></td>
						</tr></thead>
						<tbody id="sguardView">
						<tr><td></td></tr>
						</tbody>
					</table>
				</td></tr>
				</tbody>
			</table>
		</div>
	</div>

	<div class="panel-heading"><h2 class="panel-title"><?=gettext("C-ICAP Virus Table"); ?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-hover table-condensed">
				<tbody>
				<tr><td>
					<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
						<thead><tr>
							<td colspan="6" class="listtopic" align="center"><?=gettext("C-ICAP - Virus Logs"); ?></td>
						</tr></thead>
						<tbody id="CICIAPVirusView">
						<tr><td></td></tr>
						</tbody>
					</table>
				</td></tr>
				</tbody>
			</table>
		</div>
	</div>

	<div class="panel-heading"><h2 class="panel-title"><?=gettext("C-ICAP Access Table"); ?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-hover table-condensed">
				<tbody>
				<tr><td>
					<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
						<thead><tr>
							<td colspan="2" class="listtopic" align="center"><?=gettext("C-ICAP - Access Logs"); ?></td>
						</tr></thead>
						<tbody id="CICAPAccessView">
						<tr><td></td></tr>
						</tbody>
					</table>
				</td></tr>
				</tbody>
			</table>
		</div>
	</div>

	<div class="panel-heading"><h2 class="panel-title"><?=gettext("C-ICAP Server Table"); ?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-hover table-condensed">
				<tbody>
				<tr><td>
					<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
						<thead><tr>
							<td colspan="2" class="listtopic" align="center"><?=gettext("C-ICAP - Server Logs"); ?></td>
						</tr></thead>
						<tbody id="CICAPServerView">
						<tr><td></td></tr>
						</tbody>
					</table>
				</td></tr>
				</tbody>
			</table>
		</div>
	</div>

	<div class="panel-heading"><h2 class="panel-title"><?=gettext("freshclam Table"); ?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-hover table-condensed">
				<tbody>
				<tr><td>
					<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
						<thead><tr>
							<td colspan="1" class="listtopic" align="center"><?=gettext("ClamAV - freshclam Logs"); ?></td>
						</tr></thead>
						<tbody id="freshclamView">
						<tr><td></td></tr>
						</tbody>
					</table>
				</td></tr>
				</tbody>
			</table>
		</div>
	</div>

	<div class="panel-heading"><h2 class="panel-title"><?=gettext("clamd Table"); ?></h2></div>
	<div class="panel-body">
		<div class="table-responsive">
			<table class="table table-hover table-condensed">
				<tbody>
				<tr><td>
					<table class="tabcont" width="100%" border="0" cellspacing="0" cellpadding="0">
						<thead><tr>
							<td colspan="1" class="listtopic" align="center"><?=gettext("ClamAV - clamd Logs"); ?></td>
						</tr></thead>
						<tbody id="clamdView">
						<tr><td></td></tr>
						</tbody>
					</table>
				</td></tr>
				</tbody>
			</table>
		</div>
	</div>
<?php }?>
</div>

<!-- Function to call programs logs -->
<script type="text/javascript">
//<![CDATA[
function showLog(content, url, program) {
	jQuery.ajax(url,
		{
		type: 'post',
		data: {
			maxlines: $('#maxlines').val(),
			strfilter: $('#strfilter').val(),
			program: program,
			content: content
			},
		success: function(ret){
			$('#' + content).html(ret);
			}
		}
		);
}

function updateAllLogs() {
	showLog('squidView', 'squid_monitor_data.php', 'squid');
	showLog('squidCacheView', 'squid_monitor_data.php', 'squid_cache');
<?php if ($_REQUEST["menu"] != "reverse") {?>
	showLog('sguardView', 'squid_monitor_data.php', 'sguard');
	showLog('CICIAPVirusView', 'squid_monitor_data.php', 'cicap_virus');
	showLog('CICAPAccessView', 'squid_monitor_data.php', 'cicap_access');
	showLog('CICAPServerView', 'squid_monitor_data.php', 'cicap_server');
	showLog('freshclamView', 'squid_monitor_data.php', 'freshclam');
	showLog('clamdView', 'squid_monitor_data.php', 'clamd');
<?php }?>
	setTimeout(updateAllLogs, 5000);
}

events.push(function() {
	updateAllLogs();
});
//]]>
</script>
<?php include("foot.inc"); ?>
