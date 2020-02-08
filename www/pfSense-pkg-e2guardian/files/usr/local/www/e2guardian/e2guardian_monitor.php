<?php
/*
 * e2guardian_monitor.php
 * 
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2015 Rubicon Communications, LLC (Netgate)
 * Copyright (C) 2012-2017 Marcello Coutinho
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

$pgtitle = array(gettext("Package"), gettext("E2guardian"), gettext("Monitor"));
$shortcut_section = "e2guardian";
include("head.inc");

if ($savemsg) {
	print_info_box($savemsg);
}

$tab_array = array();
	$tab_array[] = array(gettext("Daemon"), false, "/pkg_edit.php?xml=e2guardian.xml");
	$tab_array[] = array(gettext("General"), false, "/pkg_edit.php?xml=e2guardian/e2guardian_config.xml");
	$tab_array[] = array(gettext("Limits"), false, "/pkg_edit.php?xml=e2guardian/e2guardian_limits.xml");
	$tab_array[] = array(gettext("Blacklist"), false, "/pkg_edit.php?xml=e2guardian/e2guardian_blacklist.xml");
	$tab_array[] = array(gettext("ACLs"), false, "/pkg.php?xml=e2guardian/e2guardian_site_acl.xml");
	$tab_array[] = array(gettext("LDAP"), false, "/pkg.php?xml=e2guardian/e2guardian_ldap.xml");
	$tab_array[] = array(gettext("Groups"), false, "/pkg.php?xml=e2guardian/e2guardian_groups.xml");
	$tab_array[] = array(gettext("Users"), false, "/pkg_edit.php?xml=e2guardian/e2guardian_users.xml");
	$tab_array[] = array(gettext("IPs"), false, "/pkg_edit.php?xml=e2guardian/e2guardian_ips.xml");
	$tab_array[] = array(gettext("Real Time"), true, "/e2guardian/e2guardian_monitor.php");
	$tab_array[] = array(gettext("Report and log"), false, "/pkg_edit.php?xml=e2guardian/e2guardian_log.xml");
	$tab_array[] = array(gettext("Sync"), false, "/pkg_edit.php?xml=e2guardian/e2guardian_sync.xml");
	$tab_array[] = array(gettext("Help"), false, "/e2guardian/e2guardian_about.php");

display_top_tabs($tab_array);

if (is_array($config['installedpackages']['e2guardianlog'])) {
        $e2glog = $config['installedpackages']['e2guardianlog']['config'][0];
} else {
        $e2glog = array();
}

?>

<div class="panel panel-default">
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("Filtering"); ?></h2></div>
	<div class="panel-body">
		<div class="table">
			<form id="paramsForm" name="paramsForm" method="post" action="">
			<table class="table table-striped table-hover table-condensed">
				<tbody>
				<tr>
					<td width="22%" valign="top" class="vncellreq">Max lines:</td>
					<td width="78%" class="vtable">
						<select name="maxlines" id="maxlines">
							<option value="5">5 lines</option>
							<option value="10">10 lines</option>
							<option value="15">15 lines</option>
							<option value="20" selected="selected">20 lines</option>
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
					<td width="22%" valign="top" class="vncellreq">Update Interval:</td>
					<td width="78%" class="vtable">
						<select name="interval" id="interval">
							<option value="1000">1 second</option>
							<option value="3000" selected="selected">3 seconds</option>
							<option value="5000">5 seconds</option>
							<option value="10000">10 seconds</option>
							<option value="15000">15 seconds</option>
							<option value="20000">20 seconds</option>
						</select>
						<br/>
						<span class="vexpl">
							<?=gettext("How often log entries are updated.")?>
						</span>
					</td>
				</tr>
				<?php if($e2glog['logfileformat'] == 1 || $e2glog['logfileformat'] == 4 || $e2glog['logdeniedcgi'] == "on") {?>
				<tr>
				<td width="22%" valign="top" class="vncellreq">Erro to show:</td>
                                        <td width="78%" class="vtable">
                                                <select name="error" id="error">
                                                        <option value="reason">Reason</option>
                                                        <option value="detailed" selected="selected">Detailed info</option>
                                                </select>
                                                <br/>
                                                <span class="vexpl">
                                                        <?=gettext("Select denied info to show while using logs in E2g format.")?>
                                                </span>
                                        </td>
				</tr>
				<?php }?>
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
				<tr>
					<td width="22%" valign="top" class="vncellreq">String filter limit:</td>
					<td width="78%" class="vtable">
						<input name="readlines" type="text" class="formfld search" id="readlines" size="50" value="" />
						<br/>
						<span class="vexpl">
							<?=gettext("Enter the number of log entries that should be checked to match the string filter")?><br/>
						</span>
					</td>
				</tr>
				</tbody>
			</table>
			</form>
		</div>
	</div>
	</div>
	</div>
	<div class="panel panel-default" style='margin:0 auto;width:97%'> 
	<div class="panel-heading"><h2 class="panel-title"><?=gettext("E2guardian Access Table"); ?></h2></div>
	<div class="panel-body">
		<div class="">
			<table class="table table-responsive table-striped table-hover table-condensed"
				<tbody>
				<tr><td>
					<table class="table" xclaxss="tabcont" width="100%" border="0" cellspacing="2" cellpadding="0">
						<thead id="e2gViewhead"><tr>
						</tr></thead>
						<tbody id="e2gView">
						<tr><td></td></tr>
						</tbody>
					</table>
				</td></tr>
				</tbody>
			</table>
		</div>
	</div>
	</div>
	<BR>
	<?php if($e2glog['logfileformat'] == 3 && $e2glog['logdeniedcgi'] == "on") {?>	
        <div class="panel panel-default" style='margin:0 auto;width:97%'>
        <div class="panel-heading"><h2 class="panel-title"><?=gettext("Detailed denied log"); ?></h2></div>
        <div class="panel-body">
                <div class="">
                        <table class="table table-responsive table-striped table-hover table-condensed"
                                <tbody>
                                <tr><td>
                                        <table class="table" xclaxss="tabcont" width="100%" border="0" cellspacing="2" cellpadding="0">
                                                <thead id="e2gerrorhead"><tr>
                                                </tr></thead>
                                                <tbody id="e2gerror">
                                                <tr><td></td></tr>
                                                </tbody>
                                        </table>
                                </td></tr>
                                </tbody>
                        </table>
                </div>
        </div>
        </div>
        <BR>
	<?php } ?>

<!-- Function to call programs logs -->
<script type="text/javascript">
//<![CDATA[
document.timeoutVal = 5000
function showLog(content, url, program) {
	jQuery.ajax(url,
		{
		type: 'post',
		data: {
			maxlines: $('#maxlines').val(),
			readlines: $('#readlines').val(),
			strfilter: $('#strfilter').val(),
			error: $('#error').val(),
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
	showLog('e2gView', 'e2guardian_monitor_data.php', 'access');
    showLog('e2gerror', 'e2guardian_monitor_data.php', 'e2gerror');
	document.timeoutVal = $('#interval').val();
	setTimeout(updateAllLogs, document.timeoutVal);
}

events.push(function() {
//alert(document.timeoutVal);
	updateAllLogs();
});
//]]>
</script>
<?php include("foot.inc"); ?>


